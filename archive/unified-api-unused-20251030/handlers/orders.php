<?php
/**
 * Orders API Handler
 * 
 * Handles purchase order operations:
 * - getPending() - Pending orders needing action
 * - getOrders() - List with filters, search, pagination
 * - getOrderDetail() - Single PO with line items
 * - updateTracking() - Add tracking number
 * - bulkExport() - CSV/PDF export
 * 
 * @package SupplierPortal\API\Handlers
 * @version 3.0.0
 */

declare(strict_types=1);

class Handler_Orders
{
    private PDO $db;
    private string $supplierId;
    
    public function __construct(PDO $db, string $supplierId)
    {
        $this->db = $db;
        $this->supplierId = $supplierId;
    }
    
    /**
     * Get pending orders requiring action
     */
    public function getPending(array $params = []): array
    {
        $limit = $params['limit'] ?? 10;
        
        $sql = "
            SELECT 
                c.id,
                c.public_id as po_number,
                c.status,
                o.name as outlet_name,
                c.created_at,
                c.due_date,
                c.total_price as total_value,
                COUNT(DISTINCT li.id) as items_count,
                SUM(li.quantity) as units_count
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_id = o.id
            LEFT JOIN purchase_order_line_items li ON c.id = li.consignment_id
            WHERE c.supplier_id = ?
            AND c.transfer_category = 'PURCHASE_ORDER'
            AND c.status IN ('OPEN', 'SENT', 'PROCESSING')
            AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY 
                CASE 
                    WHEN DATEDIFF(c.due_date, NOW()) <= 2 THEN 0
                    ELSE 1
                END,
                c.due_date ASC,
                c.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->supplierId, $limit]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate urgency
        foreach ($orders as &$order) {
            $dueDays = ceil((strtotime($order['due_date']) - time()) / (60 * 60 * 24));
            $order['status'] = $dueDays <= PO_URGENT_THRESHOLD_DAYS ? 'URGENT' : $order['status'];
            $order['units_count'] = (int)$order['units_count'];
            $order['items_count'] = (int)$order['items_count'];
            $order['total_value'] = (float)$order['total_value'];
        }
        
        return $orders;
    }
    
    /**
     * Get orders list with filters
     */
    public function getOrders(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? PAGINATION_PER_PAGE;
        $status = $params['status'] ?? null;
        $search = $params['search'] ?? null;
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        
        // Build WHERE conditions
        $where = ['c.supplier_id = ?', 'c.transfer_category = ?', 'c.deleted_at IS NULL'];
        $bindings = [$this->supplierId, 'PURCHASE_ORDER'];
        
        if ($status) {
            $where[] = 'c.status = ?';
            $bindings[] = $status;
        }
        
        if ($search) {
            $where[] = '(c.public_id LIKE ? OR o.name LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        if ($dateFrom) {
            $where[] = 'DATE(c.created_at) >= ?';
            $bindings[] = $dateFrom;
        }
        
        if ($dateTo) {
            $where[] = 'DATE(c.created_at) <= ?';
            $bindings[] = $dateTo;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Count total
        $countSql = "
            SELECT COUNT(DISTINCT c.id) as total
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_id = o.id
            WHERE {$whereClause}
        ";
        
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int)$stmt->fetchColumn();
        
        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $bindings[] = $perPage;
        $bindings[] = $offset;
        
        $sql = "
            SELECT 
                c.id,
                c.public_id as po_number,
                c.status,
                o.name as outlet_name,
                c.created_at,
                c.due_date,
                c.total_price as total_value,
                c.tracking_number,
                COUNT(DISTINCT li.id) as items_count,
                SUM(li.quantity) as units_count
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_id = o.id
            LEFT JOIN purchase_order_line_items li ON c.id = li.consignment_id
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => ceil($total / $perPage)
            ]
        ];
    }
    
    /**
     * Get single order detail
     */
    public function getOrderDetail(array $params = []): array
    {
        $orderId = $params['id'] ?? null;
        
        if (!$orderId) {
            throw new Exception('Order ID required');
        }
        
        // Get order header
        $sql = "
            SELECT 
                c.*,
                o.name as outlet_name,
                o.physical_address as outlet_address,
                o.email as outlet_email,
                o.phone as outlet_phone
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_id = o.id
            WHERE c.id = ?
            AND c.supplier_id = ?
            AND c.transfer_category = 'PURCHASE_ORDER'
            AND c.deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId, $this->supplierId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Get line items
        $sql = "
            SELECT 
                li.*,
                p.name as product_name,
                p.sku,
                p.supplier_code,
                i.inventory_level as current_stock
            FROM purchase_order_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            LEFT JOIN vend_inventory i ON p.id = i.product_id AND i.outlet_id = ?
            WHERE li.consignment_id = ?
            ORDER BY li.id ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$order['outlet_id'], $orderId]);
        $order['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $order;
    }
    
    /**
     * Update tracking number
     */
    public function updateTracking(array $params = []): array
    {
        $orderId = $params['id'] ?? null;
        $trackingNumber = $params['tracking_number'] ?? null;
        
        if (!$orderId || !$trackingNumber) {
            throw new Exception('Order ID and tracking number required');
        }
        
        $sql = "
            UPDATE vend_consignments
            SET tracking_number = ?,
                status = 'SHIPPED',
                updated_at = NOW()
            WHERE id = ?
            AND supplier_id = ?
            AND transfer_category = 'PURCHASE_ORDER'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$trackingNumber, $orderId, $this->supplierId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Order not found or already updated');
        }
        
        // Log activity
        $this->logActivity('update_tracking', $orderId, [
            'tracking_number' => $trackingNumber
        ]);
        
        return [
            'success' => true,
            'message' => 'Tracking number updated successfully'
        ];
    }
    
    /**
     * Bulk export orders
     */
    public function bulkExport(array $params = []): array
    {
        $format = $params['format'] ?? 'csv';
        $orderIds = $params['order_ids'] ?? [];
        
        if (empty($orderIds)) {
            throw new Exception('No orders selected for export');
        }
        
        // Get orders
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT 
                c.public_id as po_number,
                c.status,
                o.name as outlet,
                c.created_at as order_date,
                c.due_date,
                c.total_price as total,
                c.tracking_number,
                COUNT(DISTINCT li.id) as items,
                SUM(li.quantity) as units
            FROM vend_consignments c
            LEFT JOIN vend_outlets o ON c.outlet_id = o.id
            LEFT JOIN purchase_order_line_items li ON c.id = li.consignment_id
            WHERE c.id IN ($placeholders)
            AND c.supplier_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ";
        
        $bindings = array_merge($orderIds, [$this->supplierId]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate file
        $filename = 'orders_export_' . date('Y-m-d_His') . '.' . $format;
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        // Ensure directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        if ($format === 'csv') {
            $this->generateCSV($orders, $filepath);
        } else {
            throw new Exception('Unsupported export format');
        }
        
        return [
            'filename' => $filename,
            'url' => UPLOAD_URL . 'exports/' . $filename,
            'size' => filesize($filepath),
            'count' => count($orders)
        ];
    }
    
    /**
     * Generate CSV file
     */
    private function generateCSV(array $data, string $filepath): void
    {
        $fp = fopen($filepath, 'w');
        
        if (!$fp) {
            throw new Exception('Failed to create export file');
        }
        
        // Write header
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
        }
        
        // Write rows
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
    }
    
    /**
     * Add supplier note to order
     * 
     * @param array $params {
     *     @type int $order_id Order ID
     *     @type string $note Note text
     * }
     * @return array Success response
     * @throws Exception If validation fails
     */
    public function addNote(array $params): array
    {
        $orderId = (int)($params['order_id'] ?? 0);
        $note = trim($params['note'] ?? '');
        
        // Validation
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        if (empty($note)) {
            throw new Exception('Note cannot be empty', 400);
        }
        
        // Verify order belongs to this supplier
        $verifySQL = "
            SELECT id, public_id, notes 
            FROM vend_consignments 
            WHERE id = ? 
              AND supplier_id = ? 
              AND transfer_category = 'PURCHASE_ORDER'
              AND deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($verifySQL);
        $stmt->execute([$orderId, $this->supplierId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or access denied', 404);
        }
        
        // Append new note to existing notes
        $existingNotes = $order['notes'] ?? '';
        $timestamp = date('Y-m-d H:i:s');
        $supplierName = 'Supplier'; // TODO: Get from Auth or database
        $newNote = "\n\n[{$timestamp}] {$supplierName}:\n{$note}";
        $updatedNotes = $existingNotes . $newNote;
        
        // Update notes
        $updateSQL = "
            UPDATE vend_consignments 
            SET notes = ?
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($updateSQL);
        $stmt->execute([$updatedNotes, $orderId]);
        
        // Log activity
        $this->logActivity('add_note', $orderId, ['note_preview' => substr($note, 0, 50)]);
        
        logMessage("Supplier added note to order #{$orderId}", 'INFO', [
            'order_id' => $orderId,
            'supplier_id' => $this->supplierId,
            'note_length' => strlen($note)
        ]);
        
        return [
            'success' => true,
            'message' => 'Note added successfully',
            'order_id' => $orderId,
            'public_id' => $order['public_id']
        ];
    }
    
    /**
     * Update order status
     * 
     * @param array $params {
     *     @type int $order_id Order ID
     *     @type string $new_status New status (SENT, CANCELLED)
     * }
     * @return array Success response
     * @throws Exception If validation fails or invalid state transition
     */
    public function updateStatus(array $params): array
    {
        $orderId = (int)($params['order_id'] ?? 0);
        $newStatus = strtoupper($params['new_status'] ?? '');
        
        // Validation
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $allowedStatuses = ['SENT', 'CANCELLED'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception('Invalid status. Must be SENT or CANCELLED', 400);
        }
        
        // Verify order ownership and current state
        $verifySQL = "
            SELECT id, public_id, state 
            FROM vend_consignments 
            WHERE id = ? 
              AND supplier_id = ? 
              AND transfer_category = 'PURCHASE_ORDER'
              AND deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($verifySQL);
        $stmt->execute([$orderId, $this->supplierId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or access denied', 404);
        }
        
        // Check if status change is valid
        if ($order['state'] !== 'OPEN' && $newStatus !== 'CANCELLED') {
            throw new Exception('Cannot update status from current state: ' . $order['state'], 400);
        }
        
        // Update status
        $updateSQL = "
            UPDATE vend_consignments 
            SET state = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($updateSQL);
        $stmt->execute([$newStatus, $orderId]);
        
        // Log activity
        $this->logActivity('update_status', $orderId, [
            'old_status' => $order['state'],
            'new_status' => $newStatus
        ]);
        
        logMessage("Supplier updated order #{$orderId} status to {$newStatus}", 'INFO', [
            'order_id' => $orderId,
            'supplier_id' => $this->supplierId,
            'old_status' => $order['state'],
            'new_status' => $newStatus
        ]);
        
        return [
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'public_id' => $order['public_id'],
            'old_status' => $order['state'],
            'new_status' => $newStatus
        ];
    }
    
    /**
     * Update tracking information
     * 
     * @param array $params {
     *     @type int $order_id Order ID
     *     @type string $tracking_number Tracking number
     *     @type string $carrier Carrier name
     * }
     * @return array Success response
     * @throws Exception If validation fails
     */
    public function updateTracking(array $params): array
    {
        $orderId = (int)($params['order_id'] ?? 0);
        $trackingNumber = trim($params['tracking_number'] ?? '');
        $carrier = trim($params['carrier'] ?? '');
        
        // Validation
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        if (empty($trackingNumber)) {
            throw new Exception('Tracking number is required', 400);
        }
        
        if (empty($carrier)) {
            throw new Exception('Carrier is required', 400);
        }
        
        // Verify order belongs to this supplier
        $verifySQL = "
            SELECT id, public_id, state
            FROM vend_consignments 
            WHERE id = ? 
              AND supplier_id = ? 
              AND transfer_category = 'PURCHASE_ORDER'
              AND deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($verifySQL);
        $stmt->execute([$orderId, $this->supplierId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or access denied', 404);
        }
        
        // Update tracking information
        // Auto-transition OPEN → SENT when tracking is added
        $updateSQL = "
            UPDATE vend_consignments 
            SET 
                tracking_number = ?,
                tracking_carrier = ?,
                tracking_updated_at = NOW(),
                state = CASE 
                    WHEN state = 'OPEN' THEN 'SENT'
                    ELSE state
                END
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($updateSQL);
        $stmt->execute([$trackingNumber, $carrier, $orderId]);
        
        // Determine if state changed
        $stateChanged = ($order['state'] === 'OPEN');
        
        // Log activity
        $this->logActivity('update_tracking', $orderId, [
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'state_changed' => $stateChanged
        ]);
        
        logMessage("Supplier updated tracking for order #{$orderId}", 'INFO', [
            'order_id' => $orderId,
            'supplier_id' => $this->supplierId,
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier
        ]);
        
        return [
            'success' => true,
            'message' => 'Tracking information updated successfully',
            'order_id' => $orderId,
            'public_id' => $order['public_id'],
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'state_changed' => $stateChanged,
            'new_state' => $stateChanged ? 'SENT' : $order['state']
        ];
    }
    
    /**
     * Request additional information from Vape Shed staff
     * 
     * Creates a ticket in supplier_info_requests table for staff to respond to
     * 
     * @param array $params {
     *     @type int $order_id Order ID
     *     @type string $message Request message
     * }
     * @return array Success response with request ID
     * @throws Exception If validation fails
     */
    public function requestInfo(array $params): array
    {
        $orderId = (int)($params['order_id'] ?? 0);
        $message = trim($params['message'] ?? '');
        
        // Validation
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        if (empty($message)) {
            throw new Exception('Message cannot be empty', 400);
        }
        
        if (strlen($message) > 1000) {
            throw new Exception('Message too long (max 1000 characters)', 400);
        }
        
        // Verify order belongs to this supplier
        $verifySQL = "
            SELECT id, public_id 
            FROM vend_consignments 
            WHERE id = ? 
              AND supplier_id = ? 
              AND transfer_category = 'PURCHASE_ORDER'
              AND deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($verifySQL);
        $stmt->execute([$orderId, $this->supplierId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or access denied', 404);
        }
        
        // Create information request ticket
        $insertSQL = "
            INSERT INTO supplier_info_requests 
            (supplier_id, order_id, request_message, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ";
        
        $stmt = $this->db->prepare($insertSQL);
        $stmt->execute([$this->supplierId, $orderId, $message]);
        $requestId = (int)$this->db->lastInsertId();
        
        // Send notification email to staff (optional - implement if needed)
        // $this->notifyStaffOfInfoRequest($requestId, $order['public_id'], $message);
        
        // Log activity
        $this->logActivity('request_info', $orderId, [
            'request_id' => $requestId,
            'message_preview' => substr($message, 0, 50)
        ]);
        
        logMessage("Supplier requested info for order #{$orderId}", 'INFO', [
            'order_id' => $orderId,
            'supplier_id' => $this->supplierId,
            'request_id' => $requestId,
            'message_length' => strlen($message)
        ]);
        
        return [
            'success' => true,
            'message' => 'Information request submitted successfully',
            'order_id' => $orderId,
            'public_id' => $order['public_id'],
            'request_id' => $requestId,
            'status' => 'pending'
        ];
    }
    
    /**
     * Log supplier activity
     */
    private function logActivity(string $action, int $orderId, array $meta = []): void
    {
        $sql = "
            INSERT INTO supplier_activity_log 
            (supplier_id, action, resource_type, resource_id, meta, created_at)
            VALUES (?, ?, 'order', ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->supplierId,
            $action,
            $orderId,
            json_encode($meta)
        ]);
    }
}
