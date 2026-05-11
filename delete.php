<?php
include 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);

// First, get the user_id connected to this student
$get_user_sql = "SELECT user_id FROM students WHERE student_id = $id";
$user_result = $conn->query($get_user_sql);

if ($user_result && $user_result->num_rows > 0) {
    $student = $user_result->fetch_assoc();
    $user_id = $student['user_id'];

    // Delete the student record first
    $delete_student_sql = "DELETE FROM students WHERE student_id = $id";

    if ($conn->query($delete_student_sql) === TRUE) {

        // Then delete the connected user record
        $delete_user_sql = "DELETE FROM users WHERE user_id = $user_id";
        $conn->query($delete_user_sql);

        header("Location: students.php");
        exit();
    } else {
        echo "Error deleting student: " . $conn->error;
    }
} else {
    echo "Student not found.";
}
?>