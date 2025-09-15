<?php
require_once '../dbConfig.php';

function validatePassword($password, $conn) {
    // Get current password policy
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

    $errors = [];

    // Check minimum length
    if (strlen($password) < $passwordPolicy['min_length']) {
        $errors[] = "Password must be at least " . $passwordPolicy['min_length'] . " characters long";
    }

    // Check uppercase requirement
    if ($passwordPolicy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    // Check lowercase requirement
    if ($passwordPolicy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }

    // Check numbers requirement
    if ($passwordPolicy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    // Check special characters requirement
    if ($passwordPolicy['require_special_chars'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return $errors;
}
?>