<?php
require_once '../SignUp & Login/dbConfig.php';
global $conn;
$id = isset($_GET['id']) ? $_GET['id'] : 0;
$stmt = $conn->prepare("DELETE FROM users WHERE UserID=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$conn->close();

header('Location:admin-dash.php?page=user_management');
exit;
