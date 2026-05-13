<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function e($value) {
    return sms_escape($value ?? '');
}

function display_value($value, $fallback = 'Not recorded') {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? e($value) : $fallback;
}

function enrollment_exists($conn, $studentId, $courseId, $semester, $excludeEnrollmentId = 0) {
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ? AND semester = ? AND enrollment_id <> ? LIMIT 1");
    $stmt->bind_param("iisi", $studentId, $courseId, $semester, $excludeEnrollmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['enrollment_action'] ?? '';

    if ($action === 'delete') {
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);

        if (!sms_is_valid_positive_id($enrollmentId)) {
            sms_set_flash("error", "Invalid enrollment record.");
            sms_redirect("enrollment.php");
        }

        $lookup = $conn->prepare("
            SELECT e.enrollment_id, s.student_number, s.fullname
            FROM enrollments e
            INNER JOIN students s ON s.student_id = e.student_id
            WHERE e.enrollment_id = ?
            LIMIT 1
        ");
        $lookup->bind_param("i", $enrollmentId);
        $lookup->execute();
        $lookupResult = $lookup->get_result();
        $enrollment = $lookupResult->fetch_assoc();
        $lookup->close();

        if (!$enrollment) {
            sms_set_flash("error", "Enrollment record was not found.");
            sms_redirect("enrollment.php");
        }

        $deleteStmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $deleteStmt->bind_param("i", $enrollmentId);

        if ($deleteStmt->execute()) {
            sms_log_activity($conn, "enrollment_deleted", "Deleted enrollment for " . $enrollment['student_number'] . " - " . $enrollment['fullname'] . ".", sms_current_admin_id());
            sms_set_flash("success", "Enrollment deleted successfully.");
        } else {
            sms_set_flash("error", "Unable to delete enrollment.");
        }

        $deleteStmt->close();
        sms_redirect("enrollment.php");
    }

    if ($action === 'create' || $action === 'update') {
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        $semester = trim($_POST['semester'] ?? '');
        $grade = trim($_POST['grade'] ?? '');

        if ($action === 'update') {
            $currentEnrollment = $conn->prepare("SELECT student_id FROM enrollments WHERE enrollment_id = ? LIMIT 1");
            $currentEnrollment->bind_param("i", $enrollmentId);
            $currentEnrollment->execute();
            $currentResult = $currentEnrollment->get_result();
            $currentRow = $currentResult->fetch_assoc();
            $currentEnrollment->close();

            if (!$currentRow) {
                sms_set_flash("error", "Enrollment record was not found.");
                sms_redirect("enrollment.php");
            }

            $studentId = (int)$currentRow['student_id'];
        }

        if (!sms_is_valid_positive_id($studentId) || !sms_is_valid_positive_id($courseId)) {
            sms_set_flash("error", "Please select a valid student and course.");
            sms_redirect("enrollment.php");
        }

        if (!sms_is_valid_semester($semester)) {
            sms_set_flash("error", "Please enter a valid semester.");
            sms_redirect("enrollment.php");
        }

        if (!sms_is_valid_grade($grade)) {
            sms_set_flash("error", "Please enter a valid grade, such as 1.25, N/A, or leave it blank.");
            sms_redirect("enrollment.php");
        }

        if (enrollment_exists($conn, $studentId, $courseId, $semester, $action === 'update' ? $enrollmentId : 0)) {
            sms_set_flash("error", "This student is already enrolled in this course for the selected semester.");
            sms_redirect("enrollment.php");
        }

        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, semester, grade) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $studentId, $courseId, $semester, $grade);
        } else {
            $stmt = $conn->prepare("UPDATE enrollments SET course_id = ?, semester = ?, grade = ? WHERE enrollment_id = ?");
            $stmt->bind_param("issi", $courseId, $semester, $grade, $enrollmentId);
        }

        if ($stmt->execute()) {
            $logAction = $action === 'create' ? 'enrollment_created' : 'enrollment_updated';
            $flash = $action === 'create' ? 'Enrollment created successfully.' : 'Enrollment updated successfully.';
            sms_log_activity($conn, $logAction, ucfirst($action) . " enrollment record.", sms_current_admin_id());
            sms_set_flash("success", $flash);
        } else {
            sms_set_flash("error", sms_duplicate_message_from_error($stmt->error, "Unable to save enrollment record."));
        }

        $stmt->close();
        sms_redirect("enrollment.php");
    }
}

$studentOptions = [];
$studentStmt = $conn->prepare("SELECT student_id, student_number, fullname FROM students ORDER BY student_number ASC");
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
while ($row = $studentResult->fetch_assoc()) {
    $studentOptions[] = $row;
}
$studentStmt->close();

$courseOptions = [];
$courseStmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name ASC");
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
while ($row = $courseResult->fetch_assoc()) {
    $courseOptions[] = $row;
}
$courseStmt->close();

