<?php
/**
 * Top Navbar - Bootstrap 5 Standard with Demo Styling
 */
?>
<nav class="navbar navbar-top navbar-expand-lg">
    <div class="container-fluid px-4">
        <!-- Supplier Name (Left) -->
        <span class="navbar-text text-dark fw-semibold fs-5">
            <?php echo htmlspecialchars($supplierName ?? 'Supplier Portal'); ?>
        </span>

        <!-- Right side items -->
        <div class="d-flex align-items-center gap-3">
            <!-- Notifications Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-dark position-relative p-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell fs-5"></i>
                    <?php if (($warrantyClaimsCount ?? 0) > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $warrantyClaimsCount; ?>
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (($warrantyClaimsCount ?? 0) > 0): ?>
                        <li>
                            <a class="dropdown-item" href="/supplier/warranty.php">
                                <i class="fa-solid fa-wrench text-warning me-2"></i>
                                <?php echo $warrantyClaimsCount; ?> pending warranty claim<?php echo $warrantyClaimsCount > 1 ? 's' : ''; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (($pendingOrdersCount ?? 0) > 0): ?>
                        <li>
                            <a class="dropdown-item" href="/supplier/orders.php">
                                <i class="fa-solid fa-shopping-cart text-primary me-2"></i>
                                <?php echo $pendingOrdersCount; ?> active order<?php echo $pendingOrdersCount > 1 ? 's' : ''; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (($warrantyClaimsCount ?? 0) == 0 && ($pendingOrdersCount ?? 0) == 0): ?>
                        <li><span class="dropdown-item text-muted">No new notifications</span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-dark d-flex align-items-center gap-2 p-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-user-circle fs-4"></i>
                    <span class="d-none d-md-inline small"><?php echo htmlspecialchars(explode(' ', $supplierName ?? 'User')[0]); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header"><?php echo htmlspecialchars($supplierName ?? 'Supplier'); ?></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/supplier/account.php">
                            <i class="fa-solid fa-user me-2"></i> Account Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/supplier/reports.php">
                            <i class="fa-solid fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/supplier/logout.php">
                            <i class="fa-solid fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
