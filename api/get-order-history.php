<?php
/**
 * Get Order History and Notes
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $orderId = (int)($_GET['id'] ?? 0);

    if (!$orderId) {
        throw new Exception('Order ID required');
    }

    $pdo = pdo();
    $supplierID = getSupplierID();

    // Verify order belongs to supplier
    $stmt = $pdo->prepare("
        SELECT id FROM vend_consignments
        WHERE id = ? AND supplier_id = ?
    ");
    $stmt->execute([$orderId, $supplierID]);

    if (!$stmt->fetch()) {
        throw new Exception('Order not found');
    }

    // Get history
    $stmt = $pdo->prepare("
        SELECT
            action,
            note,
            created_by as user_name,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM order_history
        WHERE order_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$orderId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
