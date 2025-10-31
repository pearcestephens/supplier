<?php
/**
 * Supplier Portal - Inventory Movements Page
 *
 * Displays all inventory movements from different shipments with:
 * - Shipment ID
 * - Product SKU & Name
 * - Quantity moved
 * - Movement type (IN/OUT)
 * - Source/Destination
 * - Date & Time
 * - Receiving warehouse
 * - Movement status
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

$pageTitle = 'Inventory Movements';

// Get supplier ID
$supplierId = Auth::getSupplierId();

// Get filter parameters
$shipmentId = isset($_GET['shipment_id']) ? trim($_GET['shipment_id']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$movementType = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

try {
    $pdo = $GLOBALS['pdo'];

    // Build base query for inventory movements
    $query = "
        SELECT
            im.id,
            im.shipment_id,
            im.product_id,
            im.quantity,
            im.movement_type,
            im.source_location,
            im.destination_location,
            im.movement_date,
            im.created_at,
            im.status,
            im.notes,
            p.sku,
            p.name as product_name,
            s.po_number,
            s.status as shipment_status
        FROM inventory_movements im
        JOIN products p ON im.product_id = p.id
        LEFT JOIN shipments s ON im.shipment_id = s.id
        WHERE p.supplier_id = :supplier_id
    ";

    $params = ['supplier_id' => $supplierId];

    // Add shipment filter
    if (!empty($shipmentId)) {
        $query .= " AND im.shipment_id LIKE :shipment_id";
        $params['shipment_id'] = '%' . $shipmentId . '%';
    }

    // Add date range filter
    if (!empty($dateFrom)) {
        $query .= " AND DATE(im.movement_date) >= :date_from";
        $params['date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $query .= " AND DATE(im.movement_date) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    // Add movement type filter
    if (!empty($movementType)) {
        $query .= " AND im.movement_type = :movement_type";
        $params['movement_type'] = $movementType;
    }

    // Count total for pagination
    $countQuery = str_replace('SELECT', 'SELECT COUNT(DISTINCT im.id) as total FROM (SELECT', $query);
    $countQuery = str_replace('FROM inventory_movements', ', im.id FROM inventory_movements', $query);
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as counted";

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT im.id) as total FROM inventory_movements im JOIN products p ON im.product_id = p.id LEFT JOIN shipments s ON im.shipment_id = s.id WHERE p.supplier_id = :supplier_id" .
        (!empty($shipmentId) ? " AND im.shipment_id LIKE :shipment_id" : "") .
        (!empty($dateFrom) ? " AND DATE(im.movement_date) >= :date_from" : "") .
        (!empty($dateTo) ? " AND DATE(im.movement_date) <= :date_to" : "") .
        (!empty($movementType) ? " AND im.movement_type = :movement_type" : "")
    );

    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;
    $totalPages = ceil($total / $perPage);

    // Add ORDER BY and LIMIT
    $query .= " ORDER BY im.movement_date DESC, im.created_at DESC LIMIT :offset, :limit";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Inventory movements error: " . $e->getMessage());
    $movements = [];
    $total = 0;
    $totalPages = 1;
    $error = "Error loading inventory movements. Please try again.";
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
        .movements-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .movements-table {
            font-size: 13px;
            margin-top: 20px;
        }

        .movements-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .movements-table th {
            font-weight: 700;
            color: #333;
            padding: 12px;
            vertical-align: middle;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .movements-table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .movements-table tbody tr:hover {
            background: #f8f9fa;
        }

        .sku-badge {
            background: #e7f3ff;
            color: #0066cc;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }

        .movement-in {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            display: inline-block;
        }

        .movement-out {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            display: inline-block;
        }

        .movement-adjustment {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            display: inline-block;
        }

        .quantity-cell {
            font-weight: 700;
            font-size: 14px;
            color: #333;
        }

        .date-cell {
            font-family: 'Courier New', monospace;
            color: #666;
            font-size: 12px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
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

        .location-display {
            background: #f5f5f5;
            padding: 4px 6px;
            border-radius: 3px;
            font-size: 11px;
            color: #666;
            font-family: 'Courier New', monospace;
        }

        .arrow-icon {
            color: #999;
            margin: 0 4px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #0066cc;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/components/sidebar-new.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/components/page-header.php'; ?>

        <div class="container-fluid p-4">
            <div class="movements-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-exchange-alt"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="result-info">
                            Showing <?php echo !empty($movements) ? (($page - 1) * $perPage) + 1 : 0; ?> -
                            <?php echo min($page * $perPage, $total); ?>
                            of <?php echo number_format($total); ?> movements
                        </p>
                    </div>
                </div>

                <!-- Quick Stats -->
                <?php
                    // Calculate quick stats
                    $totalQtyIn = 0;
                    $totalQtyOut = 0;
                    foreach ($movements as $m) {
                        if ($m['movement_type'] === 'IN') {
                            $totalQtyIn += (int)$m['quantity'];
                        } elseif ($m['movement_type'] === 'OUT') {
                            $totalQtyOut += (int)$m['quantity'];
                        }
                    }
                ?>
                <div class="stats-row">
                    <div class="stat-card" style="border-left-color: #28a745;">
                        <div class="stat-label"><i class="fas fa-arrow-down"></i> Total In</div>
                        <div class="stat-value"><?php echo number_format($totalQtyIn); ?></div>
                    </div>
                    <div class="stat-card" style="border-left-color: #dc3545;">
                        <div class="stat-label"><i class="fas fa-arrow-up"></i> Total Out</div>
                        <div class="stat-value"><?php echo number_format($totalQtyOut); ?></div>
                    </div>
                    <div class="stat-card" style="border-left-color: #0066cc;">
                        <div class="stat-label"><i class="fas fa-cube"></i> Total Movements</div>
                        <div class="stat-value"><?php echo number_format($total); ?></div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <div class="filter-label">Shipment ID</div>
                            <input type="text" name="shipment_id" class="form-control"
                                   placeholder="Search shipment..."
                                   value="<?php echo htmlspecialchars($shipmentId); ?>">
                        </div>

                        <div class="col-md-2">
                            <div class="filter-label">From Date</div>
                            <input type="date" name="date_from" class="form-control"
                                   value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>

                        <div class="col-md-2">
                            <div class="filter-label">To Date</div>
                            <input type="date" name="date_to" class="form-control"
                                   value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>

                        <div class="col-md-2">
                            <div class="filter-label">Movement Type</div>
                            <select name="movement_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="IN" <?php echo $movementType === 'IN' ? 'selected' : ''; ?>>In</option>
                                <option value="OUT" <?php echo $movementType === 'OUT' ? 'selected' : ''; ?>>Out</option>
                                <option value="ADJUSTMENT" <?php echo $movementType === 'ADJUSTMENT' ? 'selected' : ''; ?>>Adjustment</option>
                            </select>
                        </div>

                        <div class="col-md-3" style="display: flex; align-items: flex-end; gap: 8px;">
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

                <!-- Movements Table -->
                <?php if (!empty($movements)): ?>
                    <div class="table-responsive">
                        <table class="table movements-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Date</th>
                                    <th style="width: 8%;">Shipment</th>
                                    <th style="width: 12%;">Product (SKU)</th>
                                    <th style="width: 8%;">Type</th>
                                    <th style="width: 8%;">Qty</th>
                                    <th style="width: 22%;">Location</th>
                                    <th style="width: 14%;">Status</th>
                                    <th style="width: 18%;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $movement):
                                    $typeClass = 'movement-' . strtolower($movement['movement_type']);
                                    $statusClass = 'status-' . strtolower($movement['status'] ?? 'pending');
                                    $movementDate = new DateTime($movement['movement_date']);
                                    $formattedDate = $movementDate->format('M j, Y');
                                    $formattedTime = $movementDate->format('g:i A');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="date-cell">
                                                <strong><?php echo htmlspecialchars($formattedDate); ?></strong><br>
                                                <small><?php echo htmlspecialchars($formattedTime); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <small style="font-family: 'Courier New', monospace; color: #666;">
                                                <?php echo htmlspecialchars($movement['shipment_id'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; margin-bottom: 3px;">
                                                <?php echo htmlspecialchars($movement['product_name']); ?>
                                            </div>
                                            <span class="sku-badge">
                                                <?php echo htmlspecialchars($movement['sku']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $typeClass; ?>">
                                                <i class="fas fa-<?php echo $movement['movement_type'] === 'IN' ? 'arrow-down' : ($movement['movement_type'] === 'OUT' ? 'arrow-up' : 'sync-alt'); ?>"></i>
                                                <?php echo htmlspecialchars($movement['movement_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="quantity-cell">
                                                <?php echo number_format((int)$movement['quantity']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 12px;">
                                                <?php if (!empty($movement['source_location']) && !empty($movement['destination_location'])): ?>
                                                    <span class="location-display"><?php echo htmlspecialchars($movement['source_location']); ?></span>
                                                    <span class="arrow-icon"><i class="fas fa-arrow-right"></i></span>
                                                    <span class="location-display"><?php echo htmlspecialchars($movement['destination_location']); ?></span>
                                                <?php elseif (!empty($movement['destination_location'])): ?>
                                                    <span class="location-display"><?php echo htmlspecialchars($movement['destination_location']); ?></span>
                                                <?php else: ?>
                                                    <small style="color: #ccc;">N/A</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars(ucfirst($movement['status'] ?? 'Pending')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars(substr($movement['notes'] ?? '', 0, 40)); ?>
                                                <?php if (strlen($movement['notes'] ?? '') > 40) echo '...'; ?>
                                            </small>
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
                                        <a class="page-link" href="?page=1&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>">
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
                                        <a class="page-link" href="?page=<?php echo $i; ?>&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?>&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>">
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
                        <h5>No Inventory Movements Found</h5>
                        <p>Try adjusting your filters or date range</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
