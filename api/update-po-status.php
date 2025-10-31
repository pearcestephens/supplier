<?php
/**
 * Update Purchase Order Status API
 * 
 * Handles AJAX requests to update transfer state
 * 
 * @version 4.0.0 - Unified with bootstrap
 */

declare(strict_types=1);

// Load bootstrap (unified initialization with error handlers)
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

// Check authentication (uses bootstrap helpers)
requireAuth();
$supplier_id = getSupplierID();
$conn = db();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['transfer_id']) || !isset($input['new_status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$transfer_id = intval($input['transfer_id']);
$new_status = $input['new_status'];

// Validate status
$allowed_statuses = ['SENT', 'CANCELLED'];
if (!in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

// Verify supplier owns this PO
$verify_sql = "SELECT id, state FROM vend_consignments 
               WHERE id = ? 
               AND supplier_id = ? 
               AND transfer_category = 'PURCHASE_ORDER'
               AND deleted_at IS NULL";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param('is', $transfer_id, $supplier_id);
$verify_stmt->execute();
$po = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$po) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Purchase order not found']);
    exit;
}

// Check if status change is valid
if ($po['state'] !== 'OPEN' && $new_status !== 'CANCELLED') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot update status from current state']);
    exit;
}

// Update status
$result = update_purchase_order_state($conn, $supplier_id, $transfer_id, $new_status);

if ($result) {
    // Log the action
    log_supplier_activity(
        $conn, 
        $supplier_id, 
        'update_po_status', 
        'transfer', 
        $transfer_id,
        json_encode([
            'old_status' => $po['state'],
            'new_status' => $new_status
        ])
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase order status updated successfully',
        'new_status' => $new_status
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
}
