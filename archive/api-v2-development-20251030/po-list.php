<?php
/**
 * Purchase Orders List API - Phase 3 Implementation
 * 
 * Enhanced PO listing with advanced filtering, search, and sorting
 * Optimized for large datasets (686K+ records)
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
require_once __DIR__ . '/../../supplier-config.php';
require_once __DIR__ . '/_response.php';

// Start session
Session::start();

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
    // Database connection via Database class
    $db = Database::connect();
    
    // ========================================================================
    // PARAMETER VALIDATION & SANITIZATION
    // ========================================================================
    
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 25))); // Max 100, min 10, default 25
    $offset = ($page - 1) * $limit;
    
    // Sorting
    $allowedSortFields = ['created_at', 'public_id', 'state', 'expected_delivery_date', 'total_value', 'outlet_name', 'items_count'];
    $sortField = in_array($_GET['sort'] ?? '', $allowedSortFields) ? $_GET['sort'] : 'created_at';
    $sortDirection = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    
    // Filters
    $filters = [
        'states' => $_GET['states'] ?? [], // Array of states
        'outlets' => $_GET['outlets'] ?? [], // Array of outlet IDs
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'value_min' => $_GET['value_min'] ?? null,
        'value_max' => $_GET['value_max'] ?? null,
        'search' => trim($_GET['search'] ?? ''),
        'has_items' => $_GET['has_items'] ?? null, // true/false/null
        'delivery_overdue' => $_GET['delivery_overdue'] ?? null, // true/false/null
    ];
    
    // Ensure arrays
    if (!is_array($filters['states'])) {
        $filters['states'] = $filters['states'] ? explode(',', $filters['states']) : [];
    }
    if (!is_array($filters['outlets'])) {
        $filters['outlets'] = $filters['outlets'] ? explode(',', $filters['outlets']) : [];
    }
    
    // Validate states
    $validStates = ['DRAFT', 'OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL', 'RECEIVED', 'CLOSED', 'CANCELLED', 'ARCHIVED'];
    $filters['states'] = array_intersect($filters['states'], $validStates);
    
    // ========================================================================
    // QUERY BUILDING WITH SECURITY
    // ========================================================================
    
    // Base query with proper deleted_at handling
    $baseQuery = "
        SELECT 
            t.id as id,
            t.vend_consignment_id as public_id,
            t.state as status,
            t.created_at,
            NULL as expected_delivery_date,
            NULL as supplier_sent_at,
            NULL as supplier_acknowledged_at,
            t.updated_at,
            0 as total_boxes,
            0 as total_weight_g,
            o.name as outlet_name,
            o.store_code as outlet_code,
            o.physical_state as outlet_state,
            COUNT(DISTINCT ti.id) as items_count,
            COALESCE(SUM(ti.quantity), 0) as total_qty_requested,
            COALESCE(SUM(ti.quantity_received), 0) as total_qty_received,
            COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price
                    ELSE ti.quantity * 10.00
                END
            ), 0) as total_value_ex_gst,
            COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    ELSE ti.quantity * 11.50
                END
            ), 0) as total_value_inc_gst,
            CASE 
                WHEN t.expected_delivery_date IS NOT NULL AND t.expected_delivery_date < CURDATE() AND t.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL')
                THEN 1 
                ELSE 0 
            END as is_overdue
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        WHERE t.transfer_category = 'PURCHASE_ORDER'
        AND t.supplier_id = ?
        AND t.deleted_at IS NULL
    ";
    
    $params = [$supplierID];
    $paramTypes = 's';
    
    // Apply filters
    if (!empty($filters['states'])) {
        $placeholders = str_repeat('?,', count($filters['states']) - 1) . '?';
        $baseQuery .= " AND t.state IN ({$placeholders})";
        $params = array_merge($params, $filters['states']);
        $paramTypes .= str_repeat('s', count($filters['states']));
    }
    
    if (!empty($filters['outlets'])) {
        $placeholders = str_repeat('?,', count($filters['outlets']) - 1) . '?';
        $baseQuery .= " AND t.outlet_to IN ({$placeholders})";
        $params = array_merge($params, $filters['outlets']);
        $paramTypes .= str_repeat('s', count($filters['outlets']));
    }
    
    if (!empty($filters['date_from'])) {
        $baseQuery .= " AND DATE(t.created_at) >= ?";
        $params[] = $filters['date_from'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $baseQuery .= " AND DATE(t.created_at) <= ?";
        $params[] = $filters['date_to'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['search'])) {
        $baseQuery .= " AND (t.public_id LIKE ? OR o.name LIKE ? OR t.vend_number LIKE ?)";
        $searchPattern = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
        $paramTypes .= 'sss';
    }
    
    if ($filters['delivery_overdue'] === 'true') {
        $baseQuery .= " AND t.expected_delivery_date IS NOT NULL AND t.expected_delivery_date < CURDATE() AND t.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL')";
    } elseif ($filters['delivery_overdue'] === 'false') {
        $baseQuery .= " AND NOT (t.expected_delivery_date IS NOT NULL AND t.expected_delivery_date < CURDATE() AND t.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL'))";
    }
    
    // Group by for aggregations
    $baseQuery .= " GROUP BY t.id, o.id";
    
    // Having clause for value filters
    $havingConditions = [];
    if (!empty($filters['value_min'])) {
        $havingConditions[] = "total_value_inc_gst >= ?";
        $params[] = floatval($filters['value_min']);
        $paramTypes .= 'd';
    }
    if (!empty($filters['value_max'])) {
        $havingConditions[] = "total_value_inc_gst <= ?";
        $params[] = floatval($filters['value_max']);
        $paramTypes .= 'd';
    }
    if ($filters['has_items'] === 'true') {
        $havingConditions[] = "items_count > 0";
    } elseif ($filters['has_items'] === 'false') {
        $havingConditions[] = "items_count = 0";
    }
    
    if (!empty($havingConditions)) {
        $baseQuery .= " HAVING " . implode(' AND ', $havingConditions);
    }
    
    // ========================================================================
    // COUNT QUERY FOR PAGINATION
    // ========================================================================
    
    $countQuery = "SELECT COUNT(*) as total FROM ({$baseQuery}) as counted";
    $stmt = $db->prepare($countQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare count query: " . $db->error);
    }
    
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalPOs = $totalResult->fetch_assoc()['total'];
    $stmt->close();
    
    // ========================================================================
    // MAIN QUERY WITH PAGINATION AND SORTING
    // ========================================================================
    
    // Sort mapping
    $sortMapping = [
        'created_at' => 't.created_at',
        'public_id' => 't.public_id',
        'state' => 't.state',
        'expected_delivery_date' => 't.expected_delivery_date',
        'total_value' => 'total_value_inc_gst',
        'outlet_name' => 'o.name',
        'items_count' => 'items_count'
    ];
    
    $sortColumn = $sortMapping[$sortField];
    $mainQuery = $baseQuery . " ORDER BY {$sortColumn} {$sortDirection} LIMIT ? OFFSET ?";
    
    // Add pagination parameters
    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= 'ii';
    
    $stmt = $db->prepare($mainQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare main query: " . $db->error);
    }
    
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchaseOrders = [];
    while ($row = $result->fetch_assoc()) {
        // Format data
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        $row['updated_at'] = date('Y-m-d H:i:s', strtotime($row['updated_at']));
        $row['expected_delivery_date'] = $row['expected_delivery_date'] ? date('Y-m-d', strtotime($row['expected_delivery_date'])) : null;
        $row['supplier_sent_at'] = $row['supplier_sent_at'] ? date('Y-m-d H:i:s', strtotime($row['supplier_sent_at'])) : null;
        $row['supplier_acknowledged_at'] = $row['supplier_acknowledged_at'] ? date('Y-m-d H:i:s', strtotime($row['supplier_acknowledged_at'])) : null;
        
        // Convert to proper types
        $row['items_count'] = intval($row['items_count']);
        $row['total_qty_requested'] = intval($row['total_qty_requested']);
        $row['total_qty_received'] = intval($row['total_qty_received']);
        $row['total_value_ex_gst'] = floatval($row['total_value_ex_gst']);
        $row['total_value_inc_gst'] = floatval($row['total_value_inc_gst']);
        $row['total_boxes'] = intval($row['total_boxes']);
        $row['total_weight_g'] = intval($row['total_weight_g']);
        $row['is_overdue'] = (bool)$row['is_overdue'];
        
        // Add status badge info
        $row['status_info'] = getPOStatusInfo($row['status']);
        
        $purchaseOrders[] = $row;
    }
    $stmt->close();
    
    // ========================================================================
    // SUMMARY STATISTICS
    // ========================================================================
    
    $statsQuery = "
        SELECT 
            COUNT(*) as total_active,
            COALESCE(SUM(
                CASE 
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    WHEN vp.supply_price > 0 THEN ti.quantity * vp.supply_price * 1.15
                    ELSE ti.quantity * 11.50
                END
            ), 0) as total_value_active,
            COUNT(CASE WHEN t.expected_delivery_date IS NOT NULL AND t.expected_delivery_date < CURDATE() AND t.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL') THEN 1 END) as overdue_count
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        WHERE t.transfer_category = 'PURCHASE_ORDER'
        AND t.supplier_id = ?
        AND t.deleted_at IS NULL
        AND t.state IN ('OPEN', 'PACKING', 'PACKAGED', 'SENT', 'RECEIVING', 'PARTIAL')
    ";
    
    $stmt = $db->prepare($statsQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Calculate pagination metadata
    $totalPages = ceil($totalPOs / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    // ========================================================================
    // RESPONSE
    // ========================================================================
    
    $db->close();
    
    apiResponse(true, [
        'purchase_orders' => $purchaseOrders,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => intval($totalPOs),
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage,
            'next_page' => $hasNextPage ? $page + 1 : null,
            'prev_page' => $hasPrevPage ? $page - 1 : null
        ],
        'filters_applied' => array_filter($filters),
        'sort' => [
            'field' => $sortField,
            'direction' => $sortDirection
        ]
    ], [
        'performance' => [
            'query_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ],
        'summary_stats' => [
            'total_active_pos' => intval($stats['total_active']),
            'total_value_active' => floatval($stats['total_value_active']),
            'overdue_count' => intval($stats['overdue_count'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("PO List API Error: " . $e->getMessage());
    apiResponse(false, null, [
        'code' => 'INTERNAL_ERROR',
        'message' => 'An error occurred while fetching purchase orders',
        'debug' => $e->getMessage()
    ]);
}

/**
 * Get status badge information for a PO state
 */
