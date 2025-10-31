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
    $pdo = pdo();

    $searchTerm = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.public_id as po_number,
            c.state as status,
            c.total_cost as total_amount,
            c.created_at,
            o.name as outlet_name
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        WHERE c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND c.transfer_category = 'PURCHASE_ORDER'
        AND (
            c.public_id LIKE ?
            OR o.name LIKE ?
        )
        ORDER BY c.created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$supplierId, $searchTerm, $searchTerm]);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
