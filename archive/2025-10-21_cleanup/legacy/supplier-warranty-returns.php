<?php
/**
 * supplier-warranty-returns.php
 * Warranty Returns & Faulty Products for suppliers — refreshed UI and corrected supplierID handling (string-safe)
 * Author: CIS Dev Bot
 * Last Modified: 2025-09-24
 * Dependencies: assets/functions/supplier-config.php, assets/functions/vapeshed-website.php
 */
// Supplier Warranty Returns & Faulty Products — refreshed layout + fixed supplierID handling
include_once("assets/functions/supplier-config.php");
include_once("assets/functions/vapeshed-website.php");

// Helper for safe HTML output
if (!function_exists('esc')) {
  function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Resolve absolute path of a media file stored under assets/uploads/faulty-products
if (!function_exists('wr_media_abs_path')) {
  function wr_media_abs_path(string $basename): ?string {
    // Security: only allow safe basenames
    if (!preg_match('/^[A-Za-z0-9._-]{1,200}$/', $basename)) return null;
    $candidates = [];
    $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $dir = rtrim(__DIR__, '/\\');
    // Common locations (Cloudways/Apache PHP-FPM variants)
    if ($doc) {
      $candidates[] = $doc . '/assets/uploads/faulty-products/' . $basename;
      $candidates[] = $doc . '/assets/uploads/faulty_products/' . $basename; // underscore fallback
      // Some setups expose docroot one level up (e.g., /.../applications/app/public_html)
      $candidates[] = $doc . '/public_html/assets/uploads/faulty-products/' . $basename;
      $candidates[] = $doc . '/public_html/assets/uploads/faulty_products/' . $basename;
    }
    if ($dir) {
      $candidates[] = $dir . '/assets/uploads/faulty-products/' . $basename;
      $candidates[] = $dir . '/assets/uploads/faulty_products/' . $basename;
      $candidates[] = dirname($dir) . '/assets/uploads/faulty-products/' . $basename;
      $candidates[] = dirname($dir) . '/assets/uploads/faulty_products/' . $basename;
    }
    foreach ($candidates as $p) {
      if (is_file($p)) return $p;
    }
    return null;
  }
}

// Sanitize supplierID from GET as string (IDs may be UUID-like)
global $con;
$supplierId = isset($_GET['supplierID']) ? (string)$_GET['supplierID'] : '';
$supplierId = mysqli_real_escape_string($con, $supplierId);

// CSV export (pending/current list by default)
if (isset($_GET['supplierID']) && isset($_GET['getCSV'])) {
  $faultyProducts = getFaultyProductsForSupplier($supplierId);
  $supplierInformation = getIndividualSuppliersFromDB($supplierId);

  $rows = [];
  foreach ((array)$faultyProducts as $fault) {
    $rows[] = [
      'Internal ID'      => $fault->id,
      'Product Name'     => $fault->product->name ?? '',
      'Serial'           => $fault->serial_number ?? '',
      'Fault Description'=> $fault->fault_desc ?? '',
    ];
  }
  $supplierName = isset($supplierInformation->name) ? strtolower(urlencode($supplierInformation->name)) : 'unknown-supplier';
  $filename = 'the-vape-shed_warranty-returns_'.$supplierName.'.csv';
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  if (!empty($rows)) fputcsv($out, array_keys($rows[0])); else fputcsv($out, ['Internal ID','Product Name','Serial','Fault Description']);
  foreach ($rows as $r) { fputcsv($out, $r); }
  fclose($out);
  exit;
}

// Single-file ZIP download for media items
if (isset($_GET['zip']) && $_GET['zip'] === '1' && isset($_GET['file'])) {
  $fileParam = (string)$_GET['file'];
  if (!preg_match('/^[A-Za-z0-9._-]{1,200}$/', $fileParam)) {
    http_response_code(400);
    exit('Bad file');
  }
  $abs = wr_media_abs_path($fileParam);
  if (!$abs) {
    // Fallback: redirect to public asset URL variants so web server can serve it if present
    $pub1 = 'https://staff.vapeshed.co.nz/assets/uploads/faulty-products/' . rawurlencode($fileParam);
    $pub2 = 'https://staff.vapeshed.co.nz/assets/uploads/faulty_products/' . rawurlencode($fileParam);
    header('Location: ' . $pub1, true, 302);
    exit;
  }
  if (!class_exists('ZipArchive')) {
    http_response_code(503);
    exit('ZIP unavailable');
  }
  $tmp = tempnam(sys_get_temp_dir(), 'wrzip_');
  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    exit('ZIP open failed');
  }
  $zip->addFile($abs, $fileParam);
  $zip->close();
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  header('Content-Type: application/zip');
  $zipName = pathinfo($fileParam, PATHINFO_FILENAME) . '.zip';
  header('Content-Disposition: attachment; filename="' . $zipName . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp);
  @unlink($tmp);
  exit;
}

