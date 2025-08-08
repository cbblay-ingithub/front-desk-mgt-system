<?php
// AuditLogger.php
class AuditLogger {
    private mysqli $conn;

    public function __construct(mysqli $conn) { // Changed type hint
        $this->conn = $conn;
    }


    /**
     * Log an audit event
     *
     * @param int $userId User ID performing the action
     * @param string $userRole User role (admin, front_desk, host, support)
     * @param string $actionType Action type (LOGIN, BOOKING_CREATE, etc.)
     * @param string $actionCategory Action category (AUTHENTICATION, BOOKING, USER_MGMT)
     * @param array $options Additional options
     * @return string Generated log ID
     */
    public function log($userId, $userRole, $actionType, $actionCategory, $options = []) {
        try {
            $logId = $this->generateLogId();
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionId = session_id() ?: '';

            // Prepare data
            $data = [
                $logId, $userId, $userRole, $actionType, $actionCategory,
                $options['table_affected'] ?? null,
                $options['record_id'] ?? null,
                isset($options['old_value']) ? json_encode($options['old_value']) : null,
                isset($options['new_value']) ? json_encode($options['new_value']) : null,
                $ipAddress, $userAgent, $sessionId,
                $options['status'] ?? 'SUCCESS',
                $options['description'] ?? null,
                $options['location_id'] ?? null
            ];

            $query = "
                INSERT INTO audit_logs (
                    log_id, user_id, user_role, action_type, action_category,
                    table_affected, record_id, old_value, new_value, ip_address,
                    user_agent, session_id, status, description, location_id
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "sissssissssssss", // Binding types (s=string, i=integer)
                ...$data
            );
            $stmt->execute();

            return $logId;
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log authentication events
     */
    public function logLogin($userId, $userRole, $success = true, $description = null) {
        return $this->log($userId, $userRole, 'LOGIN', 'AUTHENTICATION', [
            'status' => $success ? 'SUCCESS' : 'FAILURE',
            'description' => $description ?: ($success ? 'User logged in successfully' : 'Login attempt failed')
        ]);
    }

    public function logLogout($userId, $userRole) {
        return $this->log($userId, $userRole, 'LOGOUT', 'AUTHENTICATION', [
            'description' => 'User logged out'
        ]);
    }

    /**
     * Log user management events
     */
    public function logUserCreate($performingUserId, $performingUserRole, $newUserId, $newUserData) {
        return $this->log($performingUserId, $performingUserRole, 'USER_CREATE', 'USER_MGMT', [
            'table_affected' => 'users',
            'record_id' => $newUserId,
            'new_value' => $this->sanitizeUserData($newUserData),
            'description' => "Created new user ID: $newUserId"
        ]);
    }

    public function logUserUpdate($performingUserId, $performingUserRole, $targetUserId, $oldData, $newData) {
        return $this->log($performingUserId, $performingUserRole, 'USER_UPDATE', 'USER_MGMT', [
            'table_affected' => 'users',
            'record_id' => $targetUserId,
            'old_value' => $this->sanitizeUserData($oldData),
            'new_value' => $this->sanitizeUserData($newData),
            'description' => "Updated user ID: $targetUserId"
        ]);
    }

    public function logUserDelete($performingUserId, $performingUserRole, $deletedUserId, $userData) {
        return $this->log($performingUserId, $performingUserRole, 'USER_DELETE', 'USER_MGMT', [
            'table_affected' => 'users',
            'record_id' => $deletedUserId,
            'old_value' => $this->sanitizeUserData($userData),
            'description' => "Deleted user ID: $deletedUserId"
        ]);
    }

    /**
     * Log booking events
     */
    public function logBookingCreate($userId, $userRole, $bookingId, $bookingData) {
        return $this->log($userId, $userRole, 'BOOKING_CREATE', 'BOOKING', [
            'table_affected' => 'bookings',
            'record_id' => $bookingId,
            'new_value' => $bookingData,
            'description' => "Created booking ID: $bookingId"
        ]);
    }

    public function logBookingUpdate($userId, $userRole, $bookingId, $oldData, $newData) {
        return $this->log($userId, $userRole, 'BOOKING_UPDATE', 'BOOKING', [
            'table_affected' => 'bookings',
            'record_id' => $bookingId,
            'old_value' => $oldData,
            'new_value' => $newData,
            'description' => "Updated booking ID: $bookingId"
        ]);
    }

    public function logBookingCancel($userId, $userRole, $bookingId, $bookingData, $reason = null) {
        return $this->log($userId, $userRole, 'BOOKING_CANCEL', 'BOOKING', [
            'table_affected' => 'bookings',
            'record_id' => $bookingId,
            'old_value' => $bookingData,
            'description' => "Cancelled booking ID: $bookingId" . ($reason ? " - Reason: $reason" : "")
        ]);
    }

    /**
     * Log system events
     */
    public function logPasswordChange($userId, $userRole, $targetUserId = null) {
        $targetUserId = $targetUserId ?: $userId;
        return $this->log($userId, $userRole, 'PASSWORD_CHANGE', 'SECURITY', [
            'table_affected' => 'users',
            'record_id' => $targetUserId,
            'description' => $userId === $targetUserId ? 'Changed own password' : "Changed password for user ID: $targetUserId"
        ]);
    }

    public function logRoleChange($performingUserId, $performingUserRole, $targetUserId, $oldRole, $newRole) {
        return $this->log($performingUserId, $performingUserRole, 'ROLE_CHANGE', 'USER_MGMT', [
            'table_affected' => 'users',
            'record_id' => $targetUserId,
            'old_value' => ['role' => $oldRole],
            'new_value' => ['role' => $newRole],
            'description' => "Changed role for user ID: $targetUserId from $oldRole to $newRole"
        ]);
    }

    public function logConfigUpdate($userId, $userRole, $configKey, $oldValue, $newValue) {
        return $this->log($userId, $userRole, 'CONFIG_UPDATE', 'SYSTEM', [
            'table_affected' => 'settings',
            'old_value' => [$configKey => $oldValue],
            'new_value' => [$configKey => $newValue],
            'description' => "Updated configuration: $configKey"
        ]);
    }

    public function logDataExport($userId, $userRole, $exportType, $recordCount) {
        return $this->log($userId, $userRole, 'DATA_EXPORT', 'DATA', [
            'description' => "Exported $exportType data ($recordCount records)"
        ]);
    }

    public function logDataImport($userId, $userRole, $importType, $recordCount, $success = true) {
        return $this->log($userId, $userRole, 'DATA_IMPORT', 'DATA', [
            'status' => $success ? 'SUCCESS' : 'FAILURE',
            'description' => "Imported $importType data ($recordCount records)"
        ]);
    }

    /**
     * Generate unique 8-character log ID
     */
    private function generateLogId() {
        return strtoupper(substr(uniqid(), -8));
    }

    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'unknown';
    }

    /**
     * Sanitize user data for logging (remove sensitive fields)
     */
    private function sanitizeUserData($userData) {
        if (!is_array($userData)) {
            return $userData;
        }

        $sanitized = $userData;

        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_hash', 'token', 'api_key', 'secret'];
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Get recent activity for a user
     */
    public function getUserActivity($userId, $limit = 10) {
        $query = "
            SELECT action_type, action_category, status, description, created_at
            FROM audit_logs 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get failed login attempts for security monitoring
     */
    public function getFailedLogins($timeframe = '24 HOUR', $limit = 100) {
        $query = "
            SELECT user_id, ip_address, user_agent, created_at, description
            FROM audit_logs 
            WHERE action_type = 'LOGIN' 
            AND status = 'FAILURE' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL $timeframe)
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Usage example:
/*
// Initialize the logger
$pdo = new PDO("mysql:host=localhost;dbname=your_db", $username, $password);
$auditLogger = new AuditLogger($pdo);

// Log user login
$auditLogger->logLogin($userId, $userRole, true);

// Log booking creation
$auditLogger->logBookingCreate($userId, $userRole, $bookingId, $bookingData);

// Log user update
$auditLogger->logUserUpdate($adminUserId, 'admin', $targetUserId, $oldUserData, $newUserData);

// Log custom action
$auditLogger->log($userId, $userRole, 'CUSTOM_ACTION', 'CUSTOM_CATEGORY', [
    'description' => 'Custom action performed',
    'status' => 'SUCCESS'
]);
*/
?>