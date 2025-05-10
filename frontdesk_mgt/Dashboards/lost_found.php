<?php
global $conn;
session_start();
require_once '../dbConfig.php'; // Includes $conn
require_once 'lost_found_functions.php';

$userID = $_SESSION['userID'] ?? null;
if (!$userID) {
    // Redirect to login page or show error
    header("Location: ../Auth.html");
    exit;
}

// Use the global $conn from dbConfig.php
$items = getItems($conn); // Fetch all items
$categories = getItemCategories($conn); // Fetch categories
$conn->close(); // Close the connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found - Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px; }
        .sidebar a { color: white; display: block; padding: 10px; text-decoration: none; }
        .sidebar a:hover { background: #495057; }
        .sidebar a.active { background: #495057; }
        .container { flex: 1; padding: 20px; }
        .item-card { margin-bottom: 20px; }
        .item-card img { max-height: 150px; object-fit: cover; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 600px; }
        .close { float: right; font-size: 24px; cursor: pointer; }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <h4 class="text-white text-center">Front Desk Panel</h4>
        <a href="front-desk_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="visitor-mgt.php"><i class="fas fa-users me-2"></i> Manage Visitors</a>
        <a href="FD_frontend_dash.php"><i class="fas fa-calendar-check me-2"></i> Appointments</a>
        <a href="staff_tickets.php"><i class="fas fa-ticket-alt me-2"></i> Help Desk Tickets</a>
        <a href="lost_found.php" class="active"><i class="fas fa-suitcase me-2"></i> Lost and Found</a>
        <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>
    <div class="container">
        <h1>Lost and Found Management</h1>
        <div class="mb-3">
            <button id="logItemBtn" class="btn btn-primary">Log New Item</button>
            <a href="lost_found_report.php" class="btn btn-secondary">Generate Report</a>
        </div>

        <div class="row">
            <?php foreach ($items as $item): ?>
                <div class="col-md-4 item-card">
                    <div class="card">
                        <?php if ($item['PhotoPath']): ?>
                            <img src="<?php echo htmlspecialchars($item['PhotoPath']); ?>" class="card-img-top" alt="Item Photo">
                        <?php else: ?>
                            <div class="card-img-top text-center p-3">No Photo</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['Description']); ?></h5>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($item['Status']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($item['Location']); ?></p>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Actions
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item view-item" href="#" data-id="<?php echo $item['ItemID']; ?>">View</a></li>
                                    <li><a class="dropdown-item edit-item" href="#" data-id="<?php echo $item['ItemID']; ?>">Edit</a></li>
                                    <li><a class="dropdown-item resolve-item" href="#" data-id="<?php echo $item['ItemID']; ?>">Resolve</a></li>
                                    <li><a class="dropdown-item dispose-item" href="#" data-id="<?php echo $item['ItemID']; ?>">Dispose</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal for Logging New Item -->
<div id="logItemModal" class="modal">
    <div class="modal-content">
        <span class="close">×</span>
        <h2>Log New Item</h2>
        <form id="logItemForm" method="POST" action="lost_found_ajax.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_item">
            <input type="hidden" name="reported_by" value="<?php echo $_SESSION['userID']; ?>">
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" required>
                    <option value="lost">Lost</option>
                    <option value="found">Found</option>
                </select>
            </div>
            Lesson 1: Introduction to PHP
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control">
                    <option value="">Select Category</option>
                    <?php
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            if (isset($category['CategoryID']) && isset($category['CategoryName'])) {
                                echo '<option value="' . htmlspecialchars($category['CategoryID']) . '">' . htmlspecialchars($category['CategoryName']) . '</option>';
                            }
                        }
                    } else {
                        echo '<option value="">No categories available</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Photo (Optional)</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label">Storage Location (if found)</label>
                <input type="text" name="location_stored" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Save Item</button>
        </form>
    </div>
</div>

<!-- View Item Modal -->
<div id="viewItemModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="itemDetails">
                <!-- Item details loaded here via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editItemModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm">
                    <input type="hidden" name="itemId" id="editItemId">
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editCategory" class="form-label">Category</label>
                        <select class="form-control" id="editCategory" name="category_id">
                            <option value="">Select Category</option>
                            <?php
                            if (!empty($categories)) {
                                foreach ($categories as $category) {
                                    if (isset($category['CategoryID']) && isset($category['CategoryName'])) {
                                        echo '<option value="' . htmlspecialchars($category['CategoryID']) . '">' . htmlspecialchars($category['CategoryName']) . '</option>';
                                    }
                                }
                            } else {
                                echo '<option value="">No categories available</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editLocation" class="form-label">Location</label>
                        <input type="text" class="form-control" id="editLocation" name="location">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Resolve/Claim Item Modal -->
<div id="resolveItemModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resolve Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="resolveItemForm">
                    <input type="hidden" name="itemId" id="resolveItemId">
                    <div class="mb-3">
                        <label for="claimantName" class="form-label">Claimant Name</label>
                        <input type="text" class="form-control" id="claimantName" name="claimantName" required>
                    </div>
                    <div class="mb-3">
                        <label for="claimantContact" class="form-label">Contact Info</label>
                        <input type="text" class="form-control" id="claimantContact" name="claimantContact" required>
                    </div>
                    <div class="mb-3">
                        <label for="claimantId" class="form-label">ID Provided</label>
                        <input type="text" class="form-control" id="claimantId" name="claimantId" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="resolveBtn">Resolve</button>
            </div>
        </div>
    </div>
</div>

<!-- Dispose Item Modal -->
<div id="disposeItemModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dispose Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to dispose of this item?</p>
                <input type="hidden" id="disposeItemId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="disposeBtn">Dispose</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modal = document.getElementById('logItemModal');
    document.getElementById('logItemBtn').addEventListener('click', () => modal.style.display = 'block');
    document.querySelector('.close').addEventListener('click', () => modal.style.display = 'none');

    document.getElementById('logItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('lost_found_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item logged successfully');
                    modal.style.display = 'none';
                    location.reload();
                } else {
                    alert(data.error);
                }
            })
            .catch(error => alert('Error: ' + error));
    });

    document.addEventListener('DOMContentLoaded', function() {
        // View Item
        document.querySelectorAll('.view-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                fetch(`lost_found_ajax.php?action=get_item&id=${itemId}`)
                    .then(response => response.json())
                    .then(response => {
                        if (!response.success) {
                            console.error('Error fetching item:', response.error);
                            alert('Error: ' + response.error);
                            return;
                        }
                        const data = response.data || {};
                        document.getElementById('itemDetails').innerHTML = `
                            <p><strong>Description:</strong> ${data.Description || 'Not provided'}</p>
                            <p><strong>Category:</strong> ${data.CategoryName || 'None'}</p>
                            <p><strong>Location:</strong> ${data.Location || 'Not provided'}</p>
                            <p><strong>Status:</strong> ${data.Status || 'Unknown'}</p>
                            <p><strong>Storage Location:</strong> ${data.LocationStored || 'Not specified'}</p>
                        `;
                        new bootstrap.Modal(document.getElementById('viewItemModal')).show();
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error: ' + error);
                    });
            });
        });

        // Edit Item
        document.querySelectorAll('.edit-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                fetch(`lost_found_ajax.php?action=get_item&id=${itemId}`)
                    .then(response => response.json())
                    .then(response => {
                        if (!response.success) {
                            alert('Error: ' + response.error);
                            return;
                        }
                        const data = response.data || {};
                        document.getElementById('editItemId').value = itemId;
                        document.getElementById('editDescription').value = data.Description || '';
                        document.getElementById('editCategory').value = data.CategoryID || '';
                        document.getElementById('editLocation').value = data.Location || '';
                        new bootstrap.Modal(document.getElementById('editItemModal')).show();
                    });
            });
        });

        document.getElementById('saveEditBtn').addEventListener('click', function() {
            const form = document.getElementById('editItemForm');
            const formData = new FormData(form);
            fetch('lost_found_ajax.php?action=update_item', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();
                    location.reload();
                } else {
                    alert(data.error);
                }
            });
        });

        // Resolve Item
        document.querySelectorAll('.resolve-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                document.getElementById('resolveItemId').value = itemId;
                new bootstrap.Modal(document.getElementById('resolveItemModal')).show();
            });
        });

        document.getElementById('resolveBtn').addEventListener('click', function() {
            const form = document.getElementById('resolveItemForm');
            const formData = new FormData(form);
            fetch('lost_found_ajax.php?action=resolve_item', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('resolveItemModal')).hide();
                    location.reload();
                } else {
                    alert(data.error);
                }
            });
        });

        // Dispose Item
        document.querySelectorAll('.dispose-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                document.getElementById('disposeItemId').value = itemId;
                new bootstrap.Modal(document.getElementById('disposeItemModal')).show();
            });
        });

        document.getElementById('disposeBtn').addEventListener('click', function() {
            const itemId = document.getElementById('disposeItemId').value;
            fetch(`lost_found_ajax.php?action=dispose_item&id=${itemId}`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('disposeItemModal')).hide();
                        location.reload();
                    } else {
                        alert(data.error);
                    }
                });
        });
    });
</script>
</body>
</html>