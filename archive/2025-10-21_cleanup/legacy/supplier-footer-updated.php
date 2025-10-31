            </div><!-- /.container-fluid -->
        </main><!-- /.main -->
    </div><!-- /.app-body -->

    <!-- THE VAPE SHED ADMIN FOOTER -->
    <footer class="app-footer">
        <div>
            <a href="https://www.vapeshed.co.nz" target="_blank">
                <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="The Vape Shed" style="width: 20px; height: 20px; border-radius: 4px; margin-right: 8px;">
                The Vape Shed
            </a>
            <span>&copy; <?php echo date("Y"); ?> Ecigdis Ltd - Supplier Administration Portal</span>
        </div>
        <div>
            <small>
                Version 3.0 | 
                Developed by <a href="https://www.pearcestephens.co.nz" target="_blank">Pearce Stephens</a>
            </small>
        </div>
    </footer>

    <!-- ESSENTIAL JAVASCRIPT FOR ADMIN PORTAL -->
    <!-- Popper (required by Bootstrap 4) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>

    <!-- Bootstrap 4 JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>

    <!-- Pace Loader -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>

    <!-- jQuery UI -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- THE VAPE SHED SUPPLIER PORTAL FUNCTIONALITY -->
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers  
            $('[data-toggle="popover"]').popover();
            
            // Print functionality
            window.addEventListener('beforeprint', function() {
                $('.admin-topbar, .supplier-sidebar, .app-footer, .mobile-toggle').addClass('d-print-none');
                $('.main').css('margin-left', '0');
            });
            
            window.addEventListener('afterprint', function() {
                $('.admin-topbar, .supplier-sidebar, .app-footer, .mobile-toggle').removeClass('d-print-none');
                if ($(window).width() > 768) {
                    $('.main').css('margin-left', '280px');
                }
            });
            
            // Auto-refresh time in topbar
            setInterval(function() {
                var now = new Date();
                var timeString = now.toLocaleDateString('en-NZ', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                $('.admin-info').html('<i class="fas fa-clock"></i> ' + timeString);
            }, 60000); // Update every minute
            
            // Responsive layout adjustments
            $(window).resize(function() {
                if ($(window).width() > 768) {
                    $('#supplierSidebar').removeClass('show');
                    $('#mobileToggle i').removeClass('fa-times').addClass('fa-bars');
                }
            });
            
            // Smooth page transitions
            $('a[href^="/"]').not('[target="_blank"]').click(function(e) {
                var href = $(this).attr('href');
                if (href && href !== '#' && !href.startsWith('#')) {
                    // Add loading state
                    Pace.restart();
                }
            });
        });
        
        // Enhanced notification system for The Vape Shed supplier portal
        function showSupplierNotification(message, type = 'info', persistent = false) {
            const alertClass = type === 'error' ? 'alert-danger' : 
                             type === 'success' ? 'alert-success' : 
                             type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const iconClass = type === 'error' ? 'fa-exclamation-triangle' : 
                            type === 'success' ? 'fa-check-circle' : 
                            type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const notification = $(`
                <div class="alert ${alertClass} alert-dismissible fade show supplier-notification" role="alert">
                    <div class="d-flex align-items-center">
                        <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="VS" style="width: 24px; height: 24px; border-radius: 4px; margin-right: 10px;">
                        <div>
                            <strong><i class="fas ${iconClass} mr-1"></i> The Vape Shed Supplier Portal</strong><br>
                            <span>${message}</span>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `);
            
            $('.container-fluid').first().prepend(notification);
            
            // Auto-dismiss unless persistent
            if (!persistent) {
                setTimeout(function() {
                    notification.alert('close');
                }, 6000);
            }
        }
        
        // Stats card animation for The Vape Shed branding
        $('.stats-card').hover(
            function() {
                $(this).find('.stats-number').addClass('animate__animated animate__pulse');
            },
            function() {
                $(this).find('.stats-number').removeClass('animate__animated animate__pulse');
            }
        );
        
        // The Vape Shed portal analytics (optional)
        function trackSupplierAction(action, data = {}) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    'event_category': 'supplier_portal',
                    'event_label': 'the_vape_shed',
                    'custom_map': data
                });
            }
        }
        
        // Initialize supplier portal
        $(document).ready(function() {
            // Track page view
            trackSupplierAction('page_view', {
                'page': window.location.pathname,
                'supplier_id': <?php echo $GLOBALS['supplierID'] ? $GLOBALS['supplierID'] : 'null'; ?>
            });
            
            // Add The Vape Shed branding animation
            $('.logo-img, .navbar-brand img').hover(
                function() {
                    $(this).css('transform', 'scale(1.1) rotate(5deg)');
                },
                function() {
                    $(this).css('transform', 'scale(1) rotate(0deg)');
                }
            );
        });
    </script>

    <!-- THE VAPE SHED SUPPLIER PORTAL CUSTOM STYLES -->
    <style>
        /* Additional The Vape Shed specific styles */
        .supplier-notification {
            border-left: 4px solid;
            margin-bottom: 1rem;
        }
        
        .supplier-notification.alert-success {
            border-left-color: #27ae60;
        }
        
        .supplier-notification.alert-danger {
            border-left-color: #e74c3c;
        }
        
        .supplier-notification.alert-warning {
            border-left-color: #f39c12;
        }
        
        .supplier-notification.alert-info {
            border-left-color: #3498db;
        }
        
        /* Logo animations */
        .logo-img,
        .navbar-brand img {
            transition: transform 0.3s ease;
        }
        
        /* The Vape Shed color accents */
        .vs-accent {
            color: #3498db !important;
        }
        
        .vs-primary {
            color: #2c3e50 !important;
        }
        
        .bg-vs-gradient {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 50%, #34495e 100%) !important;
        }
    </style>

    <?php
    // Optional: extra footer scripts from page
    if (isset($extraFooterScripts) && is_string($extraFooterScripts)) {
        echo $extraFooterScripts;
    }
    ?>

</body>
</html>