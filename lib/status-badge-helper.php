<?php
/**
 * Status Badge Helper
 * Standardized badge colors and icons across the application
 *
 * Usage:
 * echo renderStatusBadge('pending', 'Order');
 * echo getStatusClass('approved');
 * echo getStatusIcon('rejected');
 */

/**
 * Get Bootstrap badge class for a status
 *
 * @param string $status The status value (case-insensitive)
 * @param string $type The type of entity (order, warranty, payment, etc.)
 * @return string Bootstrap badge class
 */
function getStatusClass($status, $type = 'default') {
    $status = strtolower(trim($status));

    // Order statuses
    $orderStatuses = [
        'pending' => 'bg-warning text-dark',
        'processing' => 'bg-info text-white',
        'sent' => 'bg-primary text-white',
        'delivered' => 'bg-success text-white',
        'cancelled' => 'bg-danger text-white',
        'on hold' => 'bg-secondary text-white',
        'completed' => 'bg-success text-white',
    ];

    // Warranty statuses
    $warrantyStatuses = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success text-white',
        'rejected' => 'bg-danger text-white',
        'under review' => 'bg-info text-white',
        'resolved' => 'bg-success text-white',
    ];

    // Payment statuses
    $paymentStatuses = [
        'paid' => 'bg-success text-white',
        'unpaid' => 'bg-danger text-white',
        'partial' => 'bg-warning text-dark',
        'pending' => 'bg-secondary text-white',
        'refunded' => 'bg-info text-white',
    ];

    // Stock statuses
    $stockStatuses = [
        'in stock' => 'bg-success text-white',
        'low stock' => 'bg-warning text-dark',
        'out of stock' => 'bg-danger text-white',
        'discontinued' => 'bg-secondary text-white',
    ];

    // Select appropriate status map
    $statusMap = $orderStatuses; // default
    if ($type === 'warranty') {
        $statusMap = $warrantyStatuses;
    } elseif ($type === 'payment') {
        $statusMap = $paymentStatuses;
    } elseif ($type === 'stock') {
        $statusMap = $stockStatuses;
    }

    return $statusMap[$status] ?? 'bg-secondary text-white';
}

/**
 * Get Font Awesome icon for a status
 *
 * @param string $status The status value (case-insensitive)
 * @param string $type The type of entity
 * @return string Font Awesome icon class
 */
function getStatusIcon($status, $type = 'default') {
    $status = strtolower(trim($status));

    $iconMap = [
        // Order icons
        'pending' => 'fa-clock',
        'processing' => 'fa-cog fa-spin',
        'sent' => 'fa-shipping-fast',
        'delivered' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle',
        'completed' => 'fa-check-double',
        'on hold' => 'fa-pause-circle',

        // Warranty icons
        'approved' => 'fa-check-circle',
        'rejected' => 'fa-times-circle',
        'under review' => 'fa-search',
        'resolved' => 'fa-check-square',

        // Payment icons
        'paid' => 'fa-check-circle',
        'unpaid' => 'fa-exclamation-circle',
        'partial' => 'fa-adjust',
        'refunded' => 'fa-undo',

        // Stock icons
        'in stock' => 'fa-check-circle',
        'low stock' => 'fa-exclamation-triangle',
        'out of stock' => 'fa-times-circle',
        'discontinued' => 'fa-ban',
    ];

    return $iconMap[$status] ?? 'fa-circle';
}

/**
 * Render a complete status badge with icon
 *
 * @param string $status The status value
 * @param string $type The type of entity
 * @param bool $showIcon Whether to include icon
 * @param bool $pulse Whether to add pulse animation for pending states
 * @return string Complete HTML for badge
 */
function renderStatusBadge($status, $type = 'default', $showIcon = true, $pulse = true) {
    $class = getStatusClass($status, $type);
    $icon = getStatusIcon($status, $type);
    $displayStatus = ucwords($status);

    // Add pulse class for pending statuses
    $pulseClass = '';
    if ($pulse && strtolower($status) === 'pending') {
        $pulseClass = ' badge-pulse';
    }

    $iconHtml = $showIcon ? "<i class=\"fas {$icon} me-1\"></i>" : '';

    return "<span class=\"badge {$class}{$pulseClass}\">{$iconHtml}{$displayStatus}</span>";
}

/**
 * Get all available statuses for a type
 *
 * @param string $type The type of entity
 * @return array Array of available statuses
 */
