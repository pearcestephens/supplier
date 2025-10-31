/**
 * Orders Page JavaScript
 * Extracted from tab-orders.php inline scripts
 * Handles all order management functionality
 * 
 * @package SupplierPortal
 * @version 1.0.0
 */

// =============================================================================
// CSV EXPORT
// =============================================================================

/**
 * Export orders to CSV with current filters
 */
function exportOrdersCSV() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status') || 'all';
    const outlet = urlParams.get('outlet') || 'all';
    const search = urlParams.get('search') || '';
    
    const params = new URLSearchParams({
        export: 'csv',
        status: status,
        outlet: outlet,
        search: search
    });
    
    window.location.href = '/supplier/api/export-orders.php?' + params.toString();
}

// =============================================================================
// TRACKING UPDATE
// =============================================================================

function updateTracking(orderId) {
    const trackingNumber = prompt('Enter tracking number for this shipment:');
    if (!trackingNumber) return;
    
    const carrier = prompt('Enter carrier name (e.g., FedEx, UPS, DHL, NZ Post):');
    if (!carrier) return;
    
    fetch('/supplier/api/update-tracking.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: orderId,
            tracking_number: trackingNumber,
            carrier: carrier
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tracking information updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Update failed'));
        }
    })
    .catch(error => {
        console.error('Update tracking error:', error);
        alert('Failed to update tracking: ' + error.message);
    });
}

//============================================================================
// BULK TRACKING UPDATE
// =============================================================================

function bulkUpdateTracking() {
    const csvData = prompt(
        'Paste CSV data (Order Number, Tracking Number):\n\n' +
        'Example:\n' +
        'JCE-PO-12345,TNT123456\n' +
        'JCE-PO-12346,TNT123457'
    );
    
    if (!csvData) return;
    
    const lines = csvData.trim().split('\n');
    if (lines.length === 0) {
        alert('No data provided');
        return;
    }
    
    const updates = [];
    for (const line of lines) {
        const [orderNumber, tracking] = line.split(',').map(s => s.trim());
        if (orderNumber && tracking) {
            updates.push({ order: orderNumber, tracking: tracking });
        }
    }
    
    if (updates.length === 0) {
        alert('Invalid CSV format');
        return;
    }
    
    // TODO: Send to bulk update API
    alert(`Ready to update ${updates.length} orders. API integration pending.`);
}

// =============================================================================
// ORDER STATUS UPDATE
// =============================================================================

function updateOrder(orderId) {
    const status = prompt(
        'Update order status:\n\n' +
        'Enter:\n' +
        '1 = Mark as Shipped\n' +
        '2 = Add Note\n' +
        '3 = Request More Info'
    );
    
    if (status === '1') {
        updateTracking(orderId);
    } 
    else if (status === '2') {
        const note = prompt('Enter your note:');
        if (note) {
            fetch('/supplier/api/add-order-note.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    order_id: orderId, 
                    note: note 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Note added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to add note'));
                }
            })
            .catch(error => {
                console.error('Add note error:', error);
                alert('Failed to add note: ' + error.message);
            });
        }
    } 
    else if (status === '3') {
        const message = prompt('What information do you need?');
        if (message) {
            fetch('/supplier/api/request-info.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    order_id: orderId, 
                    message: message 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Request sent to Vape Shed team!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to send request'));
                }
            })
            .catch(error => {
                console.error('Request info error:', error);
                alert('Failed to send request: ' + error.message);
            });
        }
    }
}

// =============================================================================
// AUTO-SUBMIT FILTERS
// =============================================================================

document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll(
        'select[name="per_page"], select[name="status"], select[name="outlet"]'
    );
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    console.log('Orders page JavaScript loaded');
    console.log(`Initialized ${filterSelects.length} auto-submit filters`);
});
