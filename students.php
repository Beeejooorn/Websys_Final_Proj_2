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

$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course_id'] ?? '');

$courseOptions = [];
$courseStmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name ASC");
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
while ($row = $courseResult->fetch_assoc()) {
    $courseOptions[] = $row;
}
$courseStmt->close();

$where = [];
$types = "";
$params = [];

if ($search !== '') {
    $searchTerm = '%' . $search . '%';
    $where[] = "(s.student_number LIKE ? OR s.fullname LIKE ? OR s.email LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ? OR s.year_level LIKE ? OR s.contact LIKE ?)";
    $types .= "sssssss";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if ($courseFilter !== '' && sms_is_valid_positive_id($courseFilter)) {
    $courseId = (int)$courseFilter;
    $where[] = "s.course_id = ?";
    $types .= "i";
    $params[] = $courseId;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
$sql = "
    SELECT s.student_id, s.student_number, s.fullname, s.email, s.year_level, s.birthdate, s.contact, s.address,
           c.course_code, c.course_name,
           COUNT(e.enrollment_id) AS enrollment_count
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.course_id
    LEFT JOIN enrollments e ON e.student_id = s.student_id
    $whereSql
    GROUP BY s.student_id, s.student_number, s.fullname, s.email, s.year_level, s.birthdate, s.contact, s.address, c.course_code, c.course_name
    ORDER BY s.student_id DESC
";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student List | Student Management System</title>
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
          <h2>Student List</h2>
          <p>Review student records, courses, and record actions in a clean database view.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

      <section class="card">
        <h2>Student Records</h2>
        <p>Student number is shown as the visible school ID. Internal database IDs are used only for actions.</p>

        <?php echo sms_flash_html(); ?>

        <form class="filter-bar" method="GET" action="students.php">
          <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search students by number, name, course, email, year level, or contact" />
          <select name="course_id">
            <option value="">All Courses</option>
            <?php foreach ($courseOptions as $course) { ?>
              <option value="<?php echo e($course['course_id']); ?>" <?php echo (string)$courseFilter === (string)$course['course_id'] ? 'selected' : ''; ?>>
                <?php echo e($course['course_code'] . ' - ' . $course['course_name']); ?>
              </option>
            <?php } ?>
          </select>
          <button type="submit" class="btn btn-primary">Apply Filter</button>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student Number</th>
                <th>Name</th>
                <th>Course</th>
                <th>Year Level</th>
                <th>Birthdate</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Enrollment</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($students && $students->num_rows > 0) { ?>
                <?php while ($row = $students->fetch_assoc()) { ?>
                  <tr>
                    <td><strong><?php echo e($row['student_number']); ?></strong></td>
                    <td>
                      <strong><?php echo e($row['fullname']); ?></strong>
                      <small><?php echo e($row['email']); ?></small>
                    </td>
                    <td>
                      <strong><?php echo display_value($row['course_name']); ?></strong>
                      <small><?php echo display_value($row['course_code']); ?></small>
                    </td>
                    <td><?php echo display_value($row['year_level']); ?></td>
                    <td><?php echo display_value($row['birthdate']); ?></td>
                    <td><?php echo display_value($row['contact']); ?></td>
                    <td><?php echo display_value($row['address']); ?></td>
                    <td>
                      <?php if ((int)$row['enrollment_count'] > 0) { ?>
                        <span class="status-badge enrolled">Enrolled</span>
                      <?php } else { ?>
                        <span class="status-badge">Registered</span>
                      <?php } ?>
                    </td>
                    <td>
                      <div class="table-actions">
                        <a class="btn btn-secondary" href="edit.php?id=<?php echo e($row['student_id']); ?>">Edit</a>
                        <form method="POST" action="delete.php" onsubmit="return confirm('Delete this student record? This will also remove connected enrollment records.');">
                          <input type="hidden" name="student_id" value="<?php echo e($row['student_id']); ?>" />
                          <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php } ?>
              <?php } else { ?>
                <tr>
                  <td colspan="9">No student records found.</td>
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
<?php $stmt->close(); ?>
