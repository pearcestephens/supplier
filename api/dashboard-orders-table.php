<?php
/**
 * Dashboard Orders Table API
 * Returns orders requiring action (processing/pending status)
 *
 * TEST: curl https://staff.vapeshed.co.nz/supplier/api/dashboard-orders-table.php?limit=10
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();

    // Get limit from query (default 10)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = min(100, max(1, $limit)); // Between 1-100

    // Query for orders requiring action with REAL line items data
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.public_id as po_number,
            c.state as status,
            c.total_cost as total_amount,
            c.created_at,
            c.expected_delivery_date as due_date,
            o.name as outlet_name,
            COUNT(DISTINCT li.product_id) as items_count,
            SUM(li.quantity) as units_count
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        LEFT JOIN vend_consignment_line_items li ON c.id = li.transfer_id AND li.deleted_at IS NULL
        WHERE c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND c.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING')
        GROUP BY c.id, c.public_id, c.state, c.total_cost, c.created_at, c.expected_delivery_date, o.name
        ORDER BY
            CASE
                WHEN c.expected_delivery_date IS NOT NULL AND c.expected_delivery_date < CURDATE() THEN 1
                WHEN c.expected_delivery_date IS NOT NULL AND c.expected_delivery_date = CURDATE() THEN 2
                ELSE 3
            END,
            c.expected_delivery_date ASC,
            c.created_at ASC
        LIMIT ?
    ");

    $stmt->execute([$supplierID, $limit]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format orders
    $formatted = [];
    foreach ($orders as $order) {
        $createdDate = new DateTime($order['created_at']);
        $today = new DateTime();

        // Handle NULL due_date
        $dueDate = null;
        $isPriority = false;
        $isOverdue = false;
        $daysUntilDue = null;
        $dueDateFormatted = null;

        if (!empty($order['due_date'])) {
            $dueDate = new DateTime($order['due_date']);
            $dueDateFormatted = $dueDate->format('M d, Y');
            $isPriority = $dueDate <= $today;
            $isOverdue = $dueDate < $today;
            $daysUntilDue = $today->diff($dueDate)->days * ($dueDate < $today ? -1 : 1);
        }

        $formatted[] = [
            'id' => (int)$order['id'],
            'po_number' => $order['po_number'],
            'outlet' => $order['outlet_name'] ?? 'Unknown Outlet',
            'status' => $order['status'],
            'items_count' => (int)$order['items_count'],
            'units_count' => (int)$order['units_count'],
            'total_amount' => (float)$order['total_amount'],
            'created_at' => $createdDate->format('M d, Y'),
            'due_date' => $dueDateFormatted,
            'is_priority' => $isPriority,
            'is_overdue' => $isOverdue,
            'days_until_due' => $daysUntilDue
        ];
    }

    // Get total count for pagination
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM vend_consignments
        WHERE supplier_id = ?
        AND deleted_at IS NULL
        AND state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING')
    ");
    $stmt->execute([$supplierID]);
    $totalCount = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $formatted,
            'total' => $totalCount,
            'limit' => $limit,
            'showing' => count($formatted)
        ],
        'meta' => [
            'supplier_id' => $supplierID,
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Dashboard Orders Table API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load orders table',
        'message' => 'An error occurred while retrieving orders data'
    ], JSON_PRETTY_PRINT);
}
