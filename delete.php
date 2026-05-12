<?php
include 'auth_check.php';
include 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "Invalid student ID.";
    exit();
}

$check_stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ? LIMIT 1");
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$student_result = $check_stmt->get_result();

if (!$student_result || $student_result->num_rows === 0) {
    echo "Student not found.";
    exit();
}

$check_stmt->close();

$conn->begin_transaction();

try {
    $delete_enrollments_stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
    $delete_enrollments_stmt->bind_param("i", $id);
    if (!$delete_enrollments_stmt->execute()) {
        throw new Exception($delete_enrollments_stmt->error);
    }
    $delete_enrollments_stmt->close();

    $delete_student_stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $delete_student_stmt->bind_param("i", $id);
    if (!$delete_student_stmt->execute()) {
        throw new Exception($delete_student_stmt->error);
    }
    $delete_student_stmt->close();

    $conn->commit();
    header("Location: students.php");
    exit();
} catch (Exception $error) {
    $conn->rollback();
    echo "Error deleting student: " . $error->getMessage();
}
?>
