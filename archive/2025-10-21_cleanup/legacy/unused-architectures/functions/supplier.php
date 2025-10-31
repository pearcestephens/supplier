<?php
/**
 * Supplier Portal - Supplier Functions
 * 
 * Functions for supplier-related operations
 * 
 * @package CIS\Supplier\Functions
 * @version 3.0.0 - Updated for UUID suppliers and deleted_at filtering
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// SUPPLIER DATA FUNCTIONS
// ============================================================================

/**
 * Get supplier details (using UUID)
 * 
 * @param string|null $supplierId Supplier ID (UUID, uses session if not provided)
 * @return array|null Supplier data or null
 */
function get_supplier_details(?string $supplierId = null): ?array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId || !validate_uuid($supplierId)) {
        return null;
    }
    
    // Use id column (UUID) and filter by deleted_at
    $query = "SELECT * FROM " . TABLE_SUPPLIERS . " 
              WHERE id = ? AND deleted_at = '' 
              LIMIT 1";
    return db_fetch_one($query, [$supplierId], 's');
}

/**
 * Get supplier statistics
 * 
 * @param string|null $supplierId Supplier ID (UUID)
 * @return array Statistics data
 */
function get_supplier_stats(?string $supplierId = null): array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId || !validate_uuid($supplierId)) {
        return [];
    }
    
    // Active orders count
    $activeOrdersQuery = "SELECT COUNT(*) as count FROM " . TABLE_PURCHASE_ORDERS . " 
                          WHERE supplier_id = ? AND status IN ('pending', 'confirmed')";
    $activeOrders = db_fetch_one($activeOrdersQuery, [$supplierId], 's');
    
    // Pending warranty claims
    $pendingClaimsQuery = "SELECT COUNT(*) as count FROM " . TABLE_WARRANTY_CLAIMS . " 
                           WHERE supplier_id = ? AND status = 'pending'";
    $pendingClaims = db_fetch_one($pendingClaimsQuery, [$supplierId], 's');
    
    // 30-day revenue
    $revenueQuery = "SELECT SUM(total_amount) as revenue FROM " . TABLE_PURCHASE_ORDERS . " 
                     WHERE supplier_id = ? 
                     AND status = 'confirmed' 
                     AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $revenue = db_fetch_one($revenueQuery, [$supplierId], 's');
    
    // Average order value
    $avgOrderQuery = "SELECT AVG(total_amount) as avg_value FROM " . TABLE_PURCHASE_ORDERS . " 
                      WHERE supplier_id = ? 
                      AND status = 'confirmed' 
                      AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $avgOrder = db_fetch_one($avgOrderQuery, [$supplierId], 's');
    
    return [
        'active_orders' => (int)($activeOrders['count'] ?? 0),
        'pending_claims' => (int)($pendingClaims['count'] ?? 0),
        'total_revenue_30d' => (float)($revenue['revenue'] ?? 0),
        'avg_order_value' => (float)($avgOrder['avg_value'] ?? 0),
    ];
}

/**
 * Get recent orders for supplier
 * 
 * @param int $limit Number of orders to fetch
 * @param string|null $supplierId Supplier ID
 * @return array Orders data
 */
function get_recent_orders(int $limit = 10, ?string $supplierId = null): array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return [];
    }
    
    $query = "SELECT po.*, o.name as outlet_name 
              FROM " . TABLE_PURCHASE_ORDERS . " po
              LEFT JOIN vend_outlets o ON po.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              WHERE po.supplier_id = ?
              ORDER BY po.order_date DESC
              LIMIT ?";
    
    return db_fetch_all($query, [$supplierId, $limit], 'si');
}

/**
 * Get pending warranty claims for supplier
 * 
 * @param int $limit Number of claims to fetch
 * @param string|null $supplierId Supplier ID
 * @return array Claims data
 */
function get_pending_claims(int $limit = 10, ?string $supplierId = null): array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return [];
    }
    
    $query = "SELECT wc.*, o.name as outlet_name, p.name as product_name
              FROM " . TABLE_WARRANTY_CLAIMS . " wc
              LEFT JOIN vend_outlets o ON wc.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              LEFT JOIN vend_products p ON wc.product_id = p.id AND p.deleted_at = '0000-00-00 00:00:00'
              WHERE wc.supplier_id = ?
              AND wc.status = 'pending'
              ORDER BY wc.submitted_date DESC
              LIMIT ?";
    
    return db_fetch_all($query, [$supplierId, $limit], 'si');
}

/**
 * Update supplier profile
 * 
 * @param array $data Profile data to update
 * @param string|null $supplierId Supplier ID
 * @return bool Success status
 */