// Stream media with correct Content-Type (images/videos/other) with optional download=1
if (isset($_GET['media']) && $_GET['media'] === '1' && isset($_GET['file'])) {
  $fileParam = (string)$_GET['file'];
  if (!preg_match('/^[A-Za-z0-9._-]{1,200}$/', $fileParam)) {
    http_response_code(400);
    exit('Bad file');
  }
  $abs = wr_media_abs_path($fileParam);
  if (!$abs) {
    // Fallback: redirect to public asset URL variants so web server can serve it if present
    $pub1 = 'https://staff.vapeshed.co.nz/assets/uploads/faulty-products/' . rawurlencode($fileParam);
    $pub2 = 'https://staff.vapeshed.co.nz/assets/uploads/faulty_products/' . rawurlencode($fileParam);
    header('Location: ' . $pub1, true, 302);
    exit;
  }
  $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
  $mime = 'application/octet-stream';
  if (in_array($ext, ['jpg','jpeg'])) $mime = 'image/jpeg';
  elseif ($ext === 'png') $mime = 'image/png';
  elseif ($ext === 'gif') $mime = 'image/gif';
  elseif ($ext === 'webp') $mime = 'image/webp';
  elseif ($ext === 'mp4') $mime = 'video/mp4';
  elseif ($ext === 'webm') $mime = 'video/webm';
  elseif ($ext === 'ogg' || $ext === 'ogv') $mime = 'video/ogg';
  elseif ($ext === 'pdf') $mime = 'application/pdf';

  $download = isset($_GET['download']) && $_GET['download'] == '1';
  // Optional suggested filename from query (original user filename)
  $suggest = isset($_GET['name']) ? (string)$_GET['name'] : '';
  if ($suggest !== '') {
    // sanitize suggested name: allow letters, numbers, space, dash, underscore, dot
    $suggest = preg_replace('/[^A-Za-z0-9._\- ]+/', '', $suggest) ?? '';
    $suggest = trim($suggest);
    if ($suggest === '') $suggest = basename($abs);
  }
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: private, max-age=0, no-store');
  header('Content-Type: ' . $mime);
  $basename = basename($abs);
  $dlName = $suggest !== '' ? $suggest : $basename;
  // Ensure extension matches real file when missing
  $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
  if ($download) {
    $dlExt = strtolower(pathinfo($dlName, PATHINFO_EXTENSION));
    if ($dlExt === '') { $dlName .= '.' . $ext; }
  }
  $disposition = ($download ? 'attachment' : 'inline');
  header('Content-Disposition: ' . $disposition . '; filename="' . $dlName . '"; filename*=UTF-8\'' . rawurlencode($dlName));
  header('Content-Length: ' . filesize($abs));
  header('Accept-Ranges: bytes');
  $lastMod = gmdate('D, d M Y H:i:s', filemtime($abs)) . ' GMT';
  header('Last-Modified: ' . $lastMod);
  readfile($abs);
  exit;
}

// ZIP all media for a single fault ID
if (isset($_GET['zip_all']) && $_GET['zip_all'] === '1' && isset($_GET['fault'])) {
  $faultId = (int)$_GET['fault'];
  if ($faultId <= 0) { http_response_code(400); exit('Bad fault id'); }
  $fault = getFaultyProducts(null, $faultId);
  if (empty($fault) || empty($fault->media)) { http_response_code(404); exit('No media found'); }
  if (!class_exists('ZipArchive')) { http_response_code(503); exit('ZIP unavailable'); }
  $tmp = tempnam(sys_get_temp_dir(), 'wrzip_');
  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== TRUE) { http_response_code(500); exit('ZIP open failed'); }
  $baseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/assets/uploads/faulty-products/';
  foreach ($fault->media as $m) {
    $tempName = (string)($m->tempFileName ?? '');
    if ($tempName === '') continue;
    if (!preg_match('/^[A-Za-z0-9._-]{1,200}$/', $tempName)) continue;
    $abs = $baseDir . $tempName;
    if (!is_file($abs)) continue;
    $niceName = (string)($m->fileName ?? $tempName);
    // Ensure no directory traversal inside zip entry name
    $niceName = preg_replace('/[\\\/]+/', '_', $niceName);
    $zip->addFile($abs, $niceName);
  }
  $zip->close();
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  header('Content-Type: application/zip');
  $zipName = 'warranty-return-' . $faultId . '-media.zip';
  header('Content-Disposition: attachment; filename="' . $zipName . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp);
  @unlink($tmp);
  exit;
}

