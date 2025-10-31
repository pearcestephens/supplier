<?php
/**
 * Supplier Portal - Common Functions
 * 
 * Utility functions used throughout the portal
 * 
 * @package CIS\Supplier\Functions
 * @version 3.0.0 - Updated for UUID suppliers and ML performance tracking
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

/**
 * Validate UUID format (version 4)
 * 
 * @param string $uuid UUID string to validate
 * @return bool True if valid UUID
 */
function validate_uuid(string $uuid): bool
{
    return (bool) preg_match(SUPPLIER_ID_REGEX, $uuid);
}

// ============================================================================
// LOGGING FUNCTIONS
// ============================================================================

/**
 * Log supplier action to database
 * 
 * @param string $action Action description
 * @param array $data Additional data to log
 * @return void
 */
function log_supplier_action(string $action, array $data = []): void
{
    $supplierId = get_supplier_id();
    
    if (!$supplierId) {
        return;
    }
    
    $query = "INSERT INTO " . TABLE_SUPPLIER_LOGS . " 
              (supplier_id, action, data, ip_address, user_agent, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    db_execute($query, [
        $supplierId,
        $action,
        json_encode($data),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
    ], 'sssss');
}

/**
 * Log security event
 * 
 * @param string $event Event type
 * @param array $data Event data
 * @return void
 */
function log_security_event(string $event, array $data = []): void
{
    $logFile = LOG_PATH . 'security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $message = sprintf(
        "[%s] %s - IP: %s - Data: %s\n",
        $timestamp,
        $event,
        $ip,
        json_encode($data)
    );
    
    file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

/**
 * Log error message
 * 
 * @param string $message Error message
 * @param array $context Additional context
 * @return void
 */
function log_error(string $message, array $context = []): void
{
    log_message('ERROR', $message, $context);
}

/**
 * Log warning message
 * 
 * @param string $message Warning message
 * @param array $context Additional context
 * @return void
 */
function log_warning(string $message, array $context = []): void
{
    log_message('WARNING', $message, $context);
}

/**
 * Log info message
 * 
 * @param string $message Info message
 * @param array $context Additional context
 * @return void
 */
function log_info(string $message, array $context = []): void
{
    log_message('INFO', $message, $context);
}

/**
 * Log debug message
 * 
 * @param string $message Debug message
 * @param array $context Additional context
 * @return void
 */
function log_debug(string $message, array $context = []): void
{
    if (SHOW_DEBUG_INFO) {
        log_message('DEBUG', $message, $context);
    }
}

/**
 * Generic log message function
 * 
 * @param string $level Log level
 * @param string $message Message
 * @param array $context Context data
 * @return void
 */
function log_message(string $level, string $message, array $context = []): void
{
    $logFile = LOG_PATH . 'portal.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' - ' . json_encode($context) : '';
    
    $logLine = sprintf(
        "[%s] [%s] %s%s\n",
        $timestamp,
        $level,
        $message,
        $contextStr
    );
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // Rotate log if too large
    if (LOG_ROTATION && file_exists($logFile) && filesize($logFile) > LOG_MAX_SIZE) {
        rotate_log($logFile);
    }
}

/**
 * Rotate log file
 * 
 * @param string $logFile Log file path
 * @return void
 */
function rotate_log(string $logFile): void
{
    $timestamp = date('Y-m-d_H-i-s');
    $archiveFile = $logFile . '.' . $timestamp;
    
    rename($logFile, $archiveFile);
    
    // Compress old log
    if (function_exists('gzencode')) {
        $content = file_get_contents($archiveFile);
        file_put_contents($archiveFile . '.gz', gzencode($content, 9));
        unlink($archiveFile);
    }
}

// ============================================================================
// SANITIZATION & VALIDATION
// ============================================================================

/**
 * Sanitize input string
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize_input(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array of inputs
 * 
 * @param array $inputs Input array
 * @return array Sanitized array
 */
function sanitize_array(array $inputs): array
{
    return array_map('sanitize_input', $inputs);
}

/**
 * Validate email address
 * 
 * @param string $email Email address
 * @return bool Validation result
 */
function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * 
 * @param string $phone Phone number
 * @return bool Validation result
 */
function validate_phone(string $phone): bool
{
    // Remove formatting
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Must be between 7 and 15 digits
    return strlen($phone) >= 7 && strlen($phone) <= 15;
}

/**
 * Validate date format
 * 
 * @param string $date Date string
 * @param string $format Date format (default: Y-m-d)
 * @return bool Validation result
 */
function validate_date(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// ============================================================================
// FORMATTING FUNCTIONS
// ============================================================================

/**
 * Format currency
 * 
 * @param float $amount Amount
 * @param string $currency Currency code (default: NZD)
 * @return string Formatted currency
 */
function format_currency(float $amount, string $currency = 'NZD'): string
{
    $symbol = $currency === 'NZD' ? '$' : $currency . ' ';
    return $symbol . number_format($amount, 2);
}

/**
 * Format date
 * 
 * @param string|int $date Date string or timestamp
 * @param string $format Date format (default: d/m/Y)
 * @return string Formatted date
 */
function format_date(string|int $date, string $format = 'd/m/Y'): string
{
    if (is_numeric($date)) {
        return date($format, (int)$date);
    }
    
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * Format datetime
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @param string $format Datetime format (default: d/m/Y H:i)
 * @return string Formatted datetime
 */
function format_datetime(string|int $datetime, string $format = 'd/m/Y H:i'): string
{
    return format_date($datetime, $format);
}

/**
 * Format phone number
 * 
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function format_phone(string $phone): string
{
    // Remove formatting
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // NZ mobile format: 021 XXX XXXX
    if (strlen($phone) === 10 && str_starts_with($phone, '02')) {
        return substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6);
    }
    
    // NZ landline format: (0X) XXX XXXX
    if (strlen($phone) === 9 && str_starts_with($phone, '0')) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 3) . ' ' . substr($phone, 5);
    }
    
    return $phone;
}

/**
 * Format file size
 * 
 * @param int $bytes File size in bytes
 * @param int $decimals Number of decimal places
 * @return string Formatted file size
 */
function format_filesize(int $bytes, int $decimals = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen((string)$bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
}

/**
 * Time ago format
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string Time ago string
 */
function time_ago(string|int $datetime): string
{
    $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return format_date($timestamp);
    }
}

// ============================================================================
// ARRAY & DATA HELPERS
// ============================================================================

/**
 * Get value from array with default
 * 
 * @param array $array Array to search
 * @param string $key Key to find
 * @param mixed $default Default value if not found
 * @return mixed Value or default
 */
function array_get(array $array, string $key, mixed $default = null): mixed
{
    return $array[$key] ?? $default;
}

/**
 * Check if array is associative
 * 
 * @param array $array Array to check
 * @return bool True if associative
 */
function is_assoc_array(array $array): bool
{
    if (empty($array)) {
        return false;
    }
    
    return array_keys($array) !== range(0, count($array) - 1);
}

/**
 * Paginate array
 * 
 * @param array $items Items to paginate
 * @param int $page Current page (1-indexed)
 * @param int $perPage Items per page
 * @return array Paginated items and metadata
 */
function paginate_array(array $items, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $total = count($items);
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'items' => array_slice($items, $offset, $perPage),
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

// ============================================================================
// HTTP HELPERS
// ============================================================================

/**
 * JSON response
 * 
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 * @return void
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code
 * @return void
 */
function redirect(string $url, int $statusCode = 302): void
{
    http_response_code($statusCode);
    header('Location: ' . $url);
    exit;
}

/**
 * Get current URL
 * 
 * @return string Current URL
 */
function current_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    return $protocol . '://' . $host . $uri;
}

/**
 * Get query parameter
 * 
 * @param string $key Parameter key
 * @param mixed $default Default value
 * @return mixed Parameter value or default
 */
function get_param(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

/**
 * Get POST parameter
 * 
 * @param string $key Parameter key
 * @param mixed $default Default value
 * @return mixed Parameter value or default
 */
function post_param(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

// ============================================================================
// FILE HELPERS
// ============================================================================

/**
 * Ensure directory exists
 * 
 * @param string $path Directory path
 * @return bool Success status
 */
function ensure_dir(string $path): bool
{
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    
    return true;
}

/**
 * Get file extension
 * 
 * @param string $filename Filename
 * @return string File extension (lowercase)
 */
function get_extension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 * 
 * @param string $filename Filename
 * @return bool Allowed status
 */
function is_allowed_extension(string $filename): bool
{
    $ext = get_extension($filename);
    return in_array($ext, ALLOWED_EXTENSIONS, true);
}
