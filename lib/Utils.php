<?php
/**
 * Standalone Utility Helpers
 * 
 * Common utility functions for the supplier portal
 * 
 * @package Supplier\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

class Utils
{
    /**
     * HTML escape (prevent XSS)
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function esc(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * JSON response helper
     * 
     * @param array $data Data to return
     * @param int $statusCode HTTP status code
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * JSON success response
     * 
     * @param mixed $data Data to return
     * @param string $message Success message
     */
    public static function jsonSuccess($data = null, string $message = 'Success'): void
    {
        self::jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], 200);
    }
    
    /**
     * JSON error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     */
    public static function jsonError(string $message, int $statusCode = 400, array $errors = []): void
    {
        self::jsonResponse([
            'success' => false,
            'error' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $statusCode);
    }
    
    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: $url", true, $statusCode);
        exit;
    }
    
    /**
     * Format bytes to human readable
     * 
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Format number with commas
     * 
     * @param float $number Number to format
     * @param int $decimals Decimal places
     * @return string Formatted number
     */
    public static function formatNumber(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals);
    }
    
    /**
     * Format currency (NZD)
     * 
     * @param float $amount Amount
     * @return string Formatted currency
     */
    public static function formatCurrency(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }
    
    /**
     * Format date
     * 
     * @param string $date Date string
     * @param string $format Date format
     * @return string Formatted date
     */
    public static function formatDate(string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : $date;
    }
    
    /**
     * Time ago in human format
     * 
     * @param string|int $datetime Date/time string or timestamp
     * @return string Human readable time ago
     */
    public static function timeAgo($datetime): string
    {
        $time = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
        if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
        return floor($diff / 31536000) . ' years ago';
    }
    
    /**
     * Truncate string with ellipsis
     * 
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append
     * @return string Truncated string
     */
    public static function truncate(string $string, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length - strlen($suffix)) . $suffix;
    }
    
    /**
     * Get client IP address (handles proxies)
     * 
     * @return string IP address
     */
    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Check for IP from proxies/load balancers
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $ip;
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool AJAX request
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Check if request is POST
     * 
     * @return bool POST request
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Check if request is GET
     * 
     * @return bool GET request
     */
    public static function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    /**
     * Get input value from POST or GET
     * 
     * @param string $key Input key
     * @param mixed $default Default value
     * @param string $method Request method (POST, GET, REQUEST)
     * @return mixed Input value
     */
    public static function input(string $key, $default = null, string $method = 'REQUEST')
    {
        $source = match(strtoupper($method)) {
            'POST' => $_POST,
            'GET' => $_GET,
            'REQUEST' => $_REQUEST,
            default => $_REQUEST,
        };
        
        return $source[$key] ?? $default;
    }
    
    /**
     * Sanitize string (strip tags and trim)
     * 
     * @param string $string String to sanitize
     * @return string Sanitized string
     */
    public static function sanitize(string $string): string
    {
        return trim(strip_tags($string));
    }
    
    /**
     * Validate email
     * 
     * @param string $email Email address
     * @return bool Valid email
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Generate random string
     * 
     * @param int $length String length
     * @return string Random string
     */
    public static function randomString(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Log message to file
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     * @param string $file Log file name
     */
    public static function log(string $message, string $level = 'INFO', string $file = 'supplier-portal.log'): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
        
        @file_put_contents(
            $logDir . '/' . $file,
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Debug dump (development only)
     * 
     * @param mixed ...$vars Variables to dump
     */
    public static function dump(...$vars): void
    {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:20px;margin:20px;border-radius:8px;overflow:auto;">';
        foreach ($vars as $var) {
            var_dump($var);
            echo "\n" . str_repeat('=', 80) . "\n";
        }
        echo '</pre>';
    }
    
    /**
     * Debug dump and die (development only)
     * 
     * @param mixed ...$vars Variables to dump
     */
    public static function dd(...$vars): void
    {
        self::dump(...$vars);
        exit;
    }
}
