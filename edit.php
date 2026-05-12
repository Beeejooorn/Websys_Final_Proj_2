<?php
include 'auth_check.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Student not found.");
}

$student_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");

if (!$student_stmt) {
    die("Unable to prepare student lookup.");
}

$student_stmt->bind_param("i", $id);
$student_stmt->execute();
$result = $student_stmt->get_result();
$row = $result->fetch_assoc();
$student_stmt->close();

if (!$row) {
    die("Student not found.");
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errorMessage = "";

$errorMessages = [
    'invalid_name' => "Full name should contain only letters, spaces, hyphens, apostrophes, or periods.",
    'invalid_email' => "Please enter a valid Gmail address, for example student@gmail.com.",
    'invalid_course' => "Please select a valid course.",
    'invalid_year_level' => "Please select a valid year level.",
    'invalid_birthdate' => "Please enter a valid birthdate that is not in the future.",
    'invalid_contact' => "Contact number may contain only numbers, spaces, dashes, or an optional + at the start.",
    'invalid_semester' => "Semester is required and may contain only letters, numbers, spaces, periods, underscores, or dashes.",
    'invalid_grade' => "Grade should be a number, N/A, or INC.",
    'save_failed' => "The student record was not updated. Please check the form values and try again."
];

$errorCode = $_GET['error'] ?? '';

if (isset($errorMessages[$errorCode])) {
    $errorMessage = $errorMessages[$errorCode];
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

$enrollment = [
    'enrollment_id' => '',
    'semester' => '',
    'grade' => ''
];

$enrollment_stmt = $conn->prepare("
    SELECT enrollment_id, semester, grade
    FROM enrollments
    WHERE student_id = ?
    ORDER BY enrollment_id DESC
    LIMIT 1
");

if ($enrollment_stmt) {
    $enrollment_stmt->bind_param("i", $id);
    $enrollment_stmt->execute();
    $enrollment_result = $enrollment_stmt->get_result();

    if ($enrollment_result && $enrollment_result->num_rows > 0) {
        $enrollment = $enrollment_result->fetch_assoc();
    }

    $enrollment_stmt->close();
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
          <p>Update selected student record using the current saved database values.</p>
        </div>
        <div class="topbar-badge">Update Operation</div>
      </header>

      <section class="card section-card">
        <?php if ($errorMessage !== "") { ?>
          <p class="error-message"><?php echo e($errorMessage); ?></p>
        <?php } ?>

        <div class="section-title">
          <div>
            <h3>Edit Student Form</h3>
            <p>Change the student information below, then submit to save the update.</p>
          </div>
        </div>

        <form method="POST" action="update.php">
          <input type="hidden" name="student_id" value="<?php echo e($row['student_id']); ?>">
          <input type="hidden" name="enrollment_id" value="<?php echo e($enrollment['enrollment_id']); ?>">

          <div class="form-section">
            <h4>Student Information</h4>
            <p>These values are loaded from the database.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="fullname">Full Name</label>
                <input id="fullname" name="fullname" type="text" value="<?php echo e($row['fullname']); ?>" pattern="[A-Za-z .'\-]{2,100}" title="Use letters, spaces, hyphens, apostrophes, or periods only" required />
              </div>

              <div class="form-group">
                  <label for="course">Course</label>
                  <select id="course" name="course" required>
                    <?php foreach ($courseOptions as $courseOption) { ?>
                      <option value="<?php echo e($courseOption); ?>" <?php if ($row['course'] == $courseOption) echo 'selected'; ?>>
                        <?php echo e($courseOption); ?>
                      </option>
                    <?php } ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="yearlevel">Year Level</label>
                  <select id="yearlevel" name="year_level" required>
                    <option value="">Select year level</option>
                    <option value="1st Year" <?php if ($row['year_level'] == '1st Year') echo 'selected'; ?>>1st Year</option>
                    <option value="2nd Year" <?php if ($row['year_level'] == '2nd Year') echo 'selected'; ?>>2nd Year</option>
                    <option value="3rd Year" <?php if ($row['year_level'] == '3rd Year') echo 'selected'; ?>>3rd Year</option>
                    <option value="4th Year" <?php if ($row['year_level'] == '4th Year') echo 'selected'; ?>>4th Year</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="birthdate">Birthdate</label>
                  <input id="birthdate" name="birthdate" type="date" value="<?php echo e($row['birthdate']); ?>" max="<?php echo date('Y-m-d'); ?>" required />
                </div>

                <div class="form-group">
                  <label for="email">Email Address</label>
                  <input id="email" name="email" type="email" value="<?php echo e($row['email']); ?>" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" title="Enter a valid Gmail address, for example student@gmail.com" required />
                </div>

                <div class="form-group">
                  <label for="contact">Contact Number</label>
                  <input id="contact" name="contact" type="tel" value="<?php echo e($row['contact']); ?>" pattern="\+?[0-9][0-9\s-]{6,19}" title="Use numbers only, with optional spaces, dashes, or + at the start" />
                </div>

                <div class="form-group full">
                  <label for="address">Address</label>
                  <textarea id="address" name="address"><?php echo e($row['address']); ?></textarea>
                </div>
            </div>
          </div>

          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>These values are loaded from the student's saved enrollment record.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" value="<?php echo e($enrollment['semester']); ?>" placeholder="Example: 1st Semester" pattern="[A-Za-z0-9 ._\-]{2,50}" title="Use letters, numbers, spaces, periods, underscores, or dashes" />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" value="<?php echo e($enrollment['grade']); ?>" placeholder="Example: 1.25 or N/A" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" title="Use a number, N/A, or INC" />
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
