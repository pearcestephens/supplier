/**
 * Button Loading State Manager
 * Automatically adds loading spinners and prevents double-submission
 *
 * Usage:
 * <button class="btn btn-primary" data-loading-text="Saving...">Save</button>
 *
 * JavaScript:
 * const btn = document.querySelector('.btn');
 * setButtonLoading(btn);
 * // ... do async work ...
 * resetButton(btn);
 */

// Set button to loading state
function setButtonLoading(button, loadingText = 'Processing...') {
    if (!button) return;

    // Store original state
    button.dataset.originalText = button.innerHTML;
    button.dataset.originalDisabled = button.disabled;

    // Set loading state
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${loadingText}`;
    button.classList.add('btn-loading');

    return button;
}

// Reset button to original state
function resetButton(button, newText = null) {
    if (!button) return;

    button.disabled = button.dataset.originalDisabled === 'true';
    button.innerHTML = newText || button.dataset.originalText;
    button.classList.remove('btn-loading');

    // Cleanup
    delete button.dataset.originalText;
    delete button.dataset.originalDisabled;

    return button;
}

// Auto-attach to forms
document.addEventListener('DOMContentLoaded', function() {
    // Add loading state to form submit buttons
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('[type="submit"]');
            if (submitButton) {
                const loadingText = submitButton.dataset.loadingText || 'Submitting...';
                setButtonLoading(submitButton, loadingText);
            }
        });
    });

    // Add loading state to buttons with data-async attribute
    document.querySelectorAll('[data-async]').forEach(button => {
        button.addEventListener('click', function(e) {
            const loadingText = this.dataset.loadingText || 'Processing...';
            setButtonLoading(this, loadingText);
        });
    });
});

// Helper: Button with promise
async function buttonWithLoading(button, asyncFunction, loadingText = 'Processing...') {
    setButtonLoading(button, loadingText);

    try {
        const result = await asyncFunction();
        resetButton(button);
        return result;
    } catch (error) {
        resetButton(button);
        throw error;
    }
}

// Example usage:
/*
document.querySelector('#save-btn').addEventListener('click', async function() {
    await buttonWithLoading(this, async () => {
        const response = await fetch('/api/save', {method: 'POST'});
        const data = await response.json();

        if (data.success) {
            showSuccessToast('Saved successfully');
        } else {
            showErrorToast(data.error);
        }

        return data;
    }, 'Saving...');
});
*/
