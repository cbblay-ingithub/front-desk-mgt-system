<?php
global $conn;
session_start();
require_once '../SignUp & Login/dbConfig.php';

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

        // Insert into visitors table
        $stmt = $conn->prepare("INSERT INTO visitors (Name, Email, Phone, IDType, IDNumber, Status, Visit_Purpose) VALUES (?, ?, ?, ?, ?, 'Checked In', ?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $idType, $idNumber, $visit_purpose);
        $stmt->execute();
        $visitorID = $stmt->insert_id;

        // Insert into Visitor_Logs table
        $logStmt = $conn->prepare("INSERT INTO visitor_Logs (CheckInTime, HostID, VisitorID, Visit_Purpose) VALUES (NOW(), ?, ?, ?)");
        $logStmt->bind_param("iis", $hostID, $visitorID, $visit_purpose);
        $logStmt->execute();

        header('Location:visitor-mgt.php?msg=checked-in');
        exit;
    } elseif ($action === 'check_out') {
        $visitorID = $_POST['visitor_id'];

        // Update visitor status
        $stmt = $conn->prepare("UPDATE visitors SET Status = 'Checked Out' WHERE VisitorID = ?");
        $stmt->bind_param("i", $visitorID);
        $stmt->execute();

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
