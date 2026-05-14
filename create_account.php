<?php
include_once 'db_connect.php';
include_once 'validation_helpers.php';

sms_start_session();

function e($value) {
    return sms_escape($value ?? '');
}

function sms_admin_count($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM admins");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

$message = "";
$messageType = "";
$adminCount = sms_admin_count($conn);
$isFirstSetup = $adminCount === 0;
$currentAdmin = null;

if (!$isFirstSetup && !empty($_SESSION['admin_id'])) {
    $currentAdmin = sms_load_admin_by_id($conn, (int)$_SESSION['admin_id']);

    if (!$currentAdmin || $currentAdmin['status'] !== 'active' || sms_is_locked($currentAdmin['locked_until'] ?? null)) {
        session_unset();
        session_destroy();
        sms_redirect("login.php");
    }

    $_SESSION['admin_role'] = $currentAdmin['role'];
    $_SESSION['username'] = $currentAdmin['username'];
    $_SESSION['admin_email'] = $currentAdmin['email'];
}

$canCreateAdmin = $isFirstSetup || ($currentAdmin && $currentAdmin['role'] === 'super_admin');
$standaloneAuthPage = $isFirstSetup || (!$currentAdmin && !$isFirstSetup);

if (!$isFirstSetup && !$currentAdmin) {
    $message = "An admin account already exists. Please log in before accessing admin account setup.";
    $messageType = "info";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canCreateAdmin) {
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $isFirstSetup ? 'super_admin' : ($_POST['role'] ?? 'admin');
    $status = $isFirstSetup ? 'active' : ($_POST['status'] ?? 'active');

    if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $message = "Please complete all account fields.";
        $messageType = "error";
    } elseif (!sms_is_valid_admin_username($username)) {
        $message = "Username must be 3-50 characters and may use letters, numbers, spaces, periods, underscores, or dashes.";
        $messageType = "error";
    } elseif (!sms_is_valid_gmail_address($email)) {
        $message = "Please enter a valid Gmail address ending in @gmail.com.";
        $messageType = "error";
    } elseif (!sms_is_valid_password($password)) {
        $message = "Password must be at least 8 characters long.";
        $messageType = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Password and confirm password do not match.";
        $messageType = "error";
    } elseif (!sms_is_valid_admin_role($role) || !sms_is_valid_admin_status($status)) {
        $message = "Please choose a valid admin role and status.";
        $messageType = "error";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($isFirstSetup) {
            $stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash, role, status) VALUES (?, ?, ?, 'super_admin', 'active')");
            $stmt->bind_param("sss", $username, $email, $passwordHash);
        } else {
            $createdBy = (int)$currentAdmin['admin_id'];
            $stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash, role, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $username, $email, $passwordHash, $role, $status, $createdBy);
        }

        if ($stmt && $stmt->execute()) {
            $newAdminId = $stmt->insert_id;
            $message = $isFirstSetup ? "Super admin account created successfully. You can now log in." : "Admin created successfully.";
            $messageType = "success";
            $adminCount++;
            $isFirstSetup = false;
            sms_log_activity($conn, "admin_created", "Created admin account: " . $username, $currentAdmin ? (int)$currentAdmin['admin_id'] : $newAdminId);
        } else {
            $message = sms_duplicate_message_from_error($stmt ? $stmt->error : $conn->error, "Unable to create admin account. Please check the username and Gmail address.");
            $messageType = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Admin Account | Student Management System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<?php if ($standaloneAuthPage) { ?>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-shell">
      <div class="auth-brand">
        <div class="brand-mark" aria-hidden="true">SMS</div>
        <div>
          <h1>Student Management System</h1>
          <p>Admin account setup</p>
        </div>
      </div>

      <section class="card auth-card">
        <div class="auth-card-header">
          <span class="topbar-badge">Admin Setup</span>
          <h2><?php echo $isFirstSetup ? 'Create Super Admin Account' : 'Admin Account Exists'; ?></h2>
          <p><?php echo $isFirstSetup ? 'Create the first administrator account before opening the dashboard.' : 'Additional admin accounts can only be created by a logged-in super admin.'; ?></p>
        </div>

        <?php if ($message !== "") { ?>
          <p class="<?php echo e($messageType); ?>-message"><?php echo e($message); ?></p>
        <?php } ?>

        <?php if ($isFirstSetup) { ?>
          <form method="POST" action="create_account.php">
            <div class="form-section auth-form-section">
              <h4>Admin Credentials</h4>
              <p>The first account is automatically saved as a super admin.</p>

              <div class="form-grid auth-form-grid">
                <div class="form-group">
                  <label for="username">Username</label>
                  <input id="username" name="username" type="text" placeholder="Enter admin username" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9 ._\-]{2,49}" required />
                </div>

                <div class="form-group">
                  <label for="email">Gmail Address</label>
                  <input id="email" name="email" type="email" placeholder="example@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" required />
                </div>

                <div class="form-group">
                  <label for="password">Password</label>
                  <input id="password" name="password" type="password" placeholder="Enter password" minlength="8" required />
                </div>

                <div class="form-group">
                  <label for="confirm_password">Confirm Password</label>
                  <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password" minlength="8" required />
                </div>
              </div>
            </div>

            <div class="button-row auth-actions">
              <button type="submit" class="btn btn-primary">Create Account</button>
              <a href="login.php" class="btn btn-secondary">Back to Login</a>
            </div>
          </form>
        <?php } else { ?>
          <div class="button-row auth-actions">
            <a href="login.php" class="btn btn-primary">Go to Login</a>
          </div>
        <?php } ?>
      </section>
    </section>
  </main>
</body>
<?php } else { ?>
<body>
  <div class="portal">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">SMS</div>
        <div>
          <h1>Student Management System</h1>
          <p>Student records workspace for academic operations.</p>
        </div>
      </div>

      <nav class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="registration.php">Student Registration</a>
        <a href="enrollment.php">Enrollment</a>
        <a href="students.php">Student List</a>
        <a href="profile.php">Profile</a>
        <a class="logout-link" href="logout.php">Logout</a>
      </nav>

      <div class="sidebar-card">
        <h3>System Workspace</h3>
        <p>Manage registration, records, enrollment, and profile information from one workspace.</p>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="page-intro">
          <h2>Create Admin Account</h2>
          <p>Only super admins can create additional admin accounts.</p>
        </div>
        <span class="topbar-badge">Admin Management</span>
      </header>

      <section class="card">
        <h2>Create New Admin</h2>
        <p>Add another admin account without using the student users table.</p>

        <?php if ($message !== "") { ?>
          <p class="<?php echo e($messageType); ?>-message"><?php echo e($message); ?></p>
        <?php } ?>

        <?php if (!$canCreateAdmin) { ?>
          <p class="warning-message">Only a super admin can create admin accounts.</p>
        <?php } else { ?>
          <form method="POST" action="create_account.php">
            <div class="form-section">
              <h4>Admin Credentials</h4>
              <p>The new account will be saved in the admins table only.</p>

              <div class="form-grid">
                <div class="form-group">
                  <label for="username">Username</label>
                  <input id="username" name="username" type="text" placeholder="Enter admin username" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9 ._\-]{2,49}" required />
                </div>

                <div class="form-group">
                  <label for="email">Gmail Address</label>
                  <input id="email" name="email" type="email" placeholder="example@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" required />
                </div>

                <div class="form-group">
                  <label for="role">Role</label>
                  <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="status">Status</label>
                  <select id="status" name="status" required>
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="password">Password</label>
                  <input id="password" name="password" type="password" placeholder="Enter password" minlength="8" required />
                </div>

                <div class="form-group">
                  <label for="confirm_password">Confirm Password</label>
                  <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password" minlength="8" required />
                </div>
              </div>
            </div>

            <div class="button-row">
              <button type="submit" class="btn btn-primary">Create Admin</button>
              <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
            </div>
          </form>
        <?php } ?>
      </section>
    </main>
  </div>
</body>
<?php } ?>
</html>
