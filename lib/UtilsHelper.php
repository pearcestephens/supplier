<?php
/**
 * Utilities Helper Class
 * 
 * Simple helper functions
 * No over-engineering - just what we need
 * 
 * @package SupplierPortal\Lib
 * @version 3.0.0
 */

declare(strict_types=1);

class UtilsHelper
{
    /**
     * Sanitize input string
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email address
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Format currency
     */
    public static function formatCurrency(float $amount, string $currency = 'NZD'): string
    {
        if ($currency === 'NZD') {
            return '$' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    /**
     * Format date
     */
    public static function formatDate(string $date, string $format = 'M j, Y'): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        return date($format, $timestamp);
    }
    
    /**
     * Format date/time
     */
    public static function formatDateTime(string $datetime, string $format = 'M j, Y g:i A'): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }
        return date($format, $timestamp);
    }
    
    /**
     * Format time ago (e.g., "3 hours ago")
     */
    public static function timeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . 'm ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . 'd ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . 'w ago';
        } else {
            return self::formatDate($datetime);
        }
    }
    
    /**
     * Generate random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired(array $data, array $fields): array
    {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        return $errors;
    }
    
    /**
     * Build pagination data
     */
    public static function buildPagination(int $total, int $page, int $perPage): array
    {
        $pages = (int)ceil($total / $perPage);
        
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
            'prev_page' => max(1, $page - 1),
            'next_page' => min($pages, $page + 1)
        ];
    }
    
    /**
     * Escape HTML for safe output
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get file extension
     */
    public static function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Check if file extension is allowed
     */
    public static function isAllowedFileType(string $filename, array $allowedTypes = []): bool
    {
        if (empty($allowedTypes)) {
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        }
        
        $extension = self::getFileExtension($filename);
        return in_array($extension, $allowedTypes);
    }
    
    /**
     * Format file size
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }
    
    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename(string $originalFilename): string
    {
        $extension = self::getFileExtension($originalFilename);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        return $basename . '_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $extension;
    }
    
    /**
     * Build URL query string
     */
    public static function buildQueryString(array $params): string
    {
        return http_build_query($params);
    }
    
    /**
     * Redirect to URL
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    /**
     * JSON response
     */
    public static function jsonResponse(bool $success, $data = null, string $message = ''): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }
}
