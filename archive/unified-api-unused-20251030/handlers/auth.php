<?php
/**
 * Authentication API Handler
 * 
 * Handles login, logout, session management
 * 
 * @package SupplierPortal\Handlers
 * @version 3.0.0
 */

declare(strict_types=1);

class Handler_Auth
{
    private ?string $supplierId;
    private Database $db;
    
    public function __construct(?string $supplierId)
    {
        $this->supplierId = $supplierId;
        $this->db = new Database();
    }
    
    /**
     * Handle login request
     * 
     * @param array $params Login credentials (email, password)
     * @return array Response data
     */
    public function login(array $params): array
    {
        // Validate required fields
        if (empty($params['email'])) {
            throw new Exception('Email is required', 400);
        }
        
        if (empty($params['password'])) {
            throw new Exception('Password is required', 400);
        }
        
        $email = trim($params['email']);
        $password = $params['password'];
        
        // Find supplier by email
        $sql = "
            SELECT id, name, email
            FROM vend_suppliers
            WHERE email = :email
              AND deleted_at IS NULL
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            throw new Exception('Invalid credentials', 401);
        }
        
        // For now, accept any password (will implement proper hashing later)
        // TODO: Implement password hashing and verification
        
        // Create session
        $sessionToken = $this->createSession($supplier['id']);
        
        // Log activity
        $this->logActivity($supplier['id'], 'login', 'User logged in');
        
        return [
            'data' => [
                'supplier' => [
                    'id' => $supplier['id'],
                    'name' => $supplier['name'],
                    'email' => $supplier['email']
                ],
                'session_token' => $sessionToken
            ],
            'message' => 'Login successful'
        ];
    }
    
    /**
     * Handle logout request
     * 
     * @param array $params Optional parameters
     * @return array Response data
     */
    public function logout(array $params = []): array
    {
        $auth = new Auth();
        $sessionToken = $auth->getSessionToken();
        
        if ($sessionToken) {
            // Delete session from database
            $sql = "
                DELETE FROM supplier_portal_sessions
                WHERE session_token = :token
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':token', $sessionToken, PDO::PARAM_STR);
            $stmt->execute();
            
            // Log activity
            if ($this->supplierId) {
                $this->logActivity($this->supplierId, 'logout', 'User logged out');
            }
        }
        
        // Destroy PHP session
        session_destroy();
        
        return [
            'data' => null,
            'message' => 'Logout successful'
        ];
    }
    
    /**
     * Get current session info
     * 
     * @param array $params Optional parameters
     * @return array Response data
     */
    public function getSession(array $params = []): array
    {
        if (!$this->supplierId) {
            throw new Exception('Not authenticated', 401);
        }
        
        // Get supplier details
        $sql = "
            SELECT id, name, email, phone, contact_name,
                   brand_logo_url, primary_color, secondary_color
            FROM vend_suppliers
            WHERE id = :supplier_id
              AND deleted_at IS NULL
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            throw new Exception('Supplier not found', 404);
        }
        
        return [
            'data' => [
                'supplier' => $supplier,
                'authenticated' => true
            ],
            'message' => 'Session retrieved successfully'
        ];
    }
    
    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================
    
    private function createSession(string $supplierId): string
    {
        // Generate secure session token
        $sessionToken = bin2hex(random_bytes(32));
        
        // Calculate expiry (24 hours from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert session into database
        $sql = "
            INSERT INTO supplier_portal_sessions 
            (supplier_id, session_token, expires_at, created_at)
            VALUES (:supplier_id, :token, :expires_at, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':token', $sessionToken, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
        $stmt->execute();
        
        // Store in PHP session
        session_start();
        $_SESSION['supplier_id'] = $supplierId;
        $_SESSION['session_token'] = $sessionToken;
        
        return $sessionToken;
    }
    
    private function logActivity(string $supplierId, string $action, string $description): void
    {
        $sql = "
            INSERT INTO supplier_activity_log
            (supplier_id, action, description, ip_address, user_agent, created_at)
            VALUES (:supplier_id, :action, :description, :ip, :user_agent, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->execute();
    }
}
