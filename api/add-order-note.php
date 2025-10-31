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
$note = $input['note'] ?? null;

if (!$orderId || !$note) {
    sendJsonResponse(false, null, 'Missing required fields: order_id, note', 400);
    exit;
}

// Verify order belongs to this supplier
$verifyQuery = "
    SELECT id, public_id, notes 
    FROM vend_consignments 
    WHERE id = ? 
      AND supplier_id = ? 
      AND transfer_category = 'PURCHASE_ORDER'
      AND deleted_at IS NULL
";
$stmt = $pdo->prepare($verifyQuery);
$stmt->execute([$orderId, $supplierID]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    sendJsonResponse(false, null, 'Order not found or access denied', 404);
    exit;
}

// Append new note to existing notes
$existingNotes = $order['notes'] ?? '';
$timestamp = date('Y-m-d H:i:s');
$supplierName = Auth::getSupplierName();
$newNote = "\n\n[{$timestamp}] {$supplierName}:\n{$note}";
$updatedNotes = $existingNotes . $newNote;

// Update notes
$updateQuery = "
    UPDATE vend_consignments 
    SET notes = ?
    WHERE id = ?
";
$stmt = $pdo->prepare($updateQuery);

if ($stmt->execute([$updatedNotes, $orderId])) {
    // Log the note addition
    $logQuery = "
        INSERT INTO supplier_activity_log 
        (supplier_id, order_id, action_type, action_details, created_at)
        VALUES (?, ?, 'note_added', ?, NOW())
    ";
    $logStmt = $pdo->prepare($logQuery);
    $actionDetails = json_encode(['note' => $note]);
    $logStmt->execute([$supplierID, $orderId, $actionDetails]);
    
    sendJsonResponse(true, [
        'order_id' => $orderId,
        'public_id' => $order['public_id'],
        'note' => $note
    ], 'Note added successfully');
} else {
    sendJsonResponse(false, [
        'error_type' => 'database_error'
    ], 'Failed to add note', 500);
}
