<?php

// Handle AJAX requests for notifications
global $conn;
require_once '../dbConfig.php';
require_once 'notif_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid request'];

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_notifications':
                $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
                $notifications = getUserNotifications($conn, $userId, $unreadOnly);
                $response = [
                    'success' => true,
                    'notifications' => $notifications,
                    'unread_count' => getUnreadNotificationCount($conn, $userId)
                ];
                break;

            case 'get_notification_count':
                $response = [
                    'success' => true,
                    'unread_count' => getUnreadNotificationCount($conn, $userId)
                ];
                break;
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($_POST['action']) || (isset($data['action']))) {
        $action = $_POST['action'] ?? $data['action'];

        switch ($action) {
            case 'mark_as_read':
                $notificationId = $_POST['notification_id'] ?? $data['notification_id'];
                if (markNotificationAsRead($conn, $notificationId)) {
                    $response = [
                        'success' => true,
                        'message' => 'Notification marked as read',
                        'unread_count' => getUnreadNotificationCount($conn, $userId)
                    ];
                } else {
                    $response['message'] = 'Failed to mark notification as read';
                }
                break;

            case 'mark_all_as_read':
                if (markAllNotificationsAsRead($conn, $userId)) {
                    $response = [
                        'success' => true,
                        'message' => 'All notifications marked as read',
                        'unread_count' => 0
                    ];
                } else {
                    $response['message'] = 'Failed to mark notifications as read';
                }
                break;
        }
    }
}

$conn->close();
echo json_encode($response);