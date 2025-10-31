<?php
/**
 * supplier-claim.php
 * --------------------------------------------------------------------------
 * Public read-only claim view. No session, tokenised access:
 *    supplier-claim.php?t=<token>
 * Fallback legacy: supplierID + claim (NOT recommended; keep if you must).
 */
include("assets/functions/config.php");
include("assets/functions/purchase-orders-ops.php");

// Security: prefer token
$token = isset($_GET['t']) ? (string)$_GET['t'] : '';
$claimId = isset($_GET['claim']) ? (int)$_GET['claim'] : 0;

$view = null;
if ($token !== '') {
  $view = poops_claim_token_view($token);
} elseif ($claimId>0 && isset($_GET['supplierID'])) {
  // legacy non-token path (kept for compatibility)
  $v = poops_claim_view($claimId);
  if ($v && (string)$v['header']['supplier_id'] === (string)$_GET['supplierID']) {
    $view = $v;
  }
}

if (!$view) {
  http_response_code(404);
  echo "<!doctype html><html><head><title>Not found</title></head><body style='font-family:sans-serif;padding:24px;'>".
       "<h3>Claim not found or link expired</h3><div>Please contact accounts@ecigdis.co.nz</div></body></html>";
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Claim #<?php echo (int)$view['header']['claim_id']; ?> — The Vape Shed</title>
  <link rel="stylesheet" href="/assets/template/css/bootstrap.min.css">
  <style>
    body{ background:#f7f8fb; }
    .card{ border:1px solid #e6e9f0; }
    .logo{ max-height:46px; }
  </style>
</head>
<body>
  <div class="container my-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <img src="/assets/template/vapeshed/images/vape-shed-logo.png" alt="The Vape Shed" class="logo">
      <div class="text-muted">Claim portal</div>
    </div>

    <div class="card">
      <div class="card-header">
        <strong>Claim #<?php echo (int)$view['header']['claim_id']; ?></strong>
        <div class="small text-muted">PO #<?php echo (int)$view['header']['purchase_order_id']; ?> • Outlet <?php echo htmlspecialchars($view['header']['outlet_id']); ?></div>
      </div>
      <div class="card-body">
        <div class="mb-2">Supplier: <strong><?php echo htmlspecialchars($view['header']['supplier_name']); ?></strong></div>

        <div class="table-responsive">
          <table class="table table-sm">
            <thead class="thead-light"><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">Unit ex‑GST</th><th class="text-right">Extended</th></tr></thead>
            <tbody>
              <?php
                $sum = 0.0;
                foreach ($view['lines'] as $ln){
                  $ext = is_null($ln['extended_cost_ex_gst']) ? ($ln['qty'] * (float)($ln['unit_cost_ex_gst'] ?? 0)) : (float)$ln['extended_cost_ex_gst'];
                  $sum += $ext;
                  echo '<tr>';
                  echo '<td>'.htmlspecialchars($ln['product_name'] ?: $ln['product_id']).'</td>';
                  echo '<td class="text-right">'.(int)$ln['qty'].'</td>';
                  echo '<td class="text-right">'.(is_null($ln['unit_cost_ex_gst'])?'—':number_format((float)$ln['unit_cost_ex_gst'],4)).'</td>';
                  echo '<td class="text-right">'.number_format($ext,2).'</td>';
                  echo '</tr>';
                }
              ?>
            </tbody>
            <tfoot><tr><th colspan="3" class="text-right">Total ex‑GST</th><th class="text-right"><?php echo number_format($sum,2); ?></th></tr></tfoot>
          </table>
        </div>

        <div class="mt-3"><strong>Evidence</strong></div>
        <div class="row">
        <?php
          if (!$view['evidence']) echo '<div class="col-12 small text-muted">No evidence files available.</div>';
          else foreach ($view['evidence'] as $e){
            $isImg = preg_match('~^image/~', $e['mime']);
            echo '<div class="col-md-3 mb-2"><div class="card"><div class="card-body p-2">';
            if ($isImg) echo '<img src="'.htmlspecialchars($e['url']).'" alt="'.htmlspecialchars($e['filename']).'" style="max-width:100%;height:auto;">';
            else echo '<div class="small"><a target="_blank" href="'.htmlspecialchars($e['url']).'"><i class="fa fa-file-pdf-o"></i> '.htmlspecialchars($e['filename']).'</a></div>';
            echo '<div class="small text-muted">'.htmlspecialchars($e['created_at']).'</div>';
            echo '</div></div></div>';
          }
        ?>
        </div>

        <div class="alert alert-info small mt-3 mb-0">
          Questions? Reply to <a href="mailto:accounts@ecigdis.co.nz">accounts@ecigdis.co.nz</a> with this claim number.
        </div>
      </div>
    </div>

  </div>
  <script src="/assets/template/js/jquery.min.js"></script>
  <script src="/assets/template/js/bootstrap.bundle.min.js"></script>
</body>
</html>
