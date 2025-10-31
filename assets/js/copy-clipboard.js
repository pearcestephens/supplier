/**
 * Copy to Clipboard Utility
 * One-click copy with visual feedback
 *
 * Usage:
 * <button onclick="copyToClipboard('TEXT123', 'Tracking number')">
 *     <i class="fas fa-copy"></i> Copy
 * </button>
 */

function copyToClipboard(text, label = 'Text') {
    // Use modern Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => {
                showSuccessToast(`${label} copied to clipboard`);

                // Visual feedback on button if it exists
                const activeButton = document.activeElement;
                if (activeButton && activeButton.tagName === 'BUTTON') {
                    const originalHTML = activeButton.innerHTML;
                    activeButton.innerHTML = '<i class="fas fa-check text-success"></i> Copied!';
                    activeButton.classList.add('copy-feedback');

                    setTimeout(() => {
                        activeButton.innerHTML = originalHTML;
                        activeButton.classList.remove('copy-feedback');
                    }, 2000);
                }
            })
            .catch(err => {
                console.error('Copy failed:', err);
                fallbackCopy(text, label);
            });
    } else {
        // Fallback for older browsers
        fallbackCopy(text, label);
    }
}

// Fallback copy method for older browsers
function fallbackCopy(text, label = 'Text') {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        document.execCommand('copy');
        showSuccessToast(`${label} copied to clipboard`);
    } catch (err) {
        showErrorToast('Failed to copy to clipboard');
    }

    document.body.removeChild(textarea);
}

// Copy table cell content
function copyTableCell(element) {
    const text = element.textContent.trim();
    const label = element.closest('td').getAttribute('data-label') || 'Content';
    copyToClipboard(text, label);
}

// Add copy buttons to specific elements
function addCopyButtons(selector, label) {
    document.querySelectorAll(selector).forEach(element => {
        if (element.querySelector('.copy-btn')) return; // Already has button

        const copyBtn = document.createElement('button');
        copyBtn.className = 'btn btn-sm btn-outline-secondary copy-btn ms-2';
        copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
        copyBtn.title = `Copy ${label}`;
        copyBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            const text = element.textContent.trim();
            copyToClipboard(text, label);
        };

        element.style.position = 'relative';
        element.appendChild(copyBtn);
    });
}

// Auto-add copy buttons on page load (examples)
document.addEventListener('DOMContentLoaded', function() {
    // Add copy buttons to tracking numbers
    addCopyButtons('[data-copyable="tracking"]', 'Tracking number');

    // Add copy buttons to order numbers
    addCopyButtons('[data-copyable="order"]', 'Order number');

    // Add copy buttons to serial numbers
    addCopyButtons('[data-copyable="serial"]', 'Serial number');
});
