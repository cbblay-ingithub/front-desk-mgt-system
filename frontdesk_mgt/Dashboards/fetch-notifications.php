<?php
// Configure session settings
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

// Start session with the same ID from cookie if exists
$cookieName = session_name();
if (isset($_COOKIE[$cookieName])) {
    session_id($_COOKIE[$cookieName]);
}

session_start();

// Allow CORS for development
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
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Please login first',
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

require_once '../dbConfig.php';
$adminId = $_SESSION['userID'];

try {
    // Update last activity
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->close();

    // Fetch notifications with improved query
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $stmt = $conn->prepare("
        SELECT id, type, title, message, related_entity_type, related_entity_id, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $adminId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->bind_param("i", $adminId);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $unreadCount = $countResult['unread_count'] ?? 0;
    $countStmt->close();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>