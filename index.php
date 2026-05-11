<?php
include 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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

function table_exists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function first_existing_column($conn, $table, $columns) {
    $table = $conn->real_escape_string($table);
    foreach ($columns as $column) {
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$safeColumn'");
        if ($result && $result->num_rows > 0) {
            return $column;
        }
    }
    return null;
}

$totalStudents = (int) single_value($conn, "SELECT COUNT(*) AS value FROM students");

$totalCourses = (int) single_value($conn, "
    SELECT COUNT(*) AS value
    FROM (
        SELECT course_name AS course_title FROM courses WHERE TRIM(course_name) <> ''
        UNION
        SELECT course AS course_title FROM students WHERE TRIM(course) <> ''
    ) AS course_source
");

$yearColumn = first_existing_column($conn, 'students', ['year_level', 'yearlevel', 'year']);
$totalYearLevels = 0;
if ($yearColumn !== null) {
    $totalYearLevels = (int) single_value($conn, "SELECT COUNT(DISTINCT `$yearColumn`) AS value FROM students WHERE TRIM(`$yearColumn`) <> ''");
}

$recentStudents = [];
$recentSql = "
    SELECT s.student_id, s.fullname, s.email, s.course,
           (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id) AS enrollment_count
    FROM students s
    ORDER BY s.student_id DESC
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
    SELECT course, COUNT(*) AS total
    FROM students
    WHERE TRIM(course) <> ''
    GROUP BY course
    ORDER BY total DESC, course ASC
    LIMIT 1
");
if ($courseResult && $courseRow = $courseResult->fetch_assoc()) {
    $mostPopulatedCourse = $courseRow['course'];
}

$primaryEnrollmentWindow = 'No enrollment semester recorded';
if (table_exists($conn, 'enrollments')) {
    $semesterResult = $conn->query("
        SELECT semester, COUNT(*) AS total
        FROM enrollments
        WHERE TRIM(semester) <> ''
        GROUP BY semester
        ORDER BY total DESC, semester DESC
        LIMIT 1
    ");
    if ($semesterResult && $semesterRow = $semesterResult->fetch_assoc()) {
        $primaryEnrollmentWindow = $semesterRow['semester'];
    }
}

$recordStatus = $totalStudents > 0
    ? number_format($totalStudents) . ' student record' . ($totalStudents === 1 ? '' : 's') . ' stored'
    : 'No student records yet';

$yearSummaryNote = $yearColumn !== null
    ? 'Distinct saved year-level values in student records.'
    : 'No year-level field exists in the current database.';
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
        <a href="students.php" class="">Student List</a>
        <a href="profile.php" class="">Profile</a>
      </nav>

      <div class="sidebar-card">
        <h3>System Workspace</h3>
        <p>Manage registration, records, and profile information from one workspace.</p>
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

<section class="grid-4">
  <article class="summary-card">
    <div class="summary-label">Total Students</div>
    <div class="summary-value"><?php echo number_format($totalStudents); ?></div>
    <div class="summary-note">Active student records in the system.</div>
  </article>
  <article class="summary-card">
    <div class="summary-label">Courses</div>
    <div class="summary-value"><?php echo number_format($totalCourses); ?></div>
    <div class="summary-note">Unique courses currently stored in the database.</div>
  </article>
  <article class="summary-card">
    <div class="summary-label">Year Levels</div>
    <div class="summary-value"><?php echo number_format($totalYearLevels); ?></div>
    <div class="summary-note"><?php echo e($yearSummaryNote); ?></div>
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
          ?>
          <div class="list-item">
            <div>
              <h4>#<?php echo e($student['student_id']); ?> - <?php echo display_value($student['fullname']); ?></h4>
              <p><?php echo display_value($student['course'], 'Course not recorded'); ?> - <?php echo display_value($student['email'], 'Email not recorded'); ?></p>
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
          <small>Primary Enrollment Window</small>
          <strong><?php echo e($primaryEnrollmentWindow); ?></strong>
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
