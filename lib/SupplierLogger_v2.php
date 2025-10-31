<?php
/**
 * Supplier Portal Logger v2.0
 *
 * Uses EXISTING CIS tables (no generic logs table):
 * - supplier_activity_log: Supplier-specific actions
 * - supplier_portal_logs: General portal events
 * - consignment_logs: Consignment/order-specific events
 *
 * Features:
 * - Automatic supplier context
 * - Session/trace correlation
 * - AI-ready structured data
 * - Performance metrics
 * - Security event tracking
 *
 * @package SupplierPortal
 * @version 2.0.0
 * @date 2025-10-31
 */

declare(strict_types=1);

class SupplierLogger
{
    private PDO $pdo;
    private ?string $supplierId = null;
    private ?string $supplierName = null;
    private ?string $sessionId = null;
    private ?string $traceId = null;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private float $requestStartTime;
    private array $context = [];

    // Severity levels (matching consignment_logs)
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    // Action types for supplier_activity_log (MUST match ENUM)
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_TRACKING_UPDATED = 'tracking_updated';
    public const ACTION_NOTE_ADDED = 'note_added';
    public const ACTION_INFO_REQUESTED = 'info_requested';
    public const ACTION_ORDER_VIEWED = 'order_viewed';
    public const ACTION_REPORT_GENERATED = 'report_generated';
    public const ACTION_CSV_EXPORTED = 'csv_exported';

