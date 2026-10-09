"""
PySide6 Authentication Interface and Asynchronous Network Workers for Maxtree 3ds Max Plugin.
Provides Login Dialog, Header Account Widget, and QThread worker lifecycle safety.
"""
from pathlib import Path
from PySide6 import QtCore, QtGui, QtWidgets

import maxtree_api_client as api_client_mod
import maxtree_license_manager as license_mgr_mod

PROJECT = Path(__file__).resolve().parent

def get_preview_pixmap(path, size):
    if not path or not Path(path).is_file():
        return QtGui.QPixmap()
    reader = QtGui.QImageReader(str(path))
    original = reader.size()
    if original.isValid():
        reader.setScaledSize(original.scaled(size, QtCore.Qt.KeepAspectRatio))
    return QtGui.QPixmap.fromImage(reader.read())


# ASYNCHRONOUS QT WORKERS
class LoginWorker(QtCore.QThread):
    finished_signal = QtCore.Signal(dict, int)  # (response_data, seq)
    error_signal = QtCore.Signal(str, str, int)  # (error_code, message, seq)

    def __init__(self, api_client, email, password, seq, parent=None):
        super().__init__(parent)
        self.api_client = api_client
        self.email = email
        self.password = password
        self.seq = seq

    def run(self):
        try:
            res = self.api_client.login(self.email, self.password)
            self.finished_signal.emit(res, self.seq)
        except api_client_mod.MaxtreeApiError as exc:
            self.error_signal.emit(exc.error_code, exc.message, self.seq)
        except Exception as exc:
            self.error_signal.emit("SERVER_ERROR", str(exc), self.seq)


class StatusWorker(QtCore.QThread):
    finished_signal = QtCore.Signal(dict, int)  # (response_data, seq)
    error_signal = QtCore.Signal(str, str, int)  # (error_code, message, seq)

    def __init__(self, api_client, token, seq, parent=None):
        super().__init__(parent)
        self.api_client = api_client
        self.token = token
        self.seq = seq

    def run(self):
        try:
            res = self.api_client.get_status(self.token)
            self.finished_signal.emit(res, self.seq)
        except api_client_mod.MaxtreeApiError as exc:
            self.error_signal.emit(exc.error_code, exc.message, self.seq)
        except Exception as exc:
            self.error_signal.emit("SERVER_ERROR", str(exc), self.seq)


class LogoutWorker(QtCore.QThread):
    finished_signal = QtCore.Signal(dict, int)  # (response_data, seq)
    error_signal = QtCore.Signal(str, str, int)  # (error_code, message, seq)

    def __init__(self, api_client, token, seq, parent=None):
        super().__init__(parent)
        self.api_client = api_client
        self.token = token
        self.seq = seq

    def run(self):
        try:
            res = self.api_client.logout(self.token)
            self.finished_signal.emit(res, self.seq)
        except Exception as exc:
            msg = str(exc)
            err_code = getattr(exc, 'error_code', 'LOGOUT_ERROR')
            self.error_signal.emit(err_code, msg, self.seq)


