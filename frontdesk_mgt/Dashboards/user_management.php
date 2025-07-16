<?php
require_once '../dbConfig.php';
global $conn;
session_start();

if (isset($_SESSION['userID'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();

    // Only log activity for admins
    $activity = "Visited " . basename($_SERVER['PHP_SELF']);
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['userID'], $activity);
    $stmt->execute();
}

// Process bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!empty($_POST['selected_users'])) {
        $userIds = array_map('intval', $_POST['selected_users']);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        try {
            switch ($_POST['bulk_action']) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE users SET status='active' WHERE UserID IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    break;
                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE UserID IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    break;
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM users WHERE UserID IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    break;
                case 'reset_metrics':
                    $stmt = $conn->prepare("UPDATE users SET last_activity = NULL, last_login = NULL, 
                                            last_logout = NULL, login_count = 0 
                                            WHERE UserID IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    $stmt = $conn->prepare("DELETE FROM user_activity_log WHERE user_id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE user_id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    $stmt->execute();
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid bulk action']);
                    exit;
            }
            echo json_encode(['success' => true, 'message' => 'Bulk action completed successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No users selected']);
    }
    exit;
}

// Get filter parameters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build query
$sql = "SELECT UserID, Name, Email, Phone, Role, status, last_activity, 
               last_login, last_logout, login_count 
        FROM users WHERE 1=1";

if ($roleFilter) $sql .= " AND Role='".$conn->real_escape_string($roleFilter)."'";
if ($statusFilter) $sql .= " AND status='".$conn->real_escape_string($statusFilter)."'";
if ($searchTerm) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $sql .= " AND (Name LIKE '%$searchTerm%' OR Email LIKE '%$searchTerm%')";
}

$result = $conn->query($sql);
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get distinct roles for filter dropdown
$roles = $conn->query("SELECT DISTINCT Role FROM users")->fetch_all(MYSQLI_ASSOC);

// Get failed login counts for all users in one query
$userIds = array_column($users, 'UserID');
$failedCounts = [];

if (!empty($userIds)) {
    $ids = implode(',', $userIds);
    $sql = "SELECT user_id, COUNT(*) as failed_count 
            FROM login_attempts 
            WHERE user_id IN ($ids) AND success = 0 
            GROUP BY user_id";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $failedCounts[$row['user_id']] = $row['failed_count'];
        }
    }
}

// Function to determine online status
function getUserOnlineStatus($user) {
    // If user is inactive, they're offline
    if ($user['status'] !== 'active') {
        return 'inactive';
    }
    $lastActivity = $user['last_activity'] ? strtotime($user['last_activity']) : 0;
    $lastLogin = $user['last_login'] ? strtotime($user['last_login']) : 0;
    $lastLogout = $user['last_logout'] ? strtotime($user['last_logout']) : 0;
    $currentTime = time();

    // User is online if:
    // 1. They have recent activity (last 5 minutes) AND
    // 2. They have logged in at some point AND
    // 3. They haven't logged out OR logged out before last login
    if ($lastLogin > 0 &&
        (!$lastLogout || $lastLogout < $lastLogin) &&
        $lastActivity > 0 &&
        ($currentTime - $lastActivity) < 300) {
        return 'online';
    }

    // Fallback
    return 'offline';
}

