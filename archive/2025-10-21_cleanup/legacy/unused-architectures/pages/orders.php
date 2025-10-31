<?php
/**
 * Orders Page
 * 
 * NO require statements - bootstrap already loaded
 */

$orders = get_supplier_orders($supplier['id'], 100);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">📦 Your Orders</h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    Order History
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="text-muted">No orders found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['id'] ?? 'N/A') ?></td>
                                            <td><?= format_date($order['created_at'] ?? null) ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?= htmlspecialchars($order['status'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td><?= (int)($order['item_count'] ?? 0) ?></td>
                                            <td><?= format_currency($order['total'] ?? 0) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary">View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
