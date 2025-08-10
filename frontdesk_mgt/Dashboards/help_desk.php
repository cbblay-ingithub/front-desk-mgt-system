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

// Modify ticket retrieval based on role
if ($userRole == 'host') {
    $sql = "SELECT t.TicketID, t.Description, t.Priority, t.Status, t.CreatedDate,
            u1.Name as CreatedByName,
            u2.Name as AssignedToName,
            c.CategoryName
            FROM Help_Desk t
            LEFT JOIN users u1 ON t.CreatedBy = u1.UserID
            LEFT JOIN users u2 ON t.AssignedTo = u2.UserID
            LEFT JOIN TicketCategories c ON t.CategoryID = c.CategoryID
            WHERE t.CreatedBy = ?
            ORDER BY t.CreatedDate DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Ticket query prepare failed: " . $conn->error);
        die("Query preparation failed");
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $tickets = getTickets($conn);
}

// Handle ticket details view
$ticketDetail = null;
$ticketDetailsHTML = '';
$ticketPrintHTML = '';
if (isset($_GET['view_ticket']) && is_numeric($_GET['view_ticket'])) {
    $ticketDetail = getTicketDetails($conn, $_GET['view_ticket']);
    if ($ticketDetail) {
        if ($userRole == 'host' && $ticketDetail['CreatedBy'] != $userId) {
            $error = "You do not have permission to view this ticket.";
        } else {
            $ticketDetailsHTML = generateTicketDetailsHTML($ticketDetail);
            $ticketPrintHTML = generateTicketPrintHTML($ticketDetail);
            echo "<script>window.onload = function() { document.getElementById('viewTicketModal').style.display = 'block'; }</script>";
        }
    }
}

