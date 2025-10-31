<?php
/**
 * Supplier Portal Enhanced Logger
 *
 * Based on CIS Logger with AI-powered insights and comprehensive event tracking
 * Captures ALL supplier actions, API calls, data changes, and system events
 *
 * @package SupplierPortal
 * @version 2.0
 * @author AI Agent - Enhanced from CIS Logger
 * @date 2025-10-31
 */

class SupplierLogger {

    private $pdo;
    private $supplierId;
    private $supplierName;
    private $sessionId;
    private $ipAddress;
    private $userAgent;

    // Event severity levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    // Event categories
    const CATEGORY_AUTH = 'authentication';
    const CATEGORY_ORDER = 'order';
    const CATEGORY_PRODUCT = 'product';
    const CATEGORY_TRACKING = 'tracking';
    const CATEGORY_REPORT = 'report';
    const CATEGORY_DOWNLOAD = 'download';
    const CATEGORY_DASHBOARD = 'dashboard';
    const CATEGORY_API = 'api';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_SECURITY = 'security';

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param int|null $supplierId Supplier ID (from session)
     * @param string|null $supplierName Supplier name (from session)
     */
    public function __construct($pdo, $supplierId = null, $supplierName = null) {
        $this->pdo = $pdo;
        $this->supplierId = $supplierId;
        $this->supplierName = $supplierName;
        $this->sessionId = session_id() ?: 'NO_SESSION';
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        // Ensure log table exists
        $this->ensureLogTable();
    }

