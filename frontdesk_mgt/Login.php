<?php
global $conn;
session_start();
require_once 'dbConfig.php';
require_once 'audit_logger.php';
$auditLogger = new AuditLogger($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email-username"]);
    $password = $_POST["password"];
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("SELECT UserID, Name, Password, Role, status, temp_password_expiry FROM users WHERE Email = ?");
    if ($stmt === false) die("Error preparing statement: " . $conn->error);

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userID, $name, $hashedPassword, $role, $status, $tempPasswordExpiry);
        $stmt->fetch();

        $success = false;
        $isTemporaryPassword = false;

        if (password_verify($password, $hashedPassword)) {
            // Check if this is a temporary password (has an expiry date)
            if ($tempPasswordExpiry !== null) {
                // Check if temporary password is still valid
                $currentTime = date('Y-m-d H:i:s');
                if ($currentTime > $tempPasswordExpiry) {
                    // Temporary password has expired
                    $auditLogger->logLogin($userID, $role, false, "Expired temporary password used");
                    header("Location: Auth.html?error=temp_password_expired");
                    exit;
                }

                $isTemporaryPassword = true;
            }

            if ($status !== 'active') {
                // Log inactive account attempt
                $auditLogger->logLogin($userID, $role, false, "Inactive account attempted login");
                header("Location: Auth.html?error=inactive_account");
                exit;
            } else {
                $success = true;

                if ($isTemporaryPassword) {
                    // Redirect to change password page for temporary password
                    $_SESSION['temp_password_user'] = $userID;
                    $_SESSION['temp_password_email'] = $email;
                    header("Location: change_temp_password.php");
                    exit;
                } else {
                    // Regular login process
                    $_SESSION['userID'] = $userID;
                    $_SESSION['name'] = $name;
                    $_SESSION['role'] = $role;

                    // Update user login tracking
                    $updateStmt = $conn->prepare("UPDATE users SET                      
                        last_login = NOW(), 
                        last_logout = NULL,
                        last_activity = NOW(), 
                        login_count = login_count + 1                      
                        WHERE UserID = ?");
                    $updateStmt->bind_param("i", $userID);
                    $updateStmt->execute();
                    $updateStmt->close();

                    // Log successful login
                    $auditLogger->logLogin($userID, $role, true);

                    // Redirect based on role
                    switch ($role) {
                        case 'Admin': header("Location: Dashboards/admin-dashboard.php"); break;
                        case 'Host': header("Location: Dashboards/host_analytics.php"); break;
                        case 'Front Desk Staff': header("Location: Dashboards/frontdesk_dashboard.php"); break;
                        case 'Support Staff': header("Location: Dashboards/HD_analytics.php"); break;
                        default: header("Location: Dashboards/401-page.html"); break;
                    }
                    exit;
                }
            }
        } else {
            // Log failed password attempt
            $auditLogger->logLogin($userID ?? 0, $role ?? 'unknown', false, "Incorrect password");

            // Redirect back to log in with error message
            header("Location: Auth.html?error=invalid_credentials&message=Incorrect password");
            exit;
        }

        // Legacy login attempt tracking
        $attemptStmt = $conn->prepare("INSERT INTO login_attempts (user_id, success, ip_address) VALUES (?, ?, ?)");
        $successInt = (int)$success;
        $attemptStmt->bind_param("iis", $userID, $successInt, $ip);
        $attemptStmt->execute();
        $attemptStmt->close();
    } else {
        // Log non-existent user attempt
        $auditLogger->logLogin(0, 'unknown', false, "Attempted login with non-existent email: $email");

        // Redirect back to login with error message
        header("Location: Auth.html?error=invalid_credentials&message=No account found with that email");
        exit;
    }
    $stmt->close();
}
$conn->close();
?>