// Status update handler
if (isset($_POST['faultID'])) {
  $faultID = (int)($_POST['faultID'] ?? 0);
  $status  = (int)($_POST['status'] ?? 0);
  $fault   = getIndividualFaultyDBEntry($faultID);
  if (!is_null($fault) && $fault->wholesale_customer_submitted_id > 0) {
    if ($status === 1) {
      $websiteProductFault = getVapeShedFaultyProduct($faultID);
      if (!is_null($websiteProductFault)) {
        $couponID = createWholesaleFaultyProductCoupon($websiteProductFault->supply_cost, $fault->wholesale_customer_submitted_id);
        updateWholesaleFaultStatusCoupon($faultID, $couponID, 1);
      }
    } else {
      $websiteProductFault = getVapeShedFaultyProduct($faultID);
      if (!is_null($websiteProductFault)) {
        deleteCouponFromVapeShedWebsite($websiteProductFault->coupon_id);
      }
      updateWholesaleFaultStatusCoupon($faultID, null, $status);
    }
  }
  $sql = "UPDATE faulty_products SET supplier_status_timestamp = NOW(), supplier_status = ".$status." WHERE id = ".$faultID;
  sql_query_update_or_insert($sql);

  // Optional action log
  $tbl = sql_query_single_row("SHOW TABLES LIKE 'supplier_warranty_actions'");
  if ($tbl) {
    $faultRich = getFaultyProducts(null, $faultID);
    $supplierIdForLogRaw = null;
    if ($faultRich && isset($faultRich->supplier->id)) {
      $supplierIdForLogRaw = $faultRich->supplier->id;
    } elseif (isset($_GET['supplierID'])) {
      $supplierIdForLogRaw = $_GET['supplierID'];
    }
    $supplierIdForLogVal = 'NULL';
    if (isset($supplierIdForLogRaw) && ctype_digit((string)$supplierIdForLogRaw)) {
      $supplierIdForLogVal = (int)$supplierIdForLogRaw;
    }
    $action = ($status === 1 ? 'ACCEPT' : ($status === 2 ? 'DENY' : 'COMMENT'));
    $couponIdVal = isset($couponID) ? (int)$couponID : 'NULL';
    $creditCents = 'NULL';
    $ins = "INSERT INTO supplier_warranty_actions (faulty_product_id, supplier_id, performed_by_user_id, action, note, credit_cents, coupon_id, created_at) VALUES (".
      (int)$faultID.", ".($supplierIdForLogVal === 'NULL' ? 'NULL' : $supplierIdForLogVal).", NULL, '".$action."', NULL, ".$creditCents.", ".($couponIdVal=== 'NULL' ? 'NULL' : $couponIdVal).", NOW())";
    @sql_query_update_or_insert($ins);
  }
  exit;
}

// Delete media handler (remove file if present and log action)
if (isset($_POST['delete_media']) && $_POST['delete_media'] === '1') {
  header('Content-Type: application/json; charset=utf-8');
  $faultID = (int)($_POST['faultID'] ?? 0);
  $file    = isset($_POST['file']) ? (string)$_POST['file'] : '';
  $orig    = isset($_POST['original']) ? (string)$_POST['original'] : '';
  if ($faultID <= 0 || !preg_match('/^[A-Za-z0-9._-]{1,200}$/', $file)) {
    echo json_encode(['ok'=>false,'error'=>'Bad input']);
    exit;
  }
  $abs = wr_media_abs_path($file);
  $deleted = false;
  if ($abs && is_file($abs)) { $deleted = @unlink($abs); }
  // Optional action log
  $tbl = @sql_query_single_row("SHOW TABLES LIKE 'supplier_warranty_actions'");
  if ($tbl) {
    $note = 'Removed media: tempFileName=' . addslashes($file) . ($orig !== '' ? ', original=' . addslashes($orig) : '');
    $ins = "INSERT INTO supplier_warranty_actions (faulty_product_id, supplier_id, performed_by_user_id, action, note, credit_cents, coupon_id, created_at) VALUES (".
      (int)$faultID.", NULL, NULL, 'MEDIA_REMOVE', '".$note."', NULL, NULL, NOW())";
    @sql_query_update_or_insert($ins);
  }
  echo json_encode(['ok'=>true,'deleted'=>$deleted]);
  exit;
}

// Include supplier templates (html-header already loaded by supplier-header)
include("assets/template/html-header.php");
include("assets/template/supplier-header.php");

// Data
$faultyProducts   = [];
$completedProducts= [];
$deniedProducts   = [];
if (!empty($supplierId)) {
  $faultyProducts    = getFaultyProductsForSupplier($supplierId);            // pending/current
  $completedProducts = getFaultyProductsForSupplier($supplierId, null, 1);  // successful
  $deniedProducts    = getFaultyProductsForSupplier($supplierId, null, 2);  // denied
}
$pendingCount   = (int)count((array)$faultyProducts);
$completedCount = (int)count((array)$completedProducts);
$deniedCount    = (int)count((array)$deniedProducts);
?>

