<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// ============================================================================
// DATABASE QUERIES - Warranty Page Logic
// ============================================================================
$db = db();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

// QUERY 1: Get Pending Claims
// SECURITY FIX: Added p.supplier_id verification to ensure supplier can only see their own claims
// PAGINATION FIX: Added LIMIT clause to prevent excessive memory usage with large result sets
$pendingQuery = "
    SELECT
        fp.id as fault_id,
        fp.product_id,
        fp.serial_number,
        fp.fault_desc as fault_description,
        fp.staff_member,
        fp.store_location,
        fp.time_created as submitted_date,
        fp.supplier_status,
        fp.supplier_status_timestamp,
        p.name as product_name,
        p.sku,
        o.name as outlet_name,
        o.id as outlet_code,
        DATEDIFF(NOW(), fp.time_created) as days_open
    FROM faulty_products fp
    LEFT JOIN vend_products p ON fp.product_id = p.id
    LEFT JOIN vend_outlets o ON fp.store_location = o.id
    WHERE p.supplier_id = ?
      AND (fp.supplier_status = 0 OR fp.supplier_status IS NULL)
    ORDER BY fp.time_created DESC
    LIMIT 100
";
$stmt1 = $db->prepare($pendingQuery);
$stmt1->bind_param('s', $supplierID);
$stmt1->execute();
$pendingClaims = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt1->close();

// QUERY 1B: Defect Analytics - Product defect rates by category (30 days)
$defectAnalyticsQuery = "
    SELECT
        p.id,
        p.sku,
        p.name as product_name,
        COUNT(fp.id) as total_claims_30d,
        ROUND((COUNT(fp.id) / NULLIF(
            SUM(CASE WHEN li.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN li.quantity ELSE 0 END), 0
        )) * 100, 2) as defect_rate_pct,
        COUNT(DISTINCT fp.fault_desc) as issue_types,
        fp.fault_desc as primary_issue
    FROM faulty_products fp
    LEFT JOIN vend_products p ON fp.product_id = p.id
    LEFT JOIN vend_consignment_line_items li ON p.id = li.product_id
    WHERE p.supplier_id = ?
        AND fp.time_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND fp.supplier_status IN (0, 1, NULL)
    GROUP BY p.id, fp.fault_desc
    ORDER BY total_claims_30d DESC
    LIMIT 10
";
$stmt1b = $db->prepare($defectAnalyticsQuery);
$stmt1b->bind_param('s', $supplierID);
$stmt1b->execute();
$defectAnalytics = $stmt1b->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt1b->close();

