<?php
// Configure session settings exactly like the dashboard
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'user_logged_in' => isset($_SESSION['userID']),
    'user_role' => $_SESSION['role'] ?? 'not_set',
    'php_session_name' => session_name(),
    'server_info' => [
        'http_host' => $_SERVER['HTTP_HOST'],
        'request_uri' => $_SERVER['REQUEST_URI']
    ]
]);
?>