<?php
/**
 * Top Navigation Bar Component
 * Modern horizontal navbar with branding and user menu
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="/supplier/dashboard.php">
            <img src="/supplier/assets/images/logo.jpg" alt="The Vape Shed" height="40" class="me-2">
            <span class="fw-bold">Supplier Portal</span>
        </a>

        <!-- Supplier Name (Center) -->
        <span class="navbar-text text-white mx-auto">
            <?php echo htmlspecialchars($supplierName ?? 'British American Tobacco'); ?>
        </span>

        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-2"></i>
                <?php echo htmlspecialchars(Auth::getSupplierEmail() ?? 'User'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/supplier/account.php"><i class="fas fa-user me-2"></i> Account Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/supplier/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
