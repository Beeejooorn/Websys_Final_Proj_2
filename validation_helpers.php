<?php
function sms_is_valid_person_name($name) {
    $name = trim((string)$name);
    return $name !== ''
        && strlen($name) <= 100
        && preg_match("/^[\p{L}][\p{L} .'\-]{1,99}$/u", $name) === 1;
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

function sms_is_valid_admin_username($username) {
    $username = trim((string)$username);
    return strlen($username) >= 3
        && strlen($username) <= 50
        && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\-]*$/', $username) === 1;
}

function sms_is_valid_password($password) {
    return strlen((string)$password) >= 8;
}
?>
