<?php
session_start();
require_once '../dbConfig.php';

global $conn;
// Fetch visitors
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM visitor_Logs vl 
         WHERE vl.VisitorID = v.VisitorID 
         AND vl.CheckOutTime IS NULL) AS is_checked_in,
         a.BadgeNumber
        FROM visitors v
        LEFT JOIN appointments a ON v.VisitorID = a.VisitorID";
$visitors = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $visitors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Manage Visitors- front desk</title>
    <!-- Main CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notification.css">

    <!-- Sneat CSS (same as host_dashboard) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

    <style>
        /* Layout structure matching host_dashboard */
        html, body {
            height: 100%;
            overflow-x: hidden !important;
        }

        .layout-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
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
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease !important;
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
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

        /* Fix content padding */
        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        /* Disable transitions during filtering */
        .no-transition {
            transition: none !important;
        }

        /* Search and filter styles */
        .search-filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }

        .search-input {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .filter-btn {
            border-radius: 20px;
            padding: 8px 16px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        /* Table enhancements */
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .badge-number {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-checked-in {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-not-checked-in {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .action-btn {
            margin: 2px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        /* Modal enhancements */
        .visitor-detail-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .visitor-detail-modal .modal-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .detail-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #212529;
            font-size: 1.1rem;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        /* Loading animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'frontdesk-sidebar.php'; ?>

        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base bx bx-menu icon-md"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!--Page Title-->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2"> Manage Visitors</h4>
                        </div>
                    </div>
                    <!--Check-In button-->
                    <div class="navbar-nav align-items-center me-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkInModal">
                            <i class="fas fa-plus-circle me-2"></i> Check-In Visitor
                        </button>
                    </div>

            </nav>
            <div class="container-fluid container-p-y">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['msg'] === 'checked_in') {
                            echo "Visitor successfully checked in.";
                        } elseif ($_GET['msg'] === 'checked_out') {
                            echo "Visitor successfully checked out.";
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter Section -->
                <div class="search-filter-section">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control search-input border-start-0"
                                       placeholder="Search by name, email, badge number, phone, or ID number...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end flex-wrap">
                                <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                                    <i class="fas fa-users me-1"></i> All (<span id="count-all">0</span>)
                                </button>
                                <button class="btn btn-outline-success filter-btn" data-filter="checked-in">
                                    <i class="fas fa-check-circle me-1"></i> Checked In (<span id="count-checked-in">0</span>)
                                </button>
                                <button class="btn btn-outline-danger filter-btn" data-filter="not-checked-in">
                                    <i class="fas fa-times-circle me-1"></i> Not Checked In (<span id="count-not-checked-in">0</span>)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visitors Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="visitorsTable">
                        <thead class="table-dark">
                        <tr>
                            <th>Badge</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="visitorsTableBody">
                        <?php foreach ($visitors as $v): ?>
                            <tr data-status="<?= $v['is_checked_in'] > 0 ? 'checked-in' : 'not-checked-in' ?>">
                                <td>
                                    <span class="badge-number"><?= htmlspecialchars($v['BadgeNumber']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($v['Name']) ?></td>
                                <td><?= htmlspecialchars($v['Email']) ?></td>
                                <td><?= htmlspecialchars($v['Phone']) ?></td>
                                <td>
                                    <span class="status-badge <?= $v['is_checked_in'] > 0 ? 'status-checked-in' : 'status-not-checked-in' ?>">
                                        <?= $v['is_checked_in'] > 0 ? 'Checked In' : 'Not Checked In' ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- View Details Button -->
                                    <button class="btn btn-info action-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#visitorDetailModal"
                                            onclick="showVisitorDetails(<?= htmlspecialchars(json_encode($v)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if ($v['is_checked_in'] > 0): ?>
                                        <form method="POST" action="process_visit.php" class="d-inline">
                                            <input type="hidden" name="action" value="check_out">
                                            <input type="hidden" name="visitor_id" value="<?= $v['VisitorID'] ?>">
                                            <button type="submit" class="btn btn-danger action-btn">
                                                <i class="fas fa-sign-out-alt"></i> Check Out
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>

                    <!-- No Results Message -->
                    <div id="noResults" class="no-results" style="display: none;">
                        <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                        <h5>No visitors found</h5>
                        <p>Try adjusting your search criteria or filters</p>
                    </div>
                </div>

                <form action="generate_report.php" method="POST" class="mt-4 text-end">
                    <button type="submit" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-file-export me-2"></i>Generate Visitor Logs
                    </button>
                </form>
            </div>

            <!-- Check In Modal -->
            <div class="modal fade" id="checkInModal" tabindex="-1" aria-labelledby="checkInModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="process_visit.php" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Check In Visitor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body row g-2">
                            <div class="col-12">
                                <input class="form-control" name="name" placeholder="Name" required>
                            </div>
                            <div class="col-6">
                                <input class="form-control" name="email" type="email" placeholder="Email" required>
                            </div>
                            <div class="col-6">
                                <input class="form-control" name="phone" placeholder="Phone">
                            </div>
                            <div class="col-6">
                                <input class="form-control" name="id_type" placeholder="ID Type">
                            </div>
                            <div class="col-6">
                                <input class="form-control" name="id_number" placeholder="ID Number">
                            </div>
                            <div class="col-6">
                                <input class="form-control" name="visit_purpose" placeholder="Purpose of Visit">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="action" value="check_in" class="btn btn-success" href="front-staff-dash.php">Check In</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Visitor Details Modal -->
            <div class="modal fade visitor-detail-modal" id="visitorDetailModal" tabindex="-1" aria-labelledby="visitorDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="visitorDetailModalLabel">
                                <i class="fas fa-user-circle me-2"></i>Visitor Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-id-badge me-2 text-primary"></i>Badge Number
                                        </div>
                                        <div class="detail-value" id="modal-badge"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-user me-2 text-primary"></i>Full Name
                                        </div>
                                        <div class="detail-value" id="modal-name"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-envelope me-2 text-primary"></i>Email Address
                                        </div>
                                        <div class="detail-value" id="modal-email"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-phone me-2 text-primary"></i>Phone Number
                                        </div>
                                        <div class="detail-value" id="modal-phone"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-id-card me-2 text-primary"></i>ID Type
                                        </div>
                                        <div class="detail-value" id="modal-id-type"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-hashtag me-2 text-primary"></i>ID Number
                                        </div>
                                        <div class="detail-value" id="modal-id-number"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-info-circle me-2 text-primary"></i>Current Status
                                        </div>
                                        <div class="detail-value" id="modal-status"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="notification.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Include Sneat JS files like host_dashboard does -->
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>

<script>
    // Global variables for filtering and searching
    let currentFilter = 'all';
    let currentSearch = '';

    $(document).ready(function() {
        // Initialize counts
        updateCounts();

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

        // Handle menu link tooltips in collapsed state
        $(document).on('mouseenter', '.layout-menu-collapsed .menu-link', function() {
            if ($('html').hasClass('layout-menu-collapsed')) {
                $(this).attr('title', $(this).data('tooltip'));
            }
        }).on('mouseleave', '.layout-menu-collapsed .menu-link', function() {
            $(this).removeAttr('title');
        });

        // Search functionality
        $('#searchInput').on('input', function() {
            currentSearch = $(this).val().toLowerCase();
            filterAndSearch();
        });

        // Filter functionality
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            filterAndSearch();
        });
    });

    // Function to filter and search visitors
    function filterAndSearch() {
        const rows = $('#visitorsTableBody tr');
        let visibleCount = 0;

        rows.each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const text = $row.text().toLowerCase();

            // Check filter
            let matchesFilter = false;
            if (currentFilter === 'all') {
                matchesFilter = true;
            } else if (currentFilter === 'checked-in') {
                matchesFilter = status === 'checked-in';
            } else if (currentFilter === 'not-checked-in') {
                matchesFilter = status === 'not-checked-in';
            }

            // Check search
            const matchesSearch = currentSearch === '' || text.includes(currentSearch);

            // Show/hide row
            if (matchesFilter && matchesSearch) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        // Show/hide no results message
        if (visibleCount === 0) {
            $('#noResults').show();
        } else {
            $('#noResults').hide();
        }

        updateCounts();
    }

    // Function to update counts in filter buttons
    function updateCounts() {
        const allRows = $('#visitorsTableBody tr');
        const checkedInRows = $('#visitorsTableBody tr[data-status="checked-in"]');
        const notCheckedInRows = $('#visitorsTableBody tr[data-status="not-checked-in"]');

        $('#count-all').text(allRows.length);
        $('#count-checked-in').text(checkedInRows.length);
        $('#count-not-checked-in').text(notCheckedInRows.length);
    }

    // Function to show visitor details in modal
    function showVisitorDetails(visitor) {
        $('#modal-badge').text(visitor.BadgeNumber || 'N/A');
        $('#modal-name').text(visitor.Name || 'N/A');
        $('#modal-email').text(visitor.Email || 'N/A');
        $('#modal-phone').text(visitor.Phone || 'N/A');
        $('#modal-id-type').text(visitor.IDType || 'N/A');
        $('#modal-id-number').text(visitor.IDNumber || 'N/A');

        // Format status with badge
        const isCheckedIn = visitor.is_checked_in > 0;
        const statusHtml = `<span class="status-badge ${isCheckedIn ? 'status-checked-in' : 'status-not-checked-in'}">
            ${isCheckedIn ? 'Checked In' : 'Not Checked In'}
        </span>`;
        $('#modal-status').html(statusHtml);

        // Format registration date if available
        const regDate = visitor.CreatedAt || visitor.created_at || 'N/A';
        if (regDate !== 'N/A') {
            const formattedDate = new Date(regDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            $('#modal-registration').text(formattedDate);
        } else {
            $('#modal-registration').text('N/A');
        }
    }

    // Add some visual feedback for loading states
    function showLoading() {
        $('#visitorsTable').addClass('loading');
    }

    function hideLoading() {
        $('#visitorsTable').removeClass('loading');
    }

    // Enhanced search with debouncing for better performance
    let searchTimeout;
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        showLoading();

        searchTimeout = setTimeout(function() {
            currentSearch = $('#searchInput').val().toLowerCase();
            filterAndSearch();
            hideLoading();
        }, 300); // Wait 300ms after user stops typing
    });
</script>
</body>
</html>