<?php
include_once 'db_connect.php';
include_once 'validation_helpers.php';

sms_start_session();

if (!empty($_SESSION['admin_id'])) {
    sms_log_activity($conn, "logout_success", "Admin logged out.", (int)$_SESSION['admin_id']);
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

session_destroy();

sms_redirect("login.php?message=logout_success");
?>
