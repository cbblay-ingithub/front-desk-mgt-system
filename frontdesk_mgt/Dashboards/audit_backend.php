<?php
global $conn;
require_once '../dbConfig.php';
require_once '../audit_logger.php';

header('Content-Type: application/json');

try {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 25;
    $offset = ($page - 1) * $limit;

    // Build query based on filters
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

    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM audit_logs $whereClause");
    if ($where) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Get paginated results
    $query = "SELECT * FROM audit_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);

    if ($where) {
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'total' => $total,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'start' => $offset + 1,
            'end' => min($offset + $limit, $total)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving logs: ' . $e->getMessage()
    ]);
}