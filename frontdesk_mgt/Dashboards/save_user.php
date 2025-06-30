<?php
global $conn;
require_once '../dbConfig.php';
session_start();

$data = $_POST;
$userId = isset($data['id']) ? intval($data['id']) : null;

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
        $conn->query("UPDATE users SET Password = '$hashedPassword' WHERE UserID = $userId");
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
}

$conn->close();