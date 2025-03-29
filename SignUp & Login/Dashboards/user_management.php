<?php
require_once '../dbConfig.php';
global $conn;

//Retrieves data in the users table
$sql = "SELECT UserID, Name, Email, Phone, Role FROM users";
$result = $conn->query($sql);
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            padding-top: 1rem;
        }
        .sidebar a {
            color: #fff;
            display: block;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <h4 class="text-white text-center">Admin Panel</h4>
    <a href="user_management.php?page=user_management">User Management</a>
    <a href="admin_dashboard.php?page=appointments">View Appointments</a>
    <a href="admin_dashboard.php?page=helpdesk">View Help Desk Tickets</a>
    <a href="admin_dashboard.php?page=lost_found">View Lost & Found</a>
</div>

<div class="content">
    <h2 class="mb-4">User Management</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
            <tr>
                <th>UserID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['UserID']) ?></td>
                    <td><?= htmlspecialchars($user['Name']) ?></td>
                    <td><?= htmlspecialchars($user['Email']) ?></td>
                    <td><?= htmlspecialchars($user['Phone']) ?></td>
                    <td><?= htmlspecialchars($user['Role']) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user['UserID'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="delete_user.php?id=<?= $user['UserID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <a href="add_user.php" class="btn btn-success">Add New User</a>
</div>
</body>
</html>
