<?php
session_start();
require_once '../dbConfig.php';
global $conn;
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Host') {
    header("Location: ../Auth.html");
    exit;
}
if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();
    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}
$hostId = $_SESSION['userID'];
require_once __DIR__ . '/appointments.php';
$appointments = getHostAppointments($hostId);
?>
<!DOCTYPE html>
<html
      lang="en"
      dir="ltr"
      data-theme="theme-default"
      data-assets-path="../../Sneat/assets/"
      data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - Appointments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Sneat CSS -->
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <link rel="stylesheet" href="notification.css">
    <style>
        /* Custom colors using Sneat's CSS variables */
        .status-badge-Cancelled { background-color: var(--bs-danger); color: #fff; }
        .status-badge-Ongoing { background-color: var(--bs-info); color: #fff; }
        .status-badge-Upcoming { background-color: var(--bs-primary); color: #fff; }
        .status-badge-Completed { background-color: var(--bs-success); color: #fff; }
        .status-badge-Overdue { background-color: var(--bs-warning); color: #212529; }
        .appointment-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--bs-box-shadow) !important;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        html, body {
            overflow-x: hidden;
        }
        /* Sidebar width fixes */
        #layout-menu {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
            flex: 0 0 260px !important;
            position: fixed !important; /* Make sidebar fixed */
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important; /* Full viewport height */
            overflow-y: auto !important; /* Allow sidebar internal scrolling if needed */
            overflow-x: hidden !important;
            z-index: 1000 !important; /* Ensure it stays on top */
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
            margin-left: 260px !important; /* Push content away from fixed sidebar */
            width: calc(100% - 260px) !important;
            height: 100vh !important; /* Full viewport height */
            overflow-y: auto !important; /* Make only main content scrollable */
            overflow-x: hidden !important;
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important; /* Adjust for collapsed sidebar */
            width: calc(100% - 78px) !important;
        }

        #appointmentsList {
            min-height: 400px;
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
        }

        .appointment-item {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        /* Replace the existing #no-appointments-message CSS with this: */
        #no-appointments-message {
            width: 100%;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
            margin: 0;
        }

        /* Ensure the appointments list container handles the message properly */
        #appointmentsList {
            min-height: 400px;
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            position: relative; /* Add this to contain any absolutely positioned children if needed */
        }

        /* Make sure the message doesn't interfere with other elements */
        #no-appointments-message .d-flex {
            pointer-events: none; /* Prevent blocking clicks on the icon and text */
        }

        #no-appointments-message .d-flex * {
            pointer-events: auto; /* Allow interactions with child elements if needed */
        }

        .layout-wrapper {
            overflow: hidden !important; /* Prevent wrapper scrolling */
            height: 100vh !important; /* Fixed height */
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important; /* Prevent container scrolling */
        }


        .no-transition {
            transition: none !important;
        }

        .row {
            margin-left: 0;
            margin-right: 0;
        }

        .container-fluid {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            max-width: none;
        }
        /* Ensure body and html don't have extra scrollbars */
        html, body {
            overflow-x: hidden !important;
            overflow-y: hidden !important; /* Only main content should scroll */
            height: 100vh !important;
        }

        /* Fix sidebar internal scrolling */
        .menu-inner {
            height: calc(100vh - 80px) !important; /* Account for header */
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        /* Ensure sidebar header stays at top */
        .app-brand {
            position: sticky !important;
            top: 0 !important;
            background: inherit !important;
            z-index: 1001 !important;
        }

        /* Fix content padding to prevent content from going behind sidebar */
        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        /* Smooth transitions for sidebar collapse/expand */
        .layout-menu {
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease !important;
        }

        .layout-content {
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }

        /* Disable transitions during filtering to prevent layout shifts */
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

    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'host-sidebar.php'; ?>
        <div class="layout-content">
            <div class="container-fluid container-p-y">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h1 class="mb-3">Appointments</h1>
                        <p class="text-muted">Manage your upcoming, ongoing, and past appointments</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                            <i class="fas fa-plus-circle me-2"></i> Schedule New Appointment
                        </button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary active filter-btn" data-filter="all">All</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Upcoming">Upcoming</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Overdue">Overdue</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Ongoing">Ongoing</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Completed">Completed</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Cancelled">Cancelled</button>
                        </div>
                    </div>
                </div>
                <div class="row" id="appointmentsList">
                    <?php if (empty($appointments)): ?>
                        <div class="col-12 text-center py-5">
                            <h4 class="text-muted">No appointments found</h4>
                            <p>Schedule an appointment by clicking the button above</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="col-md-6 col-lg-4 mb-4 appointment-item" data-status="<?= $appointment['Status'] ?>">
                                <div class="card appointment-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span class="badge rounded-pill text-bg-<?= $appointment['Status'] ?>"><?= $appointment['Status'] ?></span>
                                        <small><?= date('M d, Y', strtotime($appointment['AppointmentTime'])) ?></small>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($appointment['Name']) ?></h5>
                                        <p class="card-text mb-1">
                                            <i class="far fa-envelope me-2"></i> <?= htmlspecialchars($appointment['Email']) ?>
                                        </p>
                                        <p class="card-text mb-3">
                                            <i class="far fa-clock me-2"></i> <?= date('h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                        </p>
                                        <?php if ($appointment['Status'] == 'Cancelled'): ?>
                                            <p><strong>Cancellation Reason:</strong> <?= htmlspecialchars($appointment['CancellationReason']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($appointment['Status'] !== 'Cancelled'): ?>
                                            <div class="action-buttons">
                                                <?php if ($appointment['Status'] === 'Upcoming'): ?>
                                                    <button class="btn btn-sm btn-outline-success start-session-btn" data-id="<?= $appointment['AppointmentID'] ?>">
                                                        <i class="fas fa-play-circle me-1"></i> Start Session
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary reschedule-btn" data-id="<?= $appointment['AppointmentID'] ?>" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                                                        <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger cancel-btn" data-id="<?= $appointment['AppointmentID'] ?>">
                                                        <i class="fas fa-times-circle me-1"></i> Cancel
                                                    </button>
                                                <?php elseif ($appointment['Status'] === 'Ongoing'): ?>
                                                    <button class="btn btn-sm btn-outline-warning end-session-btn" data-id="<?= $appointment['AppointmentID'] ?>">
                                                        <i class="fas fa-stop-circle me-1"></i> End Session
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
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

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" name="action" value="schedule">
                    <input type="hidden" name="hostId" value="<?= $hostId ?>">
                    <input type="hidden" name="isNewVisitor" id="isNewVisitor" value="0">
                    <div class="mb-3" id="visitorSelectContainer">
                        <label for="visitorSelect" class="form-label">Select Visitor</label>
                        <select class="form-select" id="visitorSelect" name="visitorId">
                            <option value="">-- Select Visitor --</option>
                            <option value="new">-- Add New Visitor --</option>
                        </select>
                    </div>
                    <div id="newVisitorFields" style="display: none;">
                        <div class="mb-3">
                            <label for="newVisitorName" class="form-label">Visitor Name</label>
                            <input type="text" class="form-control" id="newVisitorName" name="newVisitorName">
                        </div>
                        <div class="mb-3">
                            <label for="newVisitorEmail" class="form-label">Visitor Email</label>
                            <input type="email" class="form-control" id="newVisitorEmail" name="newVisitorEmail">
                        </div>
                        <div class="mb-3">
                            <label for="newVisitorPhone" class="form-label">Visitor Phone (Optional)</label>
                            <input type="tel" class="form-control" id="newVisitorPhone" name="newVisitorPhone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="appointmentDateTime" class="form-label">Appointment Date & Time</label>
                        <input type="text" class="form-control" id="appointmentDateTime" name="appointmentTime" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="scheduleBtn">Schedule Appointment</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rescheduleModalLabel">Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rescheduleForm">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="appointmentId" id="rescheduleAppointmentId">
                    <div class="mb-3">
                        <p><strong>Visitor:</strong> <span id="rescheduleVisitorName"></span></p>
                    </div>
                    <div class="mb-3">
                        <label for="newDateTime" class="form-label">New Date & Time</label>
                        <input type="text" class="form-control" id="newDateTime" name="newTime" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="rescheduleBtn">Reschedule</button>
            </div>
        </div>
    </div>
</div>

<div class="notification-wrapper">
    <div class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-count" id="notificationCount">0</span>
    </div>
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button id="markAllReadBtn" class="mark-all-read">Mark All Read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="empty-notification">No notifications</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>
<script src="notification.js"></script>
<script>

    $(document).ready(function() {
        flatpickr("#appointmentDateTime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true
        });
        flatpickr("#newDateTime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true
        });
        $.ajax({
            url: 'appointments.php',
            type: 'POST',
            data: { action: 'getVisitors' },
            success: function(response) {
                if (response.length > 0) {
                    response.forEach(function(visitor) {
                        $('#visitorSelect').append(
                            $('<option></option>').val(visitor.VisitorID).text(visitor.Name + ' (' + visitor.Email + ')')
                        );
                    });
                }
            }
        });
        $('#visitorSelect').change(function() {
            if ($(this).val() === 'new') {
                $('#newVisitorFields').show();
                $('#isNewVisitor').val('1');
                $('#newVisitorName, #newVisitorEmail').prop('required', true);
                $(this).prop('required', false);
            } else {
                $('#newVisitorFields').hide();
                $('#isNewVisitor').val('0');
                $('#newVisitorName, #newVisitorEmail').prop('required', false);
                $(this).prop('required', true);
            }
        });
        // Store original appointment count for reference
        const totalAppointments = $('.appointment-item').length;

        // Improved filter function
        $('.filter-btn').click(function() {
            // Prevent any layout transitions during filtering
            $('.layout-menu, .layout-content, #layout-menu').addClass('no-transition');

            // Update active button
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');

            const filter = $(this).data('filter');

            // Hide existing "no appointments" message
            $('#no-appointments-message').remove();

            // Filter appointments with smooth animation
            $('.appointment-item').each(function() {
                const $item = $(this);
                const itemStatus = $item.data('status');

                if (filter === 'all' || itemStatus === filter) {
                    $item.fadeIn(300);
                } else {
                    $item.fadeOut(300);
                }
            });

            // Check if any items will be visible after animation
            setTimeout(function() {
                let visibleItems;
                if (filter === 'all') {
                    visibleItems = $('.appointment-item');
                } else {
                    visibleItems = $(`.appointment-item[data-status="${filter}"]`);
                }

                if (visibleItems.length === 0) {
                    // Create and show no appointments message
                    const noAppointmentsHtml = `
                <div id="no-appointments-message" class="col-12 text-center py-5" style="display: none;">
                    <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 200px;">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted mb-2">No ${filter === 'all' ? '' : filter.toLowerCase()} appointments found</h4>
                        <p class="text-muted">
                            ${filter === 'all'
                        ? 'Schedule an appointment by clicking the button above'
                        : `You currently have no ${filter.toLowerCase()} appointments`
                    }
                        </p>
                    </div>
                </div>
            `;
                    $('#appointmentsList').append(noAppointmentsHtml);
                    $('#no-appointments-message').fadeIn(300);
                }

                // Re-enable transitions after filtering is complete
                setTimeout(() => {
                    $('.layout-menu, .layout-content, #layout-menu').removeClass('no-transition');
                }, 100);

            }, 350); // Wait for fade animations to complete
        });
        $('#scheduleBtn').click(function() {
            let valid = true;
            if ($('#isNewVisitor').val() === '1') {
                if (!$('#newVisitorName').val()) { alert('Please enter visitor name'); valid = false; }
                if (!$('#newVisitorEmail').val()) { alert('Please enter visitor email'); valid = false; }
            } else {
                if (!$('#visitorSelect').val() || $('#visitorSelect').val() === '') { alert('Please select a visitor'); valid = false; }
            }
            if (!$('#appointmentDateTime').val()) { alert('Select appointment date and time'); valid = false; }
            if (!valid) return;
            const formData = $('#scheduleForm').serialize();
            $.ajax({
                url: 'appointments.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || "Unknown error occurred");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    alert('An error occurred. Check console.');
                }
            });
        });
        $(document).on('click', '.start-session-btn', function() {
            if (confirm('Start session?')) {
                const appointmentId = $(this).data('id');
                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: { action: 'startSession', appointmentId: appointmentId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message || "Unknown error occurred");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.log("Response text:", xhr.responseText);
                        alert('An error occurred. Check console.');
                    }
                });
            }
        });
        $('.reschedule-btn').click(function() {
            const appointmentId = $(this).data('id');
            $('#rescheduleAppointmentId').val(appointmentId);
            $.ajax({
                url: 'appointments.php',
                type: 'POST',
                data: { action: 'getAppointment', appointmentId: appointmentId },
                success: function(response) {
                    if (response) {
                        $('#rescheduleVisitorName').text(response.Name);
                        $('#newDateTime').val(response.AppointmentTime);
                    }
                }
            });
        });
        $('#rescheduleBtn').click(function() {
            const formData = $('#rescheduleForm').serialize();
            $.ajax({
                url: 'appointments.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || "Unknown error occurred");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    alert('An error occurred. Check console.');
                }
            });
        });
        $(document).on('click', '.end-session-btn', function() {
            if (confirm('Are you sure you want to end this session?')) {
                const appointmentId = $(this).data('id');
                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: { action: 'endSession', appointmentId: appointmentId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message || "Unknown error occurred");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.log("Response text:", xhr.responseText);
                        alert('An error occurred. Check console.');
                    }
                });
            }
        });
        $('.cancel-btn').click(function() {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                const appointmentId = $(this).data('id');
                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: { action: 'cancel', appointmentId: appointmentId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message || "Unknown error occurred");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.log("Response text:", xhr.responseText);
                        alert('An error occurred. Check console.');
                    }
                });
            }
        });
        // enhance the toggle functionality
        $(document).ready(function() {
            // Function to update tooltip text
            function updateTooltip() {
                const $toggle = $('.layout-menu-toggle');
                const isCollapsed = $('html').hasClass('layout-menu-collapsed');

                if (isCollapsed) {
                    $toggle.attr('data-tooltip', 'Expand Menu');
                    $toggle.attr('title', 'Expand Menu');
                } else {
                    $toggle.attr('data-tooltip', 'Collapse Menu');
                    $toggle.attr('title', 'Collapse Menu');
                }
            }

            // Enhanced menu toggle with better UX
            $('.layout-menu-toggle').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $html = $('html');
                const $sidebar = $('#layout-menu');
                const $toggle = $(this);

                // Add a small loading state
                $toggle.css('pointer-events', 'none');

                $html.toggleClass('layout-menu-collapsed');
                const isCollapsed = $html.hasClass('layout-menu-collapsed');

                // Explicitly set sidebar width with smooth transition
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

                // Update tooltip
                updateTooltip();

                // Store preference
                localStorage.setItem('layoutMenuCollapsed', isCollapsed);

                // Re-enable clicking after animation
                setTimeout(() => {
                    $toggle.css('pointer-events', 'auto');
                }, 300);
            });

            // Initialize on page load
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

            // Set initial tooltip
            updateTooltip();

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

            // Add keyboard support for accessibility
            $('.layout-menu-toggle').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
        });

// Add CSS class to body when sidebar is being toggled for additional styling
        $(document).on('click', '.layout-menu-toggle', function() {
            $('body').addClass('sidebar-toggling');
            setTimeout(() => {
                $('body').removeClass('sidebar-toggling');
            }, 300);
        });

    });
</script>
</body>
</html>