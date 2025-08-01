<?php
session_start();
require_once '../dbConfig.php';

global $conn;
// Fetch visitors
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM visitor_Logs vl 
         WHERE vl.VisitorID = v.VisitorID 
         AND vl.CheckOutTime IS NULL) AS is_checked_in 
        FROM visitors v";
$visitors = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $visitors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Front Desk Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notification.css">
    <style>
        body { min-height: 100vh; display: flex; }
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
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <h4 class="text-white text-center">Front Desk Panel</h4>
    <a href="frontdesk_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#checkInModal"><i class="fas fa-users me-2"></i>Manage Visitors</a>
    <a href="FD_frontend_dash.php" class="active"><i class="fas fa-calendar-check me-2"></i> Appointments</a>
    <a href="staff_tickets.php"><i class="fas fa-ticket"></i> Help Desk Tickets</a>
    <a href="lost_found.php"><i class="fa-solid fa-suitcase"></i> View Lost & Found</a>
    <a href="../Logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="main-content">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            if ($_GET['msg'] === 'checked_in') {
                echo "Visitor successfully checked in.";
            } elseif ($_GET['msg'] === 'checked_out') {
                echo "Visitor successfully checked out.";
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h2 class="mb-4">Visitor Check In / Check Out</h2>

    <!-- Visitors Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
            <tr>
                <th>Name</th><th>Email</th><th>Phone</th>
                <th>ID Type</th><th>ID Number</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($visitors as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['Name']) ?></td>
                    <td><?= htmlspecialchars($v['Email']) ?></td>
                    <td><?= htmlspecialchars($v['Phone']) ?></td>
                    <td><?= htmlspecialchars($v['IDType']) ?></td>
                    <td><?= htmlspecialchars($v['IDNumber']) ?></td>
                    <td><?= $v['is_checked_in'] > 0 ? 'Checked In' : 'Not Checked In' ?></td>
                    <td>
                        <?php if ($v['is_checked_in'] > 0): ?>
                            <form method="POST" action="process_visit.php" class="d-inline">
                                <input type="hidden" name="action" value="check_out">
                                <input type="hidden" name="visitor_id" value="<?= $v['VisitorID'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Check Out</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <form action="generate_report.php" method="POST" class="mt-4 text-end">
        <button type="submit" class="btn btn-outline-dark btn-sm">Generate Visitor Logs</button>
    </form>

</div>

<!-- Check In Modal -->
<div class="modal fade" id="checkInModal" tabindex="-1" aria-labelledby="checkInModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="process_visit.php" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Check In Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <input class="form-control" name="name" placeholder="Name" required>
                </div>
                <div class="col-6">
                    <input class="form-control" name="email" type="email" placeholder="Email" required>
                </div>
                <div class="col-6">
                    <input class="form-control" name="phone" placeholder="Phone">
                </div>
                <div class="col-6">
                    <input class="form-control" name="id_type" placeholder="ID Type">
                </div>
                <div class="col-6">
                    <input class="form-control" name="id_number" placeholder="ID Number">
                </div>
                <div class="col-6">
                    <input class="form-control" name="visit_purpose" placeholder="Purpose of Visit">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="action" value="check_in" class="btn btn-success" href="front-staff-dash.php">Check In</button>
            </div>
        </form>
    </div>
</div>

<script src="notification.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
