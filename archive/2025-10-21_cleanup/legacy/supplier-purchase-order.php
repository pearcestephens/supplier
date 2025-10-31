<?php
/**
 * The Vape Shed - Supplier Portal Purchase Order View
 * Purchase order management with integrated template and branding
 * 
 * @file supplier-purchase-order.php
 * @purpose View and manage purchase orders
 * @author Pearce Stephens
 * @last_modified 2025-10-07
 */

$pageTitle = 'Purchase Orders - The Vape Shed Supplier Portal';
$supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 12345;
$orderID = isset($_GET['orderID']) ? htmlspecialchars($_GET['orderID']) : 'PO-2025-001';

// Include the updated header with logo and menu
include_once 'supplier-header-updated.php';
?>

<!-- PURCHASE ORDER CONTENT -->
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-invoice text-primary mr-2"></i>
                Purchase Order: <?php echo $orderID; ?>
            </h1>
            <p class="mb-0 text-muted">View and manage purchase order details</p>
        </div>
        <div class="col-lg-4 text-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="printPO()">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
                <button type="button" class="btn btn-outline-info" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </button>
                <button type="button" class="btn btn-primary" onclick="updateStatus()">
                    <i class="fas fa-edit mr-1"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ORDER STATUS & INFO -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">
                    <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="VS" style="width: 24px; height: 24px; border-radius: 4px; margin-right: 8px;">
                    Order Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="font-weight-bold">Purchase Order #:</td>
                                <td><?php echo $orderID; ?></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Order Date:</td>
                                <td>October 5, 2025</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Expected Delivery:</td>
                                <td>October 12, 2025</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Payment Terms:</td>
                                <td>Net 30 Days</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Supplier:</td>
                                <td>The Vape Shed (Supplier #<?php echo $supplierID; ?>)</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="font-weight-bold">Status:</td>
                                <td><span class="badge badge-warning badge-lg">Pending Confirmation</span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Priority:</td>
                                <td><span class="badge badge-info">Standard</span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Total Items:</td>
                                <td>15 items</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Total Amount:</td>
                                <td class="h5 text-success">$2,847.50</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Reference:</td>
                                <td>REF-VS-OCT-001</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock mr-2"></i>Order Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Order Created</h6>
                            <p class="timeline-text">Oct 5, 2025 10:30 AM</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Awaiting Confirmation</h6>
                            <p class="timeline-text">Current Status</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-secondary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Expected Shipping</h6>
                            <p class="timeline-text">Oct 10, 2025</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-secondary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Expected Delivery</h6>
                            <p class="timeline-text">Oct 12, 2025</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SHIPPING & BILLING -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-truck mr-2"></i>Shipping Address
                </h6>
            </div>
            <div class="card-body">
                <address class="mb-0">
                    <strong>The Vape Shed - Auckland Central</strong><br>
                    123 Queen Street<br>
                    Auckland Central, Auckland 1010<br>
                    New Zealand<br>
                    <abbr title="Phone">P:</abbr> +64 9 123 4567
                </address>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Billing Address
                </h6>
            </div>
            <div class="card-body">
                <address class="mb-0">
                    <strong>Ecigdis Limited</strong><br>
                    456 Commerce Street<br>
                    Auckland, Auckland 1001<br>
                    New Zealand<br>
                    <abbr title="Phone">P:</abbr> +64 9 987 6543<br>
                    <abbr title="Email">E:</abbr> accounts@ecigdis.co.nz
                </address>
            </div>
        </div>
    </div>
</div>

<!-- ORDER ITEMS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list mr-2"></i>Order Items
                </h6>
                <span class="badge badge-info">15 items</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>SKU-001</strong></td>
                                <td>SMOK Nord 4 Kit</td>
                                <td>Pod system kit with coils</td>
                                <td>25</td>
                                <td>$45.99</td>
                                <td>$1,149.75</td>
                                <td><span class="badge badge-success">Available</span></td>
                            </tr>
                            <tr>
                                <td><strong>SKU-002</strong></td>
                                <td>Vaporesso Gen S Mod</td>
                                <td>220W dual battery mod</td>
                                <td>15</td>
                                <td>$89.99</td>
                                <td>$1,349.85</td>
                                <td><span class="badge badge-success">Available</span></td>
                            </tr>
                            <tr>
                                <td><strong>SKU-003</strong></td>
                                <td>Coil Pack - Nord</td>
                                <td>Pack of 5 replacement coils</td>
                                <td>50</td>
                                <td>$4.99</td>
                                <td>$249.50</td>
                                <td><span class="badge badge-warning">Limited Stock</span></td>
                            </tr>
                            <tr>
                                <td><strong>SKU-004</strong></td>
                                <td>USB-C Cable</td>
                                <td>1m charging cable</td>
                                <td>30</td>
                                <td>$2.99</td>
                                <td>$89.70</td>
                                <td><span class="badge badge-success">Available</span></td>
                            </tr>
                            <tr>
                                <td><strong>SKU-005</strong></td>
                                <td>Carrying Case</td>
                                <td>Premium leather case</td>
                                <td>10</td>
                                <td>$8.67</td>
                                <td>$86.70</td>
                                <td><span class="badge badge-danger">Out of Stock</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ORDER TOTALS -->
<div class="row mb-4">
    <div class="col-lg-8"></div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-calculator mr-2"></i>Order Summary
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-right">$2,847.50</td>
                    </tr>
                    <tr>
                        <td>Shipping:</td>
                        <td class="text-right">$25.00</td>
                    </tr>
                    <tr>
                        <td>GST (15%):</td>
                        <td class="text-right">$427.13</td>
                    </tr>
                    <tr class="border-top">
                        <td class="font-weight-bold h5">Total:</td>
                        <td class="text-right font-weight-bold h5 text-success">$3,299.63</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ACTION BUTTONS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success btn-lg" onclick="confirmOrder()">
                        <i class="fas fa-check mr-2"></i>Confirm Order
                    </button>
                    <button type="button" class="btn btn-warning btn-lg" onclick="requestChanges()">
                        <i class="fas fa-edit mr-2"></i>Request Changes
                    </button>
                    <button type="button" class="btn btn-danger btn-lg" onclick="cancelOrder()">
                        <i class="fas fa-times mr-2"></i>Cancel Order
                    </button>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        Please review the order details carefully before confirming.
                        Any changes or cancellations must be processed within 24 hours.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ORDER NOTES & COMMENTS -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-comments mr-2"></i>Order Notes & Comments
                </h6>
            </div>
            <div class="card-body">
                <div class="comments-section mb-3">
                    <div class="comment-item border-left border-primary pl-3 mb-3">
                        <div class="comment-header d-flex justify-content-between">
                            <strong>System</strong>
                            <small class="text-muted">Oct 5, 2025 10:30 AM</small>
                        </div>
                        <p class="mb-0">Purchase order created automatically from inventory requirements.</p>
                    </div>
                    <div class="comment-item border-left border-info pl-3 mb-3">
                        <div class="comment-header d-flex justify-content-between">
                            <strong>Purchasing Manager</strong>
                            <small class="text-muted">Oct 5, 2025 11:45 AM</small>
                        </div>
                        <p class="mb-0">Priority items needed for weekend stock replenishment. Please expedite if possible.</p>
                    </div>
                </div>
                
                <form id="commentForm">
                    <div class="form-group">
                        <label for="newComment">Add Comment:</label>
                        <textarea class="form-control" id="newComment" name="comment" rows="3" placeholder="Enter your comment..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i>Add Comment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- PURCHASE ORDER JAVASCRIPT -->
<script>
$(document).ready(function() {
    // Set active menu item
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('orders');
    }
    
    // Initialize purchase order view
    initPurchaseOrderView();
    
    function initPurchaseOrderView() {
        // Comment form handler
        $('#commentForm').on('submit', function(e) {
            e.preventDefault();
            addComment();
        });
        
        // Initialize tooltips for timeline
        $('[data-toggle="tooltip"]').tooltip();
    }
});

