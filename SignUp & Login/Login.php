<?php
// login.php
global $conn;
session_start();
require_once 'dbConfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize inputs
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    //execute select query
    $stmt = $conn->prepare("SELECT UserID, Name, Password, Role FROM users WHERE Email = ?");
    if ($stmt === false) {
        die("Error preparing the statement: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // Check if user exists
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userID, $name, $hashedPassword, $role);
        $stmt->fetch();

        // Verify the password
        if (password_verify($password, $hashedPassword)) {
            // Password is correct, set up session variables
            // After setting session variables
            $_SESSION['userID'] = $userID;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

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
                    // Handle unknown roles
                    header("Location: unauthorized.php");
                    break;
            }
            exit;

            // You might want to redirect the user:
            // exit;
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "No account found with that email.";
    }
    $stmt->close();
}

$conn->close();

