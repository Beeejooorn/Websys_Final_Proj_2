<?php
include 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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
        <a href="index.php" class="">Dashboard</a>
        <a href="registration.php" class="">Student Registration</a>
        <a href="enrollment.php" class="active">Enrollment</a>
        <a href="students.php" class="">Student List</a>
        <a href="profile.php" class="">Profile</a>
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
          <p>Review saved enrollment records created from student registration and updates.</p>
        </div>
        <div class="topbar-badge">Enrollment Records</div>
      </header>

      <section class="card section-card">
        <div class="section-title">
          <div>
            <h3>Enrollment Records</h3>
            <p>Saved enrollment details connected to registered student records.</p>
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
                <th>Actions</th>
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
                    <td>
                      <div class="action-links">
                        <a href="edit.php?id=<?php echo e($enrollment['student_id']); ?>">Edit</a>
                      </div>
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
