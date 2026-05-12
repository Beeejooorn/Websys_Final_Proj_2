<?php
include 'auth_check.php';
include 'db_connect.php';
include 'validation_helpers.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function load_admin($conn, $adminId) {
    $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, created_at, updated_at FROM admins WHERE admin_id = ? LIMIT 1");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    return $admin;
}

function single_value($conn, $sql, $default = 0) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['value'] ?? $default;
    }
    return $default;
}

function display_value($value, $fallback = 'Not recorded') {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? e($value) : $fallback;
}

$adminId = (int)$_SESSION['admin_id'];
$admin = load_admin($conn, $adminId);

if (!$admin) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit();
}

$usernameMessage = "";
$emailMessage = "";
$passwordMessage = "";
$totalStudents = (int) single_value($conn, "SELECT COUNT(*) AS value FROM students");
$totalCourses = (int) single_value($conn, "SELECT COUNT(*) AS value FROM courses");
$totalEnrollments = (int) single_value($conn, "SELECT COUNT(*) AS value FROM enrollments");
$totalAdmins = (int) single_value($conn, "SELECT COUNT(*) AS value FROM admins");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['profile_action'] ?? '';

    if ($action === 'update_username') {
        $newUsername = trim($_POST['username'] ?? '');

        if ($newUsername === '') {
            $usernameMessage = "Username cannot be empty.";
        } elseif (!sms_is_valid_admin_username($newUsername)) {
            $usernameMessage = "Username must be 3-50 characters and may use letters, numbers, spaces, periods, underscores, or dashes.";
        } else {
            $checkStmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? AND admin_id <> ? LIMIT 1");
            $checkStmt->bind_param("si", $newUsername, $adminId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $usernameTaken = $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($usernameTaken) {
                $usernameMessage = "That username is already used by another admin.";
            } else {
                $updateStmt = $conn->prepare("UPDATE admins SET username = ? WHERE admin_id = ?");
                $updateStmt->bind_param("si", $newUsername, $adminId);

                if ($updateStmt->execute()) {
                    $_SESSION['username'] = $newUsername;
                    $usernameMessage = "Username updated successfully.";
                } else {
                    $usernameMessage = "Unable to update username.";
                }

                $updateStmt->close();
            }
        }
    }

    if ($action === 'update_email') {
        $newEmail = strtolower(trim($_POST['email'] ?? ''));

        if ($newEmail === '') {
            $emailMessage = "Email cannot be empty.";
        } elseif (!sms_is_valid_gmail_address($newEmail)) {
            $emailMessage = "Please enter a valid Gmail address ending in @gmail.com.";
        } else {
            $checkStmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? AND admin_id <> ? LIMIT 1");
            $checkStmt->bind_param("si", $newEmail, $adminId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $emailTaken = $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($emailTaken) {
                $emailMessage = "That Gmail address is already used by another admin.";
            } else {
                $updateStmt = $conn->prepare("UPDATE admins SET email = ? WHERE admin_id = ?");
                $updateStmt->bind_param("si", $newEmail, $adminId);

                if ($updateStmt->execute()) {
                    $_SESSION['email'] = $newEmail;
                    $emailMessage = "Email updated successfully.";
                } else {
                    $emailMessage = "Unable to update email.";
                }

                $updateStmt->close();
            }
        }
    }

    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $passwordMessage = "Please complete all password fields.";
        } elseif (!sms_is_valid_password($newPassword)) {
            $passwordMessage = "New password must be at least 8 characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $passwordMessage = "New password and confirm password do not match.";
        } elseif (!password_verify($currentPassword, $admin['password_hash'])) {
            $passwordMessage = "Current password is incorrect.";
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE admin_id = ?");
            $passwordStmt->bind_param("si", $newPasswordHash, $adminId);

            if ($passwordStmt->execute()) {
                $passwordMessage = "Password updated successfully.";
            } else {
                $passwordMessage = "Unable to update password.";
            }

            $passwordStmt->close();
        }
    }

    $admin = load_admin($conn, $adminId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Profile | Student Management System</title>
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
        <a href="students.php" class="">Student List</a>
        <a href="profile.php" class="active">Profile</a>
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
          <h2>Admin Profile</h2>
          <p>Manage the signed-in administrator account.</p>
        </div>
        <div class="topbar-badge">Admin Account</div>
      </header>

      <section class="profile-layout">
        <aside class="card profile-card">
          <div class="avatar">AD</div>
          <h3><?php echo e($admin['username']); ?></h3>
          <p>Logged-in Administrator</p>

          <div class="profile-meta">
            <div class="meta-row">
              <small>Gmail Address</small>
              <strong><?php echo display_value($admin['email']); ?></strong>
            </div>
            <div class="meta-row">
              <small>Admin ID</small>
              <strong><?php echo e($admin['admin_id']); ?></strong>
            </div>
            <div class="meta-row">
              <small>Created At</small>
              <strong><?php echo e($admin['created_at']); ?></strong>
            </div>
            <div class="meta-row">
              <small>Updated At</small>
              <strong><?php echo e($admin['updated_at']); ?></strong>
            </div>
          </div>
        </aside>

        <section class="details-grid">
          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>System Information</h3>
                <p>Read-only overview of the current student management database.</p>
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
                <small>Enrollment Records</small>
                <strong><?php echo number_format($totalEnrollments); ?></strong>
              </div>
              <div class="info-box">
                <small>System Status</small>
                <strong>Database Connected</strong>
              </div>
            </div>
          </div>

          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>Admin Account Information</h3>
                <p>Current signed-in admin details from the admins table.</p>
              </div>
            </div>

            <div class="info-grid">
              <div class="info-box">
                <small>Username</small>
                <strong><?php echo e($admin['username']); ?></strong>
              </div>
              <div class="info-box">
                <small>Gmail Address</small>
                <strong><?php echo display_value($admin['email']); ?></strong>
              </div>
              <div class="info-box">
                <small>Admin ID</small>
                <strong><?php echo e($admin['admin_id']); ?></strong>
              </div>
              <div class="info-box">
                <small>Created At</small>
                <strong><?php echo e($admin['created_at']); ?></strong>
              </div>
              <div class="info-box">
                <small>Updated At</small>
                <strong><?php echo e($admin['updated_at']); ?></strong>
              </div>
              <div class="info-box">
                <small>Admin Accounts</small>
                <strong><?php echo number_format($totalAdmins); ?></strong>
              </div>
              <div class="info-box">
                <small>Password</small>
                <strong>Protected</strong>
              </div>
            </div>
          </div>

          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>Update Username</h3>
                <p>Change the username used for admin login.</p>
              </div>
            </div>

            <?php if ($usernameMessage !== "") { ?>
              <p><?php echo e($usernameMessage); ?></p>
            <?php } ?>

            <form method="POST" action="profile.php">
              <input type="hidden" name="profile_action" value="update_username" />

              <div class="form-section">
                <h4>Username</h4>
                <p>This updates the logged-in admin account only.</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?php echo e($admin['username']); ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9 ._\-]{2,49}" title="Use 3-50 characters: letters, numbers, spaces, periods, underscores, or dashes" required />
                  </div>
                </div>
              </div>

              <div class="button-row">
                <button type="submit" class="btn btn-primary">Update Username</button>
              </div>
            </form>
          </div>

          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>Update Email</h3>
                <p>Change the Gmail address connected to this admin account.</p>
              </div>
            </div>

            <?php if ($emailMessage !== "") { ?>
              <p><?php echo e($emailMessage); ?></p>
            <?php } ?>

            <form method="POST" action="profile.php">
              <input type="hidden" name="profile_action" value="update_email" />

              <div class="form-section">
                <h4>Gmail Address</h4>
                <p>Only valid Gmail addresses ending in @gmail.com are accepted.</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="email">Gmail Address</label>
                    <input id="email" name="email" type="email" value="<?php echo e($admin['email']); ?>" placeholder="example@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" title="Enter a valid Gmail address, for example admin@gmail.com" required />
                  </div>
                </div>
              </div>

              <div class="button-row">
                <button type="submit" class="btn btn-primary">Update Email</button>
              </div>
            </form>
          </div>

          <div class="card section-card">
            <div class="section-title">
              <div>
                <h3>Change Password</h3>
                <p>Verify the current password before saving a new one.</p>
              </div>
            </div>

            <?php if ($passwordMessage !== "") { ?>
              <p><?php echo e($passwordMessage); ?></p>
            <?php } ?>

            <form method="POST" action="profile.php">
              <input type="hidden" name="profile_action" value="update_password" />

              <div class="form-section">
                <h4>Password</h4>
                <p>The password hash is stored securely and is never displayed.</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input id="current_password" name="current_password" type="password" required />
                  </div>

                  <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" required />
                  </div>

                  <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" required />
                  </div>
                </div>
              </div>

              <div class="button-row">
                <button type="submit" class="btn btn-primary">Change Password</button>
              </div>
            </form>
          </div>
        </section>
      </section>
    </main>
  </div>
</body>
</html>
