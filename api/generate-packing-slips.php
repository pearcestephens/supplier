<?php
/**
 * Generate Packing Slips PDF
 * Professional, compact packing slips with checkboxes and signature blocks
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

try {
    // Get order IDs from query string
    if (!isset($_GET['orders']) || empty($_GET['orders'])) {
        throw new Exception('No orders specified');
    }

    $orderIds = array_map('intval', explode(',', $_GET['orders']));

    if (empty($orderIds)) {
        throw new Exception('Invalid order IDs');
    }

    $pdo = pdo();
    $supplierID = getSupplierID();

    // Build placeholders for IN clause
    $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';

    // Query for order details
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.public_id as po_number,
            c.state as status,
            c.total_cost as total_amount,
            c.created_at,
            c.expected_delivery_date as due_date,
            o.name as outlet_name,
            o.address_line_1,
            o.address_line_2,
            o.suburb,
            o.city,
            o.postcode,
            o.phone,
            COUNT(DISTINCT li.product_id) as items_count,
            SUM(li.qty_arrived) as units_count
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        LEFT JOIN purchase_order_line_items li ON c.id = li.purchase_order_id AND li.deleted_at IS NULL
        WHERE c.id IN ($placeholders)
        AND c.supplier_id = ?
        AND c.deleted_at IS NULL
        GROUP BY c.id, c.public_id, c.state, c.total_cost, c.created_at, c.expected_delivery_date,
                 o.name, o.address_line_1, o.address_line_2, o.suburb, o.city, o.postcode, o.phone
        ORDER BY c.created_at DESC
    ");

    $params = array_merge($orderIds, [$supplierID]);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        throw new Exception('No orders found');
    }

    // Get line items for each order
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT
                li.product_id,
                p.name as product_name,
                p.sku,
                li.qty_arrived as quantity,
                li.unit_price as price,
                (li.qty_arrived * li.unit_price) as line_total
            FROM purchase_order_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            WHERE li.purchase_order_id = ?
            AND li.deleted_at IS NULL
            ORDER BY p.name ASC
        ");

        $stmt->execute([$order['id']]);
        $order['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get supplier details
    $stmt = $pdo->prepare("SELECT company_name, email, phone FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierID]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    // Generate HTML for packing slips
    $html = generatePackingSlipsHTML($orders, $supplier);

    // Output as HTML (for printing)
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;

} catch (Exception $e) {
    error_log('Generate Packing Slips Error: ' . $e->getMessage());
    http_response_code(400);
    echo '<h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
}

/**
 * Generate HTML for packing slips with print styles
 */
