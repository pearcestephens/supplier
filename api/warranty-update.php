<?php
/**
 * Warranty Update API Endpoint
 *
 * SECURITY: Verifies supplier_id before allowing updates
 * Prevents cross-supplier tampering with warranty claims
 *
 * POST /supplier/api/warranty-update.php
 * {
 *   "fault_id": 123,
 *   "status": 1,  // 1=accepted, 2=declined
 *   "notes": "Optional response notes"
 * }
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    // Get supplier ID from session
    $supplierID = Auth::getSupplierId();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);

    $faultID = (int)($input['fault_id'] ?? 0);
    $status = (int)($input['status'] ?? 0);
    $notes = trim($input['notes'] ?? '');

    // Validation
    if (!$faultID) {
        throw new Exception('Invalid fault_id');
    }

    if (!in_array($status, [1, 2])) {
        throw new Exception('Invalid status (must be 1=accepted or 2=declined)');
    }

    // ========================================================================
    // SECURITY CHECK: Verify this warranty claim belongs to this supplier
    // ========================================================================

    $db = db();

    // Check: Is this fault_id for a product supplied by this supplier?
    $verifyStmt = $db->prepare("
        SELECT fp.id
        FROM faulty_products fp
        INNER JOIN vend_products p ON fp.product_id = p.id
        WHERE fp.id = ?
        AND p.supplier_id = ?
        LIMIT 1
    ");

    if (!$verifyStmt) {
        throw new Exception('Database error: ' . $db->error);
    }

    $verifyStmt->bind_param('is', $faultID, $supplierID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows === 0) {
        // Unauthorized: This warranty claim is not for a product this supplier supplies
        sendJsonResponse(false, [
            'error' => 'Unauthorized',
            'message' => 'This warranty claim does not belong to your supplied products'
        ], 'Authorization failed', 403);
        exit;
    }

    $verifyStmt->close();

    // ========================================================================
    // UPDATE WARRANTY CLAIM (with security verified)
    // ========================================================================

    $timestamp = date('Y-m-d H:i:s');

    $updateStmt = $db->prepare("
        UPDATE faulty_products
        SET
            supplier_status = ?,
            supplier_status_timestamp = ?,
            supplier_notes = ?
        WHERE id = ?
        AND product_id IN (
            SELECT id FROM vend_products WHERE supplier_id = ?
        )
        LIMIT 1
    ");

    if (!$updateStmt) {
        throw new Exception('Database error: ' . $db->error);
    }

    $updateStmt->bind_param('isssi', $status, $timestamp, $notes, $faultID, $supplierID);

    if (!$updateStmt->execute()) {
        throw new Exception('Update failed: ' . $updateStmt->error);
    }

    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affectedRows === 0) {
        throw new Exception('No rows updated. Warranty claim may not exist.');
    }

    // ========================================================================
    // LOG ACTION
    // ========================================================================

    $statusLabel = $status === 1 ? 'Accepted' : 'Declined';
    $logStmt = $db->prepare("
        INSERT INTO supplier_activity_log
        (supplier_id, action_type, action_details, created_at)
        VALUES (?, 'warranty_update', ?, NOW())
    ");

    if ($logStmt) {
        $logDetails = "Updated warranty claim #{$faultID} to {$statusLabel}";
        $logStmt->bind_param('ss', $supplierID, $logDetails);
        $logStmt->execute();
        $logStmt->close();
    }

    // ========================================================================
    // SUCCESS RESPONSE
    // ========================================================================

    sendJsonResponse(true, [
        'fault_id' => $faultID,
        'status' => $status,
        'status_label' => $statusLabel,
        'timestamp' => $timestamp,
        'message' => "Warranty claim updated successfully"
    ], 'Warranty claim updated');

} catch (Exception $e) {
    error_log('Warranty Update API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'warranty_update_error',
        'message' => $e->getMessage()
    ], 'Failed to update warranty claim', 500);
}
