<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "", "student_manager_system");

    if ($conn->connect_error) {
        die("Database connection failed. Please check that MySQL is running.");
    }

    $conn->set_charset("utf8mb4");
}
?>
