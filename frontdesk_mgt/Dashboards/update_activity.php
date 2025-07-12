<?php
session_start();
date_default_timezone_set("Africa/Accra");
global $conn;
require_once '../dbConfig.php';

if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();
    $stmt->close();

    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
    echo json_encode(['success' => true]); // Optional response
} else {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
}
?>