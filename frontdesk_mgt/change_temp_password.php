<?php
global $conn;
session_start();
require_once 'dbConfig.php';
require_once 'audit_logger.php';

// Check if user is coming from temporary password login
if (!isset($_SESSION['temp_password_user'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['temp_password_time'])) {
    $timeout = 15 * 60; // 15 minutes
    if (time() - $_SESSION['temp_password_time'] > $timeout) {
        unset($_SESSION['temp_password_user']);
        unset($_SESSION['temp_password_email']);
        unset($_SESSION['temp_password_time']);
        header("Location: Auth.html?error=session_expired");
        exit;
    }
} else {
    $_SESSION['temp_password_time'] = time();
}

$auditLogger = new AuditLogger($conn);
$userId = $_SESSION['temp_password_user'];
$email = $_SESSION['temp_password_email'];

// Get password policy
$policyStmt = $conn->prepare("SELECT * FROM password_policy ORDER BY id LIMIT 1");
$policyStmt->execute();
$policyResult = $policyStmt->get_result();
$passwordPolicy = $policyResult->fetch_assoc();

if (!$passwordPolicy) {
    // Default policy if none exists
    $passwordPolicy = [
        'min_length' => 8,
        'require_uppercase' => 1,
        'require_lowercase' => 1,
        'require_numbers' => 1,
        'require_special_chars' => 0
    ];
}

$errorMsg = '';
$successMsg = '';

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Get user's current password
    $stmt = $conn->prepare("SELECT Password, Name, Role FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify current password
    if (!password_verify($currentPassword, $user['Password'])) {
        $errorMsg = "Current password is incorrect.";
        $auditLogger->logPasswordChange($userId, false, "Incorrect current temporary password");
    }
    // Check if new password matches confirmation
    elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "New password and confirmation do not match.";
        $auditLogger->logPasswordChange($userId, false, "Password confirmation mismatch");
    }
    // Validate password complexity
    else {
        $complexityErrors = validatePasswordComplexity($newPassword, $passwordPolicy);

        if (!empty($complexityErrors)) {
            $errorMsg = "Password does not meet complexity requirements:<br>" . implode("<br>", $complexityErrors);
            $auditLogger->logPasswordChange($userId, false, "Password complexity failure: " . implode(", ", $complexityErrors));
        } else {
            // Hash new password and update user record
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateStmt = $conn->prepare("UPDATE users SET Password = ?, temp_password_expiry = NULL WHERE UserID = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);

            if ($updateStmt->execute()) {
                $successMsg = "Password changed successfully. Please log in with your new password.";
                $auditLogger->logPasswordChange($userId, true, "Temporary password changed successfully");

                // Clear session and redirect to login
                unset($_SESSION['temp_password_user']);
                unset($_SESSION['temp_password_email']);

                // Alternatively, automatically log them in
                $_SESSION['userID'] = $userId;
                $_SESSION['name'] = $user['Name'] ?? '';
                $_SESSION['role'] = $user['Role'] ?? '';

                // Update user login tracking
                $loginUpdateStmt = $conn->prepare("UPDATE users SET                      
                    last_login = NOW(), 
                    last_activity = NOW(), 
                    login_count = login_count + 1                      
                    WHERE UserID = ?");
                $loginUpdateStmt->bind_param("i", $userId);
                $loginUpdateStmt->execute();
                $loginUpdateStmt->close();

                // Redirect to dashboard based on role - FIXED PATH
                header("Location: " . getDashboardForRole($user['Role'] ?? ''));
                exit;
            } else {
                $errorMsg = "Error updating password. Please try again.";
                $auditLogger->logPasswordChange($userId, false, "Database error during password update");
            }
        }
    }
}

function validatePasswordComplexity($password, $policy) {
    $errors = [];

    // Check minimum length
    if (strlen($password) < $policy['min_length']) {
        $errors[] = "Minimum length of " . $policy['min_length'] . " characters required";
    }

    // Check uppercase requirement
    if ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "At least one uppercase letter required";
    }

    // Check lowercase requirement
    if ($policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
        $errors[] = "At least one lowercase letter required";
    }

    // Check number requirement
    if ($policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = "At least one number required";
    }

    // Check special character requirement
    if ($policy['require_special_chars'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "At least one special character required";
    }

    return $errors;
}

function getDashboardForRole($role): string
{
    // FIXED: Return full correct paths
    switch ($role) {
        case 'Admin': header("Location: Dashboards/admin-dashboard.php"); break;
        case 'Host': header("Location: Dashboards/host_analytics.php"); break;
        case 'Front Desk Staff': header("Location: Dashboards/frontdesk_dashboard.php"); break;
        case 'Support Staff': header("Location: Dashboards/HD_analytics.php"); break;
        default: header("Location: Dashboards/401-page.html"); break;
    };
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Temporary Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background-color: #e9ecef;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
        .requirement {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.unmet {
            color: #6c757d;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        .password-input-group {
            position: relative;
        }
    </style>
</head>
<body>
<div class="password-container">
    <div class="text-center mb-4">
        <h2>Change Temporary Password</h2>
        <p class="text-muted">Please set a new permanent password</p>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $errorMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $successMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="passwordChangeForm">
        <div class="mb-3">
            <label for="current_password" class="form-label">Current Temporary Password</label>
            <div class="password-input-group">
                <input type="password" class="form-control" id="current_password" name="current_password" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password', 'current_toggle_icon')">
                    <i class="fas fa-eye" id="current_toggle_icon"></i>
                </button>
            </div>
        </div>

        <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <div class="password-input-group">
                <input type="password" class="form-control" id="new_password" name="new_password" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password', 'new_toggle_icon')">
                    <i class="fas fa-eye" id="new_toggle_icon"></i>
                </button>
            </div>
            <div class="password-strength" id="passwordStrength"></div>
            <small class="text-muted">Password requirements:</small>
            <div class="requirement unmet" id="reqLength">Minimum <?= $passwordPolicy['min_length'] ?> characters</div>
            <?php if ($passwordPolicy['require_uppercase']): ?>
                <div class="requirement unmet" id="reqUppercase">At least one uppercase letter</div>
            <?php endif; ?>
            <?php if ($passwordPolicy['require_lowercase']): ?>
                <div class="requirement unmet" id="reqLowercase">At least one lowercase letter</div>
            <?php endif; ?>
            <?php if ($passwordPolicy['require_numbers']): ?>
                <div class="requirement unmet" id="reqNumber">At least one number</div>
            <?php endif; ?>
            <?php if ($passwordPolicy['require_special_chars']): ?>
                <div class="requirement unmet" id="reqSpecial">At least one special character</div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="password-input-group">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', 'confirm_toggle_icon')">
                    <i class="fas fa-eye" id="confirm_toggle_icon"></i>
                </button>
            </div>
            <div class="text-danger" id="confirmError" style="display: none;">Passwords do not match</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">Change Password</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePasswordVisibility(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    $(document).ready(function() {
        $('#new_password').on('input', function() {
            validatePassword($(this).val());
        });

        $('#confirm_password').on('input', function() {
            validateConfirmation();
        });

        function validatePassword(password) {
            // Length requirement
            const minLength = <?= $passwordPolicy['min_length'] ?>;
            const hasLength = password.length >= minLength;
            $('#reqLength').toggleClass('met', hasLength).toggleClass('unmet', !hasLength);

            // Uppercase requirement
            const hasUppercase = /[A-Z]/.test(password);
            $('#reqUppercase').toggleClass('met', hasUppercase).toggleClass('unmet', !hasUppercase);

            // Lowercase requirement
            const hasLowercase = /[a-z]/.test(password);
            $('#reqLowercase').toggleClass('met', hasLowercase).toggleClass('unmet', !hasLowercase);

            // Number requirement
            const hasNumber = /[0-9]/.test(password);
            $('#reqNumber').toggleClass('met', hasNumber).toggleClass('unmet', !hasNumber);

            // Special character requirement
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            $('#reqSpecial').toggleClass('met', hasSpecial).toggleClass('unmet', !hasSpecial);

            // Update strength meter
            updateStrengthMeter(password);
        }

        function validateConfirmation() {
            const password = $('#new_password').val();
            const confirm = $('#confirm_password').val();
            const hasError = confirm !== '' && password !== confirm;

            $('#confirmError').toggle(hasError);
            $('#confirm_password').toggleClass('is-invalid', hasError);
        }

        function updateStrengthMeter(password) {
            let strength = 0;

            // Length contributes to strength
            if (password.length >= <?= $passwordPolicy['min_length'] ?>) strength += 25;

            // Character variety contributes to strength
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;

            // Cap at 100
            strength = Math.min(strength, 100);

            // Update visual indicator
            const $meter = $('#passwordStrength');
            $meter.css('width', strength + '%');

            if (strength < 40) {
                $meter.removeClass('strength-medium strength-strong').addClass('strength-weak');
            } else if (strength < 80) {
                $meter.removeClass('strength-weak strength-strong').addClass('strength-medium');
            } else {
                $meter.removeClass('strength-weak strength-medium').addClass('strength-strong');
            }
        }

        $('#passwordChangeForm').on('submit', function(e) {
            const password = $('#new_password').val();
            const confirm = $('#confirm_password').val();

            if (password !== confirm) {
                e.preventDefault();
                $('#confirmError').show();
                $('#confirm_password').addClass('is-invalid');
            }
        });
    });
</script>
</body>
</html>