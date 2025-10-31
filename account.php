<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// ============================================================================
// DATABASE QUERIES - Account Page Logic
// ============================================================================
$db = db();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

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

// Get session info (timestamps will be handled by formatDate())
$lastActivity = Session::get('last_activity') ?? time();
$sessionStart = Session::get('login_time') ?? time();
$sessionDuration = is_numeric($lastActivity) ? (time() - (int)$lastActivity) : (time() - (is_numeric($sessionStart) ? (int)$sessionStart : strtotime($sessionStart)));

// Get recent activity
$activityQuery = "
    SELECT
        action_type,
        action_details as details,
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
        (SELECT COUNT(*) FROM faulty_products fp
         INNER JOIN vend_products vp ON fp.product_id = vp.id
         WHERE vp.supplier_id = ?) as total_warranties,
        (SELECT COUNT(*) FROM vend_products WHERE supplier_id = ? AND active = 1 AND deleted_at IS NULL) as active_products
";
$stmt = $db->prepare($statsQuery);
$stmt->bind_param('sss', $supplierID, $supplierID, $supplierID);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ========================================================================
// HANDLE FORM SUBMISSIONS - Server-side validation added
// ========================================================================
// Note: All form submissions now validate on the server
// See /supplier/api/account-update.php for validation rules:
// - name: 3-255 characters required
// - email: valid email format required
// - phone: optional, standard phone format if provided
// - website: optional, valid URL if provided
// ========================================================================

$activeTab = 'account';
$pageTitle = 'Account';
$pageIcon = 'fa-solid fa-user';
$pageDescription = 'Manage my contact information and account settings';
$breadcrumb = [
    ['text' => 'Account', 'href' => '/supplier/account.php']
];
?>
<?php include __DIR__ . '/components/html-head.php'; ?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar-new.php'; ?>

<!-- Page Header (Fixed Top Bar) -->
<?php include __DIR__ . '/components/page-header.php'; ?>

<!-- Main Content Area -->
<div class="main-content" id="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

