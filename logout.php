<?php
require_once __DIR__ . '/includes/session.php';
eventease_start_session();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid logout request.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', array(
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite']
    ));
}

session_destroy();
header('Location: login.php');
exit();