<?php
include_once 'auth_check.php';
include_once 'db_connect.php';
include_once 'validation_helpers.php';

function e($value) {
    return sms_escape($value ?? '');
}

function load_admin_for_profile($conn, $adminId) {
    $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, role, status, created_at, updated_at, last_login FROM admins WHERE admin_id = ? LIMIT 1");
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

function admin_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $initials = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'A';
}

$adminId = (int)$_SESSION['admin_id'];
$admin = load_admin_for_profile($conn, $adminId);

if (!$admin) {
    $_SESSION = [];
    session_destroy();
    sms_redirect("login.php");
}

$messages = [
    'username' => ['type' => '', 'text' => ''],
    'email' => ['type' => '', 'text' => ''],
    'password' => ['type' => '', 'text' => ''],
    'manage' => ['type' => '', 'text' => '']
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['profile_action'] ?? '';

    if ($action === 'update_username') {
        $newUsername = trim($_POST['username'] ?? '');

        if (!sms_is_valid_admin_username($newUsername)) {
            $messages['username'] = ['type' => 'error', 'text' => 'Username must be 3-50 characters and may use letters, numbers, spaces, periods, underscores, or dashes.'];
        } else {
            $checkStmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? AND admin_id <> ? LIMIT 1");
            $checkStmt->bind_param("si", $newUsername, $adminId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $usernameTaken = $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($usernameTaken) {
                $messages['username'] = ['type' => 'error', 'text' => 'That username is already used by another admin.'];
            } else {
                $updateStmt = $conn->prepare("UPDATE admins SET username = ? WHERE admin_id = ?");
                $updateStmt->bind_param("si", $newUsername, $adminId);

                if ($updateStmt->execute()) {
                    $_SESSION['username'] = $newUsername;
                    sms_log_activity($conn, "admin_updated", "Updated own admin username.", $adminId);
                    $messages['username'] = ['type' => 'success', 'text' => 'Admin profile updated successfully.'];
                } else {
                    $messages['username'] = ['type' => 'error', 'text' => 'Admin profile update failed. Please try again.'];
                }

                $updateStmt->close();
            }
        }
    }

    if ($action === 'update_email') {
        $newEmail = strtolower(trim($_POST['email'] ?? ''));

        if (!sms_is_valid_gmail_address($newEmail)) {
            $messages['email'] = ['type' => 'error', 'text' => 'Please enter a valid Gmail address ending in @gmail.com.'];
        } else {
            $checkStmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? AND admin_id <> ? LIMIT 1");
            $checkStmt->bind_param("si", $newEmail, $adminId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $emailTaken = $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($emailTaken) {
                $messages['email'] = ['type' => 'error', 'text' => 'That Gmail address is already used by another admin.'];
            } else {
                $updateStmt = $conn->prepare("UPDATE admins SET email = ? WHERE admin_id = ?");
                $updateStmt->bind_param("si", $newEmail, $adminId);

                if ($updateStmt->execute()) {
                    $_SESSION['admin_email'] = $newEmail;
                    sms_log_activity($conn, "admin_updated", "Updated own admin email.", $adminId);
                    $messages['email'] = ['type' => 'success', 'text' => 'Admin profile updated successfully.'];
                } else {
                    $messages['email'] = ['type' => 'error', 'text' => 'Admin profile update failed. Please try again.'];
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
            $messages['password'] = ['type' => 'error', 'text' => 'Please complete all password fields.'];
        } elseif (!sms_is_valid_password($newPassword)) {
            $messages['password'] = ['type' => 'error', 'text' => 'New password must be at least 8 characters long.'];
        } elseif ($newPassword !== $confirmPassword) {
            $messages['password'] = ['type' => 'error', 'text' => 'New password and confirm password do not match.'];
        } elseif (!password_verify($currentPassword, $admin['password_hash'])) {
            $messages['password'] = ['type' => 'error', 'text' => 'Current password is incorrect.'];
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE admin_id = ?");
            $passwordStmt->bind_param("si", $newPasswordHash, $adminId);

            if ($passwordStmt->execute()) {
                sms_log_activity($conn, "admin_updated", "Updated own admin password.", $adminId);
                $messages['password'] = ['type' => 'success', 'text' => 'Admin password changed successfully.'];
            } else {
                $messages['password'] = ['type' => 'error', 'text' => 'Password change failed. Please try again.'];
            }

            $passwordStmt->close();
        }
    }

    if ($action === 'manage_admin') {
        $targetAdminId = (int)($_POST['target_admin_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';

        if ($admin['role'] !== 'super_admin') {
            $messages['manage'] = ['type' => 'error', 'text' => 'Only a super admin can manage admin accounts.'];
        } elseif (!sms_is_valid_positive_id($targetAdminId) || !sms_is_valid_admin_role($role) || !sms_is_valid_admin_status($status)) {
            $messages['manage'] = ['type' => 'error', 'text' => 'Please choose a valid admin, role, and status.'];
        } elseif ($targetAdminId === $adminId) {
            $messages['manage'] = ['type' => 'warning', 'text' => 'Use the account forms above for your own profile. Role and status changes for your own account are blocked here.'];
        } else {
            $updateStmt = $conn->prepare("UPDATE admins SET role = ?, status = ? WHERE admin_id = ?");
            $updateStmt->bind_param("ssi", $role, $status, $targetAdminId);

            if ($updateStmt->execute()) {
                $actionName = $status === 'disabled' ? 'admin_disabled' : 'admin_updated';
                $messageText = $status === 'disabled' ? 'Admin disabled successfully.' : 'Admin updated successfully.';
                sms_log_activity($conn, $actionName, "Updated admin ID " . $targetAdminId . " to role " . $role . " and status " . $status . ".", $adminId);
                $messages['manage'] = ['type' => 'success', 'text' => $messageText];
            } else {
                $messages['manage'] = ['type' => 'error', 'text' => 'Unable to update admin. The system may be protecting the last active super admin.'];
            }

            $updateStmt->close();
        }
    }

    $admin = load_admin_for_profile($conn, $adminId);
}

$totalStudents = (int) single_value($conn, "SELECT COUNT(*) AS value FROM students");
$totalCourses = (int) single_value($conn, "SELECT COUNT(*) AS value FROM courses");
$totalEnrollments = (int) single_value($conn, "SELECT COUNT(*) AS value FROM enrollments");
$totalAdmins = (int) single_value($conn, "SELECT COUNT(*) AS value FROM admins");
$activeAdmins = (int) single_value($conn, "SELECT COUNT(*) AS value FROM admins WHERE status = 'active'");

$admins = [];
if ($admin['role'] === 'super_admin') {
    $adminResult = $conn->query("SELECT admin_id, username, email, role, status, created_at, updated_at, last_login FROM admins ORDER BY admin_id ASC");
    if ($adminResult) {
        while ($row = $adminResult->fetch_assoc()) {
            $admins[] = $row;
        }
    }
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
<body class="profile-page">
  <div class="portal">

    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">SMS</div>
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

      <?php echo sms_flash_html(); ?>

      <section class="profile-layout">
        <aside class="card profile-card">
          <div class="avatar"><?php echo e(admin_initials($admin['username'])); ?></div>
          <h3><?php echo e($admin['username']); ?></h3>
          <p>Signed-in Administrator</p>
          <div class="profile-status-row">
            <span class="account-chip"><?php echo e(ucfirst($admin['status'])); ?></span>
            <span class="account-chip account-chip-muted"><?php echo e(str_replace('_', ' ', ucwords($admin['role'], '_'))); ?></span>
          </div>

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
              <small>Status</small>
              <strong><?php echo e(ucfirst($admin['status'])); ?></strong>
            </div>
            <div class="meta-row">
              <small>Last Login</small>
              <strong><?php echo display_value($admin['last_login']); ?></strong>
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
          <div class="card section-card profile-system-card">
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
                <small>Active Admins</small>
                <strong><?php echo number_format($activeAdmins); ?></strong>
              </div>
              <div class="info-box">
                <small>System Status</small>
                <strong>Database Connected</strong>
              </div>
            </div>
          </div>

          <div class="card section-card profile-account-card">
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
                <small>Role</small>
                <strong><?php echo e(str_replace('_', ' ', ucwords($admin['role'], '_'))); ?></strong>
              </div>
              <div class="info-box">
                <small>Status</small>
                <strong><?php echo e(ucfirst($admin['status'])); ?></strong>
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

          <div class="card section-card profile-action-card">
            <div class="section-title">
              <div>
                <h3>Update Username</h3>
                <p>Change the username used for admin login.</p>
              </div>
            </div>

            <?php if ($messages['username']['text'] !== "") { ?>
              <p class="<?php echo e($messages['username']['type']); ?>-message"><?php echo e($messages['username']['text']); ?></p>
            <?php } ?>

            <form method="POST" action="profile.php">
              <input type="hidden" name="profile_action" value="update_username" />

              <div class="form-section">
                <h4>Username</h4>
                <p>This updates the logged-in admin account only.</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?php echo e($admin['username']); ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9 ._\-]{2,49}" required />
                  </div>
                </div>
              </div>

              <div class="button-row">
                <button type="submit" class="btn btn-primary">Update Username</button>
              </div>
            </form>
          </div>

          <div class="card section-card profile-action-card">
            <div class="section-title">
              <div>
                <h3>Update Email</h3>
                <p>Change the Gmail address connected to this admin account.</p>
              </div>
            </div>

            <?php if ($messages['email']['text'] !== "") { ?>
              <p class="<?php echo e($messages['email']['type']); ?>-message"><?php echo e($messages['email']['text']); ?></p>
            <?php } ?>

            <form method="POST" action="profile.php">
              <input type="hidden" name="profile_action" value="update_email" />

              <div class="form-section">
                <h4>Gmail Address</h4>
                <p>Only valid Gmail addresses ending in @gmail.com are accepted.</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="email">Gmail Address</label>
                    <input id="email" name="email" type="email" value="<?php echo e($admin['email']); ?>" placeholder="example@gmail.com" pattern="[A-Za-z0-9._%+\-]+@gmail\.com" required />
                  </div>
                </div>
              </div>

              <div class="button-row">
                <button type="submit" class="btn btn-primary">Update Email</button>
              </div>
            </form>
          </div>

          <div class="card section-card profile-action-card profile-password-card">
            <div class="section-title">
              <div>
                <h3>Change Password</h3>
                <p>Verify the current password before saving a new one.</p>
              </div>
            </div>

            <?php if ($messages['password']['text'] !== "") { ?>
              <p class="<?php echo e($messages['password']['type']); ?>-message"><?php echo e($messages['password']['text']); ?></p>
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

          <?php if ($admin['role'] === 'super_admin') { ?>
            <div class="card section-card profile-admin-management-card">
              <div class="section-title">
                <div>
                  <h3>Admin Management</h3>
                  <p>Create, review, enable, or disable administrator accounts.</p>
                </div>
                <a href="create_account.php" class="btn btn-primary">Create Admin</a>
              </div>

              <?php if ($messages['manage']['text'] !== "") { ?>
                <p class="<?php echo e($messages['manage']['type']); ?>-message"><?php echo e($messages['manage']['text']); ?></p>
              <?php } ?>

              <div class="table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Admin</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Last Login</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($admins as $adminRow) { ?>
                      <tr>
                        <td>
                          <strong><?php echo e($adminRow['username']); ?></strong>
                          <small>ID: <?php echo e($adminRow['admin_id']); ?></small>
                        </td>
                        <td><?php echo e($adminRow['email']); ?></td>
                        <td>
                          <span class="role-badge <?php echo e($adminRow['role']); ?>"><?php echo e(str_replace('_', ' ', ucwords($adminRow['role'], '_'))); ?></span>
                        </td>
                        <td>
                          <span class="status-pill <?php echo e($adminRow['status']); ?>"><?php echo e(ucfirst($adminRow['status'])); ?></span>
                        </td>
                        <td><?php echo display_value($adminRow['last_login']); ?></td>
                        <td>
                          <?php if ((int)$adminRow['admin_id'] === $adminId) { ?>
                            <span class="topbar-badge">Current Account</span>
                          <?php } else { ?>
                            <form class="inline-action-form" method="POST" action="profile.php">
                              <input type="hidden" name="profile_action" value="manage_admin" />
                              <input type="hidden" name="target_admin_id" value="<?php echo e($adminRow['admin_id']); ?>" />
                              <select name="role" aria-label="Role">
                                <option value="admin" <?php echo $adminRow['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="super_admin" <?php echo $adminRow['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                              </select>
                              <select name="status" aria-label="Status">
                                <option value="active" <?php echo $adminRow['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="disabled" <?php echo $adminRow['status'] === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                              </select>
                              <button type="submit" class="btn btn-update" onclick="return confirm('Save changes for this admin account?');">Save</button>
                            </form>
                          <?php } ?>
                        </td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php } ?>
        </section>
      </section>
    </main>
  </div>
</body>
</html>
