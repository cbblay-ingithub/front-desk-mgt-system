<?php
// Ticket operations for the Help Desk System

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

        if ($stmt->execute()) {
            $message = "Ticket created successfully!";
        } else {
            $error = "Error creating ticket: " . $conn->error;
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
        } else {
            $error = "Error assigning ticket: " . $conn->error;
        }
        $stmt->close();
    }

    // Process ticket resolution
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'resolve_ticket') {
        $ticketId = $_POST['ticket_id'];
        $resolution = $_POST['resolution'];

        // Update ticket status to "resolved"
        $sql = "UPDATE Help_Desk SET Status = 'resolved', ResolutionNotes = ?, ResolvedDate = NOW() WHERE TicketID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $resolution, $ticketId);

        if ($stmt->execute()) {
            $message = "Ticket #$ticketId has been resolved!";
        } else {
            $error = "Error resolving ticket: " . $conn->error;
        }
        $stmt->close();
    }

    // Process ticket closure
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'close_ticket') {
        $ticketId = $_POST['ticket_id'];

        // Update ticket status to "closed"
        $sql = "UPDATE Help_Desk SET Status = 'closed', LastUpdated = NOW() WHERE TicketID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);

        if ($stmt->execute()) {
            $message = "Ticket #$ticketId has been closed!";
        } else {
            $error = "Error closing ticket: " . $conn->error;
        }
        $stmt->close();
    }

    return ['message' => $message, 'error' => $error];
}

// Check for tickets older than 7 days that need to be auto-closed
function autoCloseOldTickets($conn) {
    // Find tickets that are not closed and older than 7 days
    $sql = "UPDATE Help_Desk 
            SET Status = 'closed', 
                LastUpdated = NOW(), 
                ResolutionNotes = CONCAT(IFNULL(ResolutionNotes, ''), ' [Auto-closed after 7 days of inactivity]')
            WHERE Status != 'closed' 
            AND DATEDIFF(NOW(), IFNULL(LastUpdated, CreatedDate)) > 7";

    if ($conn->query($sql)) {
        $rowsAffected = $conn->affected_rows;
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
            <!-- Remove the action attribute completely -->
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
?>