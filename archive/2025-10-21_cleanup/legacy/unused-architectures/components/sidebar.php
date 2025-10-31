<?php
/**
 * Supplier Portal - Sidebar Navigation (AdminLTE 3)
 * 
 * Tab-based navigation matching retail customer account pattern
 */

$tabs = [
    'dashboard' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard', 'badge' => null, 'badge_class' => 'success'],
    'orders' => ['icon' => 'file-invoice-dollar', 'label' => 'Purchase Orders', 'badge' => null, 'badge_class' => 'info'],
    'warranty' => ['icon' => 'tools', 'label' => 'Warranty Claims', 'badge' => 3, 'badge_class' => 'danger'],
    'downloads' => ['icon' => 'download', 'label' => 'Downloads', 'badge' => null, 'badge_class' => 'primary'],
    'reports' => ['icon' => 'chart-line', 'label' => '30-Day Reports', 'badge' => null, 'badge_class' => 'warning'],
    'account' => ['icon' => 'user-cog', 'label' => 'Account', 'badge' => null, 'badge_class' => 'secondary'],
];
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="/supplier/" class="brand-link text-center">
        <img src="/supplier/assets/images/logo.png" alt="The Vape Shed Logo" class="brand-image elevation-3" style="opacity: .8; max-height: 40px; width: auto;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
        <span class="brand-text font-weight-light" style="display:block;">
            <strong>The Vape Shed</strong><br>
            <small style="font-size: 0.8rem; opacity: 0.7;">Supplier Portal</small>
        </span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-building fa-2x text-white" style="margin-left: 5px;"></i>
            </div>
            <div class="info">
                <a href="/supplier/?tab=account" class="d-block">
                    <?php echo htmlspecialchars($supplierName); ?>
                </a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <?php foreach ($tabs as $tabKey => $tabData): ?>
                    <li class="nav-item">
                        <a href="/supplier/?tab=<?php echo $tabKey; ?>" 
                           class="nav-link <?php echo ($activeTab === $tabKey) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-<?php echo $tabData['icon']; ?>"></i>
                            <p>
                                <?php echo $tabData['label']; ?>
                                <?php if ($tabData['badge']): ?>
                                    <span class="right badge badge-<?php echo $tabData['badge_class']; ?>"><?php echo $tabData['badge']; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
                
                <li class="nav-header">QUICK ACTIONS</li>
                
                <li class="nav-item">
                    <a href="#" onclick="downloadAllOrders(); return false;" class="nav-link">
                        <i class="nav-icon fas fa-file-archive"></i>
                        <p>Download All Orders</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" onclick="exportWarrantyClaims(); return false;" class="nav-link">
                        <i class="nav-icon fas fa-file-csv"></i>
                        <p>Export Warranty CSV</p>
                    </a>
                </li>
                
                <li class="nav-header">ACCOUNT</li>
                
                <li class="nav-item">
                    <a href="/supplier/logout.php" class="nav-link text-danger">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>

            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 48px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 0.75rem 1rem;
    transition: all 0.2s;
}

.sidebar .nav-link:hover {
    color: #007bff;
    background-color: #f8f9fa;
}

.sidebar .nav-link.active {
    color: #007bff;
    background-color: #e7f3ff;
    border-left: 3px solid #007bff;
}

.sidebar .nav-link i {
    margin-right: 8px;
    width: 20px;
    text-align: center;
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .sidebar {
        position: static;
        padding-top: 0;
    }
}
</style>
