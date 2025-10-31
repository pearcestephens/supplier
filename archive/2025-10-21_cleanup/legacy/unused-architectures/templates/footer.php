<?php
/**
 * Supplier Portal - Footer Template
 * 
 * @package CIS\Supplier\Templates
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}
?>

<!-- Footer -->
<footer class="sticky-footer bg-white">
    <div class="container my-auto">
        <div class="copyright text-center my-auto">
            <div>
                <span>Copyright &copy; The Vape Shed <?= date('Y') ?> - Supplier Portal v<?= PORTAL_VERSION ?></span>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <i class="fas fa-shield-alt"></i> Secure Connection |
                    <i class="fas fa-lock"></i> Your data is protected |
                    <a href="mailto:<?= EMAIL_ADMIN ?>">Contact Support</a>
                </small>
            </div>
        </div>
    </div>
</footer>
<!-- End of Footer -->

<!-- Bootstrap core JavaScript-->
<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.bundle.min.js"></script>

<!-- Custom scripts -->
<script src="<?= JS_URL ?>portal.js"></script>
<script src="<?= JS_URL ?>components.js"></script>
<?php if (FEATURE_NEURO_AI): ?>
<script src="<?= JS_URL ?>neuro-ai.js"></script>
<?php endif; ?>

<!-- Initialize portal -->
<script>
    // Portal configuration
    const SupplierPortal = {
        baseUrl: '<?= BASE_URL ?>',
        apiUrl: '<?= API_URL ?>',
        csrfToken: '<?= generate_csrf_token() ?>',
        supplierId: '<?= get_supplier_id() ?>',
        currentPage: '<?= get_param('page', 'dashboard') ?>',
        features: {
            neuroAI: <?= FEATURE_NEURO_AI ? 'true' : 'false' ?>,
            bulkDownloads: <?= FEATURE_BULK_DOWNLOADS ? 'true' : 'false' ?>,
        },
        refreshInterval: <?= DASHBOARD_REFRESH_INTERVAL * 1000 ?>, // Convert to ms
    };
    
    // Session timeout warning
    <?php if (SESSION_TIMEOUT > 300): ?>
    setTimeout(function() {
        if (confirm('Your session is about to expire. Click OK to stay logged in.')) {
            // Ping server to refresh session
            $.get('<?= BASE_URL ?>');
        }
    }, <?= (SESSION_TIMEOUT - 300) * 1000 ?>); // 5 minutes before timeout
    <?php endif; ?>
</script>

<?php if (SHOW_DEBUG_INFO): ?>
<!-- Debug Info -->
<script>
    console.log('Supplier Portal Debug Info:', {
        version: '<?= PORTAL_VERSION ?>',
        supplier: <?= json_encode($supplier) ?>,
        page: '<?= get_param('page', 'dashboard') ?>',
        session: {
            timeout: <?= SESSION_TIMEOUT ?>,
            lastActivity: <?= $supplier['last_activity'] ?? 0 ?>,
        }
    });
</script>
<?php endif; ?>
