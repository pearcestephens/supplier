<?php
/**
 * Page Header Component - Fixed Top Bar Only
 * Displays welcome message and user actions
 *
 * Required variables:
 * - $supplierName (string): Name of the supplier
 */
?>

<!-- Page Header Top Bar - Fixed -->
<div class="page-header-wrapper">
    <div class="page-header-top">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Welcome Message -->
                <div class="welcome-message mb-0">
                    Welcome back, <span class="fw-semibold"><?php echo htmlspecialchars($supplierName ?? 'User'); ?></span>
                </div>

                <!-- User Actions -->
                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-icon position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-bell"></i>
                            <?php if (($warrantyClaimsCount ?? 0) > 0 || ($pendingOrdersCount ?? 0) > 0): ?>
                                <span class="notification-badge">
                                    <?php echo ($warrantyClaimsCount ?? 0) + ($pendingOrdersCount ?? 0); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 280px;">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (($pendingOrdersCount ?? 0) > 0): ?>
                                <li>
                                    <a class="dropdown-item" href="/supplier/orders.php">
                                        <i class="fa-solid fa-shopping-cart text-primary me-2"></i>
                                        <?php echo $pendingOrdersCount; ?> active order<?php echo $pendingOrdersCount > 1 ? 's' : ''; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (($warrantyClaimsCount ?? 0) > 0): ?>
                                <li>
                                    <a class="dropdown-item" href="/supplier/warranty.php">
                                        <i class="fa-solid fa-wrench text-warning me-2"></i>
                                        <?php echo $warrantyClaimsCount; ?> warranty claim<?php echo $warrantyClaimsCount > 1 ? 's' : ''; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (($warrantyClaimsCount ?? 0) == 0 && ($pendingOrdersCount ?? 0) == 0): ?>
                                <li><span class="dropdown-item text-muted small">No new notifications</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-icon" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-user-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($supplierName ?? ''); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/supplier/account.php"><i class="fa-solid fa-user me-2"></i> Account</a></li>
                            <li><a class="dropdown-item" href="/supplier/reports.php"><i class="fa-solid fa-chart-bar me-2"></i> Reports</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/supplier/logout.php"><i class="fa-solid fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
