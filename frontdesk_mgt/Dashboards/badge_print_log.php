<?php
session_start();
require_once '../dbConfig.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    global $conn;

    if ($_POST['action'] === 'log_print') {
        try {
            $visitorId = $_POST['visitor_id'];
            $badgeNumber = $_POST['badge_number'];
            $copies = $_POST['copies'] ?? 1;
            $printedBy = $_SESSION['userID'] ?? null;

            if (!$printedBy) {
                echo json_encode(['success' => false, 'message' => 'User not authenticated']);
                exit;
            }

            // Insert into badge_print_logs table (you may need to create this table)
            $sql = "INSERT INTO badge_print_logs (VisitorID, BadgeNumber, Copies, PrintedBy, PrintTime) 
                    VALUES (?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isii", $visitorId, $badgeNumber, $copies, $printedBy);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Badge print logged successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to log badge print']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>