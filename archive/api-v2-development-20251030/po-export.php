<?php
/**
 * Purchase Order Export API - Phase 3 Implementation
 * 
 * Bulk export functionality for POs in multiple formats (CSV, PDF)
 * Supports filtering and large dataset handling
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
    // Database connection
    $db = Database::connect();
    
    // ========================================================================
    // PARAMETER VALIDATION
    // ========================================================================
    
    $format = strtolower($_GET['format'] ?? 'csv');
    $validFormats = ['csv', 'json', 'excel'];
    
    if (!in_array($format, $validFormats)) {
        apiResponse(false, null, [
            'code' => 'INVALID_FORMAT',
            'message' => 'Invalid export format',
            'valid_formats' => $validFormats
        ]);
        exit;
    }
    
    // Export type
    $exportType = $_GET['type'] ?? 'summary'; // summary, detailed, items_only
    $validTypes = ['summary', 'detailed', 'items_only'];
    
    if (!in_array($exportType, $validTypes)) {
        $exportType = 'summary';
    }
    
    // Filters (same as po-list.php)
    $filters = [
        'states' => $_GET['states'] ?? [],
        'outlets' => $_GET['outlets'] ?? [],
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'value_min' => $_GET['value_min'] ?? null,
        'value_max' => $_GET['value_max'] ?? null,
        'search' => trim($_GET['search'] ?? ''),
        'po_ids' => $_GET['po_ids'] ?? [], // Specific PO IDs for bulk export
    ];
    
    // Ensure arrays
    if (!is_array($filters['states'])) {
        $filters['states'] = $filters['states'] ? explode(',', $filters['states']) : [];
    }
    if (!is_array($filters['outlets'])) {
        $filters['outlets'] = $filters['outlets'] ? explode(',', $filters['outlets']) : [];
    }
    if (!is_array($filters['po_ids'])) {
        $filters['po_ids'] = $filters['po_ids'] ? explode(',', $filters['po_ids']) : [];
    }
    
    // Validate states
    $validStates = ['DRAFT', 'OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL', 'RECEIVED', 'CLOSED', 'CANCELLED', 'ARCHIVED'];
    $filters['states'] = array_intersect($filters['states'], $validStates);
    
    // Limit protection
    $maxRecords = 10000; // Prevent memory issues
    $limit = min($maxRecords, intval($_GET['limit'] ?? $maxRecords));
    
    // ========================================================================
    // BUILD EXPORT QUERY
    // ========================================================================
    
    switch ($exportType) {
        case 'summary':
            $query = buildSummaryQuery($filters, $supplierID, $limit);
            break;
        case 'detailed':
            $query = buildDetailedQuery($filters, $supplierID, $limit);
            break;
        case 'items_only':
            $query = buildItemsQuery($filters, $supplierID, $limit);
            break;
    }
    
    // ========================================================================
    // EXECUTE QUERY
    // ========================================================================
    
    $stmt = $db->prepare($query['sql']);
    if (!$stmt) {
        throw new Exception("Failed to prepare export query: " . $db->error);
    }
    
    if (!empty($query['params'])) {
        $stmt->bind_param($query['param_types'], ...$query['params']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    $db->close();
    
    if (empty($data)) {
        apiResponse(false, null, [
            'code' => 'NO_DATA',
            'message' => 'No data found for export with the specified filters'
        ]);
        exit;
    }
    
    // ========================================================================
    // GENERATE EXPORT
    // ========================================================================
    
    $filename = generateFilename($exportType, $format);
    
    switch ($format) {
        case 'csv':
            exportCSV($data, $filename);
            break;
        case 'json':
            exportJSON($data, $filename);
            break;
        case 'excel':
            exportExcel($data, $filename, $exportType);
            break;
    }
    
} catch (Exception $e) {
    error_log("PO Export API Error: " . $e->getMessage());
    apiResponse(false, null, [
        'code' => 'INTERNAL_ERROR',
        'message' => 'An error occurred during export',
        'debug' => $e->getMessage()
    ]);
}

/**
 * Build summary export query
 */
