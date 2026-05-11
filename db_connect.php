<?php
$conn = new mysqli("localhost", "root", "", "student_manager_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>