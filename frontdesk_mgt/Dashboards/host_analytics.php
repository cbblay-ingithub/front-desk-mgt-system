<?php

require_once '../dbConfig.php';
require_once 'NotificationCreator.php';


global $conn;
global $unreadCount;
session_start();

if (!isset($_SESSION['userID'])) {
    header("Location: ../Auth.html");
    exit;
}

if (isset($_SESSION['userID'])) {
    $hostID = $_SESSION['userID'];

    // Update last activity
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $hostID);
    $stmt->execute();

    // Log activity
    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $hostID, $activity);
    $stmt->execute();
}

// Fetch host-specific statistics
function getHostStats($conn, $hostID) {
    $stats = [];

    // Host's Appointments
    $result = $conn->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN Status = 'Upcoming' THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN Status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing,
            SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM Appointments 
        WHERE HostID = ?
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $stats['appointments'] = $result->get_result()->fetch_assoc();

    // Host's Tickets
    $result = $conn->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN Status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN Status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN Status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN Status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN Status = 'closed' THEN 1 ELSE 0 END) as closed
        FROM Help_Desk 
        WHERE AssignedTo = ?
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $stats['tickets'] = $result->get_result()->fetch_assoc();

    // Weekly appointment trends
    $result = $conn->prepare("
        SELECT 
            DAYNAME(AppointmentTime) as day,
            COUNT(*) as count
        FROM Appointments
        WHERE HostID = ? 
        AND AppointmentTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(AppointmentTime)
        ORDER BY MIN(AppointmentTime)
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $stats['weekly_appointments'] = $result->get_result()->fetch_all(MYSQLI_ASSOC);

    return $stats;
}

