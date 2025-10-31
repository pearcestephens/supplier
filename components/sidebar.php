<?php
/**
 * Sidebar Component - Professional Black Navigation
 *
 * Features:
 * - Logo at top
 * - Badge notifications on nav items
 * - Recent Activity widget
 * - Quick Stats widget with progress bars
 * - Auto-detects active page from basename
 *
 * @package SupplierPortal
 * @version 3.0.0 - Component Architecture
 */

// Auto-detect active page from current file (no more tabs!)
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$activeTab = $activeTab ?? $currentPage; // Use $activeTab if set by page, otherwise use filename

// Get notification counts (these should be set by each page)
$warrantyClaimsCount = $warrantyClaimsCount ?? 0;
$pendingOrdersCount = $pendingOrdersCount ?? 0;
?>
<!-- ============================================================================
     PROFESSIONAL BLACK SIDEBAR (Demo-Perfect Match)
     ========================================================================== -->
<aside class="navbar-vertical">
    <!-- Logo -->
    <div class="navbar-brand">
        <img src="/supplier/assets/images/logo.jpg" alt="The Vape Shed" class="brand-logo">
    </div>

    <!-- Navigation -->
    <ul class="navbar-nav">
        <li class="nav-item <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/dashboard.php">
                <i class="fa-solid fa-chart-line nav-link-icon"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'orders' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/orders.php">
                <i class="fa-solid fa-shopping-cart nav-link-icon"></i>
                <span>Purchase Orders</span>
                <?php if ($pendingOrdersCount > 0): ?>
                    <span class="badge bg-warning ms-auto"><?php echo $pendingOrdersCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'warranty' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/warranty.php">
                <i class="fa-solid fa-wrench nav-link-icon"></i>
                <span>Warranty Claims</span>
                <?php if ($warrantyClaimsCount > 0): ?>
                    <span class="badge bg-red ms-auto"><?php echo $warrantyClaimsCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'catalog' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/catalog.php">
                <i class="fa-solid fa-box nav-link-icon"></i>
                <span>Product Catalog</span>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'inventory-movements' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/inventory-movements.php">
                <i class="fa-solid fa-exchange-alt nav-link-icon"></i>
                <span>Inventory Movements</span>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'downloads' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/downloads.php">
                <i class="fa-solid fa-download nav-link-icon"></i>
                <span>Downloads</span>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'reports' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/reports.php">
                <i class="fa-solid fa-chart-bar nav-link-icon"></i>
                <span>30-Day Reports</span>
            </a>
        </li>

        <li class="nav-item <?php echo $activeTab === 'account' ? 'active' : ''; ?>">
            <a class="nav-link" href="/supplier/account.php">
                <i class="fa-solid fa-user-circle nav-link-icon"></i>
                <span>Account Settings</span>
            </a>
        </li>
    </ul>

    <!-- ============================================================================
         SIDEBAR WIDGETS - Recent Activity & Quick Stats
         ========================================================================== -->
    <div class="sidebar-widget mt-4 px-3">
        <!-- RECENT ACTIVITY NOTIFICATIONS -->
        <h6 class="sidebar-widget-title text-muted text-uppercase mb-3" style="font-size: 11px; letter-spacing: 0.5px;">
            <i class="fa-solid fa-bell me-1"></i> Recent Activity
        </h6>

        <div class="sidebar-activity" id="sidebar-activity">
            <div class="sidebar-activity-item">
                <div class="activity-dot bg-primary"></div>
                <div class="activity-text">
                    <div class="activity-title">Loading...</div>
                    <div class="activity-time">-</div>
                </div>
            </div>
        </div>

        <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">

        <!-- QUICK STATS AT BOTTOM -->
        <h6 class="sidebar-widget-title text-muted text-uppercase mb-3" style="font-size: 11px; letter-spacing: 0.5px;">
            <i class="fa-solid fa-chart-simple me-1"></i> Quick Stats
        </h6>

        <div class="sidebar-stat-item" id="sidebar-active-orders">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted" style="font-size: 12px;">Active Orders</span>
                <strong style="font-size: 14px;">-</strong>
            </div>
            <div class="progress" style="height: 4px;">
                <div class="progress-bar bg-primary" style="width: 0%"></div>
            </div>
        </div>

        <div class="sidebar-stat-item mt-3" id="sidebar-stock-health">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted" style="font-size: 12px;">Stock Health</span>
                <strong class="text-success" style="font-size: 14px;">-</strong>
            </div>
            <div class="progress" style="height: 4px;">
                <div class="progress-bar bg-success" style="width: 0%"></div>
            </div>
        </div>

        <div class="sidebar-stat-item mt-3" id="sidebar-monthly-orders">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted" style="font-size: 12px;">This Month</span>
                <strong style="font-size: 14px;">-</strong>
            </div>
            <div class="progress" style="height: 4px;">
                <div class="progress-bar bg-info" style="width: 0%"></div>
            </div>
        </div>
    </div>
</aside>
