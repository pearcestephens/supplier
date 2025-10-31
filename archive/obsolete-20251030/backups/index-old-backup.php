<?php
/**
 * Supplier Portal - Entry Point
 * Redirects to dashboard.php
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Allow magic link login
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    Auth::loginById($_GET['supplier_id']);
}

// Redirect to dashboard
header('Location: /supplier/dashboard.php');
exit;

// ============================================================================
// AUTHENTICATION: Allow supplier_id via GET parameter or session
// ============================================================================
// NO IP RESTRICTIONS - Allow access from any IP address
// Authentication via supplier_id parameter or existing session

// Check for supplier_id in GET parameter (for magic link login)
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    $supplierID = $_GET['supplier_id'];
    
    // Authenticate using standalone Auth class
    if (!Auth::loginById($supplierID)) {
        // Invalid supplier_id - redirect to login with error
        header('Location: /supplier/login.php?error=invalid_id');
        exit;
    }
    // If login successful, continue loading the page (don't redirect)
    // This ensures the session cookie is sent to the browser
}

// Check if already authenticated via session
if (!Auth::check()) {
    // Not authenticated - redirect to login page
    header('Location: /supplier/login.php');
    exit;
}

// SECURITY: Double-check authentication before rendering any content
// If somehow we got past the redirect, halt execution
if (!Auth::check()) {
    die('Unauthorized access. Authentication required.');
}

// Get authenticated supplier details
$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// SECURITY: Ensure we have valid supplier data
if (!$supplierID || !$supplierName) {
    Session::destroy();
    header('Location: /supplier/login.php?error=session_invalid');
    exit;
}

// Get notification counts for sidebar badges
$warrantyClaimsCount = 0;
$pendingOrdersCount = 0;

try {
    $db = db();
    
    // Count pending warranty claims (join with vend_products to filter by supplier)
    $warrantyStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM faulty_products fp
        INNER JOIN vend_products vp ON fp.product_id = vp.id
        WHERE fp.supplier_status = 0 
        AND vp.supplier_id = ?
        AND vp.deleted_at IS NULL
    ");
    $warrantyStmt->bind_param('s', $supplierID);
    $warrantyStmt->execute();
    $warrantyClaimsCount = $warrantyStmt->get_result()->fetch_assoc()['count'];
    $warrantyStmt->close();
    
    // Count active orders
    $ordersStmt = $db->prepare("SELECT COUNT(*) as count FROM vend_consignments WHERE supplier_id = ? AND state IN ('OPEN', 'SENT', 'RECEIVING') AND deleted_at IS NULL");
    $ordersStmt->bind_param('s', $supplierID);
    $ordersStmt->execute();
    $pendingOrdersCount = $ordersStmt->get_result()->fetch_assoc()['count'];
    $ordersStmt->close();
} catch (Exception $e) {
    error_log('Error loading notification counts: ' . $e->getMessage());
}

// Tab routing
$activeTab = $_GET['tab'] ?? 'dashboard';
$validTabs = ['dashboard', 'orders', 'warranty', 'downloads', 'reports', 'account'];
$invalidTabRequested = false;

if (!in_array($activeTab, $validTabs)) {
    $invalidTabRequested = $activeTab; // Store invalid tab name
    $activeTab = 'dashboard'; // Fallback to dashboard
}

// Page title
$pageTitles = [
    'dashboard' => 'Dashboard',
    'orders' => 'Purchase Orders',
    'warranty' => 'Warranty Claims',
    'downloads' => 'Downloads & Archives',
    'reports' => '30-Day Reports',
    'account' => 'Account Settings'
];

$pageTitle = $pageTitles[$activeTab] ?? 'Supplier Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - The Vape Shed Supplier Portal</title>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS - Required for Grid System -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Professional Black Theme - MUST BE AFTER Bootstrap -->
    <link rel="stylesheet" href="/supplier/assets/css/professional-black.css?v=<?php echo time(); ?>">
    
    <!-- Dashboard Widgets CSS - Dashboard tab specific styles -->
    <link rel="stylesheet" href="/supplier/assets/css/dashboard-widgets.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="page">
    
    <!-- ============================================================================
         SIDEBAR NAVIGATION (Vertical)
         ========================================================================== -->
    <aside class="navbar-vertical">
        <div class="navbar-brand" style="text-align: center; padding: 20px 15px;">
            <img src="/supplier/assets/images/logo.jpg" alt="The Vape Shed" class="brand-logo" style="max-width: 180px; margin: 0 auto; display: block;">
        </div>
        
        <ul class="navbar-nav">
            <li class="nav-item <?php echo ($activeTab === 'dashboard') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=dashboard">
                    <i class="fa-solid fa-chart-line nav-link-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($activeTab === 'orders') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=orders">
                    <i class="fa-solid fa-shopping-cart nav-link-icon"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($activeTab === 'warranty') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=warranty">
                    <i class="fa-solid fa-wrench nav-link-icon"></i>
                    <span>Warranty Claims</span>
                    <?php if (isset($warrantyClaimsCount) && $warrantyClaimsCount > 0): ?>
                        <span class="badge bg-red ms-auto"><?php echo $warrantyClaimsCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($activeTab === 'downloads') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=downloads">
                    <i class="fa-solid fa-download nav-link-icon"></i>
                    <span>Downloads</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($activeTab === 'reports') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=reports">
                    <i class="fa-solid fa-chart-bar nav-link-icon"></i>
                    <span>30-Day Reports</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($activeTab === 'account') ? 'active' : ''; ?>">
                <a class="nav-link" href="?tab=account">
                    <i class="fa-solid fa-user-circle nav-link-icon"></i>
                    <span>Account Settings</span>
                </a>
            </li>
        </ul>
        
        <!-- ============================================================================
             SIDEBAR WIDGETS - Recent Activity & Quick Stats
             ========================================================================== -->
        <div class="sidebar-widget mt-4 px-3">
                        
            <h6 class="sidebar-widget-title mb-3" style="font-size: 11px; letter-spacing: 0.5px; color: #888; text-transform: uppercase;">
                Recent Activity
            </h6>
            
            <div class="sidebar-activity" id="sidebar-activity">
                <div class="sidebar-activity-item">
                    <div class="activity-dot bg-primary"></div>
                    <div class="activity-text">
                        <div class="activity-title" style="color: #fff; font-size: 13px;">Loading...</div>
                        <div class="activity-time" style="color: #888; font-size: 11px;">-</div>
                    </div>
                </div>
            </div>
            
            <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">
            
            <h6 class="sidebar-widget-title mb-3" style="font-size: 11px; letter-spacing: 0.5px; color: #888; text-transform: uppercase;">
                Quick Stats
            </h6>
            
            <div class="sidebar-stat-item" id="sidebar-active-orders">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 12px; color: #888;">Active Orders</span>
                    <strong style="font-size: 14px; color: #fff;">-</strong>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-primary" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="sidebar-stat-item mt-3" id="sidebar-stock-health">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 12px; color: #888;">Stock Health</span>
                    <strong class="text-success" style="font-size: 14px; color: #10b981;">-</strong>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="sidebar-stat-item mt-3" id="sidebar-monthly-orders">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 12px; color: #888;">This Month</span>
                    <strong style="font-size: 14px; color: #fff;">-</strong>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-info" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- ============================================================================
         HEADER TOP LAYER - Branding, Notifications, User
         ========================================================================== -->
    <header class="header-top">
        <div class="header-top-left">
            <h2 class="header-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
            <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($supplierName); ?></p>
        </div>
        
        <div class="header-top-right">
            <!-- Search -->
            <button class="header-action-btn" title="Search">
                <i class="fa-solid fa-search"></i>
            </button>
            
            <!-- Notifications -->
            <div class="dropdown d-inline-block">
                <button class="header-action-btn position-relative" title="Notifications" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell"></i>
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
                        <a class="dropdown-item text-center small" href="?tab=account#notifications">
                            View All Notifications
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- User Dropdown -->
            <div class="user-dropdown">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($supplierName); ?>&background=3b82f6&color=fff&size=40" 
                     alt="<?php echo htmlspecialchars($supplierName); ?>" 
                     class="user-avatar">
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($supplierName); ?></p>
                    <p class="user-role">Supplier Account</p>
                </div>
                <i class="fa-solid fa-chevron-down ms-2"></i>
            </div>
        </div>
    </header>
    
    <!-- ============================================================================
         HEADER BOTTOM LAYER - Breadcrumb, Page Actions
         ========================================================================== -->
    <header class="header-bottom">
        <div class="breadcrumb-nav">
            <ul class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="?tab=dashboard">
                        <i class="fa-solid fa-home"></i>
                    </a>
                </li>
                <li class="breadcrumb-separator">/</li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($pageTitle); ?></li>
            </ul>
        </div>
        
        <div class="header-bottom-actions">
            <?php if ($activeTab === 'dashboard'): ?>
                <a href="?tab=orders" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    New Order
                </a>
            <?php elseif ($activeTab === 'orders'): ?>
                <button class="btn btn-light">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>
                <button class="btn btn-light">
                    <i class="fa-solid fa-download"></i>
                    Export
                </button>
            <?php elseif ($activeTab === 'warranties'): ?>
                <button class="btn btn-light">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>
            <?php elseif ($activeTab === 'products'): ?>
                <button class="btn btn-light">
                    <i class="fa-solid fa-sync"></i>
                    Sync Now
                </button>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- ============================================================================
         PAGE WRAPPER - Main Content Area
         ========================================================================== -->
    <div class="page-wrapper">
        
        <!-- ============================================================================
             MAIN CONTENT BODY
             ========================================================================== -->
        <div class="page-body">
            <?php
            // Show invalid tab warning if needed
            if ($invalidTabRequested) {
                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                echo '<i class="fa-solid fa-triangle-exclamation"></i> ';
                echo '<strong>Invalid tab:</strong> "' . htmlspecialchars($invalidTabRequested) . '" not found. Showing Dashboard instead.';
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
            
            // SECURITY: Define constant to prevent direct tab access
            define('TAB_FILE_INCLUDED', true);
            
            // Route to appropriate tab component
            $tabFile = __DIR__ . "/tabs/tab-{$activeTab}.php";
            
            if (file_exists($tabFile)) {
                include $tabFile;
            } else {
                echo '<div class="alert alert-danger">Tab not found: ' . htmlspecialchars($activeTab) . '</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- jQuery 3.6 (Required for supplier-portal.js) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5.3 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (for dashboard sparklines) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Global Error Handler - MUST BE LOADED FIRST -->
<script src="/supplier/assets/js/error-handler.js?v=<?php echo time(); ?>"></script>

<!-- Main Portal JavaScript - Notifications & Global Functions -->
<script src="/supplier/assets/js/supplier-portal.js?v=<?php echo time(); ?>"></script>

<!-- Sidebar Widgets - Real-time stats and activity -->
<script src="/supplier/assets/js/sidebar-widgets.js?v=<?php echo time(); ?>"></script>

<!-- Custom Portal Scripts -->
<!-- <script src="/supplier/assets/js/dashboard.js?v=<?php echo time(); ?>"></script> -->
<!-- Dashboard JavaScript is embedded in tab-dashboard.php -->

</body>
</html>
