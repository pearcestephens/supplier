<?php
/**
 * Supplier Portal - Main Entry Point
 * 
 * This is the ONLY file that loads bootstrap.php
 * All other files are included and inherit the global scope
 * 
 * @package CIS\Supplier
 * @version 3.0.0
 */

declare(strict_types=1);

// ============================================================================
// BOOTSTRAP - Load ONCE
// ============================================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

// ============================================================================
// SUPPLIER PORTAL CONFIG - Load once
// ============================================================================

// Portal constants
define('SUPPLIER_PORTAL', true);
define('SUPPLIER_VERSION', '3.0.0');

// Load supplier-specific functions
require_once __DIR__ . '/includes/functions.php';

// ============================================================================
// SESSION & AUTHENTICATION
// ============================================================================

session_start();

// Get requested page
$page = $_GET['page'] ?? 'login';
$supplier_id = $_GET['id'] ?? $_SESSION['supplier_id'] ?? null;

// Public pages (no auth required)
$public_pages = ['login'];

// Check authentication for protected pages
if (!in_array($page, $public_pages)) {
    if (!$supplier_id || !validate_uuid($supplier_id)) {
        header('Location: /supplier/?page=login');
        exit;
    }
    
    // Verify supplier exists and is active
    $supplier = db_fetch_one(
        "SELECT * FROM vend_suppliers WHERE id = ? AND deleted_at = ''",
        [$supplier_id],
        's'
    );
    
    if (!$supplier) {
        session_destroy();
        header('Location: /supplier/?page=login&error=invalid');
        exit;
    }
    
    // Store in session
    $_SESSION['supplier_id'] = $supplier_id;
    $_SESSION['supplier_name'] = $supplier['name'];
    
    // Make supplier available globally
    $GLOBALS['supplier'] = $supplier;
    
    // Log activity
    log_supplier_activity($supplier_id, 'page_view', $page);
}

// ============================================================================
// ROUTING - Simple page includes
// ============================================================================

// Sanitize page name
$page = preg_replace('/[^a-z0-9_-]/', '', $page);

// Map pages to files
$page_files = [
    'login'     => 'pages/login.php',
    'dashboard' => 'pages/dashboard.php',
    'orders'    => 'pages/orders.php',
    'warranty'  => 'pages/warranty.php',
    'downloads' => 'pages/downloads.php',
    'reports'   => 'pages/reports.php',
    'account'   => 'pages/account.php',
    'logout'    => 'pages/logout.php',
];

// Get page file
$page_file = $page_files[$page] ?? null;

if (!$page_file || !file_exists(__DIR__ . '/' . $page_file)) {
    http_response_code(404);
    $page_file = 'pages/404.php';
}

// Include the page (it has access to $db, $supplier, all functions)
include __DIR__ . '/' . $page_file;
