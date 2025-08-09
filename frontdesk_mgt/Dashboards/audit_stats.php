<?php
global $conn;
require_once '../dbConfig.php';

header('Content-Type: application/json');

try {
    // Build where clause same as audit_backend.php
    $where = [];
    $params = [];
    $types = '';

    if (!empty($_GET['user_id'])) {
        $where[] = 'user_id = ?';
        $params[] = $_GET['user_id'];
        $types .= 'i';
    }

    if (!empty($_GET['user_role'])) {
        $where[] = 'user_role = ?';
        $params[] = $_GET['user_role'];
        $types .= 's';
    }

    if (!empty($_GET['action_type'])) {
        $where[] = 'action_type = ?';
        $params[] = $_GET['action_type'];
        $types .= 's';
    }

    if (!empty($_GET['status'])) {
        $where[] = 'status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $_GET['date_from'];
        $types .= 's';
    }

    if (!empty($_GET['date_to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $_GET['date_to'];
        $types .= 's';
    }


    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stats = [];

    // Total logs
    $total = $conn->query("SELECT COUNT(*) FROM audit_logs $whereClause")->fetch_row()[0];

    // Success/failure counts
    $result = $conn->query("
        SELECT 
            SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status = 'FAILURE' THEN 1 ELSE 0 END) as failure
        FROM audit_logs $whereClause
    ");
    $counts = $result->fetch_assoc();

    // Today's activity
    $today = $conn->query("
        SELECT COUNT(*) 
        FROM audit_logs 
        WHERE DATE(created_at) = CURDATE()
    ")->fetch_row()[0];

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $total,
            'success' => $counts['success'],
            'failure' => $counts['failure'],
            'today' => $today
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving stats: ' . $e->getMessage()
    ]);
}