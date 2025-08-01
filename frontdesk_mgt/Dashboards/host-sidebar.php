<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
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
</style>

<!-- Enhanced Toggle Button (Positioned on sidebar edge) -->
<button class="sidebar-toggle-enhanced" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="bx bx-chevron-left" id="toggleIcon"></i>
</button>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <!-- Brand/Header -->
    <div class="app-brand demo">
        <a href="host_dashboard.php"
           class="app-brand-link d-flex align-items-center flex-nowrap">
            <span class="app-brand-logo demo">
                <i class="bx bx-user-pin text-primary" style="font-size:25px;"></i>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-1 text-nowrap">
                Host Panel
            </span>
        </a>
    </div>

    <!-- Menu Shadow -->
    <div class="menu-inner-shadow"></div>

    <!-- Menu Items -->
    <ul class="menu-inner py-1">
        <!-- Dashboard -->
        <li class="menu-item <?= ($currentPage == 'HD_analytics.php') ? 'active' : '' ?>">
            <a href="HD_analytics.php" class="menu-link" data-tooltip="Dashboard">
                <i class="menu-icon icon-base bx bx-home-smile"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>

        <!-- Manage Appointments -->
        <li class="menu-item <?= ($currentPage == 'host_dashboard.php') ? 'active' : '' ?>">
            <a href="host_dashboard.php" class="menu-link" data-tooltip="Manage Appointments">
                <i class="menu-icon icon-base bx bx-calendar-check"></i>
                <div data-i18n="Manage Appointments">Manage Appointments</div>
            </a>
        </li>

        <!-- Manage Tickets -->
        <li class="menu-item <?= ($currentPage == 'staff_tickets.php') ? 'active' : '' ?>">
            <a href="host_tickets.php" class="menu-link" data-tooltip="Manage Tickets">
                <i class="menu-icon icon-base bx bx-message-square-detail"></i>
                <div data-i18n="Manage Tickets">Manage Tickets</div>
            </a>
        </li>

        <!-- Logout -->
        <li class="menu-item">
            <a href="../Logout.php" class="menu-link" data-tooltip="Logout">
                <i class="menu-icon icon-base bx bx-power-off"></i>
                <div data-i18n="Logout">Logout</div>
            </a>
        </li>
    </ul>
</aside>

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
</script>