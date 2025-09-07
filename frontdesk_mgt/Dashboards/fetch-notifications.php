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
    // Add these parameters at the top after session check
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(20, max(5, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] === 'true';

    if ($countOnly) {
        // Just return badge count
        $countStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $countStmt->bind_param("i", $adminId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $unreadCount = $countResult['unread_count'] ?? 0;
        $countStmt->close();

        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
        exit;
    }

// Build query based on filter
    $query = "SELECT id, type, title, message, related_entity_type, related_entity_id, is_read, created_at 
          FROM notifications WHERE user_id = ?";
    $params = [$adminId];
    $types = "i";

    if ($filter === 'unread') {
        $query .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $query .= " AND is_read = 1";
    }

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

// Execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

// Get counts for filter badges
    $countsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
    FROM notifications WHERE user_id = ?
");
    $countsStmt->bind_param("i", $adminId);
    $countsStmt->execute();
    $countsResult = $countsStmt->get_result()->fetch_assoc();
    $countsStmt->close();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $countsResult['unread'],
        'counts' => [
            'total' => $countsResult['total'],
            'unread' => $countsResult['unread'],
            'read' => $countsResult['read_count']
        ]
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>