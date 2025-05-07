<?php
// Functions for handling notifications

/**
 * Create a new notification
 * @param mysqli $conn Database connection
 * @param int $userId User ID to receive notification
 * @param int $ticketId Ticket ID associated with notification
 * @param string $type Type of notification ('assignment', 'info_request')
 * @param array $payload Additional data for notification
 * @return bool Success status
 */
function createNotification($conn, $userId, $ticketId, $type, $payload) {
    // Validate type against allowed values
    $allowedTypes = ['assignment', 'info_request', 'resolution', 'closure', 'auto_closure'];
    if (!in_array($type, $allowedTypes)) {
        error_log("Invalid notification type: $type");
        return false;
    }

    $payloadJson = json_encode($payload);

    $sql = "INSERT INTO Notifications (UserID, TicketID, Type, Payload) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iiss", $userId, $ticketId, $type, $payloadJson);

    try {
        $result = $stmt->execute();
        $notificationId = $stmt->insert_id;
        $stmt->close();

        if ($result) {
            // Log successful notification creation
            error_log("Created notification #$notificationId for user $userId, ticket $ticketId, type $type");

            // Attempt to notify user in real-time via WebSocket
            notifyUserViaWebSocket($userId, [
                'id' => $notificationId,
                'ticketId' => $ticketId,
                'type' => $type,
                'payload' => $payload,
                'createdAt' => date('Y-m-d H:i:s')
            ]);
        }

        return $result;
    } catch (mysqli_sql_exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param bool $unreadOnly Get only unread notifications
 * @return array Notifications
 */
function getUserNotifications($conn, $userId, $unreadOnly = false) {
    $sql = "SELECT n.NotificationID, n.TicketID, n.Type, n.Payload, n.IsRead, n.CreatedAt,
            h.Description as TicketDescription
            FROM Notifications n
            JOIN Help_Desk h ON n.TicketID = h.TicketID 
            WHERE n.UserID = ?";

    if ($unreadOnly) {
        $sql .= " AND n.IsRead = FALSE";
    }

    $sql .= " ORDER BY n.CreatedAt DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['Payload'] = json_decode($row['Payload'], true);
        $notifications[] = $row;
    }

    return $notifications;
}

/**
 * Mark a notification as read
 * @param mysqli $conn Database connection
 * @param int $notificationId Notification ID
 * @return bool Success status
 */
function markNotificationAsRead($conn, $notificationId) {
    $sql = "UPDATE Notifications SET IsRead = TRUE WHERE NotificationID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificationId);

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Mark all notifications for a user as read
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return bool Success status
 */
function markAllNotificationsAsRead($conn, $userId) {
    $sql = "UPDATE Notifications SET IsRead = TRUE WHERE UserID = ? AND IsRead = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get unread notification count for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($conn, $userId) {
    $sql = "SELECT COUNT(*) as count FROM Notifications WHERE UserID = ? AND IsRead = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'];
}

/**
 * Send notification via WebSocket
 * @param int $userId User ID to receive notification
 * @param array $notification Notification data
 */
function notifyUserViaWebSocket($userId, $notification) {
    // Get WebSocket server instance
    if (file_exists('server_instance.dat')) {
        try {
            $server = unserialize(file_get_contents('server_instance.dat'));
            if ($server) {
                $server->notifyUser($userId, $notification);
            }
        } catch (Exception $e) {
            // Log error but continue - fall back to database notifications
            error_log('WebSocket notification error: ' . $e->getMessage());
        }
    }
}