<?php
/**
 * Request Information from Vape Shed
 * 
 * Allows suppliers to request additional information about orders
 * 
 * @package CIS\Supplier\API
 * @version 4.0.0 - Unified with bootstrap
 */

declare(strict_types=1);

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Load bootstrap (unified initialization with error handlers)
require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication (uses bootstrap helpers)
requireAuth();
$supplierID = getSupplierID();
$supplierName = Auth::getSupplierName();
$db = db();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input'
    ]);
    exit;
}

// Validate required fields
$orderId = $input['order_id'] ?? null;
$message = $input['message'] ?? null;

if (!$orderId || !$message) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: order_id, message'
    ]);
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
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Order not found or access denied'
    ]);
    exit;
}

// Create information request ticket
$insertQuery = "
    INSERT INTO supplier_info_requests 
    (supplier_id, order_id, request_message, status, created_at)
    VALUES (?, ?, ?, 'pending', NOW())
";
$stmt = $db->prepare($insertQuery);
$stmt->bind_param('sis', $supplierID, $orderId, $message);

if ($stmt->execute()) {
    $requestId = $stmt->insert_id;
    $stmt->close();
    
    // Log the request
    $logQuery = "
        INSERT INTO supplier_activity_log 
        (supplier_id, order_id, action_type, action_details, created_at)
        VALUES (?, ?, 'info_requested', ?, NOW())
    ";
    $logStmt = $db->prepare($logQuery);
    $actionDetails = json_encode([
        'request_id' => $requestId,
        'message' => $message
    ]);
    $logStmt->bind_param('sis', $supplierID, $orderId, $actionDetails);
    $logStmt->execute();
    $logStmt->close();
    
    // TODO: Send notification email to Vape Shed staff
    // mail('orders@vapeshed.co.nz', "Info Request: Order {$order['public_id']}", ...);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Information request sent to Vape Shed team',
        'data' => [
            'request_id' => $requestId,
            'order_id' => $orderId,
            'public_id' => $order['public_id'],
            'message' => $message
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt->error
    ]);
    $stmt->close();
}
