<?php
class AuditLogger {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function logEvent($userId, $userRole, $actionType, $status, $description = '', $oldValue = null, $newValue = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $category = $this->determineCategory($actionType);

        // Prepare the statement
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs 
            (user_id, user_role, action_type, action_category, status, ip_address, description, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }

        // Convert null values to empty strings for JSON fields
        $oldValueJson = $oldValue !== null ? json_encode($oldValue) : null;
        $newValueJson = $newValue !== null ? json_encode($newValue) : null;

        // Bind parameters
        $stmt->bind_param(
            "issssssss",
            $userId,
            $userRole,
            $actionType,
            $category,
            $status,
            $ip,
            $description,
            $oldValueJson,
            $newValueJson
        );

        $result = $stmt->execute();

        if (!$result) {
            error_log("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        return $result;
    }

    private function determineCategory($actionType) {
        $categories = [
            'LOGIN' => 'AUTHENTICATION',
            'LOGOUT' => 'AUTHENTICATION',
            'PASSWORD_CHANGE' => 'AUTHENTICATION',
            'USER_CREATE' => 'USER_MANAGEMENT',
            'USER_UPDATE' => 'USER_MANAGEMENT',
            'USER_DELETE' => 'USER_MANAGEMENT',
            'ROLE_CHANGE' => 'USER_MANAGEMENT',
            'CONFIG_UPDATE' => 'SYSTEM',
            'DATA_EXPORT' => 'SYSTEM',
            'DATA_IMPORT' => 'SYSTEM'
        ];

        return $categories[$actionType] ?? 'OTHER';
    }

    // Specific convenience methods
    public function logLogin($userId, $userRole, $success, $description = '') {
        return $this->logEvent(
            $userId,
            $userRole,
            'LOGIN',
            $success ? 'SUCCESS' : 'FAILURE',
            $description
        );
    }

    public function logLogout($userId, $userRole) {
        return $this->logEvent(
            $userId,
            $userRole,
            'LOGOUT',
            'SUCCESS',
            'User logged out'
        );
    }

    public function logUserUpdate($userId, $userRole, $oldData, $newData) {
        return $this->logEvent(
            $userId,
            $userRole,
            'USER_UPDATE',
            'SUCCESS',
            'User profile updated',
            $oldData,
            $newData
        );
    }
}