function buildSummaryQuery(array $filters, string $supplierID, int $limit): array {
    $sql = "
        SELECT 
            t.public_id as 'PO Number',
            t.state as 'Status',
            DATE(t.created_at) as 'Created Date',
            t.expected_delivery_date as 'Expected Delivery',
            o.name as 'Outlet Name',
            o.store_code as 'Outlet Code',
            COUNT(DISTINCT ti.id) as 'Items Count',
            COALESCE(SUM(ti.quantity), 0) as 'Total Qty Requested',
            COALESCE(SUM(ti.quantity_received), 0) as 'Total Qty Received',
            ROUND(COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    ELSE ti.quantity * 11.50
                END
            ), 0), 2) as 'Total Value (Inc GST)',
            CASE 
                WHEN t.supplier_acknowledged_at IS NOT NULL THEN 'Yes'
                ELSE 'No'
            END as 'Acknowledged',
            CASE 
                WHEN t.supplier_sent_at IS NOT NULL THEN DATE(t.supplier_sent_at)
                ELSE NULL
            END as 'Date Sent'
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        WHERE t.transfer_category = 'PURCHASE_ORDER'
        AND t.supplier_id = ?
        AND t.deleted_at IS NULL
    ";
    
    return applyFiltersToQuery($sql, $filters, $supplierID, $limit);
}

/**
 * Build detailed export query
 */
function buildDetailedQuery(array $filters, string $supplierID, int $limit): array {
    $sql = "
        SELECT 
            t.public_id as 'PO Number',
            t.vend_number as 'Vend Number',
            t.state as 'Status',
            t.created_at as 'Created DateTime',
            t.updated_at as 'Updated DateTime',
            t.expected_delivery_date as 'Expected Delivery',
            t.supplier_acknowledged_at as 'Acknowledged At',
            t.supplier_sent_at as 'Sent At',
            o.name as 'Outlet Name',
            o.store_code as 'Outlet Code',
            CONCAT(o.physical_address_1, ', ', o.physical_city, ', ', o.physical_state, ' ', o.physical_postcode) as 'Outlet Address',
            t.total_boxes as 'Total Boxes',
            t.total_weight_g as 'Total Weight (g)',
            COUNT(DISTINCT ti.id) as 'Items Count',
            COALESCE(SUM(ti.quantity), 0) as 'Total Qty Requested',
            COALESCE(SUM(ti.quantity_received), 0) as 'Total Qty Received',
            ROUND(COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price
                    ELSE ti.quantity * 10.00
                END
            ), 0), 2) as 'Total Value Ex GST',
            ROUND(COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    ELSE ti.quantity * 11.50
                END
            ), 0), 2) as 'Total Value Inc GST',
            ROUND(
                CASE 
                    WHEN COALESCE(SUM(ti.quantity), 0) > 0 
                    THEN (COALESCE(SUM(ti.quantity_received), 0) / COALESCE(SUM(ti.quantity), 0)) * 100
                    ELSE 0 
                END, 1
            ) as 'Completion %'
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        WHERE t.transfer_category = 'PURCHASE_ORDER'
        AND t.supplier_id = ?
        AND t.deleted_at IS NULL
    ";
    
    return applyFiltersToQuery($sql, $filters, $supplierID, $limit);
}

/**
 * Build items export query
 */
function buildItemsQuery(array $filters, string $supplierID, int $limit): array {
    $sql = "
        SELECT 
            t.public_id as 'PO Number',
            t.state as 'PO Status',
            DATE(t.created_at) as 'PO Created Date',
            o.name as 'Outlet Name',
            vp.sku as 'Product SKU',
            vp.name as 'Product Name',
            vb.name as 'Brand',
            ti.quantity as 'Qty Requested',
            ti.quantity_sent as 'Qty Sent',
            ti.quantity_received as 'Qty Received',
            ti.confirmation_status as 'Confirmation Status',
            COALESCE(vp.supply_price, vp.supply_price, 10.00) as 'Unit Cost',
            ROUND(ti.quantity * COALESCE(vp.supply_price, vp.supply_price, 10.00), 2) as 'Line Total Ex GST',
            ROUND(ti.quantity * COALESCE(vp.supply_price, vp.supply_price, 10.00) * 1.15, 2) as 'Line Total Inc GST',
            CASE 
                WHEN ti.quantity_received >= ti.quantity THEN 'Complete'
                WHEN ti.quantity_received > 0 THEN 'Partial'
                ELSE 'Pending'
            END as 'Fulfillment Status',
            ti.created_at as 'Item Added DateTime'
        FROM vend_consignments t
        INNER JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        LEFT JOIN vend_brands vb ON vp.brand_id = vb.id
        WHERE t.transfer_category = 'PURCHASE_ORDER'
        AND t.supplier_id = ?
        AND t.deleted_at IS NULL
    ";
    
    return applyFiltersToQuery($sql, $filters, $supplierID, $limit, false);
}

