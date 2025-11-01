<?php
/**
 * Supplier Portal - Product Catalog Page
 *
 * Displays all products with:
 * - Product name & description
 * - SKU
 * - Barcode
 * - Supply price
 * - Retail price
 * - Margin calculation
 * - Current stock levels
 * - Product status
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Check authentication
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    Auth::loginById($_GET['supplier_id']);
}
if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$pageTitle = 'Product Catalog';

// Get supplier ID
$supplierId = Auth::getSupplierId();

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

try {
    $pdo = $GLOBALS['pdo'];

    // Build query to fetch products
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.description,
            p.supply_price as cost_price,
            p.price_including_tax as retail_price,
            COALESCE(SUM(i.inventory_level), 0) as stock_level,
            CASE
                WHEN p.supply_price > 0 THEN ROUND(((p.price_including_tax - p.supply_price) / p.supply_price) * 100, 1)
                ELSE 0
            END as margin_percent
        FROM vend_products p
        LEFT JOIN vend_inventory i ON p.id = i.product_id
        WHERE p.supplier_id = :supplier_id
    ";

    $params = ['supplier_id' => $supplierId];

    // Add search filter
    if (!empty($search)) {
        $query .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    // Add status filter (if exists, otherwise skip)
    if (!empty($status) && $status !== 'all') {
        $query .= " AND p.active = :status";
        $params['status'] = $status === 'active' ? 1 : 0;
    }

    // Get total count
    $countQuery = "SELECT COUNT(DISTINCT p.id) as total FROM (" . $query . ") as sub";
    $countStmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;
    $totalPages = ceil($total / $perPage);

    // Add GROUP BY and LIMIT
    $query .= " GROUP BY p.id ORDER BY p.name ASC LIMIT :offset, :limit";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique categories for filter
    $categoryQuery = "SELECT DISTINCT p.type FROM vend_products p WHERE p.supplier_id = :supplier_id AND p.type IS NOT NULL ORDER BY p.type";
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->bindValue(':supplier_id', $supplierId);
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log("Catalog page error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total = 0;
    $totalPages = 1;
    $error = "Error loading products. Please try again.";
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Supplier Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/supplier/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .catalog-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .product-table {
            font-size: 14px;
            margin-top: 20px;
        }

        .product-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .product-table th {
            font-weight: 700;
            color: #333;
            padding: 12px;
            vertical-align: middle;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .product-table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .product-table tbody tr:hover {
            background: #f8f9fa;
        }

        .sku-badge {
            background: #e7f3ff;
            color: #0066cc;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .barcode-display {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 4px 6px;
            border-radius: 3px;
            font-size: 11px;
            color: #666;
        }

        .price-cell {
            font-weight: 600;
            color: #333;
        }

        .margin-cell {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .margin-high {
            background: #d4edda;
            color: #155724;
        }

        .margin-medium {
            background: #fff3cd;
            color: #856404;
        }

        .margin-low {
            background: #f8d7da;
            color: #721c24;
        }

        .stock-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }

        .stock-high {
            background: #d4edda;
            color: #155724;
        }

        .stock-medium {
            background: #fff3cd;
            color: #856404;
        }

        .stock-low {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .filter-section .form-control,
        .filter-section .form-select {
            font-size: 14px;
        }

        .filter-label {
            font-weight: 600;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .result-info {
            color: #666;
            font-size: 14px;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 10px;
        }

        .excel-export {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.3s;
        }

        .excel-export:hover {
            background: #218838;
            text-decoration: none;
            color: white;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/components/sidebar-new.php'; ?>

    <div class="main-content" id="main-content">
        <?php include __DIR__ . '/components/page-header.php'; ?>

        <div class="container-fluid p-4">
            <div class="catalog-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-box"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="result-info">
                            Showing <?php echo !empty($products) ? (($page - 1) * $perPage) + 1 : 0; ?> -
                            <?php echo min($page * $perPage, $total); ?>
                            of <?php echo number_format($total); ?> products
                        </p>
                    </div>
                    <div>
                        <a href="?export=csv&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>"
                           class="excel-export">
                            <i class="fas fa-download"></i> Export to CSV
                        </a>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <div class="filter-label">Search</div>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Product name, SKU, or barcode..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="col-md-3">
                            <div class="filter-label">Category</div>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"
                                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <div class="filter-label">Status</div>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2" style="display: flex; align-items: flex-end; gap: 8px;">
                            <button type="submit" class="btn btn-primary btn-sm" style="flex: 1;">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?page=1" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Products Table -->
                <?php if (!empty($products)): ?>
                    <div class="table-responsive">
                        <table class="table product-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Product Name</th>
                                    <th style="width: 12%;">SKU</th>
                                    <th style="width: 15%;">Barcode</th>
                                    <th style="width: 10%;">Supply Price</th>
                                    <th style="width: 10%;">Retail Price</th>
                                    <th style="width: 8%;">Margin</th>
                                    <th style="width: 8%;">Stock</th>
                                    <th style="width: 8%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product):
                                    $margin = (float)($product['margin_percent'] ?? 0);
                                    $stock = (int)($product['stock_level'] ?? 0);

                                    // Determine margin class
                                    if ($margin >= 40) {
                                        $marginClass = 'margin-high';
                                    } elseif ($margin >= 25) {
                                        $marginClass = 'margin-medium';
                                    } else {
                                        $marginClass = 'margin-low';
                                    }

                                    // Determine stock class
                                    if ($stock >= 50) {
                                        $stockClass = 'stock-high';
                                    } elseif ($stock >= 10) {
                                        $stockClass = 'stock-medium';
                                    } else {
                                        $stockClass = 'stock-low';
                                    }

                                    $status = strtolower($product['status'] ?? 'active');
                                    $statusClass = $status === 'active' ? 'status-active' : 'status-inactive';
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #333;">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </div>
                                            <?php if (!empty($product['description'])): ?>
                                                <small style="color: #999;">
                                                    <?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>
                                                    <?php if (strlen($product['description']) > 60) echo '...'; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="sku-badge">
                                                <?php echo htmlspecialchars($product['sku']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['barcode'])): ?>
                                                <span class="barcode-display">
                                                    <?php echo htmlspecialchars($product['barcode']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #ccc;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="price-cell">
                                            $<?php echo number_format((float)($product['cost_price'] ?? 0), 2); ?>
                                        </td>
                                        <td class="price-cell">
                                            $<?php echo number_format((float)($product['retail_price'] ?? 0), 2); ?>
                                        </td>
                                        <td>
                                            <span class="margin-cell <?php echo $marginClass; ?>">
                                                <?php echo number_format($margin, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stock-badge <?php echo $stockClass; ?>">
                                                <?php echo number_format($stock); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination-container">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">
                                            <i class="fas fa-chevron-left"></i> First
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);

                                    for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">
                                            Last <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <div><i class="fas fa-inbox"></i></div>
                        <h5>No Products Found</h5>
                        <p>Try adjusting your search or filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
