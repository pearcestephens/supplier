<?php
/**
 * Session Debug Endpoint
 * 
 * Returns comprehensive session information for troubleshooting
 * REMOVE THIS FILE IN PRODUCTION!
 * 
 * @package SupplierPortal\Debug
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../lib/Session.php';
require_once __DIR__ . '/../lib/Auth.php';

// Start session
Session::start();

// Get debug info
$debugInfo = Session::getDebugInfo();

// Add authentication check
$debugInfo['auth_check'] = [
    'is_authenticated' => Auth::check(),
    'supplier_id' => Auth::check() ? Auth::getSupplierId() : 'NOT_AUTHENTICATED',
    'supplier_name' => Auth::check() ? Auth::getSupplierName() : 'NOT_AUTHENTICATED',
];

// Add PHP session superglobal dump (sanitized)
$debugInfo['raw_session_data'] = $_SESSION ?? [];

// Output as formatted JSON
header('Content-Type: application/json');
echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
