<?php
/**
 * Add Warranty Note API
 * 
 * Handles AJAX requests to add supplier notes to warranty claims
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
$pdo = pdo();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['fault_id']) || !isset($input['note'])) {
    sendJsonResponse(false, null, 'Missing required parameters', 400);
    exit;
}

$fault_id = intval($input['fault_id']);
$note = trim($input['note']);
$action = isset($input['action']) ? trim($input['action']) : null;
$internal_ref = isset($input['internal_ref']) ? trim($input['internal_ref']) : null;

if (empty($note)) {
    sendJsonResponse(false, null, 'Note cannot be empty', 400);
    exit;
}

// Verify supplier owns this claim (use PDO helper)
$claim = DatabasePDO::fetchOne("
    SELECT fp.id 
    FROM faulty_products fp
    INNER JOIN vend_products p ON fp.product_id = p.id
    WHERE fp.id = ? 
    AND p.supplier_id = ?
], [$fault_id, $supplier_id]);

if (!$claim) {
    sendJsonResponse(false, null, 'Warranty claim not found', 404);
    exit;
}

// Add note to faulty_product_notes table
try {
    DatabasePDO::execute("
        INSERT INTO faulty_product_notes 
        (faulty_product_id, supplier_id, note, action, internal_ref, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ", [$fault_id, $supplier_id, $note, $action, $internal_ref]);
    
    // Mark claim as updated by supplier
    DatabasePDO::execute("
        UPDATE faulty_products 
        SET supplier_update_status = 1
        WHERE id = ?
    ", [$fault_id]);
    
    sendJsonResponse(true, [
        'fault_id' => $fault_id,
        'note_preview' => substr($note, 0, 50) . (strlen($note) > 50 ? '...' : '')
    ], 'Note added successfully');
    
} catch (Exception $e) {
    error_log("Add warranty note error: " . $e->getMessage());
    sendJsonResponse(false, [
        'error_type' => 'database_error',
        'message' => $e->getMessage()
    ], 'Failed to add note', 500);
}
