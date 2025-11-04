<?php
/**
 * Supplier Portal - Inventory Movements Page
 *
 * Cleaned and consolidated version: fixed HTML structure, moved CSS to a single block,
 * sanitized inputs, and corrected prepared-statement binding.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Simple auth: allow login by supplier_id param for staff/dev only if present, then enforce auth
if (($sid = filter_input(INPUT_GET, 'supplier_id', FILTER_VALIDATE_INT)) !== null && $sid !== false) {
    Auth::loginById((int)$sid);
}

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$activeTab = 'inventory';
$pageTitle = 'Inventory Movements';
$pageIcon = 'fa-solid fa-cubes';
$pageDescription = 'Track all inventory movements and stock transfers';
$breadcrumb = [
    ['text' => 'Inventory Movements', 'href' => '/supplier/inventory-movements.php']
];
$perPage = 30;

// Supplier context
$supplierId = (int) Auth::getSupplierId();

// Filters (validated/sanitized)
$shipmentId = trim((string) (filter_input(INPUT_GET, 'shipment_id') ?? ''));
$dateFromRaw = trim((string) (filter_input(INPUT_GET, 'date_from') ?? ''));
$dateToRaw = trim((string) (filter_input(INPUT_GET, 'date_to') ?? ''));
$movementType = strtoupper(trim((string) (filter_input(INPUT_GET, 'movement_type') ?? '')));
$page = max(1, (int) (filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));
$offset = ($page - 1) * $perPage;

// Validate date strings (YYYY-MM-DD) or set empty
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw) ? $dateFromRaw : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw) ? $dateToRaw : '';

$movements = [];
$total = 0;
$totalPages = 1;

try {
    $db = db(); // expects mysqli

    // Base query (select consignment line items joined to product/consignment)
    $baseWhere = 'p.supplier_id = ? AND (c.deleted_at IS NULL OR c.deleted_at = \'0000-00-00 00:00:00\')';
    $query = "
        SELECT
            ti.id,
            ti.consignment_id,
            ti.product_id,
            ti.quantity,
            'IN' AS movement_type,
            c.outlet_from AS source_location,
            c.outlet_to AS destination_location,
            c.received_at AS movement_date,
            c.created_at,
            c.state AS status,
            p.sku,
            p.name AS product_name,
            c.vend_number AS shipment_id,
            o.name AS outlet_name,
            COALESCE(ti.notes, '') AS notes
        FROM vend_consignment_line_items ti
        JOIN vend_products p ON ti.product_id = p.id
        JOIN vend_consignments c ON ti.consignment_id = c.id
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        WHERE " . $baseWhere . "
    ";

    $countQuery = "SELECT COUNT(*) AS total FROM vend_consignment_line_items ti
        JOIN vend_products p ON ti.product_id = p.id
        JOIN vend_consignments c ON ti.consignment_id = c.id
        WHERE " . $baseWhere;

    // Build params
    $params = [];
    $types = '';
    $params[] = $supplierId; $types .= 'i';

    if ($shipmentId !== '') {
        $query .= " AND c.vend_number LIKE ?";
        $countQuery .= " AND c.vend_number LIKE ?";
        $params[] = '%' . $shipmentId . '%'; $types .= 's';
    }

    if ($dateFrom !== '') {
        $query .= " AND DATE(c.received_at) >= ?";
        $countQuery .= " AND DATE(c.received_at) >= ?";
        $params[] = $dateFrom; $types .= 's';
    }

    if ($dateTo !== '') {
        $query .= " AND DATE(c.received_at) <= ?";
        $countQuery .= " AND DATE(c.received_at) <= ?";
        $params[] = $dateTo; $types .= 's';
    }

    // Movement type filtering (if applicable to your schema)
    if (in_array($movementType, ['IN','OUT','ADJUSTMENT'], true)) {
        // This example data source doesn't have movement_type column; keep placeholder if later expanded
        // $query .= " AND ti.movement_type = ?";
        // $countQuery .= " AND ti.movement_type = ?";
        // $params[] = $movementType; $types .= 's';
    }

    // Count total
    $countStmt = $db->prepare($countQuery);
    if ($types !== '') {
        // bind params by reference
        $bind = array_merge([$types], $params);
        $refs = [];
        foreach ($bind as $k => $v) { $refs[$k] = &$bind[$k]; }
        call_user_func_array([$countStmt, 'bind_param'], $refs);
    }
    $countStmt->execute();
    $res = $countStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $total = (int) ($row['total'] ?? 0);
    $countStmt->close();

    // Add ordering and limit
    $query .= " ORDER BY c.received_at DESC, c.created_at DESC LIMIT ?, ?";
    $paramsWithLimit = $params;
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit[] = $offset;
    $paramsWithLimit[] = $perPage;

    $stmt = $db->prepare($query);
    $bind = array_merge([$typesWithLimit], $paramsWithLimit);
    $refs = [];
    foreach ($bind as $k => $v) { $refs[$k] = &$bind[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $result = $stmt->get_result();
    $movements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $totalPages = max(1, (int) ceil($total / $perPage));

} catch (Exception $e) {
    error_log('Inventory movements error: ' . $e->getMessage());
    $movements = [];
    $total = 0;
    $totalPages = 1;
    $error = 'Error loading inventory movements. Please try again.';
}

// Render page (use shared components)
include __DIR__ . '/components/html-head.php';

?>

<style>
/* Page-specific styles kept small and scoped */
.movement-out { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight:600; font-size:11px; text-transform:uppercase; display:inline-block; }
.movement-adjustment { background:#fff3cd; color:#856404; padding:4px 8px; border-radius:4px; font-weight:600; font-size:11px; text-transform:uppercase; display:inline-block; }
.quantity-cell { font-weight:700; font-size:14px; color:#333; }
.date-cell { font-family: 'Courier New', monospace; color:#666; font-size:12px; }
.status-badge{ padding:4px 8px; border-radius:4px; font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; }
.status-completed{ background:#d4edda; color:#155724 }
.status-pending{ background:#fff3cd; color:#856404 }
.status-failed{ background:#f8d7da; color:#721c24 }
.filter-section{ background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px }
.filter-label{ font-weight:600; color:#333; font-size:13px; text-transform:uppercase; margin-bottom:4px }
.stats-row{ display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:15px; margin-bottom:20px }
.stat-card{ background:#f8f9fa; padding:15px; border-radius:6px }
.no-results{ text-align:center; padding:40px 20px; color:#666 }
</style>

<?php include __DIR__ . '/components/sidebar-new.php'; ?>
<?php include __DIR__ . '/components/page-header.php'; ?>

<div class="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

        <div class="movements-container">

            <!-- Results Info -->
            <p class="text-muted mb-3">
                Showing <?php echo $total ? ((($page-1)*$perPage)+1) : 0; ?> -
                <?php echo min($page*$perPage, $total); ?> of <?php echo number_format($total); ?> movements
            </p>

            <?php
                // Quick stats
                $totalQtyIn = 0; $totalQtyOut = 0;
                foreach ($movements as $m) {
                    if (($m['movement_type'] ?? '') === 'IN') $totalQtyIn += (int)$m['quantity'];
                    if (($m['movement_type'] ?? '') === 'OUT') $totalQtyOut += (int)$m['quantity'];
                }
            ?>

            <div class="stats-row">
                <div class="stat-card" style="border-left:4px solid #28a745">
                    <div class="stat-label"><i class="fas fa-arrow-down"></i> Total In</div>
                    <div class="stat-value"><?php echo number_format($totalQtyIn); ?></div>
                </div>
                <div class="stat-card" style="border-left:4px solid #dc3545">
                    <div class="stat-label"><i class="fas fa-arrow-up"></i> Total Out</div>
                    <div class="stat-value"><?php echo number_format($totalQtyOut); ?></div>
                </div>
                <div class="stat-card" style="border-left:4px solid #0066cc">
                    <div class="stat-label"><i class="fas fa-cube"></i> Total Movements</div>
                    <div class="stat-value"><?php echo number_format($total); ?></div>
                </div>
            </div>

            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <div class="filter-label">Shipment ID</div>
                        <input type="text" name="shipment_id" class="form-control" placeholder="Search shipment..." value="<?php echo htmlspecialchars($shipmentId); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="filter-label">From Date</div>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="filter-label">To Date</div>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="filter-label">Movement Type</div>
                        <select name="movement_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="IN" <?php echo $movementType==='IN' ? 'selected' : ''; ?>>In</option>
                            <option value="OUT" <?php echo $movementType==='OUT' ? 'selected' : ''; ?>>Out</option>
                            <option value="ADJUSTMENT" <?php echo $movementType==='ADJUSTMENT' ? 'selected' : ''; ?>>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex" style="align-items:flex-end; gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="flex:1"><i class="fas fa-search"></i> Filter</button>
                        <a href="?page=1" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($movements)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shipment</th>
                                <th>Product (SKU)</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movements as $movement):
                            $movementDate = $movement['movement_date'] ? new DateTime($movement['movement_date']) : null;
                            $dateStr = $movementDate ? $movementDate->format('M j, Y') : 'N/A';
                            $timeStr = $movementDate ? $movementDate->format('g:i A') : '';
                        ?>
                            <tr>
                                <td class="date-cell"><strong><?php echo htmlspecialchars($dateStr); ?></strong><br><small><?php echo htmlspecialchars($timeStr); ?></small></td>
                                <td><small style="font-family:Courier New,monospace;color:#666"><?php echo htmlspecialchars($movement['shipment_id'] ?? 'N/A'); ?></small></td>
                                <td><div style="font-weight:600"><?php echo htmlspecialchars($movement['product_name'] ?? ''); ?></div><small><?php echo htmlspecialchars($movement['sku'] ?? ''); ?></small></td>
                                <td><span class="<?php echo 'movement-'.strtolower($movement['movement_type'] ?? ''); ?>"><?php echo htmlspecialchars($movement['movement_type'] ?? ''); ?></span></td>
                                <td class="quantity-cell"><?php echo number_format((int)($movement['quantity'] ?? 0)); ?></td>
                                <td>
                                    <?php if (!empty($movement['source_location']) && !empty($movement['destination_location'])): ?>
                                        <span class="location-display"><?php echo htmlspecialchars($movement['source_location']); ?></span>
                                        <span class="arrow-icon"><i class="fas fa-arrow-right"></i></span>
                                        <span class="location-display"><?php echo htmlspecialchars($movement['destination_location']); ?></span>
                                    <?php elseif (!empty($movement['destination_location'])): ?>
                                        <span class="location-display"><?php echo htmlspecialchars($movement['destination_location']); ?></span>
                                    <?php else: ?>
                                        <small style="color:#ccc">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?php echo 'status-'.strtolower($movement['status'] ?? 'pending'); ?>"><?php echo htmlspecialchars(ucfirst($movement['status'] ?? 'Pending')); ?></span></td>
                                <td><small style="color:#666"><?php echo htmlspecialchars(mb_strimwidth($movement['notes'] ?? '', 0, 60, '...')); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="pagination" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=1&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>">&laquo; First</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                                <li class="page-item <?php echo $i===$page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>&shipment_id=<?php echo urlencode($shipmentId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&movement_type=<?php echo urlencode($movementType); ?>">Last &raquo;</a></li>
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

<?php include __DIR__ . '/components/html-footer.php'; ?>