<!-- Warranty Returns Content -->
    <div class="container-fluid">
        <div class="fade-in">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Warranty Returns & Faulty Products</li>
      </ol>
          <div class="row">
            <div class="col">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                    <div>
                      <h4 class="mb-1">Warranty Returns & Faulty Products</h4>
                      <div class="text-muted small">Review and process pending warranty returns submitted by retail and wholesale customers.</div>
                    </div>
                    <div>
                      <a class="btn btn-sm btn-outline-primary" target="_blank" href="https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>&getCSV=true">
                        <i class="fa fa-file-csv"></i> Download Pending as CSV
                      </a>
                      <a class="btn btn-sm btn-outline-secondary ml-2" href="https://staff.vapeshed.co.nz/supplier-dashboard.php?supplierID=<?= esc($supplierId) ?>">
                        Back to Dashboard
                      </a>
                    </div>
                  </div>

                  <!-- KPIs -->
                  <div class="wr-hero">
                    <div class="kpi kpi-pending">
                      <div class="label"><i class="fa fa-hourglass-half"></i> Pending</div>
                      <div class="value"><?= (int)$pendingCount ?></div>
                    </div>
                    <div class="kpi kpi-success">
                      <div class="label"><i class="fa fa-check-circle"></i> Successful</div>
                      <div class="value"><?= (int)$completedCount ?></div>
                    </div>
                    <div class="kpi kpi-denied">
                      <div class="label"><i class="fa fa-times-circle"></i> Denied</div>
                      <div class="value"><?= (int)$deniedCount ?></div>
                    </div>
                  </div>

                  <!-- How-To & Status Effects -->
                  <div class="alert alert-info wr-howto" role="alert">
                    <div class="font-weight-bold mb-2"><i class="fa fa-info-circle"></i> How to process warranty returns</div>
                    <ul class="mb-2">
                      <li>Review each row and, if needed, click <strong>More Details</strong> to view full information and any media.</li>
                      <li>Use the <strong>Status</strong> dropdown to choose: <em>Pending</em>, <em>Successful Return</em>, or <em>Denied Return</em>.</li>
                      <li>Changes save immediately. The row will briefly highlight; on refresh it will move to the appropriate tab.</li>
                    </ul>
                    <div class="mb-2 font-weight-bold">What changing status does</div>
                    <ul class="mb-0">
                      <li><strong>Pending</strong> – No action is taken. The return remains in the <em>Current Returns</em> tab.</li>
                      <li><strong>Successful Return</strong> – If this return was submitted by a wholesale customer, a <em>credit coupon</em> is automatically created and linked to that customer. The status timestamp is updated and an action log may be recorded. On refresh, the item appears in <em>Successful Returns</em>.</li>
                      <li><strong>Denied Return</strong> – If a coupon was created previously, it is <em>deleted</em>. The status timestamp is updated and an action log may be recorded. On refresh, the item appears in <em>Denied Returns</em>.</li>
                    </ul>
                    <div class="small text-muted mt-2">Tip: Download the current Pending list as CSV using the button in the top-right.</div>
                  </div>

                  <?php if (isset($_GET['moreDetails'])): $detailId = (int)($_GET['moreDetails'] ?? 0); $fault = getFaultyProducts(null, $detailId); ?>
                    <div class="card mb-3">
                      <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Return Details #<?= esc($detailId) ?></strong>
                        <a class="btn btn-sm btn-outline-secondary" href="https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>">Back to Returns</a>
                      </div>
                      <div class="card-body">
                        <table class="table table-sm table-striped table-bordered">
                          <tbody>
                            <tr><td>Return ID:</td><td><?= esc($fault->id ?? $detailId) ?></td></tr>
                            <tr><td>Product:</td><td><?= esc($fault->product->name ?? '') ?></td></tr>
                            <tr><td>Supplier:</td><td><?= esc($fault->supplier->name ?? '') ?></td></tr>
                            <tr><td>Serial Number:</td><td><?= esc($fault->serial_number ?? '') ?></td></tr>
                            <tr><td>Date Submitted:</td><td><?= esc($fault->time_created ?? '') ?></td></tr>
                            <tr><td>Worked On By:</td><td><?= esc($fault->staff_member ?? '') ?></td></tr>
                            <tr><td>Store:</td><td><?= esc($fault->outlet->name ?? '') ?></td></tr>
                            <tr><td>Fault Description:</td><td><?= esc($fault->fault_desc ?? '') ?></td></tr>
                            <tr><td>Media:</td><td>
                              <?php if (!empty($fault->media)) { ?>
                                <?php 
                                  $imgIndex = 0; 
                                  $zipAllLink = 'https://staff.vapeshed.co.nz/supplier-warranty-returns.php?zip_all=1&fault='.(int)$detailId.'&supplierID='.esc($supplierId);
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                  <div class="text-muted small">Attached media files</div>
                                  <a class="btn btn-sm btn-outline-dark" href="<?= $zipAllLink ?>">
                                    <i class="fa fa-file-archive-o"></i> Download All as ZIP
                                  </a>
                                </div>
                                <div class="wr-media-grid" data-fault-id="<?= (int)$detailId ?>">
                                  <?php foreach ($fault->media as $m) {
                                    $fileName = (string)($m->fileName ?? 'file');
                                    $tempName = (string)($m->tempFileName ?? '');
                                    if ($tempName === '') continue;
                                    $titleRaw = (string)$fileName;
                                    $title    = esc($titleRaw);
                                    $mediaInline = 'https://staff.vapeshed.co.nz/supplier-warranty-returns.php?media=1&file=' . rawurlencode($tempName);
                                    $mediaDownload = $mediaInline . '&download=1&name=' . rawurlencode($fileName);
                                    $ext      = strtolower(pathinfo($tempName, PATHINFO_EXTENSION));
                                    $isImg    = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                    $isVid    = in_array($ext, ['mp4','webm','ogg','ogv']);
                                    $zipLink  = 'https://staff.vapeshed.co.nz/supplier-warranty-returns.php?zip=1&file=' . rawurlencode($tempName) . '&supplierID=' . esc($supplierId) . (isset($detailId)?'&moreDetails='.(int)$detailId:'');
                                    $abs = wr_media_abs_path($tempName);
                                    $missing = !$abs || !is_file($abs);
                                  ?>
                                    <div class="media-card<?= $isVid ? ' is-video' : '' ?>">
                                      <div class="media-thumb<?= $isVid ? ' is-video' : '' ?>">
                                        <?php if ($isImg): ?>
                                          <button type="button" class="media-image-open" data-role="img" data-src="<?= esc($mediaInline) ?>" data-title="<?= $title ?>" data-index="<?= (int)$imgIndex ?>" title="Open image">
                                            <img src="<?= esc($mediaInline) ?>" alt="<?= $title ?>">
                                          </button>
                                          <?php $imgIndex++; ?>
                                        <?php elseif ($isVid): ?>
                                          <button type="button" class="media-play" data-src="<?= esc($mediaInline) ?>" data-type="<?= esc($ext==='ogv'?'ogg':$ext) ?>" title="Play video">
                                            <i class="fa fa-play"></i>
                                          </button>
                                        <?php else: ?>
                                          <div class="file-blob">
                                            <i class="fa fa-file"></i>
                                          </div>
                                        <?php endif; ?>
                                        <?php if ($missing): ?>
                                          <span class="badge badge-warning" style="position:absolute; left:8px; top:8px;">Missing</span>
                                        <?php endif; ?>
                                      </div>
                                      <div class="media-meta" title="<?= $title ?>"><?= $title ?></div>
                                      <div class="media-actions">
                                        <?php if ($isImg): ?>
                                          <button class="btn btn-sm btn-outline-secondary media-image-open" data-src="<?= esc($mediaInline) ?>" data-title="<?= $title ?>">View</button>
                                        <?php elseif ($isVid): ?>
                                          <button class="btn btn-sm btn-outline-secondary media-open" data-src="<?= esc($mediaInline) ?>" data-type="<?= esc($ext==='ogv'?'ogg':$ext) ?>">Play</button>
                                          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= esc($mediaInline) ?>">Open</a>
                                        <?php else: ?>
                                          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= esc($mediaInline) ?>">Open</a>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= esc($mediaDownload) ?>">Download</a>
                                        <a class="btn btn-sm btn-outline-dark" href="<?= $zipLink ?>">ZIP</a>
                                        <button class="btn btn-sm btn-outline-danger media-delete" data-fault-id="<?= (int)$detailId ?>" data-file="<?= esc($tempName) ?>" data-original="<?= esc($fileName) ?>">Delete</button>
                                      </div>
                                    </div>
                                  <?php } ?>
                                </div>
                              <?php } else { ?>
                                <span class="text-muted">No media attached.</span>
                              <?php } ?>
                            </td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  <?php else: ?>

                    <ul class="nav nav-tabs" role="tablist">
                      <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-current" role="tab">Current Returns (<?= (int)$pendingCount ?>)</a></li>
                      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-success" role="tab">Successful Returns (<?= (int)$completedCount ?>)</a></li>
                      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-denied" role="tab">Denied Returns (<?= (int)$deniedCount ?>)</a></li>
                    </ul>

                    <div class="tab-content pt-3">
                      <div class="tab-pane active" id="tab-current" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <p class="mb-0">Products Currently Pending: <?= (int)$pendingCount ?></p>
                          <a class="small" target="_blank" href="https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>&getCSV=true">Download as CSV</a>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-sm table-striped table-hover align-middle">
                            <thead class="thead-light">
                              <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Serial</th>
                                <th>Store</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>View</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if ($pendingCount === 0): ?>
                                <tr><td colspan="7" class="text-muted">No pending returns.</td></tr>
                              <?php else: foreach ((array)$faultyProducts as $p): ?>
                                <tr>
                                  <td><?= esc($p->id) ?></td>
                                  <td><?= esc($p->product->name ?? '') ?></td>
                                  <td><?= esc($p->serial_number ?? '') ?></td>
                                  <td><?= esc($p->outlet->name ?? '') ?></td>
                                  <td><?= esc(date('d-m-Y', strtotime($p->time_created ?? 'now'))) ?></td>
                                  <td>
                                    <select data-faultid='<?= esc($p->id) ?>' class='faulty-product-status form-control form-control-sm'>
                                      <option value="0" <?php if ((int)$p->supplier_status === 0) echo 'selected'; ?>>Pending</option>
                                      <option value="1" <?php if ((int)$p->supplier_status === 1) echo 'selected'; ?>>Successful Return</option>
                                      <option value="2" <?php if ((int)$p->supplier_status === 2) echo 'selected'; ?>>Denied Return</option>
                                    </select>
                                  </td>
                                  <td><a href='https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>&moreDetails=<?= esc($p->id) ?>'>More Details</a></td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div class="tab-pane" id="tab-success" role="tabpanel">
                        <p>Products Completed: <?= (int)$completedCount ?></p>
                        <div class="table-responsive">
                          <table class="table table-sm table-striped table-hover align-middle">
                            <thead class="thead-light">
                              <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Serial</th>
                                <th>Store</th>
                                <th>Submitted</th>
                                <th>Status Changed</th>
                                <th>Status</th>
                                <th>View</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if ($completedCount === 0): ?>
                                <tr><td colspan="8" class="text-muted">No successful returns.</td></tr>
                              <?php else: foreach ((array)$completedProducts as $p): ?>
                                <tr>
                                  <td><?= esc($p->id) ?></td>
                                  <td><?= esc($p->product->name ?? '') ?></td>
                                  <td><?= esc($p->serial_number ?? '') ?></td>
                                  <td><?= esc($p->outlet->name ?? '') ?></td>
                                  <td><?= esc(date('d-m-Y', strtotime($p->time_created ?? 'now'))) ?></td>
                                  <td><?= esc($p->supplier_status_timestamp ?? '') ?></td>
                                  <td>
                                    <span class="badge badge-success">Successful</span>
                                  </td>
                                  <td><a href='https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>&moreDetails=<?= esc($p->id) ?>'>More Details</a></td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div class="tab-pane" id="tab-denied" role="tabpanel">
                        <p>Products Denied: <?= (int)$deniedCount ?></p>
                        <div class="table-responsive">
                          <table class="table table-sm table-striped table-hover align-middle">
                            <thead class="thead-light">
                              <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Serial</th>
                                <th>Store</th>
                                <th>Submitted</th>
                                <th>Status Changed</th>
                                <th>Status</th>
                                <th>View</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if ($deniedCount === 0): ?>
                                <tr><td colspan="8" class="text-muted">No denied returns.</td></tr>
                              <?php else: foreach ((array)$deniedProducts as $p): ?>
                                <tr>
                                  <td><?= esc($p->id) ?></td>
                                  <td><?= esc($p->product->name ?? '') ?></td>
                                  <td><?= esc($p->serial_number ?? '') ?></td>
                                  <td><?= esc($p->outlet->name ?? '') ?></td>
                                  <td><?= esc(date('d-m-Y', strtotime($p->time_created ?? 'now'))) ?></td>
                                  <td><?= esc($p->supplier_status_timestamp ?? '') ?></td>
                                  <td>
                                    <span class="badge badge-danger">Denied</span>
                                  </td>
                                  <td><a href='https://staff.vapeshed.co.nz/supplier-warranty-returns.php?supplierID=<?= esc($supplierId) ?>&moreDetails=<?= esc($p->id) ?>'>More Details</a></td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>

                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php include("assets/template/supplier-footer.php") ?>