// Get host-specific chart data
function getHostChartData($conn, $hostID) {
    $data = [];

    // Recent Appointments
    $result = $conn->prepare("
        SELECT 
            a.AppointmentID,
            a.AppointmentTime,
            a.Status,
            v.Name,
            v.Email
        FROM Appointments a
        JOIN Visitors v ON a.VisitorID = v.VisitorID
        WHERE a.HostID = ?
        ORDER BY a.AppointmentTime DESC
        LIMIT 5
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $data['recent_appointments'] = $result->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent Tickets
    $result = $conn->prepare("
        SELECT 
            h.TicketID,
            h.Description,
            h.Status,
            h.CreatedDate,
            u.Name as CreatedByName,
            tc.CategoryName
        FROM Help_Desk h
        JOIN users u ON h.CreatedBy = u.UserID
        LEFT JOIN TicketCategories tc ON h.CategoryID = tc.CategoryID
        WHERE h.AssignedTo = ?
        ORDER BY h.CreatedDate DESC
        LIMIT 5
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $data['recent_tickets'] = $result->get_result()->fetch_all(MYSQLI_ASSOC);

    // Appointment status distribution
    $result = $conn->prepare("
        SELECT Status, COUNT(*) as count 
        FROM Appointments
        WHERE HostID = ?
        GROUP BY Status
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $data['appointment_status'] = $result->get_result()->fetch_all(MYSQLI_ASSOC);

    // Ticket status distribution
    $result = $conn->prepare("
        SELECT Status, COUNT(*) as count 
        FROM Help_Desk
        WHERE AssignedTo = ?
        GROUP BY Status
    ");
    $result->bind_param("i", $hostID);
    $result->execute();
    $data['ticket_status'] = $result->get_result()->fetch_all(MYSQLI_ASSOC);

    return $data;
}

$hostID = $_SESSION['userID'] ?? null;
$stats = $hostID ? getHostStats($conn, $hostID) : [];
$chartData = $hostID ? getHostChartData($conn, $hostID) : [];

$conn->close();
?>

<!DOCTYPE html>
<html
    lang="en"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="../../Sneat/assets/"
    data-template="vertical-menu-template">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Host Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <!-- Sneat CSS -->
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notification-styles.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Custom styles for dashboard cards */
        /* Replace the existing stat-card styles with these */
        .stat-card {
            border-radius: 0.5rem;
            box-shadow: none;
            transition: all 0.3s ease;
            height: 100%;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05);
        }

        .stat-card .card-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 1.75rem;
            opacity: 0.2;
            transition: all 0.3s ease;
        }

        .stat-card:hover .card-icon {
            opacity: 0.3;
            transform: scale(1.1);
        }

        .stat-card .count {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--bs-heading-color);
        }

        .stat-card .label {
            font-size: 0.875rem;
            color: var(--bs-secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-card .trend {
            display: flex;
            align-items: center;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .stat-card .trend i {
            font-size: 1rem;
            margin-right: 0.25rem;
        }

        .trend-up {
            color: #28a745;
        }

        .trend-down {
            color: #dc3545;
        }

        /* Card color variants */
        .card-primary {
            background-color: rgba(105, 108, 255, 0.1);
            border-color: rgba(105, 108, 255, 0.2);
        }

        .card-primary .card-icon {
            color: #696cff;
        }

        .card-success {
            background-color: rgba(40, 199, 111, 0.1);
            border-color: rgba(40, 199, 111, 0.2);
        }

        .card-success .card-icon {
            color: #28c76f;
        }

        .card-info {
            background-color: rgba(0, 207, 232, 0.1);
            border-color: rgba(0, 207, 232, 0.2);
        }

        .card-info .card-icon {
            color: #00cfe8;
        }

        .card-warning {
            background-color: rgba(255, 171, 0, 0.1);
            border-color: rgba(255, 171, 0, 0.2);
        }

        .card-warning .card-icon {
            color: #ffab00;
        }

        .card-danger {
            background-color: rgba(234, 84, 85, 0.1);
            border-color: rgba(234, 84, 85, 0.2);
        }

        .card-danger .card-icon {
            color: #ea5455;
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }

        .activity-item {
            padding: 12px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .badge-status {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        .appointment-badge-upcoming { background-color: #007bff; }
        .appointment-badge-ongoing { background-color: #17a2b8; }
        .appointment-badge-completed { background-color: #28a745; }
        .appointment-badge-cancelled { background-color: #dc3545; }

        .ticket-badge-open { background-color: #6c757d; }
        .ticket-badge-in-progress { background-color: #17a2b8; }
        .ticket-badge-pending { background-color: #ffc107; color: #212529; }
        .ticket-badge-resolved { background-color: #28a745; }
        .ticket-badge-closed { background-color: #343a40; }

        .recent-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .stat-card.appointments { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.upcoming { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.ongoing { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
        .stat-card.completed { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.tickets { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

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
        #layout-navbar {
            position: sticky;
            top: 0;
            z-index: 999; /* Ensure it stays above other content */
            background-color: var(--bs-body-bg); /* Match your theme background */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Optional: adds subtle shadow */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .layout-menu-collapsed #layout-menu {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
            flex: 0 0 78px !important;
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

        .layout-menu-toggle {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 6px !important;
            padding: 8px !important;
            color: #fff !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
        }

        .layout-menu-toggle i {
            font-size: 16px !important;
            line-height: 1 !important;
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
            z-index: 1002 !important;
        }

        .layout-menu-collapsed #layout-menu .layout-menu-toggle {
            animation: pulse-glow 2s infinite !important;
        }

        @keyframes pulse-glow {
            0% {
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
            }
            50% {
                box-shadow: 0 0 15px rgba(255, 255, 255, 0.5), 0 0 25px rgba(255, 255, 255, 0.3);
            }
            100% {
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
            }
        }
        /* Notification styles */
        .notification-unread {
            background-color: rgba(0, 123, 255, 0.05) !important;
        }

        .notification-unread:hover {
            background-color: rgba(0, 123, 255, 0.1) !important;
        }

        .badge.dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            padding: 0;
            margin-left: 5px;
        }

        .dropdown-menu-header {
            border-bottom: 1px solid #dee2e6;
        }

        .dropdown-menu-footer {
            border-top: 1px solid #dee2e6;
        }

        .avatar-initial {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 600;
        }

        /* Notification trigger styling */
        .notification-trigger {
            position: relative;
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .notification-trigger:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #495057;
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6875rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'host-sidebar.php'; ?>

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
                            <h4 class="mb-0 fw-bold ms-2">Host Dashboard</h4>
                        </div>
                    </div>

                    <!-- User Dropdown -->
                    <div class="navbar-nav align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['name'] ?? 'Host'; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                    <ul class="navbar-nav">
                        <!-- Notification dropdown -->
                        <li class="nav-item me-3">
                            <button class="notification-trigger" id="notificationTrigger">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge" id="notificationBadge"
                                      style="display: <?= $unreadCount > 0 ? 'inline-flex' : 'none' ?>;">
                                    <?= $unreadCount > 0 ? $unreadCount : '' ?>
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid container-p-y">
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <!-- Total Appointments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card card-primary">
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="count"><?= $stats['appointments']['total_appointments'] ?? 0 ?></div>
                            <div class="label">Total Appointments</div>
                            <div class="trend trend-up">
                                <i class="fas fa-chevron-up"></i>
                                <span>12.8%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Appointments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card card-info">
                            <div class="card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="count"><?= $stats['appointments']['upcoming'] ?? 0 ?></div>
                            <div class="label">Upcoming</div>
                            <div class="trend trend-up">
                                <i class="fas fa-chevron-up"></i>
                                <span>8.2%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Ongoing Appointments Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card card-warning">
                            <div class="card-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="count"><?= $stats['appointments']['ongoing'] ?? 0 ?></div>
                            <div class="label">Ongoing</div>
                            <div class="trend trend-down">
                                <i class="fas fa-chevron-down"></i>
                                <span>3.1%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tickets Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card card-danger">
                            <div class="card-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="count"><?= $stats['tickets']['total_tickets'] ?? 0 ?></div>
                            <div class="label">Assigned Tickets</div>
                            <div class="trend trend-up">
                                <i class="fas fa-chevron-up"></i>
                                <span>5.7%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <!-- Appointment Status Chart -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Appointment Status</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="appointmentStatusDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="appointmentStatusDropdown">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Refresh</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Export</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="appointmentStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Status Chart -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Ticket Status</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="ticketStatusDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="ticketStatusDropdown">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Refresh</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Export</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="ticketStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weekly Appointments Chart -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Weekly Appointments</h5>
                                <small class="text-muted">Last 7 days</small>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="weeklyAppointmentsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Items Row -->
                <div class="row">
                    <!-- Recent Appointments -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Appointments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($chartData['recent_appointments'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times me-2"></i>
                                        No recent appointments found
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($chartData['recent_appointments'] as $appointment): ?>
                                        <div class="recent-item">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong><?= htmlspecialchars($appointment['Name']) ?></strong>
                                                <span class="badge badge-status appointment-badge-<?= strtolower($appointment['Status']) ?>">
                                                    <?= $appointment['Status'] ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?= date('M j, Y h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                                </small>
                                                <small class="text-muted text-truncate" style="max-width: 150px;">
                                                    <?= htmlspecialchars($appointment['Email']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tickets -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Tickets</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($chartData['recent_tickets'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-ticket-alt me-2"></i>
                                        No recent tickets found
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($chartData['recent_tickets'] as $ticket): ?>
                                        <div class="recent-item">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars($ticket['Description']) ?>
                                                </strong>
                                                <span class="badge badge-status ticket-badge-<?= str_replace('-', '_', strtolower($ticket['Status'])) ?>">
                                                    <?= ucfirst(str_replace('-', ' ', $ticket['Status'])) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?= date('M j, Y', strtotime($ticket['CreatedDate'])) ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($ticket['CreatedByName']) ?>
                                                    <?= $ticket['CategoryName'] ? ' • ' . htmlspecialchars($ticket['CategoryName']) : '' ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>


<script>
    $(document).ready(function() {
        // Initialize charts
        initializeCharts();

        // Update time every minute
        setInterval(updateTime, 60000);

        // Sidebar functionality - consolidated and fixed
        initializeSidebar();

        // Refresh dashboard data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    });

    function initializeSidebar() {
        // Remove any existing event handlers to prevent duplicates
        $('#sidebarToggle, .layout-menu-toggle').off('click');

        // Restore sidebar state from localStorage
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        applySidebarState(isCollapsed);

        // Handle sidebar toggle click
        $(document).on('click', '#sidebarToggle, .layout-menu-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Prevent multiple rapid clicks
            if ($(this).hasClass('toggling')) return;
            $(this).addClass('toggling');

            const $html = $('html');
            const $sidebar = $('#layout-menu');
            const $toggleIcon = $('#toggleIcon');

            // Toggle collapsed state
            $html.toggleClass('layout-menu-collapsed');
            const newCollapsedState = $html.hasClass('layout-menu-collapsed');

            // Apply the new state
            applySidebarState(newCollapsedState);

            // Store state in localStorage
            localStorage.setItem('sidebarCollapsed', newCollapsedState);

            // Re-enable clicking after animation
            setTimeout(() => {
                $(this).removeClass('toggling');
            }, 300);
        });

        // Handle keyboard navigation
        $(document).on('keydown', '#sidebarToggle, .layout-menu-toggle', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        // Handle tooltips for collapsed menu items
        $(document).on('mouseenter', '.layout-menu-collapsed .menu-link', function() {
            if ($('html').hasClass('layout-menu-collapsed')) {
                const tooltipText = $(this).find('.menu-text').text() || $(this).data('tooltip');
                if (tooltipText) {
                    $(this).attr('title', tooltipText);
                }
            }
        }).on('mouseleave', '.layout-menu-collapsed .menu-link', function() {
            $(this).removeAttr('title');
        });

        // Handle window resize
        $(window).on('resize', function() {
            const isCollapsed = $('html').hasClass('layout-menu-collapsed');
            applySidebarState(isCollapsed);
        });
    }

    function applySidebarState(isCollapsed) {
        const $html = $('html');
        const $sidebar = $('#layout-menu');
        const $toggleIcon = $('#toggleIcon');

        if (isCollapsed) {
            $html.addClass('layout-menu-collapsed');
            $sidebar.css({
                'width': '78px',
                'min-width': '78px',
                'max-width': '78px'
            });
            // Update toggle icon if it exists
            if ($toggleIcon.length) {
                $toggleIcon.removeClass('bx-chevron-left').addClass('bx-chevron-right');
            }
        } else {
            $html.removeClass('layout-menu-collapsed');
            $sidebar.css({
                'width': '260px',
                'min-width': '260px',
                'max-width': '260px'
            });
            // Update toggle icon if it exists
            if ($toggleIcon.length) {
                $toggleIcon.removeClass('bx-chevron-right').addClass('bx-chevron-left');
            }
        }

        // Add body class for animations
        $('body').addClass('sidebar-toggling');
        setTimeout(() => {
            $('body').removeClass('sidebar-toggling');
        }, 300);
    }

    function initializeCharts() {
        // Appointment Status Chart
        const appointmentStatusData = <?php echo json_encode($chartData['appointment_status'] ?? []); ?>;
        const appointmentStatusCtx = document.getElementById('appointmentStatusChart').getContext('2d');

        new Chart(appointmentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: appointmentStatusData.map(d => d.Status),
                datasets: [{
                    data: appointmentStatusData.map(d => d.count),
                    backgroundColor: [
                        '#007bff', // Upcoming
                        '#17a2b8', // Ongoing
                        '#28a745', // Completed
                        '#dc3545', // Cancelled
                        '#6c757d'  // Overdue
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Ticket Status Chart
        const ticketStatusData = <?php echo json_encode($chartData['ticket_status'] ?? []); ?>;
        const ticketStatusCtx = document.getElementById('ticketStatusChart').getContext('2d');

        new Chart(ticketStatusCtx, {
            type: 'pie',
            data: {
                labels: ticketStatusData.map(d => d.Status.charAt(0).toUpperCase() + d.Status.slice(1)),
                datasets: [{
                    data: ticketStatusData.map(d => d.count),
                    backgroundColor: [
                        '#6c757d', // Open
                        '#17a2b8', // In-progress
                        '#ffc107', // Pending
                        '#28a745', // Resolved
                        '#343a40'  // Closed
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Weekly Appointments Chart
        const weeklyAppointmentsData = <?php echo json_encode($stats['weekly_appointments'] ?? []); ?>;
        const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Fill in missing days with 0 counts
        const filledData = daysOfWeek.map(day => {
            const found = weeklyAppointmentsData.find(d => d.day === day);
            return found ? found : {day: day, count: 0};
        });

        const weeklyAppointmentsCtx = document.getElementById('weeklyAppointmentsChart').getContext('2d');

        new Chart(weeklyAppointmentsCtx, {
            type: 'bar',
            data: {
                labels: filledData.map(d => d.day.substring(0, 3)),
                datasets: [{
                    label: 'Appointments',
                    data: filledData.map(d => d.count),
                    backgroundColor: '#667eea',
                    borderColor: '#4a5bd1',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    function updateTime() {
        const now = new Date();
        const timeElement = document.querySelector('.stat-card.upcoming .count');
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
    }
    // Notification functions
    let lastUnreadCount = 0;

    function fetchNotifications() {
        $.ajax({
            url: 'fetch-notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateNotificationUI(response.notifications, response.unread_count);

                    lastUnreadCount = response.unread_count;
                } else {
                    console.error('Notification error:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch notifications:', error);
            }
        });
    }

    function updateNotificationUI(notifications, unreadCount) {
        const $notificationList = $('#notificationList');
        const $badge = $('#notificationBadge');
        const $markAllReadBtn = $('#markAllReadBtn');

        // Update badge
        if (unreadCount > 0) {
            $badge.text(unreadCount).show();
            if ($markAllReadBtn.length) $markAllReadBtn.show();
        } else {
            $badge.hide();
            if ($markAllReadBtn.length) $markAllReadBtn.hide();
        }

        // Update notification list
        if (!notifications || notifications.length === 0) {
            $notificationList.html('<div class="text-center text-muted py-4">No notifications</div>');
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const isUnread = !notif.is_read;
            const timeAgo = formatTimeAgo(notif.created_at);

            html += `
            <a href="javascript:void(0)" class="dropdown-item py-3 ${isUnread ? 'notification-unread' : ''}"
                 data-id="${notif.id}" data-entity-type="${notif.related_entity_type}"
                 data-entity-id="${notif.related_entity_id}">
                <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar">
                            <span class="avatar-initial rounded-circle bg-label-primary">
                                <i class="fas fa-bell"></i>
                            </span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${escapeHtml(notif.title)}</h6>
                        <p class="mb-0">${escapeHtml(notif.message)}</p>
                        <small class="text-muted">${timeAgo}</small>
                    </div>
                    ${isUnread ? '<div class="flex-shrink-0"><span class="badge dot bg-danger"></span></div>' : ''}
                </div>
            </a>
            <hr class="my-1">
        `;
        });

        $notificationList.html(html);

        // Add click handlers
        $notificationList.find('.dropdown-item').on('click', function() {
            const notifId = $(this).data('id');
            markNotificationAsRead(notifId);
            handleNotificationAction($(this));
        });
    }

    function markNotificationAsRead(notifId) {
        $.ajax({
            url: 'mark-notification-read.php',
            type: 'POST',
            data: { notification_id: notifId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    fetchNotifications(); // Refresh notifications
                } else {
                    console.error('Failed to mark notification as read:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to mark notification as read:', error);
            }
        });
    }

    function markAllNotificationsAsRead() {
        $.ajax({
            url: 'mark-all-notifications-read.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    fetchNotifications(); // Refresh notifications
                    $('#notificationBadge').hide();
                    $('#markAllReadBtn').hide();
                } else {
                    console.error('Failed to mark all notifications as read:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to mark all notifications as read:', error);
            }
        });
    }

    function handleNotificationAction($element) {
        const entityType = $element.data('entity-type');
        const entityId = $element.data('entity-id');

        switch(entityType) {
            case 'user':
                window.location.href = `user_management.php?user_id=${entityId}`;
                break;
            case 'ticket':
                window.location.href = `help_desk.php?ticket_id=${entityId}`;
                break;
            // Add more cases as needed
            default:
                // Do nothing for unknown types
                break;
        }
    }

    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        return `${Math.floor(diffInSeconds / 86400)}d ago`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    // Initialize notification system
    $(document).ready(function() {
        // Fetch notifications on page load
        fetchNotifications();

        // Set up periodic fetching
        setInterval(fetchNotifications, 30000); // Every 30 seconds

        // Mark all as read button
        $('#markAllReadBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markAllNotificationsAsRead();
        });

        // Handle dropdown show event to refresh notifications
        $('#notificationDropdown').on('show.bs.dropdown', function() {
            fetchNotifications();
        });

        // Refresh notifications manually
        $('#refreshNotificationsBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fetchNotifications();

            // Show loading state
            $('#notificationList').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        });
    });
</script>
<script src="global-notification-system.js"></script>
<?php include 'notification-panel.html'; ?>
</body>
</html>