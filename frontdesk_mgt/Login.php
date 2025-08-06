<?php
global $conn;
session_start();
require_once 'dbConfig.php';

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

        // Record login attempt (success or failure)
        $success = 0;
        if (password_verify($password, $hashedPassword)) {
            if ($status !== 'active') {
                echo "Your account is inactive. Please contact administrator.";
            } else {
                $success = 1;
                $_SESSION['userID'] = $userID;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;

                // UPDATE: Set login timestamp and reset logout timestamp
                $updateStmt = $conn->prepare("UPDATE users SET                      
                    last_login = NOW(), 
                    last_logout = NULL,
                    last_activity = NOW(), 
                    login_count = login_count + 1                      
                    WHERE UserID = ?");
                $updateStmt->bind_param("i", $userID);
                $updateStmt->execute();
                $updateStmt->close();

                // Record login activity
                $activity = "Logged in";
                $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
                $stmt->bind_param("is", $userID, $activity);
                $stmt->execute();
                $stmt->close();

                // Redirect based on role
                switch ($role) {
                    case 'Admin': header("Location: Dashboards/admin-dashboard.php"); break;
                    case 'Host': header("Location: Dashboards/host_dashboard.php"); break;
                    case 'Front Desk Staff': header("Location: Dashboards/visitor-mgt.php"); break;
                    case 'Support Staff': header("Location: Dashboards/help_desk.php"); break;
                    default: header("Location: unauthorized.php"); break;
                }
                exit;
            }
        } else {
            echo "Incorrect password.";
        }

        // Record login attempt
        $attemptStmt = $conn->prepare("INSERT INTO login_attempts (user_id, success, ip_address) VALUES (?, ?, ?)");
        $attemptStmt->bind_param("iis", $userID, $success, $ip);
        $attemptStmt->execute();
        $attemptStmt->close();
    } else {
        echo "No account found with that email.";
    }
    $stmt->close();
}
$conn->close();
?>