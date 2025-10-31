<?php
/**
 * HTML Head Component - Reusable HTML <head> section
 *
 * Provides consistent DOCTYPE, meta tags, and CSS includes across all pages.
 * Set $pageTitle variable before including this file.
 *
 * Usage:
 *   $pageTitle = 'Dashboard';
 *   include __DIR__ . '/components/html-head.php';
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Supplier Portal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - The Vape Shed Supplier Portal</title>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Chart.js for Flip Card Area Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- SweetAlert2 for confirmations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php if (function_exists('loadCSS')): ?>
        <?php loadCSS('assets/css'); ?>
    <?php else: ?>
        <!-- Fallback: direct links if asset loader unavailable -->
        <link rel="stylesheet" href="/supplier/assets/css/01-base.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="/supplier/assets/css/02-ux-enhancements.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="/supplier/assets/css/03-dashboard-metrics.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="/supplier/assets/css/04-order-management.css?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body>
