<?php
/**
 * SECURITY GUARD FOR TAB FILES
 * 
 * Include this at the top of every tab file to ensure:
 * 1. Tab is only accessible when included from index.php
 * 2. User is authenticated
 * 3. Required variables are set
 * 
 * Usage: require_once __DIR__ . '/../_tab-security-guard.php';
 */

// Prevent direct access to tab files
if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    die('Direct access to tab files is forbidden. Access through portal only.');
}

// Verify authentication (redundant but safe)
if (!isset($supplierID) || !isset($supplierName)) {
    http_response_code(403);
    die('Authentication required. Please log in.');
}

// Verify Auth class confirms authentication
if (!Auth::check()) {
    http_response_code(403);
    die('Invalid session. Please log in again.');
}

// All security checks passed - tab file can proceed