function update_supplier_profile(array $data, ?string $supplierId = null): bool
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return false;
    }
    
    $allowedFields = ['supplier_name', 'email', 'phone', 'address', 'city', 'country', 'postal_code'];
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($data as $field => $value) {
        if (in_array($field, $allowedFields)) {
            $updates[] = "$field = ?";
            $params[] = $value;
            $types .= 's';
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $supplierId;
    $types .= 's';
    
    $query = "UPDATE " . TABLE_SUPPLIERS . " 
              SET " . implode(', ', $updates) . ", updated_at = NOW() 
              WHERE supplier_id = ?";
    
    $result = db_execute($query, $params, $types);
    
    if ($result) {
        log_supplier_action('profile_updated', ['fields' => array_keys($data)]);
        return true;
    }
    
    return false;
}

// ============================================================================
// ORDER FUNCTIONS
// ============================================================================

/**
 * Get order details
 * 
 * @param string $orderId Order ID
 * @param string|null $supplierId Supplier ID
 * @return array|null Order data or null
 */
function get_order_details(string $orderId, ?string $supplierId = null): ?array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return null;
    }
    
    $query = "SELECT po.*, o.name as outlet_name, o.physical_address_1 as outlet_address
              FROM " . TABLE_PURCHASE_ORDERS . " po
              LEFT JOIN vend_outlets o ON po.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              WHERE po.order_id = ? AND po.supplier_id = ?
              LIMIT 1";
    
    return db_fetch_one($query, [$orderId, $supplierId], 'ss');
}

/**
 * Get order items
 * 
 * @param string $orderId Order ID
 * @return array Order items
 */
function get_order_items(string $orderId): array
{
    $query = "SELECT oi.*, p.product_name, p.sku
              FROM purchase_order_items oi
              LEFT JOIN vend_products p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?
              ORDER BY oi.line_number";
    
    return db_fetch_all($query, [$orderId], 's');
}

/**
 * Search orders
 * 
 * @param array $filters Search filters
 * @param int $page Page number
 * @param int $perPage Items per page
 * @param string|null $supplierId Supplier ID
 * @return array Search results with pagination
 */
function search_orders(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE, ?string $supplierId = null): array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return ['items' => [], 'total' => 0];
    }
    
    $where = ["supplier_id = ?"];
    $params = [$supplierId];
    $types = 's';
    
    // Date range filter
    if (!empty($filters['date_from'])) {
        $where[] = "order_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "order_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    // Outlet filter
    if (!empty($filters['outlet_id'])) {
        $where[] = "outlet_id = ?";
        $params[] = $filters['outlet_id'];
        $types .= 's';
    }
    
    // Search query
    if (!empty($filters['search'])) {
        $where[] = "(order_id LIKE ? OR notes LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM " . TABLE_PURCHASE_ORDERS . " WHERE $whereClause";
    $countResult = db_fetch_one($countQuery, $params, $types);
    $total = (int)($countResult['total'] ?? 0);
    
    // Get paginated results
    $offset = ($page - 1) * $perPage;
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    $query = "SELECT po.*, o.name as outlet_name
              FROM " . TABLE_PURCHASE_ORDERS . " po
              LEFT JOIN vend_outlets o ON po.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              WHERE $whereClause
              ORDER BY po.order_date DESC
              LIMIT ?, ?";
    
    $items = db_fetch_all($query, $params, $types);
    
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
    ];
}

// ============================================================================
// WARRANTY FUNCTIONS
// ============================================================================

/**
 * Get warranty claim details
 * 
 * @param string $claimId Claim ID
 * @param string|null $supplierId Supplier ID
 * @return array|null Claim data or null
 */
function get_warranty_claim(string $claimId, ?string $supplierId = null): ?array
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return null;
    }
    
    $query = "SELECT wc.*, o.name as outlet_name, p.name as product_name, p.sku
              FROM " . TABLE_WARRANTY_CLAIMS . " wc
              LEFT JOIN vend_outlets o ON wc.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              LEFT JOIN vend_products p ON wc.product_id = p.id AND p.deleted_at = '0000-00-00 00:00:00'
              WHERE wc.claim_id = ? AND wc.supplier_id = ?
              LIMIT 1";
    
    return db_fetch_one($query, [$claimId, $supplierId], 'ss');
}

/**
 * Update warranty claim status
 * 
 * @param string $claimId Claim ID
 * @param string $status New status
 * @param string $notes Notes
 * @param string|null $supplierId Supplier ID
 * @return bool Success status
 */
function update_warranty_status(string $claimId, string $status, string $notes = '', ?string $supplierId = null): bool
{
    $supplierId = $supplierId ?? get_supplier_id();
    
    if (!$supplierId) {
        return false;
    }
    
    $validStatuses = ['pending', 'approved', 'rejected', 'replaced', 'refunded'];
    
    if (!in_array($status, $validStatuses)) {
        return false;
    }
    
    $query = "UPDATE " . TABLE_WARRANTY_CLAIMS . " 
              SET status = ?, supplier_notes = ?, updated_at = NOW() 
              WHERE claim_id = ? AND supplier_id = ?";
    
    $result = db_execute($query, [$status, $notes, $claimId, $supplierId], 'ssss');
    
    if ($result) {
        log_supplier_action('warranty_status_updated', [
            'claim_id' => $claimId,
            'status' => $status,
        ]);
        return true;
    }
    
    return false;
}
