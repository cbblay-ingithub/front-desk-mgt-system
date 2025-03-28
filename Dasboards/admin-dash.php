<?php
require_once '../SignUp & Login/dbConfig.php'; // includes DB connection

$page = isset($_GET['page']) ? $_GET['page'] : 'user_management';

switch ($page) {
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
$conn->close();

include 'admin-dashboard.html';

