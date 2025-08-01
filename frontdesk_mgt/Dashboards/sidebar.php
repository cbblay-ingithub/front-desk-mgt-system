<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../Sneat/assets/vendor/fonts/iconify-icons.css" />
<link rel="stylesheet" href="../../Sneat/assets/vendor/css/core.css" />
<link rel="stylesheet" href="../../Sneat/assets/css/demo.css" />

<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <!-- Brand/Header -->
    <div class="app-brand demo">
        <a href="help_desk.php"
           class="app-brand-link d-flex align-items-center flex-nowrap">
    <span class="app-brand-logo demo">
      <i class="bx bx-support text-primary" style="font-size:25px;"></i>
    </span>
            <span class="app-brand-text demo menu-text fw-bold ms-1 text-nowrap">
      Support Panel
    </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base bx bx-chevron-left"></i>
        </a>
    </div>


    <!-- Menu Shadow -->
    <div class="menu-inner-shadow"></div>

    <!-- Menu Items -->
    <ul class="menu-inner py-1">
        <!-- Dashboard -->
        <li class="menu-item">
            <a href="HD_analytics.php" class="menu-link">
                <i class="menu-icon icon-base bx bx-home-smile"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>

        <!-- Manage Tickets -->
        <li class="menu-item <?= ($currentPage == 'help_desk.php') ? 'active' : '' ?>">
            <a href="help_desk.php" class="menu-link">
                <i class="menu-icon icon-base bx bx-message-square-detail"></i>
                <div data-i18n="Manage Tickets">Manage Tickets</div>
            </a>
        </li>

        <!-- Logout -->
        <li class="menu-item">
            <a href="../Logout.php" class="menu-link">
                <i class="menu-icon icon-base bx bx-power-off"></i>
                <div data-i18n="Logout">Logout</div>
            </a>
        </li>
    </ul>
</aside>
