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
if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (userID, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
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
<html
        lang="en"
        class="layout-navbar-fixed layout-menu-fixed layout-compact layout-menu-collapsed"
        dir="ltr"
        data-skin="default"
        data-assets-path="../../assets/"
        data-template="vertical-menu-template"
        data-bs-theme="light"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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

                        <!-- Notification bell and other navbar items can go here -->
                        <div class="notification-wrapper">
                            <div class="notification-bell" id="notificationBell">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count" id="notificationCount">0</span>
                            </div>
                            <div class="notification-panel" id="notificationPanel">
                                <!-- Notification content -->
                            </div>
                        </div>
                    </div>
                </nav>
                    <div class="content-wrapper">
                        <div class="container-xxl flex-grow-1 container-p-y">

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

    search.addEventListener('input', filterTickets);
    statusFilter.addEventListener('change', filterTickets);
    priorityFilter.addEventListener('change', filterTickets);
    // Get all dropdown toggle buttons
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    // Add click event listener to each toggle button
    // Add event listeners to search and filters
    document.getElementById('search').addEventListener('input', filterTickets);
    document.getElementById('status-filter').addEventListener('change', filterTickets);
    document.getElementById('priority-filter').addEventListener('change', filterTickets);

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            console.log('Closing all dropdowns');
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // View ticket action
    document.querySelectorAll('.view-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            console.log('Viewing ticket ID:', ticketId);
            window.location.href = `help_desk.php?view_ticket=${ticketId}`;
        });
    });

    // Print ticket action
    document.querySelectorAll('.print-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            console.log('Printing ticket ID:', ticketId);
            window.location.href = `help_desk.php?view_ticket=${ticketId}&action=print`;
        });
    });

    // Assign ticket action
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
        });
    });

    // Resolve ticket action
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
        });
    });

    // Close ticket action
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
        });
    });

    // Delete ticket action
    document.querySelectorAll('.delete-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            console.log('Attempting to delete ticket ID:', ticketId);
            if (confirm(`Are you sure you want to delete ticket #${ticketId}?`)) {
                alert(`Delete ticket ${ticketId} - Implement this functionality`);
            }
        });
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
    $(document).ready(function() {
        // Keep session alive and update activity
        setInterval(() => {
            fetch('update_activity.php')
                .then(response => response.json())
                .then(data => {
                    if(!data.success) console.error('Activity update failed');
                });
        }, 60000);
    });
</script>
</html>