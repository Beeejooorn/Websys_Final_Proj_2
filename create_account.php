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
$messageType = "";
$adminExists = admin_count($conn) > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

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
    } else {
        $emailCheck = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? LIMIT 1");
        $emailCheck->bind_param("s", $email);
        $emailCheck->execute();
        $emailResult = $emailCheck->get_result();
        $emailExists = $emailResult->num_rows > 0;
        $emailCheck->close();

        if ($emailExists) {
            $message = "That Gmail address is already used by an admin account.";
            $messageType = "error";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $passwordHash);

            if ($stmt->execute()) {
                $message = "Admin account created successfully. You can now log in.";
                $messageType = "success";
                $adminExists = true;
            } else {
                $message = "Unable to create admin account. Please check the username and Gmail address.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

if ($adminExists && $message === "") {
    $message = "An admin account already exists. New admin account creation is disabled. Please log in instead.";
    $messageType = "error";
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
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-shell">
      <div class="auth-brand">
        <div class="brand-mark">SMS</div>
        <div>
          <h1>Student Management System</h1>
          <p>Admin account setup</p>
        </div>
      </div>

      <section class="card auth-card">
        <div class="auth-card-header">
          <span class="topbar-badge">Admin Setup</span>
          <h2>Create Admin Account</h2>
          <p>Create the first administrator account before opening the dashboard.</p>
        </div>

        <?php if ($message !== "") { ?>
          <p class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
            <?php echo e($message); ?>
          </p>
        <?php } ?>

        <?php if (!$adminExists) { ?>
          <form method="POST" action="create_account.php">
            <div class="form-section auth-form-section">
              <h4>Admin Credentials</h4>
              <p>This setup is available only while no admin account exists.</p>

              <div class="form-grid auth-form-grid">
                <div class="form-group">
                  <label for="username">Username</label>
                  <input id="username" name="username" type="text" placeholder="Enter admin username" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9 ._\-]{2,49}" title="Use 3-50 characters: letters, numbers, spaces, periods, underscores, or dashes" required />
                </div>

                <div class="form-group">
                  <label for="email">Gmail Address</label>
                  <input id="email" name="email" type="email" placeholder="example@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" title="Enter a valid Gmail address, for example admin@gmail.com" required />
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
          <p class="auth-switch">Already have an account? <a href="login.php">Login</a></p>
        <?php } else { ?>
          <div class="button-row auth-actions">
            <a href="login.php" class="btn btn-primary">Go to Login</a>
          </div>
        <?php } ?>
      </section>
    </section>
  </main>
</body>
</html>
