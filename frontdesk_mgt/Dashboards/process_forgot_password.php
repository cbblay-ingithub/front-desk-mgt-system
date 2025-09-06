<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any errors that might be thrown before headers
ob_start();
global $conn;
require_once '../dbConfig.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // 1. Check if the email exists in the database
    $stmt = $conn->prepare("SELECT UserID, Name, Email FROM users WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // 2. Always return a "success" message to the user, even if the email doesn't exist.
    // This prevents user enumeration (figuring out which emails are registered).
    if ($user) {
        // 3. Call the stored procedure to log the request and notify admins
        $callStmt = $conn->prepare("CALL LogPasswordResetRequest(?, ?, ?)");
        $callStmt->bind_param("iss", $user['UserID'], $user['Email'], $user['Name']);
        $callStmt->execute();
    }

    // 4. Show a generic success message to the user
    $_SESSION['message'] = "If your email is registered, you will receive a reset mail shortly. Please also wait for an admin to assist you.";
    header('Location: ../Auth.html'); // Redirect back to login
    exit();
}

ob_end_flush();
?>