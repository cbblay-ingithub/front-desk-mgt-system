<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<meta charset="utf-8" />
<meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
<link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
<link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

<style>
    /* Enhanced sidebar toggle styles - positioned on edge at brand level */
    .sidebar-toggle-enhanced {
        position: fixed !important;
        top: 32px; /* Aligned with brand/header area */
        left: 260px; /* Default sidebar width */
        transform: translateX(-50%);
        z-index: 1050;
        width: 24px;
        height: 24px;
        background: var(--bs-primary, #696cff) !important;
        border: none !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(105, 108, 255, 0.3) !important;
        transition: all 0.3s ease !important;
        cursor: pointer !important;
    }

    .sidebar-toggle-enhanced:hover {
        background: var(--bs-primary-dark, #5f61e6) !important;
        transform: translateX(-50%) scale(1.1) !important;
        box-shadow: 0 4px 12px rgba(105, 108, 255, 0.4) !important;
    }

    .sidebar-toggle-enhanced i {
        font-size: 12px !important;
        transition: transform 0.3s ease !important;
    }

    /* Adjust position when sidebar is collapsed */
    .layout-menu-collapsed .sidebar-toggle-enhanced {
        left: 78px; /* Collapsed sidebar width */
    }

    /* Hide the original toggle when using enhanced version */
    .layout-menu-toggle.menu-link {
        display: none !important;
    }

    /* Ensure sidebar content adjusts when collapsed */
    .layout-menu-collapsed .app-brand-text {
        display: none;
    }

    .layout-menu-collapsed .menu-inner {
        padding-left: 0;
    }

    /* Enhanced brand area for collapsed state */
    .app-brand.demo {
        position: relative;
        padding: 1rem;
        justify-content: center;
    }

    .layout-menu-collapsed .app-brand.demo {
        padding: 1rem 0.5rem;
    }

    /* Menu item adjustments for collapsed state */
    .layout-menu-collapsed .menu-item .menu-link {
        justify-content: center;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .layout-menu-collapsed .menu-item .menu-link div {
        display: none;
    }

    /* Tooltip styles for collapsed menu items */
    .layout-menu-collapsed .menu-item .menu-link {
        position: relative;
    }

    .layout-menu-collapsed .menu-item .menu-link:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
        margin-left: 8px;
    }
    /* ========== LOGOUT MODAL STYLES ========== */
    .logout-modal-overlay {
        display: none !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background: rgba(0, 0, 0, 0.6) !important;
        backdrop-filter: blur(4px) !important;
        z-index: 9999 !important; /* Very high z-index */
        animation: fadeIn 0.3s ease;
    }

    .logout-modal-overlay.show {
        display: block !important;
    }

    .logout-modal {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        background: white !important;
        border-radius: 12px !important;
        padding: 32px !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
        max-width: 400px !important;
        width: 90% !important;
        text-align: center !important;
        animation: slideIn 0.3s ease;
    }

    .logout-modal-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .logout-modal-title {
        font-size: 20px;
        font-weight: 600;
        color: var(--bs-heading-color, #5d596c);
        margin: 0 0 12px 0;
        font-family: 'Public Sans', sans-serif;
    }

    .logout-modal-message {
        font-size: 14px;
        color: var(--bs-body-color, #6f6b7d);
        margin: 0 0 28px 0;
        line-height: 1.5;
        font-family: 'Public Sans', sans-serif;
    }

    .logout-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .logout-btn-cancel {
        background: var(--bs-gray-100, #f5f5f9) !important;
        color: var(--bs-body-color, #6f6b7d) !important;
        border: 1px solid var(--bs-border-color, #d9dee3) !important;
        padding: 10px 20px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        min-width: 90px !important;
        font-family: 'Public Sans', sans-serif !important;
    }

    .logout-btn-cancel:hover {
        background: var(--bs-gray-200, #eeedf2) !important;
        transform: translateY(-1px) !important;
    }

    .logout-btn-confirm {
        background: var(--bs-danger, #ff3e1d) !important;
        color: white !important;
        border: 1px solid var(--bs-danger, #ff3e1d) !important;
        padding: 10px 20px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        min-width: 90px !important;
        font-family: 'Public Sans', sans-serif !important;
    }

    .logout-btn-confirm:hover {
        background: #e6381a !important;
        border-color: #e6381a !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(255, 62, 29, 0.3) !important;
    }

    .logout-btn-confirm:disabled {
        opacity: 0.6 !important;
        cursor: not-allowed !important;
        transform: none !important;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
            scale: 0.95;
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
            scale: 1;
        }
    }

    /* Mobile responsive */
    @media (max-width: 576px) {
        .logout-modal {
            margin: 20px !important;
            padding: 24px !important;
            width: calc(100% - 40px) !important;
        }

        .logout-modal-actions {
            flex-direction: column;
        }

        .logout-btn-cancel, .logout-btn-confirm {
            width: 100% !important;
        }
    }

    /* Prevent interaction with sidebar when modal is open */
    body.logout-modal-open {
        overflow: hidden !important;
    }

    body.logout-modal-open .layout-menu {
        pointer-events: none !important;
    }
</style>

<!-- Enhanced Toggle Button (Positioned on sidebar edge) -->
<button class="sidebar-toggle-enhanced" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="bx bx-chevron-left" id="toggleIcon"></i>
</button>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <!-- Brand/Header -->
    <div class="app-brand demo">
        <a href="admin-dashboard.php"
           class="app-brand-link d-flex align-items-center flex-nowrap">
    <span class="app-brand-logo demo">
      <i class="bx bxs-dashboard text-primary" style="font-size:25px;"></i>
    </span>
            <span class="app-brand-text demo menu-text fw-bold ms-1 text-nowrap">
      Admin Panel
    </span>
        </a>
    </div>

    <!-- Menu Shadow -->
    <div class="menu-inner-shadow"></div>

    <!-- Menu Items -->
    <ul class="menu-inner py-1">
        <!-- Dashboard -->
        <li class="menu-item <?= ($currentPage == 'admin-dashboard.php') ? 'active' : '' ?>">
            <a href="admin-dashboard.php" class="menu-link" data-tooltip="Dashboard">
                <i class="menu-icon icon-base bx bx-home-smile"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>

        <!-- User Management -->
        <li class="menu-item <?= ($currentPage == 'user_management.php') ? 'active' : '' ?>">
            <a href="user_management.php" class="menu-link" data-tooltip="User Management">
                <i class="menu-icon icon-base bx bx-user"></i>
                <div data-i18n="User Management">User Management</div>
            </a>
        </li>

        <!-- Reporting -->
        <li class="menu-item <?= ($currentPage == 'admin-reports.php') ? 'active' : '' ?>">
            <a href="admin-reports.php" class="menu-link" data-tooltip="Reporting">
                <i class="menu-icon icon-base bx bx-bar-chart-alt"></i>
                <div data-i18n="Reporting">Reports</div>
            </a>
        </li>

        <!-- Lost & Found -->
        <li class="menu-item <?= ($currentPage == 'audit_logs.php') ? 'active' : '' ?>">
            <a href="audit_logs.php" class="menu-link" data-tooltip="Audit Logs">
                <i class="menu-icon icon-base bx bx-sitemap"></i>
                <div data-i18n="Lost & Found">Audit Logs</div>
            </a>
        </li>

        <!-- Settings -->
        <li class="menu-item <?= ($currentPage == 'settings.php') ? 'active' : '' ?>">
            <a href="settings.php" class="menu-link" data-tooltip="Settings">
                <i class="menu-icon icon-base bx bx-cog"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
        </li>

        <!-- Logout -->
        <<li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" data-tooltip="Logout" onclick="showLogoutModal()">
                <i class="menu-icon icon-base bx bx-power-off"></i>
                <div data-i18n="Logout">Logout</div>
            </a>
        </li>
    </ul>
</aside>
<div id="logoutModal" class="logout-modal-overlay" onclick="hideLogoutModal(event)">
    <div class="logout-modal">
        <div class="logout-modal-icon">
            <i class="bx bx-power-off" style="color: white;"></i>
        </div>
        <h3 class="logout-modal-title">Confirm Logout</h3>
        <p class="logout-modal-message">
            Are you sure you want to log out?<br>
            <small>You will be redirected to the login page.</small>
        </p>
        <div class="logout-modal-actions">
            <button class="logout-btn-cancel" onclick="hideLogoutModal()">
                Cancel
            </button>
            <button class="logout-btn-confirm" onclick="confirmLogout()">
                Yes, Logout
            </button>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('layout-menu');
        const toggleIcon = document.getElementById('toggleIcon');
        const body = document.body;

        // Toggle collapsed class
        sidebar.classList.toggle('layout-menu-collapsed');
        body.classList.toggle('layout-menu-collapsed');

        // Update icon - arrow points left when expanded, right when collapsed
        if (sidebar.classList.contains('layout-menu-collapsed')) {
            toggleIcon.className = 'bx bx-chevron-right';
        } else {
            toggleIcon.className = 'bx bx-chevron-left';
        }

        // Store state in localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('layout-menu-collapsed'));
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        const sidebar = document.getElementById('layout-menu');
        const toggleIcon = document.getElementById('toggleIcon');
        const body = document.body;

        if (isCollapsed) {
            sidebar.classList.add('layout-menu-collapsed');
            body.classList.add('layout-menu-collapsed');
            toggleIcon.className = 'bx bx-chevron-right';
        }
    });
    // ========== LOGOUT MODAL FUNCTIONS ==========
    function showLogoutModal() {
        console.log('showLogoutModal called');

        try {
            const modal = document.getElementById('logoutModal');
            console.log('Modal element found:', modal);

            if (modal) {
                // Force show modal with multiple methods
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('logout-modal-open');

                console.log('Modal should now be visible');
                console.log('Modal display style:', modal.style.display);
                console.log('Modal computed style:', window.getComputedStyle(modal).display);

                // Focus management
                setTimeout(() => {
                    const cancelBtn = document.querySelector('.logout-btn-cancel');
                    if (cancelBtn) {
                        cancelBtn.focus();
                    }
                }, 200);
            } else {
                console.error('Modal element not found!');
                // Fallback
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '../Logout.php';
                }
            }
        } catch (error) {
            console.error('Error in showLogoutModal:', error);
            // Fallback
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../Logout.php';
            }
        }

        return false; // Prevent any default action
    }

    function hideLogoutModal(event) {
        console.log('hideLogoutModal called', event);

        try {
            // Only hide if clicking overlay or called directly
            if (!event || event.target.id === 'logoutModal' || event.target.classList.contains('logout-modal-overlay')) {
                const modal = document.getElementById('logoutModal');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                    document.body.classList.remove('logout-modal-open');
                    console.log('Modal hidden');
                }
            }
        } catch (error) {
            console.error('Error in hideLogoutModal:', error);
        }
    }

    function confirmLogout() {
        console.log('confirmLogout called');

        try {
            const confirmBtn = document.querySelector('.logout-btn-confirm');
            const cancelBtn = document.querySelector('.logout-btn-cancel');

            if (confirmBtn && cancelBtn) {
                // Add loading state
                confirmBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Logging out...';
                confirmBtn.disabled = true;
                cancelBtn.disabled = true;

                console.log('Redirecting to logout...');
            }

            // Redirect after delay
            setTimeout(() => {
                window.location.href = '../Logout.php';
            }, 800);
        } catch (error) {
            console.error('Error in confirmLogout:', error);
            // Direct redirect if error
            window.location.href = '../Logout.php';
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('logoutModal');
            if (modal && (modal.style.display === 'block' || modal.classList.contains('show'))) {
                hideLogoutModal();
            }
        }
    });

    // Test function - you can call this in console to test modal
    function testModal() {
        showLogoutModal();
    }

    console.log('Logout modal script loaded successfully');
</script>