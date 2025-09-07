<?php
// Configure session settings
global $conn;
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['userID']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../dbConfig.php';
$adminId = $_SESSION['userID'];

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>