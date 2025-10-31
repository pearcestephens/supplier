<?php
/**
 * Supplier Portal - Main Entry Point
 * 
 * Template-driven UI control panel for wholesale suppliers
 * 
 * @package CIS\Supplier
 * @version 3.0.0 - Updated for UUID suppliers and ML performance tracking
 * @author The Vape Shed
 */

declare(strict_types=1);

// Define portal constant
define('SUPPLIER_PORTAL', true);

// Bootstrap application (uses bootstrap.php instead of app.php)
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

// Load portal configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Initialize session
init_session();

// Require authentication
require_auth();

// Get current page/view
$page = get_param('page', 'dashboard');
$validPages = ['dashboard', 'orders', 'warranty', 'downloads', 'reports', 'account'];

if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Page metadata
$pageData = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'tachometer-alt',
        'description' => 'Overview of your account activity',
    ],
    'orders' => [
        'title' => 'Purchase Orders',
        'icon' => 'shopping-cart',
        'description' => 'View and manage purchase orders',
    ],
    'warranty' => [
        'title' => 'Warranty Claims',
        'icon' => 'shield-alt',
        'description' => 'Process warranty claims and returns',
    ],
    'downloads' => [
        'title' => 'Downloads',
        'icon' => 'download',
        'description' => 'Access documents and reports',
    ],
    'reports' => [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'description' => '30-day sales and performance reports',
    ],
    'account' => [
        'title' => 'Account Settings',
        'icon' => 'user-cog',
        'description' => 'Manage your account and preferences',
    ],
];

$currentPage = $pageData[$page];

// Get supplier session data
$supplier = get_session();

// Load header template
require_once TEMPLATES_PATH . '/header.php';

// Load sidebar template
require_once TEMPLATES_PATH . '/sidebar.php';

?>

<!-- Main Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    
    <!-- Main Content -->
    <div id="content">
        
        <!-- Topbar (optional - can add breadcrumbs, user info, etc.) -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between w-100">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-<?= htmlspecialchars($currentPage['icon']) ?>"></i>
                        <?= htmlspecialchars($currentPage['title']) ?>
                    </h1>
                    <div class="topbar-info">
                        <span class="mr-3 text-gray-600">
                            <i class="fas fa-building"></i>
                            <?= htmlspecialchars($supplier['supplier_name']) ?>
                        </span>
                        <span class="text-gray-600">
                            <i class="fas fa-clock"></i>
                            <?= format_datetime(time(), 'd M Y, H:i') ?>
                        </span>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Begin Page Content -->
        <div class="container-fluid">
            
            <?php
            // Load the requested view
            $viewFile = VIEWS_PATH . '/' . $page . '.php';
            
            if (file_exists($viewFile)) {
                require_once $viewFile;
            } else {
                ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error:</strong> Page not found.
                </div>
                <?php
            }
            ?>
            
        </div>
        <!-- /.container-fluid -->
        
    </div>
    <!-- End of Main Content -->
    
    <?php
    // Load footer template
    require_once TEMPLATES_PATH . '/footer.php';
    ?>
    
</div>
<!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">Are you sure you want to logout?</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="<?= BASE_URL ?>logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
