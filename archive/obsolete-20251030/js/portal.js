/**
 * Supplier Portal - Main JavaScript
 * 
 * @package CIS\Supplier\Assets
 * @version 2.0.0
 */

(function($) {
    "use strict";

    // ========================================================================
    // SIDEBAR TOGGLE
    // ========================================================================
    
    $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
        $("body").toggleClass("sidebar-toggled");
        $(".sidebar").toggleClass("toggled");
        
        if ($(".sidebar").hasClass("toggled")) {
            $('.sidebar .collapse').collapse('hide');
        }
    });

    // Close any open menu accordions when window is resized below 768px
    $(window).resize(function() {
        if ($(window).width() < 768) {
            $('.sidebar .collapse').collapse('hide');
        }
        
        // Toggle sidebar for tablet and mobile
        if ($(window).width() < 480 && !$(".sidebar").hasClass("toggled")) {
            $("body").addClass("sidebar-toggled");
            $(".sidebar").addClass("toggled");
            $('.sidebar .collapse').collapse('hide');
        }
    });

    // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
    $('body.fixed-nav .sidebar').on('mousewheel DOMMouseScroll wheel', function(e) {
        if ($(window).width() > 768) {
            var e0 = e.originalEvent,
                delta = e0.wheelDelta || -e0.detail;
            this.scrollTop += (delta < 0 ? 1 : -1) * 30;
            e.preventDefault();
        }
    });

    // ========================================================================
    // SCROLL TO TOP
    // ========================================================================
    
    $(document).on('scroll', function() {
        var scrollDistance = $(this).scrollTop();
        if (scrollDistance > 100) {
            $('.scroll-to-top').fadeIn();
        } else {
            $('.scroll-to-top').fadeOut();
        }
    });

    $(document).on('click', 'a.scroll-to-top', function(e) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: ($($anchor.attr('href')).offset().top)
        }, 1000, 'easeInOutExpo');
        e.preventDefault();
    });

    // ========================================================================
    // TOOLTIPS & POPOVERS
    // ========================================================================
    
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();

    // ========================================================================
    // AJAX HELPERS
    // ========================================================================
    
    /**
     * Make API request with CSRF token
     */
    window.apiRequest = function(endpoint, method, data, callback) {
        method = method || 'GET';
        data = data || {};
        
        // Add CSRF token
        if (method !== 'GET') {
            data._csrf_token = SupplierPortal.csrfToken;
        }
        
        $.ajax({
            url: SupplierPortal.apiUrl + endpoint,
            method: method,
            data: data,
            dataType: 'json',
            success: function(response) {
                if (callback) callback(null, response);
            },
            error: function(xhr, status, error) {
                console.error('API Error:', error);
                if (callback) callback(error, null);
            }
        });
    };

    /**
     * Show toast notification
     */
    window.showToast = function(message, type) {
        type = type || 'info';
        
        var alertClass = 'alert-' + type;
        var icon = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        }[type] || 'info-circle';
        
        var toast = $('<div class="alert ' + alertClass + ' alert-dismissible fade show position-fixed" role="alert" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">' +
            '<i class="fas fa-' + icon + '"></i> ' +
            '<span>' + message + '</span>' +
            '<button type="button" class="close" data-dismiss="alert">' +
            '<span>&times;</span>' +
            '</button>' +
            '</div>');
        
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * Confirm action
     */
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            if (callback) callback();
            return true;
        }
        return false;
    };

    // ========================================================================
    // DATA TABLES (if using)
    // ========================================================================
    
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            pageLength: 50,
            responsive: true,
            order: [[0, 'desc']],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search...'
            }
        });
    }

    // ========================================================================
    // AUTO-REFRESH DASHBOARD
    // ========================================================================
    
    if (SupplierPortal.currentPage === 'dashboard' && SupplierPortal.refreshInterval > 0) {
        setInterval(function() {
            // Optionally refresh dashboard data via AJAX
            console.log('Dashboard auto-refresh (implement AJAX refresh if needed)');
        }, SupplierPortal.refreshInterval);
    }

    // ========================================================================
    // SESSION ACTIVITY TRACKER
    // ========================================================================
    
    // Track user activity to keep session alive
    var activityTimeout;
    
    function resetActivityTimer() {
        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(function() {
            // Ping server to keep session alive
            $.get(SupplierPortal.baseUrl + 'ping.php');
        }, 60000); // Every minute of inactivity
    }
    
    $(document).on('mousemove keypress click', resetActivityTimer);
    resetActivityTimer();

    // ========================================================================
    // FORM VALIDATION
    // ========================================================================
    
    $('.needs-validation').on('submit', function(e) {
        if (this.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // ========================================================================
    // COPY TO CLIPBOARD
    // ========================================================================
    
    window.copyToClipboard = function(text) {
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        
        showToast('Copied to clipboard!', 'success');
    };

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    
    console.log('Supplier Portal JS loaded - v' + (SupplierPortal.version || '2.0.0'));

})(jQuery);
