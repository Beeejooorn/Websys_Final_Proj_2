<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function e($value) {
    return sms_escape($value ?? '');
}

function display_value($value, $fallback = 'Not recorded') {
    $value = trim((string)($value ?? ''));
    return $value !== '' && $value !== '0000-00-00' ? e($value) : $fallback;
}

function single_value($conn, $sql, $default = 0) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['value'] ?? $default;
    }
    return $default;
}

$totalStudents = (int) single_value($conn, "SELECT COUNT(*) AS value FROM students");
$totalCourses = (int) single_value($conn, "SELECT COUNT(*) AS value FROM courses");
$totalYearLevels = (int) single_value($conn, "SELECT COUNT(DISTINCT year_level) AS value FROM students WHERE TRIM(year_level) <> ''");

$recentStudents = [];
$recentSql = "
    SELECT sd.internal_student_id, sd.student_number, sd.fullname, sd.email, sd.year_level,
           sd.course_code, sd.course_name,
           COUNT(e.enrollment_id) AS enrollment_count
    FROM v_student_display sd
    LEFT JOIN enrollments e ON e.student_id = sd.internal_student_id
    GROUP BY sd.internal_student_id, sd.student_number, sd.fullname, sd.email, sd.year_level, sd.course_code, sd.course_name
    ORDER BY sd.internal_student_id DESC
    LIMIT 4
";
$recentResult = $conn->query($recentSql);
if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentStudents[] = $row;
    }
}
$recentEntries = count($recentStudents);

$mostPopulatedCourse = 'No course data recorded';
$courseResult = $conn->query("
    SELECT c.course_code, c.course_name, COUNT(s.student_id) AS total
    FROM courses c
    INNER JOIN students s ON s.course_id = c.course_id
    GROUP BY c.course_id, c.course_code, c.course_name
    ORDER BY total DESC, c.course_name ASC
    LIMIT 1
");
if ($courseResult && $courseRow = $courseResult->fetch_assoc()) {
    $courseTotal = (int)$courseRow['total'];
    $mostPopulatedCourse = $courseRow['course_code'] . ' - ' . $courseRow['course_name'] . ' (' . number_format($courseTotal) . ' student' . ($courseTotal === 1 ? '' : 's') . ')';
}

$largestYearLevel = 'No year-level data recorded';
$yearLevelResult = $conn->query("
    SELECT TRIM(year_level) AS year_level_title, COUNT(*) AS total
    FROM students
    WHERE TRIM(year_level) <> ''
    GROUP BY TRIM(year_level)
    ORDER BY total DESC, year_level_title ASC
    LIMIT 1
");
if ($yearLevelResult && $yearLevelRow = $yearLevelResult->fetch_assoc()) {
    $yearLevelTotal = (int)$yearLevelRow['total'];
    $largestYearLevel = $yearLevelRow['year_level_title'] . ' (' . number_format($yearLevelTotal) . ' student' . ($yearLevelTotal === 1 ? '' : 's') . ')';
}

$recordStatus = $totalStudents > 0
    ? number_format($totalStudents) . ' student record' . ($totalStudents === 1 ? '' : 's') . ' stored'
    : 'No student records yet';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | Student Management System</title>
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
        <a href="index.php" class="active">Dashboard</a>
        <a href="registration.php" class="">Student Registration</a>
        <a href="enrollment.php" class="">Enrollment</a>
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
          <h2>Dashboard</h2>
          <p>A clean overview of student records, enrollment activity, and basic academic summaries.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

      <?php echo sms_flash_html(); ?>

      <section class="grid-4">
        <article class="summary-card">
          <div class="summary-label">Total Students</div>
          <div class="summary-value"><?php echo number_format($totalStudents); ?></div>
          <div class="summary-note">Active student records in the system.</div>
        </article>
        <article class="summary-card">
          <div class="summary-label">Courses</div>
          <div class="summary-value"><?php echo number_format($totalCourses); ?></div>
          <div class="summary-note">Courses stored in the courses table.</div>
        </article>
        <article class="summary-card">
          <div class="summary-label">Year Levels</div>
          <div class="summary-value"><?php echo number_format($totalYearLevels); ?></div>
          <div class="summary-note">Distinct saved year-level values in student records.</div>
        </article>
        <article class="summary-card">
          <div class="summary-label">Recent Entries</div>
          <div class="summary-value"><?php echo number_format($recentEntries); ?></div>
          <div class="summary-note">Latest student records loaded from the database.</div>
        </article>
      </section>

      <section class="layout-2">
        <div class="card section-card">
          <div class="section-title">
            <div>
              <h3>Recent Student Entries</h3>
              <p>A simple overview of newly added student records.</p>
            </div>
          </div>
          <div class="list-box">
            <?php if (!empty($recentStudents)) { ?>
              <?php foreach ($recentStudents as $student) { ?>
                <?php
                  $statusLabel = ((int)$student['enrollment_count'] > 0) ? 'Enrolled' : 'Registered';
                  $statusClass = ((int)$student['enrollment_count'] > 0) ? 'success' : 'warning';
                  $courseLabel = trim(($student['course_code'] ?? '') . ' - ' . ($student['course_name'] ?? ''), ' -');
                ?>
                <div class="list-item">
                  <div>
                    <h4><?php echo e($student['student_number']); ?> - <?php echo display_value($student['fullname']); ?></h4>
                    <p><?php echo display_value($courseLabel, 'Course not recorded'); ?> - <?php echo display_value($student['email'], 'Email not recorded'); ?></p>
                  </div>
                  <span class="pill <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
                </div>
              <?php } ?>
            <?php } else { ?>
              <div class="list-item">
                <div>
                  <h4>No student records yet</h4>
                  <p>New registrations will appear here automatically.</p>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>

        <div class="info-stack">
          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>System Overview</h3>
                <p>Core data points relevant to student management.</p>
              </div>
            </div>
            <div class="info-stack">
              <div class="info-tile">
                <small>Largest Year Level</small>
                <strong><?php echo e($largestYearLevel); ?></strong>
              </div>
              <div class="info-tile">
                <small>Most Populated Course</small>
                <strong><?php echo e($mostPopulatedCourse); ?></strong>
              </div>
              <div class="info-tile">
                <small>Current Record Status</small>
                <strong><?php echo e($recordStatus); ?></strong>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
