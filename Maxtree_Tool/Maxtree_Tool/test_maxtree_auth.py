"""
Standalone Python Test Suite for Step 05: Maxtree Authentication, License Manager & Access Control.
Can be executed directly via Python (e.g. `python3 test_maxtree_auth.py`).
"""
import sys
import time
import unittest
from unittest.mock import MagicMock, patch
from pathlib import Path

# Add plugin folder to sys.path
PLUGIN_DIR = Path(__file__).resolve().parent
if str(PLUGIN_DIR) not in sys.path:
    sys.path.insert(0, str(PLUGIN_DIR))

import maxtree_api_client as api
import maxtree_license_manager as mgr

# Mock pymxs for standalone execution outside 3ds Max
if "pymxs" not in sys.modules:
    import types
    pymxs_mod = types.ModuleType("pymxs")
    pymxs_mod.runtime = MagicMock()
    sys.modules["pymxs"] = pymxs_mod


class TestMaxtreeApiClient(unittest.TestCase):

    def setUp(self):
        # Explicit development base URL for tests
        self.client = api.MaxtreeApiClient(base_url="http://127.0.0.1:8000")

    def test_missing_base_url_raises_config_error_in_production(self):
        with patch.dict("os.environ", {"MAXTREE_API_BASE_URL": "", "MAXTREE_ENV": "production"}):
            client = api.MaxtreeApiClient(base_url="")
            with self.assertRaises(api.ConfigError):
                client.login("user@example.com", "secret")

    def test_insecure_http_rejected_for_non_loopback_domain(self):
        client = api.MaxtreeApiClient(base_url="http://api.academy.com")
        with self.assertRaises(api.ConfigError):
            client.login("user@example.com", "secret")

    @patch("urllib.request.urlopen")
    def test_login_success_parses_response(self, mock_urlopen):
        mock_response = MagicMock()
        mock_response.read.return_value = json_bytes({
            "status": "success",
            "token": "1|test_token_hash",
            "token_type": "Bearer",
            "user": {"id": 1, "name": "John Doe", "email": "user@example.com"},
            "license": {"is_active": True, "expires_at": None}
        })
        mock_response.__enter__.return_value = mock_response
        mock_urlopen.return_value = mock_response

        res = self.client.login("user@example.com", "correct_password")
        self.assertEqual(res["status"], "success")
        self.assertEqual(res["token"], "1|test_token_hash")
        self.assertTrue(res["license"]["is_active"])

    @patch("urllib.request.urlopen")
    def test_login_invalid_credentials_raises_error(self, mock_urlopen):
        import urllib.error
        http_err = urllib.error.HTTPError(
            url="http://127.0.0.1:8000/api/v1/maxtree/auth/login",
            code=401,
            msg="Unauthorized",
            hdrs={},
            fp=MagicMock(read=lambda: json_bytes({
                "status": "error",
                "error_code": "INVALID_CREDENTIALS",
                "message": "The provided credentials do not match our records."
            }))
        )
        mock_urlopen.side_effect = http_err

        with self.assertRaises(api.InvalidCredentialsError):
            self.client.login("user@example.com", "wrong_password")

    @patch("urllib.request.urlopen")
    def test_login_inactive_license_raises_error(self, mock_urlopen):
        import urllib.error
        http_err = urllib.error.HTTPError(
            url="http://127.0.0.1:8000/api/v1/maxtree/auth/login",
            code=403,
            msg="Forbidden",
            hdrs={},
            fp=MagicMock(read=lambda: json_bytes({
                "status": "error",
                "error_code": "LICENSE_INACTIVE",
                "message": "Your Maxtree license is inactive."
            }))
        )
        mock_urlopen.side_effect = http_err

        with self.assertRaises(api.LicenseInactiveError):
            self.client.login("unlicensed@example.com", "password")

    @patch("urllib.request.urlopen")
    def test_get_status_success(self, mock_urlopen):
        mock_response = MagicMock()
        mock_response.read.return_value = json_bytes({
            "status": "success",
            "authorized": True,
            "user": {"id": 1, "email": "user@example.com"},
            "license": {"is_active": True}
        })
        mock_response.__enter__.return_value = mock_response
        mock_urlopen.return_value = mock_response

        res = self.client.get_status("1|test_token_hash")
        self.assertTrue(res["authorized"])

    def test_get_status_without_token_raises_error(self):
        with self.assertRaises(api.MaxtreeApiError):
            self.client.get_status("")


