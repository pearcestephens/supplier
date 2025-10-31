<?php
/**
 * Purchase Order Update API - Phase 3 Implementation
 * 
 * Handles PO modifications like status updates, notes, and supplier actions
 * Includes comprehensive audit logging and validation
 * 
 * @package SupplierPortal\API\v2
 * @version 3.0.0
 * @author CIS Development Team
 * @created October 23, 2025
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../../lib/Session.php';
require_once __DIR__ . '/../../lib/Database.php';
Session::start();
require_once __DIR__ . '/../../supplier-config.php';
require_once __DIR__ . '/_response.php';

// Authentication check
if (!isset($_SESSION['supplier_id'])) {
    apiResponse(false, null, [
        'code' => 'AUTH_REQUIRED',
        'message' => 'Authentication required'
    ]);
    exit;
}

$supplierID = $_SESSION['supplier_id'];

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiResponse(false, null, [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only POST requests are allowed'
        ]);
        exit;
    }
    
    // Database connection
    $db = Database::connect();
    $db->autocommit(false); // Start transaction
    
    // ========================================================================
    // PARAMETER VALIDATION
    // ========================================================================
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Try form data
        $input = $_POST;
    }
    
    $poId = $input['id'] ?? null;
    $action = $input['action'] ?? null;
    $data = $input['data'] ?? [];
    
    if (!$poId) {
        apiResponse(false, null, [
            'code' => 'MISSING_PARAMETER',
            'message' => 'Purchase Order ID is required'
        ]);
        exit;
    }
    
    if (!$action) {
        apiResponse(false, null, [
            'code' => 'MISSING_PARAMETER',
            'message' => 'Action is required'
        ]);
        exit;
    }
    
    // Valid actions for suppliers
    $validActions = ['acknowledge', 'mark_sent', 'cancel', 'add_note', 'update_delivery_date'];
    if (!in_array($action, $validActions)) {
        apiResponse(false, null, [
            'code' => 'INVALID_ACTION',
            'message' => 'Invalid action specified',
            'valid_actions' => $validActions
        ]);
        exit;
    }
    
    // ========================================================================
    // VERIFY PO EXISTS AND BELONGS TO SUPPLIER
    // ========================================================================
    
    $poQuery = "
        SELECT 
            id, public_id, state, supplier_id, version, locked_at, locked_by, lock_expires_at,
            supplier_sent_at, supplier_cancelled_at, supplier_acknowledged_at, expected_delivery_date
        FROM vend_consignments 
        WHERE id = ? 
        AND transfer_category = 'PURCHASE_ORDER' 
        AND supplier_id = ? 
        AND deleted_at IS NULL
    ";
    
    $stmt = $db->prepare($poQuery);
    $stmt->bind_param('is', $poId, $supplierID);
    $stmt->execute();
    $result = $stmt->get_result();
    $po = $result->fetch_assoc();
    $stmt->close();
    
    if (!$po) {
        $db->rollback();
        apiResponse(false, null, [
            'code' => 'PO_NOT_FOUND',
            'message' => 'Purchase Order not found or access denied'
        ]);
        exit;
    }
    
    // Check if PO is locked
    if ($po['locked_at'] && $po['lock_expires_at'] && strtotime($po['lock_expires_at']) > time()) {
        $db->rollback();
        apiResponse(false, null, [
            'code' => 'PO_LOCKED',
            'message' => 'Purchase Order is currently locked by another user',
            'locked_until' => $po['lock_expires_at']
        ]);
        exit;
    }
    
    // ========================================================================
    // PROCESS ACTIONS
    // ========================================================================
    
    $changes = [];
    $logData = [
        'action' => $action,
        'old_data' => [],
        'new_data' => [],
        'metadata' => $data
    ];
    
    switch ($action) {
        case 'acknowledge':
            if ($po['supplier_acknowledged_at']) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'ALREADY_ACKNOWLEDGED',
                    'message' => 'Purchase Order has already been acknowledged'
                ]);
                exit;
            }
            
            $changes['supplier_acknowledged_at'] = date('Y-m-d H:i:s');
            $logData['old_data']['supplier_acknowledged_at'] = null;
            $logData['new_data']['supplier_acknowledged_at'] = $changes['supplier_acknowledged_at'];
            break;
            
        case 'mark_sent':
            if ($po['supplier_sent_at']) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'ALREADY_SENT',
                    'message' => 'Purchase Order has already been marked as sent'
                ]);
                exit;
            }
            
            if (!in_array($po['state'], ['OPEN', 'PACKING', 'PACKAGED'])) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'INVALID_STATE',
                    'message' => 'Cannot mark as sent in current state: ' . $po['state']
                ]);
                exit;
            }
            
            $changes['supplier_sent_at'] = date('Y-m-d H:i:s');
            $changes['state'] = 'SENT';
            
            // Optional tracking number
            if (!empty($data['tracking_number'])) {
                $changes['tracking_number'] = trim($data['tracking_number']);
                $logData['new_data']['tracking_number'] = $changes['tracking_number'];
            }
            
            $logData['old_data']['supplier_sent_at'] = null;
            $logData['old_data']['state'] = $po['state'];
            $logData['new_data']['supplier_sent_at'] = $changes['supplier_sent_at'];
            $logData['new_data']['state'] = $changes['state'];
            break;
            
        case 'cancel':
            if ($po['supplier_cancelled_at']) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'ALREADY_CANCELLED',
                    'message' => 'Purchase Order has already been cancelled'
                ]);
                exit;
            }
            
            if (!in_array($po['state'], ['OPEN', 'PACKING', 'PACKAGED'])) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'CANNOT_CANCEL',
                    'message' => 'Cannot cancel PO in current state: ' . $po['state']
                ]);
                exit;
            }
            
            $cancelReason = trim($data['reason'] ?? '');
            if (empty($cancelReason)) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'MISSING_REASON',
                    'message' => 'Cancellation reason is required'
                ]);
                exit;
            }
            
            $changes['supplier_cancelled_at'] = date('Y-m-d H:i:s');
            $changes['state'] = 'CANCELLED';
            
            $logData['old_data']['supplier_cancelled_at'] = null;
            $logData['old_data']['state'] = $po['state'];
            $logData['new_data']['supplier_cancelled_at'] = $changes['supplier_cancelled_at'];
            $logData['new_data']['state'] = $changes['state'];
            $logData['new_data']['cancel_reason'] = $cancelReason;
            break;
            
        case 'add_note':
            $note = trim($data['note'] ?? '');
            if (empty($note)) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'MISSING_NOTE',
                    'message' => 'Note text is required'
                ]);
                exit;
            }
            
            // Insert note into consignment_logs
            $noteQuery = "
                INSERT INTO consignment_logs (
                    transfer_id, event_type, event_data, actor_user_id, 
                    severity, source_system, created_at
                ) VALUES (?, 'NOTE', ?, NULL, 'info', 'SupplierPortal', NOW())
            ";
            
            $noteData = json_encode([
                'note' => $note,
                'added_by' => 'supplier',
                'supplier_id' => $supplierID
            ]);
            
            $stmt = $db->prepare($noteQuery);
            $stmt->bind_param('is', $poId, $noteData);
            $stmt->execute();
            $stmt->close();
            
            $logData['new_data']['note'] = $note;
            break;
            
        case 'update_delivery_date':
            $newDate = $data['delivery_date'] ?? null;
            if (!$newDate || !strtotime($newDate)) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'INVALID_DATE',
                    'message' => 'Valid delivery date is required (YYYY-MM-DD format)'
                ]);
                exit;
            }
            
            // Validate date is not in the past
            if (strtotime($newDate) < strtotime(date('Y-m-d'))) {
                $db->rollback();
                apiResponse(false, null, [
                    'code' => 'PAST_DATE',
                    'message' => 'Delivery date cannot be in the past'
                ]);
                exit;
            }
            
            $changes['expected_delivery_date'] = $newDate;
            $logData['old_data']['expected_delivery_date'] = $po['expected_delivery_date'];
            $logData['new_data']['expected_delivery_date'] = $newDate;
            break;
    }
    
    // ========================================================================
    // UPDATE THE PO
    // ========================================================================
    
    if (!empty($changes)) {
        // Build update query
        $setParts = [];
        $params = [];
        $paramTypes = '';
        
        foreach ($changes as $field => $value) {
            $setParts[] = "{$field} = ?";
            $params[] = $value;
            $paramTypes .= 's';
        }
        
        // Always update version and timestamp
        $setParts[] = "version = version + 1";
        $setParts[] = "updated_at = NOW()";
        
        $updateQuery = "
            UPDATE vend_consignments 
            SET " . implode(', ', $setParts) . "
            WHERE id = ? AND supplier_id = ?
        ";
        
        $params[] = $poId;
        $params[] = $supplierID;
        $paramTypes .= 'is';
        
        $stmt = $db->prepare($updateQuery);
        $stmt->bind_param($paramTypes, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            $db->rollback();
            throw new Exception("Failed to update PO: " . $db->error);
        }
    }
    
    // ========================================================================
    // LOG THE ACTION
    // ========================================================================
    
    $auditQuery = "
        INSERT INTO consignment_logs (
            transfer_id, event_type, event_data, actor_user_id, 
            severity, source_system, created_at
        ) VALUES (?, ?, ?, NULL, 'info', 'SupplierPortal', NOW())
    ";
    
    $eventType = strtoupper($action);
    $eventData = json_encode($logData);
    
    $stmt = $db->prepare($auditQuery);
    $stmt->bind_param('iss', $poId, $eventType, $eventData);
    $stmt->execute();
    $stmt->close();
    
    // ========================================================================
    // GET UPDATED PO DATA
    // ========================================================================
    
    $updatedPoQuery = "
        SELECT 
            id, public_id, state, supplier_acknowledged_at, supplier_sent_at, 
            supplier_cancelled_at, expected_delivery_date, updated_at, version
        FROM vend_consignments 
        WHERE id = ?
    ";
    
    $stmt = $db->prepare($updatedPoQuery);
    $stmt->bind_param('i', $poId);
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedPo = $result->fetch_assoc();
    $stmt->close();
    
    // ========================================================================
    // COMMIT TRANSACTION
    // ========================================================================
    
    $db->commit();
    $db->close();
    
    // Format response data
    $responseData = [
        'po_id' => intval($updatedPo['id']),
        'public_id' => $updatedPo['public_id'],
        'action_performed' => $action,
        'new_state' => $updatedPo['state'],
        'new_version' => intval($updatedPo['version']),
        'updated_at' => date('Y-m-d H:i:s', strtotime($updatedPo['updated_at'])),
        'changes_applied' => $changes
    ];
    
    // Add specific response data based on action
    switch ($action) {
        case 'acknowledge':
            $responseData['acknowledged_at'] = $updatedPo['supplier_acknowledged_at'];
            break;
        case 'mark_sent':
            $responseData['sent_at'] = $updatedPo['supplier_sent_at'];
            break;
        case 'cancel':
            $responseData['cancelled_at'] = $updatedPo['supplier_cancelled_at'];
            break;
        case 'update_delivery_date':
            $responseData['new_delivery_date'] = $updatedPo['expected_delivery_date'];
            break;
    }
    
    apiResponse(true, $responseData, null, [
        'performance' => [
            'query_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
        $db->close();
    }
    
    error_log("PO Update API Error: " . $e->getMessage());
    apiResponse(false, null, [
        'code' => 'INTERNAL_ERROR',
        'message' => 'An error occurred while updating the purchase order',
        'debug' => $e->getMessage()
    ]);
}
?>