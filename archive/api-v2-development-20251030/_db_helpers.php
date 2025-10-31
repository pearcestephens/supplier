<?php
/**
 * Database Query Helpers
 * Common queries used across API endpoints for Supplier Portal
 * 
 * Part of: Supplier Portal Redesign - Phase 1
 * Created: October 22, 2025
 * Version: 2.0
 * 
 * @package SupplierPortal\API\v2
 */

declare(strict_types=1);

require_once __DIR__ . '/../../supplier-config.php';
require_once __DIR__ . '/../../lib/Database.php';

/**
 * Supplier-specific database queries
 */
class SupplierQueries {
    private Database $db;
    private ?string $supplierId;
    
    public function __construct(?string $supplierId = null) {
        $this->db = new Database();
        $this->supplierId = $supplierId;
    }
    
    /**
     * Set supplier ID for queries
     */
    public function setSupplierId(string $supplierId): void {
        $this->supplierId = $supplierId;
    }
    
    /**
     * Get purchase orders for supplier
     * 
     * @param array $filters Filtering options
     * @return array
     */
    public function getPurchaseOrders(array $filters = []): array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        $sql = "SELECT 
                    t.id,
                    t.name AS po_number,
                    t.status,
                    t.destination_outlet_id AS outlet_id,
                    vo.name AS outlet_name,
                    vo.store_code AS outlet_code,
                    t.due_at,
                    t.created_at,
                    t.sent_at,
                    t.received_at,
                    COUNT(ti.id) AS item_count,
                    SUM(ti.qty_requested) AS total_qty_requested,
                    SUM(ti.qty_received) AS total_qty_received,
                    SUM(ti.qty_requested * ti.cost) AS total_value_requested,
                    SUM(ti.qty_received * ti.cost) AS total_value_received
                FROM transfers t
                LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                LEFT JOIN vend_outlets vo ON t.destination_outlet_id = vo.id
                LEFT JOIN vend_products vp ON ti.product_id = vp.id
                WHERE t.transfer_category = 'PURCHASE_ORDER'
                AND vp.supplier_id = ?";
        
        $params = [$this->supplierId];
        