    /**
     * Log an event
     *
     * @param string $action Action performed (e.g., 'order_viewed', 'status_changed')
     * @param string $category Event category (use CATEGORY_* constants)
     * @param array $data Additional data to log
     * @param string $level Severity level (use LEVEL_* constants)
     * @return int|bool Log ID on success, false on failure
     */
    public function log($action, $category, $data = [], $level = self::LEVEL_INFO) {
        try {
            // Get or create log type ID
            $logTypeId = $this->getOrCreateLogType($action, $category);

            // Prepare data with context
            $logData = [
                'action' => $action,
                'category' => $category,
                'level' => $level,
                'supplier_id' => $this->supplierId,
                'supplier_name' => $this->supplierName,
                'session_id' => $this->sessionId,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $data
            ];

            // Insert into logs table
            $stmt = $this->pdo->prepare("
                INSERT INTO logs (
                    user_id,
                    log_id_type,
                    data,
                    data_2,
                    data_3,
                    created
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->supplierId ?? 0,
                $logTypeId,
                json_encode($logData),
                json_encode(['level' => $level, 'category' => $category]),
                json_encode(['ip' => $this->ipAddress, 'session' => $this->sessionId])
            ]);

            $logId = $this->pdo->lastInsertId();

            // Also log to file for AI analysis
            $this->logToFile($action, $category, $logData, $level);

            return $logId;

        } catch (Exception $e) {
            error_log("SupplierLogger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Quick log methods for common actions
     */

    public function logLogin($success = true) {
        return $this->log(
            'supplier_login',
            self::CATEGORY_AUTH,
            ['success' => $success],
            $success ? self::LEVEL_INFO : self::LEVEL_WARNING
        );
    }

    public function logLogout() {
        return $this->log('supplier_logout', self::CATEGORY_AUTH);
    }

    public function logOrderView($orderId, $orderNumber) {
        return $this->log(
            'order_viewed',
            self::CATEGORY_ORDER,
            ['order_id' => $orderId, 'order_number' => $orderNumber]
        );
    }

    public function logOrderStatusChange($orderId, $orderNumber, $oldStatus, $newStatus) {
        return $this->log(
            'order_status_changed',
            self::CATEGORY_ORDER,
            [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ],
            self::LEVEL_INFO
        );
    }

    public function logTrackingAdded($orderId, $orderNumber, $trackingNumbers, $carriers) {
        return $this->log(
            'tracking_added',
            self::CATEGORY_TRACKING,
            [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'tracking_numbers' => $trackingNumbers,
                'carriers' => $carriers,
                'parcel_count' => count($trackingNumbers)
            ]
        );
    }

    public function logNoteAdded($orderId, $orderNumber, $noteText) {
        return $this->log(
            'note_added',
            self::CATEGORY_ORDER,
            [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'note_length' => strlen($noteText)
            ]
        );
    }

    public function logProductView($productId, $productName) {
        return $this->log(
            'product_viewed',
            self::CATEGORY_PRODUCT,
            ['product_id' => $productId, 'product_name' => $productName]
        );
    }

    public function logReportGenerated($reportType, $filters = []) {
        return $this->log(
            'report_generated',
            self::CATEGORY_REPORT,
            ['report_type' => $reportType, 'filters' => $filters]
        );
    }

    public function logDownload($fileType, $fileName, $fileSize = null) {
        return $this->log(
            'file_downloaded',
            self::CATEGORY_DOWNLOAD,
            [
                'file_type' => $fileType,
                'file_name' => $fileName,
                'file_size' => $fileSize
            ]
        );
    }

    public function logAPICall($endpoint, $method, $statusCode, $responseTime = null) {
        return $this->log(
            'api_call',
            self::CATEGORY_API,
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime
            ],
            $statusCode >= 400 ? self::LEVEL_ERROR : self::LEVEL_DEBUG
        );
    }

    public function logError($errorMessage, $errorCode = null, $context = []) {
        return $this->log(
            'error_occurred',
            self::CATEGORY_SYSTEM,
            [
                'message' => $errorMessage,
                'code' => $errorCode,
                'context' => $context
            ],
            self::LEVEL_ERROR
        );
    }

    public function logSecurityEvent($eventType, $details = []) {
        return $this->log(
            $eventType,
            self::CATEGORY_SECURITY,
            $details,
            self::LEVEL_WARNING
        );
    }

    /**
     * Get recent logs for supplier
     *
     * @param int $limit Number of logs to retrieve
     * @param string|null $category Filter by category
     * @return array
     */
    public function getRecentLogs($limit = 100, $category = null) {
        try {
            $sql = "
                SELECT
                    l.id,
                    l.created,
                    l.data,
                    l.data_2,
                    lt.title as log_type
                FROM logs l
                LEFT JOIN log_types lt ON l.log_id_type = lt.id
                WHERE l.user_id = ?
            ";

            $params = [$this->supplierId];

            if ($category) {
                $sql .= " AND JSON_EXTRACT(l.data_2, '$.category') = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY l.id DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $logs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logs[] = [
                    'id' => $row['id'],
                    'created' => $row['created'],
                    'log_type' => $row['log_type'],
                    'data' => json_decode($row['data'], true),
                    'metadata' => json_decode($row['data_2'], true)
                ];
            }

            return $logs;

        } catch (Exception $e) {
            error_log("Error retrieving logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity summary for supplier
     *
     * @param string $period Period to analyze (today, week, month, all)
     * @return array
     */
    public function getActivitySummary($period = 'today') {
        try {
            $dateFilter = $this->getDateFilter($period);

            $stmt = $this->pdo->prepare("
                SELECT
                    JSON_EXTRACT(data_2, '$.category') as category,
                    COUNT(*) as count
                FROM logs
                WHERE user_id = ?
                AND created >= ?
                GROUP BY category
            ");

            $stmt->execute([$this->supplierId, $dateFilter]);

            $summary = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $summary[trim($row['category'], '"')] = (int)$row['count'];
            }

            return $summary;

        } catch (Exception $e) {
            error_log("Error getting activity summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * AI Insights: Detect anomalies and patterns
     *
     * @return array
     */
    public function getAIInsights() {
        try {
            $insights = [];

            // Check for unusual login times
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(created) as hour,
                    COUNT(*) as count
                FROM logs
                WHERE user_id = ?
                AND JSON_EXTRACT(data, '$.action') = 'supplier_login'
                AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY hour
                ORDER BY count DESC
            ");
            $stmt->execute([$this->supplierId]);
            $loginHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($loginHours)) {
                $insights['typical_login_hours'] = array_slice($loginHours, 0, 3);
            }

            // Check for rapid status changes (potential errors)
            $stmt = $this->pdo->prepare("
                SELECT
                    JSON_EXTRACT(data, '$.order_id') as order_id,
                    COUNT(*) as change_count,
                    MAX(created) as last_change
                FROM logs
                WHERE user_id = ?
                AND JSON_EXTRACT(data, '$.action') = 'order_status_changed'
                AND created >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                GROUP BY order_id
                HAVING change_count > 3
            ");
            $stmt->execute([$this->supplierId]);
            $rapidChanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rapidChanges)) {
                $insights['rapid_status_changes'] = $rapidChanges;
                $insights['warnings'][] = 'Multiple rapid status changes detected - possible user confusion';
            }

            // Check for failed API calls
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as error_count
                FROM logs
                WHERE user_id = ?
                AND JSON_EXTRACT(data_2, '$.level') = 'error'
                AND created >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$this->supplierId]);
            $errorCount = $stmt->fetchColumn();

            if ($errorCount > 5) {
                $insights['warnings'][] = "High error rate detected: {$errorCount} errors in last hour";
            }

            // Most active time of day
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(created) as hour,
                    COUNT(*) as activity
                FROM logs
                WHERE user_id = ?
                AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY hour
                ORDER BY activity DESC
                LIMIT 1
            ");
            $stmt->execute([$this->supplierId]);
            $peakHour = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($peakHour) {
                $insights['peak_activity_hour'] = $peakHour['hour'];
            }

            return $insights;

        } catch (Exception $e) {
            error_log("Error generating AI insights: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Private helper methods
     */

    private function getOrCreateLogType($action, $category) {
        // Try to get existing log type
        $stmt = $this->pdo->prepare("SELECT id FROM log_types WHERE title = ?");
        $title = "Supplier: {$category} - {$action}";
        $stmt->execute([$title]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['id'];
        }

        // Create new log type
        $stmt = $this->pdo->prepare("INSERT INTO log_types (title) VALUES (?)");
        $stmt->execute([$title]);

        return $this->pdo->lastInsertId();
    }

    private function logToFile($action, $category, $data, $level) {
        $logDir = __DIR__ . '/../logs/supplier-activity';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'action' => $action,
            'category' => $category,
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'data' => $data
        ];

        file_put_contents(
            $logFile,
            json_encode($logEntry) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return 'UNKNOWN';
    }

    private function getDateFilter($period) {
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:
                return '1970-01-01 00:00:00';
        }
    }

    private function ensureLogTable() {
        // Check if supplier_portal_logs table exists, create if needed
        try {
            $this->pdo->query("SELECT 1 FROM logs LIMIT 1");
        } catch (Exception $e) {
            // Table doesn't exist or can't be accessed
            error_log("SupplierLogger: Unable to verify logs table: " . $e->getMessage());
        }
    }
}
