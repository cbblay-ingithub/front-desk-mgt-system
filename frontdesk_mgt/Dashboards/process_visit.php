<?php
global $conn;
session_start();
require_once '../dbConfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'check_in') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $idType = $_POST['id_type'];
        $idNumber = $_POST['id_number'];
        $visit_purpose = $_POST['visit_purpose'];
        $hostID = $_SESSION['user_id'];

        // Check if visitor already exists by email
        $stmt = $conn->prepare("SELECT VisitorID FROM visitors WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Visitor exists, retrieve their ID and update details
            $visitorID = $result->fetch_assoc()['VisitorID'];
            $updateStmt = $conn->prepare("UPDATE visitors SET Name = ?, Phone = ?, IDType = ?, IDNumber = ?, Visit_Purpose = ? WHERE VisitorID = ?");
            $updateStmt->bind_param("sssssi", $name, $phone, $idType, $idNumber, $visit_purpose, $visitorID);
            $updateStmt->execute();
        } else {
            // Insert new visitor (no Status column needed, see Step 3)
            $insertStmt = $conn->prepare("INSERT INTO visitors (Name, Email, Phone, IDType, IDNumber, Visit_Purpose) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssss", $name, $email, $phone, $idType, $idNumber, $visit_purpose);
            $insertStmt->execute();
            $visitorID = $insertStmt->insert_id;
        }

        // Insert into Visitor_Logs
        $logStmt = $conn->prepare("INSERT INTO visitor_Logs (CheckInTime, HostID, VisitorID, Visit_Purpose) VALUES (NOW(), ?, ?, ?)");
        $logStmt->bind_param("iis", $hostID, $visitorID, $visit_purpose);
        $logStmt->execute();

        header('Location:visitor-mgt.php?msg=checked-in');
        exit;
    } elseif ($action === 'check_out') {
        $visitorID = $_POST['visitor_id'];

        // Update Visitor_Logs with checkout time
        $logUpdate = $conn->prepare("UPDATE visitor_Logs SET CheckOutTime = NOW() WHERE VisitorID = ? AND CheckOutTime IS NULL ORDER BY CheckInTime DESC LIMIT 1");
        $logUpdate->bind_param("i", $visitorID);
        $logUpdate->execute();

        header('Location:visitor-mgt.php?msg=checked-out');
        exit;
    }

    header("Location:visitor-mgt.php");
    exit;
}
