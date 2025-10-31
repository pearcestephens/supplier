<?php
/**
 * API Template Examples
 * Copy these templates to create the required API endpoints
 */

// ============================================================================
// FILE: /supplier/api/search-orders.php
// PURPOSE: Autocomplete search for orders
// ============================================================================
/*
<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.consignment_number,
            c.total_cost,
            c.status,
            o.name as outlet_name
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_id = o.id
        WHERE c.supplier_id = ?
        AND (
            c.consignment_number LIKE ?
            OR o.name LIKE ?
        )
        ORDER BY c.date_ordered DESC
        LIMIT 10
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$supplierId, $searchTerm, $searchTerm]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($orders as $order) {
        $results[] = [
            'title' => $order['consignment_number'],
            'subtitle' => $order['outlet_name'] . ' - $' . number_format($order['total_cost'], 2),
            'type' => 'order',
            'badge' => ucfirst($order['status']),
            'url' => '/supplier/orders.php?id=' . $order['id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log('Search orders error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed'
    ]);
}
?>
*/

// ============================================================================
// FILE: /supplier/api/get-order-detail.php
// PURPOSE: Get detailed order information for modal
// ============================================================================
/*
<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/status-badge-helper.php';

header('Content-Type: application/json');

$orderId = $_GET['id'] ?? 0;
$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            o.name as outlet_name,
            o.physical_address1,
            o.physical_suburb,
            o.physical_city,
            o.physical_postcode
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_id = o.id
        WHERE c.id = ? AND c.supplier_id = ?
    ");
    $stmt->execute([$orderId, $supplierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT 
            cli.quantity,
            cli.cost,
            p.name as product_name,
            p.sku
        FROM vend_consignment_line_items cli
        LEFT JOIN vend_products p ON cli.product_id = p.id
        WHERE cli.consignment_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build HTML
    $html = '
    <div class="order-detail">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <table class="table table-sm">
                    <tr><th>Order Number:</th><td>' . htmlspecialchars($order['consignment_number']) . '</td></tr>
                    <tr><th>Date:</th><td>' . date('M d, Y', strtotime($order['date_ordered'])) . '</td></tr>
                    <tr><th>Status:</th><td>' . renderStatusBadge($order['status'], 'order') . '</td></tr>
                    <tr><th>Total:</th><td>$' . number_format($order['total_cost'], 2) . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Delivery Address</h6>
                <address>
                    <strong>' . htmlspecialchars($order['outlet_name']) . '</strong><br>
                    ' . htmlspecialchars($order['physical_address1']) . '<br>
                    ' . htmlspecialchars($order['physical_suburb']) . '<br>
                    ' . htmlspecialchars($order['physical_city']) . ' ' . htmlspecialchars($order['physical_postcode']) . '
                </address>
            </div>
        </div>
        
        <h6>Order Items</h6>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($items as $item) {
        $lineTotal = $item['quantity'] * $item['cost'];
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td><code>' . htmlspecialchars($item['sku']) . '</code></td>
                        <td class="text-end">' . $item['quantity'] . '</td>
                        <td class="text-end">$' . number_format($item['cost'], 2) . '</td>
                        <td class="text-end">$' . number_format($lineTotal, 2) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total:</th>
                        <th class="text-end">$' . number_format($order['total_cost'], 2) . '</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log('Get order detail error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load order details'
    ]);
}
?>
*/

