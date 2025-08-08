<?php
require_once '../dbConfig.php';
global $conn;
session_start();

if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    // Log activity for front desk
    $activity = "Visited Front Desk Dashboard";
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}

// Fetch dashboard statistics
function getFrontDeskStats($conn) {
    $stats = [];

    // Visitor Statistics
    $result = $conn->query("SELECT COUNT(*) as total FROM Visitors");
    $stats['total_visitors'] = $result->fetch_assoc()['total'];

    $result = $conn->query("
        SELECT COUNT(DISTINCT v.VisitorID) as checked_in 
        FROM Visitors v 
        JOIN Visitor_Logs vl ON v.VisitorID = vl.VisitorID 
        WHERE vl.CheckOutTime IS NULL
    ");
    $stats['checked_in_visitors'] = $result->fetch_assoc()['checked_in'];

    $result = $conn->query("
        SELECT COUNT(*) as today 
        FROM Visitor_Logs 
        WHERE DATE(CheckInTime) = CURDATE()
    ");
    $stats['today_visitors'] = $result->fetch_assoc()['today'];

    // Appointment Statistics
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN Status = 'Upcoming' THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN Status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing,
            SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM Appointments
        WHERE DATE(AppointmentTime) >= CURDATE()
    ");
    $appointment_stats = $result->fetch_assoc();
    $stats = array_merge($stats, $appointment_stats);

    // Ticket Statistics
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN Status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN Status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN Status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets
        FROM Help_Desk
        WHERE CreatedDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $ticket_stats = $result->fetch_assoc();
    $stats = array_merge($stats, $ticket_stats);

    return $stats;
}

// Get recent data
function getRecentData($conn) {
    $data = [];

    // Recent Visitors (last 5 check-ins)
    $result = $conn->query("
        SELECT v.Name, v.Email, v.Phone, vl.CheckInTime
        FROM Visitor_Logs vl
        JOIN Visitors v ON vl.VisitorID = v.VisitorID
        ORDER BY vl.CheckInTime DESC
        LIMIT 5
    ");
    $data['recent_visitors'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_visitors'][] = $row;
    }

    // Recent Appointments (next 5 upcoming)
    $result = $conn->query("
        SELECT a.AppointmentID, a.AppointmentTime, a.Status, v.Name, v.Email
        FROM Appointments a
        JOIN Visitors v ON a.VisitorID = v.VisitorID
        WHERE a.AppointmentTime >= NOW()
        ORDER BY a.AppointmentTime ASC
        LIMIT 5
    ");
    $data['recent_appointments'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_appointments'][] = $row;
    }

    // Recent Tickets (last 5 created) - UPDATED to use Description instead of Subject
    $result = $conn->query("
        SELECT 
            h.TicketID, 
            h.Description, 
            h.Status, 
            h.CreatedDate as CreatedAt, 
            u.Name as CreatedBy
        FROM Help_Desk h
        JOIN users u ON h.CreatedBy = u.UserID
        ORDER BY h.CreatedDate DESC
        LIMIT 5
    ");
    $data['recent_tickets'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_tickets'][] = $row;
    }

    return $data;
}

$stats = getFrontDeskStats($conn);
$recentData = getRecentData($conn);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="layout-menu-fixed layout-compact" dir="ltr" data-skin="default">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Front Desk Dashboard</title>

    <!-- CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Sidebar and layout styles */
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
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

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

        .stats-card.visitors { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-card.appointments { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.tickets { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .recent-item {
            padding: 12px;
            border-left: 4px solid;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .recent-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .recent-item.visitor { border-color: #4facfe; }
        .recent-item.appointment { border-color: #667eea; }
        .recent-item.ticket { border-color: #f093fb; }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

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
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'frontdesk-sidebar.php'; ?>

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
                            <h4 class="mb-0 fw-bold ms-2">Front Desk Dashboard</h4>
                        </div>
                    </div>

                    <div class="navbar-nav align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['name'] ?? 'Front Desk'; ?>
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
                    <!-- Visitors Card -->
                    <div class="col-md-4 mb-4">
                        <div class="stats-card visitors">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?= $stats['total_visitors'] ?></div>
                                        <div class="label">Total Visitors</div>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Checked In: <?= $stats['checked_in_visitors'] ?></small>
                                        <small>Today: <?= $stats['today_visitors'] ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Card -->
                    <div class="col-md-4 mb-4">
                        <div class="stats-card appointments">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?= $stats['total'] ?></div>
                                        <div class="label">Today's Appointments</div>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Upcoming: <?= $stats['upcoming'] ?></small>
                                        <small>Ongoing: <?= $stats['ongoing'] ?></small>
                                        <small>Completed: <?= $stats['completed'] ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tickets Card -->
                    <div class="col-md-4 mb-4">
                        <div class="stats-card tickets">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?= $stats['total_tickets'] ?></div>
                                        <div class="label">Recent Tickets (7d)</div>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-ticket-alt"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Open: <?= $stats['open_tickets'] ?></small>
                                        <small>In Progress: <?= $stats['in_progress_tickets'] ?></small>
                                        <small>Resolved: <?= $stats['resolved_tickets'] ?></small>
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
                                        <a href="check_in.php" class="quick-action-btn">
                                            <i class="fas fa-user-plus"></i>
                                            <span>Check In Visitor</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="check_out.php" class="quick-action-btn">
                                            <i class="fas fa-user-minus"></i>
                                            <span>Check Out Visitor</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="schedule_appointment.php" class="quick-action-btn">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span>Schedule Appointment</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="create_ticket.php" class="quick-action-btn">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>Create Ticket</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="visitor_logs.php" class="quick-action-btn">
                                            <i class="fas fa-history"></i>
                                            <span>Visitor Logs</span>
                                        </a>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                                        <a href="help_desk.php" class="quick-action-btn">
                                            <i class="fas fa-question-circle"></i>
                                            <span>Help Desk</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Data Section -->
                <div class="row">
                    <!-- Recent Visitors -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Visitors</h5>
                                <a href="visitor_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentData['recent_visitors'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-user-slash fa-2x mb-3"></i>
                                        <p>No recent visitors found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentData['recent_visitors'] as $visitor): ?>
                                        <div class="recent-item visitor mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($visitor['Name']) ?></strong>
                                                    <div class="text-muted small">
                                                        <?= date('M j, h:i A', strtotime($visitor['CheckInTime'])) ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <small class="text-muted"><?= htmlspecialchars($visitor['Phone']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Appointments -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Upcoming Appointments</h5>
                                <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentData['recent_appointments'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                        <p>No upcoming appointments</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentData['recent_appointments'] as $appointment): ?>
                                        <div class="recent-item appointment mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($appointment['Name']) ?></strong>
                                                    <div class="text-muted small">
                                                        <?= date('M j, h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge status-badge bg-<?=
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
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tickets -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Tickets</h5>
                                <a href="help_desk.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentData['recent_tickets'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-ticket-alt fa-2x mb-3"></i>
                                        <p>No recent tickets</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentData['recent_tickets'] as $ticket): ?>
                                        <div class="recent-item ticket mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong>Ticket #<?= $ticket['TicketID'] ?></strong>
                                                    <div class="text-muted small ticket-description" title="<?= htmlspecialchars($ticket['Description']) ?>">
                                                        <?= htmlspecialchars(substr($ticket['Description'], 0, 50)) ?><?= strlen($ticket['Description']) > 50 ? '...' : '' ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= date('M j, h:i A', strtotime($ticket['CreatedAt'])) ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge status-badge bg-<?=
                                                    match($ticket['Status']) {
                                                        'open' => 'warning',
                                                        'in-progress' => 'info',
                                                        'resolved' => 'success',
                                                        default => 'secondary'
                                                    }
                                                    ?>">
                                                        <?= ucwords(str_replace('-', ' ', $ticket['Status'])) ?>
                                                    </span>
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

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize sidebar functionality
        initializeSidebar();

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

    function initializeSidebar() {
        // Remove any existing event handlers to prevent duplicates
        $('#sidebarToggle, .layout-menu-toggle').off('click');

        // Restore sidebar state from localStorage (using consistent key)
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        applySidebarState(isCollapsed);

        // Handle sidebar toggle click (unified handler for both selectors)
        $(document).on('click', '#sidebarToggle, .layout-menu-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Prevent multiple rapid clicks
            if ($(this).hasClass('toggling')) return;
            $(this).addClass('toggling');

            const $html = $('html');

            // Toggle collapsed state
            $html.toggleClass('layout-menu-collapsed');
            const newCollapsedState = $html.hasClass('layout-menu-collapsed');

            // Apply the new state
            applySidebarState(newCollapsedState);

            // Store state in localStorage (using consistent key)
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

        // Clean up old localStorage key if it exists
        if (localStorage.getItem('layoutMenuCollapsed')) {
            localStorage.removeItem('layoutMenuCollapsed');
        }
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

        // Add body class for smooth transitions
        $('body').addClass('sidebar-toggling');
        setTimeout(() => {
            $('body').removeClass('sidebar-toggling');
        }, 300);
    }
</script>
</body>
</html>