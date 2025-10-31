<?php
/**
 * Supplier Portal - Main Entry Point (STANDALONE VERSION)
 * 
 * Modern tab-based interface for wholesale suppliers
 * Uses custom Database, Session, and Auth managers
 * 
 * @package Supplier
 * @version 2.0.0
 */

declare(strict_types=1);

// Load standalone libraries
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';

// Start session
Session::start();

// ============================================================================
// AUTHENTICATION: Allow supplier_id via GET parameter or existing session
// ============================================================================

// Check for supplier_id in GET parameter (for testing/direct access)
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    $supplierId = trim($_GET['supplier_id']);
    
    // Attempt login
    if (Auth::loginById($supplierId)) {
        // Successfully authenticated - redirect to clean URL
        header('Location: /supplier/');
        exit;
    } else {
        // Invalid supplier_id
        http_response_code(404);
        die('Supplier not found');
    }
}

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    die('Authentication required. Please provide a valid supplier_id parameter.');
}

// Get authenticated supplier data
$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// ============================================================================
// LOAD CONFIGURATION
// ============================================================================

require_once __DIR__ . '/supplier-config.php';

// ============================================================================
// TAB ROUTING
// ============================================================================

$currentTab = $_GET['tab'] ?? 'dashboard';
$validTabs = ['dashboard', 'orders', 'warranty', 'downloads', 'reports', 'account'];

if (!in_array($currentTab, $validTabs)) {
    $currentTab = 'dashboard';
}

// ============================================================================
// PAGE TITLE
// ============================================================================

$pageTitles = [
    'dashboard' => 'Dashboard Overview',
    'orders' => 'Purchase Orders',
    'warranty' => 'Warranty Claims',
    'downloads' => 'Downloads & Resources',
    'reports' => 'Sales Reports',
    'account' => 'Account Settings',
];

$pageTitle = $pageTitles[$currentTab] ?? 'Dashboard';

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($supplierName) ?> Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/supplier/assets/css/supplier-portal.css" rel="stylesheet">
    
    <!-- CSRF Token -->
    <?= Session::csrfMeta() ?>
</head>
<body>
    <!-- Header -->
    <?php require_once __DIR__ . '/components/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <?php require_once __DIR__ . '/components/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="content-wrapper">
                    <!-- Tab Content -->
                    <?php
                    $tabFile = __DIR__ . '/tabs/tab-' . $currentTab . '.php';
                    
                    if (file_exists($tabFile)) {
                        require_once $tabFile;
                    } else {
                        echo '<div class="alert alert-warning">Tab not found: ' . htmlspecialchars($currentTab) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php require_once __DIR__ . '/components/footer.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/supplier/assets/js/supplier-portal.js"></script>
    
    <!-- Debug Info (Development Only) -->
    <?php if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT): ?>
    <div style="position: fixed; bottom: 10px; right: 10px; background: #000; color: #0f0; padding: 10px; font-family: monospace; font-size: 11px; border-radius: 5px; z-index: 9999;">
        <strong>Debug Info:</strong><br>
        Supplier: <?= htmlspecialchars($supplierID) ?><br>
        Tab: <?= htmlspecialchars($currentTab) ?><br>
        DB Queries: <?= count(Database::getQueryLog()) ?><br>
        Session ID: <?= substr(Session::getId(), 0, 8) ?>...<br>
        Memory: <?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB
    </div>
    <?php endif; ?>
</body>
</html>
