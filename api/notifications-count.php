<?php
/**
 * Supplier Portal - Notifications Count API
 *
 * Returns real-time count of pending items for supplier
 *
 * @package CIS\Supplier\API
 * @version 4.0.0 - Unified with bootstrap
 */

declare(strict_types=1);

// Load bootstrap (unified initialization with error handlers)
require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication (uses bootstrap helpers)
requireAuth();
$supplierID = getSupplierID();
$pdo = pdo();

try {
    // ========================================================================
    // Get all notification counts (PDO version)
    // ========================================================================

    // 1. Pending warranty claims
    $claimsQuery = "
        SELECT COUNT(fp.id) as count
        FROM faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
          AND fp.supplier_status = 0
          AND fp.status IN ('pending', 'open', 'new')
    ";
    $stmt = $pdo->prepare($claimsQuery);
    $stmt->execute([$supplierID]);
    $pendingClaims = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 2. Urgent deliveries (orders expected in next 7 days)
    $urgentQuery = "
        SELECT COUNT(*) as count
        FROM vend_consignments
        WHERE supplier_id = ?
          AND deleted_at IS NULL
          AND state IN ('OPEN', 'PACKING')
          AND expected_delivery_date IS NOT NULL
          AND expected_delivery_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
          AND expected_delivery_date >= NOW()
    ";
    $stmt = $pdo->prepare($urgentQuery);
    $stmt->execute([$supplierID]);
    $urgentDeliveries = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 3. Overdue claims (pending > 7 days)
    $overdueQuery = "
        SELECT COUNT(fp.id) as count
        FROM faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
          AND fp.supplier_status = 0
          AND DATEDIFF(NOW(), fp.time_created) > 7
    ";
    $stmt = $pdo->prepare($overdueQuery);
    $stmt->execute([$supplierID]);
    $overdueClaims = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Calculate total count
    $totalCount = $pendingClaims + $urgentDeliveries + $overdueClaims;

    // Determine urgency level
    $urgency = 'normal';
    if ($overdueClaims > 0) {
        $urgency = 'critical';
    } elseif ($urgentDeliveries > 0 || $pendingClaims > 5) {
        $urgency = 'warning';
    }

    // Return response
    sendJsonResponse(true, [
        'count' => $totalCount,
        'urgency' => $urgency,
        'breakdown' => [
            'pending_claims' => $pendingClaims,
            'urgent_deliveries' => $urgentDeliveries,
            'overdue_claims' => $overdueClaims
        ],
        'messages' => [
            'pending' => $pendingClaims > 0 ? "{$pendingClaims} warranty claim" . ($pendingClaims > 1 ? 's' : '') . " pending review" : null,
            'urgent' => $urgentDeliveries > 0 ? "{$urgentDeliveries} order" . ($urgentDeliveries > 1 ? 's' : '') . " due within 7 days" : null,
            'overdue' => $overdueClaims > 0 ? "{$overdueClaims} claim" . ($overdueClaims > 1 ? 's' : '') . " overdue (>7 days)" : null
        ]
    ], 'Notifications retrieved successfully');

} catch (Exception $e) {
    sendJsonResponse(false, [
        'error_type' => 'notification_count_error',
        'message' => $e->getMessage()
    ], 'Failed to retrieve notifications', 500);

    // Log error
    error_log("Notifications API Error: " . $e->getMessage());
}