<!-- Account Content -->
<div class="row">
    <!-- Left Column - Company Information -->
    <div class="col-lg-6 mb-4">
        <!-- Company Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-building text-secondary me-2"></i> Company Information</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditCompany()">
                        <i class="fa-solid fa-pencil"></i> Edit
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- View Mode -->
                <div id="viewModeCompany">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Company Name</label>
                        <div class="fw-semibold"><?= e($supplierData['name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Email Address</label>
                        <div class="fw-semibold">
                            <?= e($supplierData['email'] ?? 'N/A') ?>
                            <span class="badge bg-success ms-2"><i class="fa-solid fa-check-circle"></i> Verified</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Phone Number</label>
                        <div class="fw-semibold"><?= e($supplierData['phone'] ?? 'Not provided') ?></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">Website</label>
                        <div class="fw-semibold">
                            <?php if (!empty($supplierData['website'])): ?>
                                <a href="<?= e($supplierData['website']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                    <?= e($supplierData['website']) ?> <i class="fa-solid fa-external-link-alt fa-xs"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode (hidden by default) -->
                <div id="editModeCompany" style="display: none;">
                    <form id="companyForm" onsubmit="saveCompany(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" value="<?= e($supplierData['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" value="<?= e($supplierData['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone" value="<?= e($supplierData['phone'] ?? '') ?>" placeholder="+64 21 123 4567">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" class="form-control" id="edit_website" value="<?= e($supplierData['website'] ?? '') ?>" placeholder="https://example.com">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check"></i> Save
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="cancelEditCompany()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- NZ Bank Account Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-building-columns text-secondary me-2"></i> New Zealand Bank Account</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditNZBank()">
                        <i class="fa-solid fa-pencil"></i> Edit
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- View Mode -->
                <div id="viewModeNZBank">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Bank Name</label>
                        <div class="fw-semibold" id="display_nz_bank_name">Not provided</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Account Number</label>
                        <div class="fw-semibold font-monospace" id="display_nz_account_number">Not provided</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">Account Holder Name</label>
                        <div class="fw-semibold" id="display_nz_account_holder">Not provided</div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div id="editModeNZBank" style="display: none;">
                    <form id="nzBankForm" onsubmit="saveNZBank(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                            <select class="form-select" id="nz_bank_name" required>
                                <option value="">Select Bank</option>
                                <option value="ANZ">ANZ Bank New Zealand</option>
                                <option value="ASB">ASB Bank</option>
                                <option value="BNZ">Bank of New Zealand (BNZ)</option>
                                <option value="Westpac">Westpac New Zealand</option>
                                <option value="Kiwibank">Kiwibank</option>
                                <option value="TSB">TSB Bank</option>
                                <option value="Rabobank">Rabobank New Zealand</option>
                                <option value="The Co-operative Bank">The Co-operative Bank</option>
                                <option value="HSBC">HSBC New Zealand</option>
                                <option value="SBS Bank">SBS Bank</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="nz_account_number"
                                   placeholder="12-3456-7890123-00" pattern="[0-9]{2}-[0-9]{4}-[0-9]{7}-[0-9]{2,3}"
                                   title="Format: 12-3456-7890123-00" required>
                            <small class="form-text text-muted">Format: XX-XXXX-XXXXXXX-XX</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nz_account_holder" required>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check"></i> Save
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="cancelEditNZBank()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - International Banking & Stats -->
    <div class="col-lg-6 mb-4">
        <!-- International Bank Account Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-globe text-secondary me-2"></i> International Bank Account</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditIntlBank()">
                        <i class="fa-solid fa-pencil"></i> Edit
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- View Mode -->
                <div id="viewModeIntlBank">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Bank Name</label>
                        <div class="fw-semibold" id="display_intl_bank_name">Not provided</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">SWIFT/BIC Code</label>
                        <div class="fw-semibold font-monospace" id="display_intl_swift">Not provided</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">IBAN</label>
                        <div class="fw-semibold font-monospace" id="display_intl_iban">Not provided</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Account Number</label>
                        <div class="fw-semibold font-monospace" id="display_intl_account">Not provided</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Country</label>
                        <div class="fw-semibold" id="display_intl_country">Not provided</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">Bank Address</label>
                        <div class="fw-semibold" id="display_intl_address">Not provided</div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div id="editModeIntlBank" style="display: none;">
                    <form id="intlBankForm" onsubmit="saveIntlBank(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="intl_bank_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">SWIFT/BIC Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace text-uppercase" id="intl_swift"
                                   placeholder="ABCDUS33XXX" pattern="[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?"
                                   title="8 or 11 characters: ABCDUS33XXX" maxlength="11" required>
                            <small class="form-text text-muted">8 or 11 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">IBAN</label>
                            <input type="text" class="form-control font-monospace text-uppercase" id="intl_iban"
                                   placeholder="GB82WEST12345698765432" pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}"
                                   title="Country code (2 letters) + Check digits (2 numbers) + Account number" maxlength="34">
                            <small class="form-text text-muted">Up to 34 characters (if applicable)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="intl_account" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Country <span class="text-danger">*</span></label>
                            <select class="form-select" id="intl_country" required>
                                <option value="">Select Country</option>
                                <option value="AU">Australia</option>
                                <option value="US">United States</option>
                                <option value="GB">United Kingdom</option>
                                <option value="CA">Canada</option>
                                <option value="CN">China</option>
                                <option value="JP">Japan</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                                <option value="SG">Singapore</option>
                                <option value="HK">Hong Kong</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bank Address</label>
                            <textarea class="form-control" id="intl_address" rows="2" placeholder="Street address, city, postal code"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check"></i> Save
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="cancelEditIntlBank()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Statistics Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="fa-solid fa-chart-simple text-secondary me-2"></i> Account Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="p-3">
                            <div class="display-6 fw-bold text-dark"><?= number_format((float)($stats['total_orders'] ?? 0)) ?></div>
                            <div class="small text-muted mt-1">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-4 border-start">
                        <div class="p-3">
                            <div class="display-6 fw-bold text-dark"><?= number_format((float)($stats['total_warranties'] ?? 0)) ?></div>
                            <div class="small text-muted mt-1">Warranties</div>
                        </div>
                    </div>
                    <div class="col-4 border-start">
                        <div class="p-3">
                            <div class="display-6 fw-bold text-dark"><?= number_format((float)($stats['active_products'] ?? 0)) ?></div>
                            <div class="small text-muted mt-1">Products</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Account JavaScript -->
<script src="/supplier/assets/js/account.js?v=<?php echo time(); ?>"></script>

</body>
</html>
