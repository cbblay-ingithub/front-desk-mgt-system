<?php
global $conn;
require_once '../dbConfig.php';
require_once 'password_validator.php'; // Add this line
session_start();

$data = $_POST;
$userId = isset($data['id']) ? intval($data['id']) : null;

// Validate password if provided (for new users or password changes)
if (empty($userId) || !empty($data['password'])) {
    $passwordErrors = validatePassword($data['password'], $conn);

    if (!empty($passwordErrors)) {
        echo json_encode([
            'success' => false,
            'error' => 'Password does not meet policy requirements: ' . implode(', ', $passwordErrors)
        ]);
        exit;
    }
}

if ($userId) {
    // Update existing user
    $query = "UPDATE users SET 
              Name = ?, Email = ?, Phone = ?, Role = ?, status = ?
              WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssi",
        $data['name'], $data['email'], $data['phone'],
        $data['role'], $data['status'], $userId);
    $stmt->execute();

    // Update password if provided
    if (!empty($data['password'])) {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    echo json_encode(['success' => true]);
} else {
    // Create new user (no user ID provided)
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $query = "INSERT INTO users (Name, Email, Phone, Role, Password, status) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss",
        $data['name'], $data['email'], $data['phone'],
        $data['role'], $hashedPassword, $data['status']);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create user']);
    }
}

$conn->close();