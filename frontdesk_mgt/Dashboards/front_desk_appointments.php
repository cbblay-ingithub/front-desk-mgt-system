<?php
// Prevent PHP errors from being displayed in the output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Start buffering output to catch any unwanted output before JSON
ob_start();

global $conn;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../dbConfig.php';

// Function to update appointment statuses automatically
function updateAppointmentStatuses() {
    global $conn;

    $sql = "UPDATE appointments 
            SET Status = 'Overdue' 
            WHERE Status = 'Upcoming' 
            AND AppointmentTime < NOW()";
    $conn->query($sql);

    $sql = "UPDATE appointments 
            SET Status = 'Cancelled', CancellationReason = 'No-Show' 
            WHERE Status = 'Overdue' 
            AND AppointmentTime < NOW() - INTERVAL 30 MINUTE";
    $conn->query($sql);
}

// Function to get all appointments
function getAllAppointments() {
    global $conn;

    $sql = "SELECT a.AppointmentID, a.AppointmentTime, a.Status, a.CancellationReason, 
                   v.VisitorID, v.Name AS VisitorName, v.Email AS VisitorEmail, v.Phone AS VisitorPhone, 
                   u.UserID AS HostID, u.Name AS HostName 
            FROM appointments a
            JOIN visitors v ON a.VisitorID = v.VisitorID
            JOIN users u ON a.HostID = u.UserID
            ORDER BY a.AppointmentTime DESC";

    $result = $conn->query($sql);
    $appointments = [];

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    }

    return $appointments;
}

// Function to get appointment statistics
function getAppointmentStats() {
    global $conn;

    $stats = [
        'total' => 0,
        'upcoming' => 0,
        'overdue' => 0,
        'ongoing' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];

    $sql = "SELECT Status, COUNT(*) as count FROM appointments GROUP BY Status";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status = $row['Status'];
            $count = $row['count'];

            $stats['total'] += $count;

            if ($status == 'Upcoming') $stats['upcoming'] = $count;
            elseif ($status == 'Overdue') $stats['overdue'] = $count;
            elseif ($status == 'Ongoing') $stats['ongoing'] = $count;
            elseif ($status == 'Completed') $stats['completed'] = $count;
            elseif ($status == 'Cancelled') $stats['cancelled'] = $count;
        }
    }

    return $stats;
}

// Function to get all hosts
function getAllHosts() {
    global $conn;

    $sql = "SELECT UserID, Name FROM users WHERE Role= 'Host' ORDER BY Name";
    $result = $conn->query($sql);
    $hosts = [];

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $hosts[] = $row;
        }
    }

    return $hosts;
}

// Function to check if the appointment time is within allowed hours
function isValidAppointmentTime($appointmentTime) {
    $dt = new DateTime($appointmentTime);
    $time = $dt->format('H:i:s');
    return (($time >= '09:30:00' && $time <= '11:30:00') || ($time >= '13:00:00' && $time <= '16:30:00'));
}

// Function to check if the host has a conflicting appointment
function checkSchedulingConflict($hostId, $appointmentTime) {
    global $conn;

    $startTime = date('Y-m-d H:i:s', strtotime($appointmentTime) - (45 * 60));
    $endTime = date('Y-m-d H:i:s', strtotime($appointmentTime) + (45 * 60));

    $sql = "SELECT AppointmentID FROM appointments 
            WHERE HostID = ? 
            AND AppointmentTime BETWEEN ? AND ? 
            AND Status IN ('Upcoming', 'Overdue', 'Ongoing')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $hostId, $startTime, $endTime);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

