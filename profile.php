<?php
include 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function single_value($conn, $sql, $default = 0) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['value'] ?? $default;
    }
    return $default;
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
$latestStudent = 'No student records yet';
$latestResult = $conn->query("SELECT fullname FROM students ORDER BY student_id DESC LIMIT 1");
if ($latestResult && $latestRow = $latestResult->fetch_assoc()) {
    $latestStudent = trim($latestRow['fullname']) !== '' ? $latestRow['fullname'] : 'Unnamed student record';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile | Student Management System</title>
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
        <a href="students.php" class="">Student List</a>
        <a href="profile.php" class="active">Profile</a>
      </nav>

      <div class="sidebar-card">
        <h3>System Workspace</h3>
        <p>Manage registration, records, and profile information from one workspace.</p>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="page-intro">
          <h2>Profile</h2>
          <p>General profile overview for the student management system.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

<section class="profile-layout">
  <aside class="card profile-card">
    <div class="avatar">SMS</div>
    <h3>Student Management System</h3>
    <p>Academic Records Portal</p>

    <div class="profile-meta">
      <div class="meta-row">
        <small>Workspace</small>
        <strong>Student Records</strong>
      </div>
      <div class="meta-row">
        <small>Status</small>
        <strong>Active</strong>
      </div>
      <div class="meta-row">
        <small>Access</small>
        <strong>Local Website</strong>
      </div>
    </div>
  </aside>

  <section class="details-grid">
    <div class="card section-card">
      <div class="section-title">
        <div>
          <h3>System Information</h3>
          <p>Current database summary for the website.</p>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-box">
          <small>Total Students</small>
          <strong><?php echo number_format($totalStudents); ?></strong>
        </div>
        <div class="info-box">
          <small>Courses</small>
          <strong><?php echo number_format($totalCourses); ?></strong>
        </div>
        <div class="info-box">
          <small>Latest Record</small>
          <strong><?php echo e($latestStudent); ?></strong>
        </div>
        <div class="info-box">
          <small>Database</small>
          <strong>Connected</strong>
        </div>
      </div>
    </div>

    <div class="card section-card">
      <div class="section-title">
        <div>
          <h3>Available Pages</h3>
          <p>Main sections available in the student management workspace.</p>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-box">
          <small>Dashboard</small>
          <strong><a href="index.php">Open</a></strong>
        </div>
        <div class="info-box">
          <small>Registration</small>
          <strong><a href="registration.php">Open</a></strong>
        </div>
        <div class="info-box">
          <small>Student List</small>
          <strong><a href="students.php">Open</a></strong>
        </div>
        <div class="info-box">
          <small>Profile</small>
          <strong>Current Page</strong>
        </div>
      </div>
    </div>
  </section>
</section>
    </main>
  </div>
</body>
</html>
