<?php
// test_notification.php
global $conn;
require_once '../dbConfig.php';
require_once 'notif_functions.php';
// Set user ID and ticket ID for testing
$userId = 1; // Change to an actual user ID in your system
$ticketId = 1; // Change to an actual ticket ID in your system

// Create a test notification
$result = createNotification($conn, $userId, $ticketId, 'assignment', [
    'message' => 'This is a test notification',
    'timestamp' => date('Y-m-d H:i:s')
]);

echo "Notification creation result: " . ($result ? "Success" : "Failed");
?>