<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function e($value) {
    return sms_escape($value ?? '');
}

function old_value($values, $key) {
    return e($values[$key] ?? '');
}

$message = "";
$messageType = "";
$old = [];

$courseOptions = [];
$courseStmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name ASC");
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
while ($row = $courseResult->fetch_assoc()) {
    $courseOptions[] = $row;
}
$courseStmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $old = [
        'student_number' => trim($_POST['student_number'] ?? ''),
        'fullname' => trim($_POST['fullname'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'course_id' => trim($_POST['course_id'] ?? ''),
        'year_level' => trim($_POST['year_level'] ?? ''),
        'birthdate' => trim($_POST['birthdate'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'semester' => trim($_POST['semester'] ?? ''),
        'grade' => trim($_POST['grade'] ?? '')
    ];

    if (!sms_is_valid_student_number($old['student_number'])) {
        $message = "Student ID must be exactly 10 digits.";
        $messageType = "error";
    } elseif (!sms_is_valid_person_name($old['fullname'])) {
        $message = "Please enter a valid full name using letters, spaces, periods, apostrophes, or dashes only.";
        $messageType = "error";
    } elseif (!sms_is_valid_gmail_address($old['email'])) {
        $message = "Please enter a valid Gmail address ending in @gmail.com.";
        $messageType = "error";
    } elseif (!sms_is_valid_positive_id($old['course_id'])) {
        $message = "Please select a valid course.";
        $messageType = "error";
    } elseif (!sms_is_valid_year_level($old['year_level'])) {
        $message = "Please select a valid year level.";
        $messageType = "error";
    } elseif (!sms_is_valid_birthdate($old['birthdate'])) {
        $message = "Please enter a valid birthdate that is not in the future.";
        $messageType = "error";
    } elseif (!sms_is_valid_contact_number($old['contact'])) {
        $message = "Contact number should contain numbers only, with optional plus sign, spaces, or dashes.";
        $messageType = "error";
    } elseif ($old['address'] === '') {
        $message = "Please enter the student address.";
        $messageType = "error";
    } elseif (!sms_is_valid_semester($old['semester'])) {
        $message = "Please enter a valid semester, such as 1st Semester.";
        $messageType = "error";
    } elseif (!sms_is_valid_grade($old['grade'])) {
        $message = "Please enter a valid grade, such as 1.25, N/A, or leave it blank.";
        $messageType = "error";
    } else {
        $courseId = (int)$old['course_id'];
        $courseLookup = $conn->prepare("SELECT course_id, course_name FROM courses WHERE course_id = ? LIMIT 1");
        $courseLookup->bind_param("i", $courseId);
        $courseLookup->execute();
        $courseResult = $courseLookup->get_result();
        $selectedCourse = $courseResult->fetch_assoc();
        $courseLookup->close();

        if (!$selectedCourse) {
            $message = "Please select a course that exists in the courses table.";
            $messageType = "error";
        } else {
            $duplicateStmt = $conn->prepare("SELECT student_number, email FROM students WHERE student_number = ? OR email = ? LIMIT 1");
            $duplicateStmt->bind_param("ss", $old['student_number'], $old['email']);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();
            $duplicate = $duplicateResult->fetch_assoc();
            $duplicateStmt->close();

            if ($duplicate && $duplicate['student_number'] === $old['student_number']) {
                $message = "Student ID already exists. Please enter a different Student ID.";
                $messageType = "error";
            } elseif ($duplicate && strtolower($duplicate['email']) === $old['email']) {
                $message = "That email address already exists. Please use another Gmail address.";
                $messageType = "error";
            } else {
                $conn->begin_transaction();

                try {
                    $courseName = $selectedCourse['course_name'];
                    $studentStmt = $conn->prepare("INSERT INTO students (student_number, fullname, email, course_id, course, year_level, birthdate, contact, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $studentStmt->bind_param(
                        "sssisssss",
                        $old['student_number'],
                        $old['fullname'],
                        $old['email'],
                        $courseId,
                        $courseName,
                        $old['year_level'],
                        $old['birthdate'],
                        $old['contact'],
                        $old['address']
                    );

                    if (!$studentStmt->execute()) {
                        throw new Exception($studentStmt->error);
                    }

                    $studentId = $conn->insert_id;
                    $studentStmt->close();

                    $enrollmentStmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, semester, grade) VALUES (?, ?, ?, ?)");
                    $enrollmentStmt->bind_param("iiss", $studentId, $courseId, $old['semester'], $old['grade']);

                    if (!$enrollmentStmt->execute()) {
                        throw new Exception($enrollmentStmt->error);
                    }

                    $enrollmentStmt->close();
                    $conn->commit();

                    sms_log_activity($conn, "student_created", "Created student " . $old['student_number'] . " and initial enrollment.", sms_current_admin_id());
                    $message = "Student added successfully. Initial enrollment was also saved.";
                    $messageType = "success";
                    $old = [];
                } catch (Exception $exception) {
                    $conn->rollback();
                    $message = sms_duplicate_message_from_error($exception->getMessage(), "Database action failed. Registration was not saved. Please check the form and try again.");
                    $messageType = "error";
                }
            }
        }
    }
}

$today = date('Y-m-d');
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
        <div class="brand-mark" aria-hidden="true">SMS</div>
        <h1>Student Management System</h1>
        <p>Student records workspace for academic operations.</p>
      </div>

      <nav class="nav-links">
        <a href="index.php" class="">Dashboard</a>
        <a href="registration.php" class="active">Student Registration</a>
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
          <h2>Student Registration</h2>
          <p>A visually organized form for creating student and enrollment records in one flow.</p>
        </div>
        <div class="topbar-badge">Academic Portal</div>
      </header>

      <section class="card">
        <h2>Student Registration Form</h2>
        <p>Student ID is the visible 10-digit school ID used on records, search, and enrollment lists.</p>

        <?php echo sms_flash_html(); ?>

        <?php if ($message !== "") { ?>
          <p class="<?php echo e($messageType); ?>-message"><?php echo e($message); ?></p>
        <?php } ?>

        <form method="POST" action="registration.php">
          <div class="form-section">
            <h4>Personal Information</h4>
            <p>Basic student details for record creation.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="student_number">Student ID / School ID</label>
                <input id="student_number" name="student_number" type="text" value="<?php echo old_value($old, 'student_number'); ?>" placeholder="2026123819" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" title="Enter exactly 10 digits." required />
              </div>

              <div class="form-group">
                <label for="fullname">Full Name</label>
                <input id="fullname" name="fullname" type="text" value="<?php echo old_value($old, 'fullname'); ?>" placeholder="Enter full name" maxlength="100" pattern="[A-Za-z .'\-]{2,100}" required />
              </div>

              <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                  <option value="">Select course</option>
                  <?php foreach ($courseOptions as $course) { ?>
                    <option value="<?php echo e($course['course_id']); ?>" <?php echo (string)($old['course_id'] ?? '') === (string)$course['course_id'] ? 'selected' : ''; ?>>
                      <?php echo e($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="year_level">Year Level</label>
                <select id="year_level" name="year_level" required>
                  <option value="">Select year level</option>
                  <?php foreach (sms_valid_year_levels() as $level) { ?>
                    <option value="<?php echo e($level); ?>" <?php echo ($old['year_level'] ?? '') === $level ? 'selected' : ''; ?>><?php echo e($level); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="birthdate">Birthdate</label>
                <input id="birthdate" name="birthdate" type="date" value="<?php echo old_value($old, 'birthdate'); ?>" max="<?php echo e($today); ?>" required />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h4>Contact Information</h4>
            <p>Communication details and address section.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" value="<?php echo old_value($old, 'email'); ?>" placeholder="student@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" required />
              </div>

              <div class="form-group">
                <label for="contact">Contact Number</label>
                <input id="contact" name="contact" type="text" value="<?php echo old_value($old, 'contact'); ?>" placeholder="Enter contact number" inputmode="tel" maxlength="20" pattern="\+?[0-9][0-9\s-]{6,19}" title="Use numbers only, with optional plus sign, spaces, or dashes." required />
              </div>

              <div class="form-group full-span">
                <label for="address">Address</label>
                <textarea id="address" name="address" placeholder="Enter complete address" maxlength="500" required><?php echo old_value($old, 'address'); ?></textarea>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>The selected course above will be used automatically for the enrollment record.</p>

            <div class="form-grid">
              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" value="<?php echo old_value($old, 'semester'); ?>" placeholder="Example: 1st Semester" maxlength="50" pattern="[A-Za-z0-9 ._\-]+" required />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" value="<?php echo old_value($old, 'grade'); ?>" placeholder="Example: 1.25 or N/A" maxlength="10" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" />
              </div>
            </div>
          </div>

          <div class="button-row">
            <button type="submit" class="btn btn-primary">Register and Enroll Student</button>
            <button type="reset" class="btn btn-secondary">Clear Form</button>
          </div>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
