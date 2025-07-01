<?php
global $conn;
require_once '../dbConfig.php';

// Get user ID from POST data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE UserID=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found or already deleted']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
}

$conn->close();
exit;