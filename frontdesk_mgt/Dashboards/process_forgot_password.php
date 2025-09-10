<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();
global $conn;
require_once '../dbConfig.php';
require_once 'NotificationCreator.php';
session_start();

// Set header to return JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Initialize response array
    $response = ['success' => false, 'message' => ''];

    // 1. Check if the email exists in the database
    $stmt = $conn->prepare("SELECT UserID, Name, Email FROM users WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // 2. Always return a "success" message to the user, even if the email doesn't exist.
    if ($user) {
        // 3. Call the stored procedure to log the request and notify admins
        $callStmt = $conn->prepare("CALL LogPasswordResetRequest(?, ?, ?)");
        $callStmt->bind_param("iss", $user['UserID'], $user['Email'], $user['Name']);
        $callStmt->execute();

        // 4. Create notification for all admins
        $notificationCreator = new NotificationCreator($conn);
        $notificationResult = $notificationCreator->notifyUserEvent(
            $user['UserID'],
            'password_reset_request',
            $user['UserID'],
            ['email' => $user['Email'], 'name' => $user['Name']]
        );

        if (!$notificationResult['success']) {
            error_log("Failed to create notification: " . $notificationResult['error']);
        }

        $response['success'] = true;
        $response['message'] = "If your email is registered, you will receive a reset mail shortly. Please also wait for an admin to assist you.";
    } else {
        // Still show success to prevent email enumeration
        $response['success'] = true;
        $response['message'] = "If your email is registered, you will receive a reset mail shortly. Please also wait for an admin to assist you.";
    }

    // Return JSON response instead of redirecting
    echo json_encode($response);
    exit();
}

// If not a POST request
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
ob_end_flush();
?>