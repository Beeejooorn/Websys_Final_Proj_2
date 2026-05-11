<?php
include 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function display_value($value, $fallback = 'Not recorded') {
    $value = trim((string)($value ?? ''));
    return $value !== '' && $value !== '0000-00-00' ? e($value) : $fallback;
}

$yearLevelColumn = null;
foreach (['year_level', 'yearlevel', 'year'] as $possibleColumn) {
    $safeColumn = $conn->real_escape_string($possibleColumn);
    $column_result = $conn->query("SHOW COLUMNS FROM students LIKE '$safeColumn'");
    if ($column_result && $column_result->num_rows > 0) {
        $yearLevelColumn = $possibleColumn;
        break;
    }
}

$yearLevelSelect = $yearLevelColumn !== null ? ", s.`$yearLevelColumn` AS year_level" : ", NULL AS year_level";

$search = trim($_GET['search'] ?? '');
$searchSql = "";

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);

    $searchSql = "
        WHERE s.student_id LIKE '%$safeSearch%'
        OR s.fullname LIKE '%$safeSearch%'
        OR s.email LIKE '%$safeSearch%'
        OR s.course LIKE '%$safeSearch%'
        OR s.year_level LIKE '%$safeSearch%'
        OR s.birthdate LIKE '%$safeSearch%'
        OR s.contact LIKE '%$safeSearch%'
        OR s.address LIKE '%$safeSearch%'
    ";
}

$sql = "
    SELECT s.* $yearLevelSelect,
           (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id) AS enrollment_count
    FROM students s
    $searchSql
    ORDER BY s.user_id DESC
";

$result = $conn->query($sql);
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
          <h2>Student List</h2>
          <p>Review student records, courses, and record actions in a clean database view.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

<section class="card section-card">
  <div class="section-title">
    <div>
      <h3>Student Records</h3>
      <p>Simple and professional table layout showing key student information.</p>
    </div>
  </div>

  <div class="table-toolbar">
  <form method="GET" action="students.php" class="search-shell">
    <input 
      type="text" 
      name="search" 
      value="<?php echo e($search); ?>" 
      placeholder="Search students by name, ID, course, email, year level, or contact" 
    />
  </form>

  <?php if ($search !== '') { ?>
    <a href="students.php" class="btn btn-secondary">Clear Search</a>
  <?php } ?>

  <div class="topbar-badge">Database Records</div>
</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Name</th>
          <th>Course</th>
          <th>Year Level</th>
          <th>Birthdate</th>
          <th>Contact</th>
          <th>Address</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  <?php if ($result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
      <?php
        $statusLabel = ((int)$row['enrollment_count'] > 0) ? 'Enrolled' : 'Registered';
        $statusClass = ((int)$row['enrollment_count'] > 0) ? 'success' : 'warning';
      ?>
      <tr>
        <td><?php echo e($row['student_id']); ?></td>
        <td>
          <div class="student-name"><?php echo display_value($row['fullname']); ?></div>
          <div class="student-sub"><?php echo display_value($row['email'], 'Email not recorded'); ?></div>
        </td>
          <td><?php echo display_value($row['course'], 'Course not recorded'); ?></td>
          <td><?php echo display_value($row['year_level']); ?></td>
          <td><?php echo display_value($row['birthdate']); ?></td>
          <td><?php echo display_value($row['contact']); ?></td>
          <td><?php echo display_value($row['address']); ?></td>
          <td><span class="pill <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span></td>
          <td>
            <div class="action-links">
              <a href="edit.php?id=<?php echo e($row['student_id']); ?>">Edit</a>
              <a href="delete.php?id=<?php echo e($row['student_id']); ?>" onclick="return confirm('Delete this record?')">Delete</a>
            </div>
          </td>
      </tr>
    <?php } ?>
  <?php } else { ?>
    <tr>
     <td colspan="9">
      <?php echo $search !== '' ? 'No matching students found.' : 'No student records found.'; ?>
    </td>
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
