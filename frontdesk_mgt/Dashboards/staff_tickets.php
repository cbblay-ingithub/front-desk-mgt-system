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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk Tickets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="help_desk.css">
    <style>
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
        @media (max-width: 768px) {
            .filter-btn { padding: 4px 8px; font-size: 0.9em; }
            .filter-group { gap: 5px; }
        }
    </style>
</head>
<body data-user-id="<?php echo $userId; ?>">
<div class="layout">
    <div class="sidebar">
        <?php if ($userRole == 'Front Desk Staff'): ?>
            <h4 class="text-white text-center">Front Desk Panel</h4>
            <a href="front-desk_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a href="visitor-mgt.php"><i class="fas fa-users me-2"></i> Manage Visitors</a>
            <a href="FD_frontend_dash.php"><i class="fas fa-calendar-check me-2"></i> Appointments</a>
            <a href="staff_tickets.php" class=" "><i class="fas fa-ticket"></i> Help Desk Tickets</a>
            <a href="lost_found.php"><i class="fa-solid fa-suitcase me-2"></i> View Lost & Found</a>
            <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        <?php else: ?>
            <h4 class="text-white text-center">Host Panel</h4>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a href="host_dashboard.php"><i class="fas fa-calendar-check me-2"></i> Manage Appointments</a>
            <a href="staff_tickets.php" class="active"><i class="fas fa-ticket"></i> Manage Tickets</a>
            <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        <?php endif; ?>
    </div>
    <div class="container">
        <header>
            <h1>Help Desk Tickets</h1>
            <button id="createTicketBtn" class="submit-btn">Create New Ticket</button>
        </header>

        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filters for Front Desk Only -->
        <?php if ($userRole == 'Front Desk Staff'): ?>
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
        <?php endif; ?>

        <table class="ticket-table">
            <thead>
            <tr>
                <th>Ticket ID</th>
                <th>Description</th>
                <?php if ($userRole == 'Front Desk Staff'): ?>
                    <th>Created By</th>
                    <th>Assigned To</th>
                    <th>Category</th>
                <?php endif; ?>
                <th>Priority</th>
                <th>Status</th>
                <th>Created Date</th>
                <?php if ($userRole == 'Front Desk Staff'): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody id="ticketTableBody">
            <?php if (empty($tickets)): ?>
                <tr><td colspan="<?php echo $userRole == 'host' ? 5 : 8; ?>" style="text-align: center;">No tickets found</td></tr>
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
                        <?php if ($userRole == 'Front Desk Staff'): ?>
                            <td><?php echo htmlspecialchars($ticket['CreatedByName']); ?></td>
                            <td><?php echo $ticket['AssignedToName'] ? htmlspecialchars($ticket['AssignedToName']) : 'Not assigned'; ?></td>
                            <td><?php echo $ticket['CategoryName'] ? htmlspecialchars($ticket['CategoryName']) : 'Uncategorized'; ?></td>
                        <?php endif; ?>
                        <td><span class="priority-<?php echo $ticket['Priority']; ?>"><?php echo ucfirst($ticket['Priority']); ?></span></td>
                        <td><span class="status-<?php echo $ticket['Status']; ?>"><?php echo ucfirst($ticket['Status']); ?></span></td>
                        <td><?php echo date('M d, Y H:i', strtotime($ticket['CreatedDate'])); ?></td>
                        <?php if ($userRole == 'Front Desk Staff'): ?>
                            <td>
                                <div class="dropdown">
                                    <button class="dropdown-toggle" data-ticket-id="<?php echo $ticket['TicketID']; ?>">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item view-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                            <i class="fas fa-eye me-2"></i> View
                                        </a>
                                        <a class="dropdown-item edit-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                            <i class="fas fa-edit me-2"></i> Assign
                                        </a>
                                        <a class="dropdown-item resolve-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                            <i class="fas fa-check-circle me-2"></i> Resolve
                                        </a>
                                        <a class="dropdown-item close-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                            <i class="fas fa-times-circle me-2"></i> Close
                                        </a>
                                    </div>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Ticket Modal -->
<div id="createTicketModal" class="modal">
    <div class="modal-content">
        <span class="close">×</span>
        <h2>Create New Ticket</h2>
        <form id="createTicketForm" method="POST" action="staff_tickets.php">
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
            <?php if ($userRole == 'host'): ?>
                <div class="form-group">
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" required placeholder="Describe the issue..."></textarea>
                </div>
                <input type="hidden" name="priority" value="medium">
            <?php else: ?>
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
            <?php endif; ?>
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
</script>
</body>
</html>