<?php
/**
 * Update Order Status and Details
 * Allows status changes within 24 hours
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int)($input['order_id'] ?? 0);
    $newStatus = $input['status'] ?? '';
    $carrier = $input['carrier'] ?? '';
    $note = $input['note'] ?? '';

    if (!$orderId) {
        throw new Exception('Order ID required');
    }

    $pdo = pdo();
    $supplierID = getSupplierID();

    // Get current order
    $stmt = $pdo->prepare("
        SELECT id, state, updated_at, supplier_id
        FROM staff_transfers
        WHERE id = ? AND supplier_id = ?
    ");
    $stmt->execute([$orderId, $supplierID]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Check if status can be changed
    $updatedAt = new DateTime($order['updated_at']);
    $now = new DateTime();
    $hoursSinceUpdate = ($now->getTimestamp() - $updatedAt->getTimestamp()) / 3600;

    // Can change status if:
    // 1. Not RECEIVED or RECEIVING
    // 2. Within 24 hours of last update
    // 3. Only between OPEN and SENT
    $canChangeStatus = !in_array($order['state'], ['RECEIVED', 'RECEIVING']) && $hoursSinceUpdate < 24;

    if ($newStatus && $newStatus !== $order['state']) {
        if (!$canChangeStatus) {
            throw new Exception('Status cannot be changed (>24 hours or order received)');
        }

        if (!in_array($newStatus, ['OPEN', 'SENT'])) {
            throw new Exception('Can only change status between OPEN and SENT');
        }

        // Update status
        $stmt = $pdo->prepare("
            UPDATE staff_transfers
            SET state = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $orderId]);

        // Log the change
        $stmt = $pdo->prepare("
            INSERT INTO order_history (order_id, action, note, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $orderId,
            "Status changed to $newStatus",
            $note ?: "Status updated by supplier"
        ]);
    }

    // Update carrier if provided
    if ($carrier) {
        $stmt = $pdo->prepare("
            UPDATE staff_transfers
            SET carrier_name = ?
            WHERE id = ?
        ");
        $stmt->execute([$carrier, $orderId]);
    }

    // Add note if provided and no status change
    if ($note && !$newStatus) {
        $stmt = $pdo->prepare("
            INSERT INTO order_history (order_id, action, note, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $orderId,
            'Note added',
            $note
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
