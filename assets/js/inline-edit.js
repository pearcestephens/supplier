/**
 * Inline Editing System
 * Click-to-edit fields with auto-save and validation
 *
 * Usage:
 * <div class="inline-edit"
 *      data-field="company_name"
 *      data-value="Current Value"
 *      data-save-url="/supplier/api/update-account.php">
 *     Current Value
 * </div>
 */

class InlineEditor {
    constructor(element) {
        this.element = element;
        this.field = element.getAttribute('data-field');
        this.originalValue = element.getAttribute('data-value') || element.textContent.trim();
        this.saveUrl = element.getAttribute('data-save-url');
        this.type = element.getAttribute('data-type') || 'text';
        this.isEditing = false;

        this.attachEventListeners();
    }

    attachEventListeners() {
        // Click to edit
        this.element.addEventListener('click', (e) => {
            if (!this.isEditing) {
                e.preventDefault();
                this.startEdit();
            }
        });

        // Hover effect
        this.element.addEventListener('mouseenter', () => {
            if (!this.isEditing) {
                this.element.style.cursor = 'pointer';
                this.element.style.background = '#f3f4f6';
                this.element.style.borderRadius = '0.375rem';
                this.element.style.padding = '0.25rem 0.5rem';
                this.element.style.margin = '-0.25rem -0.5rem';
            }
        });

        this.element.addEventListener('mouseleave', () => {
            if (!this.isEditing) {
                this.element.style.background = '';
                this.element.style.padding = '';
                this.element.style.margin = '';
            }
        });
    }

    startEdit() {
        this.isEditing = true;
        this.element.style.background = '';
        this.element.style.padding = '';
        this.element.style.margin = '';

        // Create input based on type
        let input;
        if (this.type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 3;
        } else if (this.type === 'select') {
            input = document.createElement('select');
            const options = this.element.getAttribute('data-options').split(',');
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.trim();
                option.textContent = opt.trim();
                if (opt.trim() === this.originalValue) {
                    option.selected = true;
                }
                input.appendChild(option);
            });
        } else {
            input = document.createElement('input');
            input.type = this.type;
        }

        input.className = 'form-control form-control-sm';
        input.value = this.originalValue;
        input.style.cssText = 'display: inline-block; width: auto; min-width: 200px;';

        // Create buttons
        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'btn-group btn-group-sm ms-2';
        buttonGroup.style.cssText = 'display: inline-block; vertical-align: middle;';

        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn btn-success btn-sm';
        saveBtn.innerHTML = '<i class="fas fa-check"></i>';
        saveBtn.title = 'Save';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-secondary btn-sm';
        cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
        cancelBtn.title = 'Cancel';

        buttonGroup.appendChild(saveBtn);
        buttonGroup.appendChild(cancelBtn);

        // Replace content
        this.element.innerHTML = '';
        this.element.appendChild(input);
        this.element.appendChild(buttonGroup);

        input.focus();
        if (this.type === 'text' || this.type === 'email') {
            input.select();
        }

        // Event listeners
        saveBtn.addEventListener('click', () => {
            this.save(input.value);
        });

        cancelBtn.addEventListener('click', () => {
            this.cancel();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && this.type !== 'textarea') {
                e.preventDefault();
                this.save(input.value);
            } else if (e.key === 'Escape') {
                this.cancel();
            }
        });

        // Click outside to cancel
        setTimeout(() => {
            const clickHandler = (e) => {
                if (!this.element.contains(e.target)) {
                    this.cancel();
                    document.removeEventListener('click', clickHandler);
                }
            };
            document.addEventListener('click', clickHandler);
        }, 100);
    }

    async save(newValue) {
        if (newValue === this.originalValue) {
            this.cancel();
            return;
        }

        // Basic validation
        if (!newValue || newValue.trim() === '') {
            if (typeof showErrorToast === 'function') {
                showErrorToast('Value cannot be empty');
            } else {
                alert('Value cannot be empty');
            }
            return;
        }

        // Email validation
        if (this.type === 'email' && !this.isValidEmail(newValue)) {
            if (typeof showErrorToast === 'function') {
                showErrorToast('Please enter a valid email address');
            } else {
                alert('Please enter a valid email address');
            }
            return;
        }

        // Show loading state
        this.element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    field: this.field,
                    value: newValue
                })
            });

            const data = await response.json();

            if (data.success) {
                this.originalValue = newValue;
                this.element.textContent = newValue;
                this.element.setAttribute('data-value', newValue);
                this.isEditing = false;

                // Show success feedback
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast('Updated successfully');
                } else {
                    this.showSuccessAnimation();
                }
            } else {
                throw new Error(data.message || 'Update failed');
            }
        } catch (error) {
            console.error('Save error:', error);
            this.element.textContent = this.originalValue;
            this.isEditing = false;

            if (typeof showErrorToast === 'function') {
                showErrorToast(error.message || 'Failed to save changes');
            } else {
                alert('Failed to save changes: ' + error.message);
            }
        }
    }

    cancel() {
        this.element.textContent = this.originalValue;
        this.isEditing = false;
    }

    showSuccessAnimation() {
        this.element.style.transition = 'all 0.3s';
        this.element.style.background = '#d1fae5';
        setTimeout(() => {
            this.element.style.background = '';
        }, 1000);
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.inline-edit').forEach(element => {
        new InlineEditor(element);
    });
});

// Helper function to add inline edit capability programmatically
function makeInlineEditable(selector, field, saveUrl, type = 'text') {
    document.querySelectorAll(selector).forEach(element => {
        element.classList.add('inline-edit');
        element.setAttribute('data-field', field);
        element.setAttribute('data-value', element.textContent.trim());
        element.setAttribute('data-save-url', saveUrl);
        element.setAttribute('data-type', type);
        new InlineEditor(element);
    });
}
