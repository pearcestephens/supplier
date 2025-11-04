<?php
/**
 * Sidebar Data Loader
 *
 * Loads notification counts for sidebar badges across ALL pages
 * MUST be included BEFORE sidebar-new.php component
 *
 * Sets:
 * - $pendingOrdersCount (OPEN + PACKING states only)
 * - $warrantyClaimsCount (supplier_status = 0)
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

// Initialize counts
$pendingOrdersCount = 0;
$warrantyClaimsCount = 0;

// Only load if we have a supplier ID
if (!isset($supplierID) || empty($supplierID)) {
    if (function_exists('Auth::getSupplierId')) {
        $supplierID = Auth::getSupplierId();
    }
}

if (isset($supplierID) && !empty($supplierID)) {
    try {
        $db = db();

        // Count PENDING orders (OPEN + PACKING states ONLY)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM vend_consignments
            WHERE supplier_id = ?
            AND state IN ('OPEN', 'PACKING')
            AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $pendingOrdersCount = (int)($result['count'] ?? 0);
        $stmt->close();

        // Count PENDING warranty claims (supplier_status = 0)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM faulty_products fp
            INNER JOIN vend_products vp ON fp.product_id = vp.id
            WHERE vp.supplier_id = ?
            AND fp.supplier_status = 0
            AND vp.deleted_at IS NULL
        ");
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $warrantyClaimsCount = (int)($result['count'] ?? 0);
        $stmt->close();

    } catch (Exception $e) {
        error_log("Sidebar data loader error: " . $e->getMessage());
        // Keep counts at 0 on error
    }
}
