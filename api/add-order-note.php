<?php
/**
 * Add Order Note
 *
 * Allows suppliers to add notes to orders
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
$pdo = pdo();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse(false, null, 'Invalid JSON input', 400);
    exit;
}

// Validate required fields
$orderId = $input['order_id'] ?? null;
$noteText = $input['note_text'] ?? $input['note'] ?? null;

if (!$orderId || !$noteText) {
    sendJsonResponse(false, null, 'Missing required fields: order_id, note', 400);
    exit;
}

// Verify order belongs to this supplier and get username
$verifyQuery = "
    SELECT st.id, st.vend_number, s.name as supplier_name
    FROM staff_transfers st
    LEFT JOIN suppliers s ON s.id = st.supplier_id
    WHERE st.id = ?
      AND st.supplier_id = ?
";
$stmt = $pdo->prepare($verifyQuery);
$stmt->execute([$orderId, $supplierID]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    sendJsonResponse(false, null, 'Order not found or access denied', 404);
    exit;
}

// Add note to order_history table
$insertQuery = "
    INSERT INTO order_history
    (order_id, action, note, created_by, created_at)
    VALUES (?, 'Note added', ?, ?, NOW())
";
$stmt = $pdo->prepare($insertQuery);

if ($stmt->execute([$orderId, $noteText, $order['supplier_name'] ?? 'Supplier'])) {
    sendJsonResponse(true, [
        'id' => $pdo->lastInsertId(),
        'order_id' => $orderId,
        'note' => $noteText,
        'created_at' => date('Y-m-d H:i:s')
    ], 'Note added successfully');
    sendJsonResponse(false, [
        'error_type' => 'database_error'
    ], 'Failed to add note', 500);
}
