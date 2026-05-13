<?php
include_once 'auth_check.php';
include_once 'validation_helpers.php';

sms_set_flash("info", "The dashboard has moved to the main Dashboard page.");
sms_redirect("index.php");
?>
