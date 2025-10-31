<?php
/**
 * Purchase Order Detail Page
 * 
 * View full PO details with line items, status updates, and actions
 */

// Get PO ID
$transfer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$transfer_id) {
    echo '<div class="alert alert-danger">Invalid purchase order ID.</div>';
    exit;
}

// Get PO details
$po_details = get_purchase_order_details($conn, $supplier_id, $transfer_id);

if (!$po_details) {
    echo '<div class="alert alert-danger">Purchase order not found or you do not have access to it.</div>';
    exit;
}

// Get line items
$line_items = get_purchase_order_items($conn, $transfer_id);

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_purchase_order_detail', 'transfer', $transfer_id);

// Calculate totals
$total_requested = 0;
$total_sent = 0;
$total_received = 0;
$total_value = 0;

foreach ($line_items as $item) {
    $total_requested += $item['qty_requested'];
    $total_sent += $item['qty_sent_total'];
    $total_received += $item['qty_received_total'];
    
    // Calculate value (use cost if available, otherwise supply_price)
    $unit_cost = $item['cost'] ?? $item['supply_price'] ?? 0;
    $total_value += $unit_cost * $item['qty_requested'];
}
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    Purchase Order: <?php echo htmlspecialchars($po_details['public_id']); ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="?page=purchase-orders">Purchase Orders</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($po_details['public_id']); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Action Buttons Row -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group float-right">
                    <button onclick="window.print()" class="btn btn-default">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="?page=purchase-orders" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <!-- PO Header Information -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i> Order Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">PO Number:</dt>
                                    <dd class="col-sm-7">
                                        <strong><?php echo htmlspecialchars($po_details['public_id']); ?></strong>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Status:</dt>
                                    <dd class="col-sm-7">
                                        <span class="badge badge-lg <?php echo get_state_badge_class($po_details['state']); ?>">
                                            <?php echo htmlspecialchars($po_details['state']); ?>
                                        </span>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Store:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo htmlspecialchars($po_details['outlet_name']); ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Created:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo date('F j, Y g:i A', strtotime($po_details['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo time_ago($po_details['created_at']); ?></small>
                                    </dd>
                                </dl>
                            </div>
                            
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">Due Date:</dt>
                                    <dd class="col-sm-7">
                                        <?php if ($po_details['due_at']): ?>
                                            <?php echo date('F j, Y', strtotime($po_details['due_at'])); ?>
                                            <?php
                                            $days_until = (strtotime($po_details['due_at']) - time()) / 86400;
                                            if ($days_until < 0) {
                                                echo '<br><span class="badge badge-danger">Overdue by ' . abs(round($days_until)) . ' days</span>';
                                            } elseif ($days_until < 7) {
                                                echo '<br><span class="badge badge-warning">Due in ' . round($days_until) . ' days</span>';
                                            } else {
                                                echo '<br><span class="badge badge-info">' . round($days_until) . ' days remaining</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Total Items:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo count($line_items); ?> line items
                                    </dd>
                                    
                                    <dt class="col-sm-5">Total Quantity:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo number_format($total_requested); ?> units
                                    </dd>
                                    
                                    <dt class="col-sm-5">Estimated Value:</dt>
                                    <dd class="col-sm-7">
                                        <strong><?php echo format_currency($total_value); ?></strong>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        
                        <?php if ($po_details['notes']): ?>
                            <hr>
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> Notes:</h5>
                                <?php echo nl2br(htmlspecialchars($po_details['notes'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats Sidebar -->
            <div class="col-md-4">
                <div class="card bg-gradient-info">
                    <div class="card-header border-0">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i> Fulfillment Progress
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <h2 class="display-4">
                                <?php 
                                $percent_received = $total_requested > 0 ? round(($total_received / $total_requested) * 100) : 0;
                                echo $percent_received; 
                                ?>%
                            </h2>
                            <p class="text-white">Received</p>
                        </div>
                        
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo $percent_received; ?>%">
                                <?php echo number_format($total_received); ?> recv
                            </div>
                            <?php if ($total_sent > $total_received): ?>
                                <div class="progress-bar bg-warning" 
                                     style="width: <?php echo round((($total_sent - $total_received) / max($total_requested, 1)) * 100); ?>%">
                                    <?php echo number_format($total_sent - $total_received); ?> sent
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <ul class="list-unstyled text-white">
                            <li class="mb-2">
                                <i class="fas fa-box"></i>
                                <strong>Requested:</strong> <?php echo number_format($total_requested); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-shipping-fast"></i>
                                <strong>Sent:</strong> <?php echo number_format($total_sent); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle"></i>
                                <strong>Received:</strong> <?php echo number_format($total_received); ?>
                            </li>
                            <li>
                                <i class="fas fa-dollar-sign"></i>
                                <strong>Value:</strong> <?php echo format_currency($total_value); ?>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if (in_array($po_details['state'], ['OPEN', 'SENT'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tasks"></i> Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($po_details['state'] === 'OPEN'): ?>
                                <button class="btn btn-success btn-block mb-2" onclick="updatePOStatus(<?php echo $transfer_id; ?>, 'SENT')">
                                    <i class="fas fa-paper-plane"></i> Mark as Sent
                                </button>
                                <button class="btn btn-warning btn-block" onclick="updatePOStatus(<?php echo $transfer_id; ?>, 'CANCELLED')">
                                    <i class="fas fa-times-circle"></i> Cancel Order
                                </button>
                            <?php elseif ($po_details['state'] === 'SENT'): ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle"></i>
                                    This order is in transit. The store will mark it as received when it arrives.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> Line Items
                            <span class="badge badge-primary ml-2"><?php echo count($line_items); ?></span>
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th class="text-center">Requested</th>
                                        <th class="text-center">Sent</th>
                                        <th class="text-center">Received</th>
                                        <th class="text-right">Unit Cost</th>
                                        <th class="text-right">Line Total</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($line_items as $index => $item): ?>
                                        <?php
                                        $unit_cost = $item['cost'] ?? $item['supply_price'] ?? 0;
                                        $line_total = $unit_cost * $item['qty_requested'];
                                        $item_percent = $item['qty_requested'] > 0 ? 
                                            round(($item['qty_received_total'] / $item['qty_requested']) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></strong>
                                                <?php if (isset($item['variant_name']) && $item['variant_name']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($item['variant_name']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($item['sku'] ?? '-'); ?></code>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-secondary">
                                                    <?php echo number_format($item['qty_requested']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-info">
                                                    <?php echo number_format($item['qty_sent_total']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-success">
                                                    <?php echo number_format($item['qty_received_total']); ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <?php echo format_currency($unit_cost); ?>
                                            </td>
                                            <td class="text-right">
                                                <strong><?php echo format_currency($line_total); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" 
                                                         style="width: <?php echo $item_percent; ?>%"
                                                         title="<?php echo $item_percent; ?>% received">
                                                        <?php if ($item_percent > 20): ?>
                                                            <?php echo $item_percent; ?>%
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="3" class="text-right">TOTALS:</td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary badge-lg">
                                                <?php echo number_format($total_requested); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-info badge-lg">
                                                <?php echo number_format($total_sent); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-success badge-lg">
                                                <?php echo number_format($total_received); ?>
                                            </span>
                                        </td>
                                        <td></td>
                                        <td class="text-right">
                                            <strong style="font-size: 1.1em;">
                                                <?php echo format_currency($total_value); ?>
                                            </strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
function updatePOStatus(transferId, newStatus) {
    const confirmMessages = {
        'SENT': 'Mark this purchase order as SENT? This indicates you have dispatched the items.',
        'CANCELLED': 'Cancel this purchase order? This action cannot be undone.',
    };
    
    if (!confirm(confirmMessages[newStatus] || 'Update this purchase order status?')) {
        return;
    }
    
    // Show loading
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Make AJAX request
    fetch('api/update-po-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            transfer_id: transferId,
            new_status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Purchase order status updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update status'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}
</script>
