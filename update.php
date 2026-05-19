<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function redirect_update_error($studentId, $message) {
    sms_set_flash("error", $message);
    sms_redirect("edit.php?id=" . urlencode((string)$studentId));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sms_set_flash("error", "Invalid update request.");
    sms_redirect("students.php");
}

$studentId = (int)($_POST['student_id'] ?? 0);
$enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
$studentNumber = trim($_POST['student_number'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$courseId = (int)($_POST['course_id'] ?? 0);
$yearLevel = trim($_POST['year_level'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$address = trim($_POST['address'] ?? '');
$semester = trim($_POST['semester'] ?? '');
$grade = trim($_POST['grade'] ?? '');

if (!sms_is_valid_positive_id($studentId)) {
    sms_set_flash("error", "Invalid student record.");
    sms_redirect("students.php");
}

if (!sms_is_valid_student_number($studentNumber)) {
    redirect_update_error($studentId, "Student ID must be exactly 10 digits.");
}

if (!sms_is_valid_person_name($fullname)) {
    redirect_update_error($studentId, "Please enter a valid full name using letters, spaces, periods, apostrophes, or dashes only.");
}

if (!sms_is_valid_gmail_address($email)) {
    redirect_update_error($studentId, "Please enter a valid Gmail address ending in @gmail.com.");
}

if (!sms_is_valid_positive_id($courseId)) {
    redirect_update_error($studentId, "Please select a valid course.");
}

if (!sms_is_valid_year_level($yearLevel)) {
    redirect_update_error($studentId, "Please select a valid year level.");
}

if (!sms_is_valid_birthdate($birthdate)) {
    redirect_update_error($studentId, "Please enter a valid birthdate that is not in the future.");
}

if (!sms_is_valid_contact_number($contact)) {
    redirect_update_error($studentId, "Contact number should contain numbers only, with optional plus sign, spaces, or dashes.");
}

if ($address === '') {
    redirect_update_error($studentId, "Please enter the student address.");
}

if (!sms_is_valid_semester($semester)) {
    redirect_update_error($studentId, "Please enter a valid semester, such as 1st Semester.");
}

if (!sms_is_valid_grade($grade)) {
    redirect_update_error($studentId, "Please enter a valid grade, such as 1.25, N/A, or leave it blank.");
}

$courseLookup = $conn->prepare("SELECT course_id, course_name FROM courses WHERE course_id = ? LIMIT 1");
$courseLookup->bind_param("i", $courseId);
$courseLookup->execute();
$courseResult = $courseLookup->get_result();
$selectedCourse = $courseResult->fetch_assoc();
$courseLookup->close();

if (!$selectedCourse) {
    redirect_update_error($studentId, "Please select a course that exists in the courses table.");
}

$duplicateStmt = $conn->prepare("SELECT student_number, email FROM students WHERE (student_number = ? OR email = ?) AND student_id <> ? LIMIT 1");
$duplicateStmt->bind_param("ssi", $studentNumber, $email, $studentId);
$duplicateStmt->execute();
$duplicateResult = $duplicateStmt->get_result();
$duplicate = $duplicateResult->fetch_assoc();
$duplicateStmt->close();

if ($duplicate && $duplicate['student_number'] === $studentNumber) {
    redirect_update_error($studentId, "Student ID already exists. Please enter a different Student ID.");
}

if ($duplicate && strtolower($duplicate['email']) === $email) {
    redirect_update_error($studentId, "That email address already exists. Please use another Gmail address.");
}

$enrollmentDuplicate = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ? AND semester = ? AND enrollment_id <> ? LIMIT 1");
$enrollmentDuplicate->bind_param("iisi", $studentId, $courseId, $semester, $enrollmentId);
$enrollmentDuplicate->execute();
$enrollmentDuplicateResult = $enrollmentDuplicate->get_result();
$hasDuplicateEnrollment = $enrollmentDuplicateResult->num_rows > 0;
$enrollmentDuplicate->close();

if ($hasDuplicateEnrollment) {
    redirect_update_error($studentId, "This student is already enrolled in this course for the selected semester.");
}

$conn->begin_transaction();

try {
    $courseName = $selectedCourse['course_name'];
    $studentStmt = $conn->prepare("
        UPDATE students
        SET student_number = ?,
            fullname = ?,
            email = ?,
            course_id = ?,
            course = ?,
            year_level = ?,
            birthdate = ?,
            contact = ?,
            address = ?
        WHERE student_id = ?
    ");
    $studentStmt->bind_param(
        "sssisssssi",
        $studentNumber,
        $fullname,
        $email,
        $courseId,
        $courseName,
        $yearLevel,
        $birthdate,
        $contact,
        $address,
        $studentId
    );

    if (!$studentStmt->execute()) {
        throw new Exception($studentStmt->error);
    }

    $studentStmt->close();

    if ($enrollmentId > 0) {
        $enrollmentStmt = $conn->prepare("
            UPDATE enrollments
            SET course_id = ?,
                semester = ?,
                grade = ?
            WHERE enrollment_id = ?
              AND student_id = ?
        ");
        $enrollmentStmt->bind_param("issii", $courseId, $semester, $grade, $enrollmentId, $studentId);
    } else {
        $enrollmentStmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, semester, grade) VALUES (?, ?, ?, ?)");
        $enrollmentStmt->bind_param("iiss", $studentId, $courseId, $semester, $grade);
    }

    if (!$enrollmentStmt->execute()) {
        throw new Exception($enrollmentStmt->error);
    }

    $enrollmentStmt->close();
    $conn->commit();

    sms_log_activity($conn, "student_updated", "Updated student " . $studentNumber . " and enrollment details.", sms_current_admin_id());
    sms_set_flash("success", "Student updated successfully. Enrollment details were saved.");
    sms_redirect("students.php");
} catch (Exception $error) {
    $conn->rollback();
    redirect_update_error($studentId, sms_duplicate_message_from_error($error->getMessage(), "Database action failed. Unable to update student. No changes were saved."));
}
?>
