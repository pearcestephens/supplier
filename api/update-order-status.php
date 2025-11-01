<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Update Order Status API
 *
 * Allows suppliers to update order status (e.g., mark as SENT)
 */

require_once __DIR__ . '/../bootstrap.php';

// Start timing for performance logging
$requestStartTime = microtime(true);

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supplierID = $_SESSION['supplier_id'];

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
$newStatus = $data['status'] ?? null;

// Validate inputs
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

if (!$newStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status is required']);
    exit;
}

// Valid statuses that suppliers can set
$validStatuses = ['SENT', 'CANCELLED'];
if (!in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status. Allowed: ' . implode(', ', $validStatuses)
    ]);
    exit;
}

try {
    $db = db();

    // Verify order belongs to supplier and get current status
    $stmt = $db->prepare("
        SELECT id, state, public_id
        FROM vend_consignments
        WHERE id = ?
        AND supplier_id = ?
        AND transfer_category = 'PURCHASE_ORDER'
        AND deleted_at IS NULL
    ");

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

    $currentStatus = $order['state'];

    // Validate status transition
    $statusTransitions = [
        'OPEN' => ['SENT', 'CANCELLED'],
        'SENT' => ['CANCELLED'],
        'RECEIVING' => [],
        'RECEIVED' => [],
        'CANCELLED' => []
    ];

    if (!isset($statusTransitions[$currentStatus]) ||
        !in_array($newStatus, $statusTransitions[$currentStatus])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Cannot change status from {$currentStatus} to {$newStatus}"
        ]);
        exit;
    }

    // Update order status
    $updateFields = ['state = ?'];
    $params = [$newStatus];
    $paramTypes = 's';

    // Add timestamp fields
    if ($newStatus === 'SENT') {
        $updateFields[] = 'supplier_sent_at = NOW()';
    } elseif ($newStatus === 'CANCELLED') {
        $updateFields[] = 'supplier_cancelled_at = NOW()';
    }

    $params[] = $orderId;
    $paramTypes .= 'i';

    $updateSQL = "
        UPDATE vend_consignments
        SET " . implode(', ', $updateFields) . "
        WHERE id = ?
        AND supplier_id = ?
    ";

    $params[] = $supplierID;
    $paramTypes .= 's';

    $stmt = $db->prepare($updateSQL);
    $stmt->bind_param($paramTypes, ...$params);
    $success = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($success && $affectedRows > 0) {
        // Log the status change
        $logStmt = $db->prepare("
            INSERT INTO order_status_log (order_id, old_status, new_status, changed_by, changed_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE order_id = order_id
        ");

        if ($logStmt) {
            $changedBy = 'supplier:' . $supplierID;
            $logStmt->bind_param('isss', $orderId, $currentStatus, $newStatus, $changedBy);
            $logStmt->execute();
            $logStmt->close();
        }

        // Enhanced Logger: Log status change with full context
        if (isset($logger)) {
            $logger->logOrderStatusChange(
                $orderId,
                $order['public_id'],
                $currentStatus,
                $newStatus
            );
        }

        // Log API call performance
        logAPICall('/api/update-order-status.php', 200, $requestStartTime);

        echo json_encode([
            'success' => true,
            'message' => "Order status updated to {$newStatus}",
            'data' => [
                'order_id' => $orderId,
                'public_id' => $order['public_id'],
                'old_status' => $currentStatus,
                'new_status' => $newStatus
            ]
        ]);
    } else {
        // Log failure
        logAPICall('/api/update-order-status.php', 500, $requestStartTime);

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order status'
        ]);
    }

} catch (Exception $e) {
    error_log("Update order status error: " . $e->getMessage());

    // Log error with full context
    if (isset($logger)) {
        $logger->logError(
            $e->getMessage(),
            $e->getCode(),
            [
                'order_id' => $orderId ?? null,
                'status' => $newStatus ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        );
    }

    logAPICall('/api/update-order-status.php', 500, $requestStartTime);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the order'
    ]);
}
