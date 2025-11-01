<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Get Activity Logs API
 *
 * Retrieves supplier activity logs with filtering and pagination
 *
 * @package SupplierPortal
 * @version 1.0
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get query parameters
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50;
    $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
    $level = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

    // Ensure limit is reasonable
    $limit = min($limit, 500);

    // Build query
    $sql = "
        SELECT
            l.id,
            l.created,
            l.data,
            l.data_2,
            l.data_3,
            lt.title as log_type
        FROM logs l
        LEFT JOIN log_types lt ON l.log_id_type = lt.id
        WHERE l.user_id = ?
    ";

    $params = [$_SESSION['supplier_id']];
    $types = 'i';

    // Add category filter
    if ($category) {
        $sql .= " AND JSON_EXTRACT(l.data_2, '$.category') = ?";
        $params[] = $category;
        $types .= 's';
    }

    // Add level filter
    if ($level) {
        $sql .= " AND JSON_EXTRACT(l.data_2, '$.level') = ?";
        $params[] = $level;
        $types .= 's';
    }

    // Add date range filter
    if ($startDate) {
        $sql .= " AND l.created >= ?";
        $params[] = $startDate;
        $types .= 's';
    }

    if ($endDate) {
        $sql .= " AND l.created <= ?";
        $params[] = $endDate . ' 23:59:59';
        $types .= 's';
    }

    $sql .= " ORDER BY l.id DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $logs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode($row['data'], true);
        $metadata = json_decode($row['data_2'], true);
        $extra = json_decode($row['data_3'], true);

        $logs[] = [
            'id' => $row['id'],
            'timestamp' => $row['created'],
            'log_type' => $row['log_type'],
            'action' => $data['action'] ?? 'unknown',
            'category' => $metadata['category'] ?? 'system',
            'level' => $metadata['level'] ?? 'info',
            'url' => $data['url'] ?? '',
            'method' => $data['method'] ?? '',
            'ip_address' => $extra['ip'] ?? '',
            'data' => $data['data'] ?? []
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'count' => count($logs),
        'filters' => [
            'category' => $category,
            'level' => $level,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Get activity logs error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve activity logs',
        'error' => $e->getMessage()
    ]);
}
