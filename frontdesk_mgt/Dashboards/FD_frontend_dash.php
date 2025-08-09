<?php
session_start();
require_once '../dbConfig.php';
require_once __DIR__ . '/audit_logger.php';
global $conn;

// Check if user is logged in and has Front Desk role
if (!isset($_SESSION['userID'])) {  // Fixed: Added closing parenthesis
    header("Location: ../Auth.html");
    exit;
}
// Include appointments functions
require_once 'front_desk_appointments.php';

// Update appointment statuses
updateAppointmentStatuses();

// Get data for the page
$appointments = getAllAppointments();
$stats = getAppointmentStats();
$hosts = getAllHosts();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'getVisitors':
                $visitors = getVisitors();
                echo json_encode($visitors);
                break;

            case 'getAppointmentDetails':
                if (!isset($_POST['appointmentId'])) {
                    throw new Exception('Appointment ID is required');
                }
                $details = getAppointmentDetails($_POST['appointmentId']);
                echo json_encode($details);
                break;

            case 'schedule':
                $required = ['hostId', 'appointmentTime'];
                if ($_POST['isNewVisitor'] === '1') {
                    array_push($required, 'newVisitorName', 'newVisitorEmail');
                } else {
                    array_push($required, 'visitorId');
                }

                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("$field is required");
                    }
                }

                $result = scheduleAppointment($_POST);
                echo json_encode($result);
                break;

            case 'reschedule':
                $required = ['appointmentId', 'newTime'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("$field is required");
                    }
                }

                $result = rescheduleAppointment($_POST);
                echo json_encode($result);
                break;

            case 'checkInWithDetails':
                $required = ['appointmentId', 'visitorId', 'idType', 'idNumber', 'visitPurpose'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("$field is required");
                    }
                }

                $result = checkInVisitor($_POST);
                echo json_encode($result);
                break;

            case 'cancelAppointment':
                if (empty($_POST['appointmentId']) || empty($_POST['reason'])) {
                    throw new Exception('Appointment ID and reason are required');
                }

                $result = cancelAppointment($_POST['appointmentId'], $_POST['reason']);
                echo json_encode($result);
                break;

            case 'checkConflict':
                if (empty($_POST['hostId']) || empty($_POST['appointmentTime'])) {
                    throw new Exception('Host ID and appointment time are required');
                }

                $appointmentId = $_POST['appointmentId'] ?? null;
                $result = checkTimeConflict($_POST['hostId'], $_POST['appointmentTime'], $appointmentId);
                echo json_encode($result);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Function to get visitors for dropdown
