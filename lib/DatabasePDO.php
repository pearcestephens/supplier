<?php
/**
 * Database PDO Wrapper Class
 * 
 * Simple, clean PDO wrapper with prepared statements
 * No over-engineering - just what we need
 * 
 * @package SupplierPortal\Lib
 * @version 3.0.0
 */

declare(strict_types=1);

class DatabasePDO
{
    private static ?PDO $instance = null;
    private PDO $pdo;
    
    /**
     * Get singleton PDO instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }
    
    /**
     * Create PDO connection
     */
    public static function connect(): PDO
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            return $pdo;
            
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Execute query and return all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Query execution failed');
        }
    }
    
    /**
     * Execute query and return single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Query execution failed');
        }
    }
    
    /**
     * Execute query and return single column value
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0)
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn($column);
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Query execution failed');
        }
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE query
     */
    public static function execute(string $sql, array $params = []): int
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Query execution failed');
        }
    }
    
    /**
     * Get last insert ID
     */
    public static function lastInsertId(): int
    {
        return (int)self::getInstance()->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }
}
