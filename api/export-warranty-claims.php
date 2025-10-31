<?php
/**
 * Export Warranty Claims as CSV
 * 
 * Generates CSV export for all warranty claims
 * 
 * @return CSV file download with warranty claims list
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
requireAuth();

try {
    $db = db();
    $supplierID = getSupplierID();
    
    // Query all warranty claims for this supplier
    $query = "
        SELECT 
            fp.id,
            fp.id as claim_number,
            fp.product_id,
            p.name as product_name,
            p.sku,
            fp.issue_description,
            fp.supplier_status,
            CASE 
                WHEN fp.supplier_status = 0 THEN 'Pending'
                WHEN fp.supplier_status = 1 THEN 'Accepted'
                WHEN fp.supplier_status = 2 THEN 'Declined'
                ELSE 'Unknown'
            END as status_label,
            fp.supplier_response_notes,
            fp.supplier_status_timestamp,
            fp.created_at,
            o.name as outlet_name,
            fp.quantity,
            fp.serial_numbers
        FROM faulty_products fp
        LEFT JOIN vend_products p ON fp.product_id = p.id
        LEFT JOIN vend_outlets o ON fp.outlet_id = o.id
        WHERE fp.supplier_id = ?
        ORDER BY fp.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Set CSV headers
    $filename = 'warranty_claims_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write export information
    fputcsv($output, ['Warranty Claims Export']);
    fputcsv($output, ['Exported By', $_SESSION['supplier_name'] ?? 'Supplier']);
    fputcsv($output, ['Export Date', date('j M Y, g:ia')]);
    fputcsv($output, ['Total Claims', count($claims)]);
    fputcsv($output, []); // Blank line
    
    // Write header row
    fputcsv($output, [
        'Claim ID',
        'Claim Number',
        'Date Reported',
        'Product Name',
        'SKU',
        'Outlet',
        'Quantity',
        'Issue Description',
        'Status',
        'Response Date',
        'Response Notes',
        'Serial Numbers'
    ]);
    
    // Count statuses
    $pendingCount = 0;
    $acceptedCount = 0;
    $declinedCount = 0;
    
    // Write claims
    foreach ($claims as $claim) {
        fputcsv($output, [
            $claim['id'],
            $claim['claim_number'] ?? 'N/A',
            date('j M Y', strtotime($claim['created_at'])),
            $claim['product_name'] ?? 'Unknown Product',
            $claim['sku'] ?? 'N/A',
            $claim['outlet_name'] ?? 'Unknown',
            $claim['quantity'],
            $claim['issue_description'] ?? 'No description',
            $claim['status_label'],
            !empty($claim['supplier_status_timestamp']) ? date('j M Y', strtotime($claim['supplier_status_timestamp'])) : 'Not responded',
            $claim['supplier_response_notes'] ?? 'No response',
            $claim['serial_numbers'] ?? 'N/A'
        ]);
        
        if ($claim['supplier_status'] == 0) $pendingCount++;
        elseif ($claim['supplier_status'] == 1) $acceptedCount++;
        elseif ($claim['supplier_status'] == 2) $declinedCount++;
    }
    
    // Write summary
    fputcsv($output, []); // Blank line
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Claims', count($claims)]);
    fputcsv($output, ['Pending', $pendingCount]);
    fputcsv($output, ['Accepted', $acceptedCount]);
    fputcsv($output, ['Declined', $declinedCount]);
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log('Export Warranty Claims Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating CSV export');
}
