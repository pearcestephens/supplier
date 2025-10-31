<?php
/**
 * Get AI Insights API
 *
 * Returns AI-powered insights and analytics from activity logs
 *
 * @package SupplierPortal
 * @version 1.0
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($logger)) {
        throw new Exception('Logger not initialized');
    }

    // Get AI insights
    $insights = $logger->getAIInsights();

    // Get activity summary
    $todaySummary = $logger->getActivitySummary('today');
    $weekSummary = $logger->getActivitySummary('week');
    $monthSummary = $logger->getActivitySummary('month');

    echo json_encode([
        'success' => true,
        'insights' => $insights,
        'activity_summary' => [
            'today' => $todaySummary,
            'week' => $weekSummary,
            'month' => $monthSummary
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Get AI insights error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate AI insights',
        'error' => $e->getMessage()
    ]);
}