function getAvailableStatuses($type = 'order') {
    $statuses = [
        'order' => ['Pending', 'Processing', 'Sent', 'Delivered', 'Cancelled', 'On Hold', 'Completed'],
        'warranty' => ['Pending', 'Approved', 'Rejected', 'Under Review', 'Resolved'],
        'payment' => ['Paid', 'Unpaid', 'Partial', 'Pending', 'Refunded'],
        'stock' => ['In Stock', 'Low Stock', 'Out of Stock', 'Discontinued'],
    ];

    return $statuses[$type] ?? $statuses['order'];
}

/**
 * Render status dropdown for filtering
 *
 * @param string $type The type of entity
 * @param string $selectedStatus Currently selected status
 * @param string $name Form field name
 * @param string $id Form field ID
 * @return string Complete HTML for select element
 */
function renderStatusDropdown($type = 'order', $selectedStatus = '', $name = 'status', $id = 'status-filter') {
    $statuses = getAvailableStatuses($type);
    $options = '<option value="">All Statuses</option>';

    foreach ($statuses as $status) {
        $value = strtolower($status);
        $selected = (strtolower($selectedStatus) === $value) ? ' selected' : '';
        $badge = renderStatusBadge($status, $type, true, false);
        $options .= "<option value=\"{$value}\"{$selected}>{$status}</option>";
    }

    return "<select name=\"{$name}\" id=\"{$id}\" class=\"form-select\">{$options}</select>";
}

/**
 * Get status priority for sorting
 *
 * @param string $status The status value
 * @return int Priority value (lower = higher priority)
 */
function getStatusPriority($status) {
    $status = strtolower(trim($status));

    $priorities = [
        'pending' => 1,
        'under review' => 2,
        'processing' => 3,
        'approved' => 4,
        'sent' => 5,
        'delivered' => 6,
        'completed' => 7,
        'resolved' => 8,
        'rejected' => 9,
        'cancelled' => 10,
        'on hold' => 11,
    ];

    return $priorities[$status] ?? 999;
}

/**
 * Check if status requires action
 *
 * @param string $status The status value
 * @return bool True if status requires user action
 */
function isActionableStatus($status) {
    $actionable = ['pending', 'under review', 'on hold'];
    return in_array(strtolower(trim($status)), $actionable);
}

/**
 * Get human-readable status description
 *
 * @param string $status The status value
 * @param string $type The type of entity
 * @return string Description of what the status means
 */
function getStatusDescription($status, $type = 'order') {
    $status = strtolower(trim($status));

    $descriptions = [
        'order' => [
            'pending' => 'Order received and awaiting processing',
            'processing' => 'Order is being prepared for shipment',
            'sent' => 'Order has been dispatched',
            'delivered' => 'Order successfully delivered',
            'cancelled' => 'Order has been cancelled',
            'on hold' => 'Order is temporarily paused',
            'completed' => 'Order completed successfully',
        ],
        'warranty' => [
            'pending' => 'Claim submitted and awaiting review',
            'approved' => 'Claim approved for replacement/refund',
            'rejected' => 'Claim has been rejected',
            'under review' => 'Claim is being reviewed by our team',
            'resolved' => 'Claim has been fully resolved',
        ],
    ];

    return $descriptions[$type][$status] ?? 'No description available';
}

// Example usage in documentation
/*

// Basic badge
echo renderStatusBadge('pending', 'order');
// Output: <span class="badge bg-warning text-dark badge-pulse"><i class="fas fa-clock me-1"></i>Pending</span>

// Badge without icon
echo renderStatusBadge('delivered', 'order', false);
// Output: <span class="badge bg-success text-white">Delivered</span>

// Custom type (warranty)
echo renderStatusBadge('approved', 'warranty');
// Output: <span class="badge bg-success text-white"><i class="fas fa-check-circle me-1"></i>Approved</span>

// In a table
<td><?php echo renderStatusBadge($order['status'], 'order'); ?></td>

// Status dropdown for filtering
echo renderStatusDropdown('order', $_GET['status'] ?? '');

// Get class only (for custom HTML)
<span class="badge <?php echo getStatusClass('pending'); ?>">Custom Badge</span>

// Sort by status priority
usort($orders, function($a, $b) {
    return getStatusPriority($a['status']) - getStatusPriority($b['status']);
});

*/
