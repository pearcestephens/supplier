<?php
/**
 * Dashboard API Handler
 * 
 * Handles all dashboard-related API requests
 * 
 * @package SupplierPortal\Handlers
 * @version 3.0.0
 */

declare(strict_types=1);

class Handler_Dashboard
{
    private PDO $db;
    private string $supplierId;
    
    public function __construct(PDO $db, string $supplierId)
    {
        $this->db = $db;
        $this->supplierId = $supplierId;
    }
    
    /**
     * Get dashboard statistics
     * 
     * @param array $params Optional parameters (date_range, etc.)
     * @return array Response data
     */
    public function getStats(array $params = []): array
    {
        $dateRange = $params['date_range'] ?? 30; // days
        $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));
        
        // Get total orders count
        $totalOrders = $this->getTotalOrders($startDate);
        
        // Get pending orders count
        $pendingOrders = $this->getPendingOrders();
        
        // Get revenue (30 days)
        $revenue = $this->getRevenue($startDate);
        
        // Get active products count
        $activeProducts = $this->getActiveProducts();
        
        // Get warranty claims count
        $warrantyClaims = $this->getWarrantyClaims();
        
        // Get stock health percentage
        $stockHealth = $this->getStockHealth();
        
        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'revenue' => $revenue,
            'active_products' => $activeProducts,
            'warranty_claims' => $warrantyClaims,
            'stock_health' => $stockHealth
        ];
    }
    
    /**
     * Get chart data for dashboard
     * 
     * @param array $params Chart parameters (date_range)
     * @return array Response data with all charts
     */
    public function getChartData(array $params = []): array
    {
        $dateRange = $params['date_range'] ?? 30;
        
        return [
            'revenue' => $this->getRevenueChartData($dateRange),
            'orders' => $this->getOrdersChartData($dateRange),
            'products' => $this->getProductsChartData()
        ];
    }
    
    /**
     * Get recent activity for sidebar
     * 
     * @param array $params Optional parameters (limit)
     * @return array Response data
     */
    public function getRecentActivity(array $params = []): array
    {
        $limit = $params['limit'] ?? 10;
        
        $sql = "
            SELECT 
                'order' as activity_type,
                c.id as reference_id,
                CONCAT('Purchase Order #', SUBSTRING(c.id, -6)) as title,
                c.status,
                c.created_at as timestamp
            FROM vend_consignments c
            WHERE c.supplier_id = :supplier_id
              AND c.transfer_category = 'PURCHASE_ORDER'
            ORDER BY c.created_at DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format for display - return array directly with expected field names
        return array_map(function($activity) {
            return [
                'text' => $this->formatActivityTitle($activity),
                'date' => $this->formatTimeAgo($activity['timestamp']),
                'color' => $this->getActivityColor($activity['status']),
                'type' => $activity['activity_type'],
                'status' => $activity['status']
            ];
        }, $activities);
    }
    
    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================
    
    private function getTotalOrders(string $startDate): array
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) >= :start_date
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentCount = (int)$result['count'];
        
        // Get previous period for comparison
        $previousPeriodStart = date('Y-m-d', strtotime($startDate . ' -30 days'));
        $previousPeriodEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        
        $sqlPrev = "
            SELECT COUNT(*) as count
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) BETWEEN :start_date AND :end_date
        ";
        
        $stmtPrev = $this->db->prepare($sqlPrev);
        $stmtPrev->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmtPrev->bindValue(':start_date', $previousPeriodStart, PDO::PARAM_STR);
        $stmtPrev->bindValue(':end_date', $previousPeriodEnd, PDO::PARAM_STR);
        $stmtPrev->execute();
        
        $resultPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
        $previousCount = (int)$resultPrev['count'];
        
        // Calculate percentage change
        $percentageChange = $previousCount > 0 
            ? round((($currentCount - $previousCount) / $previousCount) * 100, 1)
            : 0;
        
        return [
            'value' => $currentCount,
            'change' => $percentageChange,
            'trend' => $percentageChange >= 0 ? 'up' : 'down'
        ];
    }
    
    private function getPendingOrders(): int
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND status IN ('OPEN', 'SENT')
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    }
    
    private function getRevenue(string $startDate): array
    {
        $sql = "
            SELECT COALESCE(SUM(total_cost), 0) as revenue
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) >= :start_date
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentRevenue = (float)$result['revenue'];
        
        // Get previous period
        $previousPeriodStart = date('Y-m-d', strtotime($startDate . ' -30 days'));
        $previousPeriodEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        
        $sqlPrev = "
            SELECT COALESCE(SUM(total_cost), 0) as revenue
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) BETWEEN :start_date AND :end_date
        ";
        
        $stmtPrev = $this->db->prepare($sqlPrev);
        $stmtPrev->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmtPrev->bindValue(':start_date', $previousPeriodStart, PDO::PARAM_STR);
        $stmtPrev->bindValue(':end_date', $previousPeriodEnd, PDO::PARAM_STR);
        $stmtPrev->execute();
        
        $resultPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
        $previousRevenue = (float)$resultPrev['revenue'];
        
        $percentageChange = $previousRevenue > 0 
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0;
        
        return [
            'value' => $currentRevenue,
            'formatted' => '$' . number_format($currentRevenue, 2),
            'change' => $percentageChange,
            'trend' => $percentageChange >= 0 ? 'up' : 'down'
        ];
    }
    
    private function getActiveProducts(): array
    {
        $sql = "
            SELECT COUNT(DISTINCT p.id) as count
            FROM vend_products p
            WHERE p.supplier_id = :supplier_id
              AND p.deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'value' => (int)$result['count'],
            'change' => 0,
            'trend' => 'neutral'
        ];
    }
    
    private function getWarrantyClaims(): array
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = :supplier_id
              AND fp.supplier_status = 0
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'value' => (int)$result['count'],
            'change' => 0,
            'trend' => 'neutral'
        ];
    }
    
    private function getStockHealth(): float
    {
        // Stock health = percentage of products with adequate stock
        $sql = "
            SELECT 
                COUNT(DISTINCT p.id) as total_products,
                COUNT(DISTINCT CASE 
                    WHEN i.inventory_level >= 10 THEN p.id 
                END) as healthy_products
            FROM vend_products p
            LEFT JOIN vend_inventory i ON p.id = i.product_id
            WHERE p.supplier_id = :supplier_id
              AND p.deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalProducts = (int)$result['total_products'];
        $healthyProducts = (int)$result['healthy_products'];
        
        $percentage = $totalProducts > 0 
            ? round(($healthyProducts / $totalProducts) * 100, 1)
            : 0;
        
        return $percentage;
    }
    
    private function getRevenueChartData(int $days): array
    {
        $sql = "
            SELECT 
                DATE(created_at) as date,
                COALESCE(SUM(total_cost), 0) as revenue
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map of date => revenue
        $dataMap = [];
        foreach ($results as $row) {
            $dataMap[$row['date']] = (float)$row['revenue'];
        }
        
        // Fill in all dates for the last N days (even if no data)
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            $values[] = $dataMap[$date] ?? 0;
        }
        
        // Format for Chart.js
        return [
            'labels' => $labels,
            'values' => $values,
            'datasets' => [[
                'label' => 'Revenue',
                'data' => $values,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'tension' => 0.4
            ]]
        ];
    }
    
    private function getOrdersChartData(int $days): array
    {
        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders
            FROM vend_consignments
            WHERE supplier_id = :supplier_id
              AND transfer_category = 'PURCHASE_ORDER'
              AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map of date => orders
        $dataMap = [];
        foreach ($results as $row) {
            $dataMap[$row['date']] = (int)$row['orders'];
        }
        
        // Fill in all dates for the last N days (even if no data)
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            $values[] = $dataMap[$date] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'values' => $values,
            'datasets' => [[
                'label' => 'Orders',
                'data' => $values,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'tension' => 0.4
            ]]
        ];
    }
    
    private function getProductsChartData(): array
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN i.inventory_level >= 50 THEN 'High Stock'
                    WHEN i.inventory_level >= 10 THEN 'Medium Stock'
                    WHEN i.inventory_level > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END as stock_level,
                COUNT(DISTINCT p.id) as count
            FROM vend_products p
            LEFT JOIN vend_inventory i ON p.id = i.product_id
            WHERE p.supplier_id = :supplier_id
              AND p.deleted_at IS NULL
            GROUP BY stock_level
            ORDER BY FIELD(stock_level, 'High Stock', 'Medium Stock', 'Low Stock', 'Out of Stock')
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':supplier_id', $this->supplierId, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = array_column($results, 'stock_level');
        $values = array_column($results, 'count');
        
        return [
            'labels' => $labels,
            'values' => $values,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => [
                    '#10b981', // High Stock - Green
                    '#3b82f6', // Medium Stock - Blue
                    '#f59e0b', // Low Stock - Orange
                    '#ef4444'  // Out of Stock - Red
                ]
            ]]
        ];
    }
    
    private function formatActivityTitle(array $activity): string
    {
        switch ($activity['activity_type']) {
            case 'order':
                return 'New Order #' . substr((string)$activity['reference_id'], -6);
            default:
                return $activity['title'];
        }
    }
    
    private function formatTimeAgo(string $timestamp): string
    {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . 'min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h ago';
        } else {
            $days = floor($diff / 86400);
            return $days . 'd ago';
        }
    }
    
    private function getActivityColor(string $status): string
    {
        $colors = [
            'OPEN' => 'primary',
            'SENT' => 'info',
            'RECEIVING' => 'warning',
            'RECEIVED' => 'success',
            'CLOSED' => 'secondary'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
    
    /**
     * Get quick stats for sidebar
     */
    public function getQuickStats(array $params = []): array
    {
        // Active orders
        $sql = "
            SELECT COUNT(*) 
            FROM vend_consignments 
            WHERE supplier_id = ?
            AND transfer_category = 'PURCHASE_ORDER'
            AND status IN ('OPEN', 'SENT', 'PROCESSING')
            AND deleted_at IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->supplierId]);
        $activeOrders = (int)$stmt->fetchColumn();
        
        // Stock health
        $sql = "
            SELECT 
                COUNT(DISTINCT CASE WHEN i.inventory_level >= 10 THEN p.id END) as healthy,
                COUNT(DISTINCT p.id) as total
            FROM vend_products p
            LEFT JOIN vend_inventory i ON p.id = i.product_id
            WHERE p.supplier_id = ?
            AND p.deleted_at IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->supplierId]);
        $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockHealth = $stockData['total'] > 0 ? round(($stockData['healthy'] / $stockData['total']) * 100) : 0;
        
        // Revenue this month
        $sql = "
            SELECT COALESCE(SUM(total_cost), 0) as revenue
            FROM vend_consignments
            WHERE supplier_id = ?
            AND transfer_category = 'PURCHASE_ORDER'
            AND MONTH(created_at) = MONTH(NOW())
            AND YEAR(created_at) = YEAR(NOW())
            AND deleted_at IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->supplierId]);
        $revenueMonth = (float)$stmt->fetchColumn();
        
        return [
            'active_orders' => $activeOrders,
            'stock_health' => $stockHealth,
            'revenue_month' => $revenueMonth
        ];
    }
}
