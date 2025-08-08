<?php
require_once '../dbConfig.php';

// audit_logs_stats.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Build WHERE clause based on filters (same as main API)
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

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Total logs count
    $totalQuery = "SELECT COUNT(*) as total FROM audit_logs $whereClause";
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($params);
    $total = $totalStmt->fetch()['total'];

    // Success logs count
    $successParams = $params;
    $successWhereConditions = $whereConditions;
    $successWhereConditions[] = "status = 'SUCCESS'";
    $successParams[':success_status'] = 'SUCCESS';
    $successWhereClause = 'WHERE ' . implode(' AND ', $successWhereConditions);

    $successQuery = "SELECT COUNT(*) as success FROM audit_logs $successWhereClause";
    $successStmt = $pdo->prepare($successQuery);
    $successStmt->execute($successParams);
    $success = $successStmt->fetch()['success'];

    // Failure logs count
    $failureParams = $params;
    $failureWhereConditions = $whereConditions;
    $failureWhereConditions[] = "status = 'FAILURE'";
    $failureParams[':failure_status'] = 'FAILURE';
    $failureWhereClause = 'WHERE ' . implode(' AND ', $failureWhereConditions);

    $failureQuery = "SELECT COUNT(*) as failure FROM audit_logs $failureWhereClause";
    $failureStmt = $pdo->prepare($failureQuery);
    $failureStmt->execute($failureParams);
    $failure = $failureStmt->fetch()['failure'];

    // Today's logs count
    $todayParams = $params;
    $todayWhereConditions = $whereConditions;
    $todayWhereConditions[] = "DATE(created_at) = CURDATE()";
    $todayWhereClause = !empty($todayWhereConditions) ? 'WHERE ' . implode(' AND ', $todayWhereConditions) : 'WHERE DATE(created_at) = CURDATE()';

    $todayQuery = "SELECT COUNT(*) as today FROM audit_logs $todayWhereClause";
    $todayStmt = $pdo->prepare($todayQuery);
    $todayStmt->execute($todayParams);
    $today = $todayStmt->fetch()['today'];

    // Get recent activity breakdown
    $activityQuery = "
        SELECT 
            action_type,
            COUNT(*) as count
        FROM audit_logs 
        $whereClause 
        GROUP BY action_type 
        ORDER BY count DESC 
        LIMIT 10
    ";
    $activityStmt = $pdo->prepare($activityQuery);
    $activityStmt->execute($params);
    $activityBreakdown = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get hourly activity for today
    $hourlyQuery = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM audit_logs 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ";
    $hourlyStmt = $pdo->prepare($hourlyQuery);
    $hourlyStmt->execute();
    $hourlyActivity = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top users by activity
    $topUsersQuery = "
        SELECT 
            user_id,
            user_role,
            COUNT(*) as activity_count
        FROM audit_logs 
        $whereClause 
        GROUP BY user_id, user_role 
        ORDER BY activity_count DESC 
        LIMIT 5
    ";
    $topUsersStmt = $pdo->prepare($topUsersQuery);
    $topUsersStmt->execute($params);
    $topUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'stats' => [
            'total' => (int)$total,
            'success' => (int)$success,
            'failure' => (int)$failure,
            'today' => (int)$today,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0
        ],
        'activity_breakdown' => $activityBreakdown,
        'hourly_activity' => $hourlyActivity,
        'top_users' => $topUsers
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