/**
 * Apply filters to query
 */
function applyFiltersToQuery(string $sql, array $filters, string $supplierID, int $limit, bool $groupBy = true): array {
    $params = [$supplierID];
    $paramTypes = 's';
    
    // Apply filters
    if (!empty($filters['po_ids'])) {
        $placeholders = str_repeat('?,', count($filters['po_ids']) - 1) . '?';
        $sql .= " AND t.id IN ({$placeholders})";
        $params = array_merge($params, array_map('intval', $filters['po_ids']));
        $paramTypes .= str_repeat('i', count($filters['po_ids']));
    }
    
    if (!empty($filters['states'])) {
        $placeholders = str_repeat('?,', count($filters['states']) - 1) . '?';
        $sql .= " AND t.state IN ({$placeholders})";
        $params = array_merge($params, $filters['states']);
        $paramTypes .= str_repeat('s', count($filters['states']));
    }
    
    if (!empty($filters['outlets'])) {
        $placeholders = str_repeat('?,', count($filters['outlets']) - 1) . '?';
        $sql .= " AND t.outlet_to IN ({$placeholders})";
        $params = array_merge($params, $filters['outlets']);
        $paramTypes .= str_repeat('s', count($filters['outlets']));
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(t.created_at) >= ?";
        $params[] = $filters['date_from'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(t.created_at) <= ?";
        $params[] = $filters['date_to'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (t.public_id LIKE ? OR o.name LIKE ? OR t.vend_number LIKE ?)";
        $searchPattern = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
        $paramTypes .= 'sss';
    }
    
    if ($groupBy) {
        $sql .= " GROUP BY t.id";
        
        // Having clause for value filters
        $havingConditions = [];
        if (!empty($filters['value_min'])) {
            $havingConditions[] = "SUM(ti.quantity * COALESCE(vp.supply_price, vp.supply_price, 10.00) * 1.15) >= ?";
            $params[] = floatval($filters['value_min']);
            $paramTypes .= 'd';
        }
        if (!empty($filters['value_max'])) {
            $havingConditions[] = "SUM(ti.quantity * COALESCE(vp.supply_price, vp.supply_price, 10.00) * 1.15) <= ?";
            $params[] = floatval($filters['value_max']);
            $paramTypes .= 'd';
        }
        
        if (!empty($havingConditions)) {
            $sql .= " HAVING " . implode(' AND ', $havingConditions);
        }
    }
    
    $sql .= " ORDER BY t.created_at DESC LIMIT ?";
    $params[] = $limit;
    $paramTypes .= 'i';
    
    return [
        'sql' => $sql,
        'params' => $params,
        'param_types' => $paramTypes
    ];
}

/**
 * Generate filename for export
 */
function generateFilename(string $exportType, string $format): string {
    $timestamp = date('Y-m-d_H-i-s');
    $extension = ($format === 'excel') ? 'xlsx' : $format;
    return "purchase_orders_{$exportType}_{$timestamp}.{$extension}";
}

/**
 * Export as CSV
 */
function exportCSV(array $data, string $filename): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Export as JSON
 */
function exportJSON(array $data, string $filename): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode([
        'export_info' => [
            'filename' => $filename,
            'generated_at' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'supplier_id' => $_SESSION['supplier_id']
        ],
        'data' => $data
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    exit;
}

/**
 * Export as Excel (simplified - would normally use PHPSpreadsheet)
 */
function exportExcel(array $data, string $filename, string $exportType): void {
    // For now, export as CSV with Excel-friendly formatting
    // In production, would use PHPSpreadsheet library
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write title row
    fputcsv($output, ["Purchase Orders Export - " . ucfirst($exportType)]);
    fputcsv($output, ["Generated: " . date('Y-m-d H:i:s')]);
    fputcsv($output, ["Total Records: " . count($data)]);
    fputcsv($output, []); // Empty row
    
    // Write header
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>