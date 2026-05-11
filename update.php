<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $student_id = (int)($_POST['student_id'] ?? 0);
   $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
   $fullname = $_POST['fullname'] ?? '';
   $email = $_POST['email'] ?? '';
   $course = $_POST['course'] ?? '';
   $year_level = $_POST['year_level'] ?? '';
   $birthdate = $_POST['birthdate'] ?? '';
   $contact = $_POST['contact'] ?? '';
   $address = $_POST['address'] ?? '';
   $semester = trim($_POST['semester'] ?? '');
   $grade = trim($_POST['grade'] ?? '');

   $safeFullname = $conn->real_escape_string($fullname);
   $safeEmail = $conn->real_escape_string($email);
   $safeCourse = $conn->real_escape_string($course);
   $safeYearLevel = $conn->real_escape_string($year_level);
   $safeBirthdate = $conn->real_escape_string($birthdate);
   $safeContact = $conn->real_escape_string($contact);
   $safeAddress = $conn->real_escape_string($address);
   $safeSemester = $conn->real_escape_string($semester);
   $safeGrade = $conn->real_escape_string($grade);

   $sql = "UPDATE students 
        SET fullname = '$safeFullname',
            email = '$safeEmail',
            course = '$safeCourse',
            year_level = '$safeYearLevel',
            birthdate = '$safeBirthdate',
            contact = '$safeContact',
            address = '$safeAddress'
        WHERE student_id = $student_id";

    $conn->begin_transaction();

    try {
        if ($conn->query($sql) !== TRUE) {
            throw new Exception($conn->error);
        }

        if ($semester !== '' || $enrollment_id > 0) {
            if ($semester === '') {
                throw new Exception("Please enter the enrollment semester.");
            }

            $course_lookup = $conn->query("SELECT course_id FROM courses WHERE course_name = '$safeCourse' LIMIT 1");

            if (!$course_lookup || $course_lookup->num_rows === 0) {
                throw new Exception("Selected course was not found in the courses table.");
            }

            $course_row = $course_lookup->fetch_assoc();
            $course_id = (int)$course_row['course_id'];

            if ($enrollment_id > 0) {
                $enrollment_sql = "UPDATE enrollments
                    SET course_id = $course_id,
                        semester = '$safeSemester',
                        grade = '$safeGrade'
                    WHERE enrollment_id = $enrollment_id
                      AND student_id = $student_id";
            } else {
                $enrollment_sql = "INSERT INTO enrollments (student_id, course_id, semester, grade)
                    VALUES ('$student_id', '$course_id', '$safeSemester', '$safeGrade')";
            }

            if ($conn->query($enrollment_sql) !== TRUE) {
                throw new Exception($conn->error);
            }
        }

        $conn->commit();
        header("Location: students.php");
        exit();
    } catch (Exception $error) {
        $conn->rollback();
        echo "Error updating record: " . $error->getMessage();
    }
} else {
    echo "Invalid request.";
}
?>