// ============================================================================
// FILE: /supplier/api/get-warranty-detail.php
// PURPOSE: Get warranty claim details for modal
// ============================================================================
/*
<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/status-badge-helper.php';

header('Content-Type: application/json');

$claimId = $_GET['id'] ?? 0;
$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            w.*,
            p.name as product_name,
            p.sku
        FROM warranty_claims w
        LEFT JOIN vend_products p ON w.product_id = p.id
        WHERE w.id = ? AND w.supplier_id = ?
    ");
    $stmt->execute([$claimId, $supplierId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        echo json_encode(['success' => false, 'error' => 'Claim not found']);
        exit;
    }
    
    $html = '
    <div class="warranty-detail">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Claim Information</h6>
                <table class="table table-sm">
                    <tr><th>Claim Number:</th><td><span data-copyable>' . htmlspecialchars($claim['claim_number']) . '</span></td></tr>
                    <tr><th>Date Submitted:</th><td>' . date('M d, Y', strtotime($claim['created_at'])) . '</td></tr>
                    <tr><th>Status:</th><td>' . renderStatusBadge($claim['status'], 'warranty') . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Product Information</h6>
                <table class="table table-sm">
                    <tr><th>Product:</th><td>' . htmlspecialchars($claim['product_name']) . '</td></tr>
                    <tr><th>SKU:</th><td><code>' . htmlspecialchars($claim['sku']) . '</code></td></tr>
                    <tr><th>Defect Type:</th><td>' . htmlspecialchars($claim['defect_type']) . '</td></tr>
                </table>
            </div>
        </div>
        
        <h6>Description</h6>
        <div class="alert alert-light">
            ' . nl2br(htmlspecialchars($claim['description'])) . '
        </div>';
    
    if ($claim['image_url']) {
        $html .= '
        <h6>Attached Images</h6>
        <div class="row g-2">
            <div class="col-md-4">
                <img src="' . htmlspecialchars($claim['image_url']) . '" class="img-fluid rounded" alt="Claim photo">
            </div>
        </div>';
    }
    
    $html .= '</div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log('Get warranty detail error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load claim details'
    ]);
}
?>
*/

// ============================================================================
// FILE: /supplier/api/update-account.php
// PURPOSE: Handle inline editing saves
// ============================================================================
/*
<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

// Whitelist of editable fields
$allowedFields = [
    'company_name',
    'contact_name',
    'contact_email',
    'contact_phone',
    'physical_address',
    'billing_address'
];

if (!in_array($field, $allowedFields)) {
    echo json_encode(['success' => false, 'error' => 'Invalid field']);
    exit;
}

// Validation
if (empty(trim($value))) {
    echo json_encode(['success' => false, 'error' => 'Value cannot be empty']);
    exit;
}

if ($field === 'contact_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE suppliers 
        SET {$field} = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$value, $supplierId]);
    
    // Log the change
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (supplier_id, action, details, created_at)
        VALUES (?, 'account_update', ?, NOW())
    ");
    $stmt->execute([$supplierId, "Updated {$field}"]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Update account error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update'
    ]);
}
?>
*/

// ============================================================================
// FILE: /supplier/api/search-products.php
// PURPOSE: Autocomplete search for products
// ============================================================================
/*
<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.supply_price,
            COALESCE(SUM(i.count), 0) as total_stock
        FROM vend_products p
        LEFT JOIN vend_inventory i ON p.id = i.product_id
        WHERE p.supplier_id = ?
        AND (
            p.name LIKE ?
            OR p.sku LIKE ?
        )
        GROUP BY p.id
        ORDER BY p.name
        LIMIT 10
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$supplierId, $searchTerm, $searchTerm]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($products as $product) {
        $stockBadge = $product['total_stock'] > 0 ? 'In Stock' : 'Out of Stock';
        
        $results[] = [
            'title' => $product['name'],
            'subtitle' => 'SKU: ' . $product['sku'] . ' - $' . number_format($product['supply_price'], 2),
            'type' => 'product',
            'badge' => $stockBadge,
            'url' => '/supplier/products.php?id=' . $product['id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log('Search products error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed'
    ]);
}
?>
*/

// ============================================================================
// USAGE INSTRUCTIONS
// ============================================================================
/*

1. Create each API file in /supplier/api/ directory
2. Remove the comment markers (the opening and closing PHP comment tags)
3. Adjust database column names to match your schema
4. Test each endpoint individually:

   Test search-orders.php:
   curl "https://staff.vapeshed.co.nz/supplier/api/search-orders.php?q=PO"

   Test get-order-detail.php:
   curl "https://staff.vapeshed.co.nz/supplier/api/get-order-detail.php?id=123"

   Test update-account.php:
   curl -X POST "https://staff.vapeshed.co.nz/supplier/api/update-account.php" \
        -H "Content-Type: application/json" \
        -d '{"field":"company_name","value":"New Company Name"}'

4. Check error logs if responses aren't as expected
5. Ensure session authentication is working properly

SECURITY NOTES:
- All endpoints check for authentication ($_SESSION['supplier_id'])
- All use prepared statements (SQL injection safe)
- Field whitelisting on update endpoint
- Input validation on all user input
- Error messages don't expose sensitive data

*/
