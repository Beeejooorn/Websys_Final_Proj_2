<?php
include 'db_connect.php';

$id = $_GET['id'];

$sql = "DELETE FROM students WHERE student_id = $id";

if ($conn->query($sql) === TRUE) {
    header("Location: students.php");
    exit();
} else {
    echo "Error: " . $conn->error;
}
?>
