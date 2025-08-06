<?php
require_once '../dbConfig.php'; // includes DB connection

$page = $_GET['page'] ?? 'user_management';

switch ($page) {
    case 'dashboard':
        include 'admin-dashboard.php';
        break;
    case 'appointments':
        include 'appointments.php';
        break;
    case 'helpdesk':
        include 'helpdesk.php';
        break;
    case 'lost_found':
        include 'lost_found.php';
        break;
    case 'user_management':
    default:
        include 'user_management.php';
        break;
}

global $conn;
$sql = "SELECT UserID, Name, Email, Phone, Role FROM users";
$result = $conn->query($sql);

// Fetch all users as an array to pass into the HTML
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
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
$conn->close();

include 'admin-dashboard.php';

