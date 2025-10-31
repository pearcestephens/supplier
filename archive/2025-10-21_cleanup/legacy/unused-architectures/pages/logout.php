<?php
/**
 * Logout Handler
 * 
 * NO require statements - bootstrap already loaded by index.php
 */

// Log logout activity
if (isset($_SESSION['supplier_id'])) {
    log_supplier_activity($_SESSION['supplier_id'], 'logout', 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: /supplier/?page=login');
exit;
