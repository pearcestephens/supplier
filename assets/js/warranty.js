/**
 * Warranty Claims Management JavaScript
 * Supplier Portal - Warranty Page
 *
 * Functions:
 * - acceptClaim(faultID) - Accept warranty claim with resolution notes
 * - declineClaim(faultID) - Decline warranty claim with reason
 * - viewMediaLightbox(filename) - View uploaded media in lightbox
 * - exportWarrantyClaims() - Export warranty claims to CSV
 */

function acceptClaim(faultID) {
    const resolution = prompt('Enter resolution notes (e.g., "Replacement sent", "Refund issued"):');

    if (!resolution || resolution.trim() === '') {
        alert('Resolution notes are required.');
        return;
    }

    if (!confirm(`Accept warranty claim #${faultID}?\n\nResolution: "${resolution}"`)) {
        return;
    }

    // Show loading
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    $.ajax({
        url: '/supplier/api/warranty-action.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'accept',
            fault_id: parseInt(faultID),
            resolution: resolution.trim()
        }),
        success: function(response) {
            alert('✅ Claim accepted successfully!\n\nThe store will be notified of your decision.');
            location.reload();
        },
        error: function(xhr) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            const error = xhr.responseJSON?.error || 'Unknown error occurred';
            alert('❌ Error accepting claim:\n\n' + error);
        }
    });
}

function declineClaim(faultID) {
    const reason = prompt('Enter decline reason (e.g., "Physical damage", "Out of warranty period", "Customer misuse"):');

    if (!reason || reason.trim() === '') {
        alert('Decline reason is required.');
        return;
    }

    if (!confirm(`Decline warranty claim #${faultID}?\n\nReason: "${reason}"`)) {
        return;
    }

    // Show loading
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    $.ajax({
        url: '/supplier/api/warranty-action.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'decline',
            fault_id: parseInt(faultID),
            reason: reason.trim()
        }),
        success: function(response) {
            alert('✅ Claim declined successfully!\n\nThe store will be notified of your decision.');
            location.reload();
        },
        error: function(xhr) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            const error = xhr.responseJSON?.error || 'Unknown error occurred';
            alert('❌ Error declining claim:\n\n' + error);
        }
    });
}

function viewMediaLightbox(filename) {
    const url = `/supplier/api/download-media.php?file=${encodeURIComponent(filename)}`;
    $('#lightboxImage').attr('src', url);
    $('#mediaLightbox').modal('show');
}

function exportWarrantyClaims() {
    window.location.href = '/supplier/api/export-warranty-claims.php';
}

console.log('✅ Warranty.js loaded');
