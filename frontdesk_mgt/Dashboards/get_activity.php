<?php
session_start();
global $conn;
require_once '../dbConfig.php';

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId <= 0) die('<div class="alert alert-danger">Invalid user ID</div>');

// Retrieve activities
$stmt = $conn->prepare("SELECT * FROM user_activity_log 
                        WHERE user_id = ? 
                        ORDER BY activity_time DESC 
                        LIMIT 20");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activities = $stmt->get_result();

// Retrieve login attempts
$attemptStmt = $conn->prepare("SELECT * FROM login_attempts 
                               WHERE user_id = ? 
                               ORDER BY attempt_time DESC 
                               LIMIT 10");
$attemptStmt->bind_param("i", $userId);
$attemptStmt->execute();
$loginAttempts = $attemptStmt->get_result();
?>

<?php if ($activities->num_rows > 0 || $loginAttempts->num_rows > 0): ?>
    <div class="accordion" id="activityAccordion">
        <!-- Activity Log -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#activityLog">
                    User Activity (Last 20)
                </button>
            </h2>
            <div id="activityLog" class="accordion-collapse collapse show" data-bs-parent="#activityAccordion">
                <div class="accordion-body">
                    <ul class="list-group">
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($activity['activity']) ?></span>
                                    <small class="text-muted">
                                        <?= date('M d, H:i', strtotime($activity['activity_time'])) ?>
                                    </small>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Login History -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#loginHistory">
                    Login History (Last 10)
                </button>
            </h2>
            <div id="loginHistory" class="accordion-collapse collapse" data-bs-parent="#activityAccordion">
                <div class="accordion-body">
                    <ul class="list-group">
                        <?php while ($attempt = $loginAttempts->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <?= $attempt['success'] ? '✅ Successful login' : '❌ Failed login' ?>
                                        <small>(IP: <?= htmlspecialchars($attempt['ip_address']) ?>)</small>
                                    </span>
                                    <small class="text-muted">
                                        <?= date('M d, H:i', strtotime($attempt['attempt_time'])) ?>
                                    </small>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">No activity recorded</div>
<?php endif; ?>