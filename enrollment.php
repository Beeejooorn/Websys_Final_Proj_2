<?php
include 'db_connect.php';

$message = "";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $grade = $_POST['grade'] ?? '';

    if ($student_id === '' || $course_id === '' || $semester === '') {
        $message = "Please select a student, course, and semester.";
    } else {
        $student_id = (int)$student_id;
        $course_id = (int)$course_id;

        $sql = "INSERT INTO enrollments (student_id, course_id, semester, grade)
                VALUES ('$student_id', '$course_id', '$semester', '$grade')";

        if ($conn->query($sql) === TRUE) {
            $message = "Student enrolled successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

$students = [];
$student_result = $conn->query("SELECT student_id, fullname, email FROM students ORDER BY user_id DESC");

if ($student_result) {
    while ($row = $student_result->fetch_assoc()) {
        $students[] = $row;
    }
}

$courses = [];
$course_result = $conn->query("SELECT course_id, course_name, course_code FROM courses ORDER BY course_name ASC");

if ($course_result) {
    while ($row = $course_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

$enrollments = [];
$enrollment_sql = "
    SELECT 
        e.enrollment_id,
        s.fullname,
        s.student_id,
        c.course_name,
        c.course_code,
        e.semester,
        e.grade
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.student_id
    INNER JOIN courses c ON e.course_id = c.course_id
    ORDER BY e.enrollment_id DESC
";

$enrollment_result = $conn->query($enrollment_sql);

if ($enrollment_result) {
    while ($row = $enrollment_result->fetch_assoc()) {
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
<body>
  <div class="portal">

    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">SMS</div>
        <h1>Student Management System</h1>
        <p>Student records workspace for academic operations.</p>
      </div>

      <nav class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="registration.php">Student Registration</a>
        <a href="students.php">Student List</a>
        <a href="enrollment.php" class="active">Enrollment</a>
        <a href="profile.php">Profile</a>
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
          <p>Enroll registered students into courses and track semester information.</p>
        </div>
        <div class="topbar-badge">Enrollment Module</div>
      </header>

      <section class="card section-card">
        <?php if ($message != "") { ?>
          <p><?php echo e($message); ?></p>
        <?php } ?>

        <div class="section-title">
          <div>
            <h3>Enroll Student</h3>
            <p>Select a student, assign a course, and save enrollment details.</p>
          </div>
        </div>

        <form method="POST" action="enrollment.php">
          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>This will create a new record in the enrollments table.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id" required>
                  <option value="">Select student</option>
                  <?php foreach ($students as $student) { ?>
                    <option value="<?php echo e($student['student_id']); ?>">
                      <?php echo e($student['fullname']); ?> - ID: <?php echo e($student['student_id']); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                  <option value="">Select course</option>
                  <?php foreach ($courses as $course) { ?>
                    <option value="<?php echo e($course['course_id']); ?>">
                      <?php echo e($course['course_name']); ?> (<?php echo e($course['course_code']); ?>)
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" placeholder="Example: 1st Semester" required />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" placeholder="Example: 1.25 or N/A" />
              </div>
            </div>
          </div>

          <div class="button-row">
            <button type="submit" class="btn btn-primary">Enroll Student</button>
            <button type="reset" class="btn btn-secondary">Clear Form</button>
          </div>
        </form>
      </section>

      <section class="card section-card" style="margin-top: 24px;">
        <div class="section-title">
          <div>
            <h3>Enrollment Records</h3>
            <p>Saved enrollment records from the database.</p>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Enrollment ID</th>
                <th>Student</th>
                <th>Course</th>
                <th>Semester</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($enrollments)) { ?>
                <?php foreach ($enrollments as $enrollment) { ?>
                  <tr>
                    <td><?php echo e($enrollment['enrollment_id']); ?></td>
                    <td>
                      <div class="student-name"><?php echo e($enrollment['fullname']); ?></div>
                      <div class="student-sub">ID: <?php echo e($enrollment['student_id']); ?></div>
                    </td>
                    <td>
                      <?php echo e($enrollment['course_name']); ?>
                      <div class="student-sub"><?php echo e($enrollment['course_code']); ?></div>
                    </td>
                    <td><?php echo e($enrollment['semester']); ?></td>
                    <td><?php echo e($enrollment['grade']); ?></td>
                  </tr>
                <?php } ?>
              <?php } else { ?>
                <tr>
                  <td colspan="5">No enrollment records found.</td>
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