// Get media files for each pending claim
foreach ($pendingClaims as &$claim) {
    $mediaStmt = $db->prepare("
        SELECT fileName as original_name, tempFileName as stored_name
        FROM faulty_product_media_uploads
        WHERE fault_id = ?
        ORDER BY upload_time ASC
    ");
    $mediaStmt->bind_param('i', $claim['fault_id']);
    $mediaStmt->execute();
    $claim['media_files'] = $mediaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mediaStmt->close();
}
unset($claim); // Break reference

// QUERY 2: Get Approved Claims (PAGINATION FIX: Added LIMIT)
$approvedQuery = "
    SELECT
        fp.id as fault_id,
        fp.product_id,
        fp.fault_desc as fault_description,
        fp.time_created,
        fp.supplier_status,
        fp.supplier_status_timestamp as accepted_date,
        p.name as product_name,
        p.sku,
        o.name as outlet_name,
        DATEDIFF(fp.supplier_status_timestamp, fp.time_created) as days_open
    FROM faulty_products fp
    LEFT JOIN vend_products p ON fp.product_id = p.id
    LEFT JOIN vend_outlets o ON fp.store_location = o.id
    WHERE p.supplier_id = ?
      AND fp.supplier_status = 1
      AND fp.supplier_status_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY fp.supplier_status_timestamp DESC
    LIMIT 100
";
$stmt2 = $db->prepare($approvedQuery);
$stmt2->bind_param('s', $supplierID);
$stmt2->execute();
$approvedClaims = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// QUERY 3: Get Declined Claims (PAGINATION FIX: Added LIMIT)
$declinedQuery = "
    SELECT
        fp.id as fault_id,
        fp.product_id,
        fp.fault_desc as fault_description,
        fp.time_created,
        fp.supplier_status,
        fp.supplier_status_timestamp as declined_date,
        p.name as product_name,
        p.sku,
        o.name as outlet_name,
        DATEDIFF(fp.supplier_status_timestamp, fp.time_created) as days_open
    FROM faulty_products fp
    LEFT JOIN vend_products p ON fp.product_id = p.id
    LEFT JOIN vend_outlets o ON fp.store_location = o.id
    WHERE p.supplier_id = ?
      AND fp.supplier_status = 2
      AND fp.supplier_status_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY fp.supplier_status_timestamp DESC
    LIMIT 100
";
$stmt3 = $db->prepare($declinedQuery);
$stmt3->bind_param('s', $supplierID);
$stmt3->execute();
$declinedClaims = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt3->close();

$activeTab = 'warranty';
$pageTitle = 'Warranty Claims';
$pageIcon = 'fa-solid fa-wrench';
$pageDescription = 'Review and respond to warranty claims on products I supplied to The Vape Shed';
$breadcrumb = [
    ['text' => 'Warranty Claims', 'href' => '/supplier/warranty.php']
];
?>
<?php include __DIR__ . '/components/html-head.php'; ?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar-new.php'; ?>

<!-- Page Header (Fixed Top Bar) -->
<?php include __DIR__ . '/components/page-header.php'; ?>

<!-- Main Content Area -->
<div class="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

<!-- Warranty Claims Content -->
<div class="warranty-page">
    <div class="btn-toolbar mb-3">
        <button class="btn btn-sm btn-outline-success me-2" onclick="exportWarrantyClaims()">
            <i class="fa-solid fa-file-csv"></i> Export CSV
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print
        </button>
    </div>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Review</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($pendingClaims ?? []); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Accepted (30d)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($acceptedClaims ?? []); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Declined (30d)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($declinedClaims ?? []); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Pending/Accepted/Declined -->
<ul class="nav nav-tabs mb-3" id="warrantyTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
            Pending Review <span class="badge badge-warning ml-1"><?php echo count($pendingClaims ?? []); ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="accepted-tab" data-toggle="tab" href="#accepted" role="tab">
            Accepted <span class="badge badge-success ml-1"><?php echo count($acceptedClaims ?? []); ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="declined-tab" data-toggle="tab" href="#declined" role="tab">
            Declined <span class="badge badge-secondary ml-1"><?php echo count($declinedClaims ?? []); ?></span>
        </a>
    </li>
</ul>

<div class="tab-content" id="warrantyTabsContent">

    <!-- PENDING CLAIMS TAB -->
    <div class="tab-pane fade show active" id="pending" role="tabpanel">
        <?php if (empty($pendingClaims)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>No pending warranty claims</strong><br>
                You're all caught up! New claims will appear here when submitted by stores.
            </div>
        <?php else: ?>
        <?php foreach ($pendingClaims as $claim): ?>
            <div class="card shadow mb-3">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($claim['fault_id']); ?></strong>
                        <span class="ml-3">
                            <i class="fas fa-clock"></i> <?php echo $claim['days_open']; ?> days open
                        </span>
                    </div>
                    <div>
                        <span class="badge badge-light"><?php echo htmlspecialchars($claim['outlet_code']); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Product Info -->
                        <div class="col-md-6">
                            <h5><?php echo htmlspecialchars($claim['product_name']); ?></h5>
                            <p>
                                <strong>SKU:</strong> <?php echo htmlspecialchars($claim['sku'] ?? 'N/A'); ?><br>
                                <strong>Serial Number:</strong> <?php echo htmlspecialchars($claim['serial_number'] ?? 'N/A'); ?><br>
                                <strong>Outlet:</strong> <?php echo htmlspecialchars($claim['outlet_name'] ?? 'Unknown'); ?><br>
                                <strong>Staff Member:</strong> <?php echo htmlspecialchars($claim['staff_member']); ?><br>
                                <strong>Submitted:</strong> <?php echo date('j M Y, g:ia', strtotime($claim['submitted_date'])); ?>
                            </p>
                            <div class="alert alert-light">
                                <strong>Fault Description:</strong><br>
                                <?php echo nl2br(htmlspecialchars($claim['fault_description'])); ?>
                            </div>
                        </div>

                        <!-- Media Gallery -->
                        <div class="col-md-6">
                            <h6>Media Files (<?php echo count($claim['media_files'] ?? []); ?>)</h6>
                            <?php if (empty($claim['media_files'])): ?>
                                <div class="alert alert-secondary">
                                    <i class="fas fa-image"></i> No media files attached to this claim.
                                </div>
                            <?php else: ?>
                                <div class="media-gallery mb-3">
                                    <?php foreach ($claim['media_files'] as $file): ?>
                                        <?php
                                        $fileName = $file['original_name'];
                                        $storedName = $file['stored_name'];
                                        $isVideo = strpos($fileName, '.mp4') !== false || strpos($fileName, '.mov') !== false;
                                        ?>
                                        <div class="media-item">
                                            <?php if ($isVideo): ?>
                                                <video controls style="width: 100%; max-height: 200px;">
                                                    <source src="/supplier/api/download-media.php?file=<?php echo urlencode($storedName); ?>" type="video/mp4">
                                                </video>
                                            <?php else: ?>
                                                <img src="/supplier/api/download-media.php?file=<?php echo urlencode($storedName); ?>"
                                                     class="img-thumbnail" style="max-width: 200px; cursor: pointer;"
                                                     onclick="viewMediaLightbox('<?php echo htmlspecialchars($storedName); ?>');">
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <a href="/supplier/api/download-media.php?file=<?php echo urlencode($storedName); ?>&download=1"
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-download"></i> <?php echo htmlspecialchars($fileName); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="/supplier/api/download-media.php?fault_id=<?php echo $claim['fault_id']; ?>&zip=1"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-archive"></i> Download All Media (ZIP)
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn btn-success btn-block"
                                    onclick="acceptClaim('<?php echo htmlspecialchars($claim['fault_id']); ?>');">
                                <i class="fas fa-check"></i> Accept Claim
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-danger btn-block"
                                    onclick="declineClaim('<?php echo htmlspecialchars($claim['fault_id']); ?>');">
                                <i class="fas fa-times"></i> Decline Claim
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ACCEPTED CLAIMS TAB -->
    <div class="tab-pane fade" id="accepted" role="tabpanel">
        <?php if (empty($acceptedClaims)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>No accepted claims in the last 30 days</strong><br>
                Claims you accept will appear here.
            </div>
        <?php else: ?>
        <div class="card shadow">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fault ID</th>
                            <th>Product</th>
                            <th>Outlet</th>
                            <th>Accepted Date</th>
                            <th>Resolution</th>
                            <th>Days Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acceptedClaims as $claim): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$claim['fault_id']); ?></td>
                                <td><?php echo htmlspecialchars($claim['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($claim['outlet_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo $claim['accepted_date'] ? date('j M Y', strtotime($claim['accepted_date'])) : 'N/A'; ?></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($claim['resolution'] ?? 'Accepted'); ?></span></td>
                                <td><?php echo (int)$claim['days_open']; ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- DECLINED CLAIMS TAB -->
    <div class="tab-pane fade" id="declined" role="tabpanel">
        <?php if (empty($declinedClaims)): ?>
            <div class="alert alert-secondary">
                <i class="fas fa-times-circle"></i>
                <strong>No declined claims in the last 30 days</strong><br>
                Claims you decline will appear here.
            </div>
        <?php else: ?>
        <div class="card shadow">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fault ID</th>
                            <th>Product</th>
                            <th>Outlet</th>
                            <th>Declined Date</th>
                            <th>Reason</th>
                            <th>Days Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($declinedClaims as $claim): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($claim['fault_id']); ?></td>
                                <td><?php echo htmlspecialchars($claim['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($claim['outlet_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo $claim['declined_date'] ? date('j M Y', strtotime($claim['declined_date'])) : 'N/A'; ?></td>
                                <td><span class="badge badge-danger"><?php echo htmlspecialchars($claim['reason'] ?? 'Declined'); ?></span></td>
                                <td><?php echo $claim['days_open']; ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Media Lightbox Modal -->
<div class="modal fade" id="mediaLightbox" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Media Viewer</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="lightboxImage" src="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<style>
.media-gallery {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.media-item {
    flex: 0 0 auto;
}
</style>

    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Warranty Claims JavaScript -->
<script src="/supplier/assets/js/warranty.js?v=<?php echo time(); ?>"></script>

</body>
</html>