class TestMaxtreeLicenseManager(unittest.TestCase):

    def setUp(self):
        mgr.reset_manager_for_testing()
        self.manager = mgr.get_manager()

    def test_initial_state_is_unauthenticated(self):
        self.assertEqual(self.manager.state, mgr.UNAUTHENTICATED)
        self.assertFalse(self.manager.is_authorized())
        self.assertIsNone(self.manager.token)

    def test_login_flow_transitions_to_active(self):
        seq = self.manager.start_authentication()
        self.assertEqual(self.manager.state, mgr.AUTHENTICATING)

        user_data = {"id": 1, "email": "user@example.com"}
        lic_data = {"is_active": True, "expires_at": None}

        ok = self.manager.handle_login_success("token_abc", user_data, lic_data, seq)
        self.assertTrue(ok)
        self.assertEqual(self.manager.state, mgr.LICENSE_ACTIVE)
        self.assertTrue(self.manager.is_authorized())
        self.assertEqual(self.manager.token, "token_abc")

    def test_unlicensed_login_transitions_to_authenticated_unlicensed(self):
        seq = self.manager.start_authentication()
        user_data = {"id": 2, "email": "unlicensed@example.com"}
        lic_data = {"is_active": False, "expires_at": None}

        ok = self.manager.handle_login_success("token_xyz", user_data, lic_data, seq)
        self.assertTrue(ok)
        self.assertEqual(self.manager.state, mgr.AUTHENTICATED_UNLICENSED)
        self.assertFalse(self.manager.is_authorized())

    def test_out_of_order_async_response_is_discarded(self):
        seq1 = self.manager.start_authentication()
        seq2 = self.manager.start_authentication()  # Second click

        # Late response for seq1 arrives
        ok = self.manager.handle_login_success("stale_token", {}, {"is_active": True}, seq1)
        self.assertFalse(ok)
        self.assertNotEqual(self.manager.token, "stale_token")

    def test_authorization_freshness_boundary(self):
        seq = self.manager.start_authentication()
        self.manager.handle_login_success("token_123", {"id": 1}, {"is_active": True}, seq)
        self.assertTrue(self.manager.is_authorized())

        # Advance monotonic clock past freshness boundary (95s > 90s limit)
        with patch("time.monotonic", return_value=time.monotonic() + 95.0):
            self.assertFalse(self.manager.is_authorized())
            self.assertEqual(self.manager.state, mgr.TOKEN_EXPIRED)

    def test_logout_clears_token_and_resets_state(self):
        seq = self.manager.start_authentication()
        self.manager.handle_login_success("token_to_clear", {"id": 1}, {"is_active": True}, seq)
        self.assertTrue(self.manager.is_authorized())

        self.manager.logout()
        self.assertEqual(self.manager.state, mgr.UNAUTHENTICATED)
        self.assertIsNone(self.manager.token)
        self.assertFalse(self.manager.is_authorized())


class TestCoreExecutionGuards(unittest.TestCase):

    def setUp(self):
        mgr.reset_manager_for_testing()
        self.manager = mgr.get_manager()

    def test_block_runtime_insert_rejected_when_unauthorized(self):
        import block_runtime
        entry = {"path": "/fake/path/model.max"}
        with self.assertRaises(mgr.AuthorizationError):
            block_runtime.insert(None, entry)

    def test_collection_store_mutations_rejected_when_unauthorized(self):
        import collection_store
        with self.assertRaises(mgr.AuthorizationError):
            collection_store.create_collection("New Collection", {})
        with self.assertRaises(mgr.AuthorizationError):
            collection_store.rename_collection("cid_123", "Renamed")
        with self.assertRaises(mgr.AuthorizationError):
            collection_store.remove_collection("cid_123")

    def test_block_catalog_mutations_rejected_when_unauthorized(self):
        import block_catalog
        entry = {"_ref_kind": "base", "collection": "col1", "path": "/fake/model.max"}
        with self.assertRaises(mgr.AuthorizationError):
            block_catalog.edit(entry, {"display_name": "New Name"})
        with self.assertRaises(mgr.AuthorizationError):
            block_catalog.remove(entry)

    def test_asset_store_mutations_rejected_when_unauthorized(self):
        import asset_store
        with self.assertRaises(mgr.AuthorizationError):
            asset_store.create("New Asset Library")
        with self.assertRaises(mgr.AuthorizationError):
            asset_store.put("lib_123", [])

    def test_authorized_session_allows_guard_to_pass(self):
        seq = self.manager.start_authentication()
        self.manager.handle_login_success("valid_token", {"id": 1}, {"is_active": True}, seq)
        
        # When authorized, require_authorization should return without error
        try:
            mgr.require_authorization("test_op")
        except mgr.AuthorizationError:
            self.fail("require_authorization raised AuthorizationError unexpectedly for authorized session")

    def test_logout_immediately_blocks_execution(self):
        seq = self.manager.start_authentication()
        self.manager.handle_login_success("valid_token", {"id": 1}, {"is_active": True}, seq)
        self.assertTrue(self.manager.is_authorized())

        self.manager.logout()
        with self.assertRaises(mgr.AuthorizationError):
            mgr.require_authorization("test_op")


def json_bytes(obj):
    import json
    return json.dumps(obj).encode("utf-8")


if __name__ == "__main__":
    unittest.main()