// AJAX handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit;
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'getVisitors':
            $sql = "SELECT VisitorID, Name, Email FROM visitors ORDER BY Name";
            $result = $conn->query($sql);
            $visitors = [];

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $visitors[] = $row;
                }
            }

            echo json_encode($visitors);
            break;

        case 'getAppointmentDetails':
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $sql = "SELECT a.AppointmentID, a.AppointmentTime, a.Status, a.CancellationReason, 
                           v.VisitorID, v.Name AS VisitorName, v.Email AS VisitorEmail, v.Phone AS VisitorPhone, 
                           u.UserID, u.Name AS HostName 
                    FROM appointments a
                    JOIN visitors v ON a.VisitorID = v.VisitorID
                    JOIN users u ON a.HostID = u.UserID 
                    WHERE a.AppointmentID = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo json_encode($result->fetch_assoc());
            } else {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            }
            break;

        case 'schedule':
            if (!isset($_POST['hostId']) || !isset($_POST['appointmentTime'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            $hostId = $_POST['hostId'];
            $appointmentTime = $_POST['appointmentTime'];
            $isNewVisitor = $_POST['isNewVisitor'] ?? '0';

            // Check if the appointment is on a Sunday
            if (date('N', strtotime($appointmentTime)) == 7) {
                echo json_encode(['success' => false, 'message' => 'Appointments cannot be scheduled on Sundays.']);
                break;
            }

            if (!isValidAppointmentTime($appointmentTime)) {
                echo json_encode(['success' => false, 'message' => 'Appointment time is outside allowed hours (9:30 AM–11:30 AM or 1:00 PM–4:30 PM).']);
                break;
            }

            if (checkSchedulingConflict($hostId, $appointmentTime)) {
                echo json_encode(['success' => false, 'message' => 'The selected host already has an appointment scheduled within 45 minutes of this time.']);
                break;
            }

            $conn->begin_transaction();

            try {
                $visitorId = null;

                if ($isNewVisitor == '1') {
                    $newVisitorName = $_POST['newVisitorName'];
                    $newVisitorEmail = $_POST['newVisitorEmail'];
                    $newVisitorPhone = $_POST['newVisitorPhone'] ?? '';

                    $sql = "INSERT INTO visitors (Name, Email, Phone) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $newVisitorName, $newVisitorEmail, $newVisitorPhone);
                    $stmt->execute();

                    $visitorId = $conn->insert_id;
                } else {
                    $visitorId = $_POST['visitorId'];
                }

                $status = 'Upcoming';
                $sql = "INSERT INTO appointments (VisitorID, HostID, AppointmentTime, Status) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $visitorId, $hostId, $appointmentTime, $status);
                $stmt->execute();

                $conn->commit();

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'reschedule':
            if (!isset($_POST['appointmentId']) || !isset($_POST['newTime'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $newTime = $_POST['newTime'];

            // Check if the new time is on a Sunday
            if (date('N', strtotime($newTime)) == 7) {
                echo json_encode(['success' => false, 'message' => 'Appointments cannot be rescheduled to Sundays.']);
                break;
            }

            if (!isValidAppointmentTime($newTime)) {
                echo json_encode(['success' => false, 'message' => 'New appointment time is outside allowed hours (9:30 AM–11:30 AM or 1:00 PM–4:30 PM).']);
                break;
            }

            $sql = "SELECT HostID FROM appointments WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $hostId = $result->fetch_assoc()['HostID'];

                $startTime = date('Y-m-d H:i:s', strtotime($newTime) - (45 * 60));
                $endTime = date('Y-m-d H:i:s', strtotime($newTime) + (45 * 60));

                $sql = "SELECT AppointmentID FROM appointments 
                        WHERE HostID = ? 
                        AND AppointmentTime BETWEEN ? AND ? 
                        AND Status IN ('Upcoming', 'Overdue', 'Ongoing')
                        AND AppointmentID != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issi", $hostId, $startTime, $endTime, $appointmentId);
                $stmt->execute();
                $conflictResult = $stmt->get_result();

                if ($conflictResult->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'The selected host already has an appointment scheduled within 45 minutes of this time.']);
                    break;
                }

                $sql = "UPDATE appointments SET AppointmentTime = ?, Status = 'Upcoming' WHERE AppointmentID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $newTime, $appointmentId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reschedule appointment']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            }
            break;

        case 'checkIn':
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $status = 'Ongoing';
            $checkInTime = date('Y-m-d H:i:s');

            $sql = "UPDATE appointments SET Status = ?, CheckInTime = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $checkInTime, $appointmentId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Visitor checked in successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check in visitor: ' . $conn->error]);
            }
            break;

        case 'checkInWithDetails':
            if (!isset($_POST['appointmentId']) || !isset($_POST['visitorId']) ||
                !isset($_POST['idType']) || !isset($_POST['idNumber']) || !isset($_POST['visitPurpose'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $visitorId = $_POST['visitorId'];
            $idType = $_POST['idType'];
            $idNumber = $_POST['idNumber'];
            $visitPurpose = $_POST['visitPurpose'];

            $sql = "SELECT HostID FROM appointments WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                break;
            }
            $hostId = $result->fetch_assoc()['HostID'];

            $conn->begin_transaction();
            try {
                $sql = "UPDATE visitors SET IDType = ?, IDNumber = ? WHERE VisitorID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $idType, $idNumber, $visitorId);
                $stmt->execute();

                $checkInTime = date('Y-m-d H:i:s');
                $sql = "INSERT INTO visitor_Logs (CheckInTime, HostID, VisitorID, Visit_Purpose) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siis", $checkInTime, $hostId, $visitorId, $visitPurpose);
                $stmt->execute();

                $sql = "UPDATE appointments SET Status = 'Ongoing', CheckInTime = ? WHERE AppointmentID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $checkInTime, $appointmentId);
                $stmt->execute();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Visitor checked in successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()]);
            }
            break;

        case 'completeSession':
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $status = 'Completed';
            $sessionEndTime = date('Y-m-d H:i:s');

            $sql = "UPDATE appointments SET Status = ?, SessionEndTime = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $sessionEndTime, $appointmentId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to complete appointment']);
            }
            break;

        case 'checkConflict':
            if (!isset($_POST['hostId']) || !isset($_POST['appointmentTime'])) {
                echo json_encode(['success' => false, 'message' => 'Missing hostId or appointmentTime']);
                break;
            }
            $hostId = $_POST['hostId'];
            $appointmentTime = $_POST['appointmentTime'];
            $excludeAppointmentId = $_POST['appointmentId'] ?? null;

            $startTime = date('Y-m-d H:i:s', strtotime($appointmentTime) - (45 * 60));
            $endTime = date('Y-m-d H:i:s', strtotime($appointmentTime) + (45 * 60));

            $sql = "SELECT AppointmentID FROM appointments 
                    WHERE HostID = ? 
                    AND AppointmentTime BETWEEN ? AND ? 
                    AND Status IN ('Upcoming', 'Overdue', 'Ongoing')";
            if ($excludeAppointmentId) {
                $sql .= " AND AppointmentID != ?";
            }
            $stmt = $conn->prepare($sql);
            if ($excludeAppointmentId) {
                $stmt->bind_param("issi", $hostId, $startTime, $endTime, $excludeAppointmentId);
            } else {
                $stmt->bind_param("iss", $hostId, $startTime, $endTime);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Conflict: Host has another appointment within 45 minutes.']);
            } else {
                echo json_encode(['success' => true]);
            }
            break;

        case 'cancelAppointment':
            if (!isset($_POST['appointmentId']) || !isset($_POST['reason'])) {
                echo json_encode(['success' => false, 'message' => 'Missing appointment ID or reason']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $reason = $_POST['reason'];
            $status = 'Cancelled';

            $sql = "UPDATE appointments SET Status = ?, CancellationReason = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $reason, $appointmentId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

    exit;
}
?>