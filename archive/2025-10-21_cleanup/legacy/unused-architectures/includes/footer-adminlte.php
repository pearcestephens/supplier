    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer no-print">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="https://www.vapeshed.co.nz">The Vape Shed</a>.</strong>
        All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 2.0
        </div>
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    $('.alert:not(.alert-permanent)').fadeOut('slow');
}, 5000);

// Confirm actions
$('.confirm-action').on('click', function(e) {
    if (!confirm('Are you sure you want to proceed?')) {
        e.preventDefault();
        return false;
    }
});

// Print functionality
$('.btn-print').on('click', function(e) {
    e.preventDefault();
    window.print();
});
</script>

</body>
</html>
