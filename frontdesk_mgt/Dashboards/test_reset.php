<?php
// test_password_reset.php
require_once '../dbConfig.php';
global $conn;
session_start();

// Create test users if they don't exist
function createTestUsers($conn) {
    $testUsers = [
        [
            'Name' => 'Test User 1',
            'Email' => 'test1@example.com',
            'Password' => password_hash('temp123', PASSWORD_DEFAULT),
            'Role' => 'user',
            'status' => 'active'
        ],
        [
            'Name' => 'Test User 2',
            'Email' => 'test2@example.com',
            'Password' => password_hash('temp123', PASSWORD_DEFAULT),
            'Role' => 'host',
            'status' => 'active'
        ]
    ];

    foreach ($testUsers as $user) {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ?");
        $stmt->bind_param("s", $user['Email']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Insert test user
            $stmt = $conn->prepare("INSERT INTO users (Name, Email, Password, Role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $user['Name'], $user['Email'], $user['Password'], $user['Role'], $user['status']);
            $stmt->execute();
        }
    }
}

createTestUsers($conn);

// Process test requests
$testResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_reset'])) {
    $testType = $_POST['test_type'];
    $userId = intval($_POST['test_user_id']);

    // Get user details
    $stmt = $conn->prepare("SELECT Email, Name FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($testType === 'send_link') {
        // Test sending reset link
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE users SET password_reset_token = ?, token_expiration = ? WHERE UserID = ?");
        $stmt->bind_param("ssi", $token, $expiration, $userId);

        if ($stmt->execute()) {
            $resetLink = "https://yourdomain.com/reset_password.php?token=$token";
            $testResults[] = [
                'type' => 'Reset Link',
                'status' => 'success',
                'message' => "Reset link generated for " . $user['Email'],
                'details' => "Token: $token (Expires: $expiration)<br>Reset Link: $resetLink"
            ];
        } else {
            $testResults[] = [
                'type' => 'Reset Link',
                'status' => 'error',
                'message' => "Failed to generate reset token for " . $user['Email']
            ];
        }
    } elseif ($testType === 'force_reset') {
        // Test force reset
        $tempPassword = bin2hex(random_bytes(8));
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET Password = ?, password_reset_token = NULL, token_expiration = NULL WHERE UserID = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            $testResults[] = [
                'type' => 'Force Reset',
                'status' => 'success',
                'message' => "Password reset for " . $user['Email'],
                'details' => "Temporary Password: <strong>$tempPassword</strong> (Hashed in database)"
            ];
        } else {
            $testResults[] = [
                'type' => 'Force Reset',
                'status' => 'error',
                'message' => "Failed to reset password for " . $user['Email']
            ];
        }
    } elseif ($testType === 'verify_reset') {
        // Test token verification
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Set a test token
        $stmt = $conn->prepare("UPDATE users SET password_reset_token = ?, token_expiration = ? WHERE UserID = ?");
        $stmt->bind_param("ssi", $token, $expiration, $userId);
        $stmt->execute();

        // Now verify it
        $stmt = $conn->prepare("SELECT UserID, token_expiration FROM users WHERE password_reset_token = ? AND token_expiration > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $testResults[] = [
                'type' => 'Token Verification',
                'status' => 'success',
                'message' => "Token verification successful for " . $user['Email'],
                'details' => "Token: $token is valid until $expiration"
            ];
        } else {
            $testResults[] = [
                'type' => 'Token Verification',
                'status' => 'error',
                'message' => "Token verification failed for " . $user['Email']
            ];
        }

        // Clean up
        $stmt = $conn->prepare("UPDATE users SET password_reset_token = NULL, token_expiration = NULL WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
}

// Get test users
$testUsers = $conn->query("SELECT UserID, Name, Email, Role FROM users WHERE Email LIKE 'test%@example.com' ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$allUsers = $conn->query("SELECT UserID, Name, Email, Role FROM users ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Test Password Reset Functionality</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .test-result {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .test-success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        .test-error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .test-info {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .pre-wrap {
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Test Password Reset Functionality</h1>

            <?php if (!empty($testResults)): ?>
                <div class="mb-4">
                    <h3>Test Results</h3>
                    <?php foreach ($testResults as $result): ?>
                        <div class="test-result test-<?= $result['status'] ?>">
                            <h5><i class="fas fa-<?= $result['status'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                                <?= $result['type'] ?> Test: <?= ucfirst($result['status']) ?></h5>
                            <p class="mb-1"><?= $result['message'] ?></p>
                            <?php if (isset($result['details'])): ?>
                                <div class="mt-2 p-2 bg-light rounded pre-wrap"><?= $result['details'] ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Test Password Reset</h5>
                    <p class="card-subtitle">Test the password reset functionality with test users</p>
                </div>
                <div class="card-body">
                    <form method="POST" id="testResetForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Test User</label>
                                <select name="test_user_id" class="form-select" required>
                                    <option value="">Choose a test user...</option>
                                    <?php foreach ($testUsers as $user): ?>
                                        <option value="<?= $user['UserID'] ?>">
                                            <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['Email']) ?>) - <?= $user['Role'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option disabled>──────────</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <?php if (!in_array($user, $testUsers)): ?>
                                            <option value="<?= $user['UserID'] ?>">
                                                <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['Email']) ?>) - <?= $user['Role'] ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Test users are recommended to avoid affecting real users</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test Type</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="test_type" id="testSendLink" value="send_link" checked>
                                        <label class="form-check-label" for="testSendLink">
                                            Test reset link generation
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="test_type" id="testForceReset" value="force_reset">
                                        <label class="form-check-label" for="testForceReset">
                                            Test force reset with temporary password
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="test_type" id="testVerifyReset" value="verify_reset">
                                        <label class="form-check-label" for="testVerifyReset">
                                            Test token verification
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="test_reset" class="btn btn-primary">Run Test</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Test Users</h5>
                    <p class="card-subtitle">These test users are available for testing</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($testUsers)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($testUsers as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['Name']) ?></td>
                                        <td><?= htmlspecialchars($user['Email']) ?></td>
                                        <td><?= $user['Role'] ?></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No test users found. They will be created automatically when you access this page.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Testing Checklist</h5>
                </div>
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check1">
                        <label class="form-check-label" for="check1">
                            Test reset link generation creates valid token in database
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check2">
                        <label class="form-check-label" for="check2">
                            Test token verification works correctly
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check3">
                        <label class="form-check-label" for="check3">
                            Test force reset generates temporary password
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check4">
                        <label class="form-check-label" for="check4">
                            Verify temporary password can be used to login
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check5">
                        <label class="form-check-label" for="check5">
                            Test with different user roles (user, host, admin)
                        </label>
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
        // Confirm before running tests
        $('#testResetForm').on('submit', function(e) {
            const userId = $('select[name="test_user_id"]').val();
            const testType = $('input[name="test_type"]:checked').val();

            if (!userId) {
                e.preventDefault();
                alert('Please select a user to test with.');
                return;
            }

            const userName = $('select[name="test_user_id"] option:selected').text();
            let message = '';

            if (testType === 'send_link') {
                message = `Run reset link generation test for ${userName}?`;
            } else if (testType === 'force_reset') {
                message = `Run force reset test for ${userName}? This will change their password.`;
            } else if (testType === 'verify_reset') {
                message = `Run token verification test for ${userName}?`;
            }

            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>