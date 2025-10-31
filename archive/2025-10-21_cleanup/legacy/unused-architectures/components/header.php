<?php
/**
 * Supplier Portal - Header Component (AdminLTE 3)
 * 
 * Top navigation bar with supplier info and quick actions
 */
?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="/supplier/" class="nav-link"><i class="fas fa-home"></i> Home</a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="/supplier/?tab=orders" class="nav-link"><i class="fas fa-file-invoice-dollar"></i> Orders</a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        
        <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Notifications">
                <i class="far fa-bell"></i>
                <span class="badge badge-warning navbar-badge" id="notification-count">3</span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">3 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="/supplier/?tab=warranty" class="dropdown-item">
                    <i class="fas fa-tools mr-2"></i> 3 pending warranty claims
                    <span class="float-right text-muted text-sm">action required</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="/supplier/?tab=orders" class="dropdown-item">
                    <i class="fas fa-shipping-fast mr-2"></i> 2 orders due this week
                    <span class="float-right text-muted text-sm">upcoming</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="/supplier/?tab=warranty" class="dropdown-item dropdown-footer">View All Notifications</a>
            </div>
        </li>
        
        <!-- Quick Downloads -->
        <li class="nav-item">
            <a class="nav-link" href="/supplier/?tab=downloads" title="Downloads">
                <i class="fas fa-download"></i>
            </a>
        </li>
        
        <!-- Fullscreen Toggle -->
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="Fullscreen">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
        
        <!-- User Account Dropdown -->
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" aria-expanded="false">
                <i class="far fa-user-circle"></i>
                <span class="d-none d-md-inline ml-1"><?php echo htmlspecialchars($supplierName); ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <div class="dropdown-divider"></div>
                <a href="/supplier/?tab=account" class="dropdown-item">
                    <i class="fas fa-cog mr-2"></i> Account Settings
                </a>
                <a href="/supplier/?tab=reports" class="dropdown-item">
                    <i class="fas fa-chart-line mr-2"></i> Reports
                </a>
                <div class="dropdown-divider"></div>
                <a href="/supplier/logout.php" class="dropdown-item dropdown-footer">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </li>
        
    </ul>
</nav>
<!-- /.navbar -->
