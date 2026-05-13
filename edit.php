<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function e($value) {
    return sms_escape($value ?? '');
}

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!sms_is_valid_positive_id($studentId)) {
    sms_set_flash("error", "Invalid student record.");
    sms_redirect("students.php");
}

$studentStmt = $conn->prepare("
    SELECT s.student_id, s.student_number, s.fullname, s.email, s.course_id, s.year_level, s.birthdate, s.contact, s.address,
           c.course_code, c.course_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.course_id
    WHERE s.student_id = ?
    LIMIT 1
");
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$student = $studentResult->fetch_assoc();
$studentStmt->close();

if (!$student) {
    sms_set_flash("error", "Student record was not found.");
    sms_redirect("students.php");
}

$enrollment = [
    'enrollment_id' => 0,
    'semester' => '',
    'grade' => ''
];

$enrollmentStmt = $conn->prepare("SELECT enrollment_id, semester, grade FROM enrollments WHERE student_id = ? ORDER BY enrollment_id DESC LIMIT 1");
$enrollmentStmt->bind_param("i", $studentId);
$enrollmentStmt->execute();
$enrollmentResult = $enrollmentStmt->get_result();
if ($row = $enrollmentResult->fetch_assoc()) {
    $enrollment = $row;
}
$enrollmentStmt->close();

$courseOptions = [];
$courseStmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name ASC");
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
while ($row = $courseResult->fetch_assoc()) {
    $courseOptions[] = $row;
}
$courseStmt->close();

$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Student | Student Management System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
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
        <a href="enrollment.php" class="">Enrollment</a>
        <a href="students.php" class="active">Student List</a>
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
          <h2>Edit Student</h2>
          <p>Update student profile and enrollment details in one place.</p>
        </div>
        <div class="topbar-badge">Student Record</div>
      </header>

      <section class="card">
        <h2>Edit Student Form</h2>
        <p>The visible student number can be updated, but the internal database ID remains unchanged.</p>

        <?php echo sms_flash_html(); ?>

        <form method="POST" action="update.php">
          <input type="hidden" name="student_id" value="<?php echo e($student['student_id']); ?>" />
          <input type="hidden" name="enrollment_id" value="<?php echo e($enrollment['enrollment_id']); ?>" />

          <div class="form-section">
            <h4>Personal Information</h4>
            <p>Update the student details saved in the students table.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="student_number">Student Number</label>
                <input id="student_number" name="student_number" type="text" value="<?php echo e($student['student_number']); ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required />
              </div>

              <div class="form-group">
                <label for="fullname">Full Name</label>
                <input id="fullname" name="fullname" type="text" value="<?php echo e($student['fullname']); ?>" maxlength="100" pattern="[A-Za-z .'\-]{2,100}" required />
              </div>

              <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                  <option value="">Select course</option>
                  <?php foreach ($courseOptions as $course) { ?>
                    <option value="<?php echo e($course['course_id']); ?>" <?php echo (int)$student['course_id'] === (int)$course['course_id'] ? 'selected' : ''; ?>>
                      <?php echo e($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="year_level">Year Level</label>
                <select id="year_level" name="year_level" required>
                  <option value="">Select year level</option>
                  <?php foreach (sms_valid_year_levels() as $level) { ?>
                    <option value="<?php echo e($level); ?>" <?php echo $student['year_level'] === $level ? 'selected' : ''; ?>><?php echo e($level); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="birthdate">Birthdate</label>
                <input id="birthdate" name="birthdate" type="date" value="<?php echo e($student['birthdate']); ?>" max="<?php echo e($today); ?>" required />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h4>Contact Information</h4>
            <p>Update email, contact number, and address.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" value="<?php echo e($student['email']); ?>" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" required />
              </div>

              <div class="form-group">
                <label for="contact">Contact Number</label>
                <input id="contact" name="contact" type="text" value="<?php echo e($student['contact']); ?>" inputmode="tel" maxlength="20" pattern="\+?[0-9][0-9\s-]{6,19}" required />
              </div>

              <div class="form-group full-span">
                <label for="address">Address</label>
                <textarea id="address" name="address" maxlength="500" required><?php echo e($student['address']); ?></textarea>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>Update the latest enrollment record connected to this student.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" value="<?php echo e($enrollment['semester']); ?>" maxlength="50" pattern="[A-Za-z0-9 ._\-]+" required />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" value="<?php echo e($enrollment['grade']); ?>" maxlength="10" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" />
              </div>
            </div>
          </div>

          <div class="button-row">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="students.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
