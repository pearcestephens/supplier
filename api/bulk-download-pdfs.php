<?php
/**
 * Bulk Download Packing Slips as ZIP
 *
 * Creates a ZIP file containing PDFs for multiple orders
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

$supplierID = getSupplierID();
$pdo = pdo();

// Get order IDs from POST
$orderIds = json_decode($_POST['order_ids'] ?? '[]', true);

if (empty($orderIds) || !is_array($orderIds)) {
    die('No orders selected');
}

// Verify all orders belong to this supplier
$placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT id, public_id
    FROM vend_consignments
    WHERE id IN ($placeholders)
    AND supplier_id = ?
");
$stmt->execute([...$orderIds, $supplierID]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die('No valid orders found');
}

// Create temporary directory for PDFs
$tempDir = sys_get_temp_dir() . '/packing_slips_' . time();
mkdir($tempDir, 0755, true);

// Generate PDF for each order
$pdfFiles = [];
foreach ($orders as $order) {
    $pdfPath = $tempDir . '/' . $order['public_id'] . '.pdf';

    // Generate PDF (call your existing PDF generation)
    $pdfContent = file_get_contents('https://' . $_SERVER['HTTP_HOST'] . '/supplier/api/export-order-pdf.php?id=' . $order['id']);

    if ($pdfContent) {
        file_put_contents($pdfPath, $pdfContent);
        $pdfFiles[] = $pdfPath;
    }
}

// Create ZIP file
$zipFilename = 'packing_slips_' . date('Y-m-d_His') . '.zip';
$zipPath = $tempDir . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
    foreach ($pdfFiles as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();

    // Send ZIP to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);

    // Cleanup
    foreach ($pdfFiles as $file) {
        unlink($file);
    }
    unlink($zipPath);
    rmdir($tempDir);

} else {
    die('Failed to create ZIP file');
}
