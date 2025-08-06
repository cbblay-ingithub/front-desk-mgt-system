<?php
// Enable error reporting for debugging
ini_set('display_errors', 0); // Hide errors from users
ini_set('log_errors', 1); // Log errors
ini_set('error_log', 'php_errors.log'); // Specify your error log path

// Include database configuration and core ticket functions
global $conn;
require_once '../dbConfig.php';

require_once 'ticket_functions.php';
require_once 'ticket_ops.php';
require_once 'view_ticket.php';

// Start session to access user role and ID
session_start();
$userRole = $_SESSION['role'] ?? 'host'; // Default to host if role not set
$userId = $_SESSION['user_id'] ?? null;

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
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (isset($error) && $error) {
        error_log('Ticket creation error: ' . $error);
        echo json_encode(['success' => false, 'error' => $error]);
    } else {
        error_log('Ticket creation success: ' . $message);
        echo json_encode(['success' => true, 'message' => $message]);
    }
    exit;
}

// Get data for dropdowns and ticket list
$users = getUsers($conn);
$categories = getCategories($conn);

// Front Desk gets all tickets
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

// Check for old tickets to auto-close (front desk only)
$autoCloseMessage = autoCloseOldTickets($conn);
if ($autoCloseMessage) {
    $message = isset($message) ? $message . "<br>" . $autoCloseMessage : $autoCloseMessage;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk Tickets - Front Desk</title>
    <!-- Main CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notification.css">

    <!-- Sneat CSS (same as visitor-mgt.php) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <link rel="stylesheet" href="help_desk.css">
    <style>
        /* Layout structure matching visitor-mgt.php */
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

        /* Original ticket styles */
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
        .dropdown { position: relative; display: inline-block;}
        .dropdown-toggle { padding: 5px 10px;color: #626569; border-radius: 3px; background: #ffffff; border: none; cursor: pointer; }
        .dropdown-menu { display: none; position: absolute; background: white; min-width: 120px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 1; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { padding: 8px 12px; display: block; color: #212529; cursor: pointer; }
        .dropdown-item:hover { background: #f8f9fa; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .filter-btn { padding: 6px 12px; border: 1px solid #626569; border-radius: 4px; background: #f8f9fa; cursor: pointer; color: #626569; }
        .filter-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .filter-btn:hover { background: #e9ecef; }
        .filter-btn.active:hover { background: #0056b3; }
        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 13px;
            border-left: 4px solid #c33;
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
    </style>
</head>
<body data-user-id="<?php echo $userId; ?>">
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
                            <h4 class="mb-0 fw-bold ms-2">Help Desk Tickets</h4>
                        </div>
                    </div>
                    <!--Create Ticket button-->
                    <div class="navbar-nav align-items-center me-3">
                        <button id="createTicketBtn" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Create New Ticket
                        </button>
                    </div>
                </div>
            </nav>

            <div class="container-fluid container-p-y">
                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters for Front Desk -->
                <div class="filter-group">
                    <div>
                        <strong>Status:</strong>
                        <button class="filter-btn status-filter" data-status="">All</button>
                        <button class="filter-btn status-filter" data-status="open">Open</button>
                        <button class="filter-btn status-filter" data-status="in-progress">In-Progress</button>
                        <button class="filter-btn status-filter" data-status="resolved">Resolved</button>
                        <button class="filter-btn status-filter" data-status="closed">Closed</button>
                    </div>
                    <div>
                        <strong>Priority:</strong>
                        <button class="filter-btn priority-filter" data-priority="">All</button>
                        <button class="filter-btn priority-filter" data-priority="low">Low</button>
                        <button class="filter-btn priority-filter" data-priority="medium">Medium</button>
                        <button class="filter-btn priority-filter" data-priority="high">High</button>
                        <button class="filter-btn priority-filter" data-priority="critical">Critical</button>
                    </div>
                    <button class="filter-btn reset-filter">Reset Filters</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                        <tr>
                            <th>Ticket ID</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Assigned To</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="ticketTableBody">
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="9" style="text-align: center;">No tickets found</td></tr>
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
                                    <td><?php echo htmlspecialchars($ticket['CreatedByName']); ?></td>
                                    <td><?php echo $ticket['AssignedToName'] ? htmlspecialchars($ticket['AssignedToName']) : 'Not assigned'; ?></td>
                                    <td><?php echo $ticket['CategoryName'] ? htmlspecialchars($ticket['CategoryName']) : 'Uncategorized'; ?></td>
                                    <td><span class="priority-<?php echo $ticket['Priority']; ?>"><?php echo ucfirst($ticket['Priority']); ?></span></td>
                                    <td><span class="status-<?php echo $ticket['Status']; ?>"><?php echo ucfirst($ticket['Status']); ?></span></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($ticket['CreatedDate'])); ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="dropdown-toggle" data-ticket-id="<?php echo $ticket['TicketID']; ?>">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item view-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                                    <i class="fas fa-eye me-2"></i> View
                                                </a>
                                                <?php if ($ticket['Status'] != 'closed'): ?>
                                                    <a class="dropdown-item edit-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                                        <i class="fas fa-edit me-2"></i> Assign
                                                    </a>
                                                    <a class="dropdown-item resolve-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                                        <i class="fas fa-check-circle me-2"></i> Resolve
                                                    </a>
                                                    <a class="dropdown-item close-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                                        <i class="fas fa-times-circle me-2"></i> Close
                                                    </a>
                                                <?php else: ?>
                                                    <a class="dropdown-item reopen-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                                        <i class="fas fa-undo me-2"></i> Reopen
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
    <div class="modal-content">
        <span class="close">×</span>
        <h2>Create New Ticket</h2>
        <form id="createTicketForm" method="POST" action="FD_tickets.php">
            <input type="hidden" name="action" value="create_ticket">
            <input type="hidden" name="created_by" value="<?php echo $userId; ?>">
            <div class="form-group">
                <label for="created_by">Created By:</label>
                <select id="created_by" name="created_by" required>
                    <option value="">Select User</option>
                    <?php foreach ($users as $id => $name): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="assigned_to">Assigned To:</label>
                    <select id="assigned_to" name="assigned_to">
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required placeholder="Describe the issue..."></textarea>
            </div>
            <div class="form-group">
                <label for="priority">Priority:</label>
                <select id="priority" name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>
            <button type="submit" class="submit-btn">Create Ticket</button>
        </form>
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
<!-- Include Sneat JS files like visitor-mgt.php does -->
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing event listeners');

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

        // Handle create ticket form submission
        const createTicketForm = document.getElementById('createTicketForm');
        if (createTicketForm) {
            createTicketForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Submitting create ticket form');
                const formData = new FormData(this);
                const actionUrl = window.location.pathname.split('/').pop(); // Get current script name (staff_tickets.php)
                console.log('Form action URL:', actionUrl);
                fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Explicitly set AJAX header
                    }
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.log('Raw response:', text); // Log raw response for debugging
                                throw new Error(`HTTP error ${response.status}: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Create ticket response:', data);
                        if (data.success) {
                            alert(data.message || 'Ticket created successfully!');
                            document.getElementById('createTicketModal').style.display = 'none';
                            window.location.reload();
                        } else {
                            alert(data.error || 'Error creating ticket');
                        }
                    })
                    .catch(error => {
                        console.error('Error creating ticket:', error);
                        alert('Error creating ticket: ' + error.message);
                    });
            });
        } else {
            console.error('Create ticket form not found');
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
                window.location.href = `staff_tickets.php?view_ticket=${ticketId}`;
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

        // Filter tickets (front desk only)
        function filterTickets() {
            console.log('Filtering tickets');
            const activeStatus = document.querySelector('.status-filter.active')?.dataset.status || '';
            const activePriority = document.querySelector('.priority-filter.active')?.dataset.priority || '';
            const rows = document.querySelectorAll('#ticketTableBody tr');
            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                const rowPriority = row.dataset.priority;
                const show = (!activeStatus || rowStatus === activeStatus) &&
                    (!activePriority || rowPriority === activePriority);
                row.style.display = show ? '' : 'none';
            });
        }

        // Initialize filter buttons
        document.querySelectorAll('.status-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Status filter clicked:', this.dataset.status);
                document.querySelectorAll('.status-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterTickets();
            });
        });

        document.querySelectorAll('.priority-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Priority filter clicked:', this.dataset.priority);
                document.querySelectorAll('.priority-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterTickets();
            });
        });

        // Reset filters
        document.querySelector('.reset-filter')?.addEventListener('click', function() {
            console.log('Resetting filters');
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector('.status-filter[data-status=""]').classList.add('active');
            document.querySelector('.priority-filter[data-priority=""]').classList.add('active');
            filterTickets();
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
</script>
</body>
</html>