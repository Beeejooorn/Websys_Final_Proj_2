<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';
include 'validation_helpers.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function admin_count($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM admins");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

if (!empty($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$adminExists = admin_count($conn) > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$adminExists) {
        $message = "No admin account exists yet. Please create the first admin account before logging in.";
    } elseif ($login === '' || $password === '') {
        $message = "Please enter your username or Gmail address and password.";
    } elseif (strpos($login, '@') !== false && !sms_is_valid_gmail_address($login)) {
        $message = "Please enter a valid Gmail address or username.";
    } elseif (strpos($login, '@') === false && !sms_is_valid_admin_username($login)) {
        $message = "Please enter a valid username or Gmail address.";
    } else {
        $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['email'] = $admin['email'];

            header("Location: index.php");
            exit();
        }

        $message = "Invalid username or password.";
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
        <div class="brand-mark">SMS</div>
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
              No admin account exists yet. Create the first admin account to open the dashboard.
            <?php } ?>
          </p>
        </div>

        <?php if (!$adminExists) { ?>
          <div class="form-section auth-form-section">
            <h4>Admin Setup Required</h4>
            <p>The system is locked until the first administrator account is created.</p>

            <div class="button-row auth-actions">
              <a href="create_account.php" class="btn btn-primary">Create Admin Account</a>
            </div>
          </div>
        <?php } ?>

        <?php if ($message !== "") { ?>
          <p class="error-message"><?php echo e($message); ?></p>
        <?php } ?>

        <?php if ($adminExists) { ?>
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
                  <input id="password" name="password" type="password" placeholder="Enter password" required />
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