// Close connection after all DB operations
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notification.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            padding-top: 1rem;
        }
        .sidebar a {
            color: #fff;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-online { background-color: #28a745; }
        .status-away { background-color: #ffc107; }
        .status-offline { background-color: #6c757d; }
        .status-inactive { background-color: #dc3545; }
        .user-activity {
            max-height: 200px;
            overflow-y: auto;
        }
        .bulk-actions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .status-text {
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-online-text { color: #28a745; }
        .status-away-text { color: #ffc107; }
        .status-offline-text { color: #6c757d; }
        .status-inactive-text { color: #dc3545; }
    </style>
</head>
<body>
<div class="sidebar">
    <h4 class="text-white text-center">Admin Panel</h4>
    <a href="admin-dashboard.html"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="user_management.php"><i class='far fa-address-card' ></i> User Management</a>
    <a href="admin-reports.php"><i class="fas fa-ticket"></i> Reporting</a>
    <a href="lost_found.php"><i class="fa-solid fa-suitcase"></i> View Lost & Found</a>
    <a href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
    <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>User Management</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-1"></i> Add New User
        </button>
    </div>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['Role'] ?>" <?= $roleFilter === $role['Role'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['Role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name or email" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    <!-- Bulk Actions and Users Table -->
    <form id="bulkActionsForm" method="POST">
        <div class="bulk-actions">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">Select All</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="bulk_action" class="form-select" required>
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate Accounts</option>
                        <option value="deactivate">Deactivate Accounts</option>
                        <option value="delete">Delete Users</option>
                        <option value="reset_metrics">Reset Metrics & Activities</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning">Apply</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                <tr>
                    <th width="30"><input type="checkbox" id="selectAllRows"></th>
                    <th>UserID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Online Status</th>
                    <th>Last Activity</th>
                    <th>Last Login</th>
                    <th>Last Logout</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user):
                    $onlineStatus = getUserOnlineStatus($user);
                    $failedCount = $failedCounts[$user['UserID']] ?? 0;
                    ?>
                    <tr>
                        <td><input type="checkbox" name="selected_users[]" value="<?= $user['UserID'] ?>"></td>
                        <td><?= htmlspecialchars($user['UserID']) ?></td>
                        <td>
                            <span class="status-badge status-<?= $onlineStatus ?>"></span>
                            <?= htmlspecialchars($user['Name']) ?>
                        </td>
                        <td><?= htmlspecialchars($user['Email']) ?></td>
                        <td><?= htmlspecialchars($user['Phone']) ?></td>
                        <td><?= htmlspecialchars($user['Role']) ?></td>
                        <td>
                        <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                        </td>
                        <td>
                        <span class="status-text status-<?= $onlineStatus ?>-text">
                            <?= ucfirst($onlineStatus) ?>
                        </span>
                        </td>
                        <td>
                            <?php if ($user['last_activity']): ?>
                                <?= date('M d, Y H:i', strtotime($user['last_activity'])) ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                        </td>
                        <td>
                            <?= $user['last_logout'] ? date('M d, Y H:i', strtotime($user['last_logout'])) : 'Never' ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-user"
                                    data-id="<?= $user['UserID'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editUserModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-user"
                                    data-id="<?= $user['UserID'] ?>"
                                    data-name="<?= htmlspecialchars($user['Name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info view-activity"
                                    data-id="<?= $user['UserID'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#activityModal">
                                <i class="fas fa-history"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm" method="POST" action="save_user.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['Role'] ?>"><?= htmlspecialchars($role['Role']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="editUserModalContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<!-- Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Recent Activity</h6>
                <div class="user-activity" id="userActivityContent">
                    <!-- Activity will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification System -->
<div class="notification-wrapper">
    <div class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-count" id="notificationCount">0</span>
    </div>
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button id="markAllReadBtn" class="mark-all-read">Mark All Read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Notifications will be inserted here -->
            <div class="empty-notification">No notifications</div>
        </div>
    </div>
</div>

<script src="notification.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Real-time status update for current admin
        function updateAdminStatus() {
            $.ajax({
                url: 'update_activity.php',
                type: 'GET',
                success: function() {
                    // Update only admin status indicator
                    const adminRow = $(`tr:has(td:contains('<?= $_SESSION['userID'] ?? 0 ?>'))`);
                    if(adminRow.length) {
                        const badge = adminRow.find('.status-badge');
                        const text = adminRow.find('.status-text');

                        badge.removeClass('status-online status-offline status-away status-inactive');
                        text.removeClass('status-online-text status-offline-text status-away-text status-inactive-text');

                        badge.addClass('status-online');
                        text.addClass('status-online-text').text('Online');
                    }
                }
            });
        }

        // Update immediately and every minute
        updateAdminStatus();
        setInterval(updateAdminStatus, 60000);


        // Bulk actions role selection toggle
        $('#bulkActionsForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            if ($('input[name="selected_users[]"]:checked').length === 0) {
                alert('Please select at least one user.');
                return;
            }
            $.ajax({
                type: 'POST',
                url: 'user_management.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload(); // Refresh to show updated data
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                }
            });
        });

        // Select all checkboxes
        $('#selectAll, #selectAllRows').click(function() {
            $('input[name="selected_users[]"]').prop('checked', this.checked);
        });

        // Edit user modal
        $('.edit-user').click(function() {
            const userId = $(this).data('id');
            $('#editUserModalContent').load('get_user.php?id=' + userId, function() {
                $('#editUserModal').modal('show');
            });
        });

        // View activity modal
        $('.view-activity').click(function() {
            const userId = $(this).data('id');
            $.get('get_activity.php?id=' + userId, function(data) {
                $('#userActivityContent').html(data);
            });
        });

        // Delete user
        $('.delete-user').click(function() {
            const userId = $(this).data('id');
            const userName = $(this).data('name');

            if (confirm('Are you sure you want to delete ' + userName + '?')) {
                $.ajax({
                    url: 'delete_user.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {id: userId},
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Delete failed: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Request failed. Please try again.');
                    }
                });
            }
        });

        // Handle add user form submission
        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'save_user.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#addUserModal').modal('hide');
                        location.reload(); // Refresh to show new user
                    } else {
                        alert('Error adding user: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error adding user. Please try again.');
                }
            });
        });

        // Handle edit form submission
        $(document).on('submit', '#editUserForm', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'save_user.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editUserModal').modal('hide');
                        location.reload(); // Refresh to show changes
                    } else {
                        alert('Error updating user: ' + response.error);
                    }
                },
                error: function() {
                    alert('Error updating user. Please try again.');
                }
            });
        });

        // Reset modal when closed
        $('#editUserModal').on('hidden.bs.modal', function() {
            $('#editUserModalContent').html('');
        });
    });
</script>
</body>
</html>