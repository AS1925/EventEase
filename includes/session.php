<?php
date_default_timezone_set('Asia/Kolkata');

function eventease_start_session()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secure_cookie = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure_cookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ));

    session_start();
}