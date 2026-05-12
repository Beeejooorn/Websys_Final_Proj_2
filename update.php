<?php
include 'auth_check.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';
include 'validation_helpers.php';

function redirect_update_error($student_id, $error_code) {
    header("Location: edit.php?id=" . urlencode((string)$student_id) . "&error=" . urlencode($error_code));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $student_id = (int)($_POST['student_id'] ?? 0);
   $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
   $fullname = trim($_POST['fullname'] ?? '');
   $email = trim($_POST['email'] ?? '');
   $course = trim($_POST['course'] ?? '');
   $year_level = trim($_POST['year_level'] ?? '');
   $birthdate = trim($_POST['birthdate'] ?? '');
   $contact = trim($_POST['contact'] ?? '');
   $address = trim($_POST['address'] ?? '');
   $semester = trim($_POST['semester'] ?? '');
   $grade = trim($_POST['grade'] ?? '');

   if ($student_id <= 0) {
       echo "Invalid student ID.";
       exit();
   }

   if (!sms_is_valid_person_name($fullname)) {
       redirect_update_error($student_id, "invalid_name");
   }

   if (!sms_is_valid_gmail_address($email)) {
       redirect_update_error($student_id, "invalid_email");
   }

   if ($course === '') {
       redirect_update_error($student_id, "invalid_course");
   }

   if (!sms_is_valid_year_level($year_level)) {
       redirect_update_error($student_id, "invalid_year_level");
   }

   if (!sms_is_valid_birthdate($birthdate)) {
       redirect_update_error($student_id, "invalid_birthdate");
   }

   if (!sms_is_valid_contact_number($contact)) {
       redirect_update_error($student_id, "invalid_contact");
   }

   if (($semester !== '' || $enrollment_id > 0) && !sms_is_valid_semester($semester)) {
       redirect_update_error($student_id, "invalid_semester");
   }

   if (!sms_is_valid_grade($grade)) {
       redirect_update_error($student_id, "invalid_grade");
   }

   $course_lookup = $conn->prepare("SELECT course_id FROM courses WHERE course_name = ? LIMIT 1");

   if (!$course_lookup) {
       redirect_update_error($student_id, "invalid_course");
   }

   $course_lookup->bind_param("s", $course);

   if (!$course_lookup->execute()) {
       $course_lookup->close();
       redirect_update_error($student_id, "invalid_course");
   }

   $course_result = $course_lookup->get_result();

   if (!$course_result || $course_result->num_rows === 0) {
       $course_lookup->close();
       redirect_update_error($student_id, "invalid_course");
   }

   $course_row = $course_result->fetch_assoc();
   $course_id = (int)$course_row['course_id'];
   $course_lookup->close();

    $conn->begin_transaction();

    try {
        $student_stmt = $conn->prepare("
            UPDATE students
            SET fullname = ?,
                email = ?,
                course = ?,
                year_level = ?,
                birthdate = ?,
                contact = ?,
                address = ?
            WHERE student_id = ?
        ");

        if (!$student_stmt) {
            throw new Exception($conn->error);
        }

        $student_stmt->bind_param(
            "sssssssi",
            $fullname,
            $email,
            $course,
            $year_level,
            $birthdate,
            $contact,
            $address,
            $student_id
        );

        if (!$student_stmt->execute()) {
            throw new Exception($student_stmt->error);
        }

        $student_stmt->close();

        if ($semester !== '' || $enrollment_id > 0) {
            if ($enrollment_id > 0) {
                $enrollment_stmt = $conn->prepare("
                    UPDATE enrollments
                    SET course_id = ?,
                        semester = ?,
                        grade = ?
                    WHERE enrollment_id = ?
                      AND student_id = ?
                ");

                if (!$enrollment_stmt) {
                    throw new Exception($conn->error);
                }

                $enrollment_stmt->bind_param("issii", $course_id, $semester, $grade, $enrollment_id, $student_id);
            } else {
                $enrollment_stmt = $conn->prepare("
                    INSERT INTO enrollments (student_id, course_id, semester, grade)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$enrollment_stmt) {
                    throw new Exception($conn->error);
                }

                $enrollment_stmt->bind_param("iiss", $student_id, $course_id, $semester, $grade);
            }

            if (!$enrollment_stmt->execute()) {
                throw new Exception($enrollment_stmt->error);
            }

            $enrollment_stmt->close();
        }

        $conn->commit();
        header("Location: students.php");
        exit();
    } catch (Exception $error) {
        $conn->rollback();
        redirect_update_error($student_id, "save_failed");
    }
} else {
    echo "Invalid request.";
}
?>
