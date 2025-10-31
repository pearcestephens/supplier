<?php
/**
 * Purchase Order Update API
 * Handles order updates: tracking, status, notes
 * 
 * @package CIS\Supplier\API
 * @version 2.0.0
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    requireAuth();
    $supplierId = getSupplierID();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;
    $action = $input['action'] ?? null;
    
    if (!$orderId || !$action) {
        sendJsonResponse(false, null, 'Order ID and action required', 400);
        exit;
    }
    
    $pdo = pdo();
    
    // Verify order ownership
    $stmt = $pdo->prepare("
        SELECT id, state, tracking_number 
        FROM vend_consignments 
        WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$orderId, $supplierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendJsonResponse(false, null, 'Order not found or access denied', 403);
        exit;
    }
    
    // Handle different actions
    switch ($action) {
        case 'update_tracking':
            $trackingNumber = $input['tracking_number'] ?? '';
            
            $stmt = $pdo->prepare("
                UPDATE vend_consignments 
                SET tracking_number = ?,
                    updated_at = NOW()
                WHERE id = ? AND supplier_id = ?
            ");
            $stmt->execute([$trackingNumber, $orderId, $supplierId]);
            
            // Log the update
            logSupplierAction($supplierId, 'update_tracking', 'consignment', $orderId, [
                'tracking_number' => $trackingNumber
            ]);
            
            sendJsonResponse(true, [
                'tracking_number' => $trackingNumber
            ], 'Tracking number updated successfully');
            break;
            
        case 'update_status':
            $newStatus = $input['status'] ?? '';
            $allowedStatuses = ['IN_PROGRESS', 'SENT', 'RECEIVED', 'CANCELLED'];
            
            if (!in_array($newStatus, $allowedStatuses)) {
                sendJsonResponse(false, null, 'Invalid status value', 400);
                exit;
            }
            
            // Some status transitions may be restricted
            if ($order['state'] === 'RECEIVED' && $newStatus !== 'RECEIVED') {
                sendJsonResponse(false, null, 'Cannot change status of received order', 400);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE vend_consignments 
                SET state = ?,
                    updated_at = NOW()
                WHERE id = ? AND supplier_id = ?
            ");
            $stmt->execute([$newStatus, $orderId, $supplierId]);
            
            // Try to log status history (if table exists)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO consignment_status_history 
                    (consignment_id, status_from, status_to, changed_by, changed_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$orderId, $order['state'], $newStatus, $supplierId]);
            } catch (Exception $e) {
                // Table doesn't exist - that's okay
            }
            
            // Log the update
            logSupplierAction($supplierId, 'update_status', 'consignment', $orderId, [
                'status_from' => $order['state'],
                'status_to' => $newStatus
            ]);
            
            sendJsonResponse(true, [
                'status' => $newStatus,
                'previous_status' => $order['state']
            ], 'Order status updated successfully');
            break;
            
        case 'add_note':
            $note = $input['note'] ?? '';
            
            if (empty(trim($note))) {
                sendJsonResponse(false, null, 'Note content required', 400);
                exit;
            }
            
            // Check if notes table exists
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO consignment_notes 
                    (consignment_id, supplier_id, note, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$orderId, $supplierId, $note]);
                
                sendJsonResponse(true, [
                    'note' => $note,
                    'created_at' => date('Y-m-d H:i:s')
                ], 'Note added successfully');
                
            } catch (Exception $e) {
                // Table doesn't exist - use simple log instead
                logSupplierAction($supplierId, 'add_note', 'consignment', $orderId, [
                    'note' => $note
                ]);
                
                sendJsonResponse(true, [
                    'note' => $note,
                    'created_at' => date('Y-m-d H:i:s')
                ], 'Note logged successfully (via action log)');
            }
            break;
            
        default:
            sendJsonResponse(false, null, 'Unknown action: ' . $action, 400);
            break;
    }
    
} catch (Exception $e) {
    error_log("PO Update API Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Failed to update order: ' . $e->getMessage(), 500);
}

/**
 * Log supplier action
 */
function logSupplierAction($supplierId, $action, $entityType, $entityId, $data = []) {
    try {
        $pdo = pdo();
        $stmt = $pdo->prepare("
            INSERT INTO supplier_action_log 
            (supplier_id, action, entity_type, entity_id, data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $supplierId,
            $action,
            $entityType,
            $entityId,
            json_encode($data)
        ]);
    } catch (Exception $e) {
        // If table doesn't exist, just log to error log
        error_log("Supplier action: $supplierId $action $entityType:$entityId - " . json_encode($data));
    }
}
