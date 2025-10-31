<?php
/**
 * Supplier Portal - Update Warranty Claim API
 * 
 * General-purpose warranty claim status updater
 * Handles AJAX requests to update warranty claim status
 * 
 * @package CIS\Supplier\API
 * @version 4.0.0 - Unified with bootstrap
 */

declare(strict_types=1);

// Load bootstrap (unified initialization with error handlers)
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

// Check authentication (uses bootstrap helpers)
requireAuth();
$supplierID = getSupplierID();
$conn = db();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST; // Fallback
}

if (!isset($input['fault_id']) || !isset($input['status'])) {
    sendJsonResponse(false, null, 'Missing required parameters: fault_id and status', 400);
    exit;
}

$faultID = (int)$input['fault_id'];
$newStatus = (int)$input['status'];
$action = $input['action'] ?? null;
$note = $input['note'] ?? '';

// Validate status value (0=pending, 1=accepted, 2=declined)
if (!in_array($newStatus, [0, 1, 2])) {
    sendJsonResponse(false, null, 'Invalid status value. Must be 0 (pending), 1 (accepted), or 2 (declined)', 400);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verify supplier owns this claim
    $verifyQuery = "
        SELECT fp.id, fp.supplier_status 
        FROM faulty_products fp
        LEFT JOIN vend_products p ON fp.product_id = p.id
        WHERE fp.id = ? 
          AND p.supplier_id = ?
    ";
    
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param('is', $faultID, $supplierID);
    $verifyStmt->execute();
    $claim = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if (!$claim) {
        sendJsonResponse(false, null, 'Warranty claim not found or access denied', 404);
        $conn->rollback();
        exit;
    }
    
    $oldStatus = $claim['supplier_status'];
    
    // Update status
    $updateQuery = "
        UPDATE faulty_products 
        SET supplier_status = ?,
            supplier_update_status = 1,
            supplier_status_timestamp = NOW()
        WHERE id = ?
    ";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param('ii', $newStatus, $faultID);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Automatically add a note if action is provided
    if ($action || trim($note) !== '') {
        $autoNotes = [
            'APPROVED' => 'Claim has been approved by supplier.',
            'DECLINED' => 'Claim has been declined by supplier.',
            'MORE_INFO_REQUESTED' => 'Supplier has requested additional information.',
            'REOPENED' => 'Claim has been reopened for further review.'
        ];
        
        $noteText = trim($note) !== '' ? $note : ($autoNotes[$action] ?? 'Status updated');
        $actionTaken = $action ?? 'status_update';
        
        $noteQuery = "
            INSERT INTO supplier_warranty_notes 
            (fault_id, supplier_id, note, action_taken, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        $noteStmt = $conn->prepare($noteQuery);
        $noteStmt->bind_param('isss', $faultID, $supplierID, $noteText, $actionTaken);
        $noteStmt->execute();
        $noteStmt->close();
    }
    
    $conn->commit();
    
    // Success response
    sendJsonResponse(true, [
        'fault_id' => $faultID,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Warranty claim updated successfully');
    
} catch (Exception $e) {
    $conn->rollback();
    
    sendJsonResponse(false, [
        'error_type' => 'database_error',
        'message' => $e->getMessage()
    ], 'Failed to update warranty claim', 500);
    
    error_log('Update Warranty Claim Error: ' . $e->getMessage() . ' | Fault ID: ' . $faultID . ' | Supplier: ' . $supplierID);
}

$conn->close();
