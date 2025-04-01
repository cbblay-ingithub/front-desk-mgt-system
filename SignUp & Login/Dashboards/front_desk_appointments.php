<?php
global $conn;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../dbConfig.php';

// Function to get all appointments
function getAllAppointments() {
    global $conn;

    $sql = "SELECT a.AppointmentID, a.AppointmentTime, a.Status, 
                   v.VisitorID, v.Name AS VisitorName, v.Email AS VisitorEmail, v.Phone AS VisitorPhone, 
                   u.UserID AS HostID ,u.Name AS HostName 
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

            if ($status == 'Upcoming') {
                $stats['upcoming'] = $count;
            } elseif ($status == 'Ongoing') {
                $stats['ongoing'] = $count;
            } elseif ($status == 'Completed') {
                $stats['completed'] = $count;
            } elseif ($status == 'Cancelled') {
                $stats['cancelled'] = $count;
            }
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
            // Get all visitors
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
            // Get details for a specific appointment
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $sql = "SELECT a.AppointmentID, a.AppointmentTime, a.Status, 
                           v.VisitorID, v.Name AS VisitorName, v.Email AS VisitorEmail, v.Phone AS VisitorPhone, 
                           u.userID, u.Name AS HostName 
                    FROM appointments a
                    JOIN visitors v ON a.VisitorID = v.VisitorID
                    JOIN users u ON a.HostID = u.userID 
                    WHERE a.AppointmentID = ?" ;

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
            // Schedule a new appointment
            if (!isset($_POST['hostId']) || !isset($_POST['appointmentTime'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            $hostId = $_POST['hostId'];
            $appointmentTime = $_POST['appointmentTime'];
            $notes = $_POST['appointmentNotes'] ?? '';
            $isNewVisitor = $_POST['isNewVisitor'] ?? '0';

            // Begin transaction
            $conn->begin_transaction();

            try {
                $visitorId = null;

                if ($isNewVisitor == '1') {
                    // Create new visitor
                    $newVisitorName = $_POST['newVisitorName'];
                    $newVisitorEmail = $_POST['newVisitorEmail'];
                    $newVisitorPhone = $_POST['newVisitorPhone'] ?? '';

                    $sql = "INSERT INTO visitors (Name, Email, Phone) VALUES (?, ?,?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $newVisitorName, $newVisitorEmail, $newVisitorPhone);
                    $stmt->execute();

                    $visitorId = $conn->insert_id;
                } else {
                    // Use existing visitor
                    $visitorId = $_POST['visitorId'];
                }

                // Create appointment
                $status = 'Upcoming';
                $sql = "INSERT INTO appointments (VisitorID, HostID,AppointmentTime, Status) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $visitorId, $hostId,$appointmentTime, $status);
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'reschedule':
            // Reschedule an appointment
            if (!isset($_POST['appointmentId']) || !isset($_POST['newTime'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $newTime = $_POST['newTime'];
            $rescheduleReason = $_POST['rescheduleReason'] ?? '';

            // Update appointment
            $sql = "UPDATE appointments SET AppointmentTime = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $newTime, $appointmentId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reschedule appointment']);
            }
            break;

        case 'checkIn':
            // Check in a visitor
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
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check in visitor']);
            }
            break;

        case 'completeSession':
            // Complete an appointment
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $status = 'Completed';
            $checkOutTime = date('Y-m-d H:i:s');

            $sql = "UPDATE appointments SET Status = ?, CheckOutTime = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $checkOutTime, $appointmentId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to complete appointment']);
            }
            break;

        case 'cancelAppointment':
            // Cancel an appointment
            if (!isset($_POST['appointmentId'])) {
                echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
                break;
            }

            $appointmentId = $_POST['appointmentId'];
            $status = 'Cancelled';

            $sql = "UPDATE appointments SET Status = ? WHERE AppointmentID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $appointmentId);

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