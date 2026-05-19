<?php
function sms_is_valid_person_name($name) {
    $name = trim((string)$name);
    return $name !== ''
        && strlen($name) <= 100
        && preg_match("/^[\p{L}][\p{L} .'\-]{1,99}$/u", $name) === 1;
}

function sms_is_valid_student_number($studentNumber) {
    return preg_match('/^[0-9]{10}$/', trim((string)$studentNumber)) === 1;
}

function sms_is_valid_contact_number($contact) {
    $contact = trim((string)$contact);

    if ($contact === '') {
        return true;
    }

    return preg_match('/^\+?[0-9][0-9\s-]{6,19}$/', $contact) === 1;
}

function sms_is_valid_gmail_address($email) {
    $email = trim((string)$email);
    return $email !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL)
        && preg_match('/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', $email) === 1;
}

function sms_valid_year_levels() {
    return ['1st Year', '2nd Year', '3rd Year', '4th Year'];
}

function sms_is_valid_year_level($yearLevel) {
    return in_array($yearLevel, sms_valid_year_levels(), true);
}

function sms_is_valid_birthdate($birthdate) {
    $birthdate = trim((string)$birthdate);

    if ($birthdate === '') {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    $errors = DateTime::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return false;
    }

    if ($date->format('Y-m-d') !== $birthdate) {
        return false;
    }

    return $date <= new DateTime('today');
}

function sms_is_valid_semester($semester) {
    $semester = trim((string)$semester);
    return $semester !== ''
        && strlen($semester) <= 50
        && preg_match('/^[A-Za-z0-9 ._\-]+$/', $semester) === 1;
}

function sms_is_valid_grade($grade) {
    $grade = trim((string)$grade);

    if ($grade === '') {
        return true;
    }

    return preg_match('/^(N\/A|INC|[0-9]{1,3}(\.[0-9]{1,2})?)$/i', $grade) === 1;
}

function sms_is_valid_positive_id($id) {
    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

function sms_is_valid_admin_username($username) {
    $username = trim((string)$username);
    return strlen($username) >= 3
        && strlen($username) <= 50
        && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\-]*$/', $username) === 1;
}

function sms_is_valid_password($password) {
    return strlen((string)$password) >= 8;
}

function sms_is_valid_admin_role($role) {
    return in_array($role, ['super_admin', 'admin'], true);
}

function sms_is_valid_admin_status($status) {
    return in_array($status, ['active', 'disabled'], true);
}

function sms_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sms_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function sms_set_flash($type, $message) {
    sms_start_session();
    $_SESSION['flash_message'] = [
        'type' => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
        'message' => (string)$message
    ];
}

function sms_get_flash() {
    sms_start_session();

    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $message;
}

function sms_flash_html() {
    $flash = sms_get_flash();

    if (!$flash) {
        return '';
    }

    $class = $flash['type'] . '-message';
    $role = $flash['type'] === 'error' ? 'alert' : 'status';
    return '<div class="' . sms_escape($class) . '" role="' . sms_escape($role) . '">' . sms_escape($flash['message']) . '</div>';
}

function sms_redirect($url) {
    header('Location: ' . $url);
    exit();
}

function sms_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function sms_current_admin_id() {
    sms_start_session();
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function sms_log_activity($conn, $action, $description = '', $adminId = null) {
    if (!$conn instanceof mysqli) {
        return;
    }

    $adminId = $adminId ?? sms_current_admin_id();
    $ipAddress = sms_client_ip();
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("isss", $adminId, $action, $description, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

function sms_log_login_attempt($conn, $adminId, $identifier, $success) {
    if (!$conn instanceof mysqli) {
        return;
    }

    $ipAddress = sms_client_ip();
    $successValue = $success ? 1 : 0;
    $adminIdValue = $adminId !== null ? (int)$adminId : null;
    $stmt = $conn->prepare("INSERT INTO admin_login_attempts (admin_id, login_identifier, ip_address, success) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("issi", $adminIdValue, $identifier, $ipAddress, $successValue);
    $stmt->execute();
    $stmt->close();
}

function sms_load_admin_by_id($conn, $adminId) {
    $stmt = $conn->prepare("SELECT admin_id, username, email, role, status, created_at, updated_at, last_login, failed_login_count, locked_until FROM admins WHERE admin_id = ?");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    return $admin ?: null;
}

function sms_is_locked($lockedUntil) {
    if (empty($lockedUntil)) {
        return false;
    }

    return strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time();
}

function sms_duplicate_message_from_error($errorMessage, $fallback = 'A duplicate record already exists.') {
    $message = strtolower((string)$errorMessage);

    if (strpos($message, 'student_number') !== false) {
        return 'Student ID already exists. Please enter a different Student ID.';
    }

    if (strpos($message, 'email') !== false) {
        return 'That email address already exists. Please use another Gmail address.';
    }

    if (strpos($message, 'username') !== false) {
        return 'That username already exists. Please choose a different username.';
    }

    if (strpos($message, 'course_name') !== false) {
        return 'That course name already exists.';
    }

    if (strpos($message, 'course_code') !== false) {
        return 'That course code already exists.';
    }

    if (strpos($message, 'student_id') !== false && strpos($message, 'course_id') !== false && strpos($message, 'semester') !== false) {
        return 'This student is already enrolled in this course for the selected semester.';
    }

    return $fallback;
}
?>
