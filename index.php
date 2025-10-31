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