# LOGIN DIALOG WIDGET
class LoginDialog(QtWidgets.QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle("تسجيل الدخول — Maxtree Tool Login")
        self.resize(440, 380)
        self.setModal(True)

        self.manager = license_mgr_mod.get_manager()
        self.api_client = api_client_mod.MaxtreeApiClient()
        self.worker = None
        self.is_submitting = False

        self._build_ui()

    def _build_ui(self):
        layout = QtWidgets.QVBoxLayout(self)
        layout.setSpacing(12)

        # Header Logo & Title
        header = QtWidgets.QHBoxLayout()
        logo_label = QtWidgets.QLabel()
        logo_pix = get_preview_pixmap(PROJECT / "academy_logo.png", QtCore.QSize(120, 60))
        if not logo_pix.isNull():
            logo_label.setPixmap(logo_pix)
        header.addWidget(logo_label)

        title_box = QtWidgets.QVBoxLayout()
        title = QtWidgets.QLabel("MAXTREE TOOL")
        font = title.font()
        font.setPointSize(14)
        font.setBold(True)
        title.setFont(font)
        sub = QtWidgets.QLabel("Academy Account Login")
        sub.setStyleSheet("color: #666;")
        title_box.addWidget(title)
        title_box.addWidget(sub)
        header.addLayout(title_box)
        header.addStretch()
        layout.addLayout(header)

        # Divider line
        line = QtWidgets.QFrame()
        line.setFrameShape(QtWidgets.QFrame.HLine)
        line.setFrameShadow(QtWidgets.QFrame.Sunken)
        layout.addWidget(line)

        # Form layout
        form = QtWidgets.QFormLayout()
        form.setSpacing(10)

        self.email_input = QtWidgets.QLineEdit()
        self.email_input.setPlaceholderText("Enter Academy email address...")
        form.addRow("Email:", self.email_input)

        self.password_input = QtWidgets.QLineEdit()
        self.password_input.setEchoMode(QtWidgets.QLineEdit.Password)
        self.password_input.setPlaceholderText("Enter password...")
        form.addRow("Password:", self.password_input)

        self.show_password_cb = QtWidgets.QCheckBox("Show Password")
        self.show_password_cb.toggled.connect(self._toggle_password)
        form.addRow("", self.show_password_cb)

        layout.addLayout(form)

        # Error / Status Banner
        self.status_label = QtWidgets.QLabel("")
        self.status_label.setWordWrap(True)
        self.status_label.setStyleSheet("color: #d9534f; font-weight: bold;")
        layout.addWidget(self.status_label)

        # Buttons
        btn_box = QtWidgets.QHBoxLayout()
        self.login_btn = QtWidgets.QPushButton("Login")
        self.login_btn.setDefault(True)
        self.login_btn.setStyleSheet(
            "QPushButton { background-color: #2e7d32; color: #ffffff; font-weight: bold; padding: 7px 18px; border: 1px solid #4caf50; border-radius: 4px; }"
            "QPushButton:hover { background-color: #388e3c; color: #ffffff; }"
            "QPushButton:pressed { background-color: #1b5e20; color: #ffffff; }"
            "QPushButton:focus { outline: 2px solid #81c784; }"
            "QPushButton:disabled { background-color: #424242; color: #888888; border: 1px solid #555555; }"
        )
        self.cancel_btn = QtWidgets.QPushButton("Cancel")
        self.cancel_btn.setStyleSheet(
            "QPushButton { background-color: #424242; color: #ffffff; font-weight: bold; padding: 7px 18px; border: 1px solid #666666; border-radius: 4px; }"
            "QPushButton:hover { background-color: #555555; }"
            "QPushButton:pressed { background-color: #333333; }"
        )

        btn_box.addStretch()
        btn_box.addWidget(self.cancel_btn)
        btn_box.addWidget(self.login_btn)
        layout.addLayout(btn_box)

        self.login_btn.clicked.connect(self.submit_login)
        self.cancel_btn.clicked.connect(self.reject)

    def _toggle_password(self, checked):
        mode = QtWidgets.QLineEdit.Normal if checked else QtWidgets.QLineEdit.Password
        self.password_input.setEchoMode(mode)

    def submit_login(self):
        if self.is_submitting:
            return

        email = self.email_input.text().strip()
        password = self.password_input.text()

        if not email or not password:
            self.status_label.setText("Please enter both email and password.")
            return

        self.is_submitting = True
        self.login_btn.setEnabled(False)
        self.cancel_btn.setEnabled(False)
        self.email_input.setEnabled(False)
        self.password_input.setEnabled(False)
        self.status_label.setStyleSheet("color: #0275d8; font-weight: bold;")
        self.status_label.setText("Authenticating with Academy server...")

        # Clear password string promptly after submission
        self.password_input.clear()

        seq = self.manager.start_authentication()

        self.worker = LoginWorker(self.api_client, email, password, seq, parent=self)
        self.worker.finished_signal.connect(self.on_login_success)
        self.worker.error_signal.connect(self.on_login_error)
        self.worker.finished.connect(self.worker.deleteLater)
        self.worker.start()

    def on_login_success(self, res, seq):
        self.is_submitting = False
        token = res.get("token")
        user_data = res.get("user", {})
        license_data = res.get("license", {})

        if self.manager.handle_login_success(token, user_data, license_data, seq):
            if self.manager.is_authorized():
                self.accept()
            else:
                self._reset_input_form()
                self.status_label.setStyleSheet("color: #d9534f; font-weight: bold;")
                self.status_label.setText("Account authenticated, but Maxtree license is inactive or expired.")
        else:
            self._reset_input_form()
            self.status_label.setText("Authentication request superseded.")

    def on_login_error(self, error_code, message, seq):
        self.is_submitting = False
        self.manager.handle_error(error_code, message, seq)
        self._reset_input_form()

        self.status_label.setStyleSheet("color: #d9534f; font-weight: bold;")
        if error_code == "INVALID_CREDENTIALS":
            self.status_label.setText("Invalid email or password.")
        elif error_code == "ACCOUNT_DISABLED":
            self.status_label.setText("Your Academy account is disabled.")
        elif error_code == "LICENSE_INACTIVE":
            self.status_label.setText("Your Maxtree license is inactive.")
        elif error_code == "LICENSE_EXPIRED":
            self.status_label.setText("Your Maxtree license has expired.")
        elif error_code == "RATE_LIMITED":
            self.status_label.setText("Too many login attempts. Please wait 1 minute.")
        else:
            self.status_label.setText(f"Login failed: {message}")

    def _reset_input_form(self):
        self.login_btn.setEnabled(True)
        self.cancel_btn.setEnabled(True)
        self.email_input.setEnabled(True)
        self.password_input.setEnabled(True)
        self.email_input.setFocus()

    def closeEvent(self, event):
        if self.is_submitting:
            # Prevent closing modal window while network request is in flight
            event.ignore()
        else:
            event.accept()


# HEADER ACCOUNT WIDGET
class HeaderAccountWidget(QtWidgets.QWidget):
    logout_requested = QtCore.Signal()
    revalidate_requested = QtCore.Signal()

    def __init__(self, parent=None):
        super().__init__(parent)
        self.manager = license_mgr_mod.get_manager()
        self._build_ui()

    def _build_ui(self):
        layout = QtWidgets.QHBoxLayout(self)
        layout.setContentsMargins(0, 0, 0, 0)
        layout.setSpacing(8)

        self.status_badge = QtWidgets.QLabel()
        self.status_badge.setStyleSheet("font-weight: bold; padding: 3px 8px; border-radius: 3px;")
        layout.addWidget(self.status_badge)

        self.user_label = QtWidgets.QLabel()
        layout.addWidget(self.user_label)

        self.recheck_btn = QtWidgets.QPushButton("Re-verify")
        self.recheck_btn.setToolTip("Check license status with server")
        self.recheck_btn.clicked.connect(self.revalidate_requested.emit)
        layout.addWidget(self.recheck_btn)

        self.logout_btn = QtWidgets.QPushButton("Logout")
        self.logout_btn.setStyleSheet("QPushButton { background-color: #d9534f; color: white; font-weight: bold; }")
        self.logout_btn.clicked.connect(self.logout_requested.emit)
        layout.addWidget(self.logout_btn)

        self.update_view()

    def update_view(self):
        state = self.manager.state
        user = self.manager.user_data
        authorized = self.manager.is_authorized()

        if authorized:
            self.status_badge.setText("ACTIVE")
            self.status_badge.setStyleSheet("background-color: #4cae4c; color: white; font-weight: bold; padding: 2px 6px;")
            self.user_label.setText(f"Logged in as: {user.get('email', '')}")
            self.recheck_btn.setVisible(True)
            self.logout_btn.setVisible(True)
        else:
            self.status_badge.setText("LOCKED")
            self.status_badge.setStyleSheet("background-color: #d9534f; color: white; font-weight: bold; padding: 2px 6px;")
            self.user_label.setText("Not authenticated")
            self.recheck_btn.setVisible(False)
            self.logout_btn.setVisible(False)
