<?php
/**
 * HTML Footer Component - Includes footer UI and JavaScript libraries
 *
 * Provides:
 * - Professional footer bar with copyright and links
 * - Consistent JavaScript library includes
 * - Closing </body></html> tags
 *
 * Page-specific JavaScript should be included AFTER this component.
 *
 * Usage:
 *   <!-- Your page content here -->
 *         </div><!-- /.page-body -->
 *     </div><!-- /.page-wrapper -->
 * </div><!-- /.page -->
 * <?php include __DIR__ . '/components/html-footer.php'; ?>
 * <script src="/supplier/assets/js/yourpage.js"></script>
 *
 * @package SupplierPortal
 * @version 2.0.0 - Added visual footer
 */
?>

<!-- ============================================================================
     FOOTER BAR
     ========================================================================== -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-left">
            <span class="text-muted">© <?php echo date('Y'); ?> The Vape Shed. All rights reserved.</span>
        </div>
        <div class="footer-right">
            <a href="/supplier/dashboard.php" class="footer-link">Dashboard</a>
            <a href="/supplier/orders.php" class="footer-link">Orders</a>
            <a href="/supplier/warranty.php" class="footer-link">Warranty</a>
            <a href="/supplier/reports.php" class="footer-link">Reports</a>
            <a href="/supplier/downloads.php" class="footer-link">Downloads</a>
            <a href="/supplier/account.php" class="footer-link">Account</a>
        </div>
    </div>
</footer>

<!-- ============================================================================
     JAVASCRIPT LIBRARIES
     ========================================================================== -->
<!-- jQuery 3.6 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5.3 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 3.9.1 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- ============================================================================
     APPLICATION SCRIPTS (AUTO-LOADED)
     ========================================================================== -->
<?php if (function_exists('loadJS')): ?>
    <?php loadJS('assets/js'); ?>
<?php else: ?>
    <!-- Fallback loading if asset loader unavailable -->
    <script src="/supplier/assets/js/01-app.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/02-api-handler.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/03-error-handler.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/04-form-validation.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/10-orders.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/11-tracking-modal.js?v=<?php echo time(); ?>"></script>
    <script src="/supplier/assets/js/12-order-management.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<!-- SweetAlert2 for confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- NOTE: Page-specific JavaScript should be included by each page AFTER this component -->
<!-- Then close </body></html> in each page file -->
