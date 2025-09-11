<?php
global $conn;
session_start();
require_once '../dbConfig.php';

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Set content type for AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'check_in':
                handleCheckIn();
                break;
            case 'quick_check_in':
                handleQuickCheckIn();
                break;
            case 'check_out':
                handleCheckOut();
                break;
            case 'bulk_check_in':
                handleBulkCheckIn();
                break;
            case 'bulk_check_out':
                handleBulkCheckOut();
                break;
            default:
                throw new Exception('Invalid action specified');
        }
    } catch (Exception $e) {
        error_log("Process Visit Error: " . $e->getMessage());
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } else {
            header('Location: visitor-mgt.php?msg=error&error=' . urlencode($e->getMessage()));
        }
        exit;
    }
} else {
    // Invalid request method
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    } else {
        header("Location: visitor-mgt.php");
    }
    exit;
}

function handleCheckIn() {
    global $conn, $isAjax;

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $visitPurpose = trim($_POST['visit_purpose'] ?? 'General Visit');
    $hostID = $_SESSION['user_id'];

    // Basic validation
    if (empty($name) || empty($email)) {
        throw new Exception('Name and email are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $conn->begin_transaction();

    try {
        // Check if visitor already exists by email
        $stmt = $conn->prepare("SELECT VisitorID FROM visitors WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Visitor exists, get their ID and update details if needed
            $visitorID = $result->fetch_assoc()['VisitorID'];

            // Update visitor details
            $updateStmt = $conn->prepare("UPDATE visitors SET Name = ?, Phone = ?, IDType = ? WHERE VisitorID = ?");
            $updateStmt->bind_param("sssi", $name, $phone, $idType, $visitorID);
            $updateStmt->execute();

            // Check if visitor is already checked in
            $checkStmt = $conn->prepare("SELECT LogID FROM visitor_logs 
                   WHERE VisitorID = ? 
                   AND CheckOutTime IS NULL 
                   AND Status = 'Checked In'
                   ORDER BY CheckInTime DESC 
                   LIMIT 1");
            $checkStmt->bind_param("i", $visitorID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                throw new Exception("Visitor is already checked in. Please check them out first.");
            }
        } else {
            // Insert new visitor
            $insertStmt = $conn->prepare("INSERT INTO visitors (Name, Email, Phone, IDType) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $name, $email, $phone, $idType);
            $insertStmt->execute();
            $visitorID = $insertStmt->insert_id;
        }

        // Insert into visitor_logs
        $logStmt = $conn->prepare("INSERT INTO visitor_logs (CheckInTime, HostID, VisitorID, Visit_Purpose, Status) VALUES (NOW(), ?, ?, ?, 'Checked In')");
        $logStmt->bind_param("iis", $hostID, $visitorID, $visitPurpose);
        $logStmt->execute();

        $conn->commit();

        $message = "Visitor {$name} checked in successfully";

        if ($isAjax) {
            echo json_encode([
                'success' => true,
                'message' => $message,
                'visitor_id' => $visitorID,
            ]);
        } else {
            header('Location: visitor-mgt.php?msg=checked-in');
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleQuickCheckIn() {
    global $conn, $isAjax;

    $visitorID = intval($_POST['visitor_id']);
    $hostID = $_SESSION['user_id'];

    if ($visitorID <= 0) {
        throw new Exception('Invalid visitor ID');
    }

    $conn->begin_transaction();

    try {
        // Verify visitor exists
        $visitorStmt = $conn->prepare("SELECT Name FROM visitors WHERE VisitorID = ?");
        $visitorStmt->bind_param("i", $visitorID);
        $visitorStmt->execute();
        $visitorResult = $visitorStmt->get_result();

        if ($visitorResult->num_rows === 0) {
            throw new Exception('Visitor not found');
        }

        $visitorName = $visitorResult->fetch_assoc()['Name'];

        // Check if visitor is already checked in
        $checkStmt = $conn->prepare("SELECT LogID FROM visitor_logs 
               WHERE VisitorID = ? 
               AND CheckOutTime IS NULL 
               AND Status = 'Checked In'
               ORDER BY CheckInTime DESC 
               LIMIT 1");
        $checkStmt->bind_param("i", $visitorID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            throw new Exception("{$visitorName} is already checked in");
        }

        // Insert check-in record
        $logStmt = $conn->prepare("INSERT INTO visitor_logs (CheckInTime, HostID, VisitorID, Visit_Purpose, Status) VALUES (NOW(), ?, ?, 'Quick Check-in', 'Checked In')");
        $logStmt->bind_param("ii", $hostID, $visitorID);
        $logStmt->execute();

        $conn->commit();

        if ($isAjax) {
            echo json_encode([
                'success' => true,
                'message' => "{$visitorName} checked in successfully",
                'visitor_id' => $visitorID,
            ]);
        } else {
            header('Location: visitor-mgt.php?msg=checked-in');
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleCheckOut() {
    global $conn, $isAjax;

    $visitorID = intval($_POST['visitor_id']);

    if ($visitorID <= 0) {
        throw new Exception('Invalid visitor ID');
    }

    // Get visitor name for response message
    $nameStmt = $conn->prepare("SELECT Name FROM visitors WHERE VisitorID = ?");
    $nameStmt->bind_param("i", $visitorID);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $visitorName = $nameResult->num_rows > 0 ? $nameResult->fetch_assoc()['Name'] : 'Visitor';

    // Update the most recent active check-in
    $logUpdate = $conn->prepare("UPDATE visitor_logs 
                        SET CheckOutTime = NOW(), 
                            Status = 'Checked Out'
                        WHERE VisitorID = ? 
                        AND CheckOutTime IS NULL 
                        AND Status = 'Checked In'
                        ORDER BY CheckInTime DESC 
                        LIMIT 1");
    $logUpdate->bind_param("i", $visitorID);
    $logUpdate->execute();

    if ($logUpdate->affected_rows === 0) {
        throw new Exception("No active check-in found for {$visitorName}");
    }

    if ($isAjax) {
        echo json_encode([
            'success' => true,
            'message' => "{$visitorName} checked out successfully"
        ]);
    } else {
        header('Location: visitor-mgt.php?msg=checked-out');
    }
}

function handleBulkCheckIn() {
    global $conn, $isAjax;

    $visitorIDs = $_POST['visitor_ids'] ?? [];
    $hostID = $_SESSION['user_id'];

    if (!is_array($visitorIDs) || empty($visitorIDs)) {
        throw new Exception('No visitors selected');
    }

    $conn->begin_transaction();
    $updatedVisitors = [];
    $errors = [];

    try {
        foreach ($visitorIDs as $visitorID) {
            $visitorID = intval($visitorID);

            // Skip invalid IDs
            if ($visitorID <= 0) continue;

            // Get visitor name
            $nameStmt = $conn->prepare("SELECT Name FROM visitors WHERE VisitorID = ?");
            $nameStmt->bind_param("i", $visitorID);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();

            if ($nameResult->num_rows === 0) {
                $errors[] = "Visitor ID {$visitorID} not found";
                continue;
            }

            $visitorName = $nameResult->fetch_assoc()['Name'];

            // Check if already checked in
            $checkStmt = $conn->prepare("SELECT LogID FROM visitor_logs 
                   WHERE VisitorID = ? 
                   AND CheckOutTime IS NULL 
                   AND Status = 'Checked In'
                   ORDER BY CheckInTime DESC 
                   LIMIT 1");
            $checkStmt->bind_param("i", $visitorID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errors[] = "{$visitorName} is already checked in";
                continue;
            }

            // Insert check-in record - FIXED: Removed the extra parameter
            $logStmt = $conn->prepare("INSERT INTO visitor_logs (CheckInTime, HostID, VisitorID, Visit_Purpose, Status) VALUES (NOW(), ?, ?, 'Bulk Check-in', 'Checked In')");
            $logStmt->bind_param("ii", $hostID, $visitorID);

            if ($logStmt->execute()) {
                $updatedVisitors[] = $visitorID;
            } else {
                $errors[] = "Failed to check in {$visitorName}";
            }
        }

        $conn->commit();

        $successCount = count($updatedVisitors);
        $errorCount = count($errors);

        if ($successCount > 0) {
            $message = "Successfully checked in {$successCount} visitors";
            if ($errorCount > 0) {
                $message .= ". {$errorCount} errors occurred.";
            }

            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'updated_visitors' => $updatedVisitors,
                    'errors' => $errors
                ]);
            } else {
                header('Location: visitor-mgt.php?msg=bulk-checked-in');
            }
        } else {
            throw new Exception('No visitors were checked in. ' . implode(', ', $errors));
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleBulkCheckOut() {
    global $conn, $isAjax;

    $visitorIDs = $_POST['visitor_ids'] ?? [];

    if (!is_array($visitorIDs) || empty($visitorIDs)) {
        throw new Exception('No visitors selected');
    }

    $conn->begin_transaction();
    $updatedVisitors = [];
    $errors = [];

    try {
        foreach ($visitorIDs as $visitorID) {
            $visitorID = intval($visitorID);

            // Skip invalid IDs
            if ($visitorID <= 0) continue;

            // Get visitor name
            $nameStmt = $conn->prepare("SELECT Name FROM visitors WHERE VisitorID = ?");
            $nameStmt->bind_param("i", $visitorID);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();

            if ($nameResult->num_rows === 0) {
                $errors[] = "Visitor ID {$visitorID} not found";
                continue;
            }

            $visitorName = $nameResult->fetch_assoc()['Name'];

            // Update the most recent active check-in
            $logUpdate = $conn->prepare("UPDATE visitor_logs 
                                SET CheckOutTime = NOW(), 
                                    Status = 'Checked Out'
                                WHERE VisitorID = ? 
                                AND CheckOutTime IS NULL 
                                AND Status = 'Checked In'
                                ORDER BY CheckInTime DESC 
                                LIMIT 1");
            $logUpdate->bind_param("i", $visitorID);
            $logUpdate->execute();

            if ($logUpdate->affected_rows > 0) {
                $updatedVisitors[] = $visitorID;
            } else {
                $errors[] = "No active check-in found for {$visitorName}";
            }
        }

        $conn->commit();

        $successCount = count($updatedVisitors);
        $errorCount = count($errors);

        if ($successCount > 0) {
            $message = "Successfully checked out {$successCount} visitors";
            if ($errorCount > 0) {
                $message .= ". {$errorCount} errors occurred.";
            }

            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'updated_visitors' => $updatedVisitors,
                    'errors' => $errors
                ]);
            } else {
                header('Location: visitor-mgt.php?msg=bulk-checked-out');
            }
        } else {
            throw new Exception('No visitors were checked out. ' . implode(', ', $errors));
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Utility function to validate visitor data
function validateVisitorData($name, $email, $phone = '') {
    $errors = [];

    if (empty(trim($name))) {
        $errors[] = 'Name is required';
    } elseif (strlen(trim($name)) < 2) {
        $errors[] = 'Name must be at least 2 characters long';
    }

    if (empty(trim($email))) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)\.]+$/', $phone)) {
        $errors[] = 'Invalid phone number format';
    }

    return $errors;
}

// Enhanced logging function
function logActivity($conn, $action, $visitorID, $hostID, $details = '') {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (action, visitor_id, host_id, details, timestamp) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("siis", $action, $visitorID, $hostID, $details);
        $stmt->execute();
    } catch (Exception $e) {
        // Log the error but don't throw it to avoid breaking the main flow
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Function to get visitor statistics
function getVisitorStats($conn) {
    $stats = [];

    // Today's check-ins
    $todaySQL = "SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(CheckInTime) = CURDATE()";
    $result = $conn->query($todaySQL);
    $stats['today_checkins'] = $result->fetch_assoc()['count'];

    // Currently checked in
    $currentSQL = "SELECT COUNT(*) as count FROM visitor_logs WHERE CheckOutTime IS NULL AND Status = 'Checked In'";
    $result = $conn->query($currentSQL);
    $stats['currently_checked_in'] = $result->fetch_assoc()['count'];

    // This week's total
    $weekSQL = "SELECT COUNT(*) as count FROM visitor_logs WHERE YEARWEEK(CheckInTime) = YEARWEEK(CURDATE())";
    $result = $conn->query($weekSQL);
    $stats['week_total'] = $result->fetch_assoc()['count'];

    // Average visit duration (in minutes)
    $durationSQL = "SELECT AVG(TIMESTAMPDIFF(MINUTE, CheckInTime, CheckOutTime)) as avg_duration 
                    FROM visitor_logs 
                    WHERE CheckOutTime IS NOT NULL 
                    AND DATE(CheckInTime) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $result = $conn->query($durationSQL);
    $avgDuration = $result->fetch_assoc()['avg_duration'];
    $stats['avg_duration_minutes'] = $avgDuration ? round($avgDuration) : 0;

    return $stats;
}

// Function to clean up old visitor logs (for maintenance)
function cleanupOldLogs($conn, $daysToKeep = 90) {
    try {
        $stmt = $conn->prepare("DELETE FROM visitor_logs WHERE CheckInTime < DATE_SUB(NOW(), INTERVAL ? DAY) AND CheckOutTime IS NOT NULL");
        $stmt->bind_param("i", $daysToKeep);
        $stmt->execute();
        return $stmt->affected_rows;
    } catch (Exception $e) {
        error_log("Log cleanup failed: " . $e->getMessage());
        return 0;
    }
}

// Export visitor data to CSV (for reporting)
function exportVisitorData($conn, $startDate, $endDate) {
    $sql = "SELECT 
                v.Name,
                v.Email,
                v.Phone,
                vl.CheckInTime,
                vl.CheckOutTime,
                vl.Visit_Purpose,
                vl.Status,
                TIMESTAMPDIFF(MINUTE, vl.CheckInTime, COALESCE(vl.CheckOutTime, NOW())) as DurationMinutes
            FROM visitor_logs vl
            JOIN visitors v ON vl.VisitorID = v.VisitorID
            WHERE DATE(vl.CheckInTime) BETWEEN ? AND ?
            ORDER BY vl.CheckInTime DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    // Generate CSV content
    $csv = "Name,Email,Phone,Check In Time,Check Out Time,Purpose,Status,Duration (Minutes)\n";

    while ($row = $result->fetch_assoc()) {
        $csv .= sprintf(
            '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
            str_replace('"', '""', $row['Name']),
            str_replace('"', '""', $row['Email']),
            str_replace('"', '""', $row['Phone'] ?: ''),
            $row['CheckInTime'],
            $row['CheckOutTime'] ?: 'Still checked in',
            str_replace('"', '""', $row['Visit_Purpose'] ?: ''),
            $row['Status'],
            $row['DurationMinutes']
        );
    }

    return $csv;
}

?>