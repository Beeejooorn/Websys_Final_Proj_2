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

$enrollments = [];
$enrollmentSql = "
    SELECT student_number, fullname, course_code, course_name, semester, grade
    FROM v_enrollment_display
    ORDER BY enrollment_id DESC
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
        <div class="brand-mark" aria-hidden="true">SMS</div>
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
          <p>Review enrollment records created from student registration and student updates.</p>
        </div>
        <div class="topbar-badge">Enrollment Records</div>
      </header>

      <?php echo sms_flash_html(); ?>

      <section class="card compact-card enrollment-records-card">
        <h2>Enrollment Records</h2>
        <p>Saved academic enrollment details connected to each student's visible school ID.</p>

        <div class="table-wrap">
          <table class="enrollment-table">
            <thead>
              <tr>
                <th>No.</th>
                <th>Student ID</th>
                <th>Student</th>
                <th>Course</th>
                <th>Semester</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($enrollments)) { ?>
                <?php foreach ($enrollments as $index => $row) { ?>
                  <tr>
                    <td><span class="display-number"><?php echo e(sprintf('%03d', $index + 1)); ?></span></td>
                    <td><strong><?php echo e($row['student_number']); ?></strong></td>
                    <td>
                      <strong><?php echo e($row['fullname']); ?></strong>
                    </td>
                    <td>
                      <strong><?php echo e($row['course_name']); ?></strong>
                      <small><?php echo e($row['course_code']); ?></small>
                    </td>
                    <td><?php echo display_value($row['semester']); ?></td>
                    <td><?php echo display_value($row['grade'], 'N/A'); ?></td>
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
