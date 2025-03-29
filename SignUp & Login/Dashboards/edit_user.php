<?php
require_once '../dbConfig.php';
global $conn;
$id = isset($_GET['id']) ? $_GET['id'] : 0;

//Updating/Editing already existing data in the users table
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role  = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET Name=?, Email=?, Phone=?, Role=? WHERE UserID=?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $role, $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: admin-dash.php?page=user_management');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE UserID=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
<h2 class="mb-4">Edit User</h2>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="name" value="<?= htmlspecialchars($user['Name']) ?>" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input name="email" type="email" value="<?= htmlspecialchars($user['Email']) ?>" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input name="phone" value="<?= htmlspecialchars($user['Phone']) ?>" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
            <option <?= $user['Role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
            <option <?= $user['Role'] == 'Staff' ? 'selected' : '' ?>>Staff</option>
            <option <?= $user['Role'] == 'User' ? 'selected' : '' ?>>User</option>
        </select>
    </div>
    <button class="btn btn-primary">Update User</button>
    <a href="admin-dash.php?page=user_management" class="btn btn-secondary">Cancel</a>
</form>
</body>
</html>
