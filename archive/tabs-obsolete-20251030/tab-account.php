<?php
/**
 * Supplier Portal - Account Settings Tab
 *
 * Full-featured account management:
 * - Profile information display and editing
 * - Contact details management
 * - Session information
 * - Security settings
 * - Activity log
 *
 * @package CIS\Supplier\Tabs
 * @version 2.0.0
 */

declare(strict_types=1);

if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

// Get authenticated supplier details
$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// Get full supplier details from database
$supplierQuery = "
    SELECT
        id,
        name,
        email,
        phone,
        website
    FROM vend_suppliers
    WHERE id = ?
    AND deleted_at IS NULL
    LIMIT 1
";
$stmt = $db->prepare($supplierQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$supplierData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Add created_at fallback (not available in vend_suppliers table)
$supplierData['created_at'] = null;

// Get session info
$lastActivity = Session::get('last_activity') ?? time();
$sessionStart = Session::get('login_time') ?? time();
$sessionDuration = time() - $sessionStart;

// Get recent activity
$activityQuery = "
    SELECT
        activity_type,
        details,
        created_at
    FROM supplier_activity_log
    WHERE supplier_id = ?
    ORDER BY created_at DESC
    LIMIT 10
";
$stmt = $db->prepare($activityQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get account statistics
$statsQuery = "
    SELECT
        (SELECT COUNT(*) FROM vend_consignments WHERE supplier_id = ? AND transfer_category = 'PURCHASE_ORDER' AND deleted_at IS NULL) as total_orders,
        (SELECT COUNT(*) FROM faulty_products WHERE supplier_id = ?) as total_warranties,
        (SELECT COUNT(*) FROM vend_products WHERE supplier_id = ? AND active = 1 AND deleted_at IS NULL) as active_products
";
$stmt = $db->prepare($statsQuery);
$stmt->bind_param('sss', $supplierID, $supplierID, $supplierID);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!-- Page Header -->
<div class="page-header mb-4">
    <h1 class="h2">
        <i class="fa-solid fa-user-circle"></i> Account Settings
    </h1>
    <p class="text-muted">Manage your profile and view account information</p>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <!-- Profile Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-id-card"></i> Profile Information</h5>
                <button class="btn btn-sm btn-light" onclick="toggleEditMode()">
                    <i class="fa-solid fa-pencil"></i> Edit Profile
                </button>
            </div>
            <div class="card-body">
                <!-- View Mode -->
                <div id="viewMode">
                    <div class="row mb-3">
                        <div class="col-md-3 fw-semibold text-muted">Company Name:</div>
                        <div class="col-md-9"><?= e($supplierData['name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-semibold text-muted">Email:</div>
                        <div class="col-md-9">
                            <?= e($supplierData['email'] ?? 'N/A') ?>
                            <?php if (!empty($supplierData['email'])): ?>
                                <span class="badge bg-success ms-2"><i class="fa-solid fa-check"></i> Verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-semibold text-muted">Phone:</div>
                        <div class="col-md-9"><?= e($supplierData['phone'] ?? 'Not provided') ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-semibold text-muted">Website:</div>
                        <div class="col-md-9">
                            <?php if (!empty($supplierData['website'])): ?>
                                <a href="<?= e($supplierData['website']) ?>" target="_blank" rel="noopener">
                                    <?= e($supplierData['website']) ?> <i class="fa-solid fa-external-link-alt fa-xs"></i>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 fw-semibold text-muted">Member Since:</div>
                        <div class="col-md-9"><?= formatDate($supplierData['created_at'] ?? time(), 'display') ?></div>
                    </div>
                </div>

                <!-- Edit Mode (hidden by default) -->
                <div id="editMode" style="display: none;">
                    <form id="profileForm" onsubmit="saveProfile(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Company Name</label>
                            <input type="text" class="form-control" id="edit_name" value="<?= e($supplierData['name'] ?? '') ?>" required>
                            <small class="form-text text-muted">Your registered company name</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" value="<?= e($supplierData['email'] ?? '') ?>" required>
                            <small class="form-text text-muted">Primary contact email</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone" value="<?= e($supplierData['phone'] ?? '') ?>" placeholder="+64 21 123 4567">
                            <small class="form-text text-muted">Optional contact number</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" class="form-control" id="edit_website" value="<?= e($supplierData['website'] ?? '') ?>" placeholder="https://example.com">
                            <small class="form-text text-muted">Your company website</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                                <i class="fa-solid fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Statistics Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-chart-bar"></i> Account Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="stat-box">
                            <div class="stat-value text-primary display-5"><?= number_format($stats['total_orders'] ?? 0) ?></div>
                            <div class="stat-label text-muted">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="stat-box">
                            <div class="stat-value text-warning display-5"><?= number_format($stats['total_warranties'] ?? 0) ?></div>
                            <div class="stat-label text-muted">Warranty Claims</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="stat-value text-success display-5"><?= number_format($stats['active_products'] ?? 0) ?></div>
                            <div class="stat-label text-muted">Active Products</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Session Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fa-solid fa-clock"></i> Session Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Login Time:</span>
                        <span><?= formatDate($sessionStart, 'time') ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Last Activity:</span>
                        <span><?= formatDate($lastActivity, 'time') ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Session Duration:</span>
                        <span><?= gmdate('H:i:s', $sessionDuration) ?></span>
                    </div>
                </div>
                <div class="d-grid">
                    <a href="/supplier/logout.php" class="btn btn-outline-danger">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa-solid fa-list"></i> Recent Activity</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentActivity)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div>
                                        <i class="fa-solid fa-circle fa-xs text-primary me-2"></i>
                                        <strong><?= e($activity['activity_type']) ?></strong>
                                    </div>
                                    <small class="text-muted"><?= timeAgo($activity['created_at']) ?></small>
                                </div>
                                <?php if (!empty($activity['details'])): ?>
                                    <small class="text-muted ms-3"><?= e($activity['details']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0">No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