function confirmOrder() {
    if (confirm('Are you sure you want to confirm this purchase order? This action cannot be undone.')) {
        console.log('Confirming order:', '<?php echo $orderID; ?>');
        
        // Simulate API call
        setTimeout(function() {
            showSupplierNotification('Purchase order <?php echo $orderID; ?> has been confirmed successfully!', 'success');
            updateOrderStatus('Confirmed');
        }, 1000);
    }
}

function requestChanges() {
    var changes = prompt('Please describe the changes you would like to request:');
    if (changes && changes.trim() !== '') {
        console.log('Requesting changes for order:', '<?php echo $orderID; ?>', 'Changes:', changes);
        
        // Simulate API call
        setTimeout(function() {
            showSupplierNotification('Change request has been submitted for order <?php echo $orderID; ?>', 'info');
            addCommentToTimeline('Change Request', changes);
        }, 1000);
    }
}

function cancelOrder() {
    if (confirm('Are you sure you want to cancel this purchase order? This action cannot be undone.')) {
        var reason = prompt('Please provide a reason for cancellation:');
        if (reason && reason.trim() !== '') {
            console.log('Cancelling order:', '<?php echo $orderID; ?>', 'Reason:', reason);
            
            // Simulate API call
            setTimeout(function() {
                showSupplierNotification('Purchase order <?php echo $orderID; ?> has been cancelled.', 'warning');
                updateOrderStatus('Cancelled');
            }, 1000);
        }
    }
}

