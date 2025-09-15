<?php
require_once '../dbConfig.php';
require_once 'password_validator.php'; // Add this line
global $conn;

// Allows for adding new users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role  = $_POST['role'];
    $password = $_POST['password']; // Add password field

    // Validate password against policy
    $passwordErrors = validatePassword($password, $conn);

    if (!empty($passwordErrors)) {
        // Store errors in session and redirect back
        session_start();
        $_SESSION['form_errors'] = $passwordErrors;
        $_SESSION['form_data'] = $_POST;
        header('Location: add_user.php');
        exit;
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (Name, Email, Phone, Role, Password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $role, $hashedPassword);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: admin-dash.php?page=user_management');
        exit;
    } else {
        // Handle database error
        session_start();
        $_SESSION['form_errors'] = ['Database error: ' . $stmt->error];
        $_SESSION['form_data'] = $_POST;
        header('Location: add_user.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ADD FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="container py-5">
<h2 class="mb-4">Add New User</h2>

<!-- Display errors if any -->
<?php if (isset($_SESSION['form_errors'])): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($_SESSION['form_errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php unset($_SESSION['form_errors']); ?>
<?php endif; ?>

<form method="post">
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" value="<?= isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : '' ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" value="<?= isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : '' ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control" value="<?= isset($_SESSION['form_data']['phone']) ? htmlspecialchars($_SESSION['form_data']['phone']) : '' ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
            <option value="Admin" <?= (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
            <option value="Front Desk Staff" <?= (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'Front Desk Staff') ? 'selected' : '' ?>>Front Desk Staff</option>
            <option value="Host" <?= (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'Host') ? 'selected' : '' ?>>Host</option>
            <option value="Support Staff" <?= (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'Support Staff') ? 'selected' : '' ?>>Support Staff</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <!-- FIXED: Add input group with toggle button -->
        <div class="input-group">
            <input name="password" type="password" class="form-control" id="passwordField" required>
            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <small class="text-muted">Password must meet the current password policy requirements</small>
    </div>
    <button class="btn btn-success">Add User</button>
    <a href="admin-dash.php?page=user_management" class="btn btn-secondary">Cancel</a>
</form>

<?php unset($_SESSION['form_data']); ?>

<!-- ADD JAVASCRIPT FOR PASSWORD TOGGLE -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Password toggle for add_user.php
        $('#togglePassword').on('click', function() {
            const passwordField = $('#passwordField');
            const icon = $(this).find('i');

            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
                $(this).addClass('active');
            } else {
                passwordField.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
                $(this).removeClass('active');
            }
        });
    });
</script>
</body>
</html>