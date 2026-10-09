"""
Maxtree API Client module for 3ds Max Python Plugin.
Handles HTTPS authentication, license verification, and session logout requests
with the Academy Laravel API backend.
"""
import os
import json
import ssl
import urllib.request
import urllib.error
import urllib.parse
from pathlib import Path

# Exception Hierarchy
class MaxtreeApiError(Exception):
    """Base exception for all Maxtree API client errors."""
    def __init__(self, message, error_code=None, status_code=None):
        super().__init__(message)
        self.message = message
        self.error_code = error_code or "API_ERROR"
        self.status_code = status_code

    def __str__(self):
        return f"[{self.error_code}] {self.message}"

class InvalidCredentialsError(MaxtreeApiError):
    """Raised when authentication credentials are invalid (401)."""
    def __init__(self, message="Invalid email or password."):
        super().__init__(message, error_code="INVALID_CREDENTIALS", status_code=401)

class AccountDisabledError(MaxtreeApiError):
    """Raised when user account is disabled (403)."""
    def __init__(self, message="Your account is disabled."):
        super().__init__(message, error_code="ACCOUNT_DISABLED", status_code=403)

class LicenseNotFoundError(MaxtreeApiError):
    """Raised when account has no Maxtree license record (403)."""
    def __init__(self, message="No Maxtree license record found for this account."):
        super().__init__(message, error_code="LICENSE_NOT_FOUND", status_code=403)

class LicenseInactiveError(MaxtreeApiError):
    """Raised when Maxtree license is inactive (403)."""
    def __init__(self, message="Your Maxtree license is inactive."):
        super().__init__(message, error_code="LICENSE_INACTIVE", status_code=403)

class LicenseExpiredError(MaxtreeApiError):
    """Raised when Maxtree license has expired (403)."""
    def __init__(self, message="Your Maxtree license has expired."):
        super().__init__(message, error_code="LICENSE_EXPIRED", status_code=403)

class RateLimitedError(MaxtreeApiError):
    """Raised when rate limit is exceeded (429)."""
    def __init__(self, message="Too many login attempts. Please wait a minute and try again."):
        super().__init__(message, error_code="RATE_LIMITED", status_code=429)

class NetworkError(MaxtreeApiError):
    """Raised when a network timeout or connection failure occurs."""
    def __init__(self, message="Failed to connect to authentication server."):
        super().__init__(message, error_code="NETWORK_ERROR")

class ConfigError(MaxtreeApiError):
    """Raised when API client configuration is invalid or missing."""
    def __init__(self, message="API Base URL configuration missing."):
        super().__init__(message, error_code="CONFIG_ERROR")


def load_configured_base_url():
    """
    Retrieves the API Base URL from:
    1. Environment variable MAXTREE_API_BASE_URL
    2. Settings file (settings/config.json)
    3. Default development loopback URL if MAXTREE_ENV is 'development' or 'local'
    """
    env_url = os.environ.get("MAXTREE_API_BASE_URL", "").strip()
    if env_url:
        return env_url

    # Check local settings file beside plugin
    plugin_dir = Path(__file__).resolve().parent
    settings_file = plugin_dir / "settings" / "config.json"
    if settings_file.is_file():
        try:
            data = json.loads(settings_file.read_text(encoding="utf-8"))
            if isinstance(data, dict) and data.get("api_base_url"):
                return data["api_base_url"].strip()
        except Exception:
            pass

    # Development override explicit check
    env_name = os.environ.get("MAXTREE_ENV", "").lower()
    if env_name in ("development", "local", "dev"):
        return "http://127.0.0.1:8000"

    # Default production domain
    return "https://the-technical-office-for-landscaping-works.com"


