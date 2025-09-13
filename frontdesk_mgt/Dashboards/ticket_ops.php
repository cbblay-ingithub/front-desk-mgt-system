<?php
// Ticket operations for the Help Desk System

// Process ticket creation form submission
function createTicket($conn) {
    $message = null;
    $error = null;

    // Debug: Log incoming POST data
    error_log("Checking for ticket creation POST data: " . print_r($_POST, true));

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_ticket') {
        error_log("Session data: " . print_r($_SESSION, true));

        $createdBy = $_SESSION['userID'] ?? null;
        error_log("Created by user ID: " . $createdBy);

        if (!$createdBy) {
            error_log("No user ID in session");
            return ['error' => 'You must be logged in to create a ticket'];
        }

        $description = trim($_POST['description']);
        $priority = $_POST['priority'];
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $categoryID = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        // Set status based on assignment
        $status = $assignedTo ? 'in-progress' : 'open';

        try {
            // Prepare the SQL statement
            $sql = "INSERT INTO Help_Desk (CreatedBy, Description, AssignedTo, CategoryID, Priority, Status, CreatedDate) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind parameters
            $stmt->bind_param("isiiss",
                $createdBy,
                $description,
                $assignedTo,
                $categoryID,
                $priority,
                $status
            );

            // Execute the statement
            if ($stmt->execute()) {
                $newTicketId = $conn->insert_id;
                $message = "Ticket #$newTicketId created successfully!";

                // Return success response without notification
                return ['success' => true, 'message' => $message];
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error creating ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error creating ticket: ' . $e->getMessage()
            ];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
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

        // Update ticket status to "resolved", set ResolvedDate, and calculate Time Spent
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

// Check for tickets older than 14 days that need to be auto-closed
// Modify autoCloseOldTickets function
function autoCloseOldTickets($conn) {
    // First, identify tickets that will be auto-closed
    $findSql = "SELECT TicketID, CreatedBy, AssignedTo FROM Help_Desk 
                WHERE Status != 'closed' 
                AND DATEDIFF(NOW(), IFNULL(LastUpdated, CreatedDate)) > 30";
    $result = $conn->query($findSql);

    $ticketsToClose = [];
    while ($row = $result->fetch_assoc()) {
        $ticketsToClose[] = $row;
    }

    // Now update the tickets
    $updateSql = "UPDATE Help_Desk 
                  SET Status = 'closed', 
                      LastUpdated = NOW(), 
                      ResolutionNotes = CONCAT(IFNULL(ResolutionNotes, ''), ' [Auto-closed after 30 days of inactivity]'),
                      TimeSpent = IF(TimeSpent IS NULL, TIMESTAMPDIFF(MINUTE, CreatedDate, NOW()), TimeSpent)
                  WHERE Status != 'closed' 
                  AND DATEDIFF(NOW(), IFNULL(LastUpdated, CreatedDate)) > 7";

    if ($conn->query($updateSql)) {
        $rowsAffected = $conn->affected_rows;

        // Send notifications for auto-closed tickets
        foreach ($ticketsToClose as $ticket) {
            createNotification($conn, $ticket['CreatedBy'], $ticket['TicketID'], 'auto_closure', [
                'reason' => 'Inactivity for more than 14 days'
            ]);
            if ($ticket['AssignedTo']) {
                createNotification($conn, $ticket['AssignedTo'], $ticket['TicketID'], 'auto_closure', [
                    'reason' => 'Inactivity for more than 14 days'
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
function generateResolveTicketModalHTML($ticketId): string
{
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
    error_log("reopenTicket called with ticketId: $ticketId, userId: $userId");

    // Check if the ticket exists and is closed
    $sql = "SELECT Status, CreatedBy, AssignedTo FROM Help_Desk WHERE TicketID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return ['success' => false, 'error' => 'Database error'];
    }

    $stmt->bind_param("i", $ticketId);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return ['success' => false, 'error' => 'Database error'];
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($row = $result->fetch_assoc()) {
        if ($row['Status'] != 'closed') {
            error_log("Ticket $ticketId is not closed (status: {$row['Status']})");
            return ['success' => false, 'error' => 'Ticket is not closed'];
        }
    } else {
        error_log("Ticket $ticketId not found");
        return ['success' => false, 'error' => 'Ticket not found'];
    }

    // Update ticket status to 'open' and log the reopening
    $sql = "UPDATE Help_Desk 
            SET Status = 'open', 
                LastUpdated = NOW(), 
                ResolutionNotes = CONCAT(IFNULL(ResolutionNotes, ''), ' [Reopened by user #$userId on ', NOW(), ']') 
            WHERE TicketID = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return ['success' => false, 'error' => 'Database error'];
    }

    $stmt->bind_param("i", $ticketId);

    if ($stmt->execute()) {
        error_log("Ticket $ticketId reopened successfully");
        $stmt->close();
        return ['success' => true, 'message' => 'Ticket reopened successfully'];
    } else {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return ['success' => false, 'error' => 'Error reopening ticket: ' . $conn->error];
    }
}
