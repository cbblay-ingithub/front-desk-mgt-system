<?php
// Ticket operations for the Help Desk System
require_once 'notif_functions.php'; //includes notification functions

// Process ticket creation form submission
function createTicket($conn) {
    $message = null;
    $error = null;

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_ticket') {
        $createdBy = $_POST['created_by'];
        $description = $_POST['description'];
        $assignedTo = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : "NULL";
        $categoryID = !empty($_POST['category_id']) ? $_POST['category_id'] : "NULL";
        $priority = $_POST['priority'];

        // Set status to 'in-progress' if a user is assigned, otherwise 'open'
        $status = ($assignedTo != "NULL") ? "in-progress" : "open";

        $sql = "INSERT INTO Help_Desk (CreatedBy, Description, AssignedTo, CategoryID, Priority, Status, CreatedDate) 
                VALUES (?, ?, " . ($assignedTo == "NULL" ? "NULL" : "?") . ", " . ($categoryID == "NULL" ? "NULL" : "?") . ", ?, ?, NOW())";

        $stmt = $conn->prepare($sql);

        // Dynamically bind parameters based on NULL values
        $types = "ss";
        $params = [$createdBy, $description];

        if ($assignedTo != "NULL") {
            $types .= "i";
            $params[] = $assignedTo;
        }

        if ($categoryID != "NULL") {
            $types .= "i";
            $params[] = $categoryID;
        }

        $types .= "ss";
        $params[] = $priority;
        $params[] = $status; // Add status parameter

        $stmt->bind_param($types, ...$params);

        // Bind parameters and execute (adjust as per your implementation)
        if ($stmt->execute()) {
            $newTicketId = $conn->insert_id;
            error_log("Ticket created: #$newTicketId at " . date('Y-m-d H:i:s') . " with action: " . $_POST['action'] . " and POST data: " . json_encode($_POST));
            $message = "Ticket created successfully!";
        } else {
            $error = "Error creating ticket.";
        }
        $stmt->close();
    }

    return ['message' => $message, 'error' => $error];
}

// Process ticket operations (assign, resolve, close)
function processTicketOperation($conn) {
    $message = null;
    $error = null;

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return ['message' => null, 'error' => null];
    }

    // Process ticket assignment
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'assign_ticket') {
        $ticketId = $_POST['ticket_id'];
        $assignedTo = $_POST['assigned_to'];

        // Update ticket status to "in-progress" when assigned
        $sql = "UPDATE Help_Desk SET AssignedTo = ?, Status = 'in-progress', LastUpdated = NOW() WHERE TicketID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $assignedTo, $ticketId);

        if ($stmt->execute()) {
            $message = "Ticket #$ticketId has been assigned and marked as in-progress!";

            // Create notification for assigned user
            createNotification($conn, $assignedTo, $ticketId, 'assignment', [
                'assigned_by' => $_SESSION['user_id'],
                'assigned_by_name' => $_SESSION['username'] ?? 'System'
            ]);
        } else {
            $error = "Error assigning ticket: " . $conn->error;
        }
        $stmt->close();
    }

    // Process ticket resolution
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'resolve_ticket') {
        $ticketId = $_POST['ticket_id'];
        $resolution = $_POST['resolution'];

        // Update ticket status to "resolved", set ResolvedDate, and calculate TimeSpent
        // TimeSpent is the difference in minutes between CreatedDate and now (ResolvedDate)
        $sql = "UPDATE Help_Desk 
            SET Status = 'resolved', 
                ResolutionNotes = ?, 
                ResolvedDate = NOW(), 
                TimeSpent = TIMESTAMPDIFF(MINUTE, CreatedDate, NOW()) 
            WHERE TicketID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $resolution, $ticketId);

        if ($stmt->execute()) {
            $message = "Ticket #$ticketId has been resolved!";

            // Get the creator of the ticket
            $creatorQuery = "SELECT CreatedBy FROM Help_Desk WHERE TicketID = ?";
            $creatorStmt = $conn->prepare($creatorQuery);
            $creatorStmt->bind_param("i", $ticketId);
            $creatorStmt->execute();
            $result = $creatorStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Notify the ticket creator
                createNotification($conn, $row['CreatedBy'], $ticketId, 'resolution', [
                    'resolved_by' => $_SESSION['user_id'],
                    'resolved_by_name' => $_SESSION['username'] ?? 'System',
                    'resolution' => $resolution
                ]);
            }
            $creatorStmt->close();
        } else {
            $error = "Error resolving ticket: " . $conn->error;
        }
        $stmt->close();
    }

// Process ticket closure
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'close_ticket') {
        $ticketId = $_POST['ticket_id'];

        // Update ticket status to "closed" and set TimeSpent if not already set
        $sql = "UPDATE Help_Desk 
            SET Status = 'closed', 
                LastUpdated = NOW(), 
                TimeSpent = IF(TimeSpent IS NULL, TIMESTAMPDIFF(MINUTE, CreatedDate, NOW()), TimeSpent)
            WHERE TicketID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);

        if ($stmt->execute()) {
            $message = "Ticket #$ticketId has been closed!";

            // Get relevant users (creator and assignee if any)
            $usersQuery = "SELECT CreatedBy, AssignedTo FROM Help_Desk WHERE TicketID = ?";
            $usersStmt = $conn->prepare($usersQuery);
            $usersStmt->bind_param("i", $ticketId);
            $usersStmt->execute();
            $result = $usersStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['CreatedBy'] != $_SESSION['user_id']) {
                    createNotification($conn, $row['CreatedBy'], $ticketId, 'closure', [
                        'closed_by' => $_SESSION['user_id'],
                        'closed_by_name' => $_SESSION['username'] ?? 'System'
                    ]);
                }
                if ($row['AssignedTo'] && $row['AssignedTo'] != $_SESSION['user_id']) {
                    createNotification($conn, $row['AssignedTo'], $ticketId, 'closure', [
                        'closed_by' => $_SESSION['user_id'],
                        'closed_by_name' => $_SESSION['username'] ?? 'System'
                    ]);
                }
            }
            $usersStmt->close();
        } else {
            $error = "Error closing ticket: " . $conn->error;
        }
        $stmt->close();
    }

    return ['message' => $message, 'error' => $error];
}

