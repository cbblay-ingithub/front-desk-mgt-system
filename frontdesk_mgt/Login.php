<?php
// login.php
global $conn;
session_start();
require_once 'dbConfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize inputs
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Execute select query - added status field
    $stmt = $conn->prepare("SELECT UserID, Name, Password, Role, status FROM users WHERE Email = ?");
    if ($stmt === false) {
        die("Error preparing the statement: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // Check if user exists
    if ($stmt->num_rows > 0) {
        // Added status to bind_result
        $stmt->bind_result($userID, $name, $hashedPassword, $role, $status);
        $stmt->fetch();

        // Verify the password
        if (password_verify($password, $hashedPassword)) {
            // Check account status
            if ($status !== 'active') {
                echo "Your account is inactive. Please contact administrator.";
            } else {
                // Password is correct and account active, set up session
                $_SESSION['userID'] = $userID;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;

                // Update login activity
                $updateStmt = $conn->prepare("UPDATE users SET 
                      last_activity = NOW(), 
                      login_count = login_count + 1 
                      WHERE UserID = ?");
                $updateStmt->bind_param("i", $userID);
                $updateStmt->execute();
                $updateStmt->close();

                // Redirect based on role
                switch ($role) {
                    case 'Admin':
                        header("Location: Dashboards/admin-dashboard.html");
                        break;
                    case 'Host':
                        header("Location: Dashboards/host_dashboard.php");
                        break;
                    case 'Front Desk Staff':
                        header("Location: Dashboards/visitor-mgt.php");
                        break;
                    case 'Support Staff':
                        header("Location: Dashboards/help_desk.php");
                        break;
                    default:
                        header("Location: unauthorized.php");
                        break;
                }
                exit;
            }
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "No account found with that email.";
    }
    $stmt->close();
}
$conn->close();