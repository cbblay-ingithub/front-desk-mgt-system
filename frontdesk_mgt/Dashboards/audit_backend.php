<?php
require_once '../dbConfig.php';

// audit_logs_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
// Get request parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, min(100, intval($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

// Build WHERE clause based on filters
$whereConditions = [];
$params = [];

if (!empty($_GET['user_id'])) {
    $whereConditions[] = "user_id = :user_id";
    $params[':user_id'] = intval($_GET['user_id']);
}

if (!empty($_GET['user_role'])) {
    $whereConditions[] = "user_role = :user_role";
    $params[':user_role'] = $_GET['user_role'];
}

if (!empty($_GET['action_type'])) {
    $whereConditions[] = "action_type = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

if (!empty($_GET['action_category'])) {
    $whereConditions[] = "action_category = :action_category";
    $params[':action_category'] = $_GET['action_category'];
}

if (!empty($_GET['status'])) {
    $whereConditions[] = "status = :status";
    $params[':status'] = $_GET['status'];
}

if (!empty($_GET['table_affected'])) {
    $whereConditions[] = "table_affected = :table_affected";
    $params[':table_affected'] = $_GET['table_affected'];
}

if (!empty($_GET['date_from'])) {
    $whereConditions[] = "created_at >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $whereConditions[] = "created_at <= :date_to";
    $params[':date_to'] = $_GET['date_to'];
}

if (!empty($_GET['search'])) {
    $whereConditions[] = "(description LIKE :search OR action_type LIKE :search OR ip_address LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM audit_logs $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Get logs with pagination
    $query = "
        SELECT 
            log_id,
            user_id,
            user_role,
            action_type,
            action_category,
            table_affected,
            record_id,
            old_value,
            new_value,
            ip_address,
            user_agent,
            session_id,
            status,
            description,
            location_id,
            created_at
        FROM audit_logs 
        $whereClause 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate pagination info
    $totalPages = ceil($total / $limit);
    $start = $total > 0 ? $offset + 1 : 0;
    $end = min($offset + $limit, $total);

    $response = [
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => (int)$total,
            'per_page' => $limit,
            'start' => $start,
            'end' => $end
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>