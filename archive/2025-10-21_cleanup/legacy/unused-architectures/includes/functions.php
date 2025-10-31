<?php
/**
 * Supplier Portal - Helper Functions
 * 
 * Loaded once by index.php
 * NO bootstrap require - already loaded by index.php
 * 
 * @package CIS\Supplier
 */

// ============================================================================
// SUPPLIER FUNCTIONS
// ============================================================================

/**
 * Get supplier details by UUID
 */
function get_supplier(string $supplier_id): ?array
{
    return db_fetch_one(
        "SELECT * FROM vend_suppliers WHERE id = ? AND deleted_at = ''",
        [$supplier_id],
        's'
    );
}

/**
 * Log supplier activity
 */
function log_supplier_activity(string $supplier_id, string $action, ?string $details = null): bool
{
    $data = $details ? json_encode(['details' => $details]) : null;
    
    $stmt = db_query(
        "INSERT INTO supplier_portal_logs 
        (supplier_id, action, data, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())",
        [
            $supplier_id,
            $action,
            $data,
            get_client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ],
        'sssss'
    );
    
    return $stmt !== false;
}

/**
 * Create or update session
 */
function create_supplier_session(string $supplier_id): bool
{
    $session_id = session_id();
    
    // Check if session exists
    $existing = db_fetch_one(
        "SELECT id FROM supplier_sessions WHERE supplier_id = ? AND session_id = ?",
        [$supplier_id, $session_id],
        'ss'
    );
    
    if ($existing) {
        // Update existing session
        $stmt = db_query(
            "UPDATE supplier_sessions SET last_activity = NOW() WHERE id = ?",
            [$existing['id']],
            'i'
        );
    } else {
        // Create new session
        $stmt = db_query(
            "INSERT INTO supplier_sessions 
            (supplier_id, session_id, ip_address, user_agent, created_at, last_activity) 
            VALUES (?, ?, ?, ?, NOW(), NOW())",
            [
                $supplier_id,
                $session_id,
                get_client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            'ssss'
        );
    }
    
    return $stmt !== false;
}

/**
 * Get supplier orders
 */
function get_supplier_orders(string $supplier_id, int $limit = 50): array
{
    // Adjust this query based on your actual orders table structure
    return db_fetch_all(
        "SELECT * FROM purchase_orders 
        WHERE supplier_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?",
        [$supplier_id, $limit],
        'si'
    );
}

/**
 * Get supplier warranty claims
 */
function get_supplier_warranty_claims(string $supplier_id, ?string $status = null): array
{
    if ($status) {
        return db_fetch_all(
            "SELECT * FROM supplier_warranty_claims 
            WHERE supplier_id = ? AND claim_status = ?
            ORDER BY created_at DESC",
            [$supplier_id, $status],
            'ss'
        );
    }
    
    return db_fetch_all(
        "SELECT * FROM supplier_warranty_claims 
        WHERE supplier_id = ?
        ORDER BY created_at DESC",
        [$supplier_id],
        's'
    );
}

/**
 * Get unread notifications count
 */
function get_unread_notifications_count(string $supplier_id): int
{
    $result = db_fetch_one(
        "SELECT COUNT(*) as count FROM supplier_notifications 
        WHERE supplier_id = ? AND is_read = FALSE",
        [$supplier_id],
        's'
    );
    
    return $result ? (int)$result['count'] : 0;
}

/**
 * Get supplier notifications
 */
function get_supplier_notifications(string $supplier_id, bool $unread_only = false, int $limit = 20): array
{
    if ($unread_only) {
        return db_fetch_all(
            "SELECT * FROM supplier_notifications 
            WHERE supplier_id = ? AND is_read = FALSE
            ORDER BY created_at DESC 
            LIMIT ?",
            [$supplier_id, $limit],
            'si'
        );
    }
    
    return db_fetch_all(
        "SELECT * FROM supplier_notifications 
        WHERE supplier_id = ?
        ORDER BY created_at DESC 
        LIMIT ?",
        [$supplier_id, $limit],
        'si'
    );
}

/**
 * Mark notification as read
 */
function mark_notification_read(int $notification_id, string $supplier_id): bool
{
    $stmt = db_query(
        "UPDATE supplier_notifications 
        SET is_read = TRUE, read_at = NOW() 
        WHERE id = ? AND supplier_id = ?",
        [$notification_id, $supplier_id],
        'is'
    );
    
    return $stmt !== false;
}

/**
 * Format date for display
 */
function format_date(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) return 'N/A';
    
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format currency
 */
function format_currency(float $amount, string $currency = 'NZD'): string
{
    return '$' . number_format($amount, 2);
}

/**
 * Get supplier preference
 */
function get_supplier_preference(string $supplier_id, string $key, $default = null)
{
    $result = db_fetch_one(
        "SELECT preference_value FROM supplier_preferences 
        WHERE supplier_id = ? AND preference_key = ?",
        [$supplier_id, $key],
        'ss'
    );
    
    if (!$result) {
        return $default;
    }
    
    // Try to decode JSON
    $decoded = json_decode($result['preference_value'], true);
    return $decoded !== null ? $decoded : $result['preference_value'];
}

/**
 * Set supplier preference
 */
function set_supplier_preference(string $supplier_id, string $key, $value): bool
{
    // Encode non-string values as JSON
    if (!is_string($value)) {
        $value = json_encode($value);
    }
    
    // Check if preference exists
    $existing = db_fetch_one(
        "SELECT id FROM supplier_preferences WHERE supplier_id = ? AND preference_key = ?",
        [$supplier_id, $key],
        'ss'
    );
    
    if ($existing) {
        $stmt = db_query(
            "UPDATE supplier_preferences SET preference_value = ?, updated_at = NOW() WHERE id = ?",
            [$value, $existing['id']],
            'si'
        );
    } else {
        $stmt = db_query(
            "INSERT INTO supplier_preferences (supplier_id, preference_key, preference_value, created_at) 
            VALUES (?, ?, ?, NOW())",
            [$supplier_id, $key, $value],
            'sss'
        );
    }
    
    return $stmt !== false;
}
