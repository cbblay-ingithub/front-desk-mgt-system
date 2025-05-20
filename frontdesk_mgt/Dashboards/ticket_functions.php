<?php
// Core functions for the Help Desk System

// Get users for dropdown
function getUsers($conn) {
    $sql = "SELECT UserID, Name FROM users";
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[$row['UserID']] = $row['Name'];
    }
    return $users;
}

// Fetch logged-in user's name by UserID
function getLoggedInUserName($conn, $userId) {
    if (!$conn) {
        error_log("getLoggedInUserName: Database connection is null");
        return "Database Error";
    }
    $sql = "SELECT Name FROM users WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("getLoggedInUserName: Prepare failed: " . $conn->error);
        return "Database Error";
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['Name'];
    }
    error_log("getLoggedInUserName: No user found for UserID: $userId");
    $stmt->close();
    return "User Not Found";
}
// Get categories for dropdown
function getCategories($conn) {
    $categories = [];
    $sql = "SELECT CategoryID, CategoryName FROM TicketCategories WHERE IsActive = TRUE ORDER BY CategoryName";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[$row["CategoryID"]] = $row["CategoryName"];
        }
    }
    return $categories;
}

// Get tickets for the table
function getTickets($conn) {
    $sql = "SELECT t.TicketID, t.Description, t.Priority, t.Status, t.CreatedDate,
            u1.Name as CreatedByName,
            u2.Name as AssignedToName,
            c.CategoryName
            FROM Help_Desk t
            LEFT JOIN users u1 ON t.CreatedBy = u1.UserID
            LEFT JOIN users u2 ON t.AssignedTo = u2.UserID
            LEFT JOIN TicketCategories c ON t.CategoryID = c.CategoryID
            ORDER BY 
                CASE t.Priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                CASE t.Status
                    WHEN 'open' THEN 1
                    WHEN 'in-progress' THEN 2
                    WHEN 'pending' THEN 3
                    WHEN 'resolved' THEN 4
                    WHEN 'closed' THEN 5
                END,
                t.CreatedDate DESC";

    $result = $conn->query($sql);
    $tickets = [];

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
    return $tickets;
}

// Get ticket details for viewing/printing
function getTicketDetails($conn, $ticketID) {
    $sql = "SELECT t.*, 
            u1.Name as CreatedByName,
            u2.Name as AssignedToName,
            c.CategoryName,
            IF(t.Status = 'resolved', TIMESTAMPDIFF(MINUTE, t.CreatedDate, t.ResolvedDate), NULL) as TimeSpent
            FROM Help_Desk t
            LEFT JOIN Users u1 ON t.CreatedBy = u1.UserID
            LEFT JOIN Users u2 ON t.AssignedTo = u2.UserID
            LEFT JOIN TicketCategories c ON t.CategoryID = c.CategoryID
            WHERE t.TicketID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}
?>