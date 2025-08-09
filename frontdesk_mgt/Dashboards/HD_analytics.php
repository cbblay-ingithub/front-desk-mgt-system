<?php
require_once '../dbConfig.php';
global $conn;
session_start();

if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    // Log activity for support personnel
    $activity = "Visited Support Dashboard";
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}

// Fetch ticket statistics
function getTicketStats($conn) {
    $stats = [];

    // Overall ticket counts
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN Status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN Status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN Status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN Status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN Status = 'closed' THEN 1 ELSE 0 END) as closed_tickets
        FROM Help_Desk
    ");
    $stats = array_merge($stats, $result->fetch_assoc());

    // Resolution time stats
    $result = $conn->query("
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) as avg_resolution_hours,
            MAX(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) as max_resolution_hours,
            MIN(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) as min_resolution_hours
        FROM Help_Desk
        WHERE Status IN ('resolved', 'closed') AND ResolvedDate IS NOT NULL
    ");
    $stats = array_merge($stats, $result->fetch_assoc());

    // Tickets by category
    $result = $conn->query("
        SELECT 
            tc.CategoryName,
            COUNT(h.TicketID) as ticket_count
        FROM Help_Desk h
        LEFT JOIN TicketCategories tc ON h.CategoryID = tc.CategoryID
        GROUP BY tc.CategoryName
        ORDER BY ticket_count DESC
        LIMIT 5
    ");
    $stats['top_categories'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['top_categories'][] = $row;
    }

    // Monthly ticket trends
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(CreatedDate, '%Y-%m') as month,
            COUNT(*) as ticket_count,
            SUM(CASE WHEN Status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_count
        FROM Help_Desk
        WHERE CreatedDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(CreatedDate, '%Y-%m')
        ORDER BY month
    ");
    $stats['monthly_trends'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['monthly_trends'][] = $row;
    }

    return $stats;
}

// Get recent ticket data
function getRecentTicketData($conn) {
    $data = [];

    // Recent tickets (last 10 created)
    $result = $conn->query("
        SELECT 
            h.TicketID, 
            h.Description, 
            h.Status,
            h.CreatedDate,
            u.Name as CreatedBy,
            a.Name as AssignedTo,
            tc.CategoryName
        FROM Help_Desk h
        JOIN users u ON h.CreatedBy = u.UserID
        LEFT JOIN users a ON h.AssignedTo = a.UserID
        LEFT JOIN TicketCategories tc ON h.CategoryID = tc.CategoryID
        ORDER BY h.CreatedDate DESC
        LIMIT 10
    ");
    $data['recent_tickets'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_tickets'][] = $row;
    }

    // Your assigned tickets
    if (isset($_SESSION['userID'])) {
        $result = $conn->query("
            SELECT 
                h.TicketID, 
                h.Description, 
                h.Status,
                h.CreatedDate,
                u.Name as CreatedBy
            FROM Help_Desk h
            JOIN users u ON h.CreatedBy = u.UserID
            WHERE h.AssignedTo = ".$_SESSION['userID']."
            ORDER BY h.CreatedDate DESC
        ");
        $data['your_tickets'] = [];
        while ($row = $result->fetch_assoc()) {
            $data['your_tickets'][] = $row;
        }
    } else {
        $data['your_tickets'] = [];
    }

    return $data;
}

$stats = getTicketStats($conn);
$ticketData = getRecentTicketData($conn);
$conn->close();
?>

<!DOCTYPE html>
<html
    lang="en"
    class="layout-navbar-fixed layout-menu-fixed layout-compact"
    dir="ltr"
    data-skin="default"
    data-assets-path="../../assets/"
    data-template="vertical-menu-template"
    data-bs-theme="light"
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Support Dashboard</title>

    <!-- CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Dashboard Cards */
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            color: white;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stats-card.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.open { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stats-card.in-progress { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-card.resolved { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .ticket-item {
            padding: 12px;
            border-left: 4px solid;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .ticket-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .ticket-item.open { border-color: #f5576c; }
        .ticket-item.in-progress { border-color: #00f2fe; }
        .ticket-item.pending { border-color: #ffc107; }
        .ticket-item.resolved { border-color: #38f9d7; }
        .ticket-item.closed { border-color: #6c757d; }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .status-open { background-color: #f5576c; }
        .status-in-progress { background-color: #00f2fe; }
        .status-pending { background-color: #ffc107; }
        .status-resolved { background-color: #38f9d7; }
        .status-closed { background-color: #6c757d; }

        .navbar-detached {
            box-shadow: 0 1px 20px 0 rgba(76,87,125,.1);
            background-color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
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

        .ticket-description {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1.5rem;
        }

        .card {
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        /* Updated styles for top tickets visualization */
        .top-tickets-visual {
            display: flex;
            align-items: flex-end;
            height: 200px;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }

        .ticket-bar {
            flex: 1;
            margin: 0 5px;
            background: linear-gradient(to top, #667eea, #764ba2);
            border-radius: 5px 5px 0 0;
            position: relative;
            transition: all 0.3s ease;
        }

        .ticket-bar:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .ticket-bar-label {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .ticket-bar-value {
            position: absolute;
            top: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-weight: bold;
            color: #333;
        }

        .recent-tickets-container {
            max-height: 400px;
            overflow-y: auto;
        }

        /* New layout for categories section */
        .categories-container {
            display: flex;
            flex-wrap: wrap;
        }

        .categories-visual {
            flex: 1;
            min-width: 300px;
            padding-right: 20px;
        }

        .categories-chart {
            flex: 1;
            min-width: 300px;
        }
             /* Ensure fixed sidebar and proper content offset */
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

        .layout-content {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: margin-left 0.3s ease, width 0.3s ease !important;
            padding-top: 70px; /* Add padding to account for fixed navbar */

        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

        /* Prevent scrolling on body/html */
        html, body {
            overflow-x: hidden !important;
            overflow-y: hidden !important;
            height: 100vh !important;
        }


    </style>
</head>

<body>
<div data-user-id="<?php echo $_SESSION['userID'] ?? ''; ?>">
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include 'sidebar.php'; ?>

            <div class="layout-content">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base bx bx-menu icon-md"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                        <div class="navbar-nav align-items-center me-auto">
                            <div class="nav-item">
                                <h4 class="mb-0 fw-bold ms-2">Support Dashboard</h4>
                            </div>
                        </div>

                        <div class="navbar-nav align-items-center">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['name'] ?? 'Support'; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Main Content -->
                <div class="container-fluid container-p-y">
                    <!-- Stats Cards Row -->
                    <div class="row mb-4">
                        <!-- Total Tickets Card -->
                        <div class="col-md-3 mb-4">
                            <div class="stats-card total">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number"><?= $stats['total_tickets'] ?></div>
                                            <div class="label">Total Tickets</div>
                                        </div>
                                        <div class="stats-icon">
                                            <i class="fas fa-ticket-alt"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Open Tickets Card -->
                        <div class="col-md-3 mb-4">
                            <div class="stats-card open">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number"><?= $stats['open_tickets'] ?></div>
                                            <div class="label">Open Tickets</div>
                                        </div>
                                        <div class="stats-icon">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- In Progress Tickets Card -->
                        <div class="col-md-3 mb-4">
                            <div class="stats-card in-progress">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number"><?= $stats['in_progress_tickets'] ?></div>
                                            <div class="label">In Progress</div>
                                        </div>
                                        <div class="stats-icon">
                                            <i class="fas fa-spinner"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resolved Tickets Card -->
                        <div class="col-md-3 mb-4">
                            <div class="stats-card resolved">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number"><?= $stats['resolved_tickets'] + $stats['closed_tickets'] ?></div>
                                            <div class="label">Resolved/Closed</div>
                                        </div>
                                        <div class="stats-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="help_desk.php" class="quick-action-btn">
                                                <i class="fas fa-plus-circle"></i>
                                                <span>Create Ticket</span>
                                            </a>
                                        </div>
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="help_desk.php?status=open" class="quick-action-btn">
                                                <i class="fas fa-folder-open"></i>
                                                <span>View Open</span>
                                            </a>
                                        </div>
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="help_desk.php?status=in-progress" class="quick-action-btn">
                                                <i class="fas fa-spinner"></i>
                                                <span>View In Progress</span>
                                            </a>
                                        </div>
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="help_desk.php?status=resolved" class="quick-action-btn">
                                                <i class="fas fa-check"></i>
                                                <span>View Resolved</span>
                                            </a>
                                        </div>
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="help_desk.php?assignee=me" class="quick-action-btn">
                                                <i class="fas fa-user-tag"></i>
                                                <span>My Tickets</span>
                                            </a>
                                        </div>
                                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                                            <a href="reports.php" class="quick-action-btn">
                                                <i class="fas fa-chart-bar"></i>
                                                <span>Reports</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <!-- Ticket Status Distribution -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Ticket Status Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Ticket Trends -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Monthly Ticket Trends</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="monthlyTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Categories Section - Updated Layout -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Top Ticket Categories</h5>
                                </div>
                                <div class="card-body">
                                    <div class="categories-container">
                                        <!-- Categories Visualization on Left -->
                                        <div class="categories-visual">
                                            <div class="top-tickets-visual">
                                                <?php
                                                $maxCount = max(array_column($stats['top_categories'], 'ticket_count'));
                                                foreach ($stats['top_categories'] as $category):
                                                    $height = ($category['ticket_count'] / $maxCount) * 150;
                                                    ?>
                                                    <div class="ticket-bar" style="height: <?= $height ?>px">
                                                        <div class="ticket-bar-value"><?= $category['ticket_count'] ?></div>
                                                        <div class="ticket-bar-label"><?= substr($category['CategoryName'], 0, 12) ?><?= strlen($category['CategoryName']) > 12 ? '...' : '' ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Categories Chart on Right -->
                                        <div class="categories-chart">
                                            <div class="chart-container">
                                                <canvas id="categoryChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tickets Section -->
                    <div class="row">
                        <!-- Recent Tickets -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Recent Tickets</h5>
                                    <a href="help_desk.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body recent-tickets-container">
                                    <?php if (empty($ticketData['recent_tickets'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-ticket-alt fa-2x mb-3"></i>
                                            <p>No recent tickets found</p>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        $recentTicketsToShow = array_slice($ticketData['recent_tickets'], 0, 4);
                                        foreach ($recentTicketsToShow as $ticket): ?>
                                            <div class="ticket-item <?= str_replace('-', '', $ticket['Status']) ?> mb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong>Ticket #<?= $ticket['TicketID'] ?></strong>
                                                        <div class="text-muted small ticket-description" title="<?= htmlspecialchars($ticket['Description']) ?>">
                                                            <?= htmlspecialchars(substr($ticket['Description'], 0, 50)) ?><?= strlen($ticket['Description']) > 50 ? '...' : '' ?>
                                                        </div>
                                                        <div class="d-flex align-items-center mt-2">
                                                            <span class="badge status-badge status-<?= str_replace(' ', '-', $ticket['Status']) ?>">
                                                                <?= ucwords(str_replace('-', ' ', $ticket['Status'])) ?>
                                                            </span>
                                                        </div>
                                                        <div class="text-muted small mt-1">
                                                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($ticket['CreatedBy']) ?>
                                                            <?php if (!empty($ticket['AssignedTo'])): ?>
                                                                <span class="ms-2"><i class="fas fa-user-tag me-1"></i> <?= htmlspecialchars($ticket['AssignedTo']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?= date('M j, h:i A', strtotime($ticket['CreatedDate'])) ?></small>
                                                        <div class="mt-2">
                                                            <a href="ticket.php?id=<?= $ticket['TicketID'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Your Assigned Tickets -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Your Assigned Tickets</h5>
                                    <a href="tickets.php?assignee=me" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($ticketData['your_tickets'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-check-circle fa-2x mb-3"></i>
                                            <p>No tickets assigned to you</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($ticketData['your_tickets'] as $ticket): ?>
                                            <div class="ticket-item <?= str_replace('-', '', $ticket['Status']) ?> mb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong>Ticket #<?= $ticket['TicketID'] ?></strong>
                                                        <div class="text-muted small ticket-description" title="<?= htmlspecialchars($ticket['Description']) ?>">
                                                            <?= htmlspecialchars(substr($ticket['Description'], 0, 30)) ?><?= strlen($ticket['Description']) > 30 ? '...' : '' ?>
                                                        </div>
                                                        <div class="d-flex align-items-center mt-2">
                                                            <span class="badge status-badge status-<?= str_replace(' ', '-', $ticket['Status']) ?>">
                                                                <?= ucwords(str_replace('-', ' ', $ticket['Status'])) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?= date('M j', strtotime($ticket['CreatedDate'])) ?></small>
                                                        <div class="mt-2">
                                                            <a href="ticket.php?id=<?= $ticket['TicketID'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                        </div>
                                                    </div>
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
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../Sneat/assets/vendor/js/helpers.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>

<script>
    $(document).ready(function() {
        // Initialize charts
        initializeCharts();

        // Restore sidebar state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            $('html').addClass('layout-menu-collapsed');
            $('#toggleIcon').removeClass('bx-chevron-left').addClass('bx-chevron-right');
        }

        // Handle sidebar toggle
        $('#sidebarToggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $html = $('html');
            const $sidebar = $('#layout-menu');
            const $toggleIcon = $('#toggleIcon');

            $(this).css('pointer-events', 'none');
            $html.toggleClass('layout-menu-collapsed');
            const isCollapsed = $html.hasClass('layout-menu-collapsed');

            // Update icon
            if (isCollapsed) {
                $toggleIcon.removeClass('bx-chevron-left').addClass('bx-chevron-right');
            } else {
                $toggleIcon.removeClass('bx-chevron-right').addClass('bx-chevron-left');
            }

            // Store state
            localStorage.setItem('sidebarCollapsed', isCollapsed);

            setTimeout(() => {
                $(this).css('pointer-events', 'auto');
            }, 300);
        });

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

        // Refresh dashboard data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    });

    function initializeCharts() {
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
                datasets: [{
                    data: [
                        <?= $stats['open_tickets'] ?>,
                        <?= $stats['in_progress_tickets'] ?>,
                        <?= $stats['pending_tickets'] ?>,
                        <?= $stats['resolved_tickets'] ?>,
                        <?= $stats['closed_tickets'] ?>
                    ],
                    backgroundColor: [
                        '#f5576c',
                        '#00f2fe',
                        '#ffc107',
                        '#38f9d7',
                        '#6c757d'
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

        // Monthly Trends Chart
        const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyLabels = <?= json_encode(array_column($stats['monthly_trends'], 'month')) ?>;
        const monthlyData = <?= json_encode(array_column($stats['monthly_trends'], 'ticket_count')) ?>;
        const resolvedData = <?= json_encode(array_column($stats['monthly_trends'], 'resolved_count')) ?>;

        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Created Tickets',
                        data: monthlyData,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'Resolved Tickets',
                        data: resolvedData,
                        borderColor: '#43e97b',
                        backgroundColor: 'rgba(67, 233, 123, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Top Categories Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryLabels = <?= json_encode(array_column($stats['top_categories'], 'CategoryName')) ?>;
        const categoryData = <?= json_encode(array_column($stats['top_categories'], 'ticket_count')) ?>;

        new Chart(categoryCtx, {
            type: 'polarArea',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
</script>
</body>
</html>