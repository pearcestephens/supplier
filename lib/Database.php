<?php
/**
 * Standalone Database Manager
 * 
 * High-performance MySQLi connection manager with:
 * - Auto-reconnection on connection loss
 * - Connection pooling and idle timeout
 * - Prepared statement helpers
 * - Transaction support
 * - Query logging (development)
 * 
 * @package Supplier\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

class Database
{
    private static ?mysqli $connection = null;
    private static int $lastUsed = 0;
    private static int $connectionCount = 0;
    private static array $queryLog = [];
    
    // Configuration
    private const DB_HOST = '127.0.0.1';
    private const DB_NAME = 'jcepnzzkmj';
    private const DB_USER = 'jcepnzzkmj';
    private const DB_PASS = 'wprKh9Jq63';
    private const DB_CHARSET = 'utf8mb4';
    
    // Connection management
    private const CONNECT_TIMEOUT = 5;
    private const MAX_IDLE_TIME = 300; // 5 minutes
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 100000; // 0.1 seconds
    
    /**
     * Get database connection (lazy loading with auto-reconnect)
     * 
     * @param bool $forceReconnect Force new connection
     * @return mysqli Database connection
     * @throws Exception If connection fails
     */
    public static function connect(bool $forceReconnect = false): mysqli
    {
        $now = time();
        
        // Check if connection exists and is still alive
        if (!$forceReconnect && self::$connection instanceof mysqli) {
            // Close if idle too long (prevent holding stale connections)
            if (self::MAX_IDLE_TIME > 0 && ($now - self::$lastUsed) > self::MAX_IDLE_TIME) {
                self::disconnect();
            }
            // Test connection with ping
            elseif (@self::$connection->ping()) {
                self::$lastUsed = $now;
                return self::$connection;
            }
            // Connection lost, close and reconnect
            else {
                self::disconnect();
            }
        }
        
        // Create new connection with retry logic
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                
                $mysqli = new mysqli();
                
                // Set connection timeout BEFORE connecting
                $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT);
                $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
                
                // Actually connect
                $mysqli->real_connect(
                    self::DB_HOST,
                    self::DB_USER,
                    self::DB_PASS,
                    self::DB_NAME
                );
                
                if ($mysqli->connect_error) {
                    throw new Exception($mysqli->connect_error);
                }
                
                // Set charset
                $mysqli->set_charset(self::DB_CHARSET);
                
                // Set session variables for better connection management
                $mysqli->query("SET SESSION sql_mode='TRADITIONAL'");
                $mysqli->query("SET SESSION wait_timeout=300");
                $mysqli->query("SET SESSION interactive_timeout=300");
                
                // Store connection
                self::$connection = $mysqli;
                self::$lastUsed = $now;
                self::$connectionCount++;
                
                return $mysqli;
                
            } catch (Exception $e) {
                error_log("Database Connection Error (Attempt {$attempt}): " . $e->getMessage());
                
                // Wait before retry (except on last attempt)
                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY * $attempt); // Exponential backoff
                }
            }
        }
        
        throw new Exception("Database connection failed after " . self::MAX_RETRIES . " attempts");
    }
    
    /**
     * Close database connection
     */
    public static function disconnect(): void
    {
        if (self::$connection instanceof mysqli) {
            try {
                self::$connection->close();
            } catch (Exception $e) {
                // Ignore errors on close
            }
            self::$connection = null;
            self::$lastUsed = 0;
        }
    }
    
    /**
     * Execute query and return all rows
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type string (e.g., 'ss' for two strings, auto-detected if empty)
     * @return array Array of results
     * @throws Exception On query error
     */
    public static function queryAll(string $sql, array $params = [], string $types = ''): array
    {
        $db = self::connect();
        
        // Simple query without params
        if (empty($params)) {
            $result = $db->query($sql);
            if ($result === false) {
                throw new Exception("Query error: " . $db->error);
            }
            
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
        
        // Prepared statement
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare error: " . $db->error);
        }
        
        // Auto-detect types if not provided
        if ($types === '') {
            $types = str_repeat('s', count($params)); // Default all to string
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception("Execute error: " . $stmt->error);
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        $stmt->close();
        
        self::logQuery($sql, $params);
        
        return $rows;
    }
    
    /**
     * Execute query and return single row
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type string (auto-detected if empty)
     * @return array|null Single row or null if not found
     */
    public static function queryOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $results = self::queryAll($sql, $params, $types);
        return $results[0] ?? null;
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE query
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type string (auto-detected if empty)
     * @return int Number of affected rows
     * @throws Exception On query error
     */
    public static function execute(string $sql, array $params = [], string $types = ''): int
    {
        $db = self::connect();
        
        // Simple query without params
        if (empty($params)) {
            $result = $db->query($sql);
            if ($result === false) {
                throw new Exception("Query error: " . $db->error);
            }
            return $db->affected_rows;
        }
        
        // Prepared statement
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare error: " . $db->error);
        }
        
        // Auto-detect types if not provided
        if ($types === '') {
            $types = str_repeat('s', count($params));
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        self::logQuery($sql, $params);
        
        return $affected;
    }
    
    /**
     * Get last insert ID
     * 
     * @return int Last insert ID
     */
    public static function lastInsertId(): int
    {
        $db = self::connect();
        return $db->insert_id;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool Success
     */
    public static function beginTransaction(): bool
    {
        $db = self::connect();
        return $db->begin_transaction();
    }
    
    /**
     * Commit transaction
     * 
     * @return bool Success
     */
    public static function commit(): bool
    {
        $db = self::connect();
        return $db->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @return bool Success
     */
    public static function rollback(): bool
    {
        $db = self::connect();
        return $db->rollback();
    }
    
    /**
     * Execute within transaction
     * 
     * @param callable $callback Callback function
     * @return mixed Result from callback
     * @throws Exception On transaction failure
     */
    public static function transaction(callable $callback)
    {
        self::beginTransaction();
        
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }
    }
    
    /**
     * Escape string for SQL (use prepared statements instead when possible)
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public static function escape(string $value): string
    {
        $db = self::connect();
        return $db->real_escape_string($value);
    }
    
    /**
     * Get connection statistics
     * 
     * @return array Statistics
     */
    public static function getStats(): array
    {
        return [
            'connected' => self::$connection instanceof mysqli,
            'last_used' => self::$lastUsed,
            'idle_seconds' => self::$lastUsed > 0 ? time() - self::$lastUsed : 0,
            'total_connections' => self::$connectionCount,
            'query_count' => count(self::$queryLog),
        ];
    }
    
    /**
     * Log query (development only)
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     */
    private static function logQuery(string $sql, array $params): void
    {
        // Only log in development
        if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT) {
            self::$queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => microtime(true),
            ];
        }
    }
    
    /**
     * Get query log
     * 
     * @return array Query log
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }
}
