<?php
/**
 * API: Export Inventory Movements to CSV
 *
 * Exports filtered inventory movements to downloadable CSV
 */

declare(strict_types=1);

$supplierId = Auth::getSupplierId();
if (!$supplierId) {
    sendApiResponse(false, null, 'Authentication required', ['code' => 'AUTH_REQUIRED'], 401);
}

// Get filters
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

    // Get movements
    $stmt = $db->prepare("
        SELECT
            vm.created_at as movement_date,
            p.name as product_name,
            p.sku,
            vm.movement_type,
            vm.quantity,
            os.name as source_location,
            od.name as destination_location,
            vm.reference_id as reference
        FROM vend_inventory_movements vm
        INNER JOIN vend_products p ON p.id = vm.product_id
        LEFT JOIN vend_outlets os ON os.id = vm.source_outlet_id
        LEFT JOIN vend_outlets od ON od.id = vm.destination_outlet_id
        WHERE $whereClause
        ORDER BY vm.created_at DESC
        LIMIT 5000
    ");

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Create CSV in memory
    $filename = 'inventory_movements_' . date('Ymd_His') . '.csv';
    $filepath = '/tmp/' . $filename;
    $handle = fopen($filepath, 'w');

    // CSV Headers
    fputcsv($handle, [
        'Date/Time',
        'Product Name',
        'SKU',
        'Movement Type',
        'Quantity',
        'From Location',
        'To Location',
        'Reference',
        'Export Date'
    ]);

    // CSV Rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($handle, [
            $row['movement_date'],
            $row['product_name'],
            $row['sku'] ?? '',
            strtoupper($row['movement_type']),
            $row['quantity'],
            $row['source_location'] ?? '-',
            $row['destination_location'] ?? '-',
            $row['reference'] ?? '',
            date('Y-m-d H:i:s')
        ]);
    }

    fclose($handle);

    // Move to downloads directory
    $downloadsDir = __DIR__ . '/../../downloads';
    if (!is_dir($downloadsDir)) {
        mkdir($downloadsDir, 0755, true);
    }

    $finalPath = $downloadsDir . '/' . $filename;
    rename($filepath, $finalPath);

    sendApiResponse(true, [
        'filename' => $filename,
        'download_url' => '/supplier/downloads/' . $filename,
        'record_count' => $result->num_rows
    ]);

} catch (Exception $e) {
    error_log('Export inventory movements error: ' . $e->getMessage());
    sendApiResponse(false, null, 'Export failed', [
        'code' => 'EXPORT_ERROR',
        'details' => $e->getMessage()
    ], 500);
}
