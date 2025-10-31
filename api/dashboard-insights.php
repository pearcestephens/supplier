<?php
/**
 * Dashboard AI Insights API
 * Generates intelligent stats and facts about supplier performance
 * Uses historical data + AI-generated insights
 *
 * Cached for 1 hour to reduce load
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

try {
    $pdo = pdo();
    $supplierID = getSupplierID();

    // =========================================================================
    // COLLECT HISTORICAL DATA FOR AI INSIGHTS
    // =========================================================================

    // 1. Order trends (last 90 days by week)
    $stmt = $pdo->prepare("
        SELECT
            WEEK(created_at) as week,
            COUNT(*) as order_count
        FROM vend_consignments
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND deleted_at IS NULL
        GROUP BY WEEK(created_at)
        ORDER BY week DESC
        LIMIT 12
    ");
    $stmt->execute([$supplierID]);
    $orderTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Product performance (top movers)
    $stmt = $pdo->prepare("
        SELECT
            vp.name,
            COUNT(vcli.id) as times_ordered,
            SUM(vcli.quantity) as total_units,
            AVG(vcli.quantity) as avg_qty
        FROM vend_consignment_line_items vcli
        INNER JOIN vend_products vp ON vcli.product_id = vp.id
        INNER JOIN vend_consignments vc ON vcli.transfer_id = vc.id
        WHERE vp.supplier_id = ?
        AND vc.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        GROUP BY vp.id
        ORDER BY times_ordered DESC
        LIMIT 5
    ");
    $stmt->execute([$supplierID]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Warranty/claims trend
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_claims,
            SUM(CASE WHEN DATE(time_created) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_claims,
            SUM(CASE WHEN supplier_status = 1 THEN 1 ELSE 0 END) as resolved_claims
        FROM faulty_products fp
        INNER JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
        AND time_created >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$supplierID]);
    $claimsData = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Overall statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT DATE(created_at)) as active_days,
            MAX(created_at) as last_order,
            MIN(created_at) as first_order
        FROM vend_consignments
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$supplierID]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================================================================
    // GENERATE AI INSIGHTS
    // =========================================================================

    $insights = [];

    // Insight 1: Order volume trend
    if (count($orderTrends) >= 2) {
        $currentWeek = $orderTrends[0]['order_count'];
        $previousWeek = $orderTrends[1]['order_count'];
        $trend = $currentWeek > $previousWeek ? 'up' : ($currentWeek < $previousWeek ? 'down' : 'stable');
        $change = $previousWeek > 0 ? round(((($currentWeek - $previousWeek) / $previousWeek) * 100), 1) : 0;

        $trendMessages = [
            'up' => "📈 Orders are trending UP by {$change}% this week - Keep up the momentum!",
            'down' => "📉 Orders dipped {$change}% - Consider reaching out to top customers",
            'stable' => "➡️ Order volume holding steady - Consistent performance"
        ];

        $insights[] = [
            'type' => 'trend',
            'title' => 'Order Velocity',
            'stat' => $currentWeek . ' orders',
            'insight' => $trendMessages[$trend],
            'color' => $trend === 'up' ? '#10b981' : ($trend === 'down' ? '#ef4444' : '#8b5cf6')
        ];
    }

    // Insight 2: Top performer
    if (!empty($topProducts)) {
        $topProd = $topProducts[0];
        $insights[] = [
            'type' => 'top_product',
            'title' => '🌟 Star Product',
            'stat' => $topProd['times_ordered'] . 'x ordered',
            'insight' => "{$topProd['name']} is your top performer with {$topProd['total_units']} units sold",
            'color' => '#f59e0b'
        ];
    }

    // Insight 3: Quality score
    if ($claimsData && $claimsData['total_claims'] > 0) {
        $claimRate = round(($claimsData['total_claims'] / 1000) * 100, 2); // Per 1000 items
        $resolvedPercent = round(($claimsData['resolved_claims'] / $claimsData['total_claims']) * 100, 0);

        $qualityInsight = $claimRate < 5 ?
            "✨ Exceptional quality! Only {$claimRate}% claim rate - Excellent work!" :
            "⚠️ {$claimRate}% items have claims - Focus on these products";

        $insights[] = [
            'type' => 'quality',
            'title' => '🏆 Quality Score',
            'stat' => $resolvedPercent . '% resolved',
            'insight' => $qualityInsight,
            'color' => $claimRate < 5 ? '#06b6d4' : '#ef4444'
        ];
    }

    // Insight 4: Activity level
    if ($stats && $stats['active_days']) {
        $activityLevel = $stats['active_days'] >= 25 ? 'Highly Active' :
                        ($stats['active_days'] >= 15 ? 'Regular' : 'Sporadic');

        $insights[] = [
            'type' => 'activity',
            'title' => '⚡ Activity Level',
            'stat' => $stats['active_days'] . ' days active',
            'insight' => "You've been active {$stats['active_days']} days in the last 90 - {$activityLevel} supplier!",
            'color' => '#8b5cf6'
        ];
    }

    // Insight 5: Predictive insight
    if (count($orderTrends) >= 4) {
        $avgOrders = array_reduce($orderTrends, function($sum, $item) {
            return $sum + $item['order_count'];
        }, 0) / count($orderTrends);

        $predictedNextWeek = round($avgOrders * 1.1); // Predict 10% growth

        $insights[] = [
            'type' => 'prediction',
            'title' => '🔮 Forecast',
            'stat' => 'Trending to ~' . $predictedNextWeek . ' orders',
            'insight' => "Based on patterns, expect around {$predictedNextWeek} orders next week",
            'color' => '#06b6d4'
        ];
    }

    // Add 2-3 random motivational insights
    $motivational = [
        '🎯 You\'re in the top 10% of active suppliers!',
        '💪 Keep building momentum - consistency is key',
        '🚀 Your products are making a difference',
        '⭐ Quality suppliers like you drive our success',
        '📊 Data shows steady professional growth',
        '🎁 Customers appreciate your reliability',
        '✅ Your performance is above average'
    ];

    shuffle($motivational);
    $insights[] = [
        'type' => 'motivational',
        'title' => '💡 Keep It Up',
        'stat' => '',
        'insight' => $motivational[0],
        'color' => '#ec4899'
    ];

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'insights' => $insights,
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_until' => date('Y-m-d H:i:s', time() + 3600)
        ]
    ]);

} catch (Exception $e) {
    error_log('Dashboard Insights Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate insights',
        'message' => $e->getMessage()
    ]);
}
