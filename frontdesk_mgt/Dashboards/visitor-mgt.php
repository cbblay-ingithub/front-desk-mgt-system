<?php
session_start();
require_once '../dbConfig.php';

global $conn;

// FIXED QUERY: Properly checks if visitor is currently checked in
$sql = "SELECT 
            v.*,
            -- Check if visitor has any active check-in (no checkout time AND status is 'Checked In')
            IF(EXISTS (
                    SELECT 1 FROM visitor_Logs vl 
                    WHERE vl.VisitorID = v.VisitorID 
                    AND vl.CheckOutTime IS NULL
                    AND vl.Status = 'Checked In'
                ), 1, 0) AS is_checked_in,
            -- Get the most recent badge number from appointments if exists
            (SELECT a.BadgeNumber FROM appointments a 
             WHERE a.VisitorID = v.VisitorID 
             ORDER BY a.AppointmentID DESC LIMIT 1) AS BadgeNumber
        FROM visitors v
        ORDER BY v.Name";

$visitors = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $visitors[] = $row;
}

// Get visitor names for autocomplete (last 100 visitors for performance)
$autocomplete_sql = "SELECT DISTINCT Name, Email, Phone, IDType FROM visitors ORDER BY Name LIMIT 100";
$autocomplete_result = $conn->query($autocomplete_sql);
$visitor_suggestions = [];
while ($row = $autocomplete_result->fetch_assoc()) {
    $visitor_suggestions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Manage Visitors- front desk</title>
    <!-- Main CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <!-- Sneat CSS (same as host_dashboard) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

    <style>
        /* Layout structure matching host_dashboard */
        html, body {
            height: 100%;
            overflow-x: hidden !important;
        }

        .layout-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

        /* Sidebar width fixes */
        #layout-menu {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
            flex: 0 0 260px !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1000 !important;
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease !important;
        }
        #layout-navbar {
            position: sticky;
            top: 0;
            z-index: 999; /* Ensure it stays above other content */
            background-color: var(--bs-body-bg); /* Match your theme background */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Optional: adds subtle shadow */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .layout-menu-collapsed #layout-menu {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
            flex: 0 0 78px !important;
        }

        .layout-content {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: margin-left 0.3s ease, width 0.3s ease !important;
        }

        .layout-menu-collapsed .layout-content {
            margin-left: 78px !important;
            width: calc(100% - 78px) !important;
        }

        /* Fix content padding */
        .container-fluid.container-p-y {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        /* Disable transitions during filtering */
        .no-transition {
            transition: none !important;
        }

        /* Better search and filter alignment */
        .search-filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }

        .search-input {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .filter-buttons-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }

        .filter-btn {
            border-radius: 20px;
            padding: 8px 16px;
            margin: 0;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-size: 0.875rem;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .filter-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .filter-btn:hover:not(.active) {
            background-color: #f8f9fa;
            border-color: #007bff;
            color: #007bff;
        }

        /* Mobile responsive filters */
        @media (max-width: 768px) {
            .filter-buttons-container {
                justify-content: center;
            }

            .filter-btn {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }
        /* Enhanced Search and Quick Actions */
        .quick-actions-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .smart-search-container {
            position: relative;
            margin-bottom: 15px;


        }

        .smart-search {
            border: none;
            border-radius: 50px;
            padding: 15px 25px 15px 55px;
            font-size: 1.1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            color: #333;
            align-items: center;
        }

        .smart-search:focus {
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }

        .autocomplete-item {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        /* Table enhancements */
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .badge-number {
            color: #495057;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-checked-in {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-checked-out {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-not-checked-in {
            background-color: #fada7d;
            color: #72541c;
            border: 1px solid #fada7d;
        }

        /* Action Buttons */
        .action-btn {
            margin: 2px;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Bulk Selection */
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .visitor-checkbox {
            transform: scale(1.2);
        }

        /* Modal enhancements */
        .visitor-detail-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .visitor-detail-modal .modal-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .detail-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #212529;
            font-size: 1.1rem;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        /* Loading animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Error alert styling */
        .alert-danger {
            border-left: 4px solid #dc3545;
        }
        /* Keyboard Shortcuts Indicator */
        .keyboard-shortcuts {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            font-size: 0.8rem;
            z-index: 1001;
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success Animation */
        .success-checkmark {
            display: inline-block;
            width: 22px;
            height: 22px;
            transform: rotate(45deg);
            margin-right: 10px;
        }

        .success-checkmark::before {
            content: '';
            position: absolute;
            width: 3px;
            height: 9px;
            background-color: #28a745;
            left: 11px;
            top: 6px;
        }

        .success-checkmark::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 3px;
            background-color: #28a745;
            left: 8px;
            top: 12px;
        }
        /* Badge Print Button Styling */
        .btn-secondary.action-btn {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .btn-secondary.action-btn:hover {
            background-color: #545b62;
            border-color: #4e555b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Badge Print Modal Styles */
        .badge-print-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .badge-print-modal .modal-header {
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        /* Badge Preview Styles */
        .badge-preview {
            width: 350px;
            height: 220px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 15px;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .badge-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(45deg, #007bff, #0056b3);
            border-radius: 13px 13px 0 0;
        }

        .badge-content {
            padding-top: 30px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .badge-logo {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 10px;
        }

        .badge-title {
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
            position: absolute;
            top: 12px;
            left: 20px;
            right: 20px;
            text-align: center;
        }

        .badge-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #212529;
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .badge-number-display {
            font-size: 1.4rem;
            font-weight: bold;
            color: #007bff;
            background: white;
            padding: 8px 15px;
            border-radius: 25px;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .badge-footer {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: auto;
            padding-top: 10px;
        }

        .print-options {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }

        .print-option-item {
            margin-bottom: 10px;
        }

        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }

            .badge-print-content,
            .badge-print-content * {
                visibility: visible;
            }

            .badge-print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .badge-preview {
                width: 3.5in;
                height: 2.2in;
                page-break-after: always;
                margin: 0;
                box-shadow: none;
                border: 1px solid #000;
            }
        }

        /* Loading state for print button */
        .printing {
            pointer-events: none;
            opacity: 0.7;
        }

        .printing .fas {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Keyboard Shortcuts Indicator -->
<div class="keyboard-shortcuts">
    <i class="fas fa-keyboard me-2"></i>
    Ctrl+O: Check-out | Ctrl+F: Search
</div>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'frontdesk-sidebar.php'; ?>

        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base bx bx-menu icon-md"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!--Page Title-->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2"> Manage Visitors</h4>
                        </div>
                    </div>
                    <!--Check-In button
                    <div class="navbar-nav align-items-center me-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickCheckinModal">
                            <i class="fas fa-plus-circle me-2"></i> Check-In Visitor
                        </button>
                    </div>
                    -->
            </nav>
            <div class="container-fluid container-p-y">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert <?= $_GET['msg'] === 'error' ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['msg'] === 'checked-in') {
                            echo "<div class='success-checkmark'></div>Visitor successfully checked in.";
                        } elseif ($_GET['msg'] === 'checked-out') {
                            echo "<div class='success-checkmark'></div>Visitor successfully checked out.";
                        } elseif ($_GET['msg'] === 'error' && isset($_GET['error'])) {
                            echo "<i class='fas fa-exclamation-triangle me-2'></i>Error: " . htmlspecialchars($_GET['error']);
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <!-- Bulk Actions Bar (Hidden by default) -->
                <div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><span id="selectedCount">0</span> visitors selected</span>
                        <div>
                            <button class="btn btn-success me-2" id="bulkCheckinConfirm">
                                <i class="fas fa-check me-2"></i>Check-in Selected
                            </button>
                            <button class="btn btn-danger me-2" id="bulkCheckoutConfirm">
                                <i class="fas fa-sign-out-alt me-2"></i>Check-out Selected
                            </button>
                            <button class="btn btn-secondary" id="clearSelection">
                                <i class="fas fa-times me-2"></i>Clear Selection
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="search-filter-section">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="smart-search-container">
                                <input type="text" id="smartSearch" class="form-control smart-search"
                                       placeholder="Start typing visitor name, email, or phone... (Ctrl+F)"
                                       autocomplete="off">
                                <div class="autocomplete-dropdown" id="autocompleteDropdown"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="filter-buttons-container">
                                    <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                                        <i class="fas fa-users me-1"></i> All (<span id="count-all">0</span>)
                                    </button>
                                    <button class="btn btn-outline-success filter-btn" data-filter="checked-in">
                                        <i class="fas fa-check-circle me-1"></i> In (<span id="count-checked-in">0</span>)
                                    </button>
                                    <button class="btn btn-outline-warning filter-btn" data-filter="not-checked-in">
                                        <i class="fas fa-times-circle me-1"></i> Not Checked In (<span id="count-not-checked-in">0</span>)
                                    </button>
                                    <button class="btn btn-outline-secondary filter-btn" data-filter="checked-out">
                                        <i class="fas fa-sign-out-alt me-1"></i> Out (<span id="count-checked-out">0</span>)
                                    </button>
                                </div>
                        </div>
                    </div>
                </div>

                <!-- Visitors Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="visitorsTable">
                        <thead class="table-dark">
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll" class="visitor-checkbox">
                            </th>
                            <th>Badge</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="visitorsTableBody">
                        <?php foreach ($visitors as $v): ?>
                            <tr data-status="<?= $v['is_checked_in'] > 0 ? 'checked-in' : 'not-checked-in' ?>"
                                data-visitor-id="<?= $v['VisitorID'] ?>">
                                <td>
                                    <input type="checkbox" class="visitor-checkbox visitor-select"
                                           value="<?= $v['VisitorID'] ?>">
                                </td>

                                <td>
                                    <span class="badge-number"><?= htmlspecialchars($v['BadgeNumber'] ?? 'N/A') ?></span>
                                </td>
                                <td><?= htmlspecialchars($v['Name']) ?></td>
                                <td><?= htmlspecialchars($v['Email']) ?></td>
                                <td><?= htmlspecialchars($v['Phone']) ?></td>
                                <td>
            <span class="status-badge <?= $v['is_checked_in'] > 0 ? 'status-checked-in' : 'status-not-checked-in' ?>">
                <?= $v['is_checked_in'] > 0 ? 'Checked In' : 'Not Checked In' ?>
            </span>
                                </td>
                                <td>
                                    <!-- View Details Button -->
                                    <button class="btn btn-info action-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#visitorDetailModal"
                                            onclick="showVisitorDetails(<?= htmlspecialchars(json_encode($v)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <!-- Badge Print Button - Only show if visitor has a badge number -->
                                    <?php if (!empty($v['BadgeNumber']) && $v['BadgeNumber'] !== 'N/A'): ?>
                                        <button class="btn btn-secondary action-btn"
                                                onclick="printBadge('<?= htmlspecialchars($v['BadgeNumber']) ?>', '<?= htmlspecialchars($v['Name']) ?>', <?= $v['VisitorID'] ?>)"
                                                title="Print Badge">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($v['is_checked_in'] > 0): ?>
                                        <!-- Check Out Button -->
                                        <button type="button" class="btn btn-danger action-btn"
                                                onclick="handleCheckOut(<?= $v['VisitorID'] ?>, this)">
                                            <i class="fas fa-sign-out-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="fas fa-file-pdf me-2"></i>Generate Report
                    </button>

                    <!-- No Results Message -->
                    <div id="noResults" class="text-center py-5" style="display: none;">
                        <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                        <h5>No visitors found</h5>
                        <p class="text-muted">Try adjusting your search criteria or filters</p>
                    </div>
                </div>

            <!-- Check In Modal -->
            <div class="modal fade" id="quickCheckinModal" tabindex="-1" aria-labelledby="checkInModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" id="quickCheckinForm" action="process_visit.php" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Check In Visitor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body row g-2">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Visitor Name *</label>
                                <input class="form-control form-control-lg" name="name" id="checkinName"
                                       placeholder="Enter visitor name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email *</label>
                                <input class="form-control form-control-lg" name="email" id="checkinEmail"
                                       type="email" placeholder="Enter email address" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Phone</label>
                                    <input class="form-control" name="phone" id="checkinPhone"
                                           placeholder="Phone number">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Purpose</label>
                                    <input class="form-control" name="visit_purpose" placeholder="Purpose of Visit">
                                </div>
                            </div>
                            <input type="hidden" name="action" value="check_in">
                            <input type="hidden" name="visitor_id" id="existingVisitorId">
                        </div>

                        <div class="modal-footer">
                            <button type="submit" name="action" id ="#quickCheckinForm" value="check_in" class="btn btn-success">Check In</button>
                            <button type="button" class="btn btn-primary" id="modal-edit-btn">
                                <i class="fas fa-edit me-2"></i>Edit Details
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Print Badge Modal-->
            </div>
                <div class="modal fade badge-print-modal" id="badgePrintModal" tabindex="-1" aria-labelledby="badgePrintModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title text-white" id="badgePrintModalLabel">
                                    <i class="fas fa-id-badge me-2"></i>Print Badge
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Badge Preview -->
                                <div class="badge-print-content">
                                    <div class="badge-preview">
                                        <div class="badge-title">VISITOR BADGE</div>
                                        <div class="badge-content">
                                            <div class="badge-logo">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div class="badge-name" id="print-visitor-name">Visitor Name</div>
                                            <div class="badge-number-display" id="print-badge-number">000000</div>
                                            <div class="badge-footer">
                                                <div><strong>Valid for today only</strong></div>
                                                <div id="print-date"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Print Options -->
                                <div class="print-options">
                                    <h6 class="mb-3"><i class="fas fa-cog me-2"></i>Print Options</h6>

                                    <div class="print-option-item">
                                        <label class="form-label">Number of copies:</label>
                                        <select class="form-select form-select-sm" id="print-copies">
                                            <option value="1" selected>1 copy</option>
                                            <option value="2">2 copies</option>
                                            <option value="3">3 copies</option>
                                        </select>
                                    </div>

                                    <div class="print-option-item">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="include-photo" checked>
                                            <label class="form-check-label" for="include-photo">
                                                Include photo placeholder
                                            </label>
                                        </div>
                                    </div>

                                    <div class="print-option-item">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="include-qr" checked>
                                            <label class="form-check-label" for="include-qr">
                                                Exclude span of validity
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Ensure your printer is set to landscape orientation for best results. Standard badge size is 3.5" x 2.2".
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </button>
                                <button type="button" class="btn btn-primary" id="confirmPrintBadge">
                                    <i class="fas fa-print me-2"></i>Print Badge
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Report Generation Modal -->
                <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generate Visitor Report</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="reportForm" method="GET" action="generate_visitor_logs.php" target="_blank">
                                    <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date"
                                               value="<?= date('Y-m-01') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date"
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" name="report_type">
                                            <option value="detailed">Detailed Report</option>
                                            <option value="summary">Summary Report</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" form="reportForm" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- Visitor Details Modal -->
            <div class="modal fade visitor-detail-modal" id="visitorDetailModal" tabindex="-1" aria-labelledby="visitorDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="visitorDetailModalLabel">
                                <i class="fas fa-user-circle me-2"></i>Visitor Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-id-badge me-2 text-primary"></i>Badge Number
                                        </div>
                                        <div class="detail-value" id="modal-badge"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-user me-2 text-primary"></i>Full Name
                                        </div>
                                        <div class="detail-value" id="modal-name"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-envelope me-2 text-primary"></i>Email Address
                                        </div>
                                        <div class="detail-value" id="modal-email"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-phone me-2 text-primary"></i>Phone Number
                                        </div>
                                        <div class="detail-value" id="modal-phone"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-id-card me-2 text-primary"></i>ID Type
                                        </div>
                                        <div class="detail-value" id="modal-id-type"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-hashtag me-2 text-primary"></i>ID Number
                                        </div>
                                        <div class="detail-value" id="modal-id-number"></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">
                                            <i class="fas fa-info-circle me-2 text-primary"></i>Current Status
                                        </div>
                                        <div class="detail-value" id="modal-status"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Include Sneat JS files like host_dashboard does -->
<script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
<script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
<script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../Sneat/assets/vendor/js/menu.js"></script>
<script src="../../Sneat/assets/js/main.js"></script>

<script>
    // Global variables
    let currentFilter = 'all';
    let currentSearch = '';
    let visitorSuggestions = <?= json_encode($visitor_suggestions) ?>;
    let selectedVisitors = new Set();
    let activeAutocompleteIndex = -1;

    $(document).ready(function() {
        initializeApp();
        setupEventListeners();
        setupKeyboardShortcuts();
        updateCounts();
    });

    function initializeApp() {
        // Restore sidebar state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            $('html').addClass('layout-menu-collapsed');
            $('#toggleIcon').removeClass('bx-chevron-left').addClass('bx-chevron-right');
        }

        // Setup autocomplete
        setupSmartSearch();

        // Auto-focus search on load
        setTimeout(() => $('#smartSearch').focus(), 500);
    }

    function setupEventListeners() {
        // Sidebar toggle
        $('#sidebarToggle').on('click', handleSidebarToggle);

        // Search functionality
        $('#smartSearch').on('input', debounce(handleSmartSearch, 300));
        $('#smartSearch').on('keydown', handleSearchKeydown);

        // Filter buttons
        $('.filter-btn').on('click', handleFilterClick);

        // Quick action buttons
        $('#quickCheckinBtn').on('click', () => $('#quickCheckinModal').modal('show'));
        $('#bulkCheckoutBtn').on('click', toggleBulkMode);

        // Bulk actions
        $('#selectAll').on('change', handleSelectAll);
        $(document).on('change', '.visitor-select', handleVisitorSelect);
        $('#bulkCheckinConfirm').on('click', () => performBulkAction('check_in'));
        $('#bulkCheckoutConfirm').on('click', () => performBulkAction('check_out'));
        $('#clearSelection').on('click', clearSelection);

        // Form submission
        $('#quickCheckinForm').on('submit', handleQuickCheckin);

        // Click outside to close autocomplete
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.smart-search-container').length) {
                hideAutocomplete();
            }
        });
    }

    function setupKeyboardShortcuts() {
        $(document).keydown(function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'i':
                        e.preventDefault();
                        $('#quickCheckinModal').modal('show');
                        break;
                    case 'o':
                        e.preventDefault();
                        toggleBulkMode();
                        break;
                    case 'f':
                        e.preventDefault();
                        $('#smartSearch').focus();
                        break;
                }
            }

            // Escape to clear search/close modals
            if (e.key === 'Escape') {
                if ($('#smartSearch').is(':focus')) {
                    $('#smartSearch').val('').trigger('input');
                    hideAutocomplete();
                }
            }
        });
    }

    function setupSmartSearch() {
        $('#smartSearch').attr('autocomplete', 'off');
    }

    function handleSmartSearch() {
        const query = $('#smartSearch').val();
        currentSearch = query.toLowerCase();

        if (query.length >= 2) {
            showAutocomplete(query);
        } else {
            hideAutocomplete();
        }

        filterAndSearch();
    }

    function showAutocomplete(query) {
        const matches = visitorSuggestions.filter(visitor =>
            visitor.Name.toLowerCase().includes(query.toLowerCase()) ||
            visitor.Email.toLowerCase().includes(query.toLowerCase()) ||
            (visitor.Phone && visitor.Phone.includes(query))
        ).slice(0, 8); // Limit to 8 results

        if (matches.length === 0) {
            hideAutocomplete();
            return;
        }

        let html = '';
        matches.forEach((visitor, index) => {
            html += `
                <div class="autocomplete-item" data-index="${index}" onclick="selectAutocompleteItem(${JSON.stringify(visitor).replace(/"/g, '&quot;')})">
                    <div class="fw-bold">${highlightMatch(visitor.Name, query)}</div>
                    <div class="text-muted small">${highlightMatch(visitor.Email, query)}</div>
                    ${visitor.Phone ? `<div class="text-muted small">${highlightMatch(visitor.Phone, query)}</div>` : ''}
                </div>
            `;
        });

        $('#autocompleteDropdown').html(html).show();
        activeAutocompleteIndex = -1;
    }

    function hideAutocomplete() {
        $('#autocompleteDropdown').hide();
        activeAutocompleteIndex = -1;
    }

    function highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    function selectAutocompleteItem(visitor) {
        $('#checkinName').val(visitor.Name);
        $('#checkinEmail').val(visitor.Email);
        $('#checkinPhone').val(visitor.Phone || '');
        $('#existingVisitorId').val(''); // Will be determined on server side

        hideAutocomplete();
        $('#smartSearch').val(visitor.Name);
        $('#quickCheckinModal').modal('show');
    }

    function handleSearchKeydown(e) {
        const $dropdown = $('#autocompleteDropdown');
        const $items = $dropdown.find('.autocomplete-item');

        if (!$dropdown.is(':visible')) return;

        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeAutocompleteIndex = Math.min(activeAutocompleteIndex + 1, $items.length - 1);
                updateAutocompleteSelection($items);
                break;

            case 'ArrowUp':
                e.preventDefault();
                activeAutocompleteIndex = Math.max(activeAutocompleteIndex - 1, -1);
                updateAutocompleteSelection($items);
                break;

            case 'Enter':
                e.preventDefault();
                if (activeAutocompleteIndex >= 0) {
                    $items.eq(activeAutocompleteIndex).click();
                }
                break;

            case 'Escape':
                hideAutocomplete();
                break;
        }
    }

    function updateAutocompleteSelection($items) {
        $items.removeClass('active');
        if (activeAutocompleteIndex >= 0) {
            $items.eq(activeAutocompleteIndex).addClass('active');
        }
    }

    function handleFilterClick() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        filterAndSearch();
    }

    function filterAndSearch() {
        const rows = $('#visitorsTableBody tr');
        let visibleCount = 0;

        rows.each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const text = $row.text().toLowerCase();

            // Check filter
            let matchesFilter = false;
            if (currentFilter === 'all') {
                matchesFilter = true;
            } else if (currentFilter === 'checked-in') {
                matchesFilter = status === 'checked-in';
            } else if (currentFilter === 'not-checked-in') {
                matchesFilter = status === 'not-checked-in';
            } else if (currentFilter === 'checked-out') {
                matchesFilter = status === 'checked-out';
            }

            // Check search
            const matchesSearch = currentSearch === '' || text.includes(currentSearch);

            // Show/hide row with animation
            if (matchesFilter && matchesSearch) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        // Show/hide no results message
        if (visibleCount === 0) {
            $('#noResults').show();
        } else {
            $('#noResults').hide();
        }

        updateCounts();
    }

    function updateCounts() {
        const allRows = $('#visitorsTableBody tr');
        const checkedInRows = $('#visitorsTableBody tr[data-status="checked-in"]');
        const notCheckedInRows = $('#visitorsTableBody tr[data-status="not-checked-in"]');
        const checkedOutRows = $('#visitorsTableBody tr[data-status="checked-out"]');

        $('#count-all').text(allRows.length);
        $('#count-checked-in').text(checkedInRows.length);
        $('#count-not-checked-in').text(notCheckedInRows.length);
        $('#count-checked-out').text(checkedOutRows.length);
    }

    function handleSidebarToggle(e) {
        e.preventDefault();
        e.stopPropagation();

        const $html = $('html');
        const $toggleIcon = $('#toggleIcon');

        $(this).css('pointer-events', 'none');
        $html.toggleClass('layout-menu-collapsed');
        const isCollapsed = $html.hasClass('layout-menu-collapsed');

        if (isCollapsed) {
            $toggleIcon.removeClass('bx-chevron-left').addClass('bx-chevron-right');
        } else {
            $toggleIcon.removeClass('bx-chevron-right').addClass('bx-chevron-left');
        }

        localStorage.setItem('sidebarCollapsed', isCollapsed);

        setTimeout(() => {
            $(this).css('pointer-events', 'auto');
        }, 300);
    }

    // Quick Check-in Function
    function quickCheckIn(visitorId, visitorName) {
        if (!confirm(`Check in ${visitorName}?`)) return;

        showLoading();

        $.ajax({
            url: 'process_visit.php',
            method: 'POST',
            data: {
                action: 'quick_check_in',
                visitor_id: visitorId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    updateVisitorRow(visitorId, 'checked-in');
                    showNotification(response.message, 'success');
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Error checking in visitor', 'error');
            }
        });
    }

    // Quick Check-out Function
    function handleCheckOut(visitorId, buttonElement) {
        const visitorName = $(buttonElement).closest('tr').find('td').eq(2).text();
        if (!confirm(`Check out ${visitorName}?`)) return;

        const $button = $(buttonElement);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking out...');

        $.ajax({
            url: 'process_visit.php',
            method: 'POST',
            data: {
                action: 'check_out',
                visitor_id: visitorId
            },
            success: function(response) {
                if (response.success) {
                    updateVisitorRow(visitorId, 'checked-out');
                    showNotification(response.message, 'success');
                } else {
                    showNotification(response.message, 'error');
                    $button.prop('disabled', false).html('<i class="fas fa-sign-out-alt"></i> Check Out');
                }
            },
            error: function() {
                showNotification('Error checking out visitor', 'error');
                $button.prop('disabled', false).html('<i class="fas fa-sign-out-alt"></i> Check Out');
            }
        });
    }

    function updateVisitorRow(visitorId, newStatus) {
        const $row = $(`tr[data-visitor-id="${visitorId}"]`);
        $row.attr('data-status', newStatus);

        const $statusBadge = $row.find('.status-badge');
        const $actionTd = $row.find('td').last();

        // Update status badge
        $statusBadge.removeClass('status-checked-in status-not-checked-in status-checked-out');

        if (newStatus === 'checked-in') {
            $statusBadge.addClass('status-checked-in').text('Checked In');
            // Replace check-in button with check-out button
            const visitorId = $row.data('visitor-id');
            const checkoutBtn = `<button type="button" class="btn btn-danger action-btn btn-sm" onclick="quickCheckOut(${visitorId}, this)">
                                    <i class="fas fa-sign-out-alt"></i>
                                 </button>`;
            $actionTd.find('.btn-success').replaceWith(checkoutBtn);
        } else if (newStatus === 'checked-out') {
            $statusBadge.addClass('status-checked-out').text('Checked Out');
            // Remove check-out button
            $actionTd.find('.btn-danger').remove();
        } else if (newStatus === 'not-checked-in') {
            $statusBadge.addClass('status-not-checked-in').text('Not Checked In');
            // Add check-in button
            const visitorId = $row.data('visitor-id');
            const visitorName = $row.find('td').eq(2).text();
            const checkinBtn = `<button type="button" class="btn btn-success action-btn btn-sm" onclick="quickCheckIn(${visitorId}, '${visitorName}')">
                                   <i class="fas fa-sign-in-alt"></i>
                                </button>`;
            $actionTd.find('.btn-info').after(checkinBtn);
        }

        updateCounts();

        // Add visual feedback
        $row.addClass('table-success');
        setTimeout(() => $row.removeClass('table-success'), 2000);
    }

    // Bulk Operations
    function toggleBulkMode() {
        const $bulkActions = $('#bulkActions');
        if ($bulkActions.is(':visible')) {
            $bulkActions.slideUp();
            clearSelection();
        } else {
            $bulkActions.slideDown();
        }
    }

    function handleSelectAll() {
        const isChecked = $('#selectAll').prop('checked');
        $('.visitor-select:visible').prop('checked', isChecked);
        updateSelectedVisitors();
    }

    function handleVisitorSelect() {
        updateSelectedVisitors();
    }

    function updateSelectedVisitors() {
        selectedVisitors.clear();
        $('.visitor-select:checked').each(function() {
            selectedVisitors.add($(this).val());
        });

        $('#selectedCount').text(selectedVisitors.size);

        // Update select all checkbox
        const totalVisible = $('.visitor-select:visible').length;
        const totalSelected = $('.visitor-select:visible:checked').length;
        $('#selectAll').prop('checked', totalVisible > 0 && totalSelected === totalVisible);
    }

    function clearSelection() {
        selectedVisitors.clear();
        $('.visitor-select, #selectAll').prop('checked', false);
        $('#selectedCount').text('0');
        $('#bulkActions').slideUp();
    }

    function performBulkAction(action) {
        if (selectedVisitors.size === 0) {
            showNotification('Please select visitors first', 'error');
            return;
        }

        const actionText = action === 'check_in' ? 'check in' : 'check out';
        if (!confirm(`${actionText} ${selectedVisitors.size} selected visitors?`)) return;

        showLoading();

        $.ajax({
            url: 'process_visit.php',
            method: 'POST',
            data: {
                action: `bulk_${action}`,
                visitor_ids: Array.from(selectedVisitors)
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // Update rows
                    response.updated_visitors.forEach(visitorId => {
                        updateVisitorRow(visitorId, action === 'check_in' ? 'checked-in' : 'checked-out');
                    });
                    showNotification(`Successfully ${actionText} ${response.updated_visitors.length} visitors`, 'success');
                    clearSelection();
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification(`Error performing bulk ${actionText}`, 'error');
            }
        });
    }

    // Form Handlers
    function handleQuickCheckin(e) {
        e.preventDefault();

        const formData = new FormData(this);
        showLoading();

        $.ajax({
            url: 'process_visit.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    $('#quickCheckinModal').modal('hide');
                    showNotification(response.message, 'success');
                    // Optionally reload the page or update the table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Error processing check-in', 'error');
            }
        });
    }

    // Modal Functions
    function showVisitorDetails(visitor) {
        $('#modal-badge').text(visitor.BadgeNumber || 'N/A');
        $('#modal-name').text(visitor.Name || 'N/A');
        $('#modal-email').text(visitor.Email || 'N/A');
        $('#modal-phone').text(visitor.Phone || 'N/A');

        const isCheckedIn = visitor.is_checked_in > 0;
        const statusHtml = `<span class="status-badge ${isCheckedIn ? 'status-checked-in' : 'status-not-checked-in'}">
            ${isCheckedIn ? 'Checked In' : 'Not Checked In'}
        </span>`;
        $('#modal-status').html(statusHtml);

        const checkinTime = visitor.LastCheckIn || 'Never';
        $('#modal-checkin-time').text(checkinTime !== 'Never' ?
            new Date(checkinTime).toLocaleString() : 'Never');

        $('#modal-purpose').text(visitor.Visit_Purpose || 'Not specified');
    }

    // Utility Functions
    function showLoading() {
        $('#loadingOverlay').show();
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    function showNotification(message, type = 'success') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        const $alert = $(`<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`);

        $('.container-p-y').prepend($alert);

        setTimeout(() => {
            $alert.alert('close');
        }, 5000);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    // Global variables for badge printing
    let currentPrintData = {};

    /**
     * Initialize badge printing functionality
     */
    function printBadge(badgeNumber, visitorName, visitorId) {
        // Store current print data
        currentPrintData = {
            badgeNumber: badgeNumber,
            visitorName: visitorName,
            visitorId: visitorId
        };

        // Populate the badge preview
        updateBadgePreview();

        // Show the print modal
        $('#badgePrintModal').modal('show');
    }

    /**
     * Update the badge preview with current visitor data
     */
    function updateBadgePreview() {
        $('#print-visitor-name').text(currentPrintData.visitorName);
        $('#print-badge-number').text(currentPrintData.badgeNumber);
        $('#print-date').text(new Date().toLocaleDateString());

        // Add photo placeholder if enabled
        updateBadgeFeatures();
    }

    /**
     * Update badge features based on selected options
     */
    function updateBadgeFeatures() {
        const includePhoto = $('#include-photo').is(':checked');
        const includeQR = $('#include-qr').is(':checked');

        // Add or remove photo placeholder
        let photoElement = $('.badge-preview .photo-placeholder');
        if (includePhoto && photoElement.length === 0) {
            $('.badge-logo').after(`
                <div class="photo-placeholder" style="
                    width: 60px;
                    height: 60px;
                    background: #dee2e6;
                    border: 2px dashed #6c757d;
                    border-radius: 50%;
                    margin: 0 auto 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.7rem;
                    color: #6c757d;
                ">PHOTO</div>
            `);
        } else if (!includePhoto && photoElement.length > 0) {
            photoElement.remove();
        }

        // Add or remove QR code
        let qrElement = $('.badge-preview .qr-code');
        if (includeQR && qrElement.length === 0) {
            $('.badge-footer').before(`
                <div class="qr-code" style="
                    width: 40px;
                    height: 40px;
                    background: #212529;
                    margin: 10px auto;
                    position: relative;
                ">
                    <div style="
                        position: absolute;
                        top: 2px; left: 2px; right: 2px; bottom: 2px;
                        background: repeating-conic-gradient(#000 0% 25%, transparent 0% 50%) 50% / 8px 8px;
                    "></div>
                </div>
            `);
        } else if (!includeQR && qrElement.length > 0) {
            qrElement.remove();
        }
    }

    /**
     * Handle the actual printing process
     */
    function handleBadgePrint() {
        const $printButton = $('#confirmPrintBadge');
        const copies = parseInt($('#print-copies').val());

        // Show loading state
        $printButton.addClass('printing')
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Printing...');

        // Log the print action
        logBadgePrint(currentPrintData.visitorId, currentPrintData.badgeNumber, copies);

        // Simulate printing delay
        setTimeout(() => {
            try {
                // Trigger browser print
                window.print();

                // Show success notification
                showNotification(`Badge printed successfully for ${currentPrintData.visitorName}`, 'success');

                // Close modal
                $('#badgePrintModal').modal('hide');

            } catch (error) {
                showNotification('Error occurred while printing badge', 'error');
                console.error('Print error:', error);
            } finally {
                // Reset button state
                $printButton.removeClass('printing')
                    .html('<i class="fas fa-print me-2"></i>Print Badge');
            }
        }, 1000);
    }

    /**
     * Log badge printing activity
     */
    function logBadgePrint(visitorId, badgeNumber, copies) {
        $.ajax({
            url: 'badge_print_log.php', // You'll need to create this endpoint
            method: 'POST',
            data: {
                action: 'log_print',
                visitor_id: visitorId,
                badge_number: badgeNumber,
                copies: copies,
                printed_by: <?= $_SESSION['userID'] ?? 'null' ?>,
                print_time: new Date().toISOString()
            },
            success: function(response) {
                console.log('Badge print logged successfully');
            },
            error: function() {
                console.warn('Failed to log badge print');
            }
        });
    }

    /**
     * Bulk print badges for selected visitors
     */
    function printSelectedBadges() {
        const selectedVisitors = [];
        $('.visitor-select:checked').each(function() {
            const $row = $(this).closest('tr');
            const visitorId = $(this).val();
            const badgeNumber = $row.find('.badge-number').text().trim();
            const visitorName = $row.find('td').eq(2).text().trim();

            if (badgeNumber && badgeNumber !== 'N/A') {
                selectedVisitors.push({
                    id: visitorId,
                    name: visitorName,
                    badge: badgeNumber
                });
            }
        });

        if (selectedVisitors.length === 0) {
            showNotification('Please select visitors with badge numbers', 'error');
            return;
        }

        if (confirm(`Print badges for ${selectedVisitors.length} selected visitors?`)) {
            selectedVisitors.forEach(visitor => {
                logBadgePrint(visitor.id, visitor.badge, 1);
            });

            // Here you could implement batch printing logic
            showNotification(`Printing ${selectedVisitors.length} badges...`, 'success');

            // For now, just print each one
            selectedVisitors.forEach((visitor, index) => {
                setTimeout(() => {
                    currentPrintData = {
                        badgeNumber: visitor.badge,
                        visitorName: visitor.name,
                        visitorId: visitor.id
                    };
                    updateBadgePreview();
                    window.print();
                }, index * 1000); // Stagger prints by 1 second
            });
        }
    }

    // Event listeners
    $(document).ready(function() {
        // Print options change handlers
        $('#include-photo, #include-qr').on('change', updateBadgeFeatures);

        // Print confirmation
        $('#confirmPrintBadge').on('click', handleBadgePrint);

        // Add bulk print button to bulk actions if needed
        if ($('#bulkActions .btn-group').length === 0) {
            $('#bulkActions .d-flex > div').append(`
                <button class="btn btn-info me-2" id="bulkPrintBadges" onclick="printSelectedBadges()">
                    <i class="fas fa-print me-2"></i>Print Badges
                </button>
            `);
        }

        // Hide badge print button in bulk actions initially
        $('#bulkPrintBadges').hide();

        // Show/hide bulk print button based on selection
        $(document).on('change', '.visitor-select', function() {
            const selectedWithBadges = $('.visitor-select:checked').filter(function() {
                const badgeNumber = $(this).closest('tr').find('.badge-number').text().trim();
                return badgeNumber && badgeNumber !== 'N/A';
            });

            if (selectedWithBadges.length > 0) {
                $('#bulkPrintBadges').show();
            } else {
                $('#bulkPrintBadges').hide();
            }
        });

        // Handle select all for badge printing
        $('#selectAll').on('change', function() {
            setTimeout(() => {
                $(document).trigger('change', '.visitor-select');
            }, 100);
        });
    });

    // Print media query handling
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing-mode');
    });

    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing-mode');
    });
    // Add to your existing JavaScript
    function generateReport(type) {
        const startDate = $('#reportStartDate').val();
        const endDate = $('#reportEndDate').val();

        if (!startDate || !endDate) {
            showNotification('Please select both start and end dates', 'error');
            return;
        }

        const url = `generate_visitor_logs.php?start_date=${startDate}&end_date=${endDate}&report_type=${type}`;
        window.open(url, '_blank');
    }

    // Add event listeners for quick report buttons
    $('#quickDailyReport').on('click', function() {
        const today = new Date().toISOString().split('T')[0];
        window.open(`generate_visitor_logs.php?start_date=${today}&end_date=${today}`, '_blank');
    });

    $('#quickMonthlyReport').on('click', function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        window.open(`generate_visitor_logs.php?start_date=${firstDay}&end_date=${today.toISOString().split('T')[0]}`, '_blank');
    });
</script>
</body>
</html>