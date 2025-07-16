<?php
session_start();
require_once '../dbConfig.php';
require_once 'report_functions.php';
global $conn;

// Get report data
$hostReports = getHostReports();
$supportReports = getSupportReports();
$frontDeskReports = getFrontDeskReports();
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

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Host Reports -->
    <div class="report-card card">
        <div class="report-header card-header">
            <h3>Host Reports</h3>
            <form method="POST" action="generate_report.php" style="display: inline;">
                <input type="hidden" name="report_type" value="host">
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

    <!-- Support Staff Reports -->
    <div class="report-card card">
        <div class="report-header card-header">
            <h3>Support Staff Reports</h3>
            <form method="POST" action="generate_report.php" style="display: inline;">
                <input type="hidden" name="report_type" value="support">
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

    <!-- Front Desk Reports -->
    <div class="report-card card">
        <div class="report-header card-header">
            <h3>Front Desk Reports</h3>
            <form method="POST" action="generate_report.php" style="display: inline;">
                <input type="hidden" name="report_type" value="frontdesk">
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
</div>
</body>
</html>