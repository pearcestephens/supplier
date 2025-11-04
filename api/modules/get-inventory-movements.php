<?php
/**
 * API: Get Inventory Movements
 *
 * Returns inventory movements with filtering, pagination, and stats
 */

declare(strict_types=1);

$supplierId = Auth::getSupplierId();
if (!$supplierId) {
    sendApiResponse(false, null, 'Authentication required', ['code' => 'AUTH_REQUIRED'], 401);
}

// Get filters
$page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$limit = isset($_POST['limit']) ? min(100, max(10, (int)$_POST['limit'])) : 50;
$offset = ($page - 1) * $limit;

$dateFrom = $_POST['dateFrom'] ?? '';
$dateTo = $_POST['dateTo'] ?? '';
$movementType = $_POST['movementType'] ?? '';
$outlet = $_POST['outlet'] ?? '';
$productSearch = $_POST['product'] ?? '';

try {
    // Build query
    $where = ["p.supplier_id = ?"];
    $params = [$supplierId];
    $types = 's';

    if ($dateFrom) {
        $where[] = "DATE(vm.created_at) >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }

    if ($dateTo) {
        $where[] = "DATE(vm.created_at) <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }

    if ($movementType) {
        $where[] = "vm.movement_type = ?";
        $params[] = $movementType;
        $types .= 's';
    }

    if ($outlet) {
        $where[] = "(vm.source_outlet_id = ? OR vm.destination_outlet_id = ?)";
        $params[] = $outlet;
        $params[] = $outlet;
        $types .= 'ss';
    }

    if ($productSearch) {
        $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
        $search = '%' . $productSearch . '%';
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(DISTINCT vm.id) as total
        FROM vend_inventory_movements vm
        INNER JOIN vend_products p ON p.id = vm.product_id
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = (int)$totalRow['total'];

    // Get movements
    $stmt = $db->prepare("
        SELECT
            vm.id,
            vm.product_id,
            vm.movement_type,
            vm.quantity,
            vm.created_at as movement_date,
            vm.source_outlet_id,
            vm.destination_outlet_id,
            vm.reference_id as reference,
            p.name as product_name,
            p.sku,
            os.name as source_location,
            od.name as destination_location,
            CASE
                WHEN vm.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'COMPLETED'
                WHEN vm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'COMPLETED'
                ELSE 'COMPLETED'
            END as status
        FROM vend_inventory_movements vm
        INNER JOIN vend_products p ON p.id = vm.product_id
        LEFT JOIN vend_outlets os ON os.id = vm.source_outlet_id
        LEFT JOIN vend_outlets od ON od.id = vm.destination_outlet_id
        WHERE $whereClause
        ORDER BY vm.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $movements = [];
    while ($row = $result->fetch_assoc()) {
        $movements[] = [
            'id' => $row['id'],
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'sku' => $row['sku'],
            'movement_type' => strtoupper($row['movement_type']),
            'quantity' => (int)$row['quantity'],
            'movement_date' => $row['movement_date'],
            'source_location' => $row['source_location'],
            'destination_location' => $row['destination_location'],
            'reference' => $row['reference'],
            'status' => $row['status']
        ];
    }

    // Calculate stats
    $statsStmt = $db->prepare("
        SELECT
            SUM(CASE WHEN vm.movement_type = 'IN' THEN vm.quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN vm.movement_type = 'OUT' THEN vm.quantity ELSE 0 END) as total_out,
            SUM(CASE WHEN vm.movement_type = 'ADJUSTMENT' THEN vm.quantity ELSE 0 END) as total_adjust,
            COUNT(*) as total
        FROM vend_inventory_movements vm
        INNER JOIN vend_products p ON p.id = vm.product_id
        WHERE $whereClause
    ");
    $statsTypes = substr($types, 0, -2); // Remove limit and offset types
    $statsParams = array_slice($params, 0, -2); // Remove limit and offset values
    $statsStmt->bind_param($statsTypes, ...$statsParams);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();

    sendApiResponse(true, [
        'movements' => $movements,
        'stats' => [
            'total_in' => (int)$stats['total_in'],
            'total_out' => (int)$stats['total_out'],
            'total_adjust' => (int)$stats['total_adjust'],
            'total' => (int)$stats['total']
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);

} catch (Exception $e) {
    error_log('Get inventory movements error: ' . $e->getMessage());
    sendApiResponse(false, null, 'Failed to retrieve movements', [
        'code' => 'QUERY_ERROR',
        'details' => $e->getMessage()
    ], 500);
}
