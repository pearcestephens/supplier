<?php
/**
 * Supplier Portal - Entry Point
 * Redirects to dashboard.php
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// If the link carries a supplier_id, send user to password prompt screen
if (isset($_GET['supplier_id'])) {
    $supplierId = trim((string)$_GET['supplier_id']);
    $isValidFormat = (bool)preg_match('/^[a-f0-9\-]{8,}$/i', $supplierId);
    if ($supplierId === '' || !$isValidFormat) {
        header('Location: /supplier/login.php?error=invalid_id');
        exit;
    }
    header('Location: /supplier/login.php?supplier_id=' . urlencode($supplierId));
    exit;
}

// Otherwise, route based on auth status
if (Auth::check()) {
    header('Location: /supplier/dashboard.php');
} else {
    header('Location: /supplier/login.php');
}
exit;