        // Apply filters
        if (!empty($filters['status']) && is_array($filters['status'])) {
            $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
            $sql .= " AND t.status IN ({$placeholders})";
            $params = array_merge($params, $filters['status']);
        } elseif (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['outlet_id'])) {
            $sql .= " AND t.destination_outlet_id = ?";
            $params[] = $filters['outlet_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.name LIKE ? OR t.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        // Add pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get detailed purchase order information
     * 
     * @param int $transferId Transfer ID
     * @return array|null
     */
    public function getPurchaseOrderDetail(int $transferId): ?array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        // First verify this PO belongs to this supplier
        $checkSql = "SELECT t.id 
                     FROM transfers t
                     JOIN transfer_items ti ON t.id = ti.transfer_id
                     JOIN vend_products vp ON ti.product_id = vp.id
                     WHERE t.id = ? AND t.transfer_category = 'PURCHASE_ORDER' 
                     AND vp.supplier_id = ?
                     LIMIT 1";
        
        $check = $this->db->query($checkSql, [$transferId, $this->supplierId]);
        if (empty($check)) {
            return null; // Not found or not authorized
        }
        
        // Get basic transfer info
        $transferSql = "SELECT 
                           t.*,
                           vo_dest.name AS destination_outlet_name,
                           vo_dest.store_code AS destination_outlet_code,
                           vo_src.name AS source_outlet_name,
                           vo_src.store_code AS source_outlet_code
                       FROM transfers t
                       LEFT JOIN vend_outlets vo_dest ON t.destination_outlet_id = vo_dest.id
                       LEFT JOIN vend_outlets vo_src ON t.source_outlet_id = vo_src.id
                       WHERE t.id = ?";
        
        $transfer = $this->db->query($transferSql, [$transferId]);
        if (empty($transfer)) {
            return null;
        }
        
        $result = $transfer[0];
        
        // Get line items
        $itemsSql = "SELECT 
                        ti.*,
                        vp.name AS product_name,
                        vp.sku AS product_sku,
                        vp.supply_price,
                        vp.price_including_tax,
                        vb.name AS brand_name
                     FROM transfer_items ti
                     JOIN vend_products vp ON ti.product_id = vp.id
                     LEFT JOIN vend_brands vb ON vp.brand_id = vb.id
                     WHERE ti.transfer_id = ?
                     ORDER BY ti.id";
        
        $result['items'] = $this->db->query($itemsSql, [$transferId]);
        
        // Get shipments
        $shipmentsSql = "SELECT * FROM consignment_shipments 
                        WHERE transfer_id = ? 
                        ORDER BY created_at DESC";
        
        $result['shipments'] = $this->db->query($shipmentsSql, [$transferId]);
        
        // Get logs
        $logsSql = "SELECT * FROM consignment_logs 
                   WHERE transfer_id = ? 
                   ORDER BY created_at DESC 
                   LIMIT 50";
        
        $result['logs'] = $this->db->query($logsSql, [$transferId]);
        
        return $result;
    }
    
    /**
     * Get warranty claims for supplier
     * 
     * @param array $filters Filtering options
     * @return array
     */
    public function getWarrantyClaims(array $filters = []): array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        $sql = "SELECT 
                    fp.id,
                    fp.product_id,
                    vp.name AS product_name,
                    vp.sku,
                    vp.brand_id,
                    vb.name AS brand_name,
                    fp.serial_number,
                    fp.fault_desc,
                    fp.supplier_status,
                    fp.supplier_update_status,
                    fp.supplier_status_timestamp,
                    fp.created_at,
                    fp.outlet_id,
                    vo.name AS outlet_name,
                    vo.store_code AS outlet_code,
                    fp.staff_member,
                    COUNT(fpn.id) AS note_count
                FROM faulty_products fp
                JOIN vend_products vp ON fp.product_id = vp.id
                LEFT JOIN vend_brands vb ON vp.brand_id = vb.id
                LEFT JOIN vend_outlets vo ON fp.outlet_id = vo.id
                LEFT JOIN faulty_product_notes fpn ON fp.id = fpn.faulty_product_id
                WHERE vp.supplier_id = ?";
        
        $params = [$this->supplierId];
        
        // Apply filters
        if (!empty($filters['status']) && is_array($filters['status'])) {
            $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
            $sql .= " AND fp.supplier_status IN ({$placeholders})";
            $params = array_merge($params, $filters['status']);
        } elseif (!empty($filters['status'])) {
            $sql .= " AND fp.supplier_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['outlet_id'])) {
            $sql .= " AND fp.outlet_id = ?";
            $params[] = $filters['outlet_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND fp.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND fp.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (vp.name LIKE ? OR vp.sku LIKE ? OR fp.serial_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY fp.id ORDER BY fp.created_at DESC";
        
        // Add pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get detailed warranty claim information
     * 
     * @param int $claimId Claim ID
     * @return array|null
     */
    public function getWarrantyClaimDetail(int $claimId): ?array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        // First verify this claim belongs to this supplier
        $checkSql = "SELECT fp.id 
                     FROM faulty_products fp
                     JOIN vend_products vp ON fp.product_id = vp.id
                     WHERE fp.id = ? AND vp.supplier_id = ?";
        
        $check = $this->db->query($checkSql, [$claimId, $this->supplierId]);
        if (empty($check)) {
            return null;
        }
        
        // Get claim details
        $claimSql = "SELECT 
                        fp.*,
                        vp.name AS product_name,
                        vp.sku,
                        vp.brand_id,
                        vb.name AS brand_name,
                        vo.name AS outlet_name,
                        vo.store_code AS outlet_code
                     FROM faulty_products fp
                     JOIN vend_products vp ON fp.product_id = vp.id
                     LEFT JOIN vend_brands vb ON vp.brand_id = vb.id
                     LEFT JOIN vend_outlets vo ON fp.outlet_id = vo.id
                     WHERE fp.id = ?";
        
        $claim = $this->db->query($claimSql, [$claimId]);
        if (empty($claim)) {
            return null;
        }
        
        $result = $claim[0];
        
        // Get notes
        $notesSql = "SELECT * FROM faulty_product_notes 
                    WHERE faulty_product_id = ? 
                    ORDER BY created_at ASC";
        
        $result['notes'] = $this->db->query($notesSql, [$claimId]);
        
        // Get media
        $mediaSql = "SELECT * FROM faulty_product_media_uploads 
                    WHERE faulty_product_id = ? 
                    ORDER BY uploaded_at ASC";
        
        $result['media'] = $this->db->query($mediaSql, [$claimId]);
        
        return $result;
    }
    
    /**
     * Get dashboard statistics
     * 
     * @return array
     */
    public function getDashboardStats(): array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        $stats = [];
        
        // Active POs
        $activePOSql = "SELECT COUNT(DISTINCT t.id) as count
                        FROM transfers t
                        JOIN transfer_items ti ON t.id = ti.transfer_id
                        JOIN vend_products vp ON ti.product_id = vp.id
                        WHERE t.transfer_category = 'PURCHASE_ORDER'
                        AND vp.supplier_id = ?
                        AND t.status IN ('OPEN','PACKING','SENT','RECEIVING')";
        
        $result = $this->db->query($activePOSql, [$this->supplierId]);
        $stats['active_pos'] = (int)($result[0]['count'] ?? 0);
        
        // Pending warranties
        $pendingWarrantySql = "SELECT COUNT(*) as count
                              FROM faulty_products fp
                              JOIN vend_products vp ON fp.product_id = vp.id
                              WHERE vp.supplier_id = ?
                              AND fp.supplier_status IN ('pending','investigating')";
        
        $result = $this->db->query($pendingWarrantySql, [$this->supplierId]);
        $stats['pending_warranties'] = (int)($result[0]['count'] ?? 0);
        
        // This month revenue
        $monthRevenueSql = "SELECT SUM(ti.qty_received * ti.cost) as revenue
                           FROM transfer_items ti
                           JOIN transfers t ON ti.transfer_id = t.id
                           JOIN vend_products vp ON ti.product_id = vp.id
                           WHERE t.transfer_category = 'PURCHASE_ORDER'
                           AND vp.supplier_id = ?
                           AND t.status = 'RECEIVED'
                           AND MONTH(t.received_at) = MONTH(NOW())
                           AND YEAR(t.received_at) = YEAR(NOW())";
        
        $result = $this->db->query($monthRevenueSql, [$this->supplierId]);
        $stats['month_revenue'] = (float)($result[0]['revenue'] ?? 0);
        
        // In-transit shipments
        $inTransitSql = "SELECT COUNT(DISTINCT ts.id) as count
                        FROM consignment_shipments ts
                        JOIN transfers t ON ts.transfer_id = t.id
                        JOIN transfer_items ti ON t.id = ti.transfer_id
                        JOIN vend_products vp ON ti.product_id = vp.id
                        WHERE vp.supplier_id = ?
                        AND ts.status = 'IN_TRANSIT'";
        
        $result = $this->db->query($inTransitSql, [$this->supplierId]);
        $stats['in_transit_shipments'] = (int)($result[0]['count'] ?? 0);
        
        // Stock alerts (low stock)
        $stockAlertsSql = "SELECT COUNT(DISTINCT vp.id) as count
                          FROM vend_products vp
                          JOIN v_supplier_outlet_inventory vsoi ON vp.id = vsoi.product_id
                          WHERE vp.supplier_id = ?
                          AND vsoi.stock_level < vsoi.reorder_point";
        
        $result = $this->db->query($stockAlertsSql, [$this->supplierId]);
        $stats['stock_alerts'] = (int)($result[0]['count'] ?? 0);
        
        return $stats;
    }
    
    /**
     * Get top 5 products by sales
     * 
     * @param int $days Number of days to look back
     * @return array
     */
    public function getTopProducts(int $days = 30): array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        $sql = "SELECT 
                    vp.id,
                    vp.name,
                    vp.sku,
                    SUM(ti.qty_received) as qty_sold,
                    SUM(ti.qty_received * ti.cost) as revenue
                FROM vend_products vp
                JOIN transfer_items ti ON vp.id = ti.product_id
                JOIN transfers t ON ti.transfer_id = t.id
                WHERE vp.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.status = 'RECEIVED'
                AND t.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY vp.id
                ORDER BY revenue DESC
                LIMIT 5";
        
        return $this->db->query($sql, [$this->supplierId, $days]);
    }
    
    /**
     * Get outlet list for supplier
     * 
     * @return array
     */
    public function getOutlets(): array {
        $sql = "SELECT id, name, store_code, address 
                FROM vend_outlets 
                WHERE is_active = 1 
                ORDER BY name";
        
        return $this->db->query($sql);
    }
    
    /**
     * Get supplier information
     * 
     * @return array|null
     */
    public function getSupplierInfo(): ?array {
        if (!$this->supplierId) {
            throw new InvalidArgumentException('Supplier ID not set');
        }
        
        $sql = "SELECT * FROM vend_suppliers WHERE id = ?";
        $result = $this->db->query($sql, [$this->supplierId]);
        
        return $result[0] ?? null;
    }
}