<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Export Order to PDF
 *
 * Generates a PDF document for a single order with line items
 * Uses TCPDF library if available, otherwise provides HTML fallback
 */

require_once __DIR__ . '/../bootstrap.php';

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$supplierID = $_SESSION['supplier_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    http_response_code(400);
    die('Invalid order ID');
}

// Verify order belongs to supplier
$stmt = $db->prepare("
    SELECT
        t.*,
        o.name as outlet_name,
        o.physical_address_1,
        o.physical_address_2,
        o.physical_suburb,
        o.physical_city,
        o.physical_postcode,
        o.physical_state,
        o.physical_phone_number as phone
    FROM vend_consignments t
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.id = ?
    AND t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.deleted_at IS NULL
");

$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die('Order not found or access denied');
}

// Get line items
$stmt = $db->prepare("
    SELECT
        p.sku,
        p.name as product_name,
        ti.quantity as qty_ordered,
        ti.quantity_sent as qty_received,
        ti.unit_cost,
        (ti.quantity * ti.unit_cost) as line_total
    FROM vend_consignment_line_items ti
    LEFT JOIN vend_products p ON ti.product_id = p.id
    WHERE ti.transfer_id = ?
    AND ti.deleted_at IS NULL
    ORDER BY p.name ASC
");

$stmt->bind_param('i', $orderId);
$stmt->execute();
$lineItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate totals with safe handling
$totalOrdered = !empty($lineItems) ? array_sum(array_column($lineItems, 'qty_ordered')) : 0;
$totalReceived = !empty($lineItems) ? array_sum(array_column($lineItems, 'qty_received')) : 0;
$grandTotal = !empty($lineItems) ? array_sum(array_column($lineItems, 'line_total')) : 0;

// Get supplier name if available
$supplierName = 'Supplier Portal';
if (isset($_SESSION['supplier_name'])) {
    $supplierName = $_SESSION['supplier_name'];
}

// For now, output as printable HTML (can be upgraded to TCPDF later)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order <?php echo htmlspecialchars($order['public_id'] ?? $orderId); ?></title>
    <style>
        @media print {
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-section {
            padding: 10px;
            background: #f5f5f5;
        }
        .info-section h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            font-weight: bold;
            background: #f0f0f0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-OPEN { background: #ffc107; color: #000; }
        .status-SENT { background: #17a2b8; color: #fff; }
        .status-RECEIVING { background: #007bff; color: #fff; }
        .status-RECEIVED { background: #28a745; color: #fff; }
        .status-CANCELLED { background: #dc3545; color: #fff; }
        .company-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #007bff;
        }
        .company-header h2 {
            margin: 0;
            color: #007bff;
            font-size: 28px;
        }
        .company-header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .btn-print {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
            margin-right: 10px;
        }
        .btn-print:hover {
            background: #0056b3;
        }
        .btn-download {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-download:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Document</button>
        <button class="btn-download" onclick="savePDF()">💾 Save as PDF</button>
    </div>

    <div class="company-header">
        <h2><?php echo htmlspecialchars($supplierName); ?></h2>
        <div class="subtitle">Purchase Order Document</div>
    </div>

    <div class="header">
        <h1>Purchase Order</h1>
        <div>
            <strong>Order ID:</strong> <?php echo htmlspecialchars($order['public_id'] ?? $orderId); ?><br>
            <strong>Status:</strong> <span class="status-badge status-<?php echo $order['state']; ?>"><?php echo $order['state']; ?></span><br>
            <strong>Date Created:</strong> <?php echo date('d M Y', strtotime($order['created_at'])); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-section">
            <h3>Delivery To:</h3>
            <strong><?php echo htmlspecialchars($order['outlet_name']); ?></strong><br>
            <?php if ($order['physical_address_1']): ?>
                <?php echo htmlspecialchars($order['physical_address_1']); ?><br>
                <?php if ($order['physical_address_2']): ?>
                    <?php echo htmlspecialchars($order['physical_address_2']); ?><br>
                <?php endif; ?>
                <?php echo htmlspecialchars($order['physical_suburb']); ?><br>
                <?php echo htmlspecialchars($order['physical_city']); ?> <?php echo htmlspecialchars($order['physical_postcode']); ?><br>
                <?php if ($order['physical_state']): echo htmlspecialchars($order['physical_state']); ?><br><?php endif; ?>
            <?php endif; ?>
            <?php if ($order['phone']): ?>
                <strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?><br>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>Order Details:</h3>
            <?php if ($order['expected_delivery_date']): ?>
                <strong>Expected Delivery:</strong> <?php echo date('d M Y', strtotime($order['expected_delivery_date'])); ?><br>
            <?php endif; ?>
            <?php if ($order['tracking_number']): ?>
                <strong>Tracking:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?><br>
            <?php endif; ?>
            <?php if ($order['supplier_reference']): ?>
                <strong>Reference:</strong> <?php echo htmlspecialchars($order['supplier_reference']); ?><br>
            <?php endif; ?>
            <strong>Total Items:</strong> <?php echo count($lineItems); ?> line items<br>
            <strong>Total Units:</strong> <?php echo number_format($totalOrdered); ?> units
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th class="text-center">Qty Ordered</th>
                <th class="text-center">Qty Received</th>
                <th class="text-right">Unit Cost</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lineItems)): ?>
                <tr>
                    <td colspan="6" class="text-center">No items in this order</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $item): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-center"><?php echo number_format($item['qty_ordered']); ?></td>
                        <td class="text-center"><?php echo number_format($item['qty_received']); ?></td>
                        <td class="text-right">$<?php echo number_format($item['unit_cost'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($item['line_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totals">
                    <td colspan="2" class="text-right">TOTALS:</td>
                    <td class="text-center"><?php echo number_format($totalOrdered); ?></td>
                    <td class="text-center"><?php echo number_format($totalReceived); ?></td>
                    <td></td>
                    <td class="text-right">$<?php echo number_format($grandTotal, 2); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($order['consignment_notes']): ?>
        <div class="info-section">
            <h3>Order Notes:</h3>
            <p><?php echo nl2br(htmlspecialchars($order['consignment_notes'])); ?></p>
        </div>
    <?php endif; ?>

    <div style="margin-top: 40px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
        <p>Generated on <?php echo date('d M Y H:i:s'); ?> | Document is valid without signature | Order ID: <?php echo htmlspecialchars($order['public_id'] ?? $orderId); ?></p>
    </div>

    <script>
        // Save as PDF function
        function savePDF() {
            // Use browser's print dialog with "Save as PDF" option
            window.print();
        }

        // Optional: Auto-print on load
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
