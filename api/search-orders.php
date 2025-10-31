<?php
/**
 * Search Orders API Endpoint
 * Provides autocomplete search functionality for orders
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
requireAuth();

header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }
    
    // Get search query
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'results' => [],
            'message' => 'Query too short'
        ]);
        exit;
    }
    
    // Get supplier ID from session
    $supplierId = getSupplierID();    if (!$supplierId) {
        throw new Exception('Supplier ID not found in session');
    }

    // Search orders (PO number, outlet name)
    $db = Database::getInstance();
    $mysqli = $db->getConnection();

    $searchTerm = '%' . $query . '%';

    $stmt = $mysqli->prepare("
        SELECT
            po.id,
            po.po_number,
            po.status,
            po.total_amount,
            po.created_at,
            o.outlet_name
        FROM purchase_orders po
        LEFT JOIN vend_outlets o ON po.outlet_id = o.outlet_id
        WHERE po.supplier_id = ?
        AND (
            po.po_number LIKE ?
            OR o.outlet_name LIKE ?
        )
        ORDER BY po.created_at DESC
        LIMIT 10
    ");

    $stmt->bind_param('iss', $supplierId, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['po_number'],
            'subtitle' => $row['outlet_name'] . ' - $' . number_format((float)$row['total_amount'], 2),
            'status' => $row['status'],
            'date' => date('M d, Y', strtotime($row['created_at'])),
            'type' => 'order',
            'icon' => 'shopping-cart'
        ];
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Search Orders API Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}
