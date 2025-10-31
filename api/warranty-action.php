<?php
/**
 * Supplier Portal - Warranty Action API
 * 
 * Accept or decline warranty claims
 * Updates faulty_products table and creates supplier_warranty_notes
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
    // Fallback to POST for form data
    $input = $_POST;
}

$action = $input['action'] ?? '';
$faultID = isset($input['fault_id']) ? (int)$input['fault_id'] : 0;
$resolution = $input['resolution'] ?? '';
$reason = $input['reason'] ?? '';

// Validation
if (!in_array($action, ['accept', 'decline'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be "accept" or "decline"']);
    exit;
}

if ($faultID <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid fault_id']);
    exit;
}

if ($action === 'accept' && trim($resolution) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Resolution notes are required when accepting a claim']);
    exit;
}

if ($action === 'decline' && trim($reason) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Decline reason is required']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // STEP 1: Verify this claim belongs to this supplier and is pending
    $verifyQuery = "
        SELECT fp.id, fp.supplier_status, p.name as product_name
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
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Warranty claim not found or does not belong to your supplier account'
        ]);
        $conn->rollback();
        exit;
    }
    
    // Check if already processed
    if ($claim['supplier_status'] != 0) {
        $statusText = $claim['supplier_status'] == 1 ? 'accepted' : 'declined';
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => "This claim has already been {$statusText}"
        ]);
        $conn->rollback();
        exit;
    }
    
    // STEP 2: Update faulty_products table
    $newStatus = $action === 'accept' ? 1 : 2;
    
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
    
    // STEP 3: Insert into supplier_warranty_notes
    $note = $action === 'accept' ? $resolution : $reason;
    $actionTaken = $action; // 'accept' or 'decline'
    
    $noteQuery = "
        INSERT INTO supplier_warranty_notes 
        (fault_id, supplier_id, note, action_taken, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ";
    
    $noteStmt = $conn->prepare($noteQuery);
    $noteStmt->bind_param('isss', $faultID, $supplierID, $note, $actionTaken);
    $noteStmt->execute();
    $noteStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Success response
    $response = [
        'success' => true,
        'message' => $action === 'accept' 
            ? 'Warranty claim accepted successfully' 
            : 'Warranty claim declined successfully',
        'fault_id' => $faultID,
        'action' => $action,
        'product_name' => $claim['product_name'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($action === 'accept') {
        $response['resolution'] = $resolution;
    } else {
        $response['reason'] = $reason;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    
    // Log error for debugging
    error_log('Warranty Action Error: ' . $e->getMessage() . ' | Fault ID: ' . $faultID . ' | Supplier: ' . $supplierID);
}

$conn->close();
