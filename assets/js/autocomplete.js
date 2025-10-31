/**
 * Search Autocomplete System
 * Real-time search suggestions with keyboard navigation
 *
 * Usage:
 * <input type="text"
 *        id="search-orders"
 *        data-autocomplete-url="/supplier/api/search-orders.php"
 *        data-autocomplete-min="2"
 *        placeholder="Search orders...">
 */

class AutocompleteSearch {
    constructor(input) {
        this.input = input;
        this.url = input.getAttribute('data-autocomplete-url');
        this.minChars = parseInt(input.getAttribute('data-autocomplete-min')) || 2;
        this.timeout = null;
        this.selectedIndex = -1;

        this.createSuggestionsContainer();
        this.attachEventListeners();
    }

    createSuggestionsContainer() {
        this.container = document.createElement('div');
        this.container.className = 'autocomplete-suggestions';
        this.container.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        `;

        // Wrap input in relative container
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        this.input.parentNode.insertBefore(wrapper, this.input);
        wrapper.appendChild(this.input);
        wrapper.appendChild(this.container);
    }

    attachEventListeners() {
        this.input.addEventListener('input', () => {
            clearTimeout(this.timeout);
            const query = this.input.value.trim();

            if (query.length < this.minChars) {
                this.hideSuggestions();
                return;
            }

            this.timeout = setTimeout(() => {
                this.fetchSuggestions(query);
            }, 300);
        });

        this.input.addEventListener('keydown', (e) => {
            this.handleKeyboard(e);
        });

        this.input.addEventListener('blur', () => {
            setTimeout(() => this.hideSuggestions(), 200);
        });

        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target) && e.target !== this.input) {
                this.hideSuggestions();
            }
        });
    }

    async fetchSuggestions(query) {
        try {
            const response = await fetch(`${this.url}?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.results.length > 0) {
                this.displaySuggestions(data.results);
            } else {
                this.hideSuggestions();
            }
        } catch (error) {
            console.error('Autocomplete error:', error);
            this.hideSuggestions();
        }
    }

    displaySuggestions(results) {
        this.container.innerHTML = '';
        this.selectedIndex = -1;

        results.forEach((result, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.style.cssText = `
                padding: 0.75rem 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                transition: background 0.2s;
            `;

            // Icon based on type
            const iconClass = result.type === 'order' ? 'fa-shopping-cart' :
                            result.type === 'product' ? 'fa-box' :
                            'fa-search';

            item.innerHTML = `
                <i class="fas ${iconClass} text-muted"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 600;">${this.highlightMatch(result.title, this.input.value)}</div>
                    ${result.subtitle ? `<small class="text-muted">${result.subtitle}</small>` : ''}
                </div>
                ${result.badge ? `<span class="badge bg-primary">${result.badge}</span>` : ''}
            `;

            item.addEventListener('mouseover', () => {
                this.selectItem(index);
            });

            item.addEventListener('click', () => {
                this.selectResult(result);
            });

            this.container.appendChild(item);
        });

        this.container.style.display = 'block';
    }

    highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background: #fef08a; padding: 0;">$1</mark>');
    }

    hideSuggestions() {
        this.container.style.display = 'none';
        this.selectedIndex = -1;
    }

    selectItem(index) {
        const items = this.container.querySelectorAll('.autocomplete-item');
        items.forEach((item, i) => {
            if (i === index) {
                item.style.background = '#f3f4f6';
                this.selectedIndex = index;
            } else {
                item.style.background = '';
            }
        });
    }

    handleKeyboard(e) {
        const items = this.container.querySelectorAll('.autocomplete-item');

        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = (this.selectedIndex + 1) % items.length;
                this.selectItem(this.selectedIndex);
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = this.selectedIndex <= 0 ? items.length - 1 : this.selectedIndex - 1;
                this.selectItem(this.selectedIndex);
                break;

            case 'Enter':
                if (this.selectedIndex >= 0) {
                    e.preventDefault();
                    items[this.selectedIndex].click();
                }
                break;

            case 'Escape':
                this.hideSuggestions();
                break;
        }
    }

    selectResult(result) {
        if (result.url) {
            window.location.href = result.url;
        } else if (result.callback) {
            window[result.callback](result);
        }
    }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-autocomplete-url]').forEach(input => {
        new AutocompleteSearch(input);
    });
});
