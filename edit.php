<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db_connect.php';

$id = $_GET['id'];

$sql = "SELECT * FROM students WHERE student_id = $id";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$courseOptions = [];
$course_sql = "
    SELECT course_name AS course_name FROM courses WHERE TRIM(course_name) <> ''
    UNION
    SELECT course AS course_name FROM students WHERE TRIM(course) <> ''
    ORDER BY course_name
";
$course_result = $conn->query($course_sql);
if ($course_result) {
    while ($course_row = $course_result->fetch_assoc()) {
        $courseOptions[] = $course_row['course_name'];
    }
}

if (!empty($row['course']) && !in_array($row['course'], $courseOptions, true)) {
    $courseOptions[] = $row['course'];
}
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
        <a href="students.php" class="active">Student List</a>
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
          <h2>Edit Student</h2>
          <p>Update selected student record using the current saved database values.</p>
        </div>
        <div class="topbar-badge">Update Operation</div>
      </header>

      <section class="card section-card">
        <div class="section-title">
          <div>
            <h3>Edit Student Form</h3>
            <p>Change the student information below, then submit to save the update.</p>
          </div>
        </div>

        <form method="POST" action="update.php">
          <input type="hidden" name="student_id" value="<?php echo e($row['student_id']); ?>">

          <div class="form-section">
            <h4>Student Information</h4>
            <p>These values are loaded from the database.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="fullname">Full Name</label>
                <input id="fullname" name="fullname" type="text" value="<?php echo e($row['fullname']); ?>" />
              </div>

              <div class="form-group">
                  <label for="course">Course</label>
                  <select id="course" name="course">
                    <?php foreach ($courseOptions as $courseOption) { ?>
                      <option value="<?php echo e($courseOption); ?>" <?php if ($row['course'] == $courseOption) echo 'selected'; ?>>
                        <?php echo e($courseOption); ?>
                      </option>
                    <?php } ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="yearlevel">Year Level</label>
                  <select id="yearlevel" name="year_level">
                    <option value="">Select year level</option>
                    <option value="1st Year" <?php if ($row['year_level'] == '1st Year') echo 'selected'; ?>>1st Year</option>
                    <option value="2nd Year" <?php if ($row['year_level'] == '2nd Year') echo 'selected'; ?>>2nd Year</option>
                    <option value="3rd Year" <?php if ($row['year_level'] == '3rd Year') echo 'selected'; ?>>3rd Year</option>
                    <option value="4th Year" <?php if ($row['year_level'] == '4th Year') echo 'selected'; ?>>4th Year</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="email">Email Address</label>
                  <input id="email" name="email" type="email" value="<?php echo e($row['email']); ?>" />
                </div>

                <div class="form-group">
                  <label for="contact">Contact Number</label>
                  <input id="contact" name="contact" type="text" value="<?php echo e($row['contact']); ?>" />
                </div>

                <div class="form-group full">
                  <label for="address">Address</label>
                  <textarea id="address" name="address"><?php echo e($row['address']); ?></textarea>
                </div>
            </div>
          </div>

          <div class="button-row">
            <button type="submit" class="btn btn-primary">Update Student</button>
            <a href="students.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