class MaxtreeApiClient:
    """HTTPS API client for Maxtree authentication and license verification."""

    def __init__(self, base_url=None, timeout=15):
        if base_url is None:
            base_url = load_configured_base_url()
        self._base_url = (base_url or "").rstrip("/")
        self.timeout = timeout
        self.ssl_context = ssl.create_default_context()

    @property
    def base_url(self):
        return self._base_url

    def _ensure_base_url(self):
        if not self._base_url:
            raise ConfigError(
                "API Base URL is not configured. Please set MAXTREE_API_BASE_URL or configure settings/config.json."
            )

    def _is_loopback(self, url):
        parsed = urllib.parse.urlparse(url)
        return parsed.hostname in ("127.0.0.1", "localhost", "::1")

    def _send_request(self, endpoint, method="GET", body=None, token=None):
        self._ensure_base_url()
        full_url = f"{self._base_url}{endpoint}"
        parsed = urllib.parse.urlparse(full_url)

        # Enforce HTTPS for non-loopback production addresses
        if parsed.scheme.lower() != "https" and not self._is_loopback(full_url):
            raise ConfigError(
                f"Insecure HTTP connection rejected for non-loopback domain ({full_url}). HTTPS is required."
            )

        headers = {
            "Accept": "application/json",
            "User-Agent": "Maxtree3dsMaxPlugin/3.0 Python",
        }

        data_bytes = None
        if body is not None:
            headers["Content-Type"] = "application/json"
            data_bytes = json.dumps(body).encode("utf-8")

        if token:
            headers["Authorization"] = f"Bearer {token}"

        req = urllib.request.Request(full_url, data=data_bytes, headers=headers, method=method)

        # For loopback HTTP in local dev, disable SSL context requirement
        context = None if parsed.scheme.lower() == "http" else self.ssl_context

        try:
            with urllib.request.urlopen(req, timeout=self.timeout, context=context) as response:
                resp_bytes = response.read()
                resp_str = resp_bytes.decode("utf-8")
                return json.loads(resp_str) if resp_str else {}

        except urllib.error.HTTPError as http_err:
            resp_str = ""
            try:
                resp_str = http_err.read().decode("utf-8")
                payload = json.loads(resp_str) if resp_str else {}
            except Exception:
                payload = {}

            error_code = payload.get("error_code")
            message = payload.get("message") or f"Server returned HTTP {http_err.code}"

            if http_err.code == 401:
                if error_code == "INVALID_CREDENTIALS":
                    raise InvalidCredentialsError(message)
                raise MaxtreeApiError(message, error_code="UNAUTHORIZED", status_code=401)

            elif http_err.code == 403:
                if error_code == "ACCOUNT_DISABLED":
                    raise AccountDisabledError(message)
                elif error_code == "LICENSE_NOT_FOUND":
                    raise LicenseNotFoundError(message)
                elif error_code == "LICENSE_INACTIVE":
                    raise LicenseInactiveError(message)
                elif error_code == "LICENSE_EXPIRED":
                    raise LicenseExpiredError(message)
                raise MaxtreeApiError(message, error_code=error_code or "FORBIDDEN", status_code=403)

            elif http_err.code == 429:
                raise RateLimitedError(message)

            elif http_err.code == 422:
                errors = payload.get("errors", {})
                first_msg = message
                if isinstance(errors, dict) and errors:
                    first_msg = next(iter(errors.values()))[0]
                raise MaxtreeApiError(first_msg, error_code="VALIDATION_ERROR", status_code=422)

            else:
                raise MaxtreeApiError(message, error_code=error_code or f"HTTP_{http_err.code}", status_code=http_err.code)

        except urllib.error.URLError as url_err:
            raise NetworkError(f"Network error: {url_err.reason}")
        except TimeoutError:
            raise NetworkError("Connection timed out. Please check your internet connection.")
        except json.JSONDecodeError:
            raise MaxtreeApiError("Received invalid JSON response from server.", error_code="INVALID_RESPONSE")
        except Exception as exc:
            if isinstance(exc, MaxtreeApiError):
                raise
            raise NetworkError(f"Unexpected connection error: {str(exc)}")

    def login(self, email, password):
        """
        Authenticate with Academy credentials.
        Returns dict containing token, user info, and license state.
        """
        payload = {"email": email, "password": password}
        res = self._send_request("/api/v1/maxtree/auth/login", method="POST", body=payload)
        return res

    def get_status(self, token):
        """
        Check token validity and live license status.
        Returns dict containing user info and license status.
        """
        if not token:
            raise MaxtreeApiError("No token provided", error_code="NO_TOKEN", status_code=401)
        res = self._send_request("/api/v1/maxtree/license/status", method="GET", token=token)
        return res

    def logout(self, token):
        """
        Revoke current Sanctum token on server.
        """
        if not token:
            return {"status": "success", "message": "No local token to revoke."}
        try:
            return self._send_request("/api/v1/maxtree/auth/logout", method="POST", token=token)
        except Exception as exc:
            # Re-raise network or API errors so caller knows server revocation status
            raise

    def __repr__(self):
        return f"<MaxtreeApiClient base_url='{self._base_url}'>"
