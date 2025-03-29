<?php

// signup.php
global $conn;
session_start();
require_once 'dbConfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and trim inputs
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $role = trim($_POST["role"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Validating the user
    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Hashing the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute insert query
    $stmt = $conn->prepare("INSERT INTO users (Name, Email, Phone, Role, Password) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        die("Error preparing the statement: " . $conn->error);
    }
    $stmt->bind_param("sssss", $name, $email, $phone, $role, $passwordHash);

    //Redirection to Log-in page
    if ($stmt->execute()) {
        echo "Successful registration ! You can now <a href='Auth.html'>Log in</a>.";
    } else {
        // For debugging, you might output $stmt->error, but in production log errors instead.
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();

?>