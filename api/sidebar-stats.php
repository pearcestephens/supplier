<?php
/**
 * Sidebar Stats API - Real-time Quick Stats for Sidebar Widget
 * 
 * Returns:
 * - Active orders count and percentage
 * - Stock health percentage
 * - This month's orders count
 * - Recent activity (last 4 actions)
 * 
 * @package SupplierPortal\API
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
requireAuth();

try {
    $db = db();
    $supplierID = getSupplierID();
    
    // ========================================================================
    // STAT 1: Active Orders (not completed/cancelled)
    // ========================================================================
    $activeOrdersQuery = "
        SELECT COUNT(*) as count
        FROM vend_consignments
        WHERE supplier_id = ?
        AND state IN ('OPEN', 'SENT', 'RECEIVING', 'PARTIAL')
        AND deleted_at IS NULL
    ";
    $stmt = $db->prepare($activeOrdersQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $activeOrders = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Total orders for percentage calculation
    $totalOrdersQuery = "
        SELECT COUNT(*) as count
        FROM vend_consignments
        WHERE supplier_id = ?
        AND deleted_at IS NULL
    ";
    $stmt = $db->prepare($totalOrdersQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $totalOrders = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    $activeOrdersPercent = $totalOrders > 0 ? round(($activeOrders / $totalOrders) * 100) : 0;
    
    // ========================================================================
    // STAT 2: Stock Health (products with adequate stock vs low stock)
    // ========================================================================
    // Products with inventory across all outlets
    $stockHealthQuery = "
        SELECT 
            COUNT(DISTINCT p.id) as total_products,
            COUNT(DISTINCT CASE 
                WHEN i.current_amount > 5 THEN p.id 
            END) as healthy_products
        FROM vend_products p
        LEFT JOIN vend_inventory i ON p.id = i.product_id
        WHERE p.supplier_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
    ";
    $stmt = $db->prepare($stockHealthQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $stockData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stockHealthPercent = $stockData['total_products'] > 0 
        ? round(($stockData['healthy_products'] / $stockData['total_products']) * 100) 
        : 0;
    
    // ========================================================================
    // STAT 3: This Month's Orders
    // ========================================================================
    $thisMonthQuery = "
        SELECT COUNT(*) as count
        FROM vend_consignments
        WHERE supplier_id = ?
        AND YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())
        AND deleted_at IS NULL
    ";
    $stmt = $db->prepare($thisMonthQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $thisMonthOrders = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Last month for percentage calculation
    $lastMonthQuery = "
        SELECT COUNT(*) as count
        FROM vend_consignments
        WHERE supplier_id = ?
        AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND deleted_at IS NULL
    ";
    $stmt = $db->prepare($lastMonthQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $lastMonthOrders = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    $monthlyGrowthPercent = $lastMonthOrders > 0 
        ? round((($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100) 
        : 100;
    
    // ========================================================================
    // RECENT ACTIVITY: Last 5 significant events from VAST ARRAY of sources
    // Sources: Orders, Warranties, Notes, Status Changes, Tracking Updates
    // ========================================================================
    
    // Collect activities from multiple sources
    $allActivities = [];
    
    // SOURCE 1: Order Events (created, status changes)
    try {
        $orderActivitiesQuery = "
            SELECT 
                'order' as type,
                t.public_id as reference,
                t.state as status,
                t.created_at as timestamp,
                'order_status' as event_type,
                o.name as outlet_name
            FROM vend_consignments t
            LEFT JOIN vend_outlets o ON t.outlet_to = o.id
            WHERE t.supplier_id = ?
            AND t.deleted_at IS NULL
            ORDER BY t.created_at DESC
            LIMIT 10
        ";
        $stmt = $db->prepare($orderActivitiesQuery);
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $orderActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($orderActivities as $activity) {
            $allActivities[] = [
                'timestamp' => $activity['timestamp'],
                'type' => 'order',
                'event_type' => 'status',
                'status' => $activity['status'],
                'reference' => $activity['reference'],
                'outlet' => $activity['outlet_name']
            ];
        }
    } catch (Exception $e) {
        error_log('Sidebar Stats - Order Activities Error: ' . $e->getMessage());
    }
    
    // SOURCE 2: Warranty Claims (new claims, status changes)
    try {
        $warrantyActivitiesQuery = "
            SELECT 
                'warranty' as type,
                fp.id as reference,
                fp.supplier_status as status,
                fp.supplier_status_timestamp as timestamp,
                p.name as product_name,
                CASE 
                    WHEN fp.supplier_status = 0 THEN 'pending'
                    WHEN fp.supplier_status = 1 THEN 'accepted'
                    WHEN fp.supplier_status = 2 THEN 'declined'
                END as status_text
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.supplier_status_timestamp IS NOT NULL
            AND fp.supplier_status_timestamp != '0000-00-00 00:00:00'
            ORDER BY fp.supplier_status_timestamp DESC
            LIMIT 10
        ";
        $stmt = $db->prepare($warrantyActivitiesQuery);
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $warrantyActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($warrantyActivities as $activity) {
            $allActivities[] = [
                'timestamp' => $activity['timestamp'],
                'type' => 'warranty',
                'event_type' => 'status_change',
                'status' => $activity['status_text'],
                'reference' => $activity['reference'],
                'product' => $activity['product_name']
            ];
        }
    } catch (Exception $e) {
        error_log('Sidebar Stats - Warranty Activities Error: ' . $e->getMessage());
    }
    
    // SOURCE 3: Notes Added (order notes, warranty notes)
    // PLACEHOLDER - Activity log table schema needs verification
    try {
        // TODO: Verify supplier_activity_log table structure before enabling
        // Expected columns: supplier_id, consignment_id, action, created_at
        $noteActivities = []; // Return empty for now
        
        foreach ($noteActivities as $activity) {
            $allActivities[] = [
                'timestamp' => $activity['timestamp'],
                'type' => 'note',
                'event_type' => 'added',
                'reference' => $activity['reference'],
                'preview' => substr($activity['note_preview'], 11, 50) // Remove "Added note: " prefix
            ];
        }
    } catch (Exception $e) {
        error_log('Sidebar Stats - Notes Activities Error: ' . $e->getMessage());
    }
    
    // SOURCE 4: Tracking Number Updates
    // PLACEHOLDER - Activity log table schema needs verification
    try {
        // TODO: Verify supplier_activity_log table structure before enabling
        $trackingActivities = []; // Return empty for now
        
        foreach ($trackingActivities as $activity) {
            $allActivities[] = [
                'timestamp' => $activity['timestamp'],
                'type' => 'tracking',
                'event_type' => 'updated',
                'reference' => $activity['reference'],
                'tracking' => $activity['tracking']
            ];
        }
    } catch (Exception $e) {
        error_log('Sidebar Stats - Tracking Activities Error: ' . $e->getMessage());
    }
    
    // Sort all activities by timestamp (most recent first)
    if (!empty($allActivities)) {
        usort($allActivities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Take top 5 activities
        $topActivities = array_slice($allActivities, 0, 5);
    } else {
        // No activities found, return empty array
        $topActivities = [];
    }
    
    // Format activities for display with icons and rich text
    $recentActivity = [];
    foreach ($topActivities as $activity) {
        $timeAgo = timeAgo($activity['timestamp']);
        $label = '';
        $color = 'primary';
        $icon = 'circle';
        
        // Ensure reference exists and format it nicely
        $reference = $activity['reference'] ?? 'Unknown';
        
        // Truncate long UUIDs/IDs - keep only last 6 characters for display
        // Example: "JCE-PO-12345" stays as is, but "abc123def456ghi789" becomes "...hi789"
        $displayRef = $reference;
        if (strlen($reference) > 12 && strpos($reference, '-') === false) {
            // It's a UUID without dashes, truncate to last 6 chars
            $displayRef = '...' . substr($reference, -6);
        } elseif (strlen($reference) > 20) {
            // Very long reference, truncate
            $displayRef = substr($reference, 0, 12) . '...';
        }
        
        switch ($activity['type']) {
            case 'order':
                switch ($activity['status']) {
                    case 'OPEN':
                        $label = 'New Order #' . $displayRef;
                        $color = 'primary';
                        $icon = 'shopping-cart';
                        break;
                    case 'SENT':
                    case 'RECEIVING':
                        $label = 'Order #' . $displayRef . ' processing';
                        $color = 'info';
                        $icon = 'truck';
                        break;
                    case 'RECEIVED':
                        $label = 'Order #' . $displayRef . ' delivered';
                        $color = 'success';
                        $icon = 'check-circle';
                        break;
                    case 'PARTIAL':
                        $label = 'Order #' . $displayRef . ' partially received';
                        $color = 'warning';
                        $icon = 'exclamation-triangle';
                        break;
                    case 'CANCELLED':
                        $label = 'Order #' . $displayRef . ' cancelled';
                        $color = 'danger';
                        $icon = 'times-circle';
                        break;
                    default:
                        $label = 'Order #' . $displayRef . ' updated';
                        $color = 'primary';
                        $icon = 'shopping-cart';
                        break;
                }
                break;
                
            case 'warranty':
                switch ($activity['status']) {
                    case 'pending':
                        $label = 'Warranty #' . $displayRef . ' submitted';
                        $color = 'warning';
                        $icon = 'wrench';
                        break;
                    case 'accepted':
                        $label = 'Warranty #' . $displayRef . ' approved';
                        $color = 'success';
                        $icon = 'check';
                        break;
                    case 'declined':
                        $label = 'Warranty #' . $displayRef . ' declined';
                        $color = 'danger';
                        $icon = 'times';
                        break;
                    default:
                        $label = 'Warranty #' . $displayRef . ' updated';
                        $color = 'info';
                        $icon = 'wrench';
                        break;
                }
                break;
                
            case 'note':
                $label = 'Note added to #' . $displayRef;
                $color = 'info';
                $icon = 'comment';
                break;
                
            case 'tracking':
                $label = 'Tracking updated for #' . $displayRef;
                $color = 'primary';
                $icon = 'box';
                break;
                
            default:
                // Fallback for unknown activity types
                $label = 'Activity on ' . $reference;
                $color = 'secondary';
                $icon = 'circle';
                break;
        }
        
        // Only add if we have a valid label
        if (!empty($label)) {
            $recentActivity[] = [
                'label' => $label,
                'color' => $color,
                'icon' => $icon,
                'time' => $timeAgo,
                'reference' => $reference
            ];
        }
    }
    
    // ========================================================================
    // SEND RESPONSE
    // ========================================================================
    sendJsonResponse(true, [
        'active_orders' => [
            'count' => $activeOrders,
            'percent' => $activeOrdersPercent
        ],
        'stock_health' => [
            'percent' => $stockHealthPercent,
            'healthy' => $stockData['healthy_products'],
            'total' => $stockData['total_products']
        ],
        'monthly_orders' => [
            'count' => $thisMonthOrders,
            'growth_percent' => $monthlyGrowthPercent
        ],
        'recent_activity' => $recentActivity
    ]);
    
} catch (Exception $e) {
    error_log('Sidebar Stats Error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'Failed to load sidebar stats', 500);
}

/**
 * Convert timestamp to human-readable "time ago" format
 */
function timeAgo(string $timestamp): string {
    $now = time();
    $ago = strtotime($timestamp);
    $diff = $now - $ago;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ago';
    } else {
        return date('j M', $ago);
    }
}
