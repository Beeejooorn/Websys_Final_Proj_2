<?php
include_once 'auth_check.php';
include_once 'validation_helpers.php';

sms_set_flash("info", "Student registration and initial enrollment are handled on the main Student Registration page.");
sms_redirect("registration.php");
?>
