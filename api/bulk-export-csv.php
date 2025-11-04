<?php
/**
 * Bulk Export Orders to CSV or ZIP of CSVs
 * POST: order_ids JSON array
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
enforceApiRateLimit();
requireAuth();

$supplierID = getSupplierID();
$pdo = pdo();

// Parse order IDs
$orderIds = json_decode($_POST['order_ids'] ?? '[]', true);
if (!is_array($orderIds) || count($orderIds) === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No orders selected']);
    exit;
}

// Normalize ids
$orderIds = array_values(
    array_unique(
        array_filter(
            array_map('intval', $orderIds),
            fn($v) => $v > 0
        )
    )
);
if (empty($orderIds)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No valid order IDs']);
    exit;
}

// Helpers
$qPublic = $pdo->prepare("SELECT public_id FROM vend_consignments WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL");
$qItems = $pdo->prepare("SELECT p.sku, p.name as product_name, li.quantity as ordered, li.quantity_sent as sent, li.unit_cost
                         FROM vend_consignment_line_items li
                         LEFT JOIN vend_products p ON p.id = li.product_id
                         WHERE li.transfer_id = ? AND li.deleted_at IS NULL
                         ORDER BY p.name ASC");

// If only one, stream CSV directly
if (count($orderIds) === 1) {
    $oid = $orderIds[0];
    $qPublic->execute([$oid, $supplierID]);
    $pub = $qPublic->fetch(PDO::FETCH_ASSOC);
    if (!$pub) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    $qItems->execute([$oid]);
    $items = $qItems->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'PO_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$pub['public_id']) . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SKU','Product','Ordered','Sent','Unit Cost','Line Total']);
    foreach ($items as $it) {
        $ordered = (int)$it['ordered'];
        $sent = (int)$it['sent'];
        $unit = (float)$it['unit_cost'];
        fputcsv($out, [
            (string)$it['sku'],
            (string)$it['product_name'],
            $ordered,
            $sent,
            number_format($unit, 2, '.', ''),
            number_format($ordered * $unit, 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}

// Multiple: ZIP of per-order CSVs if ZipArchive available
if (class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'csvzip_');
    $zip = new ZipArchive();
    if (!$zip->open($tmp, ZipArchive::OVERWRITE)) {
        // Fallback to combined CSV if zip open fails
        goto combined;
    }

    foreach ($orderIds as $oid) {
        $qPublic->execute([$oid, $supplierID]);
        $pub = $qPublic->fetch(PDO::FETCH_ASSOC);
        if (!$pub) {
            continue; // skip unauthorized
        }
        $qItems->execute([$oid]);
        $items = $qItems->fetchAll(PDO::FETCH_ASSOC);

        $csvName = 'PO_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$pub['public_id']) . '.csv';
        $mem = fopen('php://temp', 'w+');
        fputcsv($mem, ['SKU','Product','Ordered','Sent','Unit Cost','Line Total']);
        foreach ($items as $it) {
            $ordered = (int)$it['ordered'];
            $sent = (int)$it['sent'];
            $unit = (float)$it['unit_cost'];
            fputcsv($mem, [
                (string)$it['sku'],
                (string)$it['product_name'],
                $ordered,
                $sent,
                number_format($unit, 2, '.', ''),
                number_format($ordered * $unit, 2, '.', '')
            ]);
        }
        rewind($mem);
        $zip->addFromString($csvName, stream_get_contents($mem));
        fclose($mem);
    }

    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="orders_csv_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

// Fallback combined CSV (no ZipArchive)
combined:
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders_combined_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Order','SKU','Product','Ordered','Sent','Unit Cost','Line Total']);
foreach ($orderIds as $oid) {
    $qPublic->execute([$oid, $supplierID]);
    $pub = $qPublic->fetch(PDO::FETCH_ASSOC);
    if (!$pub) { continue; }
    $qItems->execute([$oid]);
    foreach ($qItems->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $ordered = (int)$it['ordered'];
        $sent = (int)$it['sent'];
        $unit = (float)$it['unit_cost'];
        fputcsv($out, [
            (string)$pub['public_id'],
            (string)$it['sku'],
            (string)$it['product_name'],
            $ordered,
            $sent,
            number_format($unit, 2, '.', ''),
            number_format($ordered * $unit, 2, '.', '')
        ]);
    }
}
fclose($out);
exit;
