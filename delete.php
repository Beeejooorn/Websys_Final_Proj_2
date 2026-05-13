<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sms_set_flash("error", "Delete actions must be submitted from the Student List page.");
    sms_redirect("students.php");
}

$studentId = (int)($_POST['student_id'] ?? 0);

if (!sms_is_valid_positive_id($studentId)) {
    sms_set_flash("error", "Invalid student record.");
    sms_redirect("students.php");
}

$studentStmt = $conn->prepare("SELECT student_number, fullname FROM students WHERE student_id = ? LIMIT 1");
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$student = $studentResult->fetch_assoc();
$studentStmt->close();

if (!$student) {
    sms_set_flash("error", "Student record was not found.");
    sms_redirect("students.php");
}

$conn->begin_transaction();

try {
    $enrollmentStmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
    $enrollmentStmt->bind_param("i", $studentId);

    if (!$enrollmentStmt->execute()) {
        throw new Exception($enrollmentStmt->error);
    }

    $enrollmentStmt->close();

    $studentDeleteStmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $studentDeleteStmt->bind_param("i", $studentId);

    if (!$studentDeleteStmt->execute()) {
        throw new Exception($studentDeleteStmt->error);
    }

    $studentDeleteStmt->close();
    $conn->commit();

    sms_log_activity($conn, "student_deleted", "Deleted student " . $student['student_number'] . " - " . $student['fullname'] . ".", sms_current_admin_id());
    sms_set_flash("success", "Student deleted successfully.");
} catch (Exception $exception) {
    $conn->rollback();
    sms_set_flash("error", "Unable to delete student. No records were changed.");
}

sms_redirect("students.php");
?>
