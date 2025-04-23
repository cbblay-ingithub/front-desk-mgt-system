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

        $sql = "INSERT INTO Help_Desk (CreatedBy, Description, AssignedTo, CategoryID, Priority, CreatedDate) 
                VALUES (?, ?, " . ($assignedTo == "NULL" ? "NULL" : "?") . ", " . ($categoryID == "NULL" ? "NULL" : "?") . ", ?, NOW())";

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

        $types .= "s";
        $params[] = $priority;

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
?>