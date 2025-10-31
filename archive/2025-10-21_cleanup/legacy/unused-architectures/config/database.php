<?php
/**
 * Supplier Portal - Database Connection
 * 
 * Provides database connection and query helpers
 * 
 * @package CIS\Supplier\Config
 * @version 2.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

/**
 * Get database connection
 * 
 * @return mysqli|null Database connection or null on failure
 */
function get_db_connection(): ?mysqli
{
    static $connection = null;
    
    if ($connection === null) {
        // Use existing CIS database connection if available
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
            $connection = $GLOBALS['db'];
        } else {
            // Create new connection
            $connection = new mysqli(
                DB_HOST,
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                DB_NAME
            );
            
            if ($connection->connect_error) {
                log_error('Database connection failed: ' . $connection->connect_error);
                return null;
            }
            
            $connection->set_charset(DB_CHARSET);
        }
    }
    
    return $connection;
}

/**
 * Execute a prepared query
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to bind
 * @param string $types Parameter types (s=string, i=int, d=double, b=blob)
 * @return mysqli_stmt|false Prepared statement or false on failure
 */
function db_query(string $query, array $params = [], string $types = ''): mysqli_stmt|false
{
    $db = get_db_connection();
    
    if (!$db) {
        return false;
    }
    
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        log_error('Query preparation failed: ' . $db->error);
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            // Auto-detect types if not provided
            $types = str_repeat('s', count($params));
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        log_error('Query execution failed: ' . $stmt->error);
        return false;
    }
    
    return $stmt;
}

/**
 * Fetch single row from query
 * 
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @param string $types Parameter types
 * @return array|null Associative array or null
 */
function db_fetch_one(string $query, array $params = [], string $types = ''): ?array
{
    $stmt = db_query($query, $params, $types);
    
    if (!$stmt) {
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ?: null;
}

/**
 * Fetch all rows from query
 * 
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @param string $types Parameter types
 * @return array Array of associative arrays
 */
function db_fetch_all(string $query, array $params = [], string $types = ''): array
{
    $stmt = db_query($query, $params, $types);
    
    if (!$stmt) {
        return [];
    }
    
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $rows;
}

/**
 * Execute insert/update/delete query
 * 
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @param string $types Parameter types
 * @return int|false Number of affected rows or false on failure
 */
function db_execute(string $query, array $params = [], string $types = ''): int|false
{
    $stmt = db_query($query, $params, $types);
    
    if (!$stmt) {
        return false;
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    return $affected;
}

/**
 * Get last insert ID
 * 
 * @return int Last inserted ID
 */
function db_insert_id(): int
{
    $db = get_db_connection();
    return $db ? $db->insert_id : 0;
}

/**
 * Begin transaction
 * 
 * @return bool Success status
 */
function db_begin_transaction(): bool
{
    $db = get_db_connection();
    return $db ? $db->begin_transaction() : false;
}

/**
 * Commit transaction
 * 
 * @return bool Success status
 */
function db_commit(): bool
{
    $db = get_db_connection();
    return $db ? $db->commit() : false;
}

/**
 * Rollback transaction
 * 
 * @return bool Success status
 */
function db_rollback(): bool
{
    $db = get_db_connection();
    return $db ? $db->rollback() : false;
}

/**
 * Escape string for SQL
 * 
 * @param string $value Value to escape
 * @return string Escaped value
 */
function db_escape(string $value): string
{
    $db = get_db_connection();
    return $db ? $db->real_escape_string($value) : addslashes($value);
}

/**
 * Close database connection
 * 
 * @return void
 */
function db_close(): void
{
    $db = get_db_connection();
    
    if ($db) {
        $db->close();
    }
}
