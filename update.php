<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $course = $_POST['course'];

    $sql = "UPDATE students 
            SET fullname = '$fullname',
                email = '$email',
                course = '$course'
            WHERE student_id = $student_id";

    if ($conn->query($sql) === TRUE) {
        header("Location: students.php");
        exit();
    } else {
        echo "Error updating record: " . $conn->error;
    }
} else {
    echo "Invalid request.";
}
?>
