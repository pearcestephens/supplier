<?php
/**
 * Supplier Portal - Core Functions (Real Database Integration)
 * 
 * Uses actual database tables:
 * - vend_suppliers (supplier data)
 * - transfers (purchase orders with transfer_category='PURCHASE_ORDER')
 * - transfer_items (PO line items)
 * - faulty_products (warranty claims)
 * - vend_sales + vend_sales_line_items (analytics)
 * - supplier_portal_* (portal support tables)
 * 
 * @package SupplierPortal
 * @version 2.0
 */

declare(strict_types=1);

// ============================================================================
// SUPPLIER AUTHENTICATION
// ============================================================================

/**
 * Get supplier details by ID (VARCHAR business code)
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier business code (e.g., 'ACEVAPE', 'ASPIRE')
 * @return array|null Supplier data or null if not found
 */
function get_supplier(mysqli $conn, string $supplier_id): ?array
{
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            claim_email,
            phone,
            contact_name,
            website,
            brand_logo_url,
            primary_color,
            secondary_color,
            deleted_at
        FROM vend_suppliers
        WHERE id = ?
          AND (deleted_at IS NULL OR deleted_at = '')
        LIMIT 1
    ");
    
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Create supplier portal session
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param string $ip_address IP address
 * @param string $user_agent User agent string
 * @return string Session token
 */
function create_supplier_session(mysqli $conn, string $supplier_id, string $ip_address, string $user_agent): string
{
    $session_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("
        INSERT INTO supplier_portal_sessions 
        (supplier_id, session_token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('sssss', $supplier_id, $session_token, $ip_address, $user_agent, $expires_at);
    $stmt->execute();
    
    return $session_token;
}

/**
 * Validate session token
 * 
 * @param mysqli $conn Database connection
 * @param string $session_token Session token
 * @return string|null Supplier ID if valid, null otherwise
 */
function validate_session(mysqli $conn, string $session_token): ?string
{
    $stmt = $conn->prepare("
        UPDATE supplier_portal_sessions
        SET last_activity = CURRENT_TIMESTAMP
        WHERE session_token = ?
          AND expires_at > NOW()
    ");
    
    $stmt->bind_param('s', $session_token);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT supplier_id
        FROM supplier_portal_sessions
        WHERE session_token = ?
          AND expires_at > NOW()
        LIMIT 1
    ");
    
    $stmt->bind_param('s', $session_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row['supplier_id'] : null;
}

/**
 * Log supplier activity
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param string $action Action type
 * @param string|null $resource_type Resource type
 * @param string|null $resource_id Resource ID
 * @param string|null $details Additional details (JSON)
 */
function log_supplier_activity(
    mysqli $conn,
    string $supplier_id,
    string $action,
    ?string $resource_type = null,
    ?string $resource_id = null,
    ?string $details = null
): void {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO supplier_portal_logs
        (supplier_id, action, resource_type, resource_id, ip_address, user_agent, details)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('sssssss', $supplier_id, $action, $resource_type, $resource_id, $ip_address, $user_agent, $details);
    $stmt->execute();
}

// ============================================================================
// PURCHASE ORDERS (via transfers table)
// ============================================================================

/**
 * Get supplier's purchase orders
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param string|null $state Filter by state (optional)
 * @param int $limit Number of records
 * @param int $offset Pagination offset
 * @return array Purchase orders
 */
function get_supplier_purchase_orders(
    mysqli $conn,
    string $supplier_id,
    ?string $state = null,
    int $limit = 50,
    int $offset = 0
): array {
    $sql = "
        SELECT 
            t.id,
            t.public_id,
            t.vend_transfer_id,
            t.state,
            t.created_at,
            t.updated_at,
            t.outlet_to,
            o.name as outlet_name,
            o.physical_city,
            COUNT(ti.id) as total_items,
            SUM(ti.qty_requested) as total_qty_requested,
            SUM(ti.qty_sent_total) as total_qty_sent,
            SUM(ti.qty_received_total) as total_qty_received
        FROM transfers t
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id AND o.deleted_at = '0000-00-00 00:00:00'
        WHERE t.transfer_category = 'PURCHASE_ORDER'
          AND t.supplier_id = ?
          AND t.deleted_at IS NULL
    ";
    
    $params = [$supplier_id];
    $types = 's';
    
    if ($state !== null) {
        $sql .= " AND t.state = ?";
        $params[] = $state;
        $types .= 's';
    }
    
    $sql .= "
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get purchase order details
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID (for security check)
 * @param int $transfer_id Transfer ID
 * @return array|null PO details or null if not found/unauthorized
 */
function get_purchase_order_details(mysqli $conn, string $supplier_id, int $transfer_id): ?array
{
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            o.name as outlet_name,
            o.physical_address_1,
            o.physical_city,
            o.physical_postcode,
            o.physical_phone_number
        FROM transfers t
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id AND o.deleted_at = '0000-00-00 00:00:00'
        WHERE t.id = ?
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.supplier_id = ?
          AND t.deleted_at IS NULL
        LIMIT 1
    ");
    
    $stmt->bind_param('is', $transfer_id, $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get purchase order line items
 * 
 * @param mysqli $conn Database connection
 * @param int $transfer_id Transfer ID
 * @return array Line items with product details
 */
function get_purchase_order_items(mysqli $conn, int $transfer_id): array
{
    $stmt = $conn->prepare("
        SELECT 
            ti.id,
            ti.product_id,
            p.name as product_name,
            p.sku,
            p.supplier_code,
            ti.qty_requested,
            ti.qty_sent_total,
            ti.qty_received_total,
            ti.confirmation_status,
            p.supply_price,
            (ti.qty_requested * p.supply_price) as line_total
        FROM transfer_items ti
        JOIN vend_products p ON ti.product_id = p.id
        WHERE ti.transfer_id = ?
          AND ti.deleted_at IS NULL
        ORDER BY p.name
    ");
    
    $stmt->bind_param('i', $transfer_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Update purchase order state
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID (for security)
 * @param int $transfer_id Transfer ID
 * @param string $new_state New state
 * @return bool Success
 */
function update_purchase_order_state(
    mysqli $conn,
    string $supplier_id,
    int $transfer_id,
    string $new_state
): bool {
    $valid_states = ['OPEN', 'SENT', 'CANCELLED'];
    
    if (!in_array($new_state, $valid_states)) {
        return false;
    }
    
    $stmt = $conn->prepare("
        UPDATE transfers
        SET state = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
          AND transfer_category = 'PURCHASE_ORDER'
          AND supplier_id = ?
          AND deleted_at IS NULL
    ");
    
    $stmt->bind_param('sis', $new_state, $transfer_id, $supplier_id);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

// ============================================================================
// WARRANTY CLAIMS
// ============================================================================

/**
 * Get supplier's warranty claims
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param int|null $status Filter by status (0=pending, 1=resolved)
 * @param int $limit Number of records
 * @param int $offset Pagination offset
 * @return array Warranty claims
 */
function get_supplier_warranty_claims(
    mysqli $conn,
    string $supplier_id,
    ?int $status = null,
    int $limit = 50,
    int $offset = 0
): array {
    $sql = "
        SELECT 
            fp.id,
            fp.product_id,
            p.name as product_name,
            p.sku,
            fp.serial_number,
            fp.fault_desc,
            fp.staff_member,
            fp.store_location,
            fp.time_created,
            fp.supplier_status,
            fp.supplier_update_status,
            fp.supplier_status_timestamp,
            COUNT(fpm.id) as media_count
        FROM faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        LEFT JOIN faulty_product_media_uploads fpm ON fp.id = fpm.fault_id
        WHERE p.supplier_id = ?
    ";
    
    $params = [$supplier_id];
    $types = 's';
    
    if ($status !== null) {
        $sql .= " AND fp.supplier_status = ?";
        $params[] = $status;
        $types .= 'i';
    }
    
    $sql .= "
        GROUP BY fp.id
        ORDER BY fp.time_created DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get warranty claim details
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID (for security)
 * @param int $fault_id Fault ID
 * @return array|null Claim details or null
 */
function get_warranty_claim_details(mysqli $conn, string $supplier_id, int $fault_id): ?array
{
    $stmt = $conn->prepare("
        SELECT 
            fp.*,
            p.name as product_name,
            p.sku,
            p.supplier_code
        FROM faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        WHERE fp.id = ?
          AND p.supplier_id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param('is', $fault_id, $supplier_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get warranty claim media files
 * 
 * @param mysqli $conn Database connection
 * @param int $fault_id Fault ID
 * @return array Media files
 */
function get_warranty_claim_media(mysqli $conn, int $fault_id): array
{
    $stmt = $conn->prepare("
        SELECT id, fileName, tempFileName, upload_time
        FROM faulty_product_media_uploads
        WHERE fault_id = ?
        ORDER BY upload_time DESC
    ");
    
    $stmt->bind_param('i', $fault_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Add supplier note to warranty claim
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param int $fault_id Fault ID
 * @param string $note Note text
 * @param string|null $action_taken Action taken
 * @param string|null $internal_ref Internal reference
 * @return bool Success
 */
function add_warranty_note(
    mysqli $conn,
    string $supplier_id,
    int $fault_id,
    string $note,
    ?string $action_taken = null,
    ?string $internal_ref = null
): bool {
    $stmt = $conn->prepare("
        INSERT INTO supplier_warranty_notes
        (fault_id, supplier_id, note, action_taken, internal_ref)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('issss', $fault_id, $supplier_id, $note, $action_taken, $internal_ref);
    
    return $stmt->execute();
}

/**
 * Update warranty claim status
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID (for security)
 * @param int $fault_id Fault ID
 * @param int $status Status (0=pending, 1=resolved)
 * @return bool Success
 */
function update_warranty_status(
    mysqli $conn,
    string $supplier_id,
    int $fault_id,
    int $status
): bool {
    $stmt = $conn->prepare("
        UPDATE faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        SET fp.supplier_status = ?,
            fp.supplier_update_status = 1,
            fp.supplier_status_timestamp = CURRENT_TIMESTAMP
        WHERE fp.id = ?
          AND p.supplier_id = ?
    ");
    
    $stmt->bind_param('iis', $status, $fault_id, $supplier_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

// ============================================================================
// ANALYTICS & DASHBOARD
// ============================================================================

/**
 * Get dashboard statistics
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @return array Dashboard stats
 */
function get_dashboard_stats(mysqli $conn, string $supplier_id): array
{
    $stats = [];
    
    // Total active POs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM transfers
        WHERE transfer_category = 'PURCHASE_ORDER'
          AND supplier_id = ?
          AND state NOT IN ('RECEIVED', 'CLOSED', 'CANCELLED')
          AND deleted_at IS NULL
    ");
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $stats['active_pos'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Pending warranty claims
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM faulty_products fp
        JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
          AND fp.supplier_status = 0
    ");
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $stats['pending_claims'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Total products
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM vend_products
        WHERE supplier_id = ?
          AND deleted_at = '0000-00-00 00:00:00'
    ");
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $stats['total_products'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Unread notifications
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM supplier_portal_notifications
        WHERE supplier_id = ?
          AND is_read = 0
    ");
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $stats['unread_notifications'] = $stmt->get_result()->fetch_assoc()['count'];
    
    return $stats;
}

/**
 * Get top selling products (last 30 days)
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param int $limit Number of products
 * @return array Top products
 */
function get_top_selling_products(mysqli $conn, string $supplier_id, int $limit = 10): array
{
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.sku,
            COUNT(DISTINCT sli.sale_id) as transaction_count,
            SUM(sli.quantity) as units_sold,
            SUM(sli.total_price) as revenue
        FROM vend_products p
        JOIN vend_sales_line_items sli ON p.id = sli.product_id
        JOIN vend_sales s ON sli.sale_id = s.id
        WHERE p.supplier_id = ?
          AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND s.status = 'CLOSED'
          AND sli.is_return = 0
        GROUP BY p.id
        ORDER BY units_sold DESC
        LIMIT ?
    ");
    
    $stmt->bind_param('si', $supplier_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// NOTIFICATIONS
// ============================================================================

/**
 * Get supplier notifications
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param bool $unread_only Show only unread
 * @param int $limit Number of records
 * @return array Notifications
 */
function get_supplier_notifications(
    mysqli $conn,
    string $supplier_id,
    bool $unread_only = false,
    int $limit = 20
): array {
    $sql = "
        SELECT id, type, title, message, link, is_read, created_at, read_at
        FROM supplier_portal_notifications
        WHERE supplier_id = ?
    ";
    
    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $supplier_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mark notification as read
 * 
 * @param mysqli $conn Database connection
 * @param string $supplier_id Supplier ID
 * @param int $notification_id Notification ID
 * @return bool Success
 */
function mark_notification_read(mysqli $conn, string $supplier_id, int $notification_id): bool
{
    $stmt = $conn->prepare("
        UPDATE supplier_portal_notifications
        SET is_read = 1, read_at = CURRENT_TIMESTAMP
        WHERE id = ? AND supplier_id = ?
    ");
    
    $stmt->bind_param('is', $notification_id, $supplier_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Format currency
 * 
 * @param float $amount Amount
 * @return string Formatted currency
 */
function format_currency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/**
 * Get state badge class (Bootstrap color)
 * 
 * @param string $state Transfer state
 * @return string Bootstrap badge class
 */
function get_state_badge_class(string $state): string
{
    $classes = [
        'DRAFT' => 'secondary',
        'OPEN' => 'primary',
        'SENT' => 'info',
        'RECEIVING' => 'warning',
        'RECEIVED' => 'success',
        'CANCELLED' => 'danger',
        'CLOSED' => 'dark'
    ];
    
    return $classes[$state] ?? 'secondary';
}

/**
 * Time ago helper
 * 
 * @param string $datetime Datetime string
 * @return string Time ago
 */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M j, Y', $timestamp);
}
