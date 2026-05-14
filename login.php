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

if (!empty($_SESSION['admin_id'])) {
    sms_redirect("index.php");
}

$message = "";
$messageType = "error";
$adminExists = sms_admin_count($conn) > 0;

$queryMessage = $_GET['message'] ?? '';
if ($queryMessage === 'logout_success') {
    $message = "Logout successful.";
    $messageType = "success";
} elseif ($queryMessage === 'account_disabled') {
    $message = "This admin account is disabled.";
} elseif ($queryMessage === 'account_locked') {
    $message = "This admin account is temporarily locked. Please try again later.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $messageType = "error";

    if (!$adminExists) {
        $message = "No admin account exists yet. Please create the first admin account before logging in.";
    } elseif ($login === '' || $password === '') {
        $message = "Please enter your username or Gmail address and password.";
    } elseif (strpos($login, '@') !== false && !sms_is_valid_gmail_address($login)) {
        $message = "Please enter a valid Gmail address ending in @gmail.com.";
    } elseif (strpos($login, '@') === false && !sms_is_valid_admin_username($login)) {
        $message = "Please enter a valid username or Gmail address.";
    } else {
        $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, role, status, failed_login_count, locked_until FROM admins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            sms_log_login_attempt($conn, null, $login, false);
            $message = "Login failed. Please check your username/email and password.";
        } elseif ($admin['status'] !== 'active') {
            sms_log_login_attempt($conn, (int)$admin['admin_id'], $login, false);
            $message = "This admin account is disabled.";
        } elseif (sms_is_locked($admin['locked_until'] ?? null)) {
            sms_log_login_attempt($conn, (int)$admin['admin_id'], $login, false);
            $message = "This admin account is temporarily locked. Please try again later.";
        } elseif (!password_verify($password, $admin['password_hash'])) {
            $newFailedCount = ((int)$admin['failed_login_count']) + 1;
            sms_log_login_attempt($conn, (int)$admin['admin_id'], $login, false);

            if ($newFailedCount >= 5) {
                $lockStmt = $conn->prepare("UPDATE admins SET failed_login_count = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE admin_id = ?");
                $lockStmt->bind_param("ii", $newFailedCount, $admin['admin_id']);
                $lockStmt->execute();
                $lockStmt->close();
                $message = "Login failed too many times. This account is temporarily locked for 15 minutes.";
            } else {
                $failStmt = $conn->prepare("UPDATE admins SET failed_login_count = ? WHERE admin_id = ?");
                $failStmt->bind_param("ii", $newFailedCount, $admin['admin_id']);
                $failStmt->execute();
                $failStmt->close();
                $message = "Login failed. Please check your username/email and password.";
            }
        } else {
            $successStmt = $conn->prepare("UPDATE admins SET last_login = NOW(), failed_login_count = 0, locked_until = NULL WHERE admin_id = ?");
            $successStmt->bind_param("i", $admin['admin_id']);
            $successStmt->execute();
            $successStmt->close();

            sms_log_login_attempt($conn, (int)$admin['admin_id'], $login, true);

            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_status'] = $admin['status'];

            sms_log_activity($conn, "login_success", "Admin logged in.", (int)$admin['admin_id']);
            sms_set_flash("success", "Login successful.");
            sms_redirect("index.php");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login | Student Management System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-shell">
      <div class="auth-brand">
        <div class="brand-mark" aria-hidden="true">SMS</div>
        <div>
          <h1>Student Management System</h1>
          <p>Admin access portal</p>
        </div>
      </div>

      <section class="card auth-card">
        <div class="auth-card-header">
          <span class="topbar-badge"><?php echo $adminExists ? 'Secure Access' : 'First-Time Setup'; ?></span>
          <h2><?php echo $adminExists ? 'Admin Login' : 'Create Admin Account'; ?></h2>
          <p>
            <?php if ($adminExists) { ?>
              Sign in before entering the student management dashboard.
            <?php } else { ?>
              No admin account exists yet. Create the first super admin account to open the dashboard.
            <?php } ?>
          </p>
        </div>

        <?php if ($message !== "") { ?>
          <p class="<?php echo e($messageType); ?>-message"><?php echo e($message); ?></p>
        <?php } ?>

        <?php if (!$adminExists) { ?>
          <div class="form-section auth-form-section">
            <h4>Admin Setup Required</h4>
            <p>The system is locked until the first administrator account is created.</p>

            <div class="button-row auth-actions">
              <a href="create_account.php" class="btn btn-primary">Create Admin Account</a>
            </div>
          </div>
        <?php } else { ?>
          <form method="POST" action="login.php">
            <div class="form-section auth-form-section">
              <h4>Login Credentials</h4>
              <p>Use the username or Gmail address saved in the admins table.</p>

              <div class="form-grid auth-form-grid">
                <div class="form-group">
                  <label for="login">Username or Gmail Address</label>
                  <input id="login" name="login" type="text" placeholder="Enter username or Gmail address" minlength="3" maxlength="100" required />
                </div>

                <div class="form-group">
                  <label for="password">Password</label>
                  <input id="password" name="password" type="password" placeholder="Enter password" minlength="8" required />
                </div>
              </div>
            </div>

            <div class="button-row auth-actions">
              <button type="submit" class="btn btn-primary">Login</button>
            </div>
          </form>
        <?php } ?>
      </section>
    </section>
  </main>
</body>
</html>
