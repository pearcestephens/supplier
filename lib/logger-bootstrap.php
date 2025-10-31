<?php
/**
 * Logger Bootstrap
 *
 * Automatically initializes logger and tracks page views
 * Include this file in bootstrap.php to enable automatic logging
 *
 * @package SupplierPortal
 * @version 1.0
 */

// Initialize logger
global $logger;

if (!isset($logger)) {
    require_once __DIR__ . '/SupplierLogger.php';

    // Get supplier info from session
    $supplierId = $_SESSION['supplier_id'] ?? null;
    $supplierName = $_SESSION['supplier_name'] ?? null;

    // Create logger instance
    $logger = new SupplierLogger($pdo, $supplierId, $supplierName);

    // Log page view
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    $logger->log(
        'page_viewed',
        SupplierLogger::CATEGORY_SYSTEM,
        [
            'page' => $currentPage,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ],
        SupplierLogger::LEVEL_DEBUG
    );
}

/**
 * Helper function for quick logging
 *
 * @param string $action Action performed
 * @param string $category Category (use SupplierLogger::CATEGORY_* constants)
 * @param array $data Additional data
 * @param string $level Severity level
 */
function logSupplierAction($action, $category, $data = [], $level = SupplierLogger::LEVEL_INFO) {
    global $logger;
    if ($logger) {
        return $logger->log($action, $category, $data, $level);
    }
    return false;
}

/**
 * Log API call with automatic timing
 *
 * @param string $endpoint API endpoint
 * @param int $statusCode HTTP status code
 * @param float $startTime Microtime when request started
 */
function logAPICall($endpoint, $statusCode, $startTime = null) {
    global $logger;
    if ($logger) {
        $responseTime = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : null;
        return $logger->logAPICall(
            $endpoint,
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $statusCode,
            $responseTime
        );
    }
    return false;
}
