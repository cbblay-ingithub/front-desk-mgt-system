<?php
session_start();
require_once '../dbConfig.php';
require_once 'report_functions.php';
global $conn;

// Get filter parameters
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$activeTab = $_GET['tab'] ?? 'host'; // Default to host tab

// Get report data based on active tab
$hostReports = [];
$supportReports = [];
$frontDeskReports = [];

if ($activeTab === 'host') {
    $hostReports = getHostReports($startDate, $endDate);
} elseif ($activeTab === 'support') {
    $supportReports = getSupportReports($startDate, $endDate);
} elseif ($activeTab === 'frontdesk') {
    $frontDeskReports = getFrontDeskReports($startDate, $endDate);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 250px;
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
        .content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }
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
        /* Tab styles */
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
    </style>
</head>
<body>
<div class="sidebar">
    <h4 class="text-white text-center">Admin Panel</h4>
    <a href="admin-dashboard.html"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="user_management.php"><i class='far fa-address-card' ></i> User Management</a>
    <a href="admin-reports.php"><i class="fas fa-ticket"></i> Reporting</a>
    <a href="lost_found.php"><i class="fa-solid fa-suitcase"></i> View Lost & Found</a>
    <a href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
    <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="content">
    <h2 class="mb-4">Admin Reports</h2>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="reportTabs">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'host' ? 'active' : '' ?>"
               href="?<?= http_build_query(['tab' => 'host', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                Host Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'support' ? 'active' : '' ?>"
               href="?<?= http_build_query(['tab' => 'support', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                Support Staff Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'frontdesk' ? 'active' : '' ?>"
               href="?<?= http_build_query(['tab' => 'frontdesk', 'start_date' => $startDate, 'end_date' => $endDate]) ?>">
                Front Desk Reports
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
                <div class="report-card card">
                    <div class="report-header card-header">
                        <h3>Host Reports</h3>
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
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Total Appointments</h5>
                                    <div class="metric-value"><?= $hostReports['appointment_metrics']['total_appointments'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Completed</h5>
                                    <div class="metric-value"><?= $hostReports['appointment_metrics']['completed'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Resolution Rate</h5>
                                    <div class="metric-value">
                                        <?php if ($hostReports['resolution_rates']['total_tickets'] > 0): ?>
                                            <?= round(($hostReports['resolution_rates']['resolved'] / $hostReports['resolution_rates']['total_tickets']) * 100) ?>%
                                        <?php else: ?>
                                            0%
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Support Staff Reports Tab -->
        <div class="tab-pane fade <?= $activeTab === 'support' ? 'show active' : '' ?>" id="supportTab">
            <?php if ($activeTab === 'support') : ?>
                <div class="report-card card">
                    <div class="report-header card-header">
                        <h3>Support Staff Reports</h3>
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
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <h5>Total Tickets</h5>
                                    <div class="metric-value"><?= count($supportReports['ticket_volume']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <h5>Avg. Resolution</h5>
                                    <div class="metric-value">
                                        <?= round($supportReports['resolution_times']['avg_resolution_hours']) ?> hrs
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Category Breakdown</h5>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($supportReports['category_breakdown'] as $category): ?>
                                        <tr>
                                            <td><?= $category['category'] ?></td>
                                            <td><?= $category['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Front Desk Reports Tab -->
        <div class="tab-pane fade <?= $activeTab === 'frontdesk' ? 'show active' : '' ?>" id="frontdeskTab">
            <?php if ($activeTab === 'frontdesk') : ?>
                <div class="report-card card">
                    <div class="report-header card-header">
                        <h3>Front Desk Reports</h3>
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
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Appointments</h5>
                                    <div class="metric-value"><?= $frontDeskReports['scheduling_metrics']['total_appointments'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Visitors Checked In</h5>
                                    <div class="metric-value"><?= $frontDeskReports['visitor_metrics']['visitors_checked_in'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="metric-card">
                                    <h5>Lost Items Claimed</h5>
                                    <div class="metric-value">
                                        <?= $frontDeskReports['lost_found_metrics']['claimed'] ?> / <?= $frontDeskReports['lost_found_metrics']['total_items'] ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>