// Check for tickets older than 7 days that need to be auto-closed
// Modify autoCloseOldTickets function
function autoCloseOldTickets($conn) {
    // First, identify tickets that will be auto-closed
    $findSql = "SELECT TicketID, CreatedBy, AssignedTo FROM Help_Desk 
                WHERE Status != 'closed' 
                AND DATEDIFF(NOW(), IFNULL(LastUpdated, CreatedDate)) > 7";
    $result = $conn->query($findSql);

    $ticketsToClose = [];
    while ($row = $result->fetch_assoc()) {
        $ticketsToClose[] = $row;
    }

    // Now update the tickets
    $updateSql = "UPDATE Help_Desk 
                  SET Status = 'closed', 
                      LastUpdated = NOW(), 
                      ResolutionNotes = CONCAT(IFNULL(ResolutionNotes, ''), ' [Auto-closed after 7 days of inactivity]'),
                      TimeSpent = IF(TimeSpent IS NULL, TIMESTAMPDIFF(MINUTE, CreatedDate, NOW()), TimeSpent)
                  WHERE Status != 'closed' 
                  AND DATEDIFF(NOW(), IFNULL(LastUpdated, CreatedDate)) > 7";

    if ($conn->query($updateSql)) {
        $rowsAffected = $conn->affected_rows;

        // Send notifications for auto-closed tickets
        foreach ($ticketsToClose as $ticket) {
            createNotification($conn, $ticket['CreatedBy'], $ticket['TicketID'], 'auto_closure', [
                'reason' => 'Inactivity for more than 7 days'
            ]);
            if ($ticket['AssignedTo']) {
                createNotification($conn, $ticket['AssignedTo'], $ticket['TicketID'], 'auto_closure', [
                    'reason' => 'Inactivity for more than 7 days'
                ]);
            }
        }

        if ($rowsAffected > 0) {
            return "$rowsAffected ticket(s) were automatically closed due to inactivity.";
        }
    }
    return null;
}

// Function to create the Assign Ticket modal HTML
function generateAssignTicketModalHTML($ticketId, $users) {
    $html = '
    <div id="assignTicketModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Assign Ticket #' . $ticketId . '</h2>
            <form id="assignTicketForm">
                <input type="hidden" name="action" value="assign_ticket">
                <input type="hidden" name="ticket_id" value="' . $ticketId . '">
                
                <div class="form-group">
                    <label for="assigned_to">Assign To:</label>
                    <select id="assigned_to" name="assigned_to" required>
                        <option value="">Select User</option>';

    foreach ($users as $id => $name) {
        $html .= '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }

    $html .= '</select>
                </div>
                
                <button type="submit" class="submit-btn">Assign Ticket</button>
            </form>
        </div>
    </div>';

    return $html;
}
// Function to create the Resolve Ticket modal HTML
function generateResolveTicketModalHTML($ticketId) {
    return '
    <div id="resolveTicketModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Resolve Ticket #' . $ticketId . '</h2>
            <form method="POST" action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '">
                <input type="hidden" name="action" value="resolve_ticket">
                <input type="hidden" name="ticket_id" value="' . $ticketId . '">
                
                <div class="form-group">
                    <label for="resolution">Resolution:</label>
                    <textarea id="resolution" name="resolution" required placeholder="Describe how the issue was resolved..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Mark as Resolved</button>
            </form>
        </div>
    </div>';
}
// Function to reopen a closed ticket
function reopenTicket($conn, $ticketId, $userId) {
    // Check if the ticket exists and is closed
    $sql = "SELECT Status FROM Help_Desk WHERE TicketID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['Status'] != 'closed') {
            $stmt->close();
            return ['success' => false, 'error' => 'Ticket is not closed'];
        }
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Ticket not found'];
    }
    $stmt->close();

    // Update ticket status to 'open' and log the reopening
    $sql = "UPDATE Help_Desk 
            SET Status = 'open', 
                LastUpdated = NOW(), 
                ResolutionNotes = CONCAT(IFNULL(ResolutionNotes, ''), ' [Reopened by user #$userId on ', NOW(), ']') 
            WHERE TicketID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);

    if ($stmt->execute()) {
        // Notify creator and assignee
        $usersQuery = "SELECT CreatedBy, AssignedTo FROM Help_Desk WHERE TicketID = ?";
        $usersStmt = $conn->prepare($usersQuery);
        $usersStmt->bind_param("i", $ticketId);
        $usersStmt->execute();
        $result = $usersStmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Assume getUserName function exists or replace with direct query if needed
            $reopenerName = $_SESSION['username'] ?? "User #$userId";
            createNotification($conn, $row['CreatedBy'], $ticketId, 'reopen', [
                'reopened_by' => $userId,
                'reopened_by_name' => $reopenerName
            ]);
            if ($row['AssignedTo']) {
                createNotification($conn, $row['AssignedTo'], $ticketId, 'reopen', [
                    'reopened_by' => $userId,
                    'reopened_by_name' => $reopenerName
                ]);
            }
        }
        $usersStmt->close();
        $stmt->close();
        return ['success' => true, 'message' => 'Ticket reopened successfully'];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Error reopening ticket: ' . $conn->error];
    }
}
?>