    // Event types for consignment_logs (examples - can be any string)
    public const EVENT_ORDER_STATUS_CHANGED = 'order_status_changed';
    public const EVENT_TRACKING_ADDED = 'tracking_added';
    public const EVENT_NOTE_CREATED = 'note_created';
    public const EVENT_SHIPMENT_CREATED = 'shipment_created';
    public const EVENT_API_CALL = 'api_call';
    public const EVENT_VALIDATION_ERROR = 'validation_error';
    public const EVENT_PERMISSION_DENIED = 'permission_denied';

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, ?string $supplierId = null, ?string $supplierName = null)
    {
        $this->pdo = $pdo;
        $this->supplierId = $supplierId;
        $this->supplierName = $supplierName;
        $this->sessionId = session_id() ?: null;
        $this->traceId = $this->generateTraceId();
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $this->requestStartTime = microtime(true);
    }

    /**
     * Log supplier activity (uses supplier_activity_log table)
     *
     * @param string $actionType One of the ACTION_* constants
     * @param int|null $orderId Related order ID
     * @param array $details Additional details (stored as JSON in action_details)
     * @return bool Success
     */
    public function logActivity(string $actionType, ?int $orderId = null, array $details = []): bool
    {
        if (!$this->supplierId) {
            error_log("SupplierLogger: Cannot log activity without supplier_id");
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO supplier_activity_log (
                    supplier_id,
                    order_id,
                    action_type,
                    action_details,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

            return $stmt->execute([
                $this->supplierId,
                $orderId,
                $actionType,
                $detailsJson,
                $this->ipAddress,
                $this->userAgent
            ]);
        } catch (PDOException $e) {
            error_log("SupplierLogger::logActivity failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log portal event (uses supplier_portal_logs table)
     *
     * @param string $action Action performed
     * @param string|null $resourceType Type of resource (order, product, report, etc)
     * @param string|int|null $resourceId ID of the resource
     * @param array $details Additional details
     * @return bool Success
     */
    public function logPortalEvent(
        string $action,
        ?string $resourceType = null,
        $resourceId = null,
        array $details = []
    ): bool {
        if (!$this->supplierId) {
            error_log("SupplierLogger: Cannot log portal event without supplier_id");
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO supplier_portal_logs (
                    supplier_id,
                    action,
                    resource_type,
                    resource_id,
                    ip_address,
                    user_agent,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
            $resourceIdStr = $resourceId !== null ? (string)$resourceId : null;

            return $stmt->execute([
                $this->supplierId,
                $action,
                $resourceType,
                $resourceIdStr,
                $this->ipAddress,
                $this->userAgent,
                $detailsJson
            ]);
        } catch (PDOException $e) {
            error_log("SupplierLogger::logPortalEvent failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log consignment event (uses consignment_logs table)
     *
     * @param int $transferId Consignment/transfer ID
     * @param string $eventType Type of event
     * @param array $eventData Structured event data
     * @param string $severity Severity level (info, warning, error, critical)
     * @return bool Success
     */
    public function logConsignment(
        int $transferId,
        string $eventType,
        array $eventData = [],
        string $severity = self::SEVERITY_INFO
    ): bool {
        try {
            // Add supplier context to event data
            $eventData['supplier_id'] = $this->supplierId;
            $eventData['supplier_name'] = $this->supplierName;
            $eventData['session_id'] = $this->sessionId;
            $eventData['request_duration_ms'] = round((microtime(true) - $this->requestStartTime) * 1000, 2);

            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_logs (
                    transfer_id,
                    event_type,
                    event_data,
                    actor_user_id,
                    actor_role,
                    severity,
                    source_system,
                    trace_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                $transferId,
                $eventType,
                json_encode($eventData, JSON_UNESCAPED_UNICODE),
                null, // actor_user_id (suppliers don't have user_id, only supplier_id)
                'supplier',
                $severity,
                'supplier_portal',
                $this->traceId
            ]);
        } catch (PDOException $e) {
            error_log("SupplierLogger::logConsignment failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convenience method: Log login event
     */
    public function logLogin(bool $success, ?string $errorReason = null): bool
    {
        $details = [
            'success' => $success,
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => $this->sessionId
        ];

        if (!$success && $errorReason) {
            $details['error'] = $errorReason;
        }

        // Log in both tables for comprehensive tracking
        $this->logActivity(self::ACTION_LOGIN, null, $details);
        return $this->logPortalEvent('login', null, null, $details);
    }

    /**
     * Convenience method: Log logout event
     */
    public function logLogout(): bool
    {
        $details = [
            'timestamp' => date('Y-m-d H:i:s'),
            'session_duration_seconds' => $this->getSessionDuration()
        ];

        $this->logActivity(self::ACTION_LOGOUT, null, $details);
        return $this->logPortalEvent('logout', null, null, $details);
    }

    /**
     * Convenience method: Log order view
     */
    public function logOrderView(int $orderId, array $orderData = []): bool
    {
        $details = array_merge([
            'order_id' => $orderId,
            'timestamp' => date('Y-m-d H:i:s')
        ], $orderData);

        $this->logActivity(self::ACTION_ORDER_VIEWED, $orderId, $details);
        $this->logPortalEvent('order_view', 'order', $orderId, $details);

        return true;
    }

    /**
     * Convenience method: Log tracking update
     */
    public function logTrackingUpdate(int $orderId, array $trackingData): bool
    {
        $details = array_merge([
            'order_id' => $orderId,
            'timestamp' => date('Y-m-d H:i:s')
        ], $trackingData);

        $this->logActivity(self::ACTION_TRACKING_UPDATED, $orderId, $details);
        $this->logPortalEvent('tracking_update', 'order', $orderId, $details);

        // Also log in consignment_logs if we have the transfer_id
        if (isset($trackingData['transfer_id'])) {
            $this->logConsignment(
                $trackingData['transfer_id'],
                self::EVENT_TRACKING_ADDED,
                $trackingData,
                self::SEVERITY_INFO
            );
        }

        return true;
    }

    /**
     * Convenience method: Log note added
     */
    public function logNoteAdded(int $orderId, string $noteContent, ?int $transferId = null): bool
    {
        $details = [
            'order_id' => $orderId,
            'note_length' => strlen($noteContent),
            'note_preview' => substr($noteContent, 0, 100),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logActivity(self::ACTION_NOTE_ADDED, $orderId, $details);
        $this->logPortalEvent('note_add', 'order', $orderId, $details);

        if ($transferId) {
            $this->logConsignment(
                $transferId,
                self::EVENT_NOTE_CREATED,
                $details,
                self::SEVERITY_INFO
            );
        }

        return true;
    }

    /**
     * Convenience method: Log report generation
     */
    public function logReportGenerated(string $reportType, array $filters = []): bool
    {
        $details = [
            'report_type' => $reportType,
            'filters' => $filters,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logActivity(self::ACTION_REPORT_GENERATED, null, $details);
        return $this->logPortalEvent('report_generate', 'report', $reportType, $details);
    }

    /**
     * Convenience method: Log CSV export
     */
    public function logCSVExported(string $exportType, int $rowCount): bool
    {
        $details = [
            'export_type' => $exportType,
            'row_count' => $rowCount,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logActivity(self::ACTION_CSV_EXPORTED, null, $details);
        return $this->logPortalEvent('csv_export', 'export', $exportType, $details);
    }

    /**
     * Convenience method: Log API call
     */
    public function logAPICall(string $endpoint, string $method, array $params = [], ?int $responseCode = null): bool
    {
        $details = [
            'endpoint' => $endpoint,
            'method' => $method,
            'params' => $params,
            'response_code' => $responseCode,
            'duration_ms' => round((microtime(true) - $this->requestStartTime) * 1000, 2),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->logPortalEvent('api_call', 'api', $endpoint, $details);
    }

    /**
     * Convenience method: Log error
     */
    public function logError(string $errorType, string $message, array $context = []): bool
    {
        $details = array_merge([
            'error_type' => $errorType,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'trace_id' => $this->traceId
        ], $context);

        return $this->logPortalEvent('error', 'system', $errorType, $details);
    }

    /**
     * Convenience method: Log security event
     */
    public function logSecurityEvent(string $eventType, string $description, array $context = []): bool
    {
        $details = array_merge([
            'event_type' => $eventType,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s'),
            'trace_id' => $this->traceId,
            'severity' => 'security'
        ], $context);

        return $this->logPortalEvent('security_event', 'security', $eventType, $details);
    }

    /**
     * Get activity logs for a supplier
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Logs
     */
    public function getActivityLogs(int $limit = 100, int $offset = 0): array
    {
        if (!$this->supplierId) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    action_type,
                    order_id,
                    action_details,
                    ip_address,
                    created_at
                FROM supplier_activity_log
                WHERE supplier_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$this->supplierId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SupplierLogger::getActivityLogs failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get portal event logs for a supplier
     */
    public function getPortalLogs(int $limit = 100, int $offset = 0): array
    {
        if (!$this->supplierId) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    action,
                    resource_type,
                    resource_id,
                    details,
                    created_at
                FROM supplier_portal_logs
                WHERE supplier_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$this->supplierId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SupplierLogger::getPortalLogs failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get consignment logs for orders visible to supplier
     */
    public function getConsignmentLogs(array $transferIds, int $limit = 100): array
    {
        if (empty($transferIds)) {
            return [];
        }

        try {
            $placeholders = str_repeat('?,', count($transferIds) - 1) . '?';
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    transfer_id,
                    event_type,
                    event_data,
                    severity,
                    created_at
                FROM consignment_logs
                WHERE transfer_id IN ($placeholders)
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $params = array_merge($transferIds, [$limit]);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SupplierLogger::getConsignmentLogs failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate unique trace ID for request correlation
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }

    /**
     * Get session duration (if session start time is stored)
     */
    private function getSessionDuration(): ?int
    {
        if (isset($_SESSION['login_time'])) {
            return time() - $_SESSION['login_time'];
        }
        return null;
    }

    /**
     * Set supplier context (for logging after authentication)
     */
    public function setSupplierContext(string $supplierId, ?string $supplierName = null): void
    {
        $this->supplierId = $supplierId;
        $this->supplierName = $supplierName;
    }

    /**
     * Get current trace ID
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }
}