$enrollments = [];
$enrollmentSql = "
    SELECT e.enrollment_id, e.student_id, e.course_id, e.semester, e.grade,
           s.student_number, s.fullname,
           c.course_code, c.course_name
    FROM enrollments e
    INNER JOIN students s ON s.student_id = e.student_id
    INNER JOIN courses c ON c.course_id = e.course_id
    ORDER BY e.enrollment_id DESC
";
$enrollmentResult = $conn->query($enrollmentSql);
if ($enrollmentResult) {
    while ($row = $enrollmentResult->fetch_assoc()) {
        $enrollments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Enrollment | Student Management System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body class="enrollment-page">
  <div class="portal">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">SMS</div>
        <h1>Student Management System</h1>
        <p>Student records workspace for academic operations.</p>
      </div>

      <nav class="nav-links">
        <a href="index.php" class="">Dashboard</a>
        <a href="registration.php" class="">Student Registration</a>
        <a href="enrollment.php" class="active">Enrollment</a>
        <a href="students.php" class="">Student List</a>
        <a href="profile.php" class="">Profile</a>
        <a href="logout.php" class="logout-link">Logout</a>
      </nav>

      <div class="sidebar-card">
        <h3>System Workspace</h3>
        <p>Manage registration, records, enrollment, and profile information from one workspace.</p>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="page-intro">
          <h2>Enrollment</h2>
          <p>Review saved enrollment records and add records when needed.</p>
        </div>
        <div class="topbar-badge">Enrollment Records</div>
      </header>

      <?php echo sms_flash_html(); ?>

      <section class="card compact-card enrollment-form-card">
        <h2>Create Enrollment</h2>
        <p>Use this page only when an additional enrollment record is needed after registration.</p>

        <form method="POST" action="enrollment.php">
          <input type="hidden" name="enrollment_action" value="create" />
          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>Student and course dropdowns use internal IDs, but show readable school details.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id" required>
                  <option value="">Select student</option>
                  <?php foreach ($studentOptions as $student) { ?>
                    <option value="<?php echo e($student['student_id']); ?>"><?php echo e($student['student_number'] . ' - ' . $student['fullname']); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                  <option value="">Select course</option>
                  <?php foreach ($courseOptions as $course) { ?>
                    <option value="<?php echo e($course['course_id']); ?>"><?php echo e($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" placeholder="Example: 1st Semester" maxlength="50" pattern="[A-Za-z0-9 ._\-]+" required />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" placeholder="Example: 1.25 or N/A" maxlength="10" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" />
              </div>
            </div>
          </div>

          <div class="button-row">
            <button type="submit" class="btn btn-primary">Save Enrollment</button>
          </div>
        </form>
      </section>

      <section class="card compact-card enrollment-records-card">
        <h2>Enrollment Records</h2>
        <p>Saved enrollment details connected to registered student records.</p>

        <div class="table-wrap">
          <table class="enrollment-table">
            <thead>
              <tr>
                <th>Enrollment ID</th>
                <th>Student</th>
                <th>Course</th>
                <th>Semester</th>
                <th>Grade</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($enrollments)) { ?>
                <?php foreach ($enrollments as $row) { ?>
                  <tr>
                    <td><?php echo e($row['enrollment_id']); ?></td>
                    <td>
                      <strong><?php echo e($row['fullname']); ?></strong>
                      <small><?php echo e($row['student_number']); ?></small>
                    </td>
                    <td>
                      <strong><?php echo e($row['course_name']); ?></strong>
                      <small><?php echo e($row['course_code']); ?></small>
                    </td>
                    <td><?php echo display_value($row['semester']); ?></td>
                    <td><?php echo display_value($row['grade'], 'N/A'); ?></td>
                    <td>
                      <form class="inline-action-form" method="POST" action="enrollment.php">
                        <input type="hidden" name="enrollment_action" value="update" />
                        <input type="hidden" name="enrollment_id" value="<?php echo e($row['enrollment_id']); ?>" />
                        <select name="course_id" aria-label="Course" required>
                          <?php foreach ($courseOptions as $course) { ?>
                            <option value="<?php echo e($course['course_id']); ?>" <?php echo (int)$row['course_id'] === (int)$course['course_id'] ? 'selected' : ''; ?>>
                              <?php echo e($course['course_code']); ?>
                            </option>
                          <?php } ?>
                        </select>
                        <input name="semester" type="text" value="<?php echo e($row['semester']); ?>" maxlength="50" pattern="[A-Za-z0-9 ._\-]+" required />
                        <input name="grade" type="text" value="<?php echo e($row['grade']); ?>" maxlength="10" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" />
                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Save changes to this enrollment?');">Save</button>
                      </form>
                      <form class="inline-action-form" method="POST" action="enrollment.php" onsubmit="return confirm('Delete this enrollment record?');">
                        <input type="hidden" name="enrollment_action" value="delete" />
                        <input type="hidden" name="enrollment_id" value="<?php echo e($row['enrollment_id']); ?>" />
                        <button type="submit" class="btn btn-danger">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
              <?php } else { ?>
                <tr>
                  <td colspan="6">No enrollment records found.</td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
