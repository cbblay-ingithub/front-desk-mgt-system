<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <link rel="stylesheet" href="notification.css">
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
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }
        .appointment-card {
            transition: all 0.3s ease;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-badge-Cancelled {
            background-color: #dc3545;
        }
        .status-badge-Ongoing {
            background-color: #66b2b2;
        }
        .status-badge-Upcoming {
            background-color: #9133ef;
        }
        .status-badge-Completed {
            background-color: #198754;
        }
        .status-badge-Overdue {
            background-color: #ffc107;
            color: #212529;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>

<?php
// Start session to get current host ID
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Host') {
    // Redirect if not logged in as host
    header("Location: ../Auth.html");
    exit;
}

$hostId = $_SESSION['userID'];
require_once __DIR__ . '/appointments.php';

// Get all appointments for the host
$appointments = getHostAppointments($hostId);
?>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-white text-center">Host Panel</h4>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="host_dashboard.php" class="active"><i class="fas fa-calendar-check me-2"></i> Manage Appointments</a>
    <a href="staff_tickets.php"><i class="fas fa-ticket"></i> Help Desk Tickets</a>
    <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="container py-4">
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

        <!-- Appointment Filters -->
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

        <!-- Appointments List -->
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
                                <span class="badge status-badge-<?= $appointment['Status'] ?>"><?= $appointment['Status'] ?></span>
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
                                    <p><strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($appointment['CancellationReason']); ?></p>
                                <?php endif; ?>

                                <?php if ($appointment['Status'] !== 'Cancelled'): ?>
                                    <div class="action-buttons">
                                        <?php if ($appointment['Status'] === 'Upcoming'): ?>
                                            <button class="btn btn-sm btn-outline-success start-session-btn"
                                                    data-id="<?= $appointment['AppointmentID'] ?>">
                                                <i class="fas fa-play-circle me-1"></i> Start Session
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary reschedule-btn"
                                                    data-id="<?= $appointment['AppointmentID'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rescheduleModal">
                                                <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger cancel-btn"
                                                    data-id="<?= $appointment['AppointmentID'] ?>">
                                                <i class="fas fa-times-circle me-1"></i> Cancel
                                            </button>
                                        <?php elseif ($appointment['Status'] === 'Ongoing'): ?>
                                            <button class="btn btn-sm btn-outline-warning end-session-btn"
                                                    data-id="<?= $appointment['AppointmentID'] ?>">
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

<!-- Schedule Modal -->
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
                            <!-- populated with AJAX -->
                        </select>
                    </div>

                    <!-- New Visitor Fields (initially hidden) -->
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

<!-- Reschedule Modal -->
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
<!-- Notification System -->
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
            <!-- Notifications will be inserted here -->
            <div class="empty-notification">No notifications</div>
        </div>
    </div>
</div>

<!-- JavaScript Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script src="notification.js"></script>
<script>
    $(document).ready(function() {
        // Initialize datetime pickers
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

        // Load visitors for dropdown
        $.ajax({
            url: 'appointments.php',
            type: 'POST',
            data: {
                action: 'getVisitors'
            },
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

        // Toggle new visitor fields when "Add New Visitor" is selected
        $('#visitorSelect').change(function() {
            if ($(this).val() === 'new') {
                $('#newVisitorFields').show();
                $('#isNewVisitor').val('1');
                // Make new visitor fields required
                $('#newVisitorName, #newVisitorEmail').prop('required', true);
                // Make regular visitor select not required
                $(this).prop('required', false);
            } else {
                $('#newVisitorFields').hide();
                $('#isNewVisitor').val('0');
                // Make new visitor fields not required
                $('#newVisitorName, #newVisitorEmail').prop('required', false);
                // Make regular visitor select required if not "new"
                $(this).prop('required', true);
            }
        });

        // Handle filter buttons
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');

            const filter = $(this).data('filter');
            if (filter === 'all') {
                $('.appointment-item').show();
            } else {
                $('.appointment-item').hide();
                $(`.appointment-item[data-status="${filter}"]`).show();
            }
        });

        // Handle Schedule Appointment
        $('#scheduleBtn').click(function() {
            // Validate the form based on which mode we're in
            let valid = true;

            if ($('#isNewVisitor').val() === '1') {
                // Validate new visitor fields
                if (!$('#newVisitorName').val()) {
                    alert('Please enter visitor name');
                    valid = false;
                }
                if (!$('#newVisitorEmail').val()) {
                    alert('Please enter visitor email');
                    valid = false;
                }
            } else {
                // Validate existing visitor selection
                if (!$('#visitorSelect').val() || $('#visitorSelect').val() === '') {
                    alert('Please select a visitor');
                    valid = false;
                }
            }

            // Validate appointment time
            if (!$('#appointmentDateTime').val()) {
                alert('Select appointment date and time');
                valid = false;
            }

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
                        location.reload(); // Refresh to show new appointment
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

        // Handle Start Session button click
        $(document).on('click', '.start-session-btn', function() {
            if (confirm('Start session?')) {
                const appointmentId = $(this).data('id');

                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: {
                        action: 'startSession',
                        appointmentId: appointmentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show updated status
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

        // Handle Reschedule button click
        $('.reschedule-btn').click(function() {
            const appointmentId = $(this).data('id');
            $('#rescheduleAppointmentId').val(appointmentId);

            // Get appointment details
            $.ajax({
                url: 'appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointment',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#rescheduleVisitorName').text(response.Name);
                        $('#newDateTime').val(response.AppointmentTime);
                    }
                }
            });
        });

        // Handle Reschedule form submission
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
                        location.reload(); // Refresh to show updated appointment
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

        // Handle End Session button click
        $(document).on('click', '.end-session-btn', function() {
            if (confirm('Are you sure you want to end this session?')) {
                const appointmentId = $(this).data('id');

                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: {
                        action: 'endSession',
                        appointmentId: appointmentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show updated status
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

        // Handle Cancel button click
        $('.cancel-btn').click(function() {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                const appointmentId = $(this).data('id');

                $.ajax({
                    url: 'appointments.php',
                    type: 'POST',
                    data: {
                        action: 'cancel',
                        appointmentId: appointmentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show cancelled status
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
    });
</script>
</body>
</html>