<?php
require_once '../dbConfig.php';
require_once 'emails.php';
require_once 'mailTemplates.php';
global $conn;
session_start();

// Process password reset requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $userId = intval($_POST['user_id']);
        $action = 'force_reset';

        // Always generate a temporary password (regardless of the selected option)
        $tempPassword = bin2hex(random_bytes(8)); // Generate a temporary password
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET Password = ?, password_reset_token = NULL, token_expiration = NULL WHERE UserID = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            // Get user email
            $stmt = $conn->prepare("SELECT Email, Name FROM users WHERE UserID = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            // Send temporary password email
            $emailSent = sendTemporaryPasswordEmail($user['Email'], $user['Name'], $tempPassword);

            if ($emailSent) {
                $successMsg = "Password reset successfully. Temporary password sent to " . $user['Email'];
                // For testing, show the temporary password
                $successMsg .= "<br><small>Test temporary password: $tempPassword</small>";
            } else {
                $successMsg = "Password reset successfully. Temporary password: $tempPassword";
            }
        } else {
            $errorMsg = "Error resetting password.";
        }
    }
}

// Get all users
$users = $conn->query("SELECT UserID, Name, Email, Role, status FROM users ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Password Reset Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse your existing sidebar styles */
        /* Add any additional styles needed for this page */
        .password-strength {
            height: 5px;
            width: 100%;
            margin-top: 5px;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }

        /* Sidebar width fixes */
        #layout-menu {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
            flex: 0 0 260px !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1000 !important;
        }
        #layout-navbar {
            position: sticky;
            top: 0;
            z-index: 999; /* Ensure it stays above other content */
            background-color: var(--bs-body-bg); /* Match your theme background */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Optional: adds subtle shadow */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        /* Add these to your existing styles */
        .layout-menu-fixed:not(.layout-menu-collapsed) .layout-menu {
            width: 260px !important;
        }

        .layout-menu-fixed.layout-menu-collapsed .layout-menu {
            width: 78px !important;
        }

        .layout-menu-fixed .layout-menu {
            position: fixed;
            height: 100%;
        }

        .layout-menu-fixed .layout-page {
            margin-left: 260px;
        }

        .layout-menu-fixed.layout-menu-collapsed .layout-page {
            margin-left: 78px;
        }
        .layout-menu-toggle {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 6px !important;
            padding: 8px !important;
            color: #fff !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
        }

        .layout-menu-toggle i {
            font-size: 16px !important;
            line-height: 1 !important;
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
            z-index: 1002 !important;
        }

        .layout-menu-collapsed #layout-menu .layout-menu-toggle {
            animation: pulse-glow 2s infinite !important;
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 5px rgba(255, 255, 255, 0.3); }
            50% { box-shadow: 0 0 15px rgba(255, 255, 255, 0.5), 0 0 25px rgba(255, 255, 255, 0.3); }
            100% { box-shadow: 0 0 5px rgba(255, 255, 255, 0.3); }
        }

        .layout-menu-collapsed #layout-menu {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
            flex: 0 0 78px !important;
        }

        .layout-content {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

        .layout-wrapper {
            overflow: hidden !important;
            height: 100vh !important;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

        html, body {
            overflow-x: hidden !important;
            overflow-y: hidden !important;
            height: 100vh !important;
        }

        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .layout-content {
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'admin-sidebar.php'; ?>
        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="fas fa-bars"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2">Password Reset Settings</h4>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content area -->
            <div class="container-fluid container-p-y">
                <?php if (isset($successMsg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $successMsg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMsg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $errorMsg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <form method="POST" id="passwordResetForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Select User</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['UserID'] ?>">
                                            <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['Email']) ?>) - <?= $user['Role'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="reset_password" class="btn btn-primary">Reset Password & Send Temporary Password</button>
                    </form>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Password Policy Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordPolicyForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Minimum Password Length</label>
                                    <input type="number" name="min_length" class="form-control" value="8" min="6" max="20">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password Expiration (days)</label>
                                    <input type="number" name="expiration_days" class="form-control" value="90" min="30" max="365">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Required Character Types</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_uppercase" id="requireUppercase" checked>
                                        <label class="form-check-label" for="requireUppercase">Uppercase letters</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_lowercase" id="requireLowercase" checked>
                                        <label class="form-check-label" for="requireLowercase">Lowercase letters</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_numbers" id="requireNumbers" checked>
                                        <label class="form-check-label" for="requireNumbers">Numbers</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_special" id="requireSpecial">
                                        <label class="form-check-label" for="requireSpecial">Special characters</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="save_policy" class="btn btn-primary">Save Policy</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Sidebar toggle functionality
        $('.layout-menu-toggle').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $html = $('html');
            const isCollapsed = $html.hasClass('layout-menu-collapsed');

            // Toggle the collapsed state
            $html.toggleClass('layout-menu-collapsed');

            // Update the menu and content widths
            if ($html.hasClass('layout-menu-collapsed')) {
                $('#layout-menu').css({
                    'width': '78px',
                    'min-width': '78px',
                    'max-width': '78px'
                });
                $('.layout-content').css({
                    'margin-left': '78px',
                    'width': 'calc(100% - 78px)'
                });
            } else {
                $('#layout-menu').css({
                    'width': '260px',
                    'min-width': '260px',
                    'max-width': '260px'
                });
                $('.layout-content').css({
                    'margin-left': '260px',
                    'width': 'calc(100% - 260px)'
                });
            }

            // Store the state in localStorage
            localStorage.setItem('layoutMenuCollapsed', $html.hasClass('layout-menu-collapsed'));
        });

        // Initialize sidebar state from localStorage
        const isCollapsed = localStorage.getItem('layoutMenuCollapsed') === 'true';
        if (isCollapsed) {
            $('html').addClass('layout-menu-collapsed');
            $('#layout-menu').css({
                'width': '78px',
                'min-width': '78px',
                'max-width': '78px'
            });
            $('.layout-content').css({
                'margin-left': '78px',
                'width': 'calc(100% - 78px)'
            });
        }

        // Confirm before resetting password
        $('#passwordResetForm').on('submit', function(e) {
            const userId = $('select[name="user_id"]').val();

            if (!userId) {
                e.preventDefault();
                alert('Please select a user.');
                return;
            }

            const userName = $('select[name="user_id"] option:selected').text();
            const message = `Are you sure you want to reset the password for ${userName}? A temporary password will be generated and sent to their email.`;

            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>