<!-- Video Modal -->
<div class="modal fade wr-modal" id="wrVideoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <video controls preload="metadata"></video>
      </div>
    </div>
  </div>
  
</div>

<!-- Image Gallery Modal -->
<div class="modal fade wr-img-modal" id="wrImageModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body p-0 position-relative bg-dark text-center">
        <button type="button" class="close text-white position-absolute" style="right:8px; top:6px; z-index:3; opacity:.9;" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <button type="button" class="wr-img-prev" aria-label="Previous image"><i class="fa fa-chevron-left"></i></button>
        <img class="wr-img" alt="Image" />
        <button type="button" class="wr-img-next" aria-label="Next image"><i class="fa fa-chevron-right"></i></button>
        <div class="wr-img-caption text-white small px-3 py-2 text-left"></div>
        <div class="wr-img-actions p-2 text-right bg-dark">
          <a class="btn btn-sm btn-outline-light wr-img-download" href="#" download>
            <i class="fa fa-download"></i> Download
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.wr-hero { display:flex; gap:1rem; margin: 0 1px 1rem 1px; }
.wr-hero .kpi { flex:1; border-radius:14px; padding:14px 16px; position:relative; overflow:hidden; box-shadow:0 8px 24px rgba(20,40,100,.08); }
/* Sheen sweep (uses :before) */
.wr-hero .kpi:before { content:""; position:absolute; top:0; left:-150%; width:50%; height:100%; transform:skewX(-20deg); background:linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.55) 50%, rgba(255,255,255,0) 100%); opacity:0; pointer-events:none; }
.wr-hero .kpi.sheen-run:before { animation: wrSheen 1.25s ease-in-out; }
@keyframes wrSheen { 0%{ left:-150%; opacity:0 } 10%{opacity:.25} 50%{ left:120%; opacity:.45 } 100%{ left:150%; opacity:0 } }
/* Subtle top highlight */
.wr-hero .kpi:after { content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(255,255,255,.35), rgba(255,255,255,0) 40%); pointer-events:none; }
.wr-hero .kpi .label { display:flex; align-items:center; gap:.5rem; font-weight:700; font-size:0.95rem; opacity:.95; color:#223354; }
.wr-hero .kpi .label .fa { font-size:1.05rem; }
.wr-hero .kpi .value { font-size:1.9rem; font-weight:800; letter-spacing:-0.5px; margin-top:.25rem; color:#14233d; }
/* Blue/Silver palette for all three, with slight tone variations */
.kpi-pending { background-image:linear-gradient(135deg,#f4f7fc 0%, #e9f0fb 50%, #dee9f8 100%); border:1px solid #d5e0f3; }
.kpi-success { background-image:linear-gradient(135deg,#f5faff 0%, #e6f0fa 50%, #dbe6f4 100%); border:1px solid #cfdaec; }
.kpi-denied  { background-image:linear-gradient(135deg,#f3f6fb 0%, #e8eef8 50%, #dde5f2 100%); border:1px solid #d2dced; }
tr.status-updated { animation: wrFlash .9s ease-in-out 1; }
@keyframes wrFlash { 0%{background:#fffbd6;} 100%{background:transparent;} }
.wr-toast { position:fixed; right:16px; bottom:16px; background:#111; color:#fff; padding:10px 14px; border-radius:10px; opacity:.92; box-shadow:0 6px 30px #0005; z-index:9999; }
.wr-media-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
.wr-media-grid .media-card { background:#fff; border:1px solid #e7edf6; border-radius:10px; padding:10px; box-shadow:0 1px 4px rgba(16,32,64,.06); display:flex; flex-direction:column; }
.wr-media-grid .media-card.is-video { grid-column: span 2; }
.wr-media-grid .media-thumb { width:100%; aspect-ratio: 16/10; background:#f3f6fb; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative; }
.wr-media-grid .media-thumb img { width:100%; height:100%; object-fit:cover; }
.wr-media-grid .media-thumb button { border:0; padding:0; background:transparent; width:100%; height:100%; }
.wr-media-grid .media-thumb.is-video { background:linear-gradient(180deg,#1b2638,#0f182b); }
.wr-media-grid .media-thumb video { width:100%; height:100%; border-radius:8px; background:#000; }
.wr-media-grid .media-thumb .media-play { position:absolute; width:64px; height:64px; border-radius:50%; border:0; background:rgba(255,255,255,.9); color:#1c2a43; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 4px 24px rgba(0,0,0,.25); cursor:pointer; }
.wr-media-grid .media-thumb .media-play:hover { background:#fff; }
.wr-media-grid .file-blob { font-size:2rem; color:#9aa9c0; }
.wr-media-grid .media-meta { margin-top:8px; font-size:.9rem; color:#2a3854; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wr-media-grid .media-actions { margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; }

/* Modal for video playback */
.wr-modal .modal-dialog { max-width: 860px; }
.wr-modal .modal-body { padding:0; background:#000; }
.wr-modal video { width:100%; height:auto; display:block; }

/* Image modal */
.wr-img-modal .modal-dialog { max-width: 900px; }
.wr-img-modal .wr-img { max-width: 100%; max-height: 70vh; display:block; margin: 0 auto; }
.wr-img-modal .wr-img-prev, .wr-img-modal .wr-img-next {
  position:absolute; top:50%; transform: translateY(-50%);
  width:42px; height:42px; border-radius:50%; border:0; background:rgba(255,255,255,.85); color:#222; display:flex; align-items:center; justify-content:center;
  cursor:pointer; z-index:3;
}
.wr-img-modal .wr-img-prev:hover, .wr-img-modal .wr-img-next:hover { background:#fff; }
.wr-img-modal .wr-img-prev { left:10px; }
.wr-img-modal .wr-img-next { right:10px; }
.wr-img-modal .wr-img-caption { background: rgba(0,0,0,.4); position:absolute; left:0; right:0; bottom:36px; }
.wr-img-modal .wr-img-actions { position:absolute; right:0; bottom:0; left:0; border-top: 1px solid rgba(255,255,255,.1); }
</style>

<script>
function wrToast(msg){
  const el = document.createElement('div');
  el.className = 'wr-toast';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(()=>{ el.remove(); }, 2500);
}

$(document).on('change', '.faulty-product-status', function(){
  var _faultID = $(this).data('faultid');
  var _status = $(this).val();
  var $row = $(this).closest('tr');
  $.post('https://staff.vapeshed.co.nz/supplier-warranty-returns.php', { faultID: _faultID, status: _status }, function(){
    $row.addClass('status-updated');
    if (_status === '1') wrToast('Marked as Successful Return');
    else if (_status === '2') wrToast('Marked as Denied');
    else wrToast('Set to Pending');
  }).fail(function(){ wrToast('Update failed'); });
});

// Random sheen loop on KPI tiles
document.addEventListener('DOMContentLoaded', function(){
  function runSheen() {
    var tiles = document.querySelectorAll('.wr-hero .kpi');
    if (!tiles.length) return;
    var t = tiles[Math.floor(Math.random() * tiles.length)];
    t.classList.remove('sheen-run');
    void t.offsetWidth; // reflow to restart animation
    t.classList.add('sheen-run');
    var next = Math.floor(2200 + Math.random() * 4200);
    setTimeout(runSheen, next);
  }
  setTimeout(runSheen, 1200);
});

// Video modal playback
$(document).on('click', '.media-open, .media-play', function(){
  var src = $(this).data('src');
  var type = $(this).data('type');
  if (!src) return;
  var $modal = $('#wrVideoModal');
  if (!$modal.length) return;
  var $video = $modal.find('video');
  $video.empty();
  $('<source>').attr('src', src).attr('type', 'video/' + type).appendTo($video);
  $video[0].load();
  $modal.modal('show');
});

$('#wrVideoModal').on('hidden.bs.modal', function(){
  var video = $(this).find('video')[0];
  if (video) { video.pause(); video.currentTime = 0; }
});

// Image gallery modal
(function(){
  var gallery = [];
  var idx = 0;
  function openAt(newIdx){
    if (!gallery.length) return;
    idx = (newIdx + gallery.length) % gallery.length;
    var item = gallery[idx];
    var $m = $('#wrImageModal');
    $m.find('.wr-img').attr('src', item.src).attr('alt', item.title || 'Image');
    $m.find('.wr-img-caption').text(item.title || '');
    var dl = item.src.indexOf('download=1') === -1 ? (item.src + (item.src.indexOf('?')>-1?'&':'?') + 'download=1') : item.src;
    $m.find('.wr-img-download').attr('href', dl);
    $m.modal('show');
  }
  function buildGallery($grid){
    gallery = [];
    $grid.find('.media-image-open[data-role="img"]').each(function(){
      var $b = $(this);
      gallery.push({ src: $b.data('src'), title: $b.data('title') || '' });
    });
  }
  $(document).on('click', '.media-image-open', function(){
    var $grid = $(this).closest('.wr-media-grid');
    buildGallery($grid);
    var startSrc = $(this).data('src');
    var start = gallery.findIndex(function(g){ return g.src === startSrc; });
    openAt(start >= 0 ? start : 0);
  });
  $(document).on('click', '#wrImageModal .wr-img-prev', function(){ openAt(idx - 1); });
  $(document).on('click', '#wrImageModal .wr-img-next', function(){ openAt(idx + 1); });
  // Keyboard nav
  $(document).on('keydown', function(e){
    if (!$('#wrImageModal').hasClass('show')) return;
    if (e.key === 'ArrowLeft') openAt(idx - 1);
    else if (e.key === 'ArrowRight') openAt(idx + 1);
  });
})();

// Delete media
$(document).on('click', '.media-delete', function(){
  if (!confirm('Delete this media file? This cannot be undone.')) return;
  var file = $(this).data('file');
  var fault = $(this).data('fault-id');
  var original = $(this).data('original') || '';
  var $card = $(this).closest('.media-card');
  $.post('https://staff.vapeshed.co.nz/supplier-warranty-returns.php', { delete_media: '1', faultID: fault, file: file, original: original }, function(resp){
    try { var r = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e){ r = { ok:false }; }
    if (r && r.ok) {
      $card.fadeOut(150, function(){ $(this).remove(); });
      wrToast('Media removed');
    } else {
      wrToast('Delete failed');
    }
  }).fail(function(){ wrToast('Delete failed'); });
});
</script>


