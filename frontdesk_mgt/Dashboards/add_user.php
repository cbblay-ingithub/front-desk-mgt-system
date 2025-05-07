<?php
require_once '../dbConfig.php';
global $conn;

//Allows for adding new users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role  = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (Name, Email, Phone, Role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $phone, $role);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: admin-dash.php?page=user_management');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
<h2 class="mb-4">Add New User</h2>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Name</label>
        <label>
            <input name="name" class="form-control" required>
        </label>
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
            <option value="Admin">Admin</option>
            <option value="Front Desk Staff">Front Desk Staff</option>
            <option value="Host">Host</option>
            <option value="Support Staff">Support Staff</option>
        </select>
    </div>
    <button class="btn btn-success">Add User</button>
    <a href="admin-dash.php?page=user_management" class="btn btn-secondary">Cancel</a>
</form>
</body>
</html>
