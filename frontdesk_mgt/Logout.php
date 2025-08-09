<?php
global $conn;
session_start();
require_once 'dbConfig.php';
require_once 'audit_logger.php';
$auditLogger = new AuditLogger($conn);

if (isset($_SESSION['userID'])) {
    $userID = $_SESSION['userID'];
    $userRole = $_SESSION['role'] ?? 'unknown';
    global $conn;

    // Update user logout time
    $updateStmt = $conn->prepare("UPDATE users SET 
        last_logout = NOW(),
        last_activity = NOW() 
        WHERE UserID = ?");
    $updateStmt->bind_param("i", $userID);
    $updateStmt->execute();
    $updateStmt->close();

    // Log logout event
    $auditLogger->logLogout($userID, $userRole);

    // Legacy activity logging (optional - can be removed if using audit logs)
    $activity = "Logged out";
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $userID, $activity);
    $stmt->execute();
    $stmt->close();
}

// Clear session
$_SESSION = array();
session_destroy();

// Redirect to login
header("Location: Auth.html");
exit;
?>