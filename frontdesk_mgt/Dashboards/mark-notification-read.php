<?php
global $conn;
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

$cookieName = session_name();
if (isset($_COOKIE[$cookieName])) {
    session_id($_COOKIE[$cookieName]);
}

session_start();

// Allow CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../dbConfig.php';

if (isset($_POST['notification_id'])) {
    $notificationId = intval($_POST['notification_id']);
    $userId = $_SESSION['userID'];

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Notification ID required']);
}

$conn->close();
?>