<?php
// Start session to get current front desk user ID
session_start();
    $userID = $_SESSION['userID'] ?? null;
    if (!$userID) {
        // Redirect to login page or show error
        header("Location: ../Auth.html");
        exit;
    }

    require_once 'front_desk_appointments.php';

    // Get all appointments
    $appointments = getAllAppointments();

    // Get statistics
    $stats = getAppointmentStats();

    // Get all hosts for dropdown
    $hosts = getAllHosts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Front Desk Dashboard - Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <link rel ="stylesheet" href ="notification.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: row;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
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
            overflow-x: auto;
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
            background-color: #FFB343;
        }

        .status-badge-Completed {
            background-color: #198754;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .search-form {
            margin-bottom: 20px;
        }

        .stats-card {
            border-left: 4px solid;
            margin-bottom: 20px;
        }

        .stats-card.primary {
            border-left-color: #0d6efd;
        }

        .stats-card.success {
            border-left-color: #198754;
        }

        .stats-card.warning {
            border-left-color: #ffc107;
        }

        .stats-card.danger {
            border-left-color: #dc3545;
        }

        .calendar-view {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-day {
            height: 100px;
            overflow-y: auto;
        }

        .calendar-appointment {
            padding: 2px 5px;
            margin-bottom: 2px;
            border-radius: 3px;
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        .calendar-appointment.Upcoming {
            background-color: #cff4fc;
            border-left: 3px solid #FFB343;
        }

        .calendar-appointment.Ongoing {
            background-color: #fff3cd;
            border-left: 3px solid #66b2b2;
        }

        .calendar-appointment.Completed {
            background-color: #d1e7dd;
            border-left: 3px solid #198754;
        }

        .calendar-appointment.Cancelled {
            background-color: #f8d7da;
            border-left: 3px solid #dc3545;
        }

        .host-filter {
            margin-bottom: 20px;
        }
    </style>

</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-white text-center">Front Desk Panel</h4>
    <a href="front-desk_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="visitor-mgt.php"><i class="fas fa-users me-2"></i>Manage Visitors</a>
    <a href="FD_frontend_dash.php"><i class="fas fa-calendar-check me-2"></i> Appointments</a>
    <a href="helpdesk.php"><i class="fas fa-ticket"></i> Help Desk Tickets</a>
    <a href="lost_found.php"><i class="fa-solid fa-suitcase"></i> View Lost & Found</a>
    <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>


</div>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-3">Manage Appointments</h1>
                <p class="text-muted">View and manage all visitor appointments</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="fas fa-plus-circle me-2"></i> Schedule New Appointment
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Total</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $stats['total'] ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-primary text-white rounded-circle p-2">
                                    <i class="fas fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card warning">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Upcoming</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $stats['upcoming'] ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-warning text-white rounded-circle p-2">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card success">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Completed</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $stats['completed'] ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-success text-white rounded-circle p-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card danger">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Cancelled</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $stats['cancelled'] ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-danger text-white rounded-circle p-2">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Toggle Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="listViewBtn">
                        <i class="fas fa-list me-2"></i> List View
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="calendarViewBtn">
                        <i class="fas fa-calendar-alt me-2"></i> Calendar View
                    </button>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <form class="search-form d-flex">
                    <input type="text" class="form-control me-2" id="searchInput" placeholder="Search by visitor name, email, or host...">
                    <button type="button" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <div class="host-filter">
                    <select class="form-select" id="hostFilter">
                        <option value="">All Hosts</option>
                        <?php foreach ($hosts as $host): ?>
                            <option value="<?= $host['UserID'] ?>" data-host-name="<?= htmlspecialchars($host['Name']) ?>">
                                <?= htmlspecialchars($host['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Status Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="today">Today</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Upcoming">Upcoming</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Ongoing">Ongoing</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Completed">Completed</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="Cancelled">Cancelled</button>
                </div>
            </div>
        </div>

        <!-- List View (default) -->
        <div id="listView">
            <div class="row" id="appointmentsList">
                <?php if (empty($appointments)): ?>
                    <div class="col-12 text-center py-5">
                        <h4 class="text-muted">No appointments found</h4>
                        <p>Schedule an appointment by clicking the button above</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="col-md-6 col-lg-4 mb-4 appointment-item"
                             data-status="<?= $appointment['Status'] ?>"
                             data-host="<?= $appointment['HostName']?>"
                             data-host-id="<?= $appointment['HostID'] ?>"
                             data-date="<?= date('Y-m-d', strtotime($appointment['AppointmentTime'])) ?>"
                             data-search="<?= strtolower($appointment['VisitorName'] . ' ' . $appointment['VisitorEmail'] . ' ' . $appointment['HostName']) ?>">
                            <div class="card appointment-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge status-badge-<?= $appointment['Status'] ?>"><?= $appointment['Status'] ?></span>
                                    <small><?= date('M d, Y', strtotime($appointment['AppointmentTime'])) ?></small>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($appointment['VisitorName']) ?></h5>
                                    <p class="card-text mb-1">
                                        <i class="far fa-envelope me-2"></i> <?= htmlspecialchars($appointment['VisitorEmail']) ?>
                                    </p>
                                    <p class="card-text mb-1">
                                        <i class="far fa-clock me-2"></i> <?= date('h:i A', strtotime($appointment['AppointmentTime'])) ?>
                                    </p>
                                    <p class="card-text mb-3">
                                        <i class="fas fa-user-tie me-2"></i> <?= htmlspecialchars($appointment['HostName']) ?>
                                    </p>

                                    <?php if ($appointment['Status'] !== 'Cancelled' && $appointment['Status'] !== 'Completed'): ?>
                                        <div class="action-buttons">
                                            <?php if ($appointment['Status'] === 'Upcoming'): ?>
                                                <button class="btn btn-sm btn-outline-success check-in-btn"
                                                        data-id="<?= $appointment['AppointmentID'] ?>">
                                                    <i class="fas fa-check-circle me-1"></i> Check In
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
                                                <button class="btn btn-sm btn-outline-warning complete-session-btn"
                                                        data-id="<?= $appointment['AppointmentID'] ?>">
                                                    <i class="fas fa-check-double me-1"></i> Complete
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

        <!-- Calendar View (initially hidden) -->
        <div id="calendarView" style="display: none;">
            <div class="calendar-view">
                <div class="calendar-header">
                    <button class="btn btn-sm btn-outline-primary" id="prevMonth">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h4 id="currentMonth"></h4>
                    <button class="btn btn-sm btn-outline-primary" id="nextMonth">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                    </thead>
                    <tbody id="calendarBody">
                    <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" name="action" value="schedule">
                    <input type="hidden" name="isNewVisitor" id="isNewVisitor" value="0">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hostSelect" class="form-label">Select Host</label>
                            <select class="form-select" id="hostSelect" name="hostId" required>
                                <option value="">-- Select Host --</option>
                                <?php foreach ($hosts as $host): ?>
                                    <option value="<?= $host['UserID'] ?>"><?= htmlspecialchars($host['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="appointmentDateTime" class="form-label">Appointment Date & Time</label>
                            <input type="text" class="form-control" id="appointmentDateTime" name="appointmentTime" required>
                        </div>
                    </div>

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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="newVisitorName" class="form-label">Visitor Name</label>
                                <input type="text" class="form-control" id="newVisitorName" name="newVisitorName">
                            </div>
                            <div class="col-md-6">
                                <label for="newVisitorEmail" class="form-label">Visitor Email</label>
                                <input type="email" class="form-control" id="newVisitorEmail" name="newVisitorEmail">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newVisitorPhone" class="form-label">Visitor Phone</label>
                            <input type="tel" class="form-control" id="newVisitorPhone" name="newVisitorPhone">
                        </div>
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
                        <p><strong>Host:</strong> <span id="rescheduleHostName"></span></p>
                    </div>

                    <div class="mb-3">
                        <label for="newDateTime" class="form-label">New Date & Time</label>
                        <input type="text" class="form-control" id="newDateTime" name="newTime" required>
                    </div>

                    <div class="mb-3">
                        <label for="rescheduleReason" class="form-label">Reason for Rescheduling (Optional)</label>
                        <textarea class="form-control" id="rescheduleReason" name="rescheduleReason" rows="2"></textarea>
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

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentDetailsModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="appointmentDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div id="detailsActionButtons"></div>
            </div>
        </div>
    </div>
</div>
<!-- Visitor Check-In Modal -->
<div class="modal fade" id="visitorCheckInModal" tabindex="-1" aria-labelledby="visitorCheckInModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorCheckInModalLabel">Check-In Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="visitorCheckInForm">
                    <input type="hidden" name="action" value="checkInWithDetails">
                    <input type="hidden" name="appointmentId" id="checkInAppointmentId">
                    <input type="hidden" name="visitorId" id="checkInVisitorId">

                    <div class="mb-3">
                        <p><strong>Visitor:</strong> <span id="checkInVisitorName"></span></p>
                        <p><strong>Email:</strong> <span id="checkInVisitorEmail"></span></p>
                        <p><strong>Host:</strong> <span id="checkInHostName"></span></p>
                        <p><strong>Appointment Time:</strong> <span id="checkInAppointmentTime"></span></p>
                    </div>

                    <div class="mb-3">
                        <label for="visitorIDType" class="form-label">ID Type</label>
                        <select class="form-select" id="visitorIDType" name="idType" required>
                            <option value="">-- Select ID Type --</option>
                            <option value="National ID">National ID</option>
                            <option value="Passport">Passport</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="Employee ID">Employee ID</option>
                            <option value="Student ID">Student ID</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="visitorIDNumber" class="form-label">ID Number</label>
                        <input type="text" class="form-control" id="visitorIDNumber" name="idNumber" required>
                    </div>

                    <div class="mb-3">
                        <label for="visitPurpose" class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" id="visitPurpose" name="visitPurpose" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="completeCheckInBtn">Complete Check-In</button>
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
        const appointmentsData = {};
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


        // Toggle between list and calendar views
        $('#listViewBtn').click(function() {
            $(this).addClass('active');
            $('#calendarViewBtn').removeClass('active');
            $('#listView').show();
            $('#calendarView').hide();
        });

        $('#calendarViewBtn').click(function() {
            $(this).addClass('active');
            $('#listViewBtn').removeClass('active');
            $('#listView').hide();
            $('#calendarView').show();
            renderCalendar(new Date());
        });

        // Handle search functionality
        $('#searchInput').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterAppointments();
        });

        // Handle host filter
        $('#hostFilter').on('change', function() {
            filterAppointments();
        });

        // Handle filter buttons
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            filterAppointments();
        });

// Handle search functionality
        $('#searchInput').on('keyup', function() {
            filterAppointments();
        });

        function filterAppointments() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const hostFilter = $('#hostFilter').val();
            const statusFilter = $('.filter-btn.active').data('filter') || 'all';
            const today = new Date().toISOString().split('T')[0];

            $('.appointment-item').each(function() {
                let show = true;

                // Apply search filter - improve the search logic
                if (searchTerm) {
                    // Get the search data from the data attribute
                    const searchData = $(this).data('search').toString().toLowerCase();
                    // Check if the search term is contained in the search data
                    if (searchData.indexOf(searchTerm) === -1) {
                        show = false;
                    }
                }

                // Apply host filter
                if (hostFilter && show) {
                    if ($(this).data('host-id').toString() !== hostFilter.toString()) {
                        show = false;
                    }
                }

                // Apply status filter
                if (show && statusFilter !== 'all') {
                    if (statusFilter === 'today') {
                        if ($(this).data('date') !== today) {
                            show = false;
                        }
                    } else if ($(this).data('status') !== statusFilter) {
                        show = false;
                    }
                }

                // Show or hide based on filters
                $(this).toggle(show);
            });

            // Show message if no results
            const visibleItems = $('.appointment-item:visible').length;
            if (visibleItems === 0) {
                if ($('#noResultsMessage').length === 0) {
                    $('#appointmentsList').append('<div id="noResultsMessage" class="col-12 text-center py-5"><h4 class="text-muted">No appointments match your filters</h4></div>');
                }
            } else {
                $('#noResultsMessage').remove();
            }
        }
        // Calendar functionality
        let currentDate = new Date();

        function renderCalendar(date) {
            const year = date.getFullYear();
            const month = date.getMonth();

            // Update header
            $('#currentMonth').text(new Date(year, month, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));

            // Get first day of month and total days
            const firstDay = new Date(year, month, 1).getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();

            let calendarHTML = '';
            let day = 1;

            // Create calendar grid
            for (let i = 0; i < 6; i++) {
                // Skip row if it goes beyond the month
                if (day > totalDays) break;

                calendarHTML += '<tr>';

                for (let j = 0; j < 7; j++) {
                    if ((i === 0 && j < firstDay) || day > totalDays) {
                        // Empty cell
                        calendarHTML += '<td></td>';
                    } else {
                        // Format date for data attribute
                        // Continued from previous code
                        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                        // Date cell
                        calendarHTML += `<td class="calendar-day" data-date="${dateStr}">`;
                        calendarHTML += `<div class="day-number">${day}</div>`;

                        // Find appointments for this day
                        const appointments = getAppointmentsForDate(dateStr);

                        // Add appointments to the cell
                        appointments.forEach(appointment => {
                            calendarHTML += `<div class="calendar-appointment ${appointment.status}"
                                                 data-id="${appointment.id}"
                                                 title="${appointment.visitorName} - ${appointment.time}">
                                                 ${appointment.time} - ${appointment.visitorName}
                                            </div>`;
                        });

                        calendarHTML += '</td>';
                        day++;
                    }
                }

                calendarHTML += '</tr>';
            }

            $('#calendarBody').html(calendarHTML);

            // Add click handler for appointments in calendar
            $('.calendar-appointment').click(function() {
                const appointmentId = $(this).data('id');
                showAppointmentDetails(appointmentId);
            });
        }

        <?php foreach ($appointments as $appointment): ?>
        appointmentsData[<?= $appointment['AppointmentID'] ?>] = {
            id: <?= $appointment['AppointmentID'] ?>,
            status: '<?= $appointment['Status'] ?>',
            visitorName: '<?= htmlspecialchars($appointment['VisitorName'], ENT_QUOTES) ?>',
            visitorEmail: '<?= htmlspecialchars($appointment['VisitorEmail'], ENT_QUOTES) ?>',
            hostName: '<?= htmlspecialchars($appointment['HostName'], ENT_QUOTES) ?>',
            date: '<?= date('Y-m-d', strtotime($appointment['AppointmentTime'])) ?>',
            time: '<?= date('h:i A', strtotime($appointment['AppointmentTime'])) ?>',
            checkInTime: <?= $appointment['CheckInTime'] ? "'" . date('Y-m-d H:i:s', strtotime($appointment['CheckInTime'])) . "'" : 'null' ?>,
            sessionEndTime: <?= $appointment['SessionEndTime'] ? "'" . date('Y-m-d H:i:s', strtotime($appointment['SessionEndTime'])) . "'" : 'null' ?>
        };
        <?php endforeach; ?>


        // Then use this data to generate calendar appointments
        function getAppointmentsForDate(dateStr) {
            const appointments = [];

            // Iterate through appointmentsData instead of DOM elements
            Object.values(appointmentsData).forEach(appointment => {
                if (appointment.date === dateStr) {

                // For completed appointments, show the session end time
                if (appointment.status === 'Completed' && appointment.sessionEndTime) {
                    const endTime = new Date(appointment.sessionEndTime);
                    const formattedEndTime = endTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    displayTime = `${appointment.time} - ${formattedEndTime}`;
                }
                    appointments.push({
                        id: appointment.id,
                        status: appointment.status,
                        visitorName: appointment.visitorName,
                        time: appointment.time
                    });
                }
            });

            return appointments;
        }

        // Previous month button
        $('#prevMonth').click(function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar(currentDate);
        });

        // Next month button
        $('#nextMonth').click(function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar(currentDate);
        });

        // Show appointment details when clicked in calendar
        function showAppointmentDetails(appointmentId) {
            // AJAX call to get appointment details
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        let detailsHTML = `
                    <div class="mb-3">
                        <h5>${response.VisitorName}</h5>
                        <p><i class="far fa-envelope me-2"></i> ${response.VisitorEmail}</p>
                        <p><i class="fas fa-phone me-2"></i> ${response.VisitorPhone || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <p><strong>Host:</strong> ${response.HostName}</p>
                        <p><strong>Scheduled Date:</strong> ${new Date(response.AppointmentTime).toLocaleDateString()}</p>
                        <p><strong>Scheduled Time:</strong> ${new Date(response.AppointmentTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                        <p><strong>Status:</strong> <span class="badge status-badge-${response.Status}">${response.Status}</span></p>`;

                        // For completed appointments, prominently show the session duration
                        if (response.Status === 'Completed' && response.CheckInTime && response.SessionEndTime) {
                            const checkInTime = new Date(response.CheckInTime);
                            const sessionEndTime = new Date(response.SessionEndTime);

                            // Calculate duration in minutes
                            const durationMs = sessionEndTime - checkInTime;
                            const durationMins = Math.round(durationMs / 60000);
                            const hours = Math.floor(durationMins / 60);
                            const minutes = durationMins % 60;

                            let durationText = '';
                            if (hours > 0) {
                                durationText = `${hours} hour${hours > 1 ? 's' : ''}`;
                                if (minutes > 0) {
                                    durationText += ` ${minutes} minute${minutes > 1 ? 's' : ''}`;
                                }
                            } else {
                                durationText = `${minutes} minute${minutes > 1 ? 's' : ''}`;
                            }

                            detailsHTML += `
                        <div class="alert alert-success mt-3">
                            <h6><i class="fas fa-clock me-2"></i> Session Duration: ${durationText}</h6>
                            <p class="mb-0">Check-in: ${checkInTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                            <p class="mb-0">Session End: ${sessionEndTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                        </div>`;
                        }

                        detailsHTML += `
                    ${response.CheckInTime && !response.SessionEndTime ? `<p><strong>Check-in Time:</strong> ${new Date(response.CheckInTime).toLocaleString()}</p>` : ''}
                    ${response.CheckOutTime ? `<p><strong>Building Check-out:</strong> ${new Date(response.CheckOutTime).toLocaleString()}</p>` : ''}
                </div>`;

                        $('#appointmentDetails').html(detailsHTML);

                        // Add action buttons based on status
                        let buttonsHTML = '';
                        if (response.Status === 'Upcoming') {
                            buttonsHTML = `
                                <button type="button" class="btn btn-success check-in-modal-btn" data-id="${appointmentId}">
                                    <i class="fas fa-check-circle me-1"></i> Check In
                                </button>
                                <button type="button" class="btn btn-primary reschedule-modal-btn" data-id="${appointmentId}">
                                    <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                </button>
                                <button type="button" class="btn btn-danger cancel-modal-btn" data-id="${appointmentId}">
                                    <i class="fas fa-times-circle me-1"></i> Cancel
                                </button>
                            `;
                        } else if (response.Status === 'Ongoing') {
                            buttonsHTML = `
                                <button type="button" class="btn btn-warning complete-modal-btn" data-id="${appointmentId}">
                                    <i class="fas fa-check-double me-1"></i> Complete
                                </button>
                            `;
                        }

                        $('#detailsActionButtons').html(buttonsHTML);
                        $('#appointmentDetailsModal').modal('show');
                    }
                }
            });
        }

        // Schedule new appointment
        $('#scheduleBtn').click(function() {
            // Validate form
            if (!$('#scheduleForm')[0].checkValidity()) {
                $('#scheduleForm')[0].reportValidity();
                return;
            }

            // Get form data
            const formData = $('#scheduleForm').serialize();

            // AJAX request to schedule appointment
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    try {
                        // Try to parse the response if it's not already an object
                        const result = (typeof response === 'string') ? JSON.parse(response) : response;

                        if (result.success) {
                            // Close modal and reload page
                            $('#scheduleModal').modal('hide');
                            alert('Appointment scheduled successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (result.message || 'An unknown error occurred'));
                        }
                    } catch (e) {
                        console.error("Error parsing response:", e);
                        console.log("Response:", response);
                        alert('An error occurred while processing the server response.');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX error:", textStatus, errorThrown);
                    console.log("Response text:", jqXHR.responseText);
                    alert('An error occurred. Please check the console for details.');
                }
            });
        });
        // Reschedule appointment
        $('#rescheduleBtn').click(function() {
            // Validate form
            if (!$('#rescheduleForm')[0].checkValidity()) {
                $('#rescheduleForm')[0].reportValidity();
                return;
            }

            // Get form data
            const formData = $('#rescheduleForm').serialize();

            // AJAX request to reschedule appointment
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Close modal and reload page
                        $('#rescheduleModal').modal('hide');
                        alert('Appointment rescheduled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

        // Modified check-in button handler
        // Modified check-in button handler to open the modal instead of direct check-in
        $(document).on('click', '.check-in-btn, .check-in-modal-btn', function() {
            const appointmentId = $(this).data('id');

            // Get appointment details to fill the modal
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        // Populate the modal with visitor info
                        $('#checkInAppointmentId').val(appointmentId);
                        $('#checkInVisitorId').val(response.VisitorID);
                        $('#checkInVisitorName').text(response.VisitorName);
                        $('#checkInVisitorEmail').text(response.VisitorEmail);
                        $('#checkInHostName').text(response.HostName);
                        $('#checkInAppointmentTime').text(new Date(response.AppointmentTime).toLocaleString());

                        // Show the modal
                        $('#visitorCheckInModal').modal('show');
                    } else {
                        alert('Error: Could not retrieve appointment details');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

// Handle the "Complete Check-In" button click
        $('#completeCheckInBtn').click(function() {
            // Validate form
            if (!$('#visitorCheckInForm')[0].checkValidity()) {
                $('#visitorCheckInForm')[0].reportValidity();
                return;
            }

            // Get form data
            const formData = $('#visitorCheckInForm').serialize();

            // AJAX request to process check-in with visitor details
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: formData,
                dataType:'json',
                success: function(response) {
                    try {
                        // Try to parse the response if it's not already an object
                        const result = (typeof response === 'string') ? JSON.parse(response) : response;

                        if (result.success) {
                            // Close modal and reload page
                            $('#visitorCheckInModal').modal('hide');
                            alert('Visitor checked in successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (result.message || 'An unknown error occurred'));
                        }
                    } catch (e) {
                        console.error("Error parsing response:", e);
                        console.log("Response:", response);
                        alert('An error occurred while processing the server response.');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX error:", textStatus, errorThrown);
                    console.log("Response text:", jqXHR.responseText);
                    alert('An error occurred. Please check the console for details.');
                }
            });
        });
        // Handle cancel button click
        $(document).on('click', '.cancel-btn, .cancel-modal-btn', function() {
            const appointmentId = $(this).data('id');

            if (confirm('Are you sure you want to cancel this appointment?')) {
                $.ajax({
                    url: 'front_desk_appointments.php',
                    type: 'POST',
                    data: {
                        action: 'cancelAppointment',
                        appointmentId: appointmentId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Appointment cancelled successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        });

        // Handle reschedule button click to populate modal
        $(document).on('click', '.reschedule-btn', function() {
            const appointmentId = $(this).data('id');

            // Get appointment details
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#rescheduleAppointmentId').val(appointmentId);
                        $('#rescheduleVisitorName').text(response.Name);
                        $('#rescheduleHostName').text(response.Name);
                    }
                }
            });
        });

        // Handle reschedule button from modal
        $(document).on('click', '.reschedule-modal-btn', function() {
            const appointmentId = $(this).data('id');

            // Get appointment details
            $.ajax({
                url: 'front_desk_appointments.php',
                type: 'POST',
                data: {
                    action: 'getAppointmentDetails',
                    appointmentId: appointmentId
                },
                success: function(response) {
                    if (response) {
                        $('#appointmentDetailsModal').modal('hide');
                        $('#rescheduleAppointmentId').val(appointmentId);
                        $('#rescheduleVisitorName').text(response.Name);
                        $('#rescheduleHostName').text(response.Name);
                        $('#rescheduleModal').modal('show');
                    }
                }
            });
        });
    });
</script>