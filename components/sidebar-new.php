<?php
/**
 * Sidebar Component - Bootstrap 5 Standard
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$activeTab = $activeTab ?? $currentPage;
?>
<div class="sidebar" role="navigation" aria-label="Main navigation">
    <!-- Logo -->
    <div class="text-center py-2">
        <a href="/supplier/dashboard.php" aria-label="The Vape Shed Home">
            <img src="/supplier/assets/images/logo.jpg" alt="The Vape Shed Logo" style="max-width: 160px; height: auto;">
        </a>
        <div class="text-white-50 mt-1" style="font-size: 0.65rem; letter-spacing: 1px; text-transform: uppercase;">
            Supplier Portal
        </div>
    </div>

    <!-- Navigation -->
    <nav aria-label="Primary">
        <ul class="nav flex-column mb-0">
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" href="/supplier/dashboard.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" href="/supplier/orders.php">
                <i class="fa-solid fa-shopping-cart"></i> Purchase Orders
                <?php if (($pendingOrdersCount ?? 0) > 0): ?>
                    <span class="badge bg-danger rounded-pill float-end" aria-label="<?php echo $pendingOrdersCount; ?> pending orders"><?php echo $pendingOrdersCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'catalog' ? 'active' : ''; ?>" href="/supplier/catalog.php">
                <i class="fa-solid fa-box-open"></i> Product Catalog
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'inventory-movements' ? 'active' : ''; ?>" href="/supplier/inventory-movements.php">
                <i class="fa-solid fa-arrow-right-arrow-left"></i> Inventory Movements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'warranty' ? 'active' : ''; ?>" href="/supplier/warranty.php">
                <i class="fa-solid fa-wrench"></i> Warranty Claims
                <?php if (($warrantyClaimsCount ?? 0) > 0): ?>
                    <span class="badge bg-warning rounded-pill float-end" aria-label="<?php echo $warrantyClaimsCount; ?> warranty claims"><?php echo $warrantyClaimsCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php if (defined('FEATURE_DOWNLOADS_ENABLED') && FEATURE_DOWNLOADS_ENABLED): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'downloads' ? 'active' : ''; ?>" href="/supplier/downloads.php">
                <i class="fa-solid fa-download"></i> Downloads
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'reports' ? 'active' : ''; ?>" href="/supplier/reports.php">
                <i class="fa-solid fa-chart-bar"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'account' ? 'active' : ''; ?>" href="/supplier/account.php">
                <i class="fa-solid fa-user"></i> Account
            </a>
        </li>
    </ul>
    </nav>

    <!-- Activity Feed Widget (shows first, hides on short screens) -->
    <div class="sidebar-widget sidebar-activity-widget mt-2 px-3">
        <h6 class="sidebar-widget-title text-white-50 text-uppercase mb-2">
            <i class="fa-solid fa-clock me-1"></i>Recent Activity
        </h6>
        <div class="sidebar-activity" id="sidebar-activity-feed">
            <div class="activity-item mb-2">
                <div class="activity-icon bg-primary">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div class="activity-content">
                    <p class="text-white small mb-0">New order received</p>
                    <p class="text-white-50 x-small mb-0">2 hours ago</p>
                </div>
            </div>
            <div class="activity-item mb-2">
                <div class="activity-icon bg-success">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="activity-content">
                    <p class="text-white small mb-0">Order completed</p>
                    <p class="text-white-50 x-small mb-0">5 hours ago</p>
                </div>
            </div>
            <div class="activity-item mb-2">
                <div class="activity-icon bg-warning">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <div class="activity-content">
                    <p class="text-white small mb-0">Warranty claim filed</p>
                    <p class="text-white-50 x-small mb-0">Yesterday</p>
                </div>
            </div>
            <div class="activity-item mb-2 activity-extra">
                <div class="activity-icon bg-info">
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div class="activity-content">
                    <p class="text-white small mb-0">Shipment sent</p>
                    <p class="text-white-50 x-small mb-0">2 days ago</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Widget (at bottom, hides first on short screens) -->
    <div class="sidebar-widget sidebar-stats-widget mt-2 px-3">
        <h6 class="sidebar-widget-title text-white-50 text-uppercase mb-2">
            <i class="fa-solid fa-chart-pie me-1"></i>Quick Stats
        </h6>
        <div class="sidebar-stat mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white-50 x-small">Active Orders</span>
                <span class="text-white fw-bold small"><?php echo $pendingOrdersCount ?? 0; ?></span>
            </div>
            <div class="progress" style="height: 3px;">
                <div class="progress-bar bg-primary" style="width: 75%"></div>
            </div>
        </div>
        <div class="sidebar-stat mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white-50 x-small">Warranty Claims</span>
                <span class="text-white fw-bold small"><?php echo $warrantyClaimsCount ?? 0; ?></span>
            </div>
            <div class="progress" style="height: 3px;">
                <div class="progress-bar bg-warning" style="width: 45%"></div>
            </div>
        </div>
        <div class="sidebar-stat">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white-50 x-small">Products</span>
                <span class="text-white fw-bold small" id="sidebar-products-count">-</span>
            </div>
            <div class="progress" style="height: 3px;">
                <div class="progress-bar bg-success" style="width: 85%"></div>
            </div>
        </div>
    </div>
</div>
