<?php
global $conn;
require_once '../dbConfig.php';
$userId = intval($_GET['id']);

$activities = $conn->query("SELECT * FROM user_activity_log 
                           WHERE user_id = $userId 
                           ORDER BY activity_time DESC 
                           LIMIT 20");
?>

<?php if ($activities->num_rows > 0): ?>
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
<?php else: ?>
    <div class="alert alert-info">No activity recorded</div>
<?php endif; ?>