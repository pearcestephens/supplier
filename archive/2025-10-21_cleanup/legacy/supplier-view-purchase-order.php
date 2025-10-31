<?php
// supplier-view-purchase-order.php — Business-class, hardened PO detail (UUID-safe + CSV + outlet code)
include("assets/functions/supplier-config.php");

if (!function_exists('esc')) {
  function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function r2a($row){ return is_array($row) ? $row : (is_object($row) ? get_object_vars($row) : []); }
function rows_to_arrays($rows){ if(!is_array($rows)) return []; foreach($rows as $i=>$r){ $rows[$i]=r2a($r); } return $rows; }
function fmt_dt($ts){ return $ts ? substr($ts,0,19) : ''; }

// ================== PARAMS & SANITIZE ==================
$poId       = isset($_GET["purchaseOrderID"]) ? (int)$_GET["purchaseOrderID"] : 0;
$supplierId = isset($_GET["supplierID"]) ? (string)$_GET["supplierID"] : '';

if ($poId <= 0 || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $supplierId)) {
  http_response_code(400);
  exit("Bad request");
}

global $con; // provided by supplier-config.php
$poIdSafe = (int)$poId;
$sSafe    = mysqli_real_escape_string($con, $supplierId);

// ================== SELF-SERVE CSV (SKU,HANDLE,NAME,QTY) ==================
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  // Authorize
  $ok = sql_query_single_row("
    SELECT 1 FROM purchase_orders
    WHERE purchase_order_id = $poIdSafe AND supplier_id = '$sSafe' LIMIT 1
  ");
  if (!$ok) { http_response_code(403); exit("Not allowed"); }

  $rows = sql_query_collection("
    SELECT
      UPPER(COALESCE(vp.sku,''))        AS sku,
      UPPER(COALESCE(vp.handle,''))     AS handle,
      COALESCE(vp.name, li.product_id)  AS product_name,
      li.order_qty                       AS qty
    FROM purchase_order_line_items li
    LEFT JOIN vend_products vp ON vp.id = li.product_id
    WHERE li.purchase_order_id = $poIdSafe
    ORDER BY vp.name
  ");
  // Security headers
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="po_'.$poId.'_lines.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['SKU','HANDLE','NAME','QTY']);
  foreach (rows_to_arrays($rows) as $r) {
    fputcsv($out, [
      $r['sku'] ?? '',
      $r['handle'] ?? '',
      $r['product_name'] ?? ($r['product_id'] ?? ''),
      (int)($r['qty'] ?? 0),
    ]);
  }
  fclose($out);
  exit;
}

// ================== HEADER (INCL OUTLET CODE) ==================
$poRow = sql_query_single_row("
  SELECT
    po.*,
    vo.name AS outlet_name,
    /* Outlet 3-letter code: use store_code if set, else derive from name */
    CASE
      WHEN vo.store_code IS NOT NULL AND vo.store_code <> '' THEN vo.store_code
      ELSE UPPER(SUBSTRING(vo.name,1,3))
    END AS outlet_code,
    vo.physical_address_1, vo.physical_address_2,
    vo.physical_suburb,   vo.physical_city,       vo.physical_postcode
  FROM purchase_orders po
  LEFT JOIN vend_outlets vo ON vo.id = po.outlet_id AND vo.deleted_at = '0000-00-00 00:00:00'
  WHERE po.purchase_order_id = $poIdSafe
    AND po.supplier_id = '$sSafe'
  LIMIT 1
");
if (!$poRow) { http_response_code(404); exit("Not found"); }
$po = r2a($poRow);

// ================== LINES ==================
$lines = sql_query_collection("
  SELECT
    li.product_id,
    li.order_qty,
    li.qty_arrived,
    UPPER(COALESCE(vp.sku,''))    AS sku,
    UPPER(COALESCE(vp.handle,'')) AS handle,
    COALESCE(vp.name, li.product_id) AS product_name
  FROM purchase_order_line_items li
  LEFT JOIN vend_products vp ON vp.id = li.product_id
  WHERE li.purchase_order_id = $poIdSafe
  ORDER BY vp.name
");
$lines = rows_to_arrays($lines);

// Quick stats
$items_total   = count($lines);
$units_ordered = 0; $units_arrived = 0;
foreach ($lines as $ln) { $units_ordered += (int)($ln['order_qty'] ?? 0); $units_arrived += (int)($ln['qty_arrived'] ?? 0); }

// Address
$addrParts = [];
foreach (['physical_address_1','physical_address_2','physical_suburb','physical_city','physical_postcode'] as $k) {
  if (!empty($po[$k])) $addrParts[] = $po[$k];
}
$addr = implode(', ', $addrParts);

// Totals
$hasTotals  = isset($po['total_inc_gst']) || isset($po['subtotal_ex_gst']);
$totalsMode = $po['totals_mode'] ?? null;
$totalInc   = isset($po['total_inc_gst'])    ? (float)$po['total_inc_gst']    : null;
$subtotal   = isset($po['subtotal_ex_gst'])  ? (float)$po['subtotal_ex_gst']  : null;
$gst        = isset($po['gst'])              ? (float)$po['gst']              : null;

// ================== VIEW ==================
include("assets/template/html-header.php");
include("assets/template/supplier-header.php");
?>
<style>
/* Scoped, business-class polish */
#poView{ font:400 0.95rem/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans"; color:#1e2738; }
#poView .card{ border:1px solid #edf0f6; border-radius:14px; box-shadow:0 8px 24px rgba(20,40,100,.08); }
#poView .header{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
#poView .title{ font-weight:700; letter-spacing:.2px; color:#1f2b4e; }
#poView .meta{ color:#6a7286; }

/* Chips row */
#poView .chips{ display:flex; flex-wrap:wrap; gap:10px; margin:.5rem 0 1rem; }
#poView .chip{ border:1px solid rgba(30,50,120,.12); border-radius:999px; padding:8px 12px; background:#fff; display:flex; align-items:center; gap:8px; }
#poView .chip .lab{ color:#5a657a; font-weight:600; }
#poView .chip .val{ font-weight:700; color:#223a8f; }

/* Address panel */
#poView .addr{ border-left:4px solid #2b59f4; background:#fbfcff; border-radius:10px; padding:12px 14px; margin-bottom:10px; }
#poView .addr .cap{ color:#6a7286; font-weight:700; margin-bottom:4px; }

/* Table layout */
#poView .tbl thead th{ white-space:nowrap; font-weight:600; color:#273b67; }
#poView .tbl td.mono, #poView .tbl th.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
#poView .tbl .qty{ text-align:right; width:110px; }
#poView .tbl .name{ width:55%; }
#poView .tbl .sku{ width:170px; }
/* Ensure spacing between header action buttons across Bootstrap versions */
#poView .actions .btn + .btn{ margin-left: .75rem; }
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/supplier-sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="/supplier-portal.php?supplierID=<?= esc($supplierId) ?>">Supplier Portal</a></li>
        <li class="breadcrumb-item active">View Purchase Order #<?= (int)$poId; ?></li>
      </ol>

      <div class="container-fluid" id="poView">
        <div class="card mb-3">
          <div class="card-body">
            <!-- Header -->
            <div class="header">
              <div>
                <div class="title h5 mb-1">Purchase Order #<?= (int)$poId; ?></div>
                <div class="meta small">
                  Created: <strong><?= esc(fmt_dt($po['date_created'] ?? '')); ?></strong>
                  <?php if (!empty($po['completed_timestamp'])): ?>
                    &nbsp;•&nbsp; Completed: <strong><?= esc(fmt_dt($po['completed_timestamp'])); ?></strong>
                  <?php endif; ?>
                </div>
              </div>
              <div class="d-flex actions">
                <a class="btn btn-outline-secondary btn-sm"
                   href="/supplier-view-purchase-order.php?download=csv&purchaseOrderID=<?= (int)$poId; ?>&supplierID=<?= esc($supplierId); ?>">
                  <i class="fa fa-file-csv"></i> Download CSV
                </a>
                <a class="btn btn-primary btn-sm" href="https://staff.vapeshed.co.nz/supplier-dashboard.php?supplierID=<?= esc($supplierId) ?>">
                  Back to Portal
                </a>
              </div>
            </div>

            <!-- Chips -->
            <div class="chips">
              <div class="chip"><span class="lab">Outlet</span><span class="val"><?= esc((string)($po['outlet_name'] ?? '')); ?><?php if(!empty($po['outlet_code'])) echo ' ('.esc($po['outlet_code']).')'; ?></span></div>
              <div class="chip"><span class="lab">Items / Units</span><span class="val"><?= (int)$items_total; ?> items • <?= (int)$units_ordered; ?> units</span></div>
              <div class="chip"><span class="lab">Arrived</span><span class="val"><?= (int)$units_arrived; ?> units</span></div>
              <?php
                $refs=[]; if(!empty($po['packing_slip_no'])) $refs[]='Slip: '.esc($po['packing_slip_no']);
                if(!empty($po['invoice_no'])) $refs[]='Invoice: '.esc($po['invoice_no']);
                if($refs) echo '<div class="chip"><span class="lab">References</span><span class="val">'.implode(' · ',$refs).'</span></div>';
                if ($hasTotals) {
                  echo '<div class="chip"><span class="lab">Total</span><span class="val">';
                  if ($totalInc !== null) {
                    echo number_format($totalInc,2);
                    if ($totalsMode && $totalsMode !== 'INC_GST') echo ' <span class="meta">(mode: '.esc($totalsMode).')</span>';
                  } elseif ($subtotal !== null && $gst !== null) {
                    echo number_format($subtotal + $gst, 2) . ' <span class="meta">(ex '.number_format($subtotal,2).', GST '.number_format($gst,2).')</span>';
                  } else {
                    echo number_format($subtotal ?? 0, 2) . ' <span class="meta">(ex GST)</span>';
                  }
                  echo '</span></div>';
                }
              ?>
            </div>

            <!-- Address -->
            <div class="addr">
              <div class="cap small">Delivery Address</div>
              <div>The Vape Shed</div>
              <div><?= esc($addr ?: '—'); ?></div>
            </div>

            <!-- Lines -->
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle tbl">
                <?php $showArrivedCol = ($units_arrived > 0); ?>
                <colgroup>
                  <col class="sku"><col class="name"><col class="qty"><?php if($showArrivedCol): ?><col class="qty"><?php endif; ?>
                </colgroup>
                <thead>
                  <tr>
                    <th class="mono">SKU</th>
                    <th class="name">Name</th>
                    <th class="qty">Qty Ordered</th>
                    <?php if($showArrivedCol): ?><th class="qty">Qty Arrived</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$lines): $colspan = $showArrivedCol ? 4 : 3; ?>
                    <tr><td colspan="<?= $colspan ?>" class="text-muted">No lines on this purchase order.</td></tr>
                  <?php else: foreach ($lines as $ln): ?>
                    <tr>
                      <?php
                        $skuRaw = trim((string)($ln['sku'] ?? ''));
                        $showSku = ($skuRaw !== '' && strlen($skuRaw) > 9 && preg_match('/^\d+$/', $skuRaw));
                        $skuDisplay = $showSku ? $skuRaw : 'N/A';
                      ?>
                      <td class="mono"><?= esc($skuDisplay); ?></td>
                      <td>
                        <?= esc((string)($ln['product_name'] ?? $ln['product_id'] ?? '')); ?>
                      </td>
                      <td class="qty"><?= (int)($ln['order_qty'] ?? 0); ?></td>
                      <?php if($showArrivedCol): ?>
                        <td class="qty"><?= ($ln['qty_arrived'] === null || $ln['qty_arrived'] === '') ? '' : (int)$ln['qty_arrived']; ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

          </div><!-- /card-body -->
        </div><!-- /card -->
      </div><!-- /container -->
    </main>
    <?php include("assets/template/personalisation-menu.php"); ?>
  </div>

<?php include("assets/template/html-footer.php"); ?>
<?php include("assets/template/footer.php"); ?>