// Check for old tickets to auto-close (front desk only)
if ($userRole == 'Front Desk Staff') {
    $autoCloseMessage = autoCloseOldTickets($conn);
    if ($autoCloseMessage) {
        $message = isset($message) ? $message . "<br>" . $autoCloseMessage : $autoCloseMessage;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html
        lang="en"
        class="layout-navbar-fixed layout-menu-fixed layout-compact "
        dir="ltr"
        data-skin="default"
        data-assets-path="../../assets/"
        data-template="vertical-menu-template"
        data-bs-theme="light"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Support Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="help_desk.css">
    <link rel="stylesheet" href="notification.css">
    <style>
        /* Dropdown menu styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            color: #343a40;
            padding: 5px 10px;
            border-radius: 3px;
            background-color: #f8f9fa;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: #fff;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            color: #212529;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }




        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px; }
        .sidebar a { color: white; display: block; padding: 10px; text-decoration: none; }
        .sidebar a:hover { background: #495057; }
        .container { flex: 1; padding: 20px; }
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



    </style>
</head>
<div data-user-id="<?php echo $_SESSION['$userID'] ?? ''; ?>">
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include 'sidebar.php'; ?>
            <div class="layout-page">
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
                                <h4 class="mb-0 fw-bold ms-2">Manage Tickets</h4>
                            </div>
                        </div>

                        <!-- Create Ticket Button -->
                        <div class="navbar-nav align-items-center me-3">
                            <button id="createTicketBtn" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create New Ticket
                            </button>
                        </div>
                        <!-- Search Button -->
                        <div class="navbar-nav align-items-center me-3">
                            <button id="searchToggle" class="btn btn-outline-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>

                        <!-- Search Input (initially hidden) -->
                        <div class="navbar-nav align-items-center me-3" id="searchContainer" style="display: none; width: 200px;">
                            <input type="text" class="form-control" id="search" placeholder="Search tickets...">
                        </div>

                        <!-- Filter Button -->
                        <div class="navbar-nav align-items-center me-3">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-filter"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown" style="width: 250px; padding: 10px;">
                                    <h6 class="dropdown-header">Filter Tickets</h6>
                                    <div class="px-3">
                                        <div class="mb-3">
                                            <label for="status-filter" class="form-label">Status</label>
                                            <select class="form-select" id="status-filter">
                                                <option value="">All Statuses</option>
                                                <option value="open">Open</option>
                                                <option value="in-progress">In Progress</option>
                                                <option value="resolved">Resolved</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="priority-filter" class="form-label">Priority</label>
                                            <select class="form-select" id="priority-filter">
                                                <option value="">All Priorities</option>
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="critical">Critical</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                        <div class="container-fluid container-p-y">
                            <table class="ticket-table">
                                <thead>
                                <tr>
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
                                <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No tickets found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $description = htmlspecialchars($ticket['Description']);
                                                $maxLength = 25;
                                                echo strlen($description) > $maxLength ? substr($description, 0, 25) . '...' : $description;
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['CreatedByName']); ?></td>
                                            <td><?php echo $ticket['AssignedToName'] ? htmlspecialchars($ticket['AssignedToName']) : 'Not assigned'; ?></td>
                                            <td><?php echo $ticket['CategoryName'] ? htmlspecialchars($ticket['CategoryName']) : 'Uncategorized'; ?></td>
                                            <td><span class="priority-<?php echo htmlspecialchars($ticket['Priority']); ?>"><?php echo ucfirst($ticket['Priority']); ?></span></td>
                                            <td><span class="status-<?php echo htmlspecialchars($ticket['Status']); ?>"><?php echo ucfirst($ticket['Status']); ?></span></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($ticket['CreatedDate'])); ?></td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="dropdown-toggle" data-ticket-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item view-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                            <i class="fas fa-eye me-2"></i> View
                                                        </a>
                                                        <?php if ($ticket['Status'] == 'closed'): ?>
                                                            <a class="dropdown-item reopen-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-undo me-2"></i> Reopen
                                                            </a>
                                                        <?php else: ?>
                                                            <a class="dropdown-item print-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-print me-2"></i> Print
                                                            </a>
                                                            <a class="dropdown-item edit-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-edit me-2"></i> Assign
                                                            </a>
                                                            <a class="dropdown-item resolve-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-check-circle me-2"></i> Resolve
                                                            </a>
                                                            <a class="dropdown-item close-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-times-circle me-2"></i> Close
                                                            </a>
                                                            <a class="dropdown-item delete-ticket" data-id="<?php echo htmlspecialchars($ticket['TicketID']); ?>">
                                                                <i class="fas fa-trash-alt me-2"></i> Delete
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


        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div id="createTicketModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Create New Ticket</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="action" value="create_ticket">

            <div class="form-row">
                <div class="form-group">
                    <label for="created_by">Created By:</label>
                    <select id="created_by" name="created_by" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Assigned To:</label>
                    <select id="assigned_to" name="assigned_to">
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
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
            </div>

            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea>
            </div>

            <button type="submit" class="submit-btn">Create Ticket</button>
        </form>
    </div>
</div>

<!-- View Ticket Modal -->
<div id="viewTicketModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Ticket Details</h2>
        <div id="ticketDetails">
            <?php if ($ticketDetail): ?>
                <?php echo $ticketDetailsHTML; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Print Layout Container -->
<div id="printLayout" class="print-layout">
    <?php if ($ticketDetail): ?>
        <?php echo $ticketPrintHTML; ?>
    <?php endif; ?>
</div>

<script src="notification.js"></script>
<script src="../../Sneat/assets/vendor/js/helpers.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fixed JavaScript that handles both Bootstrap and custom dropdowns

    // Basic client-side filtering
    const search = document.getElementById('search');
    const statusFilter = document.getElementById('status-filter');
    const priorityFilter = document.getElementById('priority-filter');

    // Toggle search input visibility
    document.getElementById('searchToggle').addEventListener('click', function() {
        const searchContainer = document.getElementById('searchContainer');
        searchContainer.style.display = searchContainer.style.display === 'none' ? 'block' : 'none';
        if (searchContainer.style.display === 'block') {
            document.getElementById('search').focus();
        }
    });

    // Prevent filter dropdowns from closing when clicking inside
    document.querySelectorAll('#status-filter, #priority-filter').forEach(select => {
        select.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    function filterTickets() {
        const searchTerm = search.value.toLowerCase();
        const status = statusFilter.value;
        const priority = priorityFilter.value;
        document.querySelectorAll('.ticket-table tbody tr').forEach(row => {
            const desc = row.cells[0].textContent.toLowerCase();
            const rowStatus = row.cells[5].textContent.toLowerCase();
            const rowPriority = row.cells[4].textContent.toLowerCase();
            const matchesSearch = desc.includes(searchTerm);
            const matchesStatus = !status || rowStatus.includes(status);
            const matchesPriority = !priority || rowPriority.includes(priority);
            row.style.display = matchesSearch && matchesStatus && matchesPriority ? '' : 'none';
        });
    }

    // Add event listeners to search and filters
    search.addEventListener('input', filterTickets);
    statusFilter.addEventListener('change', filterTickets);
    priorityFilter.addEventListener('change', filterTickets);

    // Function to handle CUSTOM dropdown toggle (for action buttons only)
    function toggleCustomDropdown(button) {
        const dropdown = button.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');

        // Only handle custom dropdowns (not Bootstrap ones)
        if (dropdown.querySelector('[data-bs-toggle]')) {
            return; // Let Bootstrap handle this
        }

        // Close all other custom dropdowns first
        document.querySelectorAll('.dropdown-menu.show').forEach(otherMenu => {
            const parentDropdown = otherMenu.closest('.dropdown');
            if (otherMenu !== menu && !parentDropdown.querySelector('[data-bs-toggle]')) {
                otherMenu.classList.remove('show');
            }
        });

        // Toggle current dropdown
        menu.classList.toggle('show');
    }

    // Add event listeners when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {

        // Handle CUSTOM dropdown toggles (action buttons only)
        document.addEventListener('click', function(e) {
            const dropdownToggle = e.target.closest('.dropdown-toggle');

            // Only handle custom dropdowns (not Bootstrap dropdowns with data-bs-toggle)
            if (dropdownToggle && !dropdownToggle.hasAttribute('data-bs-toggle')) {
                e.preventDefault();
                e.stopPropagation();
                toggleCustomDropdown(dropdownToggle);
            }
            // Close custom dropdowns when clicking outside (but not Bootstrap ones)
            else if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    const parentDropdown = menu.closest('.dropdown');
                    if (!parentDropdown.querySelector('[data-bs-toggle]')) {
                        menu.classList.remove('show');
                    }
                });
            }
        });

        // Prevent custom dropdown menu from closing when clicking inside it
        document.addEventListener('click', function(e) {
            const dropdownMenu = e.target.closest('.dropdown-menu');
            if (dropdownMenu) {
                const parentDropdown = dropdownMenu.closest('.dropdown');
                // Only prevent for custom dropdowns
                if (!parentDropdown.querySelector('[data-bs-toggle]')) {
                    e.stopPropagation();
                }
            }
        });

        // View ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-ticket')) {
                const btn = e.target.closest('.view-ticket');
                const ticketId = btn.getAttribute('data-id');
                console.log('Viewing ticket ID:', ticketId);
                window.location.href = `help_desk.php?view_ticket=${ticketId}`;
            }
        });

        // Print ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.print-ticket')) {
                const btn = e.target.closest('.print-ticket');
                const ticketId = btn.getAttribute('data-id');
                console.log('Printing ticket ID:', ticketId);
                window.location.href = `help_desk.php?view_ticket=${ticketId}&action=print`;
            }
        });

        // Assign ticket action (edit-ticket)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-ticket')) {
                const btn = e.target.closest('.edit-ticket');
                const ticketId = btn.getAttribute('data-id');
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
                            assignModal.style.display = 'block';

                            // Close modal handler
                            const closeBtn = assignModal.querySelector('.close');
                            if (closeBtn) {
                                closeBtn.addEventListener('click', function() {
                                    console.log('Closing assign modal');
                                    assignModal.style.display = 'none';
                                    modalContainer.remove();
                                });
                            }

                            // Form submit handler
                            const assignForm = assignModal.querySelector('form');
                            if (assignForm) {
                                assignForm.addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    console.log('Submitting assign form for ticket ID:', ticketId);
                                    const formData = new FormData(this);

                                    fetch('ticket_ajax.php', {
                                        method: 'POST',
                                        body: formData
                                    })
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
                            }
                        } else {
                            alert(data.message || 'Error fetching assign modal');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching assign modal:', error);
                        alert('Error fetching assign modal: ' + error.message);
                    });
            }
        });

        // Resolve ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.resolve-ticket')) {
                const btn = e.target.closest('.resolve-ticket');
                const ticketId = btn.getAttribute('data-id');
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
                            resolveModal.style.display = 'block';

                            // Close modal handler
                            const closeBtn = resolveModal.querySelector('.close');
                            if (closeBtn) {
                                closeBtn.addEventListener('click', function() {
                                    console.log('Closing resolve modal');
                                    resolveModal.style.display = 'none';
                                    modalContainer.remove();
                                });
                            }

                            // Form submit handler
                            const resolveForm = resolveModal.querySelector('form');
                            if (resolveForm) {
                                resolveForm.addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    console.log('Submitting resolve form for ticket ID:', ticketId);
                                    const formData = new FormData(this);

                                    fetch('ticket_ajax.php', {
                                        method: 'POST',
                                        body: formData
                                    })
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
                            }
                        } else {
                            alert(data.message || 'Error fetching resolve modal');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching resolve modal:', error);
                        alert('Error fetching resolve modal: ' + error.message);
                    });
            }
        });

        // Reopen ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.reopen-ticket')) {
                const btn = e.target.closest('.reopen-ticket');
                const ticketId = btn.getAttribute('data-id');
                console.log('Attempting to reopen ticket ID:', ticketId);

                if (confirm(`Are you sure you want to reopen ticket #${ticketId}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'reopen_ticket');
                    formData.append('ticket_id', ticketId);

                    fetch('ticket_ajax.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
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
            }
        });

        // Close ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.close-ticket')) {
                const btn = e.target.closest('.close-ticket');
                const ticketId = btn.getAttribute('data-id');
                console.log('Attempting to close ticket ID:', ticketId);

                if (confirm(`Are you sure you want to close ticket #${ticketId}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'close_ticket');
                    formData.append('ticket_id', ticketId);

                    fetch('ticket_ajax.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            console.log('Close ticket response status:', response.status);
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error(`HTTP error ${response.status}: ${text}`);
                                });
                            }
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
            }
        });

        // Delete ticket action
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-ticket')) {
                const btn = e.target.closest('.delete-ticket');
                const ticketId = btn.getAttribute('data-id');
                console.log('Attempting to delete ticket ID:', ticketId);

                if (confirm(`Are you sure you want to delete ticket #${ticketId}?`)) {
                    alert(`Delete ticket ${ticketId} - Implement this functionality`);
                }
            }
        });

        // Create ticket modal
        document.getElementById('createTicketBtn').addEventListener('click', function() {
            console.log('Opening create ticket modal');
            document.getElementById('createTicketModal').style.display = 'block';
        });

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

        // Keep session alive and update activity
        setInterval(() => {
            fetch('update_activity.php')
                .then(response => response.json())
                .then(data => {
                    if(!data.success) console.error('Activity update failed');
                });
        }, 60000);
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
    // Handle sidebar toggle
    document.getElementById('sidebarToggle').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const html = document.documentElement;
        const sidebar = document.getElementById('layout-menu');
        const toggleIcon = document.getElementById('toggleIcon');

        this.style.pointerEvents = 'none';
        html.classList.toggle('layout-menu-collapsed');
        const isCollapsed = html.classList.contains('layout-menu-collapsed');

        // Update icon
        if (isCollapsed) {
            toggleIcon.classList.remove('bx-chevron-left');
            toggleIcon.classList.add('bx-chevron-right');
        } else {
            toggleIcon.classList.remove('bx-chevron-right');
            toggleIcon.classList.add('bx-chevron-left');
        }

        // Store state
        localStorage.setItem('sidebarCollapsed', isCollapsed);

        setTimeout(() => {
            this.style.pointerEvents = 'auto';
        }, 300);
    });

    // Restore sidebar state on load
    document.addEventListener('DOMContentLoaded', function() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            document.documentElement.classList.add('layout-menu-collapsed');
            const toggleIcon = document.getElementById('toggleIcon');
            if (toggleIcon) {
                toggleIcon.classList.remove('bx-chevron-left');
                toggleIcon.classList.add('bx-chevron-right');
            }
        }
    });


</script>
</html>