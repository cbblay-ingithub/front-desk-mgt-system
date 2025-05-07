<?php
// Include your database configuration
global $conn;
require_once '../dbConfig.php';

// Include core functions
require_once 'ticket_functions.php';
require_once 'ticket_ops.php';
require_once 'view_ticket.php';

// Process ticket creation
$result = createTicket($conn);
$message = $result['message'];
$error = $result['error'];

// Get data for dropdown lists
$users = getUsers($conn);
$categories = getCategories($conn);
$tickets = getTickets($conn);

// Get ticket details if requested
$ticketDetail = null;
$ticketDetailsHTML = '';
$ticketPrintHTML = '';

// Process operations at the top of your help_desk.php file
$opResult = processTicketOperation($conn);
if (isset($opResult['message']) && $opResult['message']) {
    $message = $opResult['message'];
}
if (isset($opResult['error']) && $opResult['error']) {
    $error = $opResult['error'];
}

// Check for old tickets that need to be closed automatically
$autoCloseMessage = autoCloseOldTickets($conn);
if ($autoCloseMessage) {
    $message = isset($message) ? $message . "<br>" . $autoCloseMessage : $autoCloseMessage;
}

if (isset($_GET['view_ticket']) && is_numeric($_GET['view_ticket'])) {
    $ticketDetail = getTicketDetails($conn, $_GET['view_ticket']);
    if ($ticketDetail) {
        $ticketDetailsHTML = generateTicketDetailsHTML($ticketDetail);
        $ticketPrintHTML = generateTicketPrintHTML($ticketDetail);

        // If action is print, trigger print dialog
        if (isset($_GET['action']) && $_GET['action'] == 'print') {
            echo "<script>window.onload = function() { window.print(); }</script>";
        } else {
            // Show the view modal
            echo "<script>window.onload = function() { document.getElementById('viewTicketModal').style.display = 'block'; }</script>";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk System</title>
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
    </style>
</head>
<body data-user-id="<?php echo $_SESSION['user_id'] ?? ''; ?>">
<div class="layout">
    <div class="sidebar">
        <h4 class="text-white text-center">Support Staff Panel</h4>
        <a href="HD_analytics.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="help_desk.php"><i class="fas fa-ticket"></i> Manage Tickets</a>
        <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>
    <div class="container">
        <header>
            <h1>Help Desk System</h1>
            <button id="createTicketBtn">Create New Ticket</button>
        </header>

        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

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
                    <td colspan="9" style="text-align: center;">No tickets found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td>
                            <?php
                            // Shortened description logic - 30 characters with ellipsis
                            $description = htmlspecialchars($ticket['Description']);
                            $maxLength = 25;
                            echo strlen($description) > $maxLength
                                ? substr($description, 0, $maxLength) . '...'
                                : $description;
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
                                    <a class="dropdown-item print-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                        <i class="fas fa-print me-2"></i> Print
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
                                    <a class="dropdown-item delete-ticket" data-id="<?php echo $ticket['TicketID']; ?>">
                                        <i class="fas fa-trash-alt me-2"></i> Delete
                                    </a>
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
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Assigned To:</label>
                    <select id="assigned_to" name="assigned_to">
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
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
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
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
<script src="notification.js"></script>
<script>
    // Get all dropdown toggle buttons
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    // Add click event listener to each toggle button
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();

            // Close all other open dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== this.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });

            // Toggle the dropdown menu
            this.nextElementSibling.classList.toggle('show');
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // View ticket action
    document.querySelectorAll('.view-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            window.location.href = `help_desk.php?view_ticket=${ticketId}`;
        });
    });

    // Print ticket action
    document.querySelectorAll('.print-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            window.location.href = `help_desk.php?view_ticket=${ticketId}&action=print`;
        });
    });

    // Assign ticket action
    document.querySelectorAll('.edit-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            // Fetch the assign modal via AJAX
            fetch(`ticket_ajax.php?action=get_assign_modal&ticket_id=${ticketId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Append the modal to the document
                        const modalContainer = document.createElement('div');
                        modalContainer.innerHTML = data.html;
                        document.body.appendChild(modalContainer);

                        // Show the modal
                        document.getElementById('assignTicketModal').style.display = 'block';

                        // Add event listener to close button
                        document.querySelector('#assignTicketModal .close').addEventListener('click', function() {
                            document.getElementById('assignTicketModal').style.display = 'none';
                            modalContainer.remove();
                        });

                        // Add form submission listener
                        const assignForm = document.querySelector('#assignTicketModal form');
                        if (assignForm) {
                            assignForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                const formData = new FormData(this);

                                fetch('ticket_ajax.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            document.getElementById('assignTicketModal').style.display = 'none';
                                            modalContainer.remove();
                                            window.location.reload();
                                        } else {
                                            alert(data.message || 'Error assigning ticket');
                                        }
                                    });
                            });
                        }
                    } else {
                        alert(data.message || 'Error fetching assign modal');
                    }
                });
        });
    });


    // Resolve ticket action
    document.querySelectorAll('.resolve-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            // Fetch the resolve modal via AJAX
            fetch(`ticket_ajax.php?action=get_resolve_modal&ticket_id=${ticketId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Append the modal to the document
                        const modalContainer = document.createElement('div');
                        modalContainer.innerHTML = data.html;
                        document.body.appendChild(modalContainer);

                        // Show the modal
                        document.getElementById('resolveTicketModal').style.display = 'block';

                        // Add event listener to close button
                        document.querySelector('#resolveTicketModal .close').addEventListener('click', function() {
                            document.getElementById('resolveTicketModal').style.display = 'none';
                            modalContainer.remove();
                        });

                        // Add form submission listener
                        const resolveForm = document.querySelector('#resolveTicketModal form');
                        if (resolveForm) {
                            resolveForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                const formData = new FormData(this);

                                fetch('ticket_ajax.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            document.getElementById('resolveTicketModal').style.display = 'none';
                                            modalContainer.remove();
                                            window.location.reload();
                                        } else {
                                            alert(data.message || 'Error resolving ticket');
                                        }
                                    });
                            });
                        }
                    } else {
                        alert(data.message || 'Error fetching resolve modal');
                    }
                });
        });
    });

    // Close ticket action
    document.querySelectorAll('.close-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            if (confirm(`Are you sure you want to close ticket #${ticketId}?`)) {
                // Send AJAX request to close the ticket
                const formData = new FormData();
                formData.append('action', 'close_ticket');
                formData.append('ticket_id', ticketId);

                fetch('ticket_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Refresh the page to see updated ticket status
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error closing ticket');
                        }
                    });
            }
        });
    });
    // Delete ticket action (placeholder - implement as needed)
    document.querySelectorAll('.delete-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            if (confirm(`Are you sure you want to delete ticket #${ticketId}?`)) {
                // Implement delete functionality
                alert(`Delete ticket ${ticketId} - Implement this functionality`);
            }
        });
    });

    // Original script functionality
    document.getElementById('createTicketBtn').addEventListener('click', function() {
        document.getElementById('createTicketModal').style.display = 'block';
    });

    document.querySelectorAll('.close').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
</script>
</body>
</html>