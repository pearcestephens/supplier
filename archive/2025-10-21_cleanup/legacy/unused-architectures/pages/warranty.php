<?php
/**
 * Warranty Claims Page
 * 
 * NO require statements - bootstrap already loaded
 */

$claims = get_supplier_warranty_claims($supplier['id']);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">🔧 Warranty Claims</h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    Your Warranty Claims
                </div>
                <div class="card-body">
                    <?php if (empty($claims)): ?>
                        <p class="text-muted">No warranty claims found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Claim #</th>
                                        <th>Product</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claims as $claim): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($claim['claim_number'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($claim['product_name'] ?? 'N/A') ?></td>
                                            <td><?= format_date($claim['created_at'] ?? null) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $claim['claim_status'] === 'approved' ? 'success' : 'warning' ?>">
                                                    <?= htmlspecialchars($claim['claim_status'] ?? 'N/A') ?>
                                                </span>
                                            </td>
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
