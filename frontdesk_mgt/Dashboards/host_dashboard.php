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
foreach ($appointments as &$appointment) {
    $appointment['AppointmentTime'] = date('c', strtotime($appointment['AppointmentTime']));
}
unset($appointment);
echo "<!-- Debug: " . count($appointments) . " appointments loaded -->";


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
    <link rel="stylesheet" href="../../Sneat/assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/libs/flatpickr/flatpickr.css" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/pages/app-calendar.css" />
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        /* Custom colors using Sneat's CSS variables */
        /* Calendar container responsiveness */
        .app-calendar-wrapper {
            overflow: hidden;
        }

        .app-calendar-content {
            overflow-x: auto;
        }

        #calendar-view-tab.active ~ .list-view-only {
            display: none;
        }

        .app-calendar-wrapper {
            min-height: 650px;
        }

        .app-calendar-sidebar {
            background-color: var(--bs-body-bg);
        }

        .inline-calendar {
            margin-bottom: 1rem;
        }

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

        /* Fix content padding to prevent content from going behind sidebar */
        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
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
        }

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

        /* Chat Bot Styles */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .chat-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .chat-toggle:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .chat-toggle::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
        }

        .chat-toggle:hover::before {
            width: 100%;
            height: 100%;
        }

        .chat-toggle.active {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .chat-container {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .chat-container.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .chat-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .chat-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .chat-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .chat-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .chat-messages {
            height: 340px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            animation: messageSlideIn 0.3s ease-out;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-break: break-word;
        }

        .message.bot .message-content {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
            margin-right: auto;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .message.bot::before {
            content: '🤖';
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 16px;
            flex-shrink: 0;
        }

        .typing-indicator {
            display: none;
            align-items: center;
            margin-bottom: 15px;
        }

        .typing-indicator.active {
            display: flex;
        }

        .typing-dots {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 18px;
            padding: 12px 16px;
            margin-left: 42px;
        }

        .typing-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes typing {
            0%, 80%, 100% {
                transform: scale(0.8);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .chat-input-container {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            resize: none;
            min-height: 20px;
            max-height: 80px;
            overflow-y: auto;
        }

        .chat-input:focus {
            border-color: #667eea;
        }

        .chat-input::placeholder {
            color: #999;
        }

        .chat-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-send:hover {
            transform: scale(1.05);
        }

        .chat-send:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .welcome-message {
            text-align: center;
            color: #666;
            padding: 20px;
            font-size: 14px;
        }

        .welcome-message h4 {
            color: #333;
            margin-bottom: 10px;
        }

        /* Notification Badge */
        .chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #ff4757;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 71, 87, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0);
            }
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 13px;
            border-left: 4px solid #c33;
        }
        /* Calendar specific styles */
        .fc-event {
            cursor: pointer;
            margin-bottom: 2px;
            padding: 2px 5px;
        }

        .fc-daygrid-event {
            white-space: normal;
        }

        .fc-daygrid-day-frame {
            min-height: 80px;
        }

        .fc .fc-daygrid-day-number {
            padding: 4px;
        }

        .fc .fc-col-header-cell-cushion {
            padding: 4px;
        }

        .fc .fc-toolbar-title {
            font-size: 1.25rem;
        }

        .fc .fc-button {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        .fc .fc-button-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .fc .fc-button-primary:hover {
            background-color: var(--bs-primary-dark);
            border-color: var(--bs-primary-dark);
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--bs-primary-dark);
            border-color: var(--bs-primary-dark);
        }

        .fc .fc-button-primary:disabled {
            background-color: var(--bs-primary-light);
            border-color: var(--bs-primary-light);
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'host-sidebar.php'; ?>
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
                        <h4 class="mb-0 fw-bold ms-2"> Manage Appointments</h4>
                    </div>
                </div>
                <!--Schedule Appointment button-->
                <div class="navbar-nav align-items-center me-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="fas fa-plus-circle me-2"></i> Schedule New Appointment
                    </button>
                </div>

            </nav>
        <div class="container-fluid container-p-y">
            <div class="row mb-4">
                <div class="col-md-8">
                    <p class="text-muted">Manage your upcoming, ongoing, and past appointments</p>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-12">
                    <!-- Add tabs for List and Calendar views -->
                    <ul class="nav nav-tabs mb-3" id="viewTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="list-view-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab">
                                <i class="fas fa-list me-2"></i>List View
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="calendar-view-tab" data-bs-toggle="tab" data-bs-target="#calendar-view" type="button" role="tab">
                                <i class="fas fa-calendar me-2"></i>Calendar View
                            </button>
                        </li>
                    </ul>
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
            <div class="tab-content" id="viewTabsContent">
                <!-- List View Tab -->
                <div class="tab-pane fade show active" id="list-view" role="tabpanel">
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
                                            <?php
                                            // Map status to Bootstrap classes
                                            $badgeClass = match($appointment['Status']) {
                                                'Cancelled' => 'danger',
                                                'Ongoing' => 'info',
                                                'Upcoming' => 'primary',
                                                'Completed' => 'success',
                                                'Overdue' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge rounded-pill text-bg-<?= $badgeClass ?>"><?= $appointment['Status'] ?></span>
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
                <div class="tab-pane fade" id="calendar-view" role="tabpanel">
                    <div class="card app-calendar-wrapper">
                        <div class="row g-0">
                            <!-- Calendar Sidebar -->
                            <div class="col-md-3 app-calendar-sidebar border-end" id="app-calendar-sidebar">
                                <div class="px-3 pt-2">
                                    <div class="inline-calendar"></div>
                                </div>
                                <hr class="mb-4 mx-n4 mt-3" />
                                <div class="px-4 pb-2">
                                    <div>
                                        <h6>Event Filters</h6>
                                    </div>
                                    <div class="form-check mb-3 ms-2">
                                        <input class="form-check-input select-all" type="checkbox" id="selectAll" data-value="all" checked />
                                        <label class="form-check-label" for="selectAll">View All</label>
                                    </div>
                                    <div class="app-calendar-events-filter">
                                        <div class="form-check form-check-primary mb-3 ms-2">
                                            <input class="form-check-input input-filter" type="checkbox" id="select-upcoming" data-value="upcoming" checked />
                                            <label class="form-check-label" for="select-upcoming">Upcoming</label>
                                        </div>
                                        <div class="form-check form-check-warning ms-2">
                                            <input class="form-check-input input-filter" type="checkbox" id="select-overdue" data-value="overdue" checked />
                                            <label class="form-check-label" for="select-overdue">Overdue</label>
                                        </div>
                                        <div class="form-check form-check-info ms-2">
                                            <input class="form-check-input input-filter" type="checkbox" id="select-ongoing" data-value="ongoing" checked />
                                            <label class="form-check-label" for="select-ongoing">Ongoing</label>
                                        </div>
                                        <div class="form-check form-check-success mb-3 ms-2">
                                            <input class="form-check-input input-filter" type="checkbox" id="select-completed" data-value="completed" checked />
                                            <label class="form-check-label" for="select-completed">Completed</label>
                                        </div>
                                        <div class="form-check form-check-danger mb-3 ms-2">
                                            <input class="form-check-input input-filter" type="checkbox" id="select-cancelled" data-value="cancelled" checked />
                                            <label class="form-check-label" for="select-cancelled">Cancelled</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9 app-calendar-content">
                                <div class="card shadow-none border-0">
                                    <div class="card-body pb-0">
                                        <!-- FullCalendar -->
                                        <div id="calendar"></div>
                                    </div>
                                </div>
                                <div class="app-overlay"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chat Widget -->
<div class="chat-widget">
    <!-- Chat Toggle Button -->
    <button class="chat-toggle" id="chatToggle">
        <i class="fas fa-comments"></i>
        <div class="chat-notification" id="chatNotification">1</div>
    </button>

    <!-- Chat Container -->
    <div class="chat-container" id="chatContainer">
        <!-- Chat Header -->
        <div class="chat-header">
            <button class="chat-close" id="chatClose">
                <i class="fas fa-times"></i>
            </button>
            <h3>AI Assistant</h3>
            <p>How can I help you today?</p>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="welcome-message">
                <h4>👋 Welcome!</h4>
                <p>I'm your AI assistant powered by Google Gemini. Ask me anything about appointments, scheduling, or general questions!</p>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="typing-indicator" id="typingIndicator">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-container">
            <textarea
                    class="chat-input"
                    id="chatInput"
                    placeholder="Type your message..."
                    rows="1"
            ></textarea>
            <button class="chat-send" id="chatSend">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

    <!--Schedule Modal-->
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

<!--Reschedule Modal-->
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="../../Sneat/assets/vendor/libs/moment/moment.js"></script>
    <script src="../../Sneat/assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
    <script src="../../Sneat/assets/vendor/libs/flatpickr/flatpickr.js"></script>
    <script src="../../Sneat/assets/vendor/libs/select2/select2.js"></script>
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>
<script>
    $(document).ready(function() {
        let calendar; // Declare calendar variable
        let appointmentsData = <?php echo json_encode($appointments); ?>;

        console.log('Document ready - setting up calendar');

        // Initialize date pickers first
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

        // Visitor selection handling
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

        // Filter function for list view
        $('.filter-btn').click(function() {
            $('.layout-menu, .layout-content, #layout-menu').addClass('no-transition');
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');

            const filter = $(this).data('filter');
            $('#no-appointments-message').remove();

            $('.appointment-item').each(function() {
                const $item = $(this);
                const itemStatus = $item.data('status');

                if (filter === 'all' || itemStatus === filter) {
                    $item.fadeIn(300);
                } else {
                    $item.fadeOut(300);
                }
            });

            setTimeout(function() {
                let visibleItems;
                if (filter === 'all') {
                    visibleItems = $('.appointment-item');
                } else {
                    visibleItems = $(`.appointment-item[data-status="${filter}"]`);
                }

                if (visibleItems.length === 0) {
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

                setTimeout(() => {
                    $('.layout-menu, .layout-content, #layout-menu').removeClass('no-transition');
                }, 100);
            }, 350);
        });

        // CALENDAR INITIALIZATION FUNCTION
        function initializeCalendar() {
            console.log('Initializing calendar...');
            const calendarEl = document.getElementById('calendar');

            if (!calendarEl) {
                console.error('Calendar element not found');
                return null;
            }

            // Convert PHP appointments to FullCalendar format
            const calendarEvents = [];

            if (Array.isArray(appointmentsData) && appointmentsData.length > 0) {
                appointmentsData.forEach(function(apt) {
                    // Map status to colors
                    const statusColors = {
                        'Upcoming': '#007bff',     // Blue
                        'Ongoing': '#17a2b8',      // Info blue
                        'Completed': '#28a745',    // Green
                        'Cancelled': '#dc3545',     // Red
                        'Overdue': '#ffc107'        // Yellow
                    };

                    const event = {
                        id: apt.AppointmentID.toString(),
                        title: apt.Name,
                        start: apt.AppointmentTime,
                        allDay: false,
                        backgroundColor: statusColors[apt.Status] || '#6c757d',
                        borderColor: statusColors[apt.Status] || '#6c757d',
                        textColor: '#ffffff',
                        extendedProps: {
                            status: apt.Status,
                            email: apt.Email || '',
                            appointmentId: apt.AppointmentID
                        }
                    };
                    calendarEvents.push(event);
                });
            }

            // Initialize FullCalendar
            try {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    height: 'auto',
                    events: calendarEvents,
                    eventDisplay: 'block',
                    displayEventTime: true,
                    displayEventEnd: false,
                    eventClick: function(info) {
                        showAppointmentDetails(info.event);
                    },
                    eventDidMount: function(info) {
                        // Add tooltip
                        const tooltipTitle = `${info.event.title} - ${info.event.extendedProps.status}\nTime: ${moment(info.event.start).format('h:mm A')}`;
                        info.el.setAttribute('title', tooltipTitle);
                        info.el.style.cursor = 'pointer';
                    }
                });

                calendar.render();
                console.log('Calendar rendered successfully');

            } catch (error) {
                console.error('Calendar initialization/render failed:', error);
                calendarEl.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Calendar Error!</h4>
                <p>Failed to initialize calendar. Please check the console for details.</p>
                <hr>
                <p class="mb-0">Error: ${error.message}</p>
            </div>
        `;
                return null;
            }

            return calendar;
        }

        // TAB SWITCHING - Initialize calendar when tab is shown
        $('#calendar-view-tab').on('shown.bs.tab', function (e) {
            console.log('Calendar tab activated');

            // Small delay to ensure DOM is ready
            setTimeout(() => {
                if (!calendar) {
                    console.log('Creating new calendar instance');
                    calendar = initializeCalendar();
                } else {
                    console.log('Refreshing existing calendar');
                    try {
                        calendar.updateSize();
                        calendar.refetchEvents();
                    } catch (e) {
                        console.error('Calendar refresh failed:', e);
                        // Try recreating calendar
                        calendar = initializeCalendar();
                    }
                }
            }, 150);
        });

        $('#viewTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const targetTab = $(e.target).attr('data-bs-target');
            console.log('Tab switched to:', targetTab);

            if (targetTab === '#list-view') {
                $('.list-view-only').show();
            } else {
                $('.list-view-only').hide();
            }
        });

        // Initialize inline calendar
        setTimeout(() => {
            try {
                // Initialize inline calendar
                flatpickr(".inline-calendar", {
                    inline: true,
                    onChange: function(selectedDates) {
                        if (calendar && selectedDates[0]) {
                            calendar.gotoDate(selectedDates[0]);
                        }
                    }
                });
                console.log('Inline calendar initialized');
            } catch (e) {
                console.error('Flatpickr initialization failed:', e);
            }
        }, 300);

        // APPOINTMENT DETAIL MODAL FUNCTION
        function showAppointmentDetails(event) {
            const props = event.extendedProps;
            const appointmentId = props.appointmentId;

            const modalHtml = `
            <div class="modal fade" id="appointmentDetailModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Appointment Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Visitor:</strong> ${event.title}</p>
                            <p><strong>Email:</strong> ${props.email}</p>
                            <p><strong>Date & Time:</strong> ${moment(event.start).format('MMMM DD, YYYY [at] h:mm A')}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeClass(props.status)}">${props.status}</span></p>
                        </div>
                        <div class="modal-footer">
                            ${getActionButtons(appointmentId, props.status)}
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            $('#appointmentDetailModal').remove();
            $('body').append(modalHtml);
            $('#appointmentDetailModal').modal('show');
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'Cancelled': 'danger',
                'Ongoing': 'info',
                'Upcoming': 'primary',
                'Completed': 'success',
                'Overdue': 'warning'
            };
            return classes[status] || 'secondary';
        }

        function getActionButtons(appointmentId, status) {
            let buttons = '';

            if (status === 'Upcoming') {
                buttons += `
                <button class="btn btn-success btn-sm start-session-btn" data-id="${appointmentId}">
                    <i class="fas fa-play me-1"></i> Start Session
                </button>
                <button class="btn btn-primary btn-sm reschedule-btn" data-id="${appointmentId}" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                    <i class="fas fa-calendar-alt me-1"></i> Reschedule
                </button>
                <button class="btn btn-danger btn-sm cancel-btn" data-id="${appointmentId}">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
            `;
            } else if (status === 'Ongoing') {
                buttons += `
                <button class="btn btn-warning btn-sm end-session-btn" data-id="${appointmentId}">
                    <i class="fas fa-stop me-1"></i> End Session
                </button>
            `;
            }

            return buttons;
        }

        // CALENDAR FILTER FUNCTIONALITY - Fixed
        $(document).on('change', '.input-filter', function() {
            console.log('Calendar filter changed');
            if (calendar && typeof calendar.getEvents === 'function') {
                updateCalendarFilters();
            }
        });

        $(document).on('change', '.select-all', function() {
            const isChecked = $(this).is(':checked');
            $('.input-filter').prop('checked', isChecked);
            if (calendar && typeof calendar.getEvents === 'function') {
                updateCalendarFilters();
            }
        });

        function updateCalendarFilters() {
            if (!calendar || typeof calendar.getEvents !== 'function') {
                console.warn('Calendar not available for filtering');
                return;
            }

            const checkedFilters = $('.input-filter:checked').map(function() {
                return $(this).data('value');
            }).get();

            console.log('Applying calendar filters:', checkedFilters);

            const events = calendar.getEvents();
            console.log('Total events to filter:', events.length);

            events.forEach(function(event) {
                const status = event.extendedProps.status.toLowerCase();
                const shouldShow = checkedFilters.includes(status) || checkedFilters.includes('all');

                console.log(`Event: ${event.title}, Status: ${status}, Show: ${shouldShow}`);

                if (shouldShow) {
                    event.setProp('display', 'auto');
                } else {
                    event.setProp('display', 'none');
                }
            });
        }

        // Make functions globally available
        window.showAppointmentDetails = showAppointmentDetails;
        window.getStatusBadgeClass = getStatusBadgeClass;
        window.getActionButtons = getActionButtons;

        // ALL YOUR EXISTING AJAX HANDLERS (keep these as they are)
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

        // SIDEBAR TOGGLE FUNCTIONALITY (keep existing)
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

        $('.layout-menu-toggle').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $html = $('html');
            const $sidebar = $('#layout-menu');
            const $toggle = $(this);

            $toggle.css('pointer-events', 'none');
            $html.toggleClass('layout-menu-collapsed');
            const isCollapsed = $html.hasClass('layout-menu-collapsed');

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

            updateTooltip();
            localStorage.setItem('layoutMenuCollapsed', isCollapsed);

            setTimeout(() => {
                $toggle.css('pointer-events', 'auto');
            }, 300);
        });

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

        updateTooltip();

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

            // Update calendar size on window resize
            if (calendar) {
                calendar.updateSize();
            }
        });

        $('.layout-menu-toggle').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        $(document).on('click', '.layout-menu-toggle', function() {
            $('body').addClass('sidebar-toggling');
            setTimeout(() => {
                $('body').removeClass('sidebar-toggling');
            }, 300);
        });

        console.log('All initialization complete');
    });

    // Enhanced Gemini Chat Bot Implementation with Appointment Access
    class GeminiChatBot {
        constructor() {
            this.apiKey = 'AIzaSyACxk5zCzJt6H0jJ2vs2sIP98V9jj7NcL0';
            this.apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
            this.isOpen = false;
            this.conversationHistory = [];
            this.appointmentsData = <?php echo json_encode($appointments); ?>;

            this.initializeElements();
            this.attachEventListeners();
            this.showNotification();
        }

        initializeElements() {
            this.chatToggle = document.getElementById('chatToggle');
            this.chatContainer = document.getElementById('chatContainer');
            this.chatClose = document.getElementById('chatClose');
            this.chatMessages = document.getElementById('chatMessages');
            this.chatInput = document.getElementById('chatInput');
            this.chatSend = document.getElementById('chatSend');
            this.typingIndicator = document.getElementById('typingIndicator');
            this.chatNotification = document.getElementById('chatNotification');
        }

        attachEventListeners() {
            // Toggle chat
            this.chatToggle.addEventListener('click', () => this.toggleChat());
            this.chatClose.addEventListener('click', () => this.closeChat());

            // Send message
            this.chatSend.addEventListener('click', () => this.handleSendMessage());

            // Handle Enter key
            this.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSendMessage();
                }
            });

            // Auto-resize textarea
            this.chatInput.addEventListener('input', () => {
                this.chatInput.style.height = 'auto';
                this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 80) + 'px';
            });
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            this.chatContainer.classList.add('active');
            this.chatToggle.classList.add('active');
            this.chatToggle.innerHTML = '<i class="fas fa-times"></i>';
            this.hideNotification();

            // Focus input after animation
            setTimeout(() => {
                this.chatInput.focus();
            }, 300);
        }

        closeChat() {
            this.isOpen = false;
            this.chatContainer.classList.remove('active');
            this.chatToggle.classList.remove('active');
            this.chatToggle.innerHTML = '<i class="fas fa-comments"></i>';
        }

        showNotification() {
            this.chatNotification.style.display = 'flex';
        }

        hideNotification() {
            this.chatNotification.style.display = 'none';
        }

        async handleSendMessage() {
            const message = this.chatInput.value.trim();
            if (!message) return;

            // Clear input
            this.chatInput.value = '';
            this.chatInput.style.height = 'auto';

            // Add user message
            this.addMessage(message, 'user');

            // Show typing indicator
            this.showTyping();

            try {
                // Check for appointment-related queries
                const appointmentResponse = this.handleAppointmentQuery(message);
                if (appointmentResponse) {
                    this.hideTyping();
                    this.addMessage(appointmentResponse, 'bot');
                    return;
                }

                // If not an appointment query, send to Gemini
                const response = await this.sendToGemini(message);
                this.hideTyping();
                this.addMessage(response, 'bot');
            } catch (error) {
                this.hideTyping();
                this.addMessage('Sorry, I encountered an error. Please try again later.', 'bot', true);
                console.error('Gemini API Error:', error);
            }
        }

        handleAppointmentQuery(message) {
            const lowerMessage = message.toLowerCase();
            const today = new Date().toISOString().split('T')[0];
            const now = new Date();

            // Helper function to format appointment details
            const formatAppointment = (appt) => {
                const date = new Date(appt.AppointmentTime);
                return `👤 ${appt.Name}\n` +
                    `📅 ${date.toLocaleDateString()}\n` +
                    `⏰ ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                    `📧 ${appt.Email}\n` +
                    `📌 Status: ${appt.Status}\n` +
                    (appt.CancellationReason ? `❌ Reason: ${appt.CancellationReason}\n` : '') +
                    `---\n`;
            };

            // 1. Check for status/count queries
            if (lowerMessage.includes('how many') && lowerMessage.includes('appointments')) {
                return this.getAppointmentCounts();
            }

            // 2. Check for upcoming appointments
            if (lowerMessage.includes('upcoming') || lowerMessage.includes('future') ||
                lowerMessage.includes('next appointments')) {
                return this.getUpcomingAppointments();
            }

            // 3. Check for past appointments
            if (lowerMessage.includes('past') || lowerMessage.includes('previous') ||
                lowerMessage.includes('completed appointments')) {
                const pastAppts = this.appointmentsData
                    .filter(a => new Date(a.AppointmentTime) < now && a.Status === 'Completed')
                    .sort((a, b) => new Date(b.AppointmentTime) - new Date(a.AppointmentTime));

                if (pastAppts.length === 0) return "You have no past completed appointments.";

                let response = "Here are your past completed appointments:\n\n";
                pastAppts.slice(0, 5).forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 4. Check for today's appointments
            if (lowerMessage.includes("today") || lowerMessage.includes('this day')) {
                return this.getTodaysAppointments();
            }

            // 5. Check for specific date
            const dateMatch = message.match(/(\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b)|(\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* \d{1,2},? \d{4}\b)/i);
            if (dateMatch) {
                const searchDate = new Date(dateMatch[0]);
                const dateStr = searchDate.toLocaleDateString();
                const dateAppts = this.appointmentsData.filter(appt => {
                    const apptDate = new Date(appt.AppointmentTime).toLocaleDateString();
                    return apptDate === dateStr;
                });

                if (dateAppts.length === 0) return `No appointments found for ${dateStr}.`;

                let response = `Appointments on ${dateStr}:\n\n`;
                dateAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 6. Check for specific visitor
            const nameMatch = message.match(/(?:details|information|about|for)\s+(.+)/i);
            if (nameMatch && nameMatch[1]) {
                const searchName = nameMatch[1].trim();
                const matchedAppts = this.appointmentsData.filter(a =>
                    a.Name.toLowerCase().includes(searchName.toLowerCase())
                );

                if (matchedAppts.length === 0) return `No appointments found for ${searchName}.`;

                let response = matchedAppts.length === 1 ?
                    `Found 1 appointment for ${searchName}:\n\n` :
                    `Found ${matchedAppts.length} appointments for ${searchName}:\n\n`;

                matchedAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 7. Check for status-specific queries
            const statuses = ['upcoming', 'ongoing', 'completed', 'cancelled', 'overdue'];
            const statusQuery = statuses.find(status => lowerMessage.includes(status));
            if (statusQuery) {
                const filtered = this.appointmentsData.filter(a =>
                    a.Status.toLowerCase() === statusQuery
                );

                if (filtered.length === 0) return `You have no ${statusQuery} appointments.`;

                let response = `Your ${statusQuery} appointments:\n\n`;
                filtered.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 8. Check for time-based queries (morning/afternoon/evening)
            const timePeriods = {
                morning: { start: 6, end: 12 },
                afternoon: { start: 12, end: 17 },
                evening: { start: 17, end: 21 },
                night: { start: 21, end: 6 }
            };

            const periodQuery = Object.keys(timePeriods).find(period =>
                lowerMessage.includes(period)
            );

            if (periodQuery) {
                const { start, end } = timePeriods[periodQuery];
                const periodAppts = this.appointmentsData.filter(appt => {
                    const hours = new Date(appt.AppointmentTime).getHours();
                    return periodQuery === 'night' ?
                        hours >= start || hours < end :
                        hours >= start && hours < end;
                });

                if (periodAppts.length === 0) return `No ${periodQuery} appointments found.`;

                let response = `Your ${periodQuery} appointments:\n\n`;
                periodAppts.forEach(appt => response += formatAppointment(appt));
                return response;
            }

            // 9. Check for general appointment list
            if (lowerMessage.includes('list all') || lowerMessage.includes('all appointments')) {
                if (this.appointmentsData.length === 0) return "You have no appointments.";

                let response = "All your appointments:\n\n";
                this.appointmentsData
                    .sort((a, b) => new Date(a.AppointmentTime) - new Date(b.AppointmentTime))
                    .forEach(appt => response += formatAppointment(appt));
                return response;
            }

            return null;
        }


        getAppointmentCounts() {
            const counts = {
                total: this.appointmentsData.length,
                upcoming: this.appointmentsData.filter(a => a.Status === 'Upcoming').length,
                ongoing: this.appointmentsData.filter(a => a.Status === 'Ongoing').length,
                completed: this.appointmentsData.filter(a => a.Status === 'Completed').length,
                cancelled: this.appointmentsData.filter(a => a.Status === 'Cancelled').length
            };

            return `Here's your appointment summary:\n\n` +
                `📅 Total Appointments: ${counts.total}\n` +
                `⏳ Upcoming: ${counts.upcoming}\n` +
                `🔵 Ongoing: ${counts.ongoing}\n` +
                `✅ Completed: ${counts.completed}\n` +
                `❌ Cancelled: ${counts.cancelled}`;
        }

        getUpcomingAppointments() {
            const upcoming = this.appointmentsData
                .filter(a => a.Status === 'Upcoming')
                .sort((a, b) => new Date(a.AppointmentTime) - new Date(b.AppointmentTime))
                .slice(0, 5); // Limit to 5 upcoming

            if (upcoming.length === 0) {
                return "You have no upcoming appointments scheduled.";
            }

            let response = "Here are your upcoming appointments:\n\n";
            upcoming.forEach(appt => {
                const date = new Date(appt.AppointmentTime);
                response += `👤 ${appt.Name}\n` +
                    `📅 ${date.toLocaleDateString()}\n` +
                    `⏰ ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                    `✉️ ${appt.Email}\n\n`;
            });

            return response;
        }

        getAppointmentDetails(name) {
            const appointment = this.appointmentsData.find(a =>
                a.Name.toLowerCase().includes(name.toLowerCase())
            );

            if (!appointment) {
                return `I couldn't find an appointment for ${name}. Please check the name and try again.`;
            }

            const date = new Date(appointment.AppointmentTime);
            return `Here are the details for ${appointment.Name}:\n\n` +
                `📅 Date: ${date.toLocaleDateString()}\n` +
                `⏰ Time: ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}\n` +
                `📧 Email: ${appointment.Email}\n` +
                `📌 Status: ${appointment.Status}\n` +
                (appointment.CancellationReason ? `❌ Reason: ${appointment.CancellationReason}\n` : '');
        }

        getTodaysAppointments() {
            const today = new Date().toISOString().split('T')[0];
            const todaysAppts = this.appointmentsData.filter(appt => {
                const apptDate = new Date(appt.AppointmentTime).toISOString().split('T')[0];
                return apptDate === today && appt.Status !== 'Cancelled';
            });

            if (todaysAppts.length === 0) {
                return "You have no appointments scheduled for today.";
            }

            let response = `You have ${todaysAppts.length} appointment(s) today:\n\n`;
            todaysAppts.forEach(appt => {
                const time = new Date(appt.AppointmentTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                response += `⏰ ${time} - ${appt.Name} (${appt.Status})\n`;
            });

            return response;
        }

        addMessage(content, sender, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';

            if (isError) {
                contentDiv.className += ' error-message';
            }

            // Format message content (basic markdown support)
            contentDiv.innerHTML = this.formatMessage(content);
            messageDiv.appendChild(contentDiv);

            // Remove welcome message if it exists
            const welcomeMessage = this.chatMessages.querySelector('.welcome-message');
            if (welcomeMessage) {
                welcomeMessage.remove();
            }

            this.chatMessages.appendChild(messageDiv);
            this.scrollToBottom();

            // Store in conversation history
            this.conversationHistory.push({
                role: sender === 'user' ? 'user' : 'model',
                parts: [{ text: content }]
            });
        }

        formatMessage(text) {
            // Basic markdown formatting
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code style="background: #f1f1f1; padding: 2px 4px; border-radius: 3px;">$1</code>')
                .replace(/\n/g, '<br>');
        }

        showTyping() {
            this.typingIndicator.classList.add('active');
            this.scrollToBottom();
        }

        hideTyping() {
            this.typingIndicator.classList.remove('active');
        }

        scrollToBottom() {
            setTimeout(() => {
                this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            }, 100);
        }

        async sendToGemini(message) {
            // Prepare conversation context
            const contents = [
                {
                    role: 'user',
                    parts: [{ text: message }]
                }
            ];

            // Add conversation history (last 10 messages to manage context length)
            const recentHistory = this.conversationHistory.slice(-10);
            if (recentHistory.length > 0) {
                contents.unshift(...recentHistory);
            }

            // Add system message with appointment context
            const systemMessage = {
                role: 'user',
                parts: [{
                    text: `You are an AI assistant for an appointment management system. The user is a host managing appointments.
                    Current appointment stats: Total ${this.appointmentsData.length}, Upcoming: ${this.appointmentsData.filter(a => a.Status === 'Upcoming').length},
                    Ongoing: ${this.appointmentsData.filter(a => a.Status === 'Ongoing').length}. Today is ${new Date().toLocaleDateString()}.
                    You can help with appointment queries, scheduling, and general questions. Keep responses concise and helpful.`
                }]
            };
            contents.unshift(systemMessage);

            const requestBody = {
                contents: contents,
                generationConfig: {
                    temperature: 0.7,
                    topK: 40,
                    topP: 0.95,
                    maxOutputTokens: 1024,
                },
                safetySettings: [
                    {
                        category: "HARM_CATEGORY_HARASSMENT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_HATE_SPEECH",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    },
                    {
                        category: "HARM_CATEGORY_DANGEROUS_CONTENT",
                        threshold: "BLOCK_MEDIUM_AND_ABOVE"
                    }
                ]
            };

            const response = await fetch(`${this.apiUrl}?key=${this.apiKey}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`API Error: ${response.status} - ${errorData.error?.message || 'Unknown error'}`);
            }

            const data = await response.json();

            if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                return data.candidates[0].content.parts[0].text;
            } else {
                throw new Error('Unexpected API response format');
            }
        }
    }

    // Initialize the chatbot when the page loads
    document.addEventListener('DOMContentLoaded', () => {
        new GeminiChatBot();
    });
</script></body>
</html>