<?php
/**
 * Search Products API Endpoint
 * Provides autocomplete search functionality for products
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

    // Search products (name, SKU)
    $pdo = pdo();

    $searchTerm = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name as product_name,
            p.sku,
            COALESCE(vi.inventory_level, 0) as current_stock,
            p.supply_price as unit_price,
            p.active as status,
            p.id as vend_product_id
        FROM vend_products p
        LEFT JOIN vend_inventory vi ON p.id = vi.product_id
        WHERE p.supplier_id = ?
        AND p.deleted_at = '0000-00-00 00:00:00'
        AND (
            p.name LIKE ?
            OR p.sku LIKE ?
        )
        ORDER BY p.name
        LIMIT 10
    ");

    $stmt->execute([$supplierId, $searchTerm, $searchTerm]);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Determine stock status
        $stockStatus = 'in stock';
        $stockClass = 'success';

        if ($row['current_stock'] <= 0) {
            $stockStatus = 'out of stock';
            $stockClass = 'danger';
        } elseif ($row['current_stock'] < 10) {
            $stockStatus = 'low stock';
            $stockClass = 'warning';
        }

        $results[] = [
            'id' => $row['id'],
            'title' => $row['product_name'],
            'subtitle' => 'SKU: ' . $row['sku'] . ' | Stock: ' . $row['current_stock'] . ' | $' . number_format((float)$row['unit_price'], 2),
            'sku' => $row['sku'],
            'stock' => $row['current_stock'],
            'price' => $row['unit_price'],
            'stock_status' => $stockStatus,
            'stock_class' => $stockClass,
            'type' => 'product',
            'icon' => 'box'
        ];
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Search Products API Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}
