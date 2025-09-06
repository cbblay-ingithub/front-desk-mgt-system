<?php
// Turn off all error reporting for production
error_reporting(0);
ini_set("display_errors", 0);

// Start output buffering
ob_start();

// Configure session settings BEFORE session_start() - EXACT same as dashboard
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// For development on different ports, make cookie accessible to all ports
if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

// Try to require the config file
try {
    require_once "../dbConfig.php";
} catch (Exception $e) {
    ob_end_clean();
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "error" => "Config loading failed"]);
    exit;
}

// Check if session cookie exists and try to use it
$cookieName = session_name();
if (isset($_COOKIE[$cookieName])) {
    session_id($_COOKIE[$cookieName]);
    error_log("Using existing session ID from cookie: " . $_COOKIE[$cookieName]);
}

// Start session
session_start();

// Debug logging
error_log("fetch-notifications.php - Session ID: " . session_id());
error_log("fetch-notifications.php - Session data: " . print_r($_SESSION, true));
error_log("fetch-notifications.php - Cookie data: " . print_r($_COOKIE, true));

// Check if we have a valid session
if (!isset($_SESSION["userID"])) {
    // Try to regenerate session if cookie exists but session is empty
    if (isset($_COOKIE[session_name()])) {
        session_regenerate_id(true);
        error_log("Session regenerated with ID: " . session_id());
    }

    ob_end_clean();
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error" => "No active session",
        "debug" => [
            "session_data" => $_SESSION,
            "session_id" => session_id(),
            "cookie_name" => session_name(),
            "cookie_exists" => isset($_COOKIE[session_name()]),
            "cookie_value" => $_COOKIE[session_name()] ?? "not_set",
            "user_id_set" => isset($_SESSION["userID"]),
            "role_set" => isset($_SESSION["role"]),
            "role_value" => $_SESSION["role"] ?? "not_set"
        ]
    ]);
    exit;
}

// Check if user is admin
$userRole = strtolower($_SESSION["role"] ?? "");
if ($userRole !== "admin") {
    ob_end_clean();
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error" => "Insufficient permissions",
        "debug" => [
            "user_id" => $_SESSION["userID"],
            "role_value" => $_SESSION["role"] ?? "not_set",
            "role_lowercase" => $userRole,
            "is_admin" => ($userRole === "admin")
        ]
    ]);
    exit;
}

$adminId = $_SESSION["userID"];

try {
    // Update last activity
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->close();

    // Fetch notifications
    $stmt = $conn->prepare("SELECT id, type, title, message, related_entity_type, related_entity_id, is_read, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->bind_param("i", $adminId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $unreadCountData = $countResult->fetch_assoc();
    $unreadCount = $unreadCountData["unread_count"] ?? 0;
    $countStmt->close();

    // Clean output and return JSON
    ob_end_clean();
    header("Content-Type: application/json");
    echo json_encode([
        "success" => true,
        "notifications" => $notifications,
        "unread_count" => $unreadCount,
        "debug" => [
            "admin_id" => $adminId,
            "notification_count" => count($notifications),
            "session_id" => session_id()
        ]
    ]);

} catch (Exception $e) {
    error_log("fetch-notifications.php error: " . $e->getMessage());
    ob_end_clean();
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error" => "Database error",
        "debug" => ["error_message" => $e->getMessage()]
    ]);
}

$conn->close();
?>