<?php
/**
 * Update Tracking Information
 * 
 * Allows suppliers to add/update tracking numbers for orders
 * 
 * @package CIS\Supplier\API
 * @version 4.0.0 - Unified with bootstrap
 */

declare(strict_types=1);

// Load bootstrap (unified initialization with error handlers)
require_once dirname(__DIR__) . '/bootstrap.php';

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Check authentication (uses bootstrap helpers)
requireAuth();
$supplierID = getSupplierID();
$db = db();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse(false, null, 'Invalid JSON input', 400);
    exit;
}

// Validate required fields
$orderId = $input['order_id'] ?? null;
$trackingNumber = $input['tracking_number'] ?? null;
$carrier = $input['carrier'] ?? null;

if (!$orderId || !$trackingNumber || !$carrier) {
    sendJsonResponse(false, null, 'Missing required fields: order_id, tracking_number, carrier', 400);
    exit;
}

// Verify order belongs to this supplier
$verifyQuery = "
    SELECT id, public_id 
    FROM vend_consignments 
    WHERE id = ? 
      AND supplier_id = ? 
      AND transfer_category = 'PURCHASE_ORDER'
      AND deleted_at IS NULL
";
$stmt = $db->prepare($verifyQuery);
$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    sendJsonResponse(false, null, 'Order not found or access denied', 404);
    exit;
}

// Update tracking information
$updateQuery = "
    UPDATE vend_consignments 
    SET 
        tracking_number = ?,
        tracking_carrier = ?,
        tracking_updated_at = NOW(),
        state = CASE 
            WHEN state = 'OPEN' THEN 'SENT'
            ELSE state
        END
    WHERE id = ?
";
$stmt = $db->prepare($updateQuery);
$stmt->bind_param('ssi', $trackingNumber, $carrier, $orderId);

if ($stmt->execute()) {
    $stmt->close();
    
    // Log the tracking update
    $logQuery = "
        INSERT INTO supplier_activity_log 
        (supplier_id, order_id, action_type, action_details, created_at)
        VALUES (?, ?, 'tracking_updated', ?, NOW())
    ";
    $logStmt = $db->prepare($logQuery);
    $actionDetails = json_encode([
        'tracking_number' => $trackingNumber,
        'carrier' => $carrier
    ]);
    $logStmt->bind_param('sis', $supplierID, $orderId, $actionDetails);
    $logStmt->execute();
    $logStmt->close();
    
    sendJsonResponse(true, [
        'order_id' => $orderId,
        'public_id' => $order['public_id'],
        'tracking_number' => $trackingNumber,
        'carrier' => $carrier
    ], 'Tracking information updated successfully');
} else {
    sendJsonResponse(false, [
        'error_type' => 'database_error',
        'message' => $stmt->error
    ], 'Database error', 500);
    $stmt->close();
}
