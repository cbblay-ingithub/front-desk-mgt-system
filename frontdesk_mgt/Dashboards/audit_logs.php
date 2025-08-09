<?php

?>
<!DOCTYPE html>
<html lang="en"
      class="layout-wide customizer-hide"
      dir="ltr"
      data-skin="default"
      data-assets-path="../Sneat/assets/"
      data-template="horizontal-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />
    <style>
        html, body {
            overflow-x: hidden !important;
            height: 100vh !important;
        }

        .layout-wrapper {
            overflow: hidden !important;
            height: 100vh !important;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden !important;
        }

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
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
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

        .container-fluid {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            max-width: none;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
        }

        .main-content {
            flex: 1;
            margin-left: 260px; /* Default sidebar width */
            transition: margin-left 0.3s ease;
        }

        .filters {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        body.layout-menu-collapsed .main-content {
            margin-left: 78px; /* Collapsed sidebar width */
        }


        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #555;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #555;
            border: 2px solid #e1e8ed;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }

        .stat-card.total { border-left-color: #3498db; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.failure { border-left-color: #e74c3c; }
        .stat-card.today { border-left-color: #f39c12; }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .logs-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 2px solid #e1e8ed;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .table-header h3 {
            color: #333;
        }

        .export-btn {
            background: #28a745;
            color: white;
        }

        .export-btn:hover {
            background: #218838;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e1e8ed;
            white-space: nowrap;
        }

        .logs-table td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        .logs-table tbody tr:hover {
            background: #f8f9fb;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-failure {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: #e3f2fd;
            color: #1565c0;
        }

        .role-front_desk {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .role-host {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .role-support {
            background: #fff3e0;
            color: #ef6c00;
        }

        .action-type {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .json-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            background: #f8f9fa;
            padding: 0.25rem;
            border-radius: 4px;
            cursor: pointer;
            color: #666;
        }

        .pagination {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e1e8ed;
        }

        .pagination-info {
            color: #666;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e1e8ed;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .page-btn:hover {
            background: #f8f9fa;
        }

        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e1e8ed;
        }

        .close {
            font-size: 2rem;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #333;
        }

        .json-display {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e1e8ed;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'admin-sidebar.php'; ?>
        <div class="layout-content">
            <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base bx bx-menu icon-md"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                    <!-- Page Title -->
                    <div class="navbar-nav align-items-center me-auto">
                        <div class="nav-item">
                            <h4 class="mb-0 fw-bold ms-2">Audit Logs</h4>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="container-fluid container-p-y">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <p class="text-muted">System activity monitoring and compliance tracking</p>
                    </div>
                </div>


            <!-- Filters Section -->
            <div class="filters">
                <div class="filter-group">
                    <label for="userFilter">User ID</label>
                    <input type="number" id="userFilter" placeholder="Enter user ID">
                </div>

                <div class="filter-group">
                    <label for="roleFilter">User Role</label>
                    <select id="roleFilter">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="front_desk">Front Desk</option>
                        <option value="host">Host</option>
                        <option value="support">Support</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="actionFilter">Action Type</label>
                    <select id="actionFilter">
                        <option value="">All Actions</option>
                        <option value="LOGIN">Login</option>
                        <option value="LOGOUT">Logout</option>
                        <option value="USER_CREATE">User Create</option>
                        <option value="USER_UPDATE">User Update</option>
                        <option value="USER_DELETE">User Delete</option>
                        <option value="PASSWORD_CHANGE">Password Change</option>
                        <option value="ROLE_CHANGE">Role Change</option>
                        <option value="CONFIG_UPDATE">Config Update</option>
                        <option value="DATA_EXPORT">Data Export</option>
                        <option value="DATA_IMPORT">Data Import</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="SUCCESS">Success</option>
                        <option value="FAILURE">Failure</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="dateFromFilter">Date From</label>
                    <input type="datetime-local" id="dateFromFilter">
                </div>

                <div class="filter-group">
                    <label for="dateToFilter">Date To</label>
                    <input type="datetime-local" id="dateToFilter">
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats">
                <div class="stat-card total">
                    <div class="stat-value" id="totalLogs">-</div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value" id="successLogs">-</div>
                    <div class="stat-label">Successful Actions</div>
                </div>
                <div class="stat-card failure">
                    <div class="stat-value" id="failureLogs">-</div>
                    <div class="stat-label">Failed Actions</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-value" id="todayLogs">-</div>
                    <div class="stat-label">Today's Activity</div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="logs-table-container">
                <div class="table-header">
                    <h3>Audit Trail</h3>
                    <button class="btn export-btn" onclick="exportLogs()">Export CSV</button>
                </div>

                <div id="errorContainer"></div>

                <div class="table-wrapper">
                    <table class="logs-table">
                        <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>Description</th>
                            <th>Details</th>
                        </tr>
                        </thead>
                        <tbody id="logsTableBody">
                        <tr>
                            <td colspan="10" class="loading">Loading audit logs...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <div class="pagination-info">
                        Showing <span id="pageInfo">0-0 of 0</span> logs
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn" id="prevBtn" onclick="changePage(-1)">← Previous</button>
                        <span id="pageNumbers"></span>
                        <button class="page-btn" id="nextBtn" onclick="changePage(1)">Next →</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Modal for JSON details -->
        <div id="jsonModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Log Details</h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div id="modalBody">
                    <div style="margin-bottom: 1rem;">
                        <strong>Old Value:</strong>
                        <div id="oldValue" class="json-display"></div>
                    </div>
                    <div>
                        <strong>New Value:</strong>
                        <div id="newValue" class="json-display"></div>
                    </div>
                </div>
            </div>
        </div>
    <script src="../../Sneat/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../Sneat/assets/vendor/libs/popper/popper.js"></script>
    <script src="../../Sneat/assets/vendor/js/bootstrap.js"></script>
    <script src="../../Sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../Sneat/assets/vendor/js/menu.js"></script>
    <script src="../../Sneat/assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the menu
        if (typeof Menu !== 'undefined') {
            Menu.init();
        }

        // Load logs and stats
        loadLogs();
        loadStats();

        // Sidebar toggle functionality
        const layoutMenuToggle = document.querySelector('.layout-menu-toggle');
        if (layoutMenuToggle) {
            layoutMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const html = document.documentElement;
                const sidebar = document.getElementById('layout-menu');

                html.classList.toggle('layout-menu-collapsed');
                const isCollapsed = html.classList.contains('layout-menu-collapsed');

                if (isCollapsed) {
                    sidebar.style.width = '78px';
                    sidebar.style.minWidth = '78px';
                    sidebar.style.maxWidth = '78px';
                } else {
                    sidebar.style.width = '260px';
                    sidebar.style.minWidth = '260px';
                    sidebar.style.maxWidth = '260px';
                }

                localStorage.setItem('layoutMenuCollapsed', isCollapsed);
            });
        }

        // Restore sidebar state
        const isCollapsed = localStorage.getItem('layoutMenuCollapsed') === 'true';
        if (isCollapsed) {
            document.documentElement.classList.add('layout-menu-collapsed');
            document.getElementById('layout-menu').style.width = '78px';
            document.getElementById('layout-menu').style.minWidth = '78px';
            document.getElementById('layout-menu').style.maxWidth = '78px';
        }
    });
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {};
    const logsPerPage = 25;

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadLogs();
        loadStats();
    });

    // Apply filters
    function applyFilters() {
        currentFilters = {
            user_id: document.getElementById('userFilter').value,
            user_role: document.getElementById('roleFilter').value,
            action_type: document.getElementById('actionFilter').value,
            status: document.getElementById('statusFilter').value,
            date_from: document.getElementById('dateFromFilter').value,
            date_to: document.getElementById('dateToFilter').value
        };

        // Remove empty filters
        Object.keys(currentFilters).forEach(key => {
            if (!currentFilters[key]) {
                delete currentFilters[key];
            }
        });

        currentPage = 1;
        loadLogs();
        loadStats();
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('userFilter').value = '';
        document.getElementById('roleFilter').value = '';
        document.getElementById('actionFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('dateFromFilter').value = '';
        document.getElementById('dateToFilter').value = '';

        currentFilters = {};
        currentPage = 1;
        loadLogs();
        loadStats();
    }

    // Load audit logs
    async function loadLogs() {
        try {
            const params = new URLSearchParams({
                page: currentPage,
                limit: logsPerPage,
                ...currentFilters
            });

            const response = await fetch(`audit_backend.php?${params}`);
            const data = await response.json();

            if (data.success) {
                displayLogs(data.logs);
                updatePagination(data.pagination);
            } else {
                showError(data.message || 'Failed to load audit logs');
            }
        } catch (error) {
            console.error('Error loading logs:', error);
            showError('Error loading audit logs. Please try again.');
        }
    }

    // Load statistics
    async function loadStats() {
        try {
            const params = new URLSearchParams(currentFilters);
            const response = await fetch(`audit_stats.php?${params}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('totalLogs').textContent = data.stats.total.toLocaleString();
                document.getElementById('successLogs').textContent = data.stats.success.toLocaleString();
                document.getElementById('failureLogs').textContent = data.stats.failure.toLocaleString();
                document.getElementById('todayLogs').textContent = data.stats.today.toLocaleString();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Display logs in table
    function displayLogs(logs) {
        const tbody = document.getElementById('logsTableBody');

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 2rem; color: #666;">No audit logs found matching your criteria.</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => `
                <tr>
                    <td><code>${log.log_id}</code></td>
                    <td>${formatDateTime(log.created_at)}</td>
                    <td>${log.user_id}</td>
                    <td><span class="role-badge role-${log.user_role}">${log.user_role}</span></td>
                    <td><span class="action-type">${log.action_type}</span></td>
                    <td>${log.action_category}</td>
                    <td><span class="status-badge status-${log.status.toLowerCase()}">${log.status}</span></td>
                    <td><code>${log.ip_address}</code></td>
                    <td style="max-width: 300px;">${log.description || '-'}</td>
                    <td>
                        ${(log.old_value || log.new_value) ?
            `<button class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="showLogDetails('${log.log_id}', ${escapeJson(log.old_value)}, ${escapeJson(log.new_value)})">View Details</button>`
            : '-'
        }
                    </td>
                </tr>
            `).join('');
    }

    // Update pagination controls
    function updatePagination(pagination) {
        totalPages = pagination.total_pages;
        currentPage = pagination.current_page;

        document.getElementById('pageInfo').textContent =
            `${pagination.start}-${pagination.end} of ${pagination.total}`;

        document.getElementById('prevBtn').disabled = currentPage <= 1;
        document.getElementById('nextBtn').disabled = currentPage >= totalPages;

        // Generate page numbers
        let pageNumbers = '';
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            pageNumbers += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }

        document.getElementById('pageNumbers').innerHTML = pageNumbers;
    }

    // Change page
    function changePage(direction) {
        const newPage = currentPage + direction;
        if (newPage >= 1 && newPage <= totalPages) {
            currentPage = newPage;
            loadLogs();
        }
    }

    // Go to specific page
    function goToPage(page) {
        currentPage = page;
        loadLogs();
    }

    // Show log details modal
    function showLogDetails(logId, oldValue, newValue) {
        document.getElementById('oldValue').textContent = oldValue ? JSON.stringify(JSON.parse(oldValue), null, 2) : 'No previous value';
        document.getElementById('newValue').textContent = newValue ? JSON.stringify(JSON.parse(newValue), null, 2) : 'No new value';
        document.getElementById('jsonModal').style.display = 'block';
    }

    // Close modal
    function closeModal() {
        document.getElementById('jsonModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('jsonModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Export logs to CSV
    async function exportLogs() {
        try {
            const params = new URLSearchParams({
                export: 'csv',
                ...currentFilters
            });

            const response = await fetch(`audit_logs_export.php?${params}`);
            const blob = await response.blob();

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `audit_logs_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Error exporting logs:', error);
            showError('Error exporting logs. Please try again.');
        }
    }

    // Utility functions
    function formatDateTime(dateTime) {
        return new Date(dateTime).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    function escapeJson(jsonString) {
        if (!jsonString) return 'null';
        return "'" + jsonString.replace(/'/g, "\\'").replace(/\n/g, '\\n') + "'";
    }

    function showError(message) {
        const errorContainer = document.getElementById('errorContainer');
        errorContainer.innerHTML = `<div class="error">${message}</div>`;
        setTimeout(() => {
            errorContainer.innerHTML = '';
        }, 5000);
    }
</script>
</body>
</html>