function getVisitors() {
    global $conn;
    $stmt = $conn->prepare("SELECT VisitorID, Name, Email FROM visitors ORDER BY Name");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get appointment details
function getAppointmentDetails($appointmentId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT a.*, v.Name AS VisitorName, v.Email AS VisitorEmail, v.Phone AS VisitorPhone,
               h.Name AS HostName, h.UserID AS HostID
        FROM appointments a
        JOIN visitors v ON a.VisitorID = v.VisitorID
        JOIN users h ON a.HostID = h.UserID
        WHERE a.AppointmentID = ?
    ");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to schedule a new appointment
function scheduleAppointment($data) {
    global $conn;

    $conn->begin_transaction();

    try {
        // Handle new visitor if needed
        if ($data['isNewVisitor'] === '1') {
            $stmt = $conn->prepare("INSERT INTO visitors (Name, Email, Phone) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $data['newVisitorName'], $data['newVisitorEmail'], $data['newVisitorPhone']);
            $stmt->execute();
            $visitorId = $stmt->insert_id;
        } else {
            $visitorId = $data['visitorId'];
        }

        // Schedule appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (VisitorID, HostID, AppointmentTime, Status, CreatedBy)
            VALUES (?, ?, ?, 'Upcoming', ?)
        ");
        $status = 'Upcoming';
        $createdBy = $_SESSION['userID'];
        $stmt->bind_param("iisi", $visitorId, $data['hostId'], $data['appointmentTime'], $createdBy);
        $stmt->execute();

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Appointment scheduled successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to reschedule an appointment
function rescheduleAppointment($data) {
    global $conn;

    $stmt = $conn->prepare("
        UPDATE appointments 
        SET AppointmentTime = ?, Status = 'Upcoming', UpdatedAt = NOW()
        WHERE AppointmentID = ?
    ");
    $stmt->bind_param("si", $data['newTime'], $data['appointmentId']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Log reschedule reason if provided
        if (!empty($data['rescheduleReason'])) {
            $stmt = $conn->prepare("
                INSERT INTO appointment_notes (AppointmentID, Note, CreatedBy)
                VALUES (?, ?, ?)
            ");
            $note = "Rescheduled: " . $data['rescheduleReason'];
            $createdBy = $_SESSION['userID'];
            $stmt->bind_param("isi", $data['appointmentId'], $note, $createdBy);
            $stmt->execute();
        }

        return [
            'success' => true,
            'message' => 'Appointment rescheduled successfully'
        ];
    } else {
        throw new Exception('Failed to reschedule appointment');
    }
}

// Function to check in a visitor
function checkInVisitor($data) {
    global $conn;

    $conn->begin_transaction();

    try {
        // Update appointment status
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET Status = 'Ongoing', CheckInTime = NOW(), UpdatedAt = NOW()
            WHERE AppointmentID = ?
        ");
        $stmt->bind_param("i", $data['appointmentId']);
        $stmt->execute();

        // Record visitor check-in details
        $stmt = $conn->prepare("
            INSERT INTO visitor_checkins (VisitorID, AppointmentID, IDType, IDNumber, Purpose, CheckedInBy)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $checkedInBy = $_SESSION['userID'];
        $stmt->bind_param("iisssi",
            $data['visitorId'],
            $data['appointmentId'],
            $data['idType'],
            $data['idNumber'],
            $data['visitPurpose'],
            $checkedInBy
        );
        $stmt->execute();

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Visitor checked in successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to cancel an appointment
function cancelAppointment($appointmentId, $reason) {
    global $conn;

    $stmt = $conn->prepare("
        UPDATE appointments 
        SET Status = 'Cancelled', CancellationReason = ?, UpdatedAt = NOW()
        WHERE AppointmentID = ?
    ");
    $stmt->bind_param("si", $reason, $appointmentId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return [
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ];
    } else {
        throw new Exception('Failed to cancel appointment');
    }
}

// Function to check for time conflicts
function checkTimeConflict($hostId, $appointmentTime, $excludeAppointmentId = null) {
    global $conn;

    $query = "
        SELECT COUNT(*) AS conflict_count
        FROM appointments
        WHERE HostID = ? 
        AND AppointmentTime = ?
        AND Status NOT IN ('Cancelled', 'Completed')
    ";

    if ($excludeAppointmentId) {
        $query .= " AND AppointmentID != ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $hostId, $appointmentTime, $excludeAppointmentId);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $hostId, $appointmentTime);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['conflict_count'] > 0) {
        return [
            'success' => false,
            'message' => 'The selected time is already booked for this host'
        ];
    } else {
        return [
            'success' => true,
            'message' => 'Time slot is available'
        ];
    }
}
?>

<!DOCTYPE html>
<html
        lang="en"
        dir="ltr"
        data-theme="theme-default"
        data-assets-path="../../Sneat/assets/"
        data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments- Front Desk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Sneat CSS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/pages/app-calendar.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/libs/flatpickr/flatpickr.css" />
    <link rel="stylesheet" href="notification.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: row;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background-color: #343a40;
            padding-top: 1rem;
        }

        .sidebar a {
            color: #fff;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
        }

        .sidebar a:hover {
            background-color: #495057;
        }

        .main-content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
            overflow-x: auto;
        }

        .appointment-card {
            transition: all 0.3s ease;
        }

        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .search-form {
            margin-bottom: 20px;
        }

        .stats-card {
            border-left: 4px solid;
            margin-bottom: 20px;
        }

        .stats-card.primary {
            border-left-color: #0d6efd;
        }

        .stats-card.success {
            border-left-color: #198754;
        }

        .stats-card.warning {
            border-left-color: #ffc107;
        }

        .stats-card.danger {
            border-left-color: #dc3545;
        }

        .calendar-view {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .host-filter {
            margin-bottom: 20px;
        }
        .flatpickr-calendar {
            display: block !important;
            z-index: 9999 !important;
        }
        /* Sidebar width fixes */
        #layout-menu {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
            flex: 0 0 260px !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1000 !important;
        }

        .layout-menu-collapsed #layout-menu {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
            flex: 0 0 78px !important;
        }
        #layout-navbar {
            position: sticky;
            top: 0;
            z-index: 999; /* Ensure it stays above other content */
            background-color: var(--bs-body-bg); /* Match your theme background */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Optional: adds subtle shadow */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .layout-content {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

        .layout-wrapper {
            overflow: hidden !important;
            height: 100vh !important;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

        .no-transition {
            transition: none !important;
        }
        .tab-content {
            position: relative;
            min-height: 500px; /* Adjust as needed */
        }

        .tab-pane {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            display: none;
        }

        .tab-pane.active {
            display: block;
            position: static; /* Reset for active pane */
        }

        #calendarView {
            width: 100%;
        }

        /* Chat Bot Styles */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .chat-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .chat-toggle:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .chat-toggle::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
        }

        .chat-toggle:hover::before {
            width: 100%;
            height: 100%;
        }

        .chat-toggle.active {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .chat-container {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .chat-container.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .chat-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .chat-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .chat-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .chat-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .chat-messages {
            height: 340px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            animation: messageSlideIn 0.3s ease-out;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-break: break-word;
        }

        .message.bot .message-content {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
            margin-right: auto;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .message.bot::before {
            content: '🤖';
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 16px;
            flex-shrink: 0;
        }

        .typing-indicator {
            display: none;
            align-items: center;
            margin-bottom: 15px;
        }

        .typing-indicator.active {
            display: flex;
        }

        .typing-dots {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 18px;
            padding: 12px 16px;
            margin-left: 42px;
        }

        .typing-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes typing {
            0%, 80%, 100% {
                transform: scale(0.8);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .chat-input-container {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            resize: none;
            min-height: 20px;
            max-height: 80px;
            overflow-y: auto;
        }

        .chat-input:focus {
            border-color: #667eea;
        }

        .chat-input::placeholder {
            color: #999;
        }

        .chat-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-send:hover {
            transform: scale(1.05);
        }

        .chat-send:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .welcome-message {
            text-align: center;
            color: #666;
            padding: 20px;
            font-size: 14px;
        }

        .welcome-message h4 {
            color: #333;
            margin-bottom: 10px;
        }

        /* Notification Badge */
        .chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #ff4757;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 71, 87, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0);
            }
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 13px;
            border-left: 4px solid #c33;
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'frontdesk-sidebar.php'; ?>
        <!-- Main Content -->
        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base bx bx-menu icon-md"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!-- Page Title -->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2">Manage Appointments</h4>
                        </div>
                    </div>
                    <!-- Schedule Appointment button -->
                    <div class="navbar-nav align-items-center me-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                            <i class="fas fa-plus-circle me-2"></i> Schedule New Appointment
                        </button>
                    </div>
                </div>
            </nav>
            <div class="container-fluid py-4">
                <!-- View Toggle Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="listViewBtn">
                                <i class="fas fa-list me-2"></i> List View
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="calendarViewBtn">
                                <i class="fas fa-calendar-alt me-2"></i> Calendar View
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form class="search-form d-flex">
                            <input type="text" class="form-control me-2" id="searchInput" placeholder="Search by visitor name, email, or host...">
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <div class="host-filter">
                            <select class="form-select" id="hostFilter">
                                <option value="">All Hosts</option>
                                <?php foreach ($hosts as $host): ?>
                                    <option value="<?= $host['UserID'] ?>" data-host-name="<?= htmlspecialchars($host['Name']) ?>">
                                        <?= htmlspecialchars($host['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Status Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="today">Today</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Upcoming">Upcoming</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Overdue">Overdue</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Ongoing">Ongoing</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Completed">Completed</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Cancelled">Cancelled</button>
                        </div>
                    </div>
                </div>
                <div class="tab-content" id="viewTabsContent" style="position: relative;">
                    <!-- List View (default) -->
                    <div class="tab-pane fade show active" id="list-view" role="tabpanel">
                        <div class="row" id="appointmentsList">
                            <?php if (empty($appointments)): ?>
                                <div class="col-12 text-center py-5">
                                    <h4 class="text-muted">No appointments found</h4>
                                    <p>Schedule an appointment by clicking the button above</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <div class="col-md-6 col-lg-4 mb-4 appointment-item"
                                         data-status="<?= $appointment['Status'] ?>"
                                         data-host="<?= $appointment['HostName']?>"
                                         data-host-id="<?= $appointment['HostID'] ?>"
                                         data-date="<?= date('Y-m-d', strtotime($appointment['AppointmentTime'])) ?>"
                                         data-search="<?= strtolower($appointment['VisitorName'] . ' ' . $appointment['VisitorEmail'] . ' ' . $appointment['HostName']) ?>">
                                        <div class="card appointment-card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <?php
                                                // Map status to Bootstrap classes
                                                $badgeClass = match($appointment['Status']) {
                                                    'Cancelled' => 'danger',
                                                    'Ongoing' => 'info',
                                                    'Upcoming' => 'primary',
                                                    'Completed' => 'success',
                                                    'Overdue' => 'warning',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge rounded-pill text-bg-<?= $badgeClass ?>"><?= $appointment['Status'] ?></span>
                                                <small><?= date('M d, Y', strtotime($appointment['AppointmentTime'])) ?></small>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($appointment['VisitorName']) ?></h5>
                                                <p class="card-text mb-1">
                                                    <i class="far fa-envelope me-2"></i> <?= htmlspecialchars($appointment['VisitorEmail']) ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <i class="far fa-clock me-2"></i> <?= date('h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                                </p>
                                                <p class="card-text mb-3">
                                                    <i class="fas fa-user-tie me-2"></i> <?= htmlspecialchars($appointment['HostName']) ?>
                                                </p>

                                                <?php if ($appointment['Status'] === 'Upcoming' || $appointment['Status'] === 'Overdue'): ?>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-success check-in-btn"
                                                                data-id="<?= $appointment['AppointmentID'] ?>">
                                                            <i class="fas fa-check-circle me-1"></i> Check In
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary reschedule-btn"
                                                                data-id="<?= $appointment['AppointmentID'] ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#rescheduleModal">
                                                            <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger cancel-btn"
                                                                data-id="<?= $appointment['AppointmentID'] ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#cancelModal">
                                                            <i class="fas fa-times-circle me-1"></i> Cancel
                                                        </button>
                                                    </div>
                                                <?php elseif ($appointment['Status'] === 'Ongoing'): ?>
                                                    <button class="btn btn-sm btn-outline-warning complete-session-btn"
                                                            data-id="<?= $appointment['AppointmentID'] ?>">
                                                        <i class="fas fa-check-double me-1"></i> Complete
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($appointment['Status'] === 'Cancelled' && !empty($appointment['CancellationReason'])): ?>
                                                    <p class="text-muted mt-2">Cancellation Reason: <?= htmlspecialchars($appointment['CancellationReason']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Calendar View Tab -->
                    <div class="tab-pane fade" id="calendar-view" role="tabpanel">
                        <div class="card app-calendar-wrapper">
                            <div class="row g-0">
                                <!-- Calendar Sidebar -->
                                <div class="col-md-3 app-calendar-sidebar border-end" id="app-calendar-sidebar">
                                    <div class="px-3 pt-2">
                                        <div class="inline-calendar"></div>
                                    </div>
                                    <hr class="mb-4 mx-n4 mt-3" />
                                    <div class="px-4 pb-2">
                                        <div>
                                            <h6>Event Filters</h6>
                                        </div>
                                        <div class="form-check mb-3 ms-2">
                                            <input class="form-check-input select-all" type="checkbox" id="selectAll" data-value="all" checked />
                                            <label class="form-check-label" for="selectAll">View All</label>
                                        </div>
                                        <div class="app-calendar-events-filter">
                                            <div class="form-check form-check-primary mb-3 ms-2">
                                                <input class="form-check-input input-filter" type="checkbox" id="select-upcoming" data-value="upcoming" checked />
                                                <label class="form-check-label" for="select-upcoming">Upcoming</label>
                                            </div>
                                            <div class="form-check form-check-warning ms-2">
                                                <input class="form-check-input input-filter" type="checkbox" id="select-overdue" data-value="overdue" checked />
                                                <label class="form-check-label" for="select-overdue">Overdue</label>
                                            </div>
                                            <div class="form-check form-check-info ms-2">
                                                <input class="form-check-input input-filter" type="checkbox" id="select-ongoing" data-value="ongoing" checked />
                                                <label class="form-check-label" for="select-ongoing">Ongoing</label>
                                            </div>
                                            <div class="form-check form-check-success mb-3 ms-2">
                                                <input class="form-check-input input-filter" type="checkbox" id="select-completed" data-value="completed" checked />
                                                <label class="form-check-label" for="select-completed">Completed</label>
                                            </div>
                                            <div class="form-check form-check-danger mb-3 ms-2">
                                                <input class="form-check-input input-filter" type="checkbox" id="select-cancelled" data-value="cancelled" checked />
                                                <label class="form-check-label" for="select-cancelled">Cancelled</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-9 app-calendar-content">
                                    <div class="card shadow-none border-0">
                                        <div class="card-body pb-0">
                                            <!-- FullCalendar -->
                                            <div id="calendar"></div>
                                        </div>
                                    </div>
                                    <div class="app-overlay"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Chat Widget -->
<div class="chat-widget">
    <!-- Chat Toggle Button -->
    <button class="chat-toggle" id="chatToggle">
        <i class="fas fa-comments"></i>
        <div class="chat-notification" id="chatNotification">1</div>
    </button>

    <!-- Chat Container -->
    <div class="chat-container" id="chatContainer">
        <!-- Chat Header -->
        <div class="chat-header">
            <button class="chat-close" id="chatClose">
                <i class="fas fa-times"></i>
            </button>
            <h3>AI Assistant</h3>
            <p>How can I help you today?</p>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="welcome-message">
                <h4>👋 Welcome!</h4>
                <p>I'm your AI assistant powered by Google Gemini. Ask me anything about appointments, scheduling, or general questions!</p>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="typing-indicator" id="typingIndicator">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-container">
            <textarea
                    class="chat-input"
                    id="chatInput"
                    placeholder="Type your message..."
                    rows="1"
            ></textarea>
            <button class="chat-send" id="chatSend">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>




<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" name="action" value="schedule">
                    <input type="hidden" name="isNewVisitor" id="isNewVisitor" value="0">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hostSelect" class="form-label">Select Host</label>
                            <select class="form-select" id="hostSelect" name="hostId" required>
                                <option value="">-- Select Host --</option>
                                <?php foreach ($hosts as $host): ?>
                                    <option value="<?= $host['UserID'] ?>"><?= htmlspecialchars($host['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="appointmentDateTime" class="form-label">Appointment Date & Time</label>
                            <input type="text" class="form-control" id="appointmentTime" name="appointmentTime" required>
                        </div>
                    </div>

                    <div class="mb-3" id="visitorSelectContainer">
                        <label for="visitorSelect" class="form-label">Select Visitor</label>
                        <select class="form-select" id="visitorSelect" name="visitorId">
                            <option value="">-- Select Visitor --</option>
                            <option value="new">-- Add New Visitor --</option>
                            <!-- populated with AJAX -->
                        </select>
                    </div>

                    <!-- New Visitor Fields (initially hidden) -->
                    <div id="newVisitorFields" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="newVisitorName" class="form-label">Visitor Name</label>
                                <input type="text" class="form-control" id="newVisitorName" name="newVisitorName">
                            </div>
                            <div class="col-md-6">
                                <label for="newVisitorEmail" class="form-label">Visitor Email</label>
                                <input type="email" class="form-control" id="newVisitorEmail" name="newVisitorEmail">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newVisitorPhone" class="form-label">Visitor Phone</label>
                            <input type="tel" class="form-control" id="newVisitorPhone" name="newVisitorPhone">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="scheduleBtn">Schedule Appointment</button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rescheduleModalLabel">Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rescheduleForm">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="appointmentId" id="rescheduleAppointmentId">
                    <input type="hidden" name="hostId" id="rescheduleHostId">

                    <div class="mb-3">
                        <p><strong>Visitor:</strong> <span id="rescheduleVisitorName"></span></p>
                        <p><strong>Host:</strong> <span id="rescheduleHostName"></span></p>
                    </div>

                    <div class="mb-3">
                        <label for="newTime" class="form-label">New Date & Time</label>
                        <input type="text" class="form-control" id="newTime" name="newTime" required>
                    </div>

                    <div class="mb-3">
                        <label for="rescheduleReason" class="form-label">Reason for Rescheduling (Optional)</label>
                        <textarea class="form-control" id="rescheduleReason" name="rescheduleReason" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="rescheduleBtn">Reschedule</button>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentDetailsModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="appointmentDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div id="detailsActionButtons"></div>
            </div>
        </div>
    </div>
</div>

<!-- Visitor Check-In Modal -->
<div class="modal fade" id="visitorCheckInModal" tabindex="-1" aria-labelledby="visitorCheckInModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorCheckInModalLabel">Check-In Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="visitorCheckInForm">
                    <input type="hidden" name="action" value="checkInWithDetails">
                    <input type="hidden" name="appointmentId" id="checkInAppointmentId">
                    <input type="hidden" name="visitorId" id="checkInVisitorId">

                    <div class="mb-3">
                        <p><strong>Visitor:</strong> <span id="checkInVisitorName"></span></p>
                        <p><strong>Email:</strong> <span id="checkInVisitorEmail"></span></p>
                        <p><strong>Host:</strong> <span id="checkInHostName"></span></p>
                        <p><strong>Appointment Time:</strong> <span id="checkInAppointmentTime"></span></p>
                    </div>

                    <div class="mb-3">
                        <label for="visitorIDType" class="form-label">ID Type</label>
                        <select class="form-select" id="visitorIDType" name="idType" required>
                            <option value="">-- Select ID Type --</option>
                            <option value="National ID">National ID</option>
                            <option value="Passport">Passport</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="Employee ID">Employee ID</option>
                            <option value="Student ID">Student ID</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="visitorIDNumber" class="form-label">ID Number</label>
                        <input type="text" class="form-control" id="visitorIDNumber" name="idNumber" required>
                    </div>

                    <div class="mb-3">
                        <label for="visitPurpose" class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" id="visitPurpose" name="visitPurpose" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="completeCheckInBtn">Complete Check-In</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this appointment?</p>
                <div class="mb-3">
                    <label for="cancellationReason" class="form-label">Cancellation Reason</label>
                    <select class="form-select" id="cancellationReason" required>
                        <option value="">-- Select Reason --</option>
                        <option value="Visitor Cancelled">Visitor Cancelled</option>
                        <option value="Host Cancelled">Host Cancelled</option>
                        <option value="Scheduling Conflict">Scheduling Conflict</option>
                        <option value="Emergency">Emergency</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Cancel Appointment</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>
<script src="notification.js"></script>
<script>
    $(document).ready(function() {
        const appointmentsData = {};

        // Load visitors for dropdown
        $.ajax({
            url: 'front_desk_appointments.php',
            type: 'POST',
            data: {
                action: 'getVisitors'
            },
            success: function(response) {
                if (response.length > 0) {
                    response.forEach(function(visitor) {
                        $('#visitorSelect').append(
                            $('<option></option>').val(visitor.VisitorID).text(visitor.Name + ' (' + visitor.Email + ')')
                        );
                    });
                }
            }
        });

        // Toggle new visitor fields
        $('#visitorSelect').change(function() {
            if ($(this).val() === 'new') {
                $('#newVisitorFields').show();
                $('#isNewVisitor').val('1');
                $('#newVisitorName, #newVisitorEmail').prop('required', true);
                $(this).prop('required', false);
            } else {
                $('#newVisitorFields').hide();
                $('#isNewVisitor').val('0');
                $('#newVisitorName, #newVisitorEmail').prop('required', false);
                $(this).prop('required', true);
            }
        });

        $('#listViewBtn').click(function() {
            $(this).addClass('active').removeClass('btn-outline-primary').addClass('btn-primary');
            $('#calendarViewBtn').removeClass('active').removeClass('btn-primary').addClass('btn-outline-primary');
            $('#list-view').addClass('show active');
            $('#calendar-view').removeClass('show active');
        });

        $('#calendarViewBtn').click(function() {
            $(this).addClass('active').removeClass('btn-outline-primary').addClass('btn-primary');
            $('#listViewBtn').removeClass('active').removeClass('btn-primary').addClass('btn-outline-primary');
            $('#calendar-view').addClass('show active');
            $('#list-view').removeClass('show active');

            // Initialize calendar if not already done
            setTimeout(() => {
                if (!window.calendar) {
                    window.calendar = initializeCalendar();
                } else {
                    try {
                        window.calendar.refetchEvents();
                        window.calendar.render();
                    } catch (e) {
                        console.error('Calendar refresh failed:', e);
                        window.calendar = initializeCalendar();
                    }
                }
            }, 100);
        });
    // CALENDAR INITIALIZATION FUNCTION
        function initializeCalendar() {
            console.log('Initializing calendar...');
            const calendarEl = document.getElementById('calendar');

            if (!calendarEl) {
                console.error('Calendar element not found');
                return null;
            }

            try {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    height: 'auto',
                    events: function(fetchInfo, successCallback, failureCallback) {
                        $.ajax({
                            url: 'front_desk_appointments.php',
                            type: 'POST',
                            data: {
                                action: 'getAppointmentsForCalendar'
                            },
                            success: function(response) {
                                successCallback(response);
                            },
                            error: function(error) {
                                console.error('Error loading calendar events:', error);
                                failureCallback(error);
                            }
                        });
                    },
                    eventClick: function(info) {
                        const event = info.event;
                        const modalHtml = `
                    <div class="modal fade" id="calendarEventModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Appointment Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Visitor:</strong> ${event.extendedProps.visitorName}</p>
                                    <p><strong>Host:</strong> ${event.extendedProps.hostName}</p>
                                    <p><strong>Time:</strong> ${event.start.toLocaleString()}</p>
                                    <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeClass(event.extendedProps.status)}">${event.extendedProps.status}</span></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    ${getActionButtons(event.id, event.extendedProps.status)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                        $('#calendarEventModal').remove();
                        $('body').append(modalHtml);
                        $('#calendarEventModal').modal('show');
                    },
                    eventDidMount: function(info) {
                        // Add tooltip
                        $(info.el).tooltip({
                            title: `${info.event.title}\nStatus: ${info.event.extendedProps.status}\nClick for details`,
                            placement: 'top',
                            trigger: 'hover'
                        });
                    }
                });

                calendar.render();
                return calendar;
            } catch (error) {
                console.error('Calendar initialization failed:', error);
                return null;
            }
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'Cancelled': 'danger',
                'Ongoing': 'info',
                'Upcoming': 'primary',
                'Completed': 'success',
                'Overdue': 'warning'
            };
            return classes[status] || 'secondary';
        }

        function getActionButtons(appointmentId, status) {
            let buttons = '';

            if (status === 'Upcoming' || status === 'Overdue') {
                buttons += `
            <button class="btn btn-sm btn-success check-in-btn" data-id="${appointmentId}">
                <i class="fas fa-check-circle me-1"></i> Check In
            </button>
            <button class="btn btn-sm btn-primary reschedule-btn" data-id="${appointmentId}">
                <i class="fas fa-calendar-alt me-1"></i> Reschedule
            </button>
            <button class="btn btn-sm btn-danger cancel-btn" data-id="${appointmentId}">
                <i class="fas fa-times me-1"></i> Cancel
            </button>
        `;
            } else if (status === 'Ongoing') {
                buttons += `
            <button class="btn btn-sm btn-warning complete-btn" data-id="${appointmentId}">
                <i class="fas fa-stop me-1"></i> Complete
            </button>
        `;
            }

            return buttons;
        }

    // CALENDAR FILTER FUNCTIONALITY
    $(document).on('change', '.input-filter', function() {
        console.log('Calendar filter changed');
        if (calendar && typeof calendar.getEvents === 'function') {
            updateCalendarFilters();
        }
    });

    $(document).on('change', '.select-all', function() {
        const isChecked = $(this).is(':checked');
        $('.input-filter').prop('checked', isChecked);
        if (calendar && typeof calendar.getEvents === 'function') {
            updateCalendarFilters();
        }
    });

    function updateCalendarFilters() {
        if (!calendar || typeof calendar.getEvents !== 'function') {
            console.warn('Calendar not available for filtering');
            return;
        }

        const checkedFilters = $('.input-filter:checked').map(function() {
            return $(this).data('value');
        }).get();

        console.log('Applying calendar filters:', checkedFilters);

        const events = calendar.getEvents();
        console.log('Total events to filter:', events.length);

        events.forEach(function(event) {
            const status = event.extendedProps.status.toLowerCase();
            const shouldShow = checkedFilters.includes(status) || checkedFilters.includes('all');

            console.log(`Event: ${event.title}, Status: ${status}, Show: ${shouldShow}`);

            if (shouldShow) {
                event.setProp('display', 'auto');
            } else {
                event.setProp('display', 'none');
            }
        });
    }

    // Initialize inline calendar
    setTimeout(() => {
        try {
            // Initialize inline calendar
            flatpickr(".inline-calendar", {
                inline: true,
                onChange: function(selectedDates) {
                    if (calendar && selectedDates[0]) {
                        calendar.gotoDate(selectedDates[0]);
                    }
                }
            });
            console.log('Inline calendar initialized');
        } catch (e) {
            console.error('Flatpickr initialization failed:', e);
        }
    }, 300);

    // Button handlers for calendar modal
        $(document).on('click', '.check-in-btn, .reschedule-btn, .cancel-btn, .complete-btn', function() {
            const appointmentId = $(this).data('id');
            const action = $(this).hasClass('check-in-btn') ? 'checkIn' :
                $(this).hasClass('reschedule-btn') ? 'reschedule' :
                    $(this).hasClass('cancel-btn') ? 'cancel' : 'complete';

            $('#calendarEventModal').modal('hide');

            // Handle each action appropriately
            if (action === 'checkIn') {
                // Show check-in modal
                $('#checkInAppointmentId').val(appointmentId);
                $('#visitorCheckInModal').modal('show');
            } else if (action === 'reschedule') {
                // Show reschedule modal
                $('#rescheduleAppointmentId').val(appointmentId);
                $('#rescheduleModal').modal('show');
            } else if (action === 'cancel') {
                // Show cancel modal
                $('#cancelModal').data('appointmentId', appointmentId);
                $('#cancelModal').modal('show');
            } else if (action === 'complete') {
                // Handle completion
            }
        });



    // Search and filter handlers
        $('#searchInput').on('keyup', function() {
            filterAppointments();
        });

        $('#hostFilter').on('change', function() {
            filterAppointments();
        });

        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            filterAppointments();
        });

        function filterAppointments() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const hostFilter = $('#hostFilter').val();
            const statusFilter = $('.filter-btn.active').data('filter') || 'all';
            const today = new Date().toISOString().split('T')[0];

            $('.appointment-item').each(function() {
                let show = true;

                if (searchTerm) {
                    const searchData = $(this).data('search').toString().toLowerCase();
                    if (searchData.indexOf(searchTerm) === -1) {
                        show = false;
                    }
                }

                if (hostFilter && show) {
                    if ($(this).data('host-id').toString() !== hostFilter.toString()) {
                        show = false;
                    }
                }

                if (show && statusFilter !== 'all') {
                    if (statusFilter === 'today') {
                        if ($(this).data('date') !== today) {
                            show = false;
                        }
                    } else if ($(this).data('status') !== statusFilter) {
                        show = false;
                    }
                }

                $(this).toggle(show);
            });

            const visibleItems = $('.appointment-item:visible').length;
            if (visibleItems === 0) {
                if ($('#noResultsMessage').length === 0) {
                    $('#appointmentsList').append('<div id="noResultsMessage" class="col-12 text-center py-5"><h4 class="text-muted">No appointments match your filters</h4></div>');
                }
            } else {
                $('#noResultsMessage').remove();
            }
        }


        // New scheduleAppointment function
        function scheduleAppointment() {
            if (!document.querySelector('#scheduleForm').checkValidity()) {
                document.querySelector('#scheduleForm').reportValidity();
                return;
            }

            let formData = new FormData(document.querySelector('#scheduleForm'));
            formData.append('action', 'schedule');

            fetch('front_desk_appointments.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#scheduleModal').modal('hide');
                        alert('Appointment scheduled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        // Bind scheduleAppointment
        $('#scheduleBtn').click(function() {
            scheduleAppointment();
        });

        $('#rescheduleBtn').click(function() {
            if (!$('#rescheduleForm')[0].checkValidity()) {
                $('#rescheduleForm')[0].reportValidity();
                return;
            }

            // Explicitly collect form data
            const formData = {
                action: 'reschedule',
                appointmentId: $('#rescheduleAppointmentId').val(),
                newTime: $('#newTime').val(),
                hostId: $('#rescheduleHostId').val(),
                rescheduleReason: $('#rescheduleReason').val()
            };

            // Log form data for debugging
            console.log('Reschedule Form Data:', formData);

            // Check if required fields are present
            if (!formData.appointmentId || !formData.newTime) {
                alert('Error: Required fields are missing. Please ensure appointment ID and new time are provided.');
                return;
            }

            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#rescheduleModal').modal('hide');
                        alert('Appointment rescheduled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred. Please try again.');
                }
            });
        });

        $(document).on('click', '.check-in-btn, .check-in-modal-btn', function() {
            const appointmentId = $(this).data('id');
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#checkInAppointmentId').val(appointmentId);
                        $('#checkInVisitorId').val(response.VisitorID);
                        $('#checkInVisitorName').text(response.VisitorName);
                        $('#checkInVisitorEmail').text(response.VisitorEmail);
                        $('#checkInHostName').text(response.HostName);
                        $('#checkInAppointmentTime').text(new Date(response.AppointmentTime).toLocaleString());
                        $('#visitorCheckInModal').modal('show');
                    } else {
                        alert('Error: Could not retrieve appointment details');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

        $('#completeCheckInBtn').click(function() {
            if (!$('#visitorCheckInForm')[0].checkValidity()) {
                $('#visitorCheckInForm')[0].reportValidity();
                return;
            }

            const formData = $('#visitorCheckInForm').serialize();
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#visitorCheckInModal').modal('hide');
                        alert('Visitor checked in successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('An error occurred during check-in.');
                }
            });
        });

        $('#cancelModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var appointmentId = button.data('id');
            var modal = $(this);
            modal.data('appointmentId', appointmentId);
        });

        $('#confirmCancelBtn').click(function() {
            var modal = $('#cancelModal');
            var appointmentId = modal.data('appointmentId');
            var reason = $('#cancellationReason').val();
            if (!reason) {
                alert('Please select a cancellation reason.');
                return;
            }
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'cancelAppointment',
                    appointmentId: appointmentId,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        modal.modal('hide');
                        alert('Appointment cancelled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });


// TAB SWITCHING - Initialize calendar when tab is shown
        $('#calendarViewBtn').click(function() {
            console.log('Calendar tab activated');

            // Small delay to ensure DOM is ready
            setTimeout(() => {
                if (!window.calendar) {
                    console.log('Creating new calendar instance');
                    window.calendar = initializeCalendar();
                } else {
                    console.log('Refreshing existing calendar');
                    try {
                        window.calendar.refetchEvents();
                        window.calendar.render();
                    } catch (e) {
                        console.error('Calendar refresh failed:', e);
                        window.calendar = initializeCalendar();
                    }
                }
            }, 150);
        });


        $(document).on('click', '.reschedule-btn', function() {
            const appointmentId = $(this).data('id');
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#rescheduleAppointmentId').val(appointmentId);
                        $('#rescheduleVisitorName').text(response.VisitorName);
                        $('#rescheduleHostName').text(response.HostName);
                        $('#rescheduleHostId').val(response.HostID);
                        $('#rescheduleModal').modal('show');
                    }
                }
            });
        });

        $(document).on('click', '.reschedule-modal-btn', function() {
            const appointmentId = $(this).data('id');
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#appointmentDetailsModal').modal('hide');
                        $('#rescheduleAppointmentId').val(appointmentId);
                        $('#rescheduleVisitorName').text(response.VisitorName);
                        $('#rescheduleHostName').text(response.HostName);
                        $('#rescheduleHostId').val(response.HostID);
                        $('#rescheduleModal').modal('show');
                    }
                }
            });
        });
    });

    // Flatpickr Configuration
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Flatpickr for scheduling new appointments
        flatpickr('#appointmentTime', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            minDate: 'today',
            time_24hr: false,
            minuteIncrement: 15,
            disable: [
                function(date) {
                    return (date.getDay() === 0); // Disable Sundays (0 = Sunday)
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                let hostId = document.querySelector('#hostSelect').value;
                if (hostId && dateStr) {
                    checkAvailableSlots(hostId, dateStr);
                }
            }
        });

        // Initialize Flatpickr for rescheduling appointments
        flatpickr('#newTime', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            minDate: 'today',
            time_24hr: false,
            minuteIncrement: 15,
            disable: [
                function(date) {
                    return (date.getDay() === 0); // Disable Sundays (0 = Sunday)
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                let appointmentId = document.querySelector('#rescheduleAppointmentId').value;
                let hostId = document.querySelector('#rescheduleHostId').value;
                if (hostId && dateStr) {
                    checkAvailableSlots(hostId, dateStr, appointmentId);
                }
            }
        });
    });

    function checkAvailableSlots(hostId, appointmentTime, appointmentId = null) {
        fetch('front_desk_appointments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=checkConflict&hostId=${hostId}&appointmentTime=${appointmentTime}&appointmentId=${appointmentId}`
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert(`Selected time is not available: ${data.message}`);
                } else {
                    console.log('Time is available');
                }
            });
    }

    // Enhanced Gemini Chat Bot Implementation with Appointment Access
    class GeminiChatBot {
        constructor() {
            this.apiKey = 'AIzaSyACxk5zCzJt6H0jJ2vs2sIP98V9jj7NcL0';
            this.apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
            this.isOpen = false;
            this.conversationHistory = [];
            this.appointmentsData = <?php echo json_encode($appointments); ?>;

            this.initializeElements();
            this.attachEventListeners();
            this.showNotification();
        }

        initializeElements() {
            this.chatToggle = document.getElementById('chatToggle');
            this.chatContainer = document.getElementById('chatContainer');
            this.chatClose = document.getElementById('chatClose');
            this.chatMessages = document.getElementById('chatMessages');
            this.chatInput = document.getElementById('chatInput');
            this.chatSend = document.getElementById('chatSend');
            this.typingIndicator = document.getElementById('typingIndicator');
            this.chatNotification = document.getElementById('chatNotification');
        }

        attachEventListeners() {
            // Toggle chat
            this.chatToggle.addEventListener('click', () => this.toggleChat());
            this.chatClose.addEventListener('click', () => this.closeChat());

            // Send message
            this.chatSend.addEventListener('click', () => this.handleSendMessage());

            // Handle Enter key
            this.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSendMessage();
                }
            });

            // Auto-resize textarea
            this.chatInput.addEventListener('input', () => {
                this.chatInput.style.height = 'auto';
                this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 80) + 'px';
            });
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            this.chatContainer.classList.add('active');
            this.chatToggle.classList.add('active');
            this.chatToggle.innerHTML = '<i class="fas fa-times"></i>';
            this.hideNotification();

            // Focus input after animation
            setTimeout(() => {
                this.chatInput.focus();
            }, 300);
        }

        closeChat() {
            this.isOpen = false;
            this.chatContainer.classList.remove('active');
            this.chatToggle.classList.remove('active');
            this.chatToggle.innerHTML = '<i class="fas fa-comments"></i>';
        }

        showNotification() {
            this.chatNotification.style.display = 'flex';
        }

        hideNotification() {
            this.chatNotification.style.display = 'none';
        }

        async handleSendMessage() {
            const message = this.chatInput.value.trim();
            if (!message) return;

            // Clear input
            this.chatInput.value = '';
            this.chatInput.style.height = 'auto';

            // Add user message
            this.addMessage(message, 'user');

            // Show typing indicator
            this.showTyping();

            try {
                // Check for appointment-related queries
                const appointmentResponse = this.handleAppointmentQuery(message);
                if (appointmentResponse) {
                    this.hideTyping();
                    this.addMessage(appointmentResponse, 'bot');
                    return;
                }

                // If not an appointment query, send to Gemini
                const response = await this.sendToGemini(message);
                this.hideTyping();
                this.addMessage(response, 'bot');
            } catch (error) {
                this.hideTyping();
                this.addMessage('Sorry, I encountered an error. Please try again later.', 'bot', true);
                console.error('Gemini API Error:', error);
            }
        }

        handleAppointmentQuery(message) {
            const lowerMessage = message.toLowerCase();
            const today = new Date().toISOString().split('T')[0];
            const now = new Date();

            // Helper function to format appointment details
            const formatAppointment = (appt) => {
                const date = new Date(appt.AppointmentTime);
                return `👤 ${appt.Name}\n` +
                    `📅 ${date.toLocaleDateString()}\n` +
                    `⏰ ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                    `📧 ${appt.Email}\n` +
                    `📌 Status: ${appt.Status}\n` +
                    (appt.CancellationReason ? `❌ Reason: ${appt.CancellationReason}\n` : '') +
                    `---\n`;
            };

            // 1. Check for status/count queries
            if (lowerMessage.includes('how many') && lowerMessage.includes('appointments')) {
                return this.getAppointmentCounts();
            }

            // 2. Check for upcoming appointments
            if (lowerMessage.includes('upcoming') || lowerMessage.includes('future') ||
                lowerMessage.includes('next appointments')) {
                return this.getUpcomingAppointments();
            }

            // 3. Check for past appointments
            if (lowerMessage.includes('past') || lowerMessage.includes('previous') ||
                lowerMessage.includes('completed appointments')) {
                const pastAppts = this.appointmentsData
                    .filter(a => new Date(a.AppointmentTime) < now && a.Status === 'Completed')
                    .sort((a, b) => new Date(b.AppointmentTime) - new Date(a.AppointmentTime));

                if (pastAppts.length === 0) return "You have no past completed appointments.";

                let response = "Here are your past completed appointments:\n\n";
                pastAppts.slice(0, 5).forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 4. Check for today's appointments
            if (lowerMessage.includes("today") || lowerMessage.includes('this day')) {
                return this.getTodaysAppointments();
            }

            // 5. Check for specific date
            const dateMatch = message.match(/(\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b)|(\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* \d{1,2},? \d{4}\b)/i);
            if (dateMatch) {
                const searchDate = new Date(dateMatch[0]);
                const dateStr = searchDate.toLocaleDateString();
                const dateAppts = this.appointmentsData.filter(appt => {
                    const apptDate = new Date(appt.AppointmentTime).toLocaleDateString();
                    return apptDate === dateStr;
                });

                if (dateAppts.length === 0) return `No appointments found for ${dateStr}.`;

                let response = `Appointments on ${dateStr}:\n\n`;
                dateAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 6. Check for specific visitor
            const nameMatch = message.match(/(?:details|information|about|for)\s+(.+)/i);
            if (nameMatch && nameMatch[1]) {
                const searchName = nameMatch[1].trim();
                const matchedAppts = this.appointmentsData.filter(a =>
                    a.Name.toLowerCase().includes(searchName.toLowerCase())
                );

                if (matchedAppts.length === 0) return `No appointments found for ${searchName}.`;

                let response = matchedAppts.length === 1 ?
                    `Found 1 appointment for ${searchName}:\n\n` :
                    `Found ${matchedAppts.length} appointments for ${searchName}:\n\n`;

                matchedAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 7. Check for status-specific queries
            const statuses = ['upcoming', 'ongoing', 'completed', 'cancelled', 'overdue'];
            const statusQuery = statuses.find(status => lowerMessage.includes(status));
            if (statusQuery) {
                const filtered = this.appointmentsData.filter(a =>
                    a.Status.toLowerCase() === statusQuery
                );

                if (filtered.length === 0) return `You have no ${statusQuery} appointments.`;

                let response = `Your ${statusQuery} appointments:\n\n`;
                filtered.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 8. Check for time-based queries (morning/afternoon/evening)
            const timePeriods = {
                morning: { start: 6, end: 12 },
                afternoon: { start: 12, end: 17 },
                evening: { start: 17, end: 21 },
                night: { start: 21, end: 6 }
            };

            const periodQuery = Object.keys(timePeriods).find(period =>
                lowerMessage.includes(period)
            );

            if (periodQuery) {
                const { start, end } = timePeriods[periodQuery];
                const periodAppts = this.appointmentsData.filter(appt => {
                    const hours = new Date(appt.AppointmentTime).getHours();
                    return periodQuery === 'night' ?
                        hours >= start || hours < end :
                        hours >= start && hours < end;
                });

                if (periodAppts.length === 0) return `No ${periodQuery} appointments found.`;

                let response = `Your ${periodQuery} appointments:\n\n`;
                periodAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 9. Check for general appointment list
            if (lowerMessage.includes('list all') || lowerMessage.includes('all appointments')) {
                if (this.appointmentsData.length === 0) return "You have no appointments.";

                let response = "All your appointments:\n\n";
                this.appointmentsData
                    .sort((a, b) => new Date(a.AppointmentTime) - new Date(b.AppointmentTime))
                    .forEach(appt => response += formatAppointment(appt));
                return response;
            }

            return null;
        }


        getAppointmentCounts() {
            const counts = {
                total: this.appointmentsData.length,
                upcoming: this.appointmentsData.filter(a => a.Status === 'Upcoming').length,
                ongoing: this.appointmentsData.filter(a => a.Status === 'Ongoing').length,
                completed: this.appointmentsData.filter(a => a.Status === 'Completed').length,
                cancelled: this.appointmentsData.filter(a => a.Status === 'Cancelled').length
            };

            return `Here's your appointment summary:\n\n` +
                `📅 Total Appointments: ${counts.total}\n` +
                `⏳ Upcoming: ${counts.upcoming}\n` +
                `🔵 Ongoing: ${counts.ongoing}\n` +
                `✅ Completed: ${counts.completed}\n` +
                `❌ Cancelled: ${counts.cancelled}`;
        }

        getUpcomingAppointments() {
            const upcoming = this.appointmentsData
                .filter(a => a.Status === 'Upcoming')
                .sort((a, b) => new Date(a.AppointmentTime) - new Date(b.AppointmentTime))
                .slice(0, 5); // Limit to 5 upcoming

            if (upcoming.length === 0) {
                return "You have no upcoming appointments scheduled.";
            }

            let response = "Here are your upcoming appointments:\n\n";
            upcoming.forEach(appt => {
                const date = new Date(appt.AppointmentTime);
                response += `👤 ${appt.Name}\n` +
                    `📅 ${date.toLocaleDateString()}\n` +
                    `⏰ ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                    `✉️ ${appt.Email}\n\n`;
            });

            return response;
        }

        getAppointmentDetails(name) {
            const appointment = this.appointmentsData.find(a =>
                a.Name.toLowerCase().includes(name.toLowerCase())
            );

            if (!appointment) {
                return `I couldn't find an appointment for ${name}. Please check the name and try again.`;
            }

            const date = new Date(appointment.AppointmentTime);
            return `Here are the details for ${appointment.Name}:\n\n` +
                `📅 Date: ${date.toLocaleDateString()}\n` +
                `⏰ Time: ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                `📧 Email: ${appointment.Email}\n` +
                `📌 Status: ${appointment.Status}\n` +
                (appointment.CancellationReason ? `❌ Reason: ${appointment.CancellationReason}\n` : '');
        }

        getTodaysAppointments() {
            const today = new Date().toISOString().split('T')[0];
            const todaysAppts = this.appointmentsData.filter(appt => {
                const apptDate = new Date(appt.AppointmentTime).toISOString().split('T')[0];
                return apptDate === today && appt.Status !== 'Cancelled';
            });

            if (todaysAppts.length === 0) {
                return "You have no appointments scheduled for today.";
            }

            let response = `You have ${todaysAppts.length} appointment(s) today:\n\n`;
            todaysAppts.forEach(appt => {
                const time = new Date(appt.AppointmentTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                response += `⏰ ${time} - ${appt.Name} (${appt.Status})\n`;
            });

            return response;
        }

        addMessage(content, sender, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';

            if (isError) {
                contentDiv.className += ' error-message';
            }

            // Format message content (basic markdown support)
            contentDiv.innerHTML = this.formatMessage(content);
            messageDiv.appendChild(contentDiv);

            // Remove welcome message if it exists
            const welcomeMessage = this.chatMessages.querySelector('.welcome-message');
            if (welcomeMessage) {
                welcomeMessage.remove();
            }

            this.chatMessages.appendChild(messageDiv);
            this.scrollToBottom();

            // Store in conversation history
            this.conversationHistory.push({
                role: sender === 'user' ? 'user' : 'model',
                parts: [{ text: content }]
            });
        }

        formatMessage(text) {
            // Basic markdown formatting
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code style="background: #f1f1f1; padding: 2px 4px; border-radius: 3px;">$1</code>')
                .replace(/\n/g, '<br>');
        }

        showTyping() {
            this.typingIndicator.classList.add('active');
            this.scrollToBottom();
        }

        hideTyping() {
            this.typingIndicator.classList.remove('active');
        }

        scrollToBottom() {
            setTimeout(() => {
                this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            }, 100);
        }

        async sendToGemini(message) {
            // Prepare conversation context
            const contents = [
                {
                    role: 'user',
                    parts: [{ text: message }]
                }
            ];

            // Add conversation history (last 10 messages to manage context length)
            const recentHistory = this.conversationHistory.slice(-10);
            if (recentHistory.length > 0) {
                contents.unshift(...recentHistory);
            }

            // Add system message with appointment context
            const systemMessage = {
                role: 'user',
                parts: [{
                    text: `You are an AI assistant for an appointment management system. The user is a host managing appointments.
                    Current appointment stats: Total ${this.appointmentsData.length}, Upcoming: ${this.appointmentsData.filter(a => a.Status === 'Upcoming').length},
                    Ongoing: ${this.appointmentsData.filter(a => a.Status === 'Ongoing').length}. Today is ${new Date().toLocaleDateString()}.
                    You can help with appointment queries, scheduling, and general questions. Keep responses concise and helpful.`
                }]
            };
            contents.unshift(systemMessage);

            const requestBody = {
                contents: contents,
                generationConfig: {
                    temperature: 0.7,
                    topK: 40,
                    topP: 0.95,
                    maxOutputTokens: 1024,
                },
                safetySettings: [
                    {
                        category: "HARM_CATEGORY_HARASSMENT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_HATE_SPEECH",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_DANGEROUS_CONTENT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    }
                ]
            };

            const response = await fetch(`${this.apiUrl}?key=${this.apiKey}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`API Error: ${response.status} - ${errorData.error?.message || 'Unknown error'}`);
            }

            const data = await response.json();

            if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                return data.candidates[0].content.parts[0].text;
            } else {
                throw new Error('Unexpected API response format');
            }
        }
    }

    // Initialize the chatbot when the page loads
    document.addEventListener('DOMContentLoaded', () => {
        new GeminiChatBot();
    });


    // Add this to properly close the $(document).ready(function() {
    $(document).ready(function() {
        setInterval(function() {
            $.post('update_activity.php');
        }, 60000); // Update every 60 seconds
    });
    // SIDEBAR TOGGLE FUNCTIONALITY
    function updateTooltip() {
        const $toggle = $('.layout-menu-toggle');
        const isCollapsed = $('html').hasClass('layout-menu-collapsed');

        if (isCollapsed) {
            $toggle.attr('data-tooltip', 'Expand Menu');
            $toggle.attr('title', 'Expand Menu');
        } else {
            $toggle.attr('data-tooltip', 'Collapse Menu');
            $toggle.attr('title', 'Collapse Menu');
        }
    }

    $('.layout-menu-toggle').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $html = $('html');
        const $sidebar = $('#layout-menu');
        const $toggle = $(this);

        $toggle.css('pointer-events', 'none');
        $html.toggleClass('layout-menu-collapsed');
        const isCollapsed = $html.hasClass('layout-menu-collapsed');

        if (isCollapsed) {
            $sidebar.css({
                'width': '78px',
                'min-width': '78px',
                'max-width': '78px'
            });
        } else {
            $sidebar.css({
                'width': '260px',
                'min-width': '260px',
                'max-width': '260px'
            });
        }

        updateTooltip();
        localStorage.setItem('layoutMenuCollapsed', isCollapsed);

        setTimeout(() => {
            $toggle.css('pointer-events', 'auto');
        }, 300);
    });

    // Initialize sidebar state
    const isCollapsed = localStorage.getItem('layoutMenuCollapsed') === 'true';
    if (isCollapsed) {
        $('html').addClass('layout-menu-collapsed');
        $('#layout-menu').css({
            'width': '78px',
            'min-width': '78px',
            'max-width': '78px'
        });
    } else {
        $('#layout-menu').css({
            'width': '260px',
            'min-width': '260px',
            'max-width': '260px'
        });
    }

    updateTooltip();

    // Handle window resize
    $(window).resize(function() {
        const $sidebar = $('#layout-menu');
        const isCollapsed = $('html').hasClass('layout-menu-collapsed');

        if (isCollapsed) {
            $sidebar.css({
                'width': '78px',
                'min-width': '78px',
                'max-width': '78px'
            });
        } else {
            $sidebar.css({
                'width': '260px',
                'min-width': '260px',
                'max-width': '260px'
            });
        }
    });
</script>
</body>
</html>