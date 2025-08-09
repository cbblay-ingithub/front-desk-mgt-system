<?php
session_start();
require_once '../dbConfig.php';

global $conn;
// Fetch visitors
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM visitor_Logs vl 
         WHERE vl.VisitorID = v.VisitorID 
         AND vl.CheckOutTime IS NULL) AS is_checked_in 
        FROM visitors v";
$visitors = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $visitors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse"">
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

                <!-- Visitors Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                        <tr>
                            <th>Name</th><th>Email</th><th>Phone</th>
                            <th>ID Type</th><th>ID Number</th><th>Status</th><th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['Name']) ?></td>
                                <td><?= htmlspecialchars($v['Email']) ?></td>
                                <td><?= htmlspecialchars($v['Phone']) ?></td>
                                <td><?= htmlspecialchars($v['IDType']) ?></td>
                                <td><?= htmlspecialchars($v['IDNumber']) ?></td>
                                <td><?= $v['is_checked_in'] > 0 ? 'Checked In' : 'Not Checked In' ?></td>
                                <td>
                                    <?php if ($v['is_checked_in'] > 0): ?>
                                        <form method="POST" action="process_visit.php" class="d-inline">
                                            <input type="hidden" name="action" value="check_out">
                                            <input type="hidden" name="visitor_id" value="<?= $v['VisitorID'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Check Out</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <form action="generate_report.php" method="POST" class="mt-4 text-end">
                    <button type="submit" class="btn btn-outline-dark btn-sm">Generate Visitor Logs</button>
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
    // Sidebar toggle functionality matching host_dashboard
    $(document).ready(function() {
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
    });
</script>
</body>
</html>