<?php
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// For development on different ports, make cookie accessible to all ports
if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

require_once '../dbConfig.php';
require_once 'NotificationCreator.php';

global $conn;
global $unreadCount;
session_start();

// Debug: log session status
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    // Log activity for admins
    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}

// Fetch dashboard statistics
function getDashboardStats($conn) {
    $stats = [];

    // Total Users
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $result->fetch_assoc()['total'];

    // Active Users (last 30 days)
    $result = $conn->query("SELECT COUNT(*) as active FROM users WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users'] = $result->fetch_assoc()['active'];

    // New Users (last 30 days)
    $result = $conn->query("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['new_users'] = $result->fetch_assoc()['new_users'];

    // Total Tickets
    $result = $conn->query("SELECT COUNT(*) as total FROM Help_Desk");
    $stats['total_tickets'] = $result->fetch_assoc()['total'];

    // Open Tickets
    $result = $conn->query("SELECT COUNT(*) as open FROM Help_Desk WHERE Status = 'open'");
    $stats['open_tickets'] = $result->fetch_assoc()['open'];

    // Total Visitors
    $result = $conn->query("SELECT COUNT(*) as total FROM Visitors");
    $stats['total_visitors'] = $result->fetch_assoc()['total'];

    // Checked In Visitors
    $result = $conn->query("SELECT COUNT(DISTINCT v.VisitorID) as checked_in 
                           FROM Visitors v 
                           JOIN Visitor_Logs vl ON v.VisitorID = vl.VisitorID 
                           WHERE vl.CheckOutTime IS NULL");
    $stats['checked_in_visitors'] = $result->fetch_assoc()['checked_in'];


    $result = $conn->query("
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(DISTINCT DATE(AppointmentTime)) as days_with_appointments
    FROM Appointments
    WHERE AppointmentTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
    $appointment_stats = $result->fetch_assoc();
    $stats['average_appointments'] = $appointment_stats['days_with_appointments'] > 0
        ? round($appointment_stats['total_appointments'] / $appointment_stats['days_with_appointments'], 1)
        : 0;

    // Lost & Found Items
    $result = $conn->query("SELECT COUNT(*) as total FROM Lost_And_Found");
    $stats['total_items'] = $result->fetch_assoc()['total'];

    // Unclaimed Items
    $result = $conn->query("SELECT COUNT(*) as unclaimed FROM Lost_And_Found WHERE Status IN ('lost', 'found')");
    $stats['unclaimed_items'] = $result->fetch_assoc()['unclaimed'];

    return $stats;
}



// Get chart data
function getChartData($conn) {
    $data = [];

    // User registration trend (last 7 days)
    $result = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $data['user_trend'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['user_trend'][] = $row;
    }

    // Ticket status distribution
    $result = $conn->query("
        SELECT Status, COUNT(*) as count 
        FROM Help_Desk 
        GROUP BY Status
    ");
    $data['ticket_status'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['ticket_status'][] = $row;
    }


    // Add this to the getChartData function
    $result = $conn->query("
    SELECT 
        a.AppointmentID,
        a.AppointmentTime,
        a.Status,
        v.Name,
        v.Email
    FROM Appointments a
    JOIN Visitors v ON a.VisitorID = v.VisitorID
    ORDER BY a.AppointmentTime DESC
    LIMIT 5
");
    $data['recent_appointments'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_appointments'][] = $row;
    }
    // User roles distribution
    $result = $conn->query("
        SELECT Role, COUNT(*) as count 
        FROM users 
        GROUP BY Role
    ");
    $data['user_roles'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['user_roles'][] = $row;
    }

    // Monthly visitor trends (last 6 months)
    $result = $conn->query("
        SELECT 
            YEAR(CheckInTime) as year,
            MONTH(CheckInTime) as month,
            COUNT(*) as count
        FROM Visitor_Logs 
        WHERE CheckInTime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(CheckInTime), MONTH(CheckInTime)
        ORDER BY year, month
    ");
    $data['visitor_trend'] = [];
    while ($row = $result->fetch_assoc()) {
        $monthName = date('M', mktime(0, 0, 0, $row['month'], 1));
        $data['visitor_trend'][] = [
            'month' => $monthName,
            'count' => $row['count']
        ];
    }
    $unreadCount = 0;
    if (isset($_SESSION['userID'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $_SESSION['userID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $unreadData = $result->fetch_assoc();
        $unreadCount = $unreadData['unread_count'] ?? 0;
        $stmt->close();
    }

    // Recent activity
    $result = $conn->query("
        SELECT 
            u.Name,
            ual.activity,
            ual.activity_time
        FROM user_activity_log ual
        JOIN users u ON ual.user_id = u.UserID
        ORDER BY ual.activity_time DESC
        LIMIT 10
    ");
    $data['recent_activity'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_activity'][] = $row;
    }

    return $data;
}

$stats = getDashboardStats($conn);
$chartData = getChartData($conn);

// Additional stats from first version
$result = $conn->query("SELECT COUNT(*) as lost_items FROM Lost_And_Found WHERE Status IN ('lost', 'found')");
$stats['lost_items'] = $result->fetch_assoc()['lost_items'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class=" layout-menu-fixed" dir="ltr" data-skin="default">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Admin Dashboard</title>

    <!-- CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="notification-styles.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
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
        /* Add these to your existing styles */
        .layout-menu-fixed:not(.layout-menu-collapsed) .layout-menu {
            width: 260px !important;
        }

        .layout-menu-fixed.layout-menu-collapsed .layout-menu {
            width: 78px !important;
        }

        .layout-menu-fixed .layout-menu {
            position: fixed;
            height: 100%;
        }

        .layout-menu-fixed .layout-page {
            margin-left: 260px;
        }

        .layout-menu-fixed.layout-menu-collapsed .layout-page {
            margin-left: 78px;
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
            0% { box-shadow: 0 0 5px rgba(255, 255, 255, 0.3); }
            50% { box-shadow: 0 0 15px rgba(255, 255, 255, 0.5), 0 0 25px rgba(255, 255, 255, 0.3); }
            100% { box-shadow: 0 0 5px rgba(255, 255, 255, 0.3); }
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

        html, body {
            overflow-x: hidden !important;
            overflow-y: hidden !important;
            height: 100vh !important;
        }

        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .layout-content {
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }

        /* Analytics Cards Styles */
        .analytics-card {
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .analytics-card .avatar {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .analytics-card .avatar-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .analytics-card h4 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        /* Background label colors */
        .bg-label-primary { background-color: rgba(102, 126, 234, 0.1); color: #667eea; }
        .bg-label-success { background-color: rgba(67, 233, 123, 0.1); color: #43e97b; }
        .bg-label-warning { background-color: rgba(255, 171, 0, 0.1); color: #ffab00; }
        .bg-label-info { background-color: rgba(79, 172, 254, 0.1); color: #4facfe; }
        .bg-label-danger { background-color: rgba(255, 107, 107, 0.1); color: #ff6b6b; }

        /* Badge styles */
        .badge {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        /* Animation for numbers */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .analytics-card h4 {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .chart-container {
            position: relative;
            height: 400px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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

        .navbar-detached {
            box-shadow: 0 1px 20px 0 rgba(76,87,125,.1);
            background-color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }

        .card {
            border: none;
            box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px 0 rgba(67, 89, 113, 0.16);
        }

        /* Animation for stats */
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            opacity: 0;
            animation: countUp 1s ease-out forwards;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .quick-action-btn {
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
            height: 120px;
        }

        .quick-action-btn:hover {
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }

        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        /* Additional styles from first version */
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card .card-body {
            padding: 1.5rem;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }

        .stat-card .count {
            font-size: 2rem;
            font-weight: 600;
        }

        .stat-card .label {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .status-text {
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-online-text { color: #28a745; }
        .status-away-text { color: #ffc107; }
        .status-offline-text { color: #6c757d; }
        .status-inactive-text { color: #dc3545; }

        .filter-dropdown {
            width: 350px;
            padding: 1rem;
        }

        .filter-dropdown .form-group {
            margin-bottom: 1rem;
        }

        .filter-dropdown .btn-apply {
            width: 100%;
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

<body class="admin-page">
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'admin-sidebar.php'; ?>

        <div class="layout-content">
            <!-- Navbar -->
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="fas fa-bars"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2"> Dashboard</h4>
                        </div>
                    </div>

                    <div class="navbar-nav align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['name'] ?? 'Admin'; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="admin_settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                        <!-- Inside the navbar-nav align-items-center div -->
                        <ul class="navbar-nav">
                            <!-- Replace the current notification dropdown with this -->
                            <li class="nav-item me-3">
                                <button class="notification-trigger" id="notificationTrigger">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-badge" id="notificationBadge"
                                          style="display: <?= $unreadCount > 0 ? 'inline-flex' : 'none' ?>;">
                                        <?= $unreadCount > 0 ? $unreadCount : '' ?>
                                    </span>
                                </button>
                            </li>
                            </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end py-0" aria-labelledby="notificationDropdown">
                                    <div class="dropdown-menu-header">
                                        <div class="dropdown-header d-flex justify-content-between align-items-center py-3">
                                            <h5 class="text-body mb-0">Notifications</h5>
                                            <?php if ($unreadCount > 0): ?>
                                                <a href="javascript:void(0)" class="text-muted" id="markAllReadBtn"><small>Mark all read</small></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="notification-list" id="notificationList">
                                        <!-- Notifications will be loaded here via AJAX -->
                                        <div class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown-menu-footer">
                                        <a href="notifications.php" class="dropdown-item text-center text-primary">View all notifications</a>
                                        <a href="javascript:void(0)" id="refreshNotificationsBtn" class="dropdown-item text-center text-secondary">
                                            <i class="fas fa-sync-alt me-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid container-p-y">
                <!-- First Row of Stats Cards (from first version) -->
                <div class="row mb-4">
                    <!-- Total Users Card -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-primary rounded">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Total Users</small>
                                        <h4 class="mb-0"><?php echo $stats['total_users']; ?></h4>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="badge bg-label-primary p-1 rounded">
                                        <i class="fas fa-arrow-up"></i>
                                        <span class="ms-1"><?php echo round(($stats['active_users']/$stats['total_users'])*100); ?>%</span>
                                    </div>
                                    <small class="text-muted ms-2">Active Users</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Visitors Card -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-success rounded">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Active Visitors</small>
                                        <h4 class="mb-0"><?php echo $stats['checked_in_visitors']; ?></h4>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="badge bg-label-success p-1 rounded">
                                        <i class="fas fa-arrow-up"></i>
                                        <span class="ms-1"><?php echo $stats['total_visitors']; ?></span>
                                    </div>
                                    <small class="text-muted ms-2">Total Visitors</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Open Tickets Card -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-warning rounded">
                                            <i class="fas fa-ticket-alt"></i>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Open Tickets</small>
                                        <h4 class="mb-0"><?php echo $stats['open_tickets']; ?></h4>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="badge bg-label-warning p-1 rounded">
                                        <i class="fas fa-arrow-up"></i>
                                        <span class="ms-1"><?php echo $stats['total_tickets']; ?></span>
                                    </div>
                                    <small class="text-muted ms-2">Total Tickets</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Card -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-info rounded">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Avg Appointments</small>
                                        <h4 class="mb-0"><?php echo $stats['average_appointments']; ?>/day</h4>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="badge bg-label-info p-1 rounded">
                                        <i class="fas fa-clock"></i>
                                        <span class="ms-1">30d</span>
                                    </div>
                                    <small class="text-muted ms-2">Time Period</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions (from second version) -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="user_management.php" class="quick-action-btn">
                                            <i class="fas fa-users"></i>
                                            <span>Manage Users</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="audit_logs.php" class="quick-action-btn">
                                            <i class="fas fa-ticket-alt"></i>
                                            <span>View Logs</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="admin-reports.php" class="quick-action-btn">
                                            <i class="fas fa-user-friends"></i>
                                            <span>Visitor Reports</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="admin-reports.php" class="quick-action-btn">
                                            <i class="fas fa-search"></i>
                                            <span>Appointments Reports</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="admin-reports.php" class="quick-action-btn">
                                            <i class="fas fa-chart-bar"></i>
                                            <span>All Reports</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="admin_settings.php" class="quick-action-btn">
                                            <i class="fas fa-cog"></i>
                                            <span>Settings</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row (from second version) -->
                <div class="row mb-4">
                    <!-- User Registration Trend -->
                    <div class="col-lg-8 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">User Registration Trend</h5>
                                <small class="text-muted">Last 7 days</small>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Status Distribution -->
                    <div class="col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ticket Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ticketStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Charts Row (from second version) -->
                <div class="row mb-4">
                    <!-- User Roles Distribution -->
                    <div class="col-lg-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Roles Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userRolesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visitor Trends -->
                    <div class="col-lg-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Visitor Trends</h5>
                                <small class="text-muted">Last 6 months</small>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="visitorTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent System Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($chartData['recent_activity'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent activity found
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($chartData['recent_activity'] as $activity): ?>
                                        <div class="activity-item mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($activity['Name']) ?></strong>
                                                    <div class="text-muted small">
                                                        <?= htmlspecialchars($activity['activity']) ?>
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M j, H:i', strtotime($activity['activity_time'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

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
                                        <div class="appointment-item mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($appointment['Name']) ?></strong>
                                                    <div class="text-muted small">
                                                        <?= date('M j, Y h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                                    </div>
                                                    <span class="badge bg-<?=
                                                    match($appointment['Status']) {
                                                        'Upcoming' => 'primary',
                                                        'Ongoing' => 'info',
                                                        'Completed' => 'success',
                                                        'Cancelled' => 'danger',
                                                        default => 'secondary'
                                                    }
                                                    ?>">
                                        <?= $appointment['Status'] ?>
                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($appointment['Email']) ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="global-notification-system.js"></script>
<script>
    $(document).ready(function() {
        // Initialize charts
        initializeCharts();

        // Sidebar toggle functionality
        $('.layout-menu-toggle').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $html = $('html');
            const $sidebar = $('#layout-menu');
            const $toggle = $(this);

            $toggle.css('pointer-events', 'none');
            $html.toggleClass('layout-menu-collapsed');
            // Initialize sidebar state from localStorage
            const isCollapsed = localStorage.getItem('layoutMenuCollapsed') === 'true';

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

            localStorage.setItem('layoutMenuCollapsed', isCollapsed);

            setTimeout(() => {
                $toggle.css('pointer-events', 'auto');
            }, 300);
        });

        $('.layout-menu-toggle').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $html = $('html');
            const isCollapsed = $html.hasClass('layout-menu-collapsed');

            // Toggle the collapsed state
            $html.toggleClass('layout-menu-collapsed');

            // Update the menu and content widths
            if ($html.hasClass('layout-menu-collapsed')) {
                $('#layout-menu').css({
                    'width': '78px',
                    'min-width': '78px',
                    'max-width': '78px'
                });
                $('.layout-content').css({
                    'margin-left': '78px',
                    'width': 'calc(100% - 78px)'
                });
            } else {
                $('#layout-menu').css({
                    'width': '260px',
                    'min-width': '260px',
                    'max-width': '260px'
                });
                $('.layout-content').css({
                    'margin-left': '260px',
                    'width': 'calc(100% - 260px)'
                });
            }

            // Store the state in localStorage
            localStorage.setItem('layoutMenuCollapsed', $html.hasClass('layout-menu-collapsed'));
        });

    });

    function initializeCharts() {
        // User Registration Trend Chart
        const userTrendData = <?php echo json_encode($chartData['user_trend']); ?>;
        const userTrendCtx = document.getElementById('userTrendChart').getContext('2d');

        // Fill missing days with 0
        const last7Days = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            const existing = userTrendData.find(d => d.date === dateStr);
            last7Days.push({
                date: dateStr,
                count: existing ? existing.count : 0
            });
        }

        new Chart(userTrendCtx, {
            type: 'line',
            data: {
                labels: last7Days.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'New Users',
                    data: last7Days.map(d => d.count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
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

        // Ticket Status Chart
        const ticketStatusData = <?php echo json_encode($chartData['ticket_status']); ?>;
        const ticketStatusCtx = document.getElementById('ticketStatusChart').getContext('2d');

        new Chart(ticketStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ticketStatusData.map(d => d.Status.charAt(0).toUpperCase() + d.Status.slice(1)),
                datasets: [{
                    data: ticketStatusData.map(d => d.count),
                    backgroundColor: [
                        '#ff6b6b',
                        '#4ecdc4',
                        '#45b7d1',
                        '#96ceb4',
                        '#ffeaa7'
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

        // User Roles Chart
        const userRolesData = <?php echo json_encode($chartData['user_roles']); ?>;
        const userRolesCtx = document.getElementById('userRolesChart').getContext('2d');

        new Chart(userRolesCtx, {
            type: 'bar',
            data: {
                labels: userRolesData.map(d => d.Role || 'Unassigned'),
                datasets: [{
                    label: 'Users',
                    data: userRolesData.map(d => d.count),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(255, 107, 107, 0.8)',
                        'rgba(78, 205, 196, 0.8)',
                        'rgba(69, 183, 209, 0.8)'
                    ],
                    borderColor: [
                        '#667eea',
                        '#764ba2',
                        '#ff6b6b',
                        '#4ecdc4',
                        '#45b7d1'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
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

        // Visitor Trend Chart
        const visitorTrendData = <?php echo json_encode($chartData['visitor_trend']); ?>;
        const visitorTrendCtx = document.getElementById('visitorTrendChart').getContext('2d');

        new Chart(visitorTrendCtx, {
            type: 'line',
            data: {
                labels: visitorTrendData.map(d => d.month),
                datasets: [{
                    label: 'Visitors',
                    data: visitorTrendData.map(d => d.count),
                    borderColor: '#4facfe',
                    backgroundColor: 'rgba(79, 172, 254, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4facfe',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
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

    // Animate stat numbers
    function animateStats() {
        $('.stat-number').each(function() {
            const $this = $(this);
            const target = parseInt($this.text().replace(/,/g, ''));
            const increment = target / 50;
            let current = 0;

            const timer = setInterval(function() {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                $this.text(Math.floor(current).toLocaleString());
            }, 30);
        });
    }

    // Start animation when page loads
    $(document).ready(function() {
        setTimeout(animateStats, 500);
    });

    // Refresh dashboard data every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);

    // Add loading states for quick actions
    $('.quick-action-btn').on('click', function() {
        const $btn = $(this);
        const originalContent = $btn.html();

        $btn.html('<i class="fas fa-spinner fa-spin"></i><span>Loading...</span>');

        // Reset after 2 seconds (in case the page doesn't redirect)
        setTimeout(function() {
            $btn.html(originalContent);
        }, 2000);
    });

    // Initialize tooltips with jQuery
    $(function () {
        $('[data-bs-toggle="tooltip"]').each(function() {
            new bootstrap.Tooltip(this);
        });
    });

    // Make cards clickable
    $('.stats-card.users').click(function() {
        window.location.href = 'user_management.php';
    });

    $('.stats-card.tickets').click(function() {
        window.location.href = 'help_desk.php';
    });

    $('.stats-card.visitors').click(function() {
        window.location.href = 'manage_visitors.php';
    });

    $('.stats-card.items').click(function() {
        window.location.href = 'lost_and_found.php';
    });

    // Add cursor pointer to clickable cards
    $('.stats-card').css('cursor', 'pointer');

    // Update time in the dashboard every minute
    function updateTime() {
        const now = new Date();
        const timeElement = document.querySelector('.stat-card.bg-dark .count');
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
    }

    /*
    // Enhanced notification functions
    let lastUnreadCount = 0;
    function fetchNotifications() {
        console.log("Fetching notifications...");

        $.ajax({
            url: 'fetch-notifications.php',
            type: 'GET',
            dataType: 'json',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                console.log("Notification response:", response);
                if (response.success) {
                    updateNotificationUI(response.notifications, response.unread_count);

                    // Play sound if there are new notifications
                    if (response.unread_count > 0 && response.unread_count > lastUnreadCount) {
                        playNotificationSound();
                    }

                    lastUnreadCount = response.unread_count;
                } else {
                    console.error('Notification error:', response.error);
                    // Handle unauthorized access
                    if (response.error && response.error.includes('Unauthorized')) {
                        $('#notificationList').html('<div class="text-center text-muted py-4">Please login to view notifications</div>');
                        $('#notificationBadge').hide();
                    } else {
                        $('#notificationList').html('<div class="text-center text-muted py-4">Error loading notifications</div>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch notifications:', error, xhr);

                // Handle 401 Unauthorized specifically
                if (xhr.status === 401) {
                    $('#notificationList').html('<div class="text-center text-muted py-4">Session expired. Please refresh the page.</div>');
                    $('#notificationBadge').hide();
                } else {
                    $('#notificationList').html('<div class="text-center text-muted py-4">Error loading notifications</div>');
                }
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
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    fetchNotifications(); // Refresh notifications
                } else {
                    console.error('Failed to mark notification as read:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to mark notification as read:', error);
                if (xhr.status === 401) {
                    alert('Your session has expired. Please refresh the page.');
                }
            }
        });
    }

    function markAllNotificationsAsRead() {
        $.ajax({
            url: 'mark-all-notifications-read.php',
            type: 'POST',
            dataType: 'json',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                console.log("Mark all read response:", response);
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
                if (xhr.status === 401) {
                    alert('Your session has expired. Please refresh the page.');
                }
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

    function playNotificationSound() {
        // Create a simple notification sound
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (e) {
            console.log('Audio context not supported:', e);
        }
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
    });

    // Poll every 30 seconds
    $(document).ready(function() {
        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    });
    // Refresh notifications manually
    $('#refreshNotificationsBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fetchNotifications();

        // Show loading state
        $('#notificationList').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    });
    */
    setInterval(updateTime, 60000);
</script>
<?php include 'notification-panel.html'; ?>
</body>
</html>