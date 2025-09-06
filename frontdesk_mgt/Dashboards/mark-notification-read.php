<?php
global $conn;
require_once '../dbConfig.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['userID']) && $_SESSION['Role'] === 'admin') {
    $notificationId = intval($_POST['notification_id']);
    $adminId = $_SESSION['userID'];

    // Ensure the admin is only marking their own notifications as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $adminId);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}
echo json_encode(['success' => false]);
?>