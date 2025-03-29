<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
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
            background-color: #00FFFF;
        }
        .status-badge-Upcoming {
            background-color: #FFB343;
        }
        .status-badge-Completed {
            background-color: #198754;
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
require_once 'appointments.php';

// Get all appointments for the host
$appointments = getHostAppointments($hostId);
?>
<div class="sidebar">
    <h4 class="text-white text-center">Host Panel</h4>
    <a href="host_dashboard.php">Manage Appointments</a>
</div>
<div class="container py-5">
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
                <p>Schedule your first appointment by clicking the button above</p>
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

                            <?php if ($appointment['Status'] !== 'Cancelled'): ?>
                                <!-- In the appointments list section, modify the action buttons div -->
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
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

                    <div class="mb-3">
                        <label for="visitorSelect" class="form-label">Select Visitor</label>
                        <select class="form-select" id="visitorSelect" name="visitorId" required>
                            <option value="">-- Select Visitor --</option>
                            <!-- Will be populated via AJAX -->
                        </select>
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

<!-- JavaScript Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
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
            const formData = $('#scheduleForm').serialize();

            $.ajax({
                url: 'appointments.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload(); // Refresh to show new appointment
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
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
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error! Please try again.');
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
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload(); // Refresh to show updated appointment
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error!, Please try again.');
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
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error! Please try again.');
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
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload(); // Refresh to show cancelled status
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error!, Please try again.');
                    }
                });
            }
        });
    });
</script>
</body>
</html>