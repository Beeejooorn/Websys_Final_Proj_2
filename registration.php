<?php
include 'auth_check.php';
include 'db_connect.php';
include 'validation_helpers.php';

$message = "";
$messageType = "";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function set_form_message($text, $type = "error") {
    global $message, $messageType;

    $message = $text;
    $messageType = $type;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $grade = trim($_POST['grade'] ?? '');

    if (!sms_is_valid_person_name($fullname)) {
        set_form_message("Student was not registered. Full name should contain only letters, spaces, hyphens, apostrophes, or periods.");
    } elseif (!sms_is_valid_gmail_address($email)) {
        set_form_message("Student was not registered. Please enter a valid Gmail address ending in @gmail.com, for example student@gmail.com.");
    } elseif ($course === '') {
        set_form_message("Student was not registered. Please select a valid course.");
    } elseif (!sms_is_valid_year_level($year_level)) {
        set_form_message("Student was not registered. Please select a valid year level.");
    } elseif (!sms_is_valid_birthdate($birthdate)) {
        set_form_message("Student was not registered. Please enter a valid birthdate that is not in the future.");
    } elseif (!sms_is_valid_contact_number($contact)) {
        set_form_message("Student was not registered. Contact number may contain only numbers, spaces, dashes, or an optional + at the start.");
    } elseif (!sms_is_valid_semester($semester)) {
        set_form_message("Please enter the enrollment semester.");
    } elseif (!sms_is_valid_grade($grade)) {
        set_form_message("Student was not registered. Grade should be a number, N/A, or INC.");
    } else {
        $course_lookup = $conn->prepare("SELECT course_id FROM courses WHERE course_name = ? LIMIT 1");

        if (!$course_lookup) {
            set_form_message("Student was not registered. Unable to prepare the course lookup.");
        } else {
        $course_lookup->bind_param("s", $course);
        if (!$course_lookup->execute()) {
            set_form_message("Student was not registered. Unable to check the selected course.");
            $course_lookup->close();
        } else {
        $course_result = $course_lookup->get_result();

        if (!$course_result || $course_result->num_rows === 0) {
            set_form_message("Student was not registered. Selected course was not found in the courses table.");
            $course_lookup->close();
        } else {
            $course_row = $course_result->fetch_assoc();
            $course_id = (int)$course_row['course_id'];
            $course_lookup->close();

            $conn->begin_transaction();

            try {
                $student_stmt = $conn->prepare("
                    INSERT INTO students
                        (fullname, email, course, year_level, birthdate, contact, address)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$student_stmt) {
                    throw new Exception("Unable to prepare the student record insert.");
                }

                $student_stmt->bind_param("sssssss", $fullname, $email, $course, $year_level, $birthdate, $contact, $address);

                if (!$student_stmt->execute()) {
                    throw new Exception($student_stmt->error);
                }

                $student_id = $conn->insert_id;
                $student_stmt->close();

                if ($student_id <= 0) {
                    throw new Exception("The student record was not saved correctly.");
                }

                $enrollment_stmt = $conn->prepare("
                    INSERT INTO enrollments (student_id, course_id, semester, grade)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$enrollment_stmt) {
                    throw new Exception("Unable to prepare the enrollment record insert.");
                }

                $enrollment_stmt->bind_param("iiss", $student_id, $course_id, $semester, $grade);

                if (!$enrollment_stmt->execute()) {
                    throw new Exception($enrollment_stmt->error);
                }

                $enrollment_stmt->close();

                if (!$conn->commit()) {
                    throw new Exception("The registration could not be completed.");
                }

                set_form_message("Student registered and enrolled successfully. Student ID: " . $student_id, "success");
            } catch (Exception $error) {
                $conn->rollback();
                set_form_message("Student was not registered. " . $error->getMessage());
            }
        }
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

$defaultYearLevelOptions = sms_valid_year_levels();

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

      <section class="card section-card">

        <?php if ($message != "") { ?>
          <p class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo e($message); ?></p>
        <?php } ?>

        <div class="section-title">
          <div>
            <h3>Student Registration Form</h3>
            <p>Structured fields grouped clearly so student details and initial enrollment are saved together.</p>
          </div>
        </div>

        <form method="POST" action="registration.php">

          <div class="form-section">
            <h4>Personal Information</h4>
            <p>Basic student details for record creation.</p>

            <div class="form-grid">

              <div class="form-group">
                <label for="fullname">Full Name</label>
                <input id="fullname" name="fullname" type="text" placeholder="Enter full name" pattern="[A-Za-z .'\-]{2,100}" title="Use letters, spaces, hyphens, apostrophes, or periods only" required />
              </div>

              <div class="form-group">
                <label for="course">Course</label>
                <select id="course" name="course" required>
                  <option value="">Select course</option>
                  <?php foreach ($courseOptions as $courseOption) { ?>
                    <option value="<?php echo e($courseOption); ?>"><?php echo e($courseOption); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="yearlevel">Year Level</label>
                <select id="yearlevel" name="year_level" required>
                  <option value="">Select year level</option>
                  <?php foreach ($yearLevelOptions as $yearLevelOption) { ?>
                    <option value="<?php echo e($yearLevelOption); ?>"><?php echo e($yearLevelOption); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group">
                <label for="birthdate">Birthdate</label>
                <input id="birthdate" name="birthdate" type="date" max="<?php echo date('Y-m-d'); ?>" required />
              </div>

            </div>
          </div>

          <div class="form-section">
            <h4>Contact Information</h4>
            <p>Communication details and address section.</p>

            <div class="form-grid">

              <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" placeholder="Enter email address" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" title="Enter a valid Gmail address, for example student@gmail.com" required />
              </div>

              <div class="form-group">
                <label for="contact">Contact Number</label>
                <input id="contact" name="contact" type="tel" placeholder="Enter contact number" pattern="\+?[0-9][0-9\s-]{6,19}" title="Use numbers only, with optional spaces, dashes, or + at the start" />
              </div>

              <div class="form-group full">
                <label for="address">Address</label>
                <textarea id="address" name="address" placeholder="Enter complete address"></textarea>
              </div>

            </div>
          </div>

          <div class="form-section">
            <h4>Enrollment Information</h4>
            <p>The selected course above will be used automatically for the enrollment record.</p>

            <div class="form-grid">

              <div class="form-group">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" type="text" placeholder="Example: 1st Semester" pattern="[A-Za-z0-9 ._\-]{2,50}" title="Use letters, numbers, spaces, periods, underscores, or dashes" required />
              </div>

              <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" type="text" placeholder="Example: 1.25 or N/A" pattern="(N/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)" title="Use a number, N/A, or INC" />
              </div>

            </div>
          </div>

          <?php if ($message != "") { ?>
            <p class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo e($message); ?></p>
          <?php } ?>

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
