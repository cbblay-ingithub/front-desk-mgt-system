<?php
/**
 * Notification Creator Class
 * Helps create notifications for the global notification system
 */

class NotificationCreator {
    private $conn;
    private $defaultTypes = [
        'user' => 'User Management',
        'ticket' => 'Help Desk',
        'appointment' => 'Appointments',
        'visitor' => 'Visitor Management',
        'lost_item' => 'Lost & Found',
        'system' => 'System',
        'security' => 'Security Alert'
    ];

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    /**
     * Create a notification for specific users
     */
    public function createNotification($userIds, $title, $message, $type = 'system', $options = []) {
        try {
            // Ensure userIds is an array
            if (!is_array($userIds)) {
                $userIds = [$userIds];
            }

            $priority = $options['priority'] ?? 'normal';
            $relatedEntityType = $options['related_entity_type'] ?? null;
            $relatedEntityId = $options['related_entity_id'] ?? null;
            $metadata = isset($options['metadata']) ? json_encode($options['metadata']) : null;

            $stmt = $this->conn->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, priority, related_entity_type, related_entity_id, metadata, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $successCount = 0;
            foreach ($userIds as $userId) {
                $stmt->bind_param("isssssis",
                    $userId, $title, $message, $type, $priority,
                    $relatedEntityType, $relatedEntityId, $metadata
                );

                if ($stmt->execute()) {
                    $successCount++;
                }
            }

            $stmt->close();

            return [
                'success' => $successCount > 0,
                'created_count' => $successCount,
                'total_recipients' => count($userIds)
            ];

        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create notification for all admins
     */
    public function notifyAllAdmins($title, $message, $type = 'system', $options = []) {
        try {
            // Get all admin user IDs
            $stmt = $this->conn->prepare("SELECT UserID FROM users WHERE Role IN ('admin', 'super_admin')");
            $stmt->execute();
            $result = $stmt->get_result();

            $adminIds = [];
            while ($row = $result->fetch_assoc()) {
                $adminIds[] = $row['UserID'];
            }
            $stmt->close();

            if (empty($adminIds)) {
                return [
                    'success' => false,
                    'error' => 'No admin users found'
                ];
            }

            return $this->createNotification($adminIds, $title, $message, $type, $options);

        } catch (Exception $e) {
            error_log("Failed to notify all admins: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create user-related notifications
     */
    public function notifyUserEvent($userId, $event, $targetUserId = null, $metadata = []) {
        $templates = [
            'user_registered' => [
                'title' => 'New User Registration',
                'message' => 'A new user has registered and requires approval.',
                'type' => 'user',
                'priority' => 'normal'
            ],
            'user_updated' => [
                'title' => 'User Profile Updated',
                'message' => 'A user profile has been updated.',
                'type' => 'user',
                'priority' => 'low'
            ],
            'user_suspended' => [
                'title' => 'User Account Suspended',
                'message' => 'A user account has been suspended.',
                'type' => 'user',
                'priority' => 'high'
            ],
            'user_role_changed' => [
                'title' => 'User Role Changed',
                'message' => 'A user\'s role has been modified.',
                'type' => 'user',
                'priority' => 'normal'
            ],
            'password_reset_request' => [
                'title' => 'Password Reset Request',
                'message' => 'A user has requested a password reset.',
                'type' => 'security',
                'priority' => 'normal'
            ]
        ];

        if (!isset($templates[$event])) {
            return ['success' => false, 'error' => 'Unknown user event'];
        }

        $template = $templates[$event];
        $options = [
            'priority' => $template['priority'],
            'related_entity_type' => 'user',
            'related_entity_id' => $targetUserId,
            'metadata' => $metadata
        ];

        return $this->notifyAllAdmins($template['title'], $template['message'], $template['type'], $options);
    }

    /**
     * Create ticket-related notifications
     */
    public function notifyTicketEvent($userId, $event, $ticketId, $metadata = []) {
        $templates = [
            'ticket_created' => [
                'title' => 'New Support Ticket',
                'message' => 'A new support ticket has been submitted.',
                'type' => 'ticket',
                'priority' => 'normal'
            ],
            'ticket_updated' => [
                'title' => 'Ticket Updated',
                'message' => 'A support ticket has been updated.',
                'type' => 'ticket',
                'priority' => 'low'
            ],
            'ticket_urgent' => [
                'title' => 'Urgent Ticket',
                'message' => 'An urgent support ticket requires immediate attention.',
                'type' => 'ticket',
                'priority' => 'high'
            ],
            'ticket_resolved' => [
                'title' => 'Ticket Resolved',
                'message' => 'A support ticket has been resolved.',
                'type' => 'ticket',
                'priority' => 'low'
            ]
        ];

        if (!isset($templates[$event])) {
            return ['success' => false, 'error' => 'Unknown ticket event'];
        }

        $template = $templates[$event];
        $options = [
            'priority' => $template['priority'],
            'related_entity_type' => 'ticket',
            'related_entity_id' => $ticketId,
            'metadata' => $metadata
        ];

        return $this->notifyAllAdmins($template['title'], $template['message'], $template['type'], $options);
    }

    /**
     * Create appointment-related notifications
     */
    public function notifyAppointmentEvent($userId, $event, $appointmentId, $metadata = []) {
        $templates = [
            'appointment_created' => [
                'title' => 'New Appointment Scheduled',
                'message' => 'A new appointment has been scheduled.',
                'type' => 'appointment',
                'priority' => 'normal'
            ],
            'appointment_cancelled' => [
                'title' => 'Appointment Cancelled',
                'message' => 'An appointment has been cancelled.',
                'type' => 'appointment',
                'priority' => 'normal'
            ],
            'appointment_reminder' => [
                'title' => 'Appointment Reminder',
                'message' => 'Upcoming appointment requires attention.',
                'type' => 'appointment',
                'priority' => 'normal'
            ],
            'appointment_missed' => [
                'title' => 'Missed Appointment',
                'message' => 'An appointment was missed.',
                'type' => 'appointment',
                'priority' => 'low'
            ]
        ];

        if (!isset($templates[$event])) {
            return ['success' => false, 'error' => 'Unknown appointment event'];
        }

        $template = $templates[$event];
        $options = [
            'priority' => $template['priority'],
            'related_entity_type' => 'appointment',
            'related_entity_id' => $appointmentId,
            'metadata' => $metadata
        ];

        return $this->notifyAllAdmins($template['title'], $template['message'], $template['type'], $options);
    }

    /**
     * Create visitor-related notifications
     */
    public function notifyVisitorEvent($userId, $event, $visitorId, $metadata = []) {
        $templates = [
            'visitor_checkin' => [
                'title' => 'Visitor Check-in',
                'message' => 'A visitor has checked in.',
                'type' => 'visitor',
                'priority' => 'low'
            ],
            'visitor_checkout' => [
                'title' => 'Visitor Check-out',
                'message' => 'A visitor has checked out.',
                'type' => 'visitor',
                'priority' => 'low'
            ],
            'visitor_overstay' => [
                'title' => 'Visitor Overstay Alert',
                'message' => 'A visitor has exceeded their expected visit duration.',
                'type' => 'visitor',
                'priority' => 'normal'
            ],
            'visitor_security' => [
                'title' => 'Visitor Security Alert',
                'message' => 'A security incident involving a visitor has been reported.',
                'type' => 'security',
                'priority' => 'high'
            ]
        ];

        if (!isset($templates[$event])) {
            return ['success' => false, 'error' => 'Unknown visitor event'];
        }

        $template = $templates[$event];
        $options = [
            'priority' => $template['priority'],
            'related_entity_type' => 'visitor',
            'related_entity_id' => $visitorId,
            'metadata' => $metadata
        ];

        return $this->notifyAllAdmins($template['title'], $template['message'], $template['type'], $options);
    }

    /**
     * Create system notifications
     */
    public function notifySystemEvent($event, $metadata = []) {
        $templates = [
            'system_backup' => [
                'title' => 'System Backup Completed',
                'message' => 'Scheduled system backup has completed successfully.',
                'type' => 'system',
                'priority' => 'low'
            ],
            'system_maintenance' => [
                'title' => 'System Maintenance',
                'message' => 'System maintenance is scheduled.',
                'type' => 'system',
                'priority' => 'normal'
            ],
            'system_error' => [
                'title' => 'System Error',
                'message' => 'A system error has been detected.',
                'type' => 'system',
                'priority' => 'high'
            ],
            'security_breach' => [
                'title' => 'Security Alert',
                'message' => 'Potential security breach detected.',
                'type' => 'security',
                'priority' => 'high'
            ]
        ];

        if (!isset($templates[$event])) {
            return ['success' => false, 'error' => 'Unknown system event'];
        }

        $template = $templates[$event];
        $options = [
            'priority' => $template['priority'],
            'metadata' => $metadata
        ];

        return $this->notifyAllAdmins($template['title'], $template['message'], $template['type'], $options);
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats($userId = null) {
        try {
            $whereClause = $userId ? "WHERE user_id = ?" : "";
            $params = $userId ? [$userId] : [];
            $paramTypes = $userId ? "i" : "";

            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
                    COUNT(DISTINCT type) as unique_types
                FROM notifications 
                $whereClause
            ");

            if ($userId) {
                $stmt->bind_param($paramTypes, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications($daysToKeep = 30) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND is_read = 1
            ");
            $stmt->bind_param("i", $daysToKeep);
            $stmt->execute();
            $deletedCount = $stmt->affected_rows;
            $stmt->close();

            return [
                'success' => true,
                'deleted_count' => $deletedCount
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Create ticket assignment notification
     */
    public function notifyTicketAssignment($UserID, $ticketId, $ticketDescription, $assignedByName) {
        $title = "New Ticket Assigned to You";
        $message = "You have been assigned to ticket #{$ticketId}: " .
            (strlen($ticketDescription) > 50 ?
                substr($ticketDescription, 0, 50) . '...' : $ticketDescription);

        $options = [
            'priority' => 'normal',
            'related_entity_type' => 'ticket',
            'related_entity_id' => $ticketId,
            'metadata' => [
                'assigned_by' => $assignedByName,
                'assigned_at' => date('Y-m-d H:i:s')
            ]
        ];

        return $this->createNotification([$UserID], $title, $message, 'ticket', $options);
    }
}

// Example usage functions for easy integration:

/**
 * Quick function to create a notification
 */
function createNotification($conn, $userIds, $title, $message, $type = 'system', $options = []) {
    $notificationCreator = new NotificationCreator($conn);
    return $notificationCreator->createNotification($userIds, $title, $message, $type, $options);
}

/**
 * Quick function to notify all admins
 */
function notifyAdmins($conn, $title, $message, $type = 'system', $options = []) {
    $notificationCreator = new NotificationCreator($conn);
    return $notificationCreator->notifyAllAdmins($title, $message, $type, $options);
}

/**
 * Quick function for user events
 */
function notifyUserEvent($conn, $event, $targetUserId = null, $metadata = []) {
    $notificationCreator = new NotificationCreator($conn);
    return $notificationCreator->notifyUserEvent(null, $event, $targetUserId, $metadata);
}

/**
 * Quick function for ticket events
 */
function notifyTicketEvent($conn, $event, $ticketId, $metadata = []) {
    $notificationCreator = new NotificationCreator($conn);
    return $notificationCreator->notifyTicketEvent(null, $event, $ticketId, $metadata);
}
?>