<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'sakthi';

$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost'));
$serverHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$serverHost = preg_replace('/:\d+$/', '', $serverHost);
$localHosts = array('localhost', '127.0.0.1', '::1');
$isLocalRequest = PHP_SAPI === 'cli'
    || in_array($serverName, $localHosts, true)
    || in_array($serverHost, $localHosts, true)
    || strpos($serverName, 'localhost') !== false
    || strpos($serverName, '127.0.0.1') !== false
    || strpos($serverName, '::1') !== false;

if (
    $dbName !== 'sakthi'
    || $dbHost !== 'localhost'
    || $dbUser !== 'root'
    || $dbPassword !== ''
    || !$isLocalRequest
) {
    error_log('Database configuration is restricted to the local XAMPP sakthi database.');
    http_response_code(500);
    exit('Database configuration error.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed with MySQL error code ' . $exception->getCode() . '.');
    http_response_code(500);
    exit('Database connection failed.');
}
?>