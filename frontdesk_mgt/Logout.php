<?php
session_start();
require_once 'dbConfig.php';

if (isset($_SESSION['userID'])) {
    $userID = $_SESSION['userID'];
    global $conn;

    // SINGLE UPDATE: Set logout timestamp
    $updateStmt = $conn->prepare("UPDATE users SET 
        last_logout = NOW(),
        last_activity = NOW() 
        WHERE UserID = ?");
    $updateStmt->bind_param("i", $userID);
    $updateStmt->execute();
    $updateStmt->close();

    // Record logout activity
    $activity = "Logged out";
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $userID, $activity);
    $stmt->execute();
    $stmt->close();

}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: Auth.html");
exit;
?>