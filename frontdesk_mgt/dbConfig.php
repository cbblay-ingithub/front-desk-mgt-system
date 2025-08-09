<?php
// dbConfig.php
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "Skywalker$45";
$dbName = "frontdesk";

// Create database connection
$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection Error: " . $conn->connect_error);
}