function generatePackingSlipsHTML(array $orders, array $supplier): string {
    $totalPages = count($orders);
    $currentPage = 0;

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slips - <?php echo count($orders); ?> Orders</title>
    <style>
        /* Reset and Base */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }

        /* Print Styles */
        @media print {
            body { margin: 0; padding: 0; }
            .page-break { page-break-after: always; }
            @page {
                size: A4;
                margin: 8mm 10mm 8mm 10mm;
            }
        }

        @media screen {
            body { background: #f0f0f0; padding: 20px; }
            .packing-slip {
                background: white;
                margin: 0 auto 20px;
                max-width: 210mm;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }

        /* Layout */
        .packing-slip {
            position: relative;
            padding: 12mm 10mm;
            min-height: 277mm; /* A4 height minus margins */
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6mm;
            padding-bottom: 3mm;
            border-bottom: 3px solid #000;
        }

        .header-left { flex: 1; }
        .header-right { text-align: right; }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }

        .doc-title {
            font-size: 24pt;
            font-weight: bold;
            letter-spacing: -0.5px;
        }

        .po-number {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 1mm;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4mm;
            margin-bottom: 5mm;
            padding: 3mm;
            background: #f8f8f8;
            border: 1px solid #ddd;
        }

        .info-section h3 {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1mm;
            color: #666;
        }

        .info-section p {
            font-size: 10pt;
            margin-bottom: 1mm;
            line-height: 1.4;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 60px;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 9pt;
        }

        .items-table thead {
            background: #000;
            color: #fff;
        }

        .items-table th {
            padding: 2mm;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .items-table td {
            padding: 2mm;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .items-table tbody tr:hover {
            background: #f0f0f0;
        }

        .checkbox-col { width: 25px; text-align: center; }
        .qty-col { width: 50px; text-align: center; font-weight: bold; }
        .sku-col { width: 100px; font-family: monospace; }
        .notes-col { width: 120px; }

        /* Checkbox */
        .checkbox {
            width: 14px;
            height: 14px;
            border: 2px solid #000;
            display: inline-block;
            vertical-align: middle;
        }

        /* Summary */
        .summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5mm;
            padding: 3mm;
            background: #f0f0f0;
            border: 2px solid #000;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            font-size: 8pt;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 1mm;
        }

        .summary-value {
            font-size: 16pt;
            font-weight: bold;
        }

        /* Signature Area */
        .signature-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5mm;
            margin-bottom: 5mm;
        }

        .signature-box {
            border: 2px solid #000;
            padding: 3mm;
            min-height: 20mm;
        }

        .signature-label {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 12mm;
            padding-top: 1mm;
        }

        .signature-sublabel {
            font-size: 7pt;
            color: #666;
        }

        /* Notes Section */
        .notes-section {
            border: 2px dashed #999;
            padding: 3mm;
            min-height: 15mm;
            margin-bottom: 3mm;
        }

        .notes-title {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .notes-lines {
            background: repeating-linear-gradient(
                transparent,
                transparent 5mm,
                #ddd 5mm,
                #ddd calc(5mm + 1px)
            );
            min-height: 12mm;
        }

        /* Update Notice Box */
        .update-notice {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 3mm;
            margin-bottom: 3mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .update-notice-content {
            flex: 1;
        }

        .update-notice-title {
            font-weight: bold;
            font-size: 10pt;
            color: #000;
            margin-bottom: 1mm;
        }

        .update-notice-text {
            font-size: 8pt;
            line-height: 1.4;
            color: #333;
        }

        .qr-code-box {
            width: 25mm;
            height: 25mm;
            border: 2px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            flex-shrink: 0;
            margin-left: 3mm;
        }

        .qr-placeholder {
            font-size: 7pt;
            text-align: center;
            color: #666;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 8mm;
            left: 10mm;
            right: 10mm;
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #666;
            padding-top: 2mm;
            border-top: 1px solid #ddd;
        }

        .page-number {
            font-weight: bold;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 1mm 2mm;
            border-radius: 2mm;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-urgent { background: #dc3545; color: white; }
        .status-packing { background: #ffc107; color: #000; }
        .status-ready { background: #28a745; color: white; }

        /* Utility */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', monospace; }
        .bold { font-weight: bold; }

        /* Barcode Placeholder */
        .barcode {
            height: 15mm;
            background: repeating-linear-gradient(
                90deg,
                #000 0px,
                #000 1px,
                #fff 1px,
                #fff 2px
            );
            margin: 2mm 0;
        }
    </style>
</head>
<body>
<?php
    foreach ($orders as $index => $order):
        $currentPage++;
        $isLastPage = ($currentPage === $totalPages);

        // Format address
        $addressParts = array_filter([
            $order['address_line_1'],
            $order['address_line_2'],
            $order['suburb'],
            $order['city'],
            $order['postcode']
        ]);
        $fullAddress = implode(', ', $addressParts);

        // Calculate totals
        $totalQty = array_sum(array_column($order['line_items'], 'quantity'));
        $totalValue = array_sum(array_column($order['line_items'], 'line_total'));

        // Status badge
        $statusClass = 'status-ready';
        $statusText = $order['status'];
        if (in_array($order['status'], ['OPEN', 'PACKING'])) {
            $statusClass = 'status-packing';
        }

        // Due date formatting
        $dueDate = $order['due_date'] ? date('d/m/Y', strtotime($order['due_date'])) : 'Not specified';
        $isOverdue = $order['due_date'] && strtotime($order['due_date']) < time();
?>
    <div class="packing-slip">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">THE VAPE SHED</div>
                <div style="font-size: 9pt; color: #666;">
                    <?php echo htmlspecialchars($supplier['company_name'] ?? 'Supplier'); ?><br>
                    <?php if (!empty($supplier['phone'])): ?>
                    Tel: <?php echo htmlspecialchars($supplier['phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <div class="doc-title">PACKING SLIP</div>
                <div class="po-number"><?php echo htmlspecialchars($order['po_number']); ?></div>
                <div class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-section">
                <h3>Deliver To</h3>
                <p class="bold"><?php echo htmlspecialchars($order['outlet_name']); ?></p>
                <p><?php echo htmlspecialchars($fullAddress); ?></p>
                <?php if (!empty($order['phone'])): ?>
                <p>Ph: <?php echo htmlspecialchars($order['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-section">
                <h3>Order Details</h3>
                <p><span class="info-label">Order Date:</span> <?php echo date('d/m/Y', strtotime($order['created_at'])); ?></p>
                <p><span class="info-label">Due Date:</span>
                    <span style="<?php echo $isOverdue ? 'color: #dc3545; font-weight: bold;' : ''; ?>">
                        <?php echo $dueDate; ?>
                        <?php if ($isOverdue): ?><strong> (OVERDUE)</strong><?php endif; ?>
                    </span>
                </p>
                <p><span class="info-label">Total Items:</span> <?php echo $order['items_count']; ?> products</p>
                <p><span class="info-label">Total Units:</span> <?php echo $totalQty; ?> units</p>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="checkbox-col">✓</th>
                    <th class="qty-col">QTY</th>
                    <th class="sku-col">SKU</th>
                    <th>PRODUCT NAME</th>
                    <th class="notes-col">NOTES / CONDITION</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['line_items'] as $item): ?>
                <tr>
                    <td class="checkbox-col"><span class="checkbox"></span></td>
                    <td class="qty-col"><?php echo $item['quantity']; ?></td>
                    <td class="sku-col"><?php echo htmlspecialchars($item['sku'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="notes-col"></td>
                </tr>
                <?php endforeach; ?>

                <?php
                // Fill remaining space with empty rows if less than 15 items
                $emptyRows = max(0, 15 - count($order['line_items']));
                for ($i = 0; $i < $emptyRows; $i++):
                ?>
                <tr>
                    <td class="checkbox-col"><span class="checkbox"></span></td>
                    <td class="qty-col"></td>
                    <td class="sku-col"></td>
                    <td></td>
                    <td class="notes-col"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">Total Products</div>
                <div class="summary-value"><?php echo $order['items_count']; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Units</div>
                <div class="summary-value"><?php echo $totalQty; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Order Value</div>
                <div class="summary-value">$<?php echo number_format($totalValue, 2); ?></div>
            </div>
        </div>

        <!-- Signature Area -->
        <div class="signature-area">
            <div class="signature-box">
                <div class="signature-label">Packed By</div>
                <div class="signature-line">
                    <div class="signature-sublabel">Name & Signature</div>
                </div>
                <div style="margin-top: 2mm; font-size: 8pt;">
                    Date: ______________ Time: ______________
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Received By</div>
                <div class="signature-line">
                    <div class="signature-sublabel">Name & Signature</div>
                </div>
                <div style="margin-top: 2mm; font-size: 8pt;">
                    Date: ______________ Time: ______________
                </div>
            </div>
        </div>

        <!-- Update Notice with QR Code -->
        <div class="update-notice">
            <div class="update-notice-content">
                <div class="update-notice-title">
                    ⚠️ FOUND SHORTFALLS OR NEED TO MAKE CHANGES?
                </div>
                <div class="update-notice-text">
                    <strong>Before this order arrives:</strong> Visit staff.vapeshed.co.nz/supplier to update quantities,
                    add missing items, or leave notes about shortages. Scan the QR code or use PO: <strong><?php echo htmlspecialchars($order['po_number']); ?></strong>
                    <br>
                    <strong>Changes made online will automatically update our system!</strong>
                </div>
            </div>
            <div class="qr-code-box">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php
                    echo urlencode('https://staff.vapeshed.co.nz/supplier/orders.php?po=' . $order['po_number']);
                ?>" alt="QR Code" style="width: 100%; height: 100%;">
            </div>
        </div>

        <!-- Notes Section -->
        <div class="notes-section">
            <div class="notes-title">Special Instructions / Delivery Notes</div>
            <div class="notes-lines"></div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>
                Printed: <?php echo date('d/m/Y H:i'); ?> |
                <strong>All items must be checked upon receipt</strong>
            </div>
            <div class="page-number">
                Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
            </div>
        </div>
    </div>

    <?php if (!$isLastPage): ?>
    <div class="page-break"></div>
    <?php endif; ?>

<?php endforeach; ?>

<script>
    // Auto-print on load
    window.onload = function() {
        // Small delay to ensure rendering is complete
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
    <?php
    return ob_get_clean();
}