function updateOrderStatus(newStatus) {
    // Update the status badge
    $('.badge-warning').removeClass('badge-warning').addClass('badge-success').text(newStatus);
    
    // Add to timeline
    var timelineItem = `
        <div class="timeline-item">
            <div class="timeline-marker bg-success"></div>
            <div class="timeline-content">
                <h6 class="timeline-title">${newStatus}</h6>
                <p class="timeline-text">${new Date().toLocaleString()}</p>
            </div>
        </div>
    `;
    $('.timeline').append(timelineItem);
}

function addComment() {
    var comment = $('#newComment').val().trim();
    if (comment !== '') {
        console.log('Adding comment:', comment);
        
        // Add comment to UI
        var commentItem = `
            <div class="comment-item border-left border-success pl-3 mb-3">
                <div class="comment-header d-flex justify-content-between">
                    <strong>Supplier</strong>
                    <small class="text-muted">${new Date().toLocaleString()}</small>
                </div>
                <p class="mb-0">${comment}</p>
            </div>
        `;
        $('.comments-section').append(commentItem);
        
        // Clear form
        $('#newComment').val('');
        
        showSupplierNotification('Comment added successfully!', 'success');
    }
}

function addCommentToTimeline(title, content) {
    var commentItem = `
        <div class="comment-item border-left border-warning pl-3 mb-3">
            <div class="comment-header d-flex justify-content-between">
                <strong>${title}</strong>
                <small class="text-muted">${new Date().toLocaleString()}</small>
            </div>
            <p class="mb-0">${content}</p>
        </div>
    `;
    $('.comments-section').append(commentItem);
}

function printPO() {
    window.print();
}

function downloadPDF() {
    console.log('Downloading PDF for order:', '<?php echo $orderID; ?>');
    showSupplierNotification('PDF download started for order <?php echo $orderID; ?>', 'info');
}

function updateStatus() {
    console.log('Opening status update modal for order:', '<?php echo $orderID; ?>');
    showSupplierNotification('Status update feature coming soon!', 'info');
}
</script>

<!-- Timeline CSS -->
<style>
.timeline {
    position: relative;
    padding-left: 1.5rem;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -1.75rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    padding-left: 0.5rem;
}

.timeline-title {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-text {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 0;
}

.comment-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.comment-header {
    margin-bottom: 0.5rem;
}

/* Print styles */
@media print {
    .btn-group,
    .card-header,
    #commentForm,
    .comments-section {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php
// Include the updated footer
include_once 'supplier-footer-updated.php';
?>