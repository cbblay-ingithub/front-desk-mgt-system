<?php
// Configure session settings
global $conn;
ini_set('session.cookie_domain', $_SERVER['HTTP_HOST']);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if ($_SERVER['HTTP_HOST'] === 'localhost:63342') {
    ini_set('session.cookie_domain', 'localhost');
}

require_once '../dbConfig.php';
session_start();


$adminId = $_SESSION['userID'];

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    header('Location: notifications.php');
    exit;
}

// Handle filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query based on filter
$query = "SELECT n.* FROM notifications n WHERE n.user_id = ?";
$params = [$adminId];
$types = "i";

if ($filter === 'unread') {
    $query .= " AND n.is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND n.is_read = 1";
}

$query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Get notifications
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for filters
$countStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
    FROM notifications WHERE user_id = ?
");
$countStmt->bind_param("i", $adminId);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();

// Get total for pagination
$totalNotifications = $countResult['total'];
$totalPages = ceil($totalNotifications / $limit);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-item.unread {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: #f0f0f0;
        }
        .filter-active {
            font-weight: bold;
            color: #007bff !important;
        }
    </style>
</head>
<body>
<?php include 'admin-sidebar.php'; ?>

<div class="layout-content">
    <?php include 'admin-navbar.php'; ?>

    <div class="container-fluid container-p-y">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold">Notifications</h4>
            <form method="post">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                    <i class="fas fa-check-double me-1"></i> Mark all as read
                </button>
            </form>
        </div>

        <!-- Filter tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'all' ? 'filter-active' : '' ?>"
                   href="?filter=all">All (<?= $countResult['total'] ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'unread' ? 'filter-active' : '' ?>"
                   href="?filter=unread">Unread (<?= $countResult['unread'] ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'read' ? 'filter-active' : '' ?>"
                   href="?filter=read">Read (<?= $countResult['read_count'] ?>)</a>
            </li>
        </ul>

        <!-- Notifications list -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-bell-slash fa-2x mb-3"></i>
                        <p>No notifications found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>"
                             data-id="<?= $notification['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                    <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                    </small>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <button class="btn btn-sm btn-outline-primary mark-read-btn"
                                            data-id="<?= $notification['id'] ?>">
                                        Mark read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Mark individual notification as read
        $('.mark-read-btn').click(function() {
            const notifId = $(this).data('id');
            const $item = $(this).closest('.notification-item');

            $.post('mark-notification-read.php', { notification_id: notifId }, function(response) {
                if (response.success) {
                    $item.removeClass('unread');
                    $item.find('.mark-read-btn').remove();

                    // Update badge count
                    const currentCount = parseInt($('#notificationBadge').text());
                    if (currentCount > 1) {
                        $('#notificationBadge').text(currentCount - 1);
                    } else {
                        $('#notificationBadge').hide();
                    }
                }
            });
        });
    });
</script>
</body>
</html>