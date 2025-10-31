<?php
/**
 * Supplier Portal - Main Entry Point (AdminLTE Professional Theme)
 * 
 * Single entry point using AdminLTE 3 Bootstrap admin template
 * Mobile-responsive, production-ready UI
 * 
 * @package SupplierPortal
 * @version 2.0
 */

declare(strict_types=1);

// Start session
session_start();

// Load bootstrap (database connection)
require_once __DIR__ . '/../bootstrap.php';

// Load functions
require_once __DIR__ . '/includes/functions-real.php';

// Get requested page
$page = $_GET['page'] ?? 'dashboard';

// ============================================================================
// AUTHENTICATION CHECK (URL-Based with Session)
// ============================================================================

// Handle logout
if ($page === 'logout') {
    if (isset($_SESSION['supplier_session_token'])) {
        $supplier_id = validate_session($conn, $_SESSION['supplier_session_token']);
        if ($supplier_id) {
            log_supplier_activity($conn, $supplier_id, 'logout');
        }
        
        // Delete session from database
        $stmt = $conn->prepare("DELETE FROM supplier_portal_sessions WHERE session_token = ?");
        $stmt->bind_param('s', $_SESSION['supplier_session_token']);
        $stmt->execute();
    }
    
    session_destroy();
    
    // Show logout message
    echo '<!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Logged Out</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    </head><body class="hold-transition" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="card" style="width: 400px; text-align: center; padding: 30px;">
        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
        <h3 class="mt-3">Logged Out Successfully</h3>
        <p class="text-muted">You have been logged out of the supplier portal.</p>
        <p class="mt-3"><small>You can close this window or access the portal again via your unique link.</small></p>
    </div>
    </body></html>';
    exit;
}

// Check for supplier_id in URL (new session creation)
if (isset($_GET['supplier_id'])) {
    $url_supplier_id = strtoupper(trim($_GET['supplier_id']));
    
    // Validate supplier exists
    $supplier = get_supplier($conn, $url_supplier_id);
    
    if ($supplier === null) {
        // Invalid supplier ID
        echo '<!DOCTYPE html>
        <html><head><meta charset="utf-8"><title>Access Denied</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        </head><body class="hold-transition" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh;">
        <div class="card" style="width: 400px; text-align: center; padding: 30px;">
            <i class="fas fa-times-circle text-danger" style="font-size: 4rem;"></i>
            <h3 class="mt-3">Access Denied</h3>
            <p class="text-muted">Invalid Supplier ID: <strong>' . htmlspecialchars($url_supplier_id) . '</strong></p>
            <p class="mt-3"><small>Please contact The Vape Shed support if you believe this is an error.</small></p>
        </div>
        </body></html>';
        exit;
    }
    
    // Create new session
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $session_token = create_supplier_session($conn, $url_supplier_id, $ip_address, $user_agent);
    
    // Store in PHP session
    $_SESSION['supplier_session_token'] = $session_token;
    $_SESSION['supplier_id'] = $url_supplier_id;
    
    // Log activity
    log_supplier_activity($conn, $url_supplier_id, 'login', null, null, json_encode(['method' => 'url_auth']));
    
    // Redirect to dashboard (clean URL without supplier_id parameter)
    header('Location: ?page=dashboard');
    exit;
}

// Check if session exists
if (!isset($_SESSION['supplier_session_token'])) {
    // No session - show access instructions
    echo '<!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Supplier Portal Access</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </head><body class="hold-transition" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="card" style="width: 500px; padding: 40px;">
        <div class="text-center mb-4">
            <i class="fas fa-lock text-primary" style="font-size: 4rem;"></i>
            <h2 class="mt-3">Supplier Portal</h2>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>Access Required</strong>
        </div>
        <p>This supplier portal requires authentication via a unique access link.</p>
        <p>Your unique portal link format:</p>
        <div class="bg-light p-3 rounded text-center">
            <code style="font-size: 1.1rem;">?supplier_id=YOUR_SUPPLIER_ID</code>
        </div>
        <p class="mt-3 mb-0 text-muted"><small><i class="fas fa-envelope mr-1"></i> Contact The Vape Shed if you need your access link.</small></p>
    </div>
    </body></html>';
    exit;
}

// Validate existing session
$supplier_id = validate_session($conn, $_SESSION['supplier_session_token']);

if ($supplier_id === null) {
    // Session expired - clear and show message
    unset($_SESSION['supplier_session_token']);
    unset($_SESSION['supplier_id']);
    
    echo '<!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Session Expired</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </head><body class="hold-transition" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="card" style="width: 400px; text-align: center; padding: 30px;">
        <i class="fas fa-clock text-warning" style="font-size: 4rem;"></i>
        <h3 class="mt-3">Session Expired</h3>
        <p class="text-muted">Your session has expired after 24 hours of inactivity.</p>
        <p class="mt-3"><small>Please access the portal again using your unique link.</small></p>
    </div>
    </body></html>';
    exit;
}

// Load supplier data
$supplier = get_supplier($conn, $supplier_id);

if ($supplier === null) {
    // Supplier not found or deleted
    unset($_SESSION['supplier_session_token']);
    unset($_SESSION['supplier_id']);
    
    echo '<!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Account Not Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </head><body class="hold-transition" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="card" style="width: 400px; text-align: center; padding: 30px;">
        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
        <h3 class="mt-3">Account Not Found</h3>
        <p class="text-muted">Your supplier account could not be found or has been deactivated.</p>
        <p class="mt-3"><small>Please contact The Vape Shed support.</small></p>
    </div>
    </body></html>';
    exit;
}

// Load dashboard stats (used in sidebar)
$stats = get_dashboard_stats($conn, $supplier_id);

// ============================================================================
// PAGE ROUTING
// ============================================================================

// Define allowed pages
$allowed_pages = [
    'logout',
    'dashboard',
    'purchase-orders',
    'purchase-order-detail',
    'warranty-claims',
    'warranty-claim-detail',
    'analytics',
    'products',
    'downloads',
    'account',
    'notifications'
];

// Validate page
if (!in_array($page, $allowed_pages)) {
    $page = '404';
}

// Set page title
$page_titles = [
    'dashboard' => 'Dashboard',
    'purchase-orders' => 'Purchase Orders',
    'purchase-order-detail' => 'Purchase Order Details',
    'warranty-claims' => 'Warranty Claims',
    'warranty-claim-detail' => 'Warranty Claim Details',
    'analytics' => 'Analytics & Reports',
    'products' => 'Products',
    'downloads' => 'Downloads',
    'account' => 'Account Settings',
    'notifications' => 'Notifications'
];

$page_title = $page_titles[$page] ?? 'Supplier Portal';

// ============================================================================
// RENDER PAGE
// ============================================================================

// Include header
include __DIR__ . '/includes/header-adminlte.php';

// Include page content
$page_file = __DIR__ . '/pages/' . $page . '.php';

if (file_exists($page_file)) {
    include $page_file;
} else {
    include __DIR__ . '/pages/404.php';
}

// Include footer
include __DIR__ . '/includes/footer-adminlte.php';
