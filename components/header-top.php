<?php
/**
 * Header Top Component - Branding & Actions
 *
 * @package SupplierPortal
 */
?>
<!-- Header Top -->
<header class="header-top">
    <div class="header-content">
        <div class="d-flex align-items-center">
            <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars($supplierName ?? 'Supplier Portal'); ?></h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-icon position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="notification-count" style="display: none; font-size: 0.7rem; padding: 0.25rem 0.4rem;">0</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 320px;">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <strong>Notifications</strong>
                        <span class="badge bg-primary ms-2" id="notification-count-text">0</span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li id="notification-list">
                        <div class="text-center text-muted py-3 small">
                            No new notifications
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-center small" href="/supplier/account.php#notifications">
                            View All Notifications
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">
                        <strong><?php echo htmlspecialchars($supplierName ?? 'User'); ?></strong>
                        <small class="d-block text-muted"><?php echo htmlspecialchars(Auth::getSupplierEmail() ?? ''); ?></small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/supplier/account.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
                    <li><a class="dropdown-item" href="/supplier/downloads.php"><i class="fas fa-download me-2"></i>Downloads</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="SupplierPortal.logout(); return false;"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>
