<?php
global $conn;
require_once '../dbConfig.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

try {
    $userId = $_SESSION['userID'];
    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] == 'true';
    $lastCheck = isset($_GET['last_check']) ? intval($_GET['last_check']) : 0;
    $filter = $_GET['filter'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Convert last_check timestamp to MySQL datetime
    $lastCheckDate = $lastCheck > 0 ? date('Y-m-d H:i:s', $lastCheck / 1000) : '1970-01-01 00:00:00';

    if ($countOnly) {
        // Just return counts and new notifications since last check
        $response = getNotificationCounts($conn, $userId, $lastCheckDate);
    } else {
        // Return full notification list
        $response = getNotifications($conn, $userId, $filter, $offset, $limit);
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Notification fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

function getNotificationCounts($conn, $userId, $lastCheckDate) {
    try {
        // Get total unread count
        $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $unreadCount = $result->fetch_assoc()['unread_count'];
        $stmt->close();

        // Get new notifications since last check
        $stmt = $conn->prepare("
            SELECT 
                id, title, message, type, related_entity_type, related_entity_id, 
                created_at, is_read, priority
            FROM notifications 
            WHERE user_id = ? AND created_at > ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->bind_param("is", $userId, $lastCheckDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $newNotifications = [];
        while ($row = $result->fetch_assoc()) {
            $newNotifications[] = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'],
                'related_entity_type' => $row['related_entity_type'],
                'related_entity_id' => $row['related_entity_id'],
                'created_at' => $row['created_at'],
                'is_read' => (bool)$row['is_read'],
                'priority' => $row['priority'] ?? 'normal'
            ];
        }
        $stmt->close();

        // Get counts by filter
        $counts = getFilterCounts($conn, $userId);

        return [
            'success' => true,
            'unread_count' => $unreadCount,
            'new_notifications' => $newNotifications,
            'counts' => $counts,
            'server_time' => time() * 1000 // JavaScript timestamp
        ];

    } catch (Exception $e) {
        throw new Exception("Failed to get notification counts: " . $e->getMessage());
    }
}

function getNotifications($conn, $userId, $filter, $offset, $limit) {
    try {
        // Build WHERE clause based on filter
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        $paramTypes = "i";

        switch ($filter) {
            case 'unread':
                $whereClause .= " AND is_read = 0";
                break;
            case 'read':
                $whereClause .= " AND is_read = 1";
                break;
            case 'priority':
                $whereClause .= " AND priority = 'high'";
                break;
            // 'all' case needs no additional conditions
        }

        // Get notifications with pagination
        $stmt = $conn->prepare("
            SELECT 
                id, title, message, type, related_entity_type, related_entity_id, 
                created_at, is_read, priority, metadata
            FROM notifications 
            $whereClause 
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                    ELSE 2 
                END,
                created_at DESC 
            LIMIT ? OFFSET ?
        ");

        $paramTypes .= "ii";
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $metadata = $row['metadata'] ? json_decode($row['metadata'], true) : [];

            $notifications[] = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'],
                'related_entity_type' => $row['related_entity_type'],
                'related_entity_id' => $row['related_entity_id'],
                'created_at' => $row['created_at'],
                'is_read' => (bool)$row['is_read'],
                'priority' => $row['priority'] ?? 'normal',
                'metadata' => $metadata
            ];
        }
        $stmt->close();

        // Get counts for filter tabs
        $counts = getFilterCounts($conn, $userId);

        // Get total unread count for badge
        $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $unreadCount = $result->fetch_assoc()['unread_count'];
        $stmt->close();

        return [
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'counts' => $counts,
            'pagination' => [
                'page' => ($offset / $limit) + 1,
                'limit' => $limit,
                'has_more' => count($notifications) === $limit
            ]
        ];

    } catch (Exception $e) {
        throw new Exception("Failed to get notifications: " . $e->getMessage());
    }
}

function getFilterCounts($conn, $userId) {
    try {
        // Get counts for each filter
        $counts = [
            'total' => 0,
            'unread' => 0,
            'read' => 0,
            'priority' => 0
        ];

        // Total count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts['total'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Unread count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts['unread'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Read count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts['read'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Priority count (high priority notifications)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND priority = 'high'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts['priority'] = $result->fetch_assoc()['count'];
        $stmt->close();

        return $counts;

    } catch (Exception $e) {
        throw new Exception("Failed to get filter counts: " . $e->getMessage());
    }
}

// Update user's last notification check time
if (!$countOnly && isset($_SESSION['userID'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_notification_check = NOW() WHERE UserID = ?");
        $stmt->bind_param("i", $_SESSION['userID']);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to update last notification check: " . $e->getMessage());
    }
}

$conn->close();
?>