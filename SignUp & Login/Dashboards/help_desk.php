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
    </head>
<body>
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
                <th>ID</th>
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
                        <td><?php echo $ticket['TicketID']; ?></td>
                        <td><?php echo htmlspecialchars(substr($ticket['Description'], 0, 50)) . (strlen($ticket['Description']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($ticket['CreatedByName']); ?></td>
                        <td><?php echo $ticket['AssignedToName'] ? htmlspecialchars($ticket['AssignedToName']) : 'Not assigned'; ?></td>
                        <td><?php echo $ticket['CategoryName'] ? htmlspecialchars($ticket['CategoryName']) : 'Uncategorized'; ?></td>
                        <td><span class="priority-<?php echo $ticket['Priority']; ?>"><?php echo ucfirst($ticket['Priority']); ?></span></td>
                        <td><span class="status-<?php echo $ticket['Status']; ?>"><?php echo ucfirst($ticket['Status']); ?></span></td>
                        <td><?php echo date('M d, Y H:i', strtotime($ticket['CreatedDate'])); ?></td>
                        <td>
                            <button class="action-btn view-btn" data-id="<?php echo $ticket['TicketID']; ?>">View</button>
                            <button class="action-btn print-btn" data-id="<?php echo $ticket['TicketID']; ?>">Print</i></button>
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

    <script src="help_desk_script.js"></script>
</body>
</html>