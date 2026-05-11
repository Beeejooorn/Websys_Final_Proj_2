<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $student_id = $_POST['student_id'];
  $fullname = $_POST['fullname'];
  $email = $_POST['email'];
  $course = $_POST['course'];
  $year_level = $_POST['year_level'];
  $birthdate = $_POST['birthdate'];
  $contact = $_POST['contact'];
  $address = $_POST['address'];

   $sql = "UPDATE students 
        SET fullname = '$fullname',
            email = '$email',
            course = '$course',
            year_level = '$year_level',
            birthdate = '$birthdate',
            contact = '$contact',
            address = '$address'
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
