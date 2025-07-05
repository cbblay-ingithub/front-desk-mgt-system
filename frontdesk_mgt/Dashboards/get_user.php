<?php
global $conn;
require_once '../dbConfig.php';
if (!isset($_GET['id'])) {
    die("Error: No user ID provided.");
}
$userId = intval($_GET['id']);

// Use prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("User not found");

$roles = $conn->query("SELECT DISTINCT Role FROM users")->fetch_all(MYSQLI_ASSOC);
?>

<div class="modal-header">
    <h5 class="modal-title">Edit User: <?= htmlspecialchars($user['Name']) ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form id="editUserForm" method="POST">
    <input type="hidden" name="id" value="<?= $user['UserID'] ?>">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($user['Name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($user['Email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= htmlspecialchars($user['Phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['Role'] ?>"
                            <?= $user['Role'] === $role['Role'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['Role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>