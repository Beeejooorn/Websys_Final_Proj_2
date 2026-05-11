<?php
include 'db_connect.php';

$message = "";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = trim($_POST['student_id'] ?? '');
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $course = $_POST['course'] ?? '';

    if ($student_id === '' || !ctype_digit($student_id) || (int)$student_id <= 0) {
        $message = "Please enter a valid numeric Student ID.";
    } else {
        $student_id = (int)$student_id;
        $check_student = $conn->query("SELECT student_id FROM students WHERE student_id = $student_id");

        if ($check_student && $check_student->num_rows > 0) {
            $message = "Student ID already exists. Please enter a different Student ID.";
        } else {
            $password_column = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
            $has_password_column = $password_column && $password_column->num_rows > 0;
            $sql_user = $has_password_column
                ? "INSERT INTO users (email, password) VALUES ('$email', '')"
                : "INSERT INTO users (email) VALUES ('$email')";

            if ($conn->query($sql_user) === TRUE) {
                $user_id = $conn->insert_id;

                $sql_student = "INSERT INTO students (student_id, user_id, fullname, email, course) 
                                VALUES ('$student_id', '$user_id', '$fullname', '$email', '$course')";

                if ($conn->query($sql_student) === TRUE) {
                    $message = "Student registered successfully";
                } else {
                    $message = "Error: " . $conn->error;
                }
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}

$defaultCourseOptions = [
    'BS Information Technology',
    'BS Computer Science',
    'BS Business Administration',
    'BS Education'
];
$courseOptions = $defaultCourseOptions;
$course_sql = "
    SELECT course_name AS course_name FROM courses WHERE TRIM(course_name) <> ''
    UNION
    SELECT course AS course_name FROM students WHERE TRIM(course) <> ''
    ORDER BY course_name
";
$course_result = $conn->query($course_sql);
if ($course_result) {
    while ($course_row = $course_result->fetch_assoc()) {
        if (!in_array($course_row['course_name'], $courseOptions, true)) {
            $courseOptions[] = $course_row['course_name'];
        }
    }
}

$defaultYearLevelOptions = [
    '1st Year',
    '2nd Year',
    '3rd Year',
    '4th Year'
];
$yearLevelOptions = $defaultYearLevelOptions;
$yearLevelColumn = null;
foreach (['year_level', 'yearlevel', 'year'] as $possibleColumn) {
    $safeColumn = $conn->real_escape_string($possibleColumn);
    $column_result = $conn->query("SHOW COLUMNS FROM students LIKE '$safeColumn'");
    if ($column_result && $column_result->num_rows > 0) {
        $yearLevelColumn = $possibleColumn;
        break;
    }
}

if ($yearLevelColumn !== null) {
    $year_result = $conn->query("SELECT DISTINCT `$yearLevelColumn` AS year_level FROM students WHERE TRIM(`$yearLevelColumn`) <> '' ORDER BY `$yearLevelColumn`");
    if ($year_result) {
        while ($year_row = $year_result->fetch_assoc()) {
            if (!in_array($year_row['year_level'], $yearLevelOptions, true)) {
                $yearLevelOptions[] = $year_row['year_level'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Registration | Student Management System</title>
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
        <a href="registration.php" class="active">Student Registration</a>
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
          <h2>Student Registration</h2>
          <p>A visually organized registration form layout for adding new student records.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

<section class="card section-card">

  <?php if ($message != "") { ?>
  <p><?php echo e($message); ?></p>
<?php } ?>

  <div class="section-title">
    <div>
      <h3>Student Registration Form</h3>
      <p>Structured fields grouped clearly for a clean and readable registration layout.</p>
    </div>
  </div>

  <form method="POST" action="registration.php">
    <div class="form-section">
      <h4>Personal Information</h4>
      <p>Basic student details for record creation.</p>
      <div class="form-grid">
        <div class="form-group">
          <label for="fullname">Full Name</label>
          <input id="fullname" name="fullname" type="text" placeholder="Enter full name" />
        </div>
        <div class="form-group">
          <label for="studentid">Student ID</label>
          <input id="studentid" name="student_id" type="number" min="1" step="1" placeholder="Enter student ID" required />
        </div>
        <div class="form-group">
          <label for="course">Course</label>
          <select id="course" name="course">
            <option value="">Select course</option>
            <?php foreach ($courseOptions as $courseOption) { ?>
              <option value="<?php echo e($courseOption); ?>"><?php echo e($courseOption); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="form-group">
          <label for="yearlevel">Year Level</label>
          <select id="yearlevel">
            <option>Select year level</option>
            <?php foreach ($yearLevelOptions as $yearLevelOption) { ?>
              <option><?php echo e($yearLevelOption); ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h4>Contact Information</h4>
      <p>Communication details and address section.</p>
      <div class="form-grid">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input id="email" name="email" type="email" placeholder="Enter email address" />
        </div>
        <div class="form-group">
          <label for="contact">Contact Number</label>
          <input id="contact" type="text" placeholder="Enter contact number" />
        </div>
        <div class="form-group full">
          <label for="address">Address</label>
          <textarea id="address" placeholder="Enter complete address"></textarea>
        </div>
      </div>
    </div>

    <div class="button-row">
     <button type="submit" class="btn btn-primary">Register Student</button>
      <button type="reset" class="btn btn-secondary">Clear Form</button>
    </div>
  </form>
</section>
    </main>
  </div>
</body>
</html>
