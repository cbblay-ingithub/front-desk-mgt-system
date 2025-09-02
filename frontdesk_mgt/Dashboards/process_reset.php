<?php
require_once '../dbConfig.php';
global $conn;
session_start();

// Check admin permissions
if (!isset($_SESSION['userID']) || $_SESSION['Role'] !== 'admin') {
    header('404-page.html');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Process password reset requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id']);
    $action = $_POST['reset_action'];

    // Get user details
    $stmt = $conn->prepare("SELECT Email, Name FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    if ($action === 'send_link') {
        // Generate reset token and send email
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE users SET password_reset_token = ?, token_expiration = ? WHERE UserID = ?");
        $stmt->bind_param("ssi", $token, $expiration, $userId);

        if ($stmt->execute()) {
            // Send reset email
            $resetLink = "https://yourdomain.com/reset_password.php?token=$token";
            $emailSent = sendPasswordResetEmail($user['Email'], $user['Name'], $resetLink);

            if ($emailSent) {
                echo json_encode(['success' => true, 'message' => 'Reset link sent successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send reset email']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Error generating reset token']);
        }
    } elseif ($action === 'force_reset') {
        // Get password length from admin settings or default to 8
        $passwordLength = isset($_POST['password_length']) ? intval($_POST['password_length']) : 8;

        // Validate length (minimum 6, maximum 20 characters)
        $passwordLength = max(6, min(20, $passwordLength));

        // Generate a temporary password with the specified length
        $tempPassword = generateTemporaryPassword($passwordLength);
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET Password = ?, password_reset_token = NULL, token_expiration = NULL WHERE UserID = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            // Return the temporary password
            echo json_encode(['success' => true, 'temp_password' => $tempPassword, 'message' => 'Password reset successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error resetting password']);
        }
    }
}

$conn->close();

function generateTemporaryPassword($length = 8): string
{
    $chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
    $password = '';
    $charCount = strlen($chars);

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charCount - 1)];
    }

    return $password;
}
?>