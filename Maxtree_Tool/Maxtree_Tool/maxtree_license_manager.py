"""
Maxtree License Manager module for 3ds Max Python Plugin.
Manages authentication state, in-memory session token, authorization freshness,
sequence counter for async safety, and Qt signals for UI access control.
"""
import time
import threading

try:
    from PySide6 import QtCore
    HAS_PYSIDE = True
except ImportError:
    HAS_PYSIDE = False
    class QtCore:
        class QObject:
            pass

# Explicit Authorization State Constants
UNAUTHENTICATED = "UNAUTHENTICATED"
AUTHENTICATING = "AUTHENTICATING"
AUTHENTICATED_UNLICENSED = "AUTHENTICATED_UNLICENSED"
LICENSE_ACTIVE = "LICENSE_ACTIVE"
LICENSE_INACTIVE = "LICENSE_INACTIVE"
LICENSE_EXPIRED = "LICENSE_EXPIRED"
TOKEN_EXPIRED = "TOKEN_EXPIRED"
NETWORK_UNAVAILABLE = "NETWORK_UNAVAILABLE"
SERVER_ERROR = "SERVER_ERROR"

# Freshness configuration (90s max freshness boundary, 60s periodic recheck)
FRESHNESS_LIMIT_SECONDS = 90.0

class AuthorizationError(Exception):
    """Raised when an operation requires Maxtree authorization that is missing or expired."""
    def __init__(self, message="Maxtree authorization required.", operation="operation", state=UNAUTHENTICATED):
        super().__init__(message)
        self.message = message
        self.operation = operation
        self.state = state

    def __str__(self):
        return f"[AuthorizationError] ({self.operation}): {self.message}"

