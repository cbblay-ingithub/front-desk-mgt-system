<?php
session_start();
require_once '../dbConfig.php';
require_once 'report_functions.php';
global $conn;

// Update user activity (from user_management.php)
if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    // Only log activity for admins
    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}

// Get filter parameters
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$activeTab = $_GET['tab'] ?? 'host';

// Fetch report data
$teamReports = [];
$users = [];
$individualReports = [];

if ($activeTab === 'host') {
    $teamReports = getHostReports($startDate, $endDate);
    $users = getUsersByRole('Host');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualHostMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($activeTab === 'support') {
    $teamReports = getSupportReports($startDate, $endDate);
    $users = getUsersByRole('Support Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualSupportMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($activeTab === 'frontdesk') {
    $teamReports = getFrontDeskReports($startDate, $endDate);
    $users = getUsersByRole('Front Desk Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualFrontDeskMetrics($user['UserID'], $startDate, $endDate);
    }
}

// Close connection after all DB operations
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Reports -Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/pages/card-analytics.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.css" />
    <link rel="stylesheet" href="notification-styles.css">
    <style>
        /* Sidebar width fixes (from user_management.php) */
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

        /* Original report styles */
        .report-card {
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .report-header {
            background-color: #0d6efd;
            color: white;
            padding: 1rem;
            border-radius: 8px 8px 0 0;
        }

        .metric-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .metric-value {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .nav-tabs .nav-link {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border: none;
            color: #495057;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: white;
            border-bottom: 3px solid #0d6efd;
        }

        .tab-content {
            background-color: white;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        /* Add this to your existing <style> section */

        /* Chart container improvements */
        .chart-container {
            position: relative;
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #eceef1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Loading states */
        .chart-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            color: #a1acb8;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #696cff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Chart specific styling */
        #ticketStatusChart, #categoryBreakdownChart {
            min-height: 350px;
        }

        /* Alert styling within charts */
        .chart-container .alert {
            margin: 0;
            border: none;
            background: transparent;
        }

        .chart-container .alert-info {
            color: #0f5132;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        .chart-container .alert-warning {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }

        .chart-container .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .chart-container {
                padding: 1rem;
            }

            #ticketStatusChart, #categoryBreakdownChart {
                min-height: 250px;
            }
        }

        /* Navbar styles (from user_management.php) */
        .navbar-detached {
            box-shadow: 0 1px 20px 0 rgba(76,87,125,.1);
            background-color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }

        /* Ensure body and html don't have extra scrollbars */
        html, body {
            overflow-x: hidden !important;
            overflow-y: hidden !important;
            height: 100vh !important;
        }

        /* Content padding */
        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .layout-content {
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }
    </style>
</head>
<body class="admin-page">
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'admin-sidebar.php'; ?>
        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="fas fa-bars"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!-- Page Title -->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2">Reports</h4>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content area -->
            <div class="container-fluid container-p-y">

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4" id="reportTabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'host' ? 'active' : '' ?>"
                           href="?<?= http_build_query(['tab' => 'host', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                            Hosts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'support' ? 'active' : '' ?>"
                           href="?<?= http_build_query(['tab' => 'support', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                            Support Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'frontdesk' ? 'active' : '' ?>"
                           href="?<?= http_build_query(['tab' => 'frontdesk', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                            Front Desk
                        </a>
                    </li>
                </ul>

                <!-- Date Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="tab" value="<?= $activeTab ?>">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Apply Filter</button>
                                <?php if ($startDate || $endDate) : ?>
                                    <a href="?tab=<?= $activeTab ?>" class="btn btn-outline-secondary ms-2">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="reportContent">
                    <!-- Host Reports Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'host' ? 'show active' : '' ?>" id="hostTab">
                        <?php if ($activeTab === 'host') : ?>
                            <!-- Team Metrics -->
                            <div class="row mb-4">
                                <h5 class="card-title m-0 me-2">Hosts Overview</h5>
                                <div class="col-md-6 col-lg-3 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="badge bg-label-primary p-2 rounded mb-3">
                                                        <i class="bx bx-calendar-check bx-sm"></i>
                                                    </span>
                                                    <h5 class="card-title mb-1">Total Appointments</h5>
                                                    <h2 class="mb-0"><?= $teamReports['appointment_metrics']['total_appointments'] ?? 0 ?></h2>
                                                </div>
                                                <div class="avatar flex-shrink-0">

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-md-6 col-lg-3 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                        <span class="badge bg-label-success p-2 rounded mb-3">
                                                            <i class="bx bx-check-circle bx-sm"></i>
                                                        </span>
                                                    <h5 class="card-title mb-1">Completed</h5>
                                                    <h2 class="mb-0"><?= $teamReports['appointment_metrics']['completed'] ?? 0 ?></h2>
                                                </div>
                                                <div class="avatar flex-shrink-0">

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                <span class="badge bg-label-danger p-2 rounded mb-3">
                                    <i class="bx bx-x-circle bx-sm"></i>
                                </span>
                                                    <h5 class="card-title mb-1">Cancelled</h5>
                                                    <h2 class="mb-0"><?= $teamReports['appointment_metrics']['cancelled'] ?? 0 ?></h2>
                                                </div>
                                                <div class="avatar flex-shrink-0">

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                <span class="badge bg-label-warning p-2 rounded mb-3">
                                    <i class="bx bx-time bx-sm"></i>
                                </span>
                                                    <h5 class="card-title mb-1">Upcoming</h5>
                                                    <h2 class="mb-0"><?= $teamReports['appointment_metrics']['upcoming'] ?? 0 ?></h2>
                                                </div>
                                                <div class="avatar flex-shrink-0">

                                </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <form method="POST" action="generate_report.php" style="display: inline;">
                            <input type="hidden" name="report_type" value="host">
                            <input type="hidden" name="start_date" value="<?= $startDate ?>">
                            <input type="hidden" name="end_date" value="<?= $endDate ?>">
                            <button type="submit" class="btn btn-light btn-sm">
                                <i class="fas fa-file-pdf me-1"></i> Export PDF
                            </button>
                        </form>
                        <!-- Cancellation Reasons -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title m-0 me-2">Cancellation Reasons</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($teamReports['cancellation_reasons'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                            <tr>
                                                <th>Reason</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($teamReports['cancellation_reasons'] as $reason): ?>
                                                <tr>
                                                    <td><?= $reason['CancellationReason'] ?: 'No reason given' ?></td>
                                                    <td><?= $reason['count'] ?></td>
                                                    <td><?= round(($reason['count'] / $teamReports['appointment_metrics']['cancelled']) * 100, 2) ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">No cancellation data available</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Visitor Metrics -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title m-0 me-2">Visitor Metrics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($teamReports['visitor_metrics'])): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <h6 class="mb-1">New Visitors</h6>
                                                    <h3 class="mb-0"><?= $teamReports['visitor_metrics']['new_visitors'] ?? 0 ?></h3>
                                                </div>
                                                <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-user-plus bx-sm"></i>
                            </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <h6 class="mb-1">Returning Visitors</h6>
                                                    <h3 class="mb-0"><?= $teamReports['visitor_metrics']['returning_visitors'] ?? 0 ?></h3>
                                                </div>
                                                <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-user-check bx-sm"></i>
                            </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 15px;">
                                        <?php
                                        $totalVisitors = ($teamReports['visitor_metrics']['new_visitors'] ?? 0) + ($teamReports['visitor_metrics']['returning_visitors'] ?? 0);
                                        $newPercent = $totalVisitors > 0 ? ($teamReports['visitor_metrics']['new_visitors'] / $totalVisitors) * 100 : 0;
                                        $returningPercent = $totalVisitors > 0 ? ($teamReports['visitor_metrics']['returning_visitors'] / $totalVisitors) * 100 : 0;
                                        ?>
                                        <div class="progress-bar bg-success" style="width: <?= $newPercent ?>%"></div>
                                        <div class="progress-bar bg-primary" style="width: <?= $returningPercent ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">No visitor data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between">
                                            <h5 class="card-title m-0 me-2">Completion Rate</h5>
                                            <div class="dropdown">
                                                <button class="btn p-0" type="button" id="completionRate" data-bs-toggle="dropdown">
                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                </button>
                                                <!--
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="javascript:void(0);">Last 7 Days</a>
                                                    <a class="dropdown-item" href="javascript:void(0);">Last 30 Days</a>
                                                    <a class="dropdown-item" href="javascript:void(0);">Last 90 Days</a>
                                                </div>
                                                -->
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-3">
                                                <div class="d-flex flex-column">
                                                    <h5 class="mb-0"><?= $completionRate = "";
                                                        ?>%</h5>
                                                    <small>Current Rate</small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-label-success">+2.15%</span>
                                                </div>
                                            </div>
                                            <div id="completionRateGauge"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Individual Performance Cards -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title m-0 me-2">Individual Host Performance</h5>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="individualPerformance" data-bs-toggle="dropdown">
                                            <i class="bx bx-dots-vertical-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="javascript:void(0);">Export as PDF</a>
                                            <a class="dropdown-item" href="javascript:void(0);">Export as CSV</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($users)): ?>
                                            <div class="col-12 text-center py-4">
                                                <div class="alert alert-info">No host staff available or no data found.</div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): $metrics = $individualReports[$user['UserID']]; ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h5 class="card-title m-0"><?= $user['Name'] ?></h5>
                                                            <span class="badge bg-label-primary">Host</span>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between mb-3">
                                                                <div>
                                                                    <h6 class="mb-1">Appointments</h6>
                                                                    <h4 class="mb-0"><?= $metrics['total_appointments'] ?? 0 ?></h4>
                                                                </div>
                                                                <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="bx bx-calendar bx-sm"></i>
                                                </span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-3">
                                                                <div>
                                                                    <h6 class="mb-1">Completed</h6>
                                                                    <h4 class="mb-0"><?= $metrics['completed'] ?? 0 ?></h4>
                                                                </div>
                                                                <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="bx bx-check-circle bx-sm"></i>
                                                </span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-3">
                                                                <div>
                                                                    <h6 class="mb-1">No-Show Rate</h6>
                                                                    <h4 class="mb-0"><?= $metrics['no_show_rate'] ?? 0 ?>%</h4>
                                                                </div>
                                                                <div class="avatar">
                                                                <span class="avatar-initial rounded bg-label-warning">
                                                                    <i class="bx bx-user-x bx-sm"></i>
                                                                </span>
                                                                    </div>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-3">
                                                                <div>
                                                                    <h6 class="mb-1">Peak Hour</h6>
                                                                    <h4 class="mb-0"><?= $metrics['peak_hour'] ? date('g A', strtotime($metrics['peak_hour'].':00')) : 'N/A' ?></h4>
                                                                </div>
                                                                <div class="avatar">
                                                                    <span class="avatar-initial rounded bg-label-info">
                                                                        <i class="bx bx-time bx-sm"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="progress mb-3" style="height: 8px">
                                                                <div class="progress-bar bg-success" style="width: <?= ($metrics['completed'] / $metrics['total_appointments']) * 100 ?>%"></div>
                                                                <div class="progress-bar bg-danger" style="width: <?= ($metrics['cancelled'] / $metrics['total_appointments']) * 100 ?>%"></div>
                                                            </div>
                                                            <div id="hostPerformanceChart_<?= $user['UserID'] ?>"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <!-- Support Staff Reports Tab -->
                <div class="tab-pane fade <?= $activeTab === 'support' ? 'show active' : '' ?>" id="supportTab">
                    <?php if ($activeTab === 'support') : ?>

                        <!-- Team Metrics -->
                        <div class="card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title m-0 me-2">Support Team Overview</h5>
                                <form method="POST" action="generate_report.php" style="display: inline;">
                                    <input type="hidden" name="report_type" value="support">
                                    <input type="hidden" name="start_date" value="<?= $startDate ?>">
                                    <input type="hidden" name="end_date" value="<?= $endDate ?>">
                                    <button type="submit" class="btn btn-light btn-sm">
                                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                                    </button>
                                </form>
                            </div>
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row g-4 mb-4">
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                        <span class="badge bg-label-primary rounded p-2 mb-3">
                                            <i class="bx bx-support bx-sm"></i>
                                        </span>
                                                        <h5 class="mb-1">Total Tickets</h5>
                                                        <h3 class="mb-2"><?= $teamReports['total_tickets'] ?? 0 ?></h3>
                                                        <p class="mb-0 text-success">
                                                            <span>+<?= round(($teamReports['total_tickets'] ?? 0) * 0.12) ?></span>
                                                            <span>vs last month</span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                        <span class="badge bg-label-success rounded p-2 mb-3">
                                            <i class="bx bx-check-circle bx-sm"></i>
                                        </span>
                                                        <h5 class="mb-1">Resolved</h5>
                                                        <h3 class="mb-2"><?= $teamReports['status_breakdown']['resolved'] ?? 0 ?></h3>
                                                        <p class="mb-0 text-success">
                                                            <span>+<?= round(($teamReports['status_breakdown']['resolved'] ?? 0) * 0.08) ?></span>
                                                            <span>vs last month</span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-label-warning rounded p-2 mb-3">
                                                            <i class="bx bx-time-five bx-sm"></i>
                                                        </span>
                                                        <h5 class="mb-1">Avg. Resolution</h5>
                                                        <h3 class="mb-2"><?= $teamReports['resolution_times']['avg_resolution_hours'] ? round($teamReports['resolution_times']['avg_resolution_hours']) : 'N/A' ?>h</h3>
                                                        <p class="mb-0 text-danger">
                                                            <span>-<?= round(($teamReports['resolution_times']['avg_resolution_hours'] ?? 0) * 0.05) ?>h</span>
                                                            <span>vs last month</span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                        <span class="badge bg-label-info rounded p-2 mb-3">
                                            <i class="bx bx-refresh bx-sm"></i>
                                        </span>
                                                        <h5 class="mb-1">Reopened Rate</h5>
                                                        <h3 class="mb-2"><?= isset($individualReports) && !empty($individualReports) ? round(array_sum(array_column($individualReports, 'reopened_rate')) / count($individualReports)) : 0 ?>%</h3>
                                                        <p class="mb-0 text-success">
                                                            <span>-<?= round((isset($individualReports) && !empty($individualReports) ? (array_sum(array_column($individualReports, 'reopened_rate')) / count($individualReports)) : 0) * 0.03) ?>%</span>
                                                            <span>vs last month</span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Charts Row - This is the critical section -->
                                < <div class="row">
                                    <!-- Enhanced Status Distribution Chart -->
                                    <div class="col-md-6 mb-4">
                                        <div class="chart-container">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Ticket Status Distribution</h5>
                                            </div>
                                            <div id="ticketStatusChart">
                                                <div class="chart-loading">
                                                    <div class="loading-spinner"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Enhanced Category Breakdown Chart -->
                                    <div class="col-md-6 mb-4">
                                        <div class="chart-container">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Issue Categories</h5>
                                            </div>
                                            <div id="categoryBreakdownChart">
                                                <div class="chart-loading">
                                                    <div class="loading-spinner"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Staff Performance Cards (keep your existing code here) -->
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title m-0 me-2">Individual Staff Performance</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($users)): ?>
                                    <div class="alert alert-warning">No support staff available or no data found.</div>
                                <?php else: ?>
                                    <div class="row g-4">
                                        <?php foreach ($users as $user):
                                            $metrics = $individualReports[$user['UserID']];
                                            $resolved = $metrics['status_breakdown']['resolved'] ?? 0;
                                            $total = $metrics['total_tickets'] ?? 1;
                                            $resolutionRate = $total > 0 ? round(($resolved / $total) * 100) : 0;
                                            ?>
                                            <div class="col-md-6 col-lg-4 col-xl-3">
                                                <div class="card h-100">
                                                    <div class="card-body text-center">
                                                        <div class="avatar avatar-xl mb-3">
                                                            <span class="avatar-initial rounded-circle bg-label-primary"><?= substr($user['Name'], 0, 1) ?></span>
                                                        </div>
                                                        <h5 class="mb-1"><?= htmlspecialchars($user['Name']) ?></h5>
                                                        <span class="text-body-secondary">Support Staff</span>

                                                        <div class="my-4">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span>Performance</span>
                                                                <span><?= $resolutionRate ?>%</span>
                                                            </div>
                                                            <div class="progress" style="height: 6px">
                                                                <div class="progress-bar bg-<?= $resolutionRate > 80 ? 'success' : ($resolutionRate > 50 ? 'warning' : 'danger') ?>"
                                                                     role="progressbar" style="width: <?= $resolutionRate ?>%"
                                                                     aria-valuenow="<?= $resolutionRate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                        </div>

                                                        <div class="d-flex justify-content-around text-center mt-4 mb-2">
                                                            <div>
                                                                <h5 class="mb-0"><?= $resolved ?></h5>
                                                                <small>Resolved</small>
                                                            </div>
                                                            <div>
                                                                <h5 class="mb-0"><?= $metrics['avg_resolution_hours'] !== null ? round($metrics['avg_resolution_hours']) : 'N/A' ?>h</h5>
                                                                <small>Avg. Time</small>
                                                            </div>
                                                            <div>
                                                                <h5 class="mb-0"><?= $metrics['reopened_rate'] ?? 0 ?>%</h5>
                                                                <small>Reopened</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Front Desk Reports Tab -->
                <div class="tab-pane fade <?= $activeTab === 'frontdesk' ? 'show active' : '' ?>" id="frontdeskTab">
                    <?php if ($activeTab === 'frontdesk') : ?>
                        <!-- Team Metrics -->
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="m-0 me-2">Front Desk Performance Overview</h5>
                                    <p class="card-subtitle">Key metrics for front desk operations</p>
                                </div>
                                <!--
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="frontDeskDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded icon-lg text-body-secondary"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="javascript:void(0);">Last 7 Days</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Last 30 Days</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Last 90 Days</a>
                                    </div>

                                </div>
                                -->
                                <form method="POST" action="generate_report.php" style="display: inline;">
                                    <input type="hidden" name="report_type" value="frontdesk">
                                    <input type="hidden" name="start_date" value="<?= $startDate ?>">
                                    <input type="hidden" name="end_date" value="<?= $endDate ?>">
                                    <button type="submit" class="btn btn-light btn-sm">
                                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                                    </button>
                                </form>
                            </div>

                            <div class="card-body">
                                <div class="row">
                                    <!-- Total Appointments -->
                                    <div class="col-md-6 col-xl-3 mb-4">
                                        <div class="metric-card">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1">Appointments</h5>
                                                    <div class="metric-value"><?= $teamReports['scheduling_metrics']['total_appointments'] ?? 0 ?></div>
                                                    <small class="text-success">+2.4% from last period</small>
                                                </div>
                                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="icon-base bx bx-calendar icon-lg"></i>
                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Completed Appointments -->
                                    <div class="col-md-6 col-xl-3 mb-4">
                                        <div class="metric-card">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1">Completed</h5>
                                                    <div class="metric-value"><?= $teamReports['scheduling_metrics']['completed'] ?? 0 ?></div>
                                                    <small class="text-success">+5.1% from last period</small>
                                                </div>
                                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-success">
                                        <i class="icon-base bx bx-check-circle icon-lg"></i>
                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Check-In Time -->
                                    <div class="col-md-6 col-xl-3 mb-4">
                                        <div class="metric-card">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1">Avg Check-In</h5>
                                                    <div class="metric-value"><?= $teamReports['visitor_metrics']['avg_checkin_time'] ? round($teamReports['visitor_metrics']['avg_checkin_time']) : 'N/A' ?> min</div>
                                                    <small class="text-danger">-1.2 min from last period</small>
                                                </div>
                                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-info">
                                        <i class="icon-base bx bx-time-five icon-lg"></i>
                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Peak Hour -->
                                    <div class="col-md-6 col-xl-3 mb-4">
                                        <div class="metric-card">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1">Peak Hour</h5>
                                                    <div class="metric-value"><?= $teamReports['peak_hour'] ? $teamReports['peak_hour'] . ':00' : 'N/A' ?></div>
                                                    <small>Most active time</small>
                                                </div>
                                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-warning">
                                        <i class="icon-base bx bx-trending-up icon-lg"></i>
                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Client Metrics Row -->
                                <div class="row mt-4">
                                    <div class="col-md-6 mb-4">
                                        <div class="metric-card h-100">
                                            <h5 class="mb-3">Client Types</h5>
                                            <?php
                                            $newClients = $teamReports['client_metrics']['new_clients'] ?? 0;
                                            $returningClients = $teamReports['client_metrics']['returning_clients'] ?? 0;
                                            $totalClients = $newClients + $returningClients;
                                            $newPercentage = $totalClients > 0 ? round(($newClients / $totalClients) * 100) : 0;
                                            $returningPercentage = $totalClients > 0 ? round(($returningClients / $totalClients) * 100) : 0;
                                            ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <span class="badge bg-label-primary me-2"><?= $newClients ?></span>
                                                    <span>New Clients</span>
                                                </div>
                                                <div class="progress flex-grow-1" style="height: 10px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $newPercentage ?>%" aria-valuenow="<?= $newPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="ms-3 text-end">
                                                    <span class="fw-medium"><?= $newPercentage ?>%</span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge bg-label-success me-2"><?= $returningClients ?></span>
                                                    <span>Returning Clients</span>
                                                </div>
                                                <div class="progress flex-grow-1" style="height: 10px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $returningPercentage ?>%" aria-valuenow="<?= $returningPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="ms-3 text-end">
                                                    <span class="fw-medium"><?= $returningPercentage ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <div class="metric-card h-100">
                                            <h5 class="mb-3">Appointment Status</h5>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Completed</span>
                                                <span class="fw-medium"><?= $teamReports['scheduling_metrics']['completed'] ?? 0 ?></span>
                                            </div>
                                            <div class="progress mb-3" style="height: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar"
                                                     style="width: <?= $teamReports['scheduling_metrics']['total_appointments'] > 0 ? round(($teamReports['scheduling_metrics']['completed'] / $teamReports['scheduling_metrics']['total_appointments']) * 100) : 0 ?>%"
                                                     aria-valuenow="<?= $teamReports['scheduling_metrics']['completed'] ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="<?= $teamReports['scheduling_metrics']['total_appointments'] ?>">
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Cancelled</span>
                                                <span class="fw-medium"><?= $teamReports['scheduling_metrics']['cancelled'] ?? 0 ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-danger" role="progressbar"
                                                     style="width: <?= $teamReports['scheduling_metrics']['total_appointments'] > 0 ? round(($teamReports['scheduling_metrics']['cancelled'] / $teamReports['scheduling_metrics']['total_appointments']) * 100) : 0 ?>%"
                                                     aria-valuenow="<?= $teamReports['scheduling_metrics']['cancelled'] ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="<?= $teamReports['scheduling_metrics']['total_appointments'] ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Performance Cards -->
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title m-0 me-2">Individual Staff Performance</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="staffPerformanceDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded icon-lg text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="staffPerformanceDropdown">
                                        <a class="dropdown-item" href="javascript:void(0);">Export as PDF</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Export as CSV</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (empty($users)): ?>
                                        <div class="col-12">
                                            <div class="alert alert-info">No front desk staff available or no data found.</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): $metrics = $individualReports[$user['UserID']]; ?>
                                            <div class="col-md-6 col-xl-4 mb-4">
                                                <div class="metric-card h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h5 class="mb-0"><?= $user['Name'] ?></h5>
                                                        <span class="badge bg-label-primary">Front Desk</span>
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Appointments Scheduled</span>
                                                            <span class="fw-medium"><?= $metrics['total_appointments_scheduled'] ?? 0 ?></span>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, ($metrics['total_appointments_scheduled'] / ($teamReports['scheduling_metrics']['total_appointments'] ?? 1) * 100)) ?>%"></div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Avg Check-In Time</span>
                                                            <span class="fw-medium"><?= $metrics['avg_checkin_time'] !== null ? round($metrics['avg_checkin_time'], 1) : 'N/A' ?> min</span>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-<?= ($metrics['avg_checkin_time'] ?? 0) < 10 ? 'success' : (($metrics['avg_checkin_time'] ?? 0) < 20 ? 'warning' : 'danger') ?>"
                                                                 role="progressbar"
                                                                 style="width: <?= min(100, ($metrics['avg_checkin_time'] ?? 0) * 5) ?>%">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Error Rate</span>
                                                            <span class="fw-medium"><?= $metrics['error_rate'] ?? 0 ?>%</span>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-<?= ($metrics['error_rate'] ?? 0) < 5 ? 'success' : (($metrics['error_rate'] ?? 0) < 15 ? 'warning' : 'danger') ?>"
                                                                 role="progressbar"
                                                                 style="width: <?= $metrics['error_rate'] ?? 0 ?>%">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="global-notification-system.js"></script>
    <script>
        $(document).ready(function() {
            // Real-time status update for current admin (from user_management.php)
            function updateAdminStatus() {
                $.ajax({
                    url: 'update_activity.php',
                    type: 'GET',
                    success: function() {
                        // Status indicator would be updated here if needed
                    }
                });
            }

            // Update immediately and every minute
            updateAdminStatus();
            setInterval(updateAdminStatus, 60000);

            // Sidebar toggle functionality (from user_management.php)
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

                localStorage.setItem('layoutMenuCollapsed', isCollapsed);

                setTimeout(() => {
                    $toggle.css('pointer-events', 'auto');
                }, 300);
            });

            // Initialize sidebar state from localStorage (from user_management.php)
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
        });

        // Host Appointments Chart
        <?php if ($activeTab === 'host') : ?>
        var hostAppointmentsOptions = {
            series: [{
                name: 'Appointments',
                data: [28, 40, 36, 52, 38, 60, 55]
            }],
            chart: {
                height: 100,
                type: 'area',
                sparkline: {
                    enabled: true
                },
                toolbar: {
                    show: false
                }
            },
            colors: ['#7367F0'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 0.5,
                    opacityFrom: 0.5,
                    opacityTo: 0.1
                }
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                marker: {
                    show: false
                }
            }
        };

        var hostStatusOptions = {
            series: [
                <?= $teamReports['appointment_metrics']['completed'] ?? 0 ?>,
                <?= $teamReports['appointment_metrics']['cancelled'] ?? 0 ?>,
                <?= $teamReports['appointment_metrics']['upcoming'] ?? 0 ?>
            ],
            chart: {
                type: 'donut',
                height: 150
            },
            labels: ['Completed', 'Cancelled', 'Upcoming'],
            colors: ['#28C76F', '#EA5455', '#FF9F43'],
            legend: {
                show: false
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '75%'
                    }
                }
            }
        };

        // Only initialize charts if elements exist
        if (document.querySelector("#hostAppointmentsChart")) {
            var hostAppointmentsChart = new ApexCharts(document.querySelector("#hostAppointmentsChart"), hostAppointmentsOptions);
            hostAppointmentsChart.render();
        }

        if (document.querySelector("#hostStatusChart")) {
            var hostStatusChart = new ApexCharts(document.querySelector("#hostStatusChart"), hostStatusOptions);
            hostStatusChart.render();
        }
        <?php endif; ?>

        // Support Charts
        <?php if ($activeTab === 'support') : ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing support charts...');

            // Get the data from PHP with proper JSON encoding
            var statusBreakdown = <?= json_encode($teamReports['status_breakdown'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var categoryBreakdown = <?= json_encode($teamReports['category_breakdown'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var totalTickets = <?= $teamReports['total_tickets'] ?? 0 ?>;

            console.log('Raw Status Breakdown:', statusBreakdown);
            console.log('Raw Category Breakdown:', categoryBreakdown);
            console.log('Total Tickets:', totalTickets);

            // Function to safely initialize charts
            function initializeSupportCharts() {
                // === TICKET STATUS CHART ===
                var statusContainer = document.getElementById('ticketStatusChart');
                if (statusContainer) {
                    console.log('Initializing status chart...');

                    // Clear any existing content
                    statusContainer.innerHTML = '';

                    try {
                        var statusData = [];
                        var statusLabels = [];
                        var statusColors = [];

                        // Prepare status data with better error handling
                        if (statusBreakdown && typeof statusBreakdown === 'object') {
                            // Convert object to arrays
                            var statusMapping = {
                                'open': { label: 'Open', color: '#ff9f43' },
                                'in_progress': { label: 'In Progress', color: '#00cfe8' },
                                'in-progress': { label: 'In Progress', color: '#00cfe8' },
                                'resolved': { label: 'Resolved', color: '#28a745' },
                                'closed': { label: 'Closed', color: '#6c757d' },
                                'pending': { label: 'Pending', color: '#ffc107' }
                            };

                            Object.keys(statusBreakdown).forEach(function(status) {
                                var count = parseInt(statusBreakdown[status]) || 0;
                                if (count > 0) {
                                    var mapping = statusMapping[status.toLowerCase()] || {
                                        label: status.charAt(0).toUpperCase() + status.slice(1),
                                        color: '#696cff'
                                    };
                                    statusLabels.push(mapping.label);
                                    statusData.push(count);
                                    statusColors.push(mapping.color);
                                }
                            });
                        }

                        console.log('Processed Status Data:', statusData);
                        console.log('Status Labels:', statusLabels);

                        if (statusData.length > 0 && statusData.some(val => val > 0)) {
                            var statusOptions = {
                                series: statusData,
                                chart: {
                                    type: 'donut',
                                    height: 350,
                                    fontFamily: 'Public Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif',
                                    animations: {
                                        enabled: true,
                                        easing: 'easeinout',
                                        speed: 800
                                    }
                                },
                                labels: statusLabels,
                                colors: statusColors,
                                legend: {
                                    position: 'bottom',
                                    fontSize: '13px',
                                    fontFamily: 'inherit',
                                    markers: {
                                        width: 8,
                                        height: 8,
                                        offsetX: -3
                                    }
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '70%',
                                            labels: {
                                                show: true,
                                                name: {
                                                    show: true,
                                                    fontSize: '16px',
                                                    fontFamily: 'inherit',
                                                    color: '#566a7f'
                                                },
                                                value: {
                                                    show: true,
                                                    fontSize: '24px',
                                                    fontFamily: 'inherit',
                                                    fontWeight: 600,
                                                    color: '#566a7f'
                                                },
                                                total: {
                                                    show: true,
                                                    showAlways: true,
                                                    label: 'Total',
                                                    fontSize: '14px',
                                                    fontFamily: 'inherit',
                                                    color: '#a1acb8',
                                                    formatter: function() {
                                                        return totalTickets.toString();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                },
                                dataLabels: {
                                    enabled: true,
                                    formatter: function(val) {
                                        return Math.round(val) + '%';
                                    },
                                    style: {
                                        fontSize: '12px',
                                        fontFamily: 'inherit',
                                        fontWeight: 500
                                    }
                                },
                                tooltip: {
                                    style: {
                                        fontSize: '12px',
                                        fontFamily: 'inherit'
                                    },
                                    y: {
                                        formatter: function(val) {
                                            return val + ' tickets';
                                        }
                                    }
                                },
                                stroke: {
                                    width: 0
                                },
                                responsive: [{
                                    breakpoint: 480,
                                    options: {
                                        chart: {
                                            height: 300
                                        },
                                        legend: {
                                            position: 'bottom'
                                        }
                                    }
                                }]
                            };

                            console.log('Creating status chart...');
                            var statusChart = new ApexCharts(statusContainer, statusOptions);
                            statusChart.render().then(function() {
                                console.log('Status chart rendered successfully');
                            }).catch(function(error) {
                                console.error('Status chart render error:', error);
                                statusContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-warning">Unable to load status chart</div></div>';
                            });

                        } else {
                            console.log('No status data available');
                            statusContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-info"><i class="bx bx-info-circle me-2"></i>No ticket status data available</div></div>';
                        }

                    } catch (error) {
                        console.error('Error creating status chart:', error);
                        statusContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-danger">Error loading status chart</div></div>';
                    }
                }

                // === CATEGORY BREAKDOWN CHART ===
                var categoryContainer = document.getElementById('categoryBreakdownChart');
                if (categoryContainer) {
                    console.log('Initializing category chart...');

                    // Clear any existing content
                    categoryContainer.innerHTML = '';

                    try {
                        var categories = [];
                        var counts = [];

                        // Prepare category data with better error handling
                        if (categoryBreakdown && Array.isArray(categoryBreakdown) && categoryBreakdown.length > 0) {
                            categoryBreakdown.forEach(function(item) {
                                if (item && item.category && (item.count > 0 || item.total > 0)) {
                                    var categoryName = item.category || item.Category || 'Unknown';
                                    var categoryCount = parseInt(item.count || item.total || 0);
                                    if (categoryCount > 0) {
                                        categories.push(categoryName);
                                        counts.push(categoryCount);
                                    }
                                }
                            });
                        } else if (categoryBreakdown && typeof categoryBreakdown === 'object') {
                            // Handle case where it might be an object instead of array
                            Object.keys(categoryBreakdown).forEach(function(key) {
                                var count = parseInt(categoryBreakdown[key]) || 0;
                                if (count > 0) {
                                    categories.push(key.charAt(0).toUpperCase() + key.slice(1));
                                    counts.push(count);
                                }
                            });
                        }

                        console.log('Processed Categories:', categories);
                        console.log('Category Counts:', counts);

                        if (categories.length > 0 && counts.length > 0) {
                            var categoryOptions = {
                                series: [{
                                    name: 'Tickets',
                                    data: counts
                                }],
                                chart: {
                                    type: 'bar',
                                    height: 350,
                                    fontFamily: 'Public Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif',
                                    toolbar: {
                                        show: false
                                    },
                                    animations: {
                                        enabled: true,
                                        easing: 'easeinout',
                                        speed: 800
                                    }
                                },
                                plotOptions: {
                                    bar: {
                                        borderRadius: 6,
                                        horizontal: true,
                                        barHeight: '60%',
                                        dataLabels: {
                                            position: 'center'
                                        }
                                    }
                                },
                                dataLabels: {
                                    enabled: true,
                                    style: {
                                        colors: ['#fff'],
                                        fontSize: '12px',
                                        fontFamily: 'inherit',
                                        fontWeight: 500
                                    },
                                    formatter: function(val) {
                                        return val;
                                    }
                                },
                                xaxis: {
                                    categories: categories,
                                    labels: {
                                        style: {
                                            fontFamily: 'inherit',
                                            fontSize: '12px',
                                            colors: '#a1acb8'
                                        }
                                    },
                                    axisBorder: {
                                        show: false
                                    },
                                    axisTicks: {
                                        show: false
                                    }
                                },
                                yaxis: {
                                    labels: {
                                        style: {
                                            fontFamily: 'inherit',
                                            fontSize: '12px',
                                            colors: '#a1acb8'
                                        }
                                    }
                                },
                                colors: ['#696cff'],
                                grid: {
                                    borderColor: '#eceef1',
                                    strokeDashArray: 6,
                                    xaxis: {
                                        lines: {
                                            show: true
                                        }
                                    },
                                    yaxis: {
                                        lines: {
                                            show: false
                                        }
                                    }
                                },
                                tooltip: {
                                    style: {
                                        fontSize: '12px',
                                        fontFamily: 'inherit'
                                    },
                                    y: {
                                        formatter: function(val) {
                                            return val + ' tickets';
                                        }
                                    }
                                }
                            };

                            console.log('Creating category chart...');
                            var categoryChart = new ApexCharts(categoryContainer, categoryOptions);
                            categoryChart.render().then(function() {
                                console.log('Category chart rendered successfully');
                            }).catch(function(error) {
                                console.error('Category chart render error:', error);
                                categoryContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-warning">Unable to load category chart</div></div>';
                            });

                        } else {
                            console.log('No category data available');
                            categoryContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-info"><i class="bx bx-info-circle me-2"></i>No category data available</div></div>';
                        }

                    } catch (error) {
                        console.error('Error creating category chart:', error);
                        categoryContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-danger">Error loading category chart</div></div>';
                    }
                }
            }

            // Initialize charts with proper timing
            if (typeof ApexCharts !== 'undefined') {
                // ApexCharts is already loaded
                initializeSupportCharts();
            } else {
                // Wait for ApexCharts to load
                var checkApexCharts = setInterval(function() {
                    if (typeof ApexCharts !== 'undefined') {
                        clearInterval(checkApexCharts);
                        initializeSupportCharts();
                    }
                }, 100);

                // Timeout after 5 seconds
                setTimeout(function() {
                    clearInterval(checkApexCharts);
                    if (typeof ApexCharts === 'undefined') {
                        console.error('ApexCharts failed to load');
                        var statusContainer = document.getElementById('ticketStatusChart');
                        var categoryContainer = document.getElementById('categoryBreakdownChart');
                        if (statusContainer) {
                            statusContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-danger">Chart library failed to load</div></div>';
                        }
                        if (categoryContainer) {
                            categoryContainer.innerHTML = '<div class="text-center p-4"><div class="alert alert-danger">Chart library failed to load</div></div>';
                        }
                    }
                }, 5000);
            }
        });
        <?php endif; ?>

        // Front Desk Charts
        <?php if ($activeTab === 'frontdesk') : ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Client Type Pie Chart
            <?php
            $newClients = $teamReports['client_metrics']['new_clients'] ?? 0;
            $returningClients = $teamReports['client_metrics']['returning_clients'] ?? 0;
            $totalClients = $newClients + $returningClients;
            $newPercentage = $totalClients > 0 ? round(($newClients / $totalClients) * 100) : 0;
            $returningPercentage = $totalClients > 0 ? round(($returningClients / $totalClients) * 100) : 0;
            ?>

            if (document.getElementById('clientTypeChart')) {
                var clientTypeOptions = {
                    series: [<?= $newPercentage ?>, <?= $returningPercentage ?>],
                    chart: {
                        type: 'donut',
                        height: 300
                    },
                    labels: ['New Clients', 'Returning Clients'],
                    colors: ['#696cff', '#71dd37'],
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            chart: {
                                width: 200
                            },
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }]
                };

                var clientTypeChart = new ApexCharts(document.getElementById("clientTypeChart"), clientTypeOptions);
                clientTypeChart.render();
            }

            // Appointment Status Chart
            if (document.getElementById('appointmentStatusChart')) {
                var statusOptions = {
                    series: [{
                        name: 'Completed',
                        data: [<?= $teamReports['scheduling_metrics']['completed'] ?? 0 ?>]
                    }, {
                        name: 'Cancelled',
                        data: [<?= $teamReports['scheduling_metrics']['cancelled'] ?? 0 ?>]
                    }],
                    chart: {
                        type: 'bar',
                        height: 300,
                        stacked: true,
                        toolbar: {
                            show: false
                        }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            borderRadius: 4,
                            columnWidth: '50%',
                        },
                    },
                    colors: ['#71dd37', '#ff3e1d'],
                    dataLabels: {
                        enabled: false
                    },
                    xaxis: {
                        categories: ['Appointments'],
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        }
                    },
                    yaxis: {
                        show: false
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left'
                    },
                    grid: {
                        show: false
                    }
                };

                var statusChart = new ApexCharts(document.getElementById("appointmentStatusChart"), statusOptions);
                statusChart.render();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>