<?php
require_once __DIR__ . '/session.php';

if (!defined('EVENTEASE_ADMIN_EMAIL')) {
    define('EVENTEASE_ADMIN_EMAIL', 'admin@gmail.com');
}

function eventease_csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        eventease_start_session();
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function eventease_require_valid_csrf_post()
{
    if (session_status() === PHP_SESSION_NONE) {
        eventease_start_session();
    }

    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST'
        || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Invalid request.');
    }
}

function eventease_is_valid_image_upload($upload)
{
    if (
        !is_array($upload)
        || !isset($upload['error'], $upload['name'], $upload['tmp_name'])
        || $upload['error'] !== UPLOAD_ERR_OK
        || !is_uploaded_file($upload['tmp_name'])
    ) {
        return false;
    }

    $mime_types = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    );
    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $file_size = filesize($upload['tmp_name']);

    if (!isset($mime_types[$extension]) || $file_size === false || $file_size < 1 || $file_size > 5 * 1024 * 1024) {
        return false;
    }

    $image_info = @getimagesize($upload['tmp_name']);
    return $image_info !== false && $image_info['mime'] === $mime_types[$extension];
}

function eventease_parse_datetime_local($value)
{
    if (!is_string($value)) {
        return false;
    }
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTime::getLastErrors();
    if (
        !$date
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        return false;
    }

    return $date->format('Y-m-d H:i:s');
}

function eventease_checkin_window_status($event, $now = null)
{
    if (
        !is_array($event)
        || !isset($event['event_date'])
        || !is_string($event['event_date'])
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $event['event_date']) !== 1
    ) {
        return array('status' => 'closed', 'opens_at' => null, 'closes_at' => null);
    }

    $timezone = new DateTimeZone('Asia/Kolkata');
    $start_time = isset($event['start_time']) && is_string($event['start_time'])
        ? $event['start_time']
        : '';
    if ($start_time === '' || $start_time === '00:00:00') {
        $start_time = isset($event['event_time']) && is_string($event['event_time'])
            ? $event['event_time']
            : '';
    }
    if ($start_time === '') {
        $start_time = '00:00:00';
    } elseif (strlen($start_time) === 5) {
        $start_time .= ':00';
    }

    $end_time = isset($event['end_time']) && is_string($event['end_time'])
        ? $event['end_time']
        : '';
    if ($end_time === '') {
        $end_time = '23:59:59';
    } elseif (strlen($end_time) === 5) {
        $end_time .= ':00';
    }

    $start = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $event['event_date'] . ' ' . $start_time,
        $timezone
    );
    $end = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $event['event_date'] . ' ' . $end_time,
        $timezone
    );
    if (!$start || !$end || $end < $start) {
        return array('status' => 'closed', 'opens_at' => null, 'closes_at' => null);
    }

    $opens_at = $start->sub(new DateInterval('PT2H'));
    $current_time = $now instanceof DateTimeImmutable
        ? $now->setTimezone($timezone)
        : new DateTimeImmutable('now', $timezone);
    if ($current_time < $opens_at) {
        $status = 'not_open';
    } elseif ($current_time > $end) {
        $status = 'closed';
    } else {
        $status = 'open';
    }

    return array(
        'status' => $status,
        'opens_at' => $opens_at->format('Y-m-d H:i:s'),
        'closes_at' => $end->format('Y-m-d H:i:s')
    );
}

function eventease_generate_registration_number($conn)
{
    $check = $conn->prepare('SELECT 1 FROM registrations WHERE registration_number = ? LIMIT 1');
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    for ($attempt = 0; $attempt < 10; $attempt++) {
        try {
            $random_bytes = random_bytes(12);
        } catch (Exception $exception) {
            throw new RuntimeException('Could not obtain secure randomness for a registration number.', 0, $exception);
        }
        $suffix = '';
        for ($index = 0; $index < 12; $index++) {
            $suffix .= $alphabet[ord($random_bytes[$index]) & 31];
        }
        $number = 'EE-' . date('Y') . '-' . $suffix;
        $check->bind_param('s', $number);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            return $number;
        }
    }

    throw new RuntimeException('Could not generate a unique registration number.');
}

function eventease_current_role()
{
    if (session_status() === PHP_SESSION_NONE) {
        eventease_start_session();
    }

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return '';
    }

    $role = (string)($_SESSION['role'] ?? '');
    if ($role === 'user') {
        $role = 'customer';
    }
    if (!in_array($role, array('customer', 'organizer', 'vendor', 'admin'), true)) {
        $email = strtolower((string)($_SESSION['user_email'] ?? ''));
        $role = $email === EVENTEASE_ADMIN_EMAIL ? 'admin' : 'customer';
    }

    $_SESSION['role'] = $role;
    return $role;
}

function require_role(...$allowed_roles)
{
    if (session_status() === PHP_SESSION_NONE) {
        eventease_start_session();
    }

    $base_path = (
        strpos($_SERVER['PHP_SELF'], '/admin/') !== false
        || strpos($_SERVER['PHP_SELF'], '/user/') !== false
        || strpos($_SERVER['PHP_SELF'], '/organizer/') !== false
        || strpos($_SERVER['PHP_SELF'], '/vendor/') !== false
    ) ? '../' : '';

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: ' . $base_path . 'login.php');
        exit();
    }

    $role = eventease_current_role();
    $allowed = array_map(function ($allowed_role) {
        return $allowed_role === 'user' ? 'customer' : (string)$allowed_role;
    }, $allowed_roles);

    if (!in_array($role, $allowed, true)) {
        if ($role === 'admin') {
            header('Location: ' . $base_path . 'admin/dashboard.php');
        } elseif ($role === 'organizer') {
            header('Location: ' . $base_path . 'organizer/dashboard.php');
        } elseif ($role === 'vendor') {
            header('Location: ' . $base_path . 'vendor/dashboard.php');
        } elseif ($role === 'customer') {
            header('Location: ' . $base_path . 'user/dashboard.php');
        } else {
            header('Location: ' . $base_path . 'login.php');
        }
        exit();
    }
}