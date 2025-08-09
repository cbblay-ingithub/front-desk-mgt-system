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

    $stmt = $conn->prepare("SELECT UserID, Name, Password, Role, status FROM users WHERE Email = ?");
    if ($stmt === false) die("Error preparing statement: " . $conn->error);

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userID, $name, $hashedPassword, $role, $status);
        $stmt->fetch();

        $success = false;
        if (password_verify($password, $hashedPassword)) {
            if ($status !== 'active') {
                // Log inactive account attempt
                $auditLogger->logLogin($userID, $role, false, "Inactive account attempted login");
                header("Location: Dashboards/401- page.html");
                exit;
            } else {
                $success = true;
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
                    default: header("Location: Dashboards/401- page.html"); break;
                }
                exit;
            }
        } else {
            // Log failed password attempt
            $auditLogger->logLogin($userID ?? 0, $role ?? 'unknown', false, "Incorrect password");
            echo "Incorrect password.";
        }

        // Legacy login attempt tracking (optional - can be removed if using audit logs)
        $attemptStmt = $conn->prepare("INSERT INTO login_attempts (user_id, success, ip_address) VALUES (?, ?, ?)");
        $successInt = (int)$success; // Create a variable first
        $attemptStmt->bind_param("iis", $userID, $successInt, $ip);
        $attemptStmt->execute();
        $attemptStmt->close();
    } else {
        // Log non-existent user attempt
        $auditLogger->logLogin(0, 'unknown', false, "Attempted login with non-existent email: $email");
        echo "No account found with that email.";
    }
    $stmt->close();
}
$conn->close();
?>