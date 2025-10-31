<?php
/**
 * Purchase Orders List API
 * Returns list of orders with optional filters
 * 
 * @package CIS\Supplier\API
 * @version 2.0.0
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    requireAuth();
    $supplierId = getSupplierID();
    $outletsOnly = isset($_GET['outlets_only']) && $_GET['outlets_only'] === '1';
    
    $pdo = pdo();
    
    if ($outletsOnly) {
        // Return just outlet list for filter dropdown
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.id, o.name
            FROM vend_outlets o
            INNER JOIN vend_consignments c ON o.id = c.outlet_to
            WHERE c.supplier_id = ? 
              AND c.deleted_at IS NULL
            ORDER BY o.name ASC
        ");
        $stmt->execute([$supplierId]);
        $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse(true, ['outlets' => $outlets], 'Outlets retrieved');
        
    } else {
        // Get filter parameters
        $status = $_GET['status'] ?? 'all';
        $outlet = $_GET['outlet'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE conditions
        $whereConditions = ["c.supplier_id = ?", "c.deleted_at IS NULL"];
        $bindParams = [$supplierId];
        
        if ($status !== 'all') {
            $whereConditions[] = "c.state = ?";
            $bindParams[] = $status;
        }
        
        if ($outlet !== 'all') {
            $whereConditions[] = "c.outlet_to = ?";
            $bindParams[] = $outlet;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(c.public_id LIKE ? OR o.name LIKE ?)";
            $searchPattern = '%' . $search . '%';
            $bindParams[] = $searchPattern;
            $bindParams[] = $searchPattern;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countQuery = "
            SELECT COUNT(DISTINCT c.id)
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_to = o.id
            WHERE {$whereClause}
        ";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($bindParams);
        $totalRecords = (int)$stmt->fetchColumn();
        
        // Get orders
        $ordersQuery = "
            SELECT 
                c.id,
                c.public_id,
                c.state as status,
                c.created_at,
                c.expected_delivery_date,
                c.tracking_number,
                c.total_cost as total_value,
                o.name as outlet_name,
                COUNT(DISTINCT li.product_id) as item_count,
                SUM(li.qty_arrived) as total_quantity
            FROM vend_consignments c
            LEFT JOIN purchase_order_line_items li ON c.id = li.purchase_order_id AND li.deleted_at IS NULL
            LEFT JOIN vend_outlets o ON c.outlet_to = o.id
            WHERE {$whereClause}
            GROUP BY c.id, c.public_id, c.state, c.created_at, 
                     c.expected_delivery_date, c.tracking_number, c.total_cost, 
                     o.name
            ORDER BY c.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $pdo->prepare($ordersQuery);
        $stmt->execute($bindParams);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse(true, [
            'orders' => $orders,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage)
            ]
        ], 'Orders retrieved successfully');
    }
    
} catch (Exception $e) {
    error_log("PO List API Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Failed to load orders: ' . $e->getMessage(), 500);
}