function getPOStatusInfo(string $state): array {
    $statusMap = [
        'DRAFT' => ['label' => 'Draft', 'color' => 'secondary', 'icon' => 'edit'],
        'OPEN' => ['label' => 'Open', 'color' => 'primary', 'icon' => 'folder-open'],
        'PACKING' => ['label' => 'Packing', 'color' => 'warning', 'icon' => 'box'],
        'PACKAGED' => ['label' => 'Packaged', 'color' => 'info', 'icon' => 'boxes'],
        'SENT' => ['label' => 'Sent', 'color' => 'success', 'icon' => 'shipping-fast'],
        'RECEIVING' => ['label' => 'Receiving', 'color' => 'warning', 'icon' => 'dolly'],
        'PARTIAL' => ['label' => 'Partial', 'color' => 'warning', 'icon' => 'balance-scale'],
        'RECEIVED' => ['label' => 'Received', 'color' => 'success', 'icon' => 'check-circle'],
        'CLOSED' => ['label' => 'Closed', 'color' => 'dark', 'icon' => 'lock'],
        'CANCELLED' => ['label' => 'Cancelled', 'color' => 'danger', 'icon' => 'times-circle'],
        'ARCHIVED' => ['label' => 'Archived', 'color' => 'muted', 'icon' => 'archive']
    ];
    
    return $statusMap[$state] ?? ['label' => $state, 'color' => 'secondary', 'icon' => 'question'];
}
?>