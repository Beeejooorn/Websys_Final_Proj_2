<?php
include_once 'db_connect.php';
include_once 'validation_helpers.php';

sms_start_session();

if (empty($_SESSION['admin_id'])) {
    sms_redirect("login.php");
}

$currentAdmin = sms_load_admin_by_id($conn, (int)$_SESSION['admin_id']);

if (!$currentAdmin) {
    session_unset();
    session_destroy();
    sms_redirect("login.php");
}

if ($currentAdmin['status'] !== 'active') {
    session_unset();
    session_destroy();
    sms_redirect("login.php?message=account_disabled");
}

if (sms_is_locked($currentAdmin['locked_until'] ?? null)) {
    session_unset();
    session_destroy();
    sms_redirect("login.php?message=account_locked");
}

$_SESSION['admin_id'] = (int)$currentAdmin['admin_id'];
$_SESSION['username'] = $currentAdmin['username'];
$_SESSION['admin_email'] = $currentAdmin['email'];
$_SESSION['admin_role'] = $currentAdmin['role'];
$_SESSION['admin_status'] = $currentAdmin['status'];
?>
