<?php
session_start();

// Enable error reporting for debugging
ini_set('log_errors', 1); // Log errors
ini_set('error_log', 'php_errors.log'); // Specify your error log path

// Include database configuration and core ticket functions
global $conn;
require_once '../dbConfig.php';

require_once 'ticket_functions.php';
require_once 'ticket_ops.php';
require_once 'view_ticket.php';

// Start session to access user role and ID

$userRole = $_SESSION['role'] ?? 'host'; // Default to host if role not set
$userId = $_SESSION['userID'] ?? null;

// Process ticket creation
$result = createTicket($conn);
$message = $result['message'];
$error = $result['error'];

// Process ticket operations (assign, resolve, close)
$opResult = processTicketOperation($conn);
if (isset($opResult['message']) && $opResult['message']) {
    $message = $opResult['message'];
}
if (isset($opResult['error']) && $opResult['error']) {
    $error = $opResult['error'];
}

// Add JSON response for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($result);
    exit;
}

// Get data for dropdowns and ticket list
$users = getUsers($conn);
$categories = getCategories($conn);
$tickets = getTickets($conn);


// Handle ticket details view
$ticketDetail = null;
$ticketDetailsHTML = '';
$ticketPrintHTML = '';
if (isset($_GET['view_ticket']) && is_numeric($_GET['view_ticket'])) {
    $ticketDetail = getTicketDetails($conn, $_GET['view_ticket']);
    if ($ticketDetail) {
        $ticketDetailsHTML = generateTicketDetailsHTML($ticketDetail);
        $ticketPrintHTML = generateTicketPrintHTML($ticketDetail);
        echo "<script>window.onload = function() { document.getElementById('viewTicketModal').style.display = 'block'; }</script>";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Help Desk - Host</title>
    <link rel="stylesheet" href="../../Sneat/assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="help_desk.css">
    <style>
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px; }
        .sidebar a { color: white; display: block; padding: 10px; text-decoration: none; }
        .sidebar a:hover { background: #495057; }
        .ticket-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .ticket-table th, .ticket-table td { border: 1px solid #dee2e6; padding: 10px; text-align: left; }
        .ticket-table th { background: #f8f9fa; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 600px; border-radius: 5px; }
        .close { float: right; font-size: 20px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group select, .form-group textarea, .form-group input { width: 100%; padding: 8px; }
        .submit-btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .submit-btn:hover { background: #0056b3; }
        .priority-low { color: green; }
        .priority-medium { color: orange; }
        .priority-high { color: red; }
        .priority-critical { color: darkred; font-weight: bold; }
        .status-open { color: blue; }
        .status-in-progress { color: orange; }
        .status-resolved { color: green; }
        .status-closed { color: gray; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .filter-btn { padding: 6px 12px; border: 1px solid #626569; border-radius: 4px; background: #f8f9fa; cursor: pointer; color: #626569; }
        .filter-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .filter-btn:hover { background: #e9ecef; }
        .filter-btn.active:hover { background: #0056b3; }

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

        @media (max-width: 768px) {
            .filter-btn { padding: 4px 8px; font-size: 0.9em; }
            .filter-group { gap: 5px; }
            .chat-container {
                width: calc(100vw - 40px);
                height: calc(100vh - 140px);
                bottom: 80px;
                right: 20px;
                left: 20px;
            }
        }
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
        #layout-navbar {
            position: sticky;
            top: 0;
            z-index: 999;
            background-color: var(--bs-body-bg);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding-top: 0.5rem; /* Reduce top padding */
            padding-bottom: 0.5rem;
            margin-top: 0; /* Ensure no margin above */
        }



        .container-p-y {
            padding-top: 0.5rem !important; /* Reduce top padding */
            padding-bottom: 1rem !important;
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
            padding-top: 0.8rem; /* Remove any padding above content */
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }
        .layout-menu-collapsed #layout-menu .layout-menu-toggle {
            animation: pulse-glow 2s infinite !important;
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include __DIR__ . '/host-sidebar.php'; ?>
        <div class="layout-content">
            <!-- Navbar -->
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base bx bx-menu icon-md"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!-- Page Title -->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2">Help Desk Tickets</h4>
                        </div>
                    </div>
                    <!-- Create Ticket button -->
                    <div class="navbar-nav align-items-center me-3">
                        <button class="btn btn-primary" id="createTicketBtn">
                            <i class="fas fa-plus-circle me-2"></i> Create New Ticket
                        </button>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <div class="container-fluid container-p-y">
                <?php if (isset($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover ticket-table">
                            <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="ticketTableBody">
                            <?php if (empty($tickets)): ?>
                                <tr><td colspan="6" style="text-align: center;">No tickets found</td></tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr data-status="<?php echo $ticket['Status']; ?>" data-priority="<?php echo $ticket['Priority']; ?>">
                                        <td><?php echo $ticket['TicketID']; ?></td>
                                        <td>
                                            <?php
                                            $description = htmlspecialchars($ticket['Description']);
                                            echo strlen($description) > 25 ? substr($description, 0, 25) . '...' : $description;
                                            ?>
                                        </td>
                                        <td><?php echo $ticket['CategoryName'] ? htmlspecialchars($ticket['CategoryName']) : 'Uncategorized'; ?></td>
                                        <td><span class="priority-<?php echo htmlspecialchars($ticket['Priority']); ?>"><?php echo ucfirst($ticket['Priority']); ?></span></td>
                                        <td><span class="status-<?php echo htmlspecialchars($ticket['Status']); ?>"><?php echo ucfirst($ticket['Status']); ?></span></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($ticket['CreatedDate'])); ?></td>
                                        <td>
                                            <a href="host_tickets.php?view_ticket=<?php echo $ticket['TicketID']; ?>" class="btn btn-sm btn-outline-primary" title="View details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
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
                <h3>Help Desk Assistant</h3>
                <p>How can I help you with your tickets today?</p>
            </div>

            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <h4>👋 Welcome!</h4>
                    <p>I'm your AI assistant for the Help Desk system. I can help you with:</p>
                    <ul>
                        <li>Creating new tickets</li>
                        <li>Checking ticket status</li>
                        <li>Resolving common issues</li>
                        <li>Answering questions about the system</li>
                    </ul>
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
                    placeholder="Type your question about tickets..."
                    rows="1"
                ></textarea>
                <button class="chat-send" id="chatSend">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

<!-- Create Ticket Modal -->
<div id="createTicketModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close">&times;</span>
        <h2>Create New Ticket</h2>
        <form id="createTicketForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="action" value="create_ticket">
            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="assigned_to">Assigned To:</label>
                    <select id="assigned_to" name="assigned_to" class="form-control">
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="priority">Priority:</label>
                    <select id="priority" name="priority" required class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required class="form-control" style="min-height: 100px;"></textarea>
            </div>

            <div class="form-group" style="text-align: right;">
                <button type="button" id="submitTicketBtn" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Ticket
                </button>
            </div>
        </form>
        <div id="formFeedback" style="margin-top: 15px;"></div>
    </div>
</div>

    <!-- View Ticket Modal -->
    <div id="viewTicketModal" class="modal">
        <div class="modal-content">
            <span class="close">×</span>
            <h2>Ticket Details</h2>
            <div id="ticketDetails"><?php echo $ticketDetailsHTML; ?></div>
        </div>
    </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="../../Sneat/assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
<script src="../../Sneat/assets/vendor/libs/moment/moment.js"></script>
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>
<script>
    // Initialize modal functionality
    function initializeModalEvents() {
        console.log('Initializing modal events');

        // Open create ticket modal
        const createTicketBtn = document.getElementById('createTicketBtn');
        if (createTicketBtn) {
            createTicketBtn.addEventListener('click', function() {
                console.log('Opening create ticket modal');
                document.getElementById('createTicketModal').style.display = 'block';
            });
        } else {
            console.error('Create ticket button not found');
        }

        // Close modals
        document.querySelectorAll('.close').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function() {
                console.log('Closing modal');
                this.closest('.modal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                console.log('Closing modal via outside click');
                event.target.style.display = 'none';
            }
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing event listeners');

        // Initialize modals
        initializeModalEvents();

        // Create ticket form submission
        document.getElementById('submitTicketBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('createTicketForm');
            const formData = new FormData(form);
            const submitBtn = this;
            const feedbackDiv = document.getElementById('formFeedback');

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            feedbackDiv.innerHTML = '';
            feedbackDiv.className = '';

            fetch('host_tickets.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Server error: ${response.status} - ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        feedbackDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        // Close modal and refresh after short delay
                        setTimeout(() => {
                            document.getElementById('createTicketModal').style.display = 'none';
                            window.location.reload();
                        }, 1000);
                    } else {
                        feedbackDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Error creating ticket'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Ticket';
                });
        });

        // Dropdown menu handling
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Toggling dropdown for ticket ID:', this.getAttribute('data-ticket-id'));
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    if (menu !== this.nextElementSibling) {
                        menu.classList.remove('show');
                    }
                });
                this.nextElementSibling.classList.toggle('show');
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                console.log('Closing all dropdowns');
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // View ticket
        document.querySelectorAll('.view-ticket').forEach(btn => {
            btn.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                console.log('Viewing ticket ID:', ticketId);
                window.location.href = `host_tickets.php?view_ticket=${ticketId}`;
            });
        });

        // Assign ticket
        document.querySelectorAll('.edit-ticket').forEach(btn => {
            btn.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                console.log('Fetching assign modal for ticket ID:', ticketId);
                fetch(`ticket_ajax.php?action=get_assign_modal&ticket_id=${ticketId}`)
                    .then(response => {
                        console.log('Assign modal response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Assign modal data:', data);
                        if (data.success) {
                            const modalContainer = document.createElement('div');
                            modalContainer.innerHTML = data.html;
                            document.body.appendChild(modalContainer);
                            const assignModal = document.getElementById('assignTicketModal');
                            if (assignModal) {
                                assignModal.style.display = 'block';
                                document.querySelector('#assignTicketModal .close').addEventListener('click', function() {
                                    console.log('Closing assign modal');
                                    assignModal.style.display = 'none';
                                    modalContainer.remove();
                                });
                                const assignForm = document.querySelector('#assignTicketModal form');
                                if (assignForm) {
                                    assignForm.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        console.log('Submitting assign form for ticket ID:', ticketId);
                                        const formData = new FormData(this);
                                        fetch('ticket_ajax.php', { method: 'POST', body: formData })
                                            .then(response => response.json())
                                            .then(data => {
                                                console.log('Assign form response:', data);
                                                if (data.success) {
                                                    alert(data.message);
                                                    assignModal.style.display = 'none';
                                                    modalContainer.remove();
                                                    window.location.reload();
                                                } else {
                                                    alert(data.message || 'Error assigning ticket');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error submitting assign form:', error);
                                                alert('Error assigning ticket: ' + error.message);
                                            });
                                    });
                                } else {
                                    console.error('Assign form not found in modal');
                                }
                            } else {
                                console.error('Assign modal not found in response HTML');
                            }
                        } else {
                            alert(data.message || 'Error fetching assign modal');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching assign modal:', error);
                        alert('Error fetching assign modal: ' + error.message);
                    });
            });
        });

        // Resolve ticket
        document.querySelectorAll('.resolve-ticket').forEach(btn => {
            btn.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                console.log('Fetching resolve modal for ticket ID:', ticketId);
                fetch(`ticket_ajax.php?action=get_resolve_modal&ticket_id=${ticketId}`)
                    .then(response => {
                        console.log('Resolve modal response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Resolve modal data:', data);
                        if (data.success) {
                            const modalContainer = document.createElement('div');
                            modalContainer.innerHTML = data.html;
                            document.body.appendChild(modalContainer);
                            const resolveModal = document.getElementById('resolveTicketModal');
                            if (resolveModal) {
                                resolveModal.style.display = 'block';
                                document.querySelector('#resolveTicketModal .close').addEventListener('click', function() {
                                    console.log('Closing resolve modal');
                                    resolveModal.style.display = 'none';
                                    modalContainer.remove();
                                });
                                const resolveForm = document.querySelector('#resolveTicketModal form');
                                if (resolveForm) {
                                    resolveForm.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        console.log('Submitting resolve form for ticket ID:', ticketId);
                                        const formData = new FormData(this);
                                        fetch('ticket_ajax.php', { method: 'POST', body: formData })
                                            .then(response => response.json())
                                            .then(data => {
                                                console.log('Resolve form response:', data);
                                                if (data.success) {
                                                    alert(data.message);
                                                    resolveModal.style.display = 'none';
                                                    modalContainer.remove();
                                                    window.location.reload();
                                                } else {
                                                    alert(data.message || 'Error resolving ticket');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error submitting resolve form:', error);
                                                alert('Error resolving ticket: ' + error.message);
                                            });
                                    });
                                } else {
                                    console.error('Resolve form not found in modal');
                                }
                            } else {
                                console.error('Resolve modal not found in response HTML');
                            }
                        } else {
                            alert(data.message || 'Error fetching resolve modal');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching resolve modal:', error);
                        alert('Error fetching resolve modal: ' + error.message);
                    });
            });
        });

        // Reopen ticket action
        document.querySelectorAll('.reopen-ticket').forEach(btn => {
            btn.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                console.log('Attempting to reopen ticket ID:', ticketId);
                if (confirm(`Are you sure you want to reopen ticket #${ticketId}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'reopen_ticket');
                    formData.append('ticket_id', ticketId);
                    fetch('ticket_ajax.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin' // Ensure cookies are sent, though not required
                    })
                        .then(response => {
                            console.log('Reopen ticket response status:', response.status);
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error(`HTTP error ${response.status}: ${text}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Reopen ticket response:', data);
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert(data.message || 'Error reopening ticket');
                            }
                        })
                        .catch(error => {
                            console.error('Error reopening ticket:', error);
                            alert('Error reopening ticket: ' + error.message);
                        });
                }
            });
        });

        // Close ticket
        document.querySelectorAll('.close-ticket').forEach(btn => {
            btn.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                console.log('Attempting to close ticket ID:', ticketId);
                if (confirm(`Are you sure you want to close ticket #${ticketId}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'close_ticket');
                    formData.append('ticket_id', ticketId);
                    fetch('ticket_ajax.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            console.log('Close ticket response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Close ticket response:', data);
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert(data.message || 'Error closing ticket');
                            }
                        })
                        .catch(error => {
                            console.error('Error closing ticket:', error);
                            alert('Error closing ticket: ' + error.message);
                        });
                }
            });
        });
    });
    // Enhanced Gemini Chat Bot Implementation with Ticket Record Access
    class HelpDeskChatBot {
        constructor() {
            this.apiKey = 'AIzaSyACxk5zCzJt6H0jJ2vs2sIP98V9jj7NcL0';
            this.apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
            this.isOpen = false;
            this.conversationHistory = [];
            this.ticketsData = <?php echo json_encode($tickets); ?>;
            this.userRole = '<?php echo $userRole; ?>';
            this.userId = '<?php echo $userId; ?>';

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

        // ENHANCED: Updated handleSendMessage with detailed ticket context
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
                // Check if this is a specific ticket query first
                const specificResponse = await this.handleTicketQuery(message, this.ticketsData);

                if (specificResponse) {
                    this.hideTyping();
                    this.addMessage(specificResponse, 'bot');
                    return;
                }

                // Prepare detailed ticket information for general queries
                const ticketSummary = this.prepareTicketContext(this.ticketsData, this.userRole);

                // Enhanced system message with detailed ticket context
                const systemMessage = `You are a helpful AI assistant for a help desk ticket management system.
                    The user is a ${this.userRole} (ID: ${this.userId}) managing tickets.

                    CURRENT TICKET DATA:
                    ${ticketSummary}

                    You can help with:
                    - Finding specific tickets by ID, status, priority, or description
                    - Providing ticket details and history
                    - Suggesting actions for tickets
                    - Creating new tickets
                    - Checking ticket status and assignments
                    - Resolving common issues
                    - Answering questions about the ticket system
                    - Providing guidance on ticket management

                    When referencing tickets, always include the Ticket ID for clarity.
                    Keep responses concise and friendly. Current date: ${new Date().toLocaleDateString()}

                    User's message: ${message}`;

                // Send to Gemini API
                const response = await this.sendToGemini(systemMessage);
                this.hideTyping();
                this.addMessage(response, 'bot');
            } catch (error) {
                this.hideTyping();
                this.addMessage('Sorry, I encountered an error. Please try again later.', 'bot', true);
                console.error('Gemini API Error:', error);
            }
        }

        // NEW: Prepare comprehensive ticket context for AI
        prepareTicketContext(ticketsData, userRole) {
            if (!ticketsData || ticketsData.length === 0) {
                return "No tickets found in the system.";
            }

            let context = `TICKET STATISTICS:
    - Total Tickets: ${ticketsData.length}
    - Open: ${ticketsData.filter(t => t.Status === 'open').length}
    - In-Progress: ${ticketsData.filter(t => t.Status === 'in-progress').length}
    - Resolved: ${ticketsData.filter(t => t.Status === 'resolved').length}
    - Closed: ${ticketsData.filter(t => t.Status === 'closed').length}

DETAILED TICKET RECORDS:
`;

            // Add detailed information for each ticket
            ticketsData.forEach((ticket, index) => {
                if (index < 20) { // Limit to first 20 tickets to avoid context length issues
                    context += `
Ticket #${ticket.TicketID}:
  - Description: ${ticket.Description}
  - Priority: ${ticket.Priority}
  - Status: ${ticket.Status}
  - Created: ${ticket.CreatedDate}`;

                    // Add role-specific information
                    if (userRole === 'Front Desk Staff') {
                        context += `
  - Created By: ${ticket.CreatedByName || 'Unknown'}
  - Assigned To: ${ticket.AssignedToName || 'Unassigned'}
  - Category: ${ticket.CategoryName || 'Uncategorized'}`;
                    }
                    context += '\n';
                }
            });

            if (ticketsData.length > 20) {
                context += `\n... and ${ticketsData.length - 20} more tickets (showing first 20 for brevity)\n`;
            }

            // Add priority and status breakdowns
            const priorities = ticketsData.reduce((acc, ticket) => {
                acc[ticket.Priority] = (acc[ticket.Priority] || 0) + 1;
                return acc;
            }, {});

            context += `
PRIORITY BREAKDOWN:
${Object.entries(priorities).map(([priority, count]) => `- ${priority}: ${count}`).join('\n')}

RECENT TICKETS (Last 5):
`;

            // Add most recent tickets
            const recentTickets = ticketsData
                .sort((a, b) => new Date(b.CreatedDate) - new Date(a.CreatedDate))
                .slice(0, 5);

            recentTickets.forEach(ticket => {
                context += `- Ticket #${ticket.TicketID}: ${ticket.Description.substring(0, 50)}${ticket.Description.length > 50 ? '...' : ''} (${ticket.Status})\n`;
            });

            return context;
        }

        // NEW: Handle specific ticket queries before sending to Gemini
        async handleTicketQuery(message, ticketsData) {
            // Check if user is asking about specific ticket ID
            const ticketIdMatch = message.match(/ticket\s*#?(\d+)/i);
            if (ticketIdMatch) {
                const ticketId = ticketIdMatch[1];
                const ticket = ticketsData.find(t => t.TicketID == ticketId);

                if (ticket) {
                    return this.formatTicketDetails(ticket);
                } else {
                    return `I couldn't find ticket #${ticketId} in the system. Please check the ticket ID and try again.`;
                }
            }

            // Check for status queries
            const statusMatch = message.match(/(open|in-progress|resolved|closed)\s+tickets?/i);
            if (statusMatch) {
                const status = statusMatch[1].toLowerCase();
                const matchingTickets = ticketsData.filter(t => t.Status.toLowerCase() === status);

                if (matchingTickets.length > 0) {
                    return this.formatTicketList(matchingTickets, `${status} tickets`);
                } else {
                    return `No ${status} tickets found.`;
                }
            }

            // Check for priority queries
            const priorityMatch = message.match(/(high|low|medium|critical)\s+priority\s+tickets?/i);
            if (priorityMatch) {
                const priority = priorityMatch[1].toLowerCase();
                const matchingTickets = ticketsData.filter(t => t.Priority.toLowerCase() === priority);

                if (matchingTickets.length > 0) {
                    return this.formatTicketList(matchingTickets, `${priority} priority tickets`);
                } else {
                    return `No ${priority} priority tickets found.`;
                }
            }

            // Check for assignment queries
            const assignedMatch = message.match(/(?:tickets?\s+assigned\s+to|assigned\s+tickets?)\s+(.+)/i);
            if (assignedMatch && this.userRole === 'Front Desk Staff') {
                const assigneeName = assignedMatch[1].trim();
                const matchingTickets = ticketsData.filter(t =>
                    t.AssignedToName && t.AssignedToName.toLowerCase().includes(assigneeName.toLowerCase())
                );

                if (matchingTickets.length > 0) {
                    return this.formatTicketList(matchingTickets, `tickets assigned to ${assigneeName}`);
                } else {
                    return `No tickets found assigned to ${assigneeName}.`;
                }
            }

            // Check for unassigned tickets query
            if (message.match(/unassigned\s+tickets?/i) && this.userRole === 'Front Desk Staff') {
                const unassignedTickets = ticketsData.filter(t => !t.AssignedToName);

                if (unassignedTickets.length > 0) {
                    return this.formatTicketList(unassignedTickets, 'unassigned tickets');
                } else {
                    return 'All tickets are currently assigned.';
                }
            }

            // Check for today's tickets
            if (message.match(/today'?s?\s+tickets?|tickets?\s+created\s+today/i)) {
                const today = new Date().toDateString();
                const todayTickets = ticketsData.filter(t =>
                    new Date(t.CreatedDate).toDateString() === today
                );

                if (todayTickets.length > 0) {
                    return this.formatTicketList(todayTickets, "today's tickets");
                } else {
                    return 'No tickets were created today.';
                }
            }

            return null; // No specific query matched
        }

        // NEW: Format detailed ticket information
        formatTicketDetails(ticket) {
            let details = `**Ticket #${ticket.TicketID} Details:**
        - **Description:** ${ticket.Description}
        - **Priority:** ${ticket.Priority}
        - **Status:** ${ticket.Status}
        - **Created:** ${new Date(ticket.CreatedDate).toLocaleDateString()}`;

            if (this.userRole === 'Front Desk Staff') {
                details += `
            - **Created By:** ${ticket.CreatedByName || 'Unknown'}
            - **Assigned To:** ${ticket.AssignedToName || 'Unassigned'}
            - **Category:** ${ticket.CategoryName || 'Uncategorized'}`;
            }

            // Add suggested actions based on status
            if (ticket.Status === 'open') {
                details += `\n\n**Suggested Actions:**
    - Assign this ticket to a staff member
    - Set priority if not already set
    - Add category for better organization`;
                } else if (ticket.Status === 'in-progress') {
                details += `\n\n**Current Status:** This ticket is being worked on.`;
            } else if (ticket.Status === 'resolved') {
                details += `\n\n**Status:** This ticket has been resolved and can be closed if the solution is satisfactory.`;
            }

            return details;
        }

        // NEW: Format ticket list for multiple tickets
        formatTicketList(tickets, description) {
            let result = `**Found ${tickets.length} ${description}:**\n\n`;

            tickets.slice(0, 10).forEach(ticket => { // Show max 10 tickets
                const truncatedDesc = ticket.Description.length > 60
                    ? ticket.Description.substring(0, 60) + '...'
                    : ticket.Description;

                result += `• **Ticket #${ticket.TicketID}** (${ticket.Priority} priority, ${ticket.Status})\n  ${truncatedDesc}\n`;

                if (this.userRole === 'Front Desk Staff' && ticket.AssignedToName) {
                    result += `  Assigned to: ${ticket.AssignedToName}\n`;
                }
                result += '\n';
            });

            if (tickets.length > 10) {
                result += `... and ${tickets.length - 10} more tickets. Use more specific search terms to narrow results.`;
            }

            return result;
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
        new HelpDeskChatBot();
    });
    // Temporary test - add this at the bottom of your page
    console.log('Button exists:', document.getElementById('createTicketBtn') !== null);
    console.log('Modal exists:', document.getElementById('createTicketModal') !== null);

    // Simple test click handler
    document.getElementById('createTicketBtn').addEventListener('click', function() {
        console.log('Button clicked!');
        document.getElementById('createTicketModal').style.display = 'block';
    });
</script>
</body>
</html>