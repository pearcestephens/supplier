<?php
/**
 * Warranty Claims Handler
 * 
 * Handles all warranty-related API operations:
 * - Get claims list (paginated, filtered)
 * - Get claim details
 * - Add supplier notes
 * - Accept/decline claims
 * 
 * @package SupplierPortal\API\Handlers
 * @version 4.0.0 - Unified Architecture
 */

declare(strict_types=1);

class Handler_Warranty {
    private PDO $pdo;
    private string $supplierID;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param string $supplierID Authenticated supplier UUID
     */
    public function __construct(PDO $pdo, string $supplierID) {
        $this->pdo = $pdo;
        $this->supplierID = $supplierID;
    }
    
    /**
     * Handle incoming requests
     * 
     * @param string $method Method name (addNote, processAction, etc.)
     * @param array $params Request parameters
     * @return array Response data
     * @throws Exception If method not found or validation fails
     */
    public function handle(string $method, array $params): array {
        if (!method_exists($this, $method)) {
            throw new Exception("Method not found: {$method}", 404);
        }
        
        return $this->$method($params);
    }
    
    /**
     * Get paginated list of warranty claims
     * 
     * @param array $params {
     *     @type int $page Page number (default: 1)
     *     @type int $per_page Items per page (default: 25)
     *     @type string $status Filter by status (pending, accepted, declined, all)
     *     @type string $search Search term
     * }
     * @return array {
     *     @type array $claims List of claims
     *     @type int $total Total count
     *     @type int $page Current page
     *     @type int $pages Total pages
     * }
     */
    private function getList(array $params): array {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min(100, (int)($params['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        
        $status = $params['status'] ?? 'all';
        $search = trim($params['search'] ?? '');
        
        // Build WHERE conditions
        $where = ["p.supplier_id = :supplier_id"];
        $bindings = [':supplier_id' => $this->supplierID];
        
        // Status filter
        if ($status !== 'all') {
            switch ($status) {
                case 'pending':
                    $where[] = "fp.supplier_status = 0";
                    break;
                case 'accepted':
                    $where[] = "fp.supplier_status = 1";
                    break;
                case 'declined':
                    $where[] = "fp.supplier_status = 2";
                    break;
            }
        }
        
        // Search filter
        if (!empty($search)) {
            $where[] = "(p.name LIKE :search OR p.sku LIKE :search OR fp.id LIKE :search)";
            $bindings[':search'] = '%' . $search . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSQL = "
            SELECT COUNT(fp.id) as total
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE {$whereClause}
        ";
        
        $stmt = $this->pdo->prepare($countSQL);
        $stmt->execute($bindings);
        $total = (int)$stmt->fetchColumn();
        
        // Get claims
        $claimsSQL = "
            SELECT 
                fp.id,
                fp.product_id,
                p.name as product_name,
                p.sku,
                fp.fault_description,
                fp.customer_name,
                fp.outlet_id,
                o.name as outlet_name,
                fp.time_created as created_at,
                fp.supplier_status,
                fp.supplier_update_status,
                fp.status,
                CASE 
                    WHEN fp.supplier_status = 0 THEN 'Pending'
                    WHEN fp.supplier_status = 1 THEN 'Accepted'
                    WHEN fp.supplier_status = 2 THEN 'Declined'
                    ELSE 'Unknown'
                END as supplier_status_label,
                (SELECT COUNT(*) FROM faulty_product_notes 
                 WHERE faulty_product_id = fp.id) as notes_count,
                (SELECT COUNT(*) FROM faulty_product_media 
                 WHERE faulty_product_id = fp.id) as media_count
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            LEFT JOIN vend_outlets o ON fp.outlet_id = o.id
            WHERE {$whereClause}
            ORDER BY fp.time_created DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($claimsSQL);
        
        // Bind all parameters
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate pages
        $pages = (int)ceil($total / $perPage);
        
        return [
            'claims' => $claims,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage
        ];
    }
    
    /**
     * Get single claim details with notes and media
     * 
     * @param array $params {
     *     @type int $fault_id Claim ID
     * }
     * @return array Claim details
     * @throws Exception If claim not found or access denied
     */
    private function getDetail(array $params): array {
        $faultID = (int)($params['fault_id'] ?? 0);
        
        if ($faultID <= 0) {
            throw new Exception('Invalid fault_id', 400);
        }
        
        // Get claim details
        $claimSQL = "
            SELECT 
                fp.*,
                p.name as product_name,
                p.sku,
                p.supplier_id,
                o.name as outlet_name,
                o.outlet_code
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            LEFT JOIN vend_outlets o ON fp.outlet_id = o.id
            WHERE fp.id = :fault_id
              AND p.supplier_id = :supplier_id
        ";
        
        $stmt = $this->pdo->prepare($claimSQL);
        $stmt->execute([
            ':fault_id' => $faultID,
            ':supplier_id' => $this->supplierID
        ]);
        
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$claim) {
            throw new Exception('Warranty claim not found or access denied', 404);
        }
        
        // Get notes
        $notesSQL = "
            SELECT 
                fpn.*,
                CASE 
                    WHEN fpn.supplier_id IS NOT NULL THEN 'supplier'
                    ELSE 'staff'
                END as author_type
            FROM faulty_product_notes fpn
            WHERE fpn.faulty_product_id = :fault_id
            ORDER BY fpn.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($notesSQL);
        $stmt->execute([':fault_id' => $faultID]);
        $claim['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get media
        $mediaSQL = "
            SELECT *
            FROM faulty_product_media
            WHERE faulty_product_id = :fault_id
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($mediaSQL);
        $stmt->execute([':fault_id' => $faultID]);
        $claim['media'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $claim;
    }
    
    /**
     * Add supplier note to warranty claim
     * 
     * @param array $params {
     *     @type int $fault_id Claim ID
     *     @type string $note Note text (required)
     *     @type string $action Optional action type
     *     @type string $internal_ref Optional internal reference
     * }
     * @return array Success response with note preview
     * @throws Exception If validation fails
     */
    private function addNote(array $params): array {
        $faultID = (int)($params['fault_id'] ?? 0);
        $note = trim($params['note'] ?? '');
        $action = trim($params['action'] ?? '');
        $internalRef = trim($params['internal_ref'] ?? '');
        
        // Validation
        if ($faultID <= 0) {
            throw new Exception('Invalid fault_id', 400);
        }
        
        if (empty($note)) {
            throw new Exception('Note cannot be empty', 400);
        }
        
        // Verify supplier owns this claim
        $verifySQL = "
            SELECT fp.id 
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE fp.id = :fault_id 
              AND p.supplier_id = :supplier_id
        ";
        
        $stmt = $this->pdo->prepare($verifySQL);
        $stmt->execute([
            ':fault_id' => $faultID,
            ':supplier_id' => $this->supplierID
        ]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Warranty claim not found or access denied', 404);
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Insert note
            $insertSQL = "
                INSERT INTO faulty_product_notes 
                (faulty_product_id, supplier_id, note, action, internal_ref, created_at)
                VALUES (:fault_id, :supplier_id, :note, :action, :internal_ref, NOW())
            ";
            
            $stmt = $this->pdo->prepare($insertSQL);
            $stmt->execute([
                ':fault_id' => $faultID,
                ':supplier_id' => $this->supplierID,
                ':note' => $note,
                ':action' => $action ?: null,
                ':internal_ref' => $internalRef ?: null
            ]);
            
            // Mark claim as updated by supplier
            $updateSQL = "
                UPDATE faulty_products 
                SET supplier_update_status = 1
                WHERE id = :fault_id
            ";
            
            $stmt = $this->pdo->prepare($updateSQL);
            $stmt->execute([':fault_id' => $faultID]);
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            logMessage("Supplier added note to warranty claim #{$faultID}", 'INFO', [
                'fault_id' => $faultID,
                'supplier_id' => $this->supplierID,
                'note_length' => strlen($note)
            ]);
            
            return [
                'success' => true,
                'message' => 'Note added successfully',
                'fault_id' => $faultID,
                'note_preview' => substr($note, 0, 50) . (strlen($note) > 50 ? '...' : '')
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to add note: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Accept or decline warranty claim
     * 
     * @param array $params {
     *     @type string $action 'accept' or 'decline' (required)
     *     @type int $fault_id Claim ID (required)
     *     @type string $resolution Resolution notes (required if accepting)
     *     @type string $reason Decline reason (required if declining)
     * }
     * @return array Success response
     * @throws Exception If validation fails or claim already processed
     */
    private function processAction(array $params): array {
        $action = $params['action'] ?? '';
        $faultID = (int)($params['fault_id'] ?? 0);
        $resolution = trim($params['resolution'] ?? '');
        $reason = trim($params['reason'] ?? '');
        
        // Validation
        if (!in_array($action, ['accept', 'decline'])) {
            throw new Exception('Invalid action. Must be "accept" or "decline"', 400);
        }
        
        if ($faultID <= 0) {
            throw new Exception('Invalid fault_id', 400);
        }
        
        if ($action === 'accept' && empty($resolution)) {
            throw new Exception('Resolution notes are required when accepting a claim', 400);
        }
        
        if ($action === 'decline' && empty($reason)) {
            throw new Exception('Decline reason is required', 400);
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Verify claim ownership and status
            $verifySQL = "
                SELECT fp.id, fp.supplier_status, p.name as product_name
                FROM faulty_products fp
                INNER JOIN vend_products p ON fp.product_id = p.id
                WHERE fp.id = :fault_id
                  AND p.supplier_id = :supplier_id
            ";
            
            $stmt = $this->pdo->prepare($verifySQL);
            $stmt->execute([
                ':fault_id' => $faultID,
                ':supplier_id' => $this->supplierID
            ]);
            
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$claim) {
                throw new Exception('Warranty claim not found or access denied', 404);
            }
            
            // Check if already processed
            if ($claim['supplier_status'] != 0) {
                $statusLabel = ['0' => 'Pending', '1' => 'Accepted', '2' => 'Declined'];
                throw new Exception(
                    'Claim already processed. Current status: ' . $statusLabel[$claim['supplier_status']],
                    400
                );
            }
            
            // Update claim status
            $newStatus = ($action === 'accept') ? 1 : 2;
            $noteText = ($action === 'accept') ? $resolution : $reason;
            
            $updateSQL = "
                UPDATE faulty_products 
                SET 
                    supplier_status = :status,
                    supplier_update_status = 1,
                    supplier_response_date = NOW()
                WHERE id = :fault_id
            ";
            
            $stmt = $this->pdo->prepare($updateSQL);
            $stmt->execute([
                ':status' => $newStatus,
                ':fault_id' => $faultID
            ]);
            
            // Add note documenting the action
            $insertNoteSQL = "
                INSERT INTO faulty_product_notes 
                (faulty_product_id, supplier_id, note, action, created_at)
                VALUES (:fault_id, :supplier_id, :note, :action_type, NOW())
            ";
            
            $stmt = $this->pdo->prepare($insertNoteSQL);
            $stmt->execute([
                ':fault_id' => $faultID,
                ':supplier_id' => $this->supplierID,
                ':note' => $noteText,
                ':action_type' => $action
            ]);
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            logMessage("Supplier {$action}ed warranty claim #{$faultID}", 'INFO', [
                'fault_id' => $faultID,
                'supplier_id' => $this->supplierID,
                'action' => $action,
                'product_name' => $claim['product_name']
            ]);
            
            $actionLabel = ucfirst($action);
            
            return [
                'success' => true,
                'message' => "Warranty claim {$action}ed successfully",
                'fault_id' => $faultID,
                'action' => $action,
                'new_status' => $newStatus,
                'new_status_label' => $actionLabel . 'ed'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Re-throw with original message if it's our validation exception
            if (in_array($e->getCode(), [400, 404])) {
                throw $e;
            }
            
            throw new Exception('Failed to process action: ' . $e->getMessage(), 500);
        }
    }
}