class _LicenseManagerBase(QtCore.QObject if HAS_PYSIDE else object):
    if HAS_PYSIDE:
        state_changed = QtCore.Signal(str, dict)  # (state_name, metadata)

    def __init__(self):
        if HAS_PYSIDE:
            super().__init__()

        self._lock = threading.RLock()
        self._state = UNAUTHENTICATED
        self._token = None  # Strictly process-memory storage
        self._user_data = {}
        self._license_data = {}
        self._last_verified_at = 0.0
        self._last_error = ""
        self._seq_counter = 0
        self._callbacks = []

    def add_listener(self, callback):
        """Register a state change callback (for non-Qt or testing)."""
        with self._lock:
            if callback not in self._callbacks:
                self._callbacks.append(callback)

    def remove_listener(self, callback):
        with self._lock:
            if callback in self._callbacks:
                self._callbacks.remove(callback)

    def next_sequence(self):
        """Generates a new incremented sequence number for async requests."""
        with self._lock:
            self._seq_counter += 1
            return self._seq_counter

    @property
    def current_sequence(self):
        with self._lock:
            return self._seq_counter

    @property
    def state(self):
        with self._lock:
            return self._state

    @property
    def user_data(self):
        with self._lock:
            return dict(self._user_data)

    @property
    def license_data(self):
        with self._lock:
            return dict(self._license_data)

    @property
    def last_error(self):
        with self._lock:
            return self._last_error

    @property
    def token(self):
        """Returns the in-memory bearer token."""
        with self._lock:
            return self._token

    def is_authorized(self):
        """
        Evaluates authorization and strict freshness limit.
        Fail-closed: Returns True ONLY if state is LICENSE_ACTIVE AND
        the last verification occurred within FRESHNESS_LIMIT_SECONDS (90s).
        """
        with self._lock:
            if self._state != LICENSE_ACTIVE:
                return False
            if not self._token:
                return False
            elapsed = time.monotonic() - self._last_verified_at
            if elapsed > FRESHNESS_LIMIT_SECONDS:
                # Stale authorization boundary exceeded
                self._set_state(TOKEN_EXPIRED, "Session authorization expired due to staleness. Revalidation required.")
                return False
            return True

    def _set_state(self, new_state, error_msg="", user_info=None, license_info=None, seq=None):
        with self._lock:
            if seq is not None and seq < self._seq_counter:
                # Discard late response arriving out-of-order or after logout/reset
                return False

            self._state = new_state
            self._last_error = error_msg
            if user_info is not None:
                self._user_data = user_info
            if license_info is not None:
                self._license_data = license_info

            if new_state == LICENSE_ACTIVE:
                self._last_verified_at = time.monotonic()

            meta = {
                "state": self._state,
                "user": self.user_data,
                "license": self.license_data,
                "error": self._last_error,
                "authorized": self.is_authorized(),
            }

            callbacks_to_call = list(self._callbacks)

        # Emit PySide signal if available
        if HAS_PYSIDE:
            try:
                self.state_changed.emit(new_state, meta)
            except Exception:
                pass

        # Call non-Qt listeners
        for cb in callbacks_to_call:
            try:
                cb(new_state, meta)
            except Exception:
                pass

        return True

    def start_authentication(self):
        seq = self.next_sequence()
        self._set_state(AUTHENTICATING, seq=seq)
        return seq

    def handle_login_success(self, token, user_data, license_data, seq):
        with self._lock:
            if seq < self._seq_counter:
                return False
            self._token = token
            is_active = bool(license_data.get("is_active"))
            if is_active:
                return self._set_state(LICENSE_ACTIVE, user_info=user_data, license_info=license_data, seq=seq)
            else:
                return self._set_state(AUTHENTICATED_UNLICENSED, error_msg="Account valid, but Maxtree license is inactive.", user_info=user_data, license_info=license_data, seq=seq)

    def handle_status_success(self, user_data, license_data, seq):
        with self._lock:
            if seq < self._seq_counter or not self._token:
                return False
            is_active = bool(license_data.get("is_active"))
            if is_active:
                return self._set_state(LICENSE_ACTIVE, user_info=user_data, license_info=license_data, seq=seq)
            else:
                return self._set_state(LICENSE_INACTIVE, error_msg="Maxtree license is no longer active.", user_info=user_data, license_info=license_data, seq=seq)

    def handle_error(self, error_code, message, seq):
        with self._lock:
            if seq < self._seq_counter:
                return False

            state_map = {
                "INVALID_CREDENTIALS": UNAUTHENTICATED,
                "ACCOUNT_DISABLED": UNAUTHENTICATED,
                "LICENSE_NOT_FOUND": AUTHENTICATED_UNLICENSED,
                "LICENSE_INACTIVE": LICENSE_INACTIVE,
                "LICENSE_EXPIRED": LICENSE_EXPIRED,
                "UNAUTHORIZED": TOKEN_EXPIRED,
                "NETWORK_ERROR": NETWORK_UNAVAILABLE,
            }
            target_state = state_map.get(error_code, SERVER_ERROR)

            if target_state in (UNAUTHENTICATED, TOKEN_EXPIRED, SERVER_ERROR):
                # Clear token on invalid credential or token expiry errors
                self._token = None

            return self._set_state(target_state, error_msg=message, seq=seq)

    def logout(self, server_revoked=False):
        """
        Clears local session state and invalidates current token in memory immediately.
        Increments sequence counter so pending async responses are discarded.
        """
        with self._lock:
            self.next_sequence()  # Increment sequence counter to ignore any in-flight workers
            self._token = None
            self._user_data = {}
            self._license_data = {}
            self._last_verified_at = 0.0
            msg = "Logged out successfully." if server_revoked else "Logged out locally (server token revocation unconfirmed)."
            self._set_state(UNAUTHENTICATED, error_msg=msg)

    def __repr__(self):
        token_indicator = "PRESENT" if self._token else "NONE"
        return f"<MaxtreeLicenseManager state='{self._state}' token='{token_indicator}'>"


# Singleton Instance
_instance = None
_instance_lock = threading.Lock()

def get_manager():
    global _instance
    if _instance is None:
        with _instance_lock:
            if _instance is None:
                _instance = _LicenseManagerBase()
    return _instance

def reset_manager_for_testing():
    global _instance
    with _instance_lock:
        if _instance is not None:
            _instance.logout()
            _instance = None

def require_authorization(operation_name="operation"):
    """
    Centralized execution guard. Call at the entry point of protected operations.
    Raises AuthorizationError if current session is not validly authorized or stale.
    """
    mgr = get_manager()
    if not mgr.is_authorized():
        st = mgr.state
        err = mgr.last_error or "Session is unauthenticated or Maxtree license is inactive."
        raise AuthorizationError(
            message=f"Access denied for '{operation_name}'. {err}",
            operation=operation_name,
            state=st
        )

