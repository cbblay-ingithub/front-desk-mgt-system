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
    <title>Reports -Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding-top: 1rem;
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
<body>
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
                            <div class="report-card card">
                                <div class="report-header card-header">
                                    <h3>Host Team Reports</h3>
                                    <form method="POST" action="generate_report.php" style="display: inline;">
                                        <input type="hidden" name="report_type" value="host">
                                        <input type="hidden" name="start_date" value="<?= $startDate ?>">
                                        <input type="hidden" name="end_date" value="<?= $endDate ?>">
                                        <button type="submit" class="btn btn-light btn-sm">
                                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                                        </button>
                                    </form>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4"><div class="metric-card"><h5>Total Appointments</h5><div class="metric-value"><?= $teamReports['appointment_metrics']['total_appointments'] ?? 0 ?></div></div></div>
                                        <div class="col-md-4"><div class="metric-card"><h5>Completed</h5><div class="metric-value"><?= $teamReports['appointment_metrics']['completed'] ?? 0 ?></div></div></div>
                                        <div class="col-md-4"><div class="metric-card"><h5>Completion Rate</h5><div class="metric-value"><?= $teamReports['resolution_rates']['total_tickets'] > 0 ? round(($teamReports['resolution_rates']['resolved'] / $teamReports['resolution_rates']['total_tickets']) * 100) : 0 ?>%</div></div></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Individual Performance Cards -->
                            <div class="report-card card">
                                <div class="report-header card-header"><h3>Individual Host Performance</h3></div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($users)): ?>
                                            <p>No host staff available or no data found.</p>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): $metrics = $individualReports[$user['UserID']]; ?>
                                                <div class="col-md-4">
                                                    <div class="metric-card">
                                                        <h5><?= $user['Name'] ?></h5>
                                                        <p>Total Appointments: <strong><?= $metrics['total_appointments'] ?? 0 ?></strong></p>
                                                        <p>Completed: <strong><?= $metrics['completed'] ?? 0 ?></strong></p>
                                                        <p>No-Show Rate: <strong><?= $metrics['no_show_rate'] ?? 0 ?>%</strong></p>
                                                        <p>Cancelled: <strong><?= $metrics['cancelled'] ?? 0 ?></strong></p>
                                                        <p>Upcoming: <strong><?= $metrics['upcoming'] ?? 0 ?></strong></p>
                                                        <p>Peak Hour: <strong><?= $metrics['peak_hour'] ? $metrics['peak_hour'] . ':00' : 'N/A' ?></strong></p>
                                                        <p>Monthly Trends:</p>
                                                        <ul>
                                                            <?php foreach ($metrics['monthly_trends'] as $trend): ?>
                                                                <li><?= date('M Y', mktime(0,0,0,$trend['month'],1,$trend['year'])) ?>: <?= $trend['completed'] ?? 0 ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
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
                            <div class="report-card card">
                                <div class="report-header card-header">
                                    <h3>Support Team Reports</h3>
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
                                    <div class="row">
                                        <div class="col-md-3"><div class="metric-card"><h5>Total Tickets</h5><div class="metric-value"><?= $teamReports['total_tickets'] ?? 0 ?></div></div></div>
                                        <div class="col-md-3"><div class="metric-card"><h5>Avg. Resolution</h5><div class="metric-value"><?= $teamReports['resolution_times']['avg_resolution_hours'] ? round($teamReports['resolution_times']['avg_resolution_hours']) : 'N/A' ?> hrs</div></div></div>
                                        <div class="col-md-6"><h5>Category Breakdown</h5><table class="table table-bordered"><thead><tr><th>Category</th><th>Count</th></tr></thead><tbody><?php foreach ($teamReports['category_breakdown'] as $category): ?><tr><td><?= $category['category'] ?></td><td><?= $category['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Individual Performance Cards -->
                            <div class="report-card card">
                                <div class="report-header card-header"><h3>Individual Staff Performance</h3></div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($users)): ?>
                                            <p>No support staff available or no data found.</p>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): $metrics = $individualReports[$user['UserID']]; ?>
                                                <div class="col-md-4">
                                                    <div class="metric-card">
                                                        <h5><?= $user['Name'] ?></h5>
                                                        <p>Open Tickets: <strong><?= $metrics['status_breakdown']['open'] ?? 0 ?></strong></p>
                                                        <p>In Progress: <strong><?= $metrics['status_breakdown']['in-progress'] ?? 0 ?></strong></p>
                                                        <p>Resolved: <strong><?= $metrics['status_breakdown']['resolved'] ?? 0 ?></strong></p>
                                                        <p>Closed: <strong><?= $metrics['status_breakdown']['closed'] ?? 0 ?></strong></p>
                                                        <p>Avg. Resolution Time: <strong><?= $metrics['avg_resolution_hours'] !== null ? round($metrics['avg_resolution_hours']) : 'N/A' ?> hrs</strong></p>
                                                        <p>Tickets Created: <strong><?= $metrics['tickets_created'] ?? 0 ?></strong></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Front Desk Reports Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'frontdesk' ? 'show active' : '' ?>" id="frontdeskTab">
                        <?php if ($activeTab === 'frontdesk') : ?>
                            <!-- Team Metrics -->
                            <div class="report-card card">
                                <div class="report-header card-header">
                                    <h3>Front Desk Team Reports</h3>
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
                                        <div class="col-md-4"><div class="metric-card"><h5>Appointments</h5><div class="metric-value"><?= $teamReports['scheduling_metrics']['total_appointments'] ?? 0 ?></div></div></div>
                                        <div class="col-md-4"><div class="metric-card"><h5>Visitors Checked In</h5><div class="metric-value"><?= $teamReports['visitor_metrics']['visitors_checked_in'] ?? 0 ?></div></div></div>
                                        <div class="col-md-4"><div class="metric-card"><h5>Lost Items Claimed</h5><div class="metric-value"><?= $teamReports['lost_found_metrics']['claimed'] ?? 0 ?> / <?= $teamReports['lost_found_metrics']['total_items'] ?? 0 ?></div></div></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Individual Performance Cards -->
                            <div class="report-card card">
                                <div class="report-header card-header"><h3>Individual Front Desk Performance</h3></div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($users)): ?>
                                            <p>No front desk staff available or no data found.</p>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): $metrics = $individualReports[$user['UserID']]; ?>
                                                <div class="col-md-4">
                                                    <div class="metric-card">
                                                        <h5><?= $user['Name'] ?></h5>
                                                        <p>Appointments Scheduled: <strong><?= $metrics['total_appointments_scheduled'] ?? 0 ?></strong></p>
                                                        <p>Avg. Check-In Time: <strong><?= $metrics['avg_checkin_time'] !== null ? round($metrics['avg_checkin_time'], 2) : 'N/A' ?> mins</strong></p>
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
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
</script>
</body>
</html>