<?php
/**
 * Supplier Portal - Product Catalog
 *
 * Modern card-based product catalog with advanced filtering, search,
 * stock indicators, and bulk actions
 *
 * @package Supplier
 * @version 2.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Require authentication
Auth::check() or Auth::redirect();

$supplier = Auth::getSupplier();
$pageTitle = 'Product Catalog';

include __DIR__ . '/includes/header.php';
?>

<style>
/* Product Catalog Styles */
.catalog-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.catalog-header h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 700;
}

.catalog-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.filters-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.search-box {
    position: relative;
}

.search-box input {
    padding-left: 2.75rem;
    height: 48px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.search-box input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 1.2rem;
}

.filter-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select {
    min-width: 200px;
    height: 48px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    padding: 0 1rem;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: #f5f5f5;
    padding: 0.25rem;
    border-radius: 8px;
}

.view-toggle button {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle button.active {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.view-toggle button i {
    font-size: 1.2rem;
    color: #666;
}

.view-toggle button.active i {
    color: #667eea;
}

.bulk-actions {
    background: #fff9e6;
    border: 2px solid #ffcc00;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: none;
}

.bulk-actions.active {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.bulk-actions-label {
    font-weight: 600;
    color: #856404;
}

.bulk-actions .btn {
    margin-left: auto;
}

/* Grid View */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.product-card-checkbox {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 10;
    width: 24px;
    height: 24px;
    cursor: pointer;
}

.product-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: rgba(0, 0, 0, 0.1);
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    padding: 1.25rem;
}

.product-sku {
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.product-name {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.75rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-item {
    text-align: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-label {
    font-size: 0.7rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #333;
}

.stat-value.success { color: #28a745; }
.stat-value.warning { color: #ffc107; }
.stat-value.danger { color: #dc3545; }

.stock-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.stock-indicator.in-stock {
    background: #d4edda;
    color: #155724;
}

.stock-indicator.low-stock {
    background: #fff3cd;
    color: #856404;
}

.stock-indicator.out-of-stock {
    background: #f8d7da;
    color: #721c24;
}

.stock-indicator i {
    font-size: 1rem;
}

/* List View */
.products-list {
    display: none;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.products-list.active {
    display: block;
}

.products-grid.hidden {
    display: none;
}

.products-table {
    width: 100%;
    margin: 0;
}

.products-table thead {
    background: #f8f9fa;
}

.products-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #666;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e0e0e0;
}

.products-table td {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}

.products-table tbody tr:hover {
    background: #f8f9fa;
}

.product-mini-image {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-mini-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.pagination {
    display: flex;
    gap: 0.5rem;
    margin: 0;
}

.pagination .page-link {
    border-radius: 6px;
    border: 2px solid #e0e0e0;
    padding: 0.5rem 1rem;
    color: #666;
    transition: all 0.3s ease;
}

.pagination .page-link:hover {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.pagination .page-item.active .page-link {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: white;
    border: 2px solid #667eea;
    color: #667eea;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.export-btn:hover {
    background: #667eea;
    color: white;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    display: none;
}

.loading-overlay.active {
    display: flex;
}

.loading-spinner {
    width: 64px;
    height: 64px;
    border: 4px solid #f0f0f0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
    margin-bottom: 1.5rem;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="catalog-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><i class="bi bi-box-seam"></i> Product Catalog</h1>
                <p id="catalog-subtitle">Loading products...</p>
            </div>
            <button class="export-btn" id="export-btn">
                <i class="bi bi-download"></i> Export Catalog
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        class="form-control"
                        id="search-input"
                        placeholder="Search products by name or SKU..."
                    >
                </div>
            </div>
            <div class="col-md-6">
                <div class="filter-group">
                    <select class="filter-select" id="stock-filter">
                        <option value="">All Stock Levels</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                    <select class="filter-select" id="store-filter">
                        <option value="">All Stores</option>
                        <!-- Populated dynamically -->
                    </select>
                    <select class="filter-select" id="sort-filter">
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="stock-asc">Stock (Low to High)</option>
                        <option value="stock-desc">Stock (High to Low)</option>
                        <option value="sku-asc">SKU (A-Z)</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="view-toggle">
                    <button class="active" data-view="grid" title="Grid View">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <button data-view="list" title="List View">
                        <i class="bi bi-list-ul"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="bulk-actions" id="bulk-actions">
        <input type="checkbox" id="select-all-checkbox" style="width: 20px; height: 20px;">
        <span class="bulk-actions-label">
            <span id="selected-count">0</span> products selected
        </span>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" id="bulk-export-btn">
                <i class="bi bi-download"></i> Export Selected
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="clear-selection-btn">
                <i class="bi bi-x-circle"></i> Clear Selection
            </button>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid" id="products-grid">
        <!-- Populated by JavaScript -->
    </div>

    <!-- Products List -->
    <div class="products-list" id="products-list">
        <table class="products-table">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="select-all-list"></th>
                    <th width="60"></th>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Total Stock</th>
                    <th>In Stock</th>
                    <th>Low Stock</th>
                    <th>Out of Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="products-list-body">
                <!-- Populated by JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div class="empty-state" id="empty-state" style="display: none;">
        <i class="bi bi-inbox"></i>
        <h3>No Products Found</h3>
        <p>Try adjusting your search or filters</p>
        <button class="btn btn-primary" id="clear-filters-btn">
            <i class="bi bi-arrow-counterclockwise"></i> Clear All Filters
        </button>
    </div>

    <!-- Pagination -->
    <div class="pagination-container" id="pagination-container" style="display: none;">
        <div class="pagination-info">
            Showing <strong id="showing-start">0</strong> to <strong id="showing-end">0</strong> of <strong id="total-products">0</strong> products
        </div>
        <nav>
            <ul class="pagination" id="pagination">
                <!-- Populated by JavaScript -->
            </ul>
        </nav>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-spinner"></div>
</div>

<script>
// Product Catalog Manager
const ProductCatalog = {
    products: [],
    filteredProducts: [],
    selectedProducts: new Set(),
    currentView: 'grid',
    currentPage: 1,
    itemsPerPage: 24,
    filters: {
        search: '',
        stock: '',
        store: '',
        sort: 'name-asc'
    },

    init() {
        this.bindEvents();
        this.loadStores();
        this.loadProducts();
    },

    bindEvents() {
        // Search
        document.getElementById('search-input').addEventListener('input', (e) => {
            this.filters.search = e.target.value.toLowerCase();
            this.applyFilters();
        });

        // Filters
        document.getElementById('stock-filter').addEventListener('change', (e) => {
            this.filters.stock = e.target.value;
            this.applyFilters();
        });

        document.getElementById('store-filter').addEventListener('change', (e) => {
            this.filters.store = e.target.value;
            this.applyFilters();
        });

        document.getElementById('sort-filter').addEventListener('change', (e) => {
            this.filters.sort = e.target.value;
            this.applyFilters();
        });

        // View toggle
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.currentTarget.dataset.view;
                this.switchView(view);
            });
        });

        // Select all
        document.getElementById('select-all-checkbox').addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });

        document.getElementById('select-all-list')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });

        // Bulk actions
        document.getElementById('bulk-export-btn').addEventListener('click', () => {
            this.exportSelected();
        });

        document.getElementById('clear-selection-btn').addEventListener('click', () => {
            this.clearSelection();
        });

        // Export
        document.getElementById('export-btn').addEventListener('click', () => {
            this.exportAll();
        });

        // Clear filters
        document.getElementById('clear-filters-btn').addEventListener('click', () => {
            this.clearFilters();
        });
    },

    async loadStores() {
        try {
            const response = await API.call('get-stores');
            if (response.success && response.data.stores) {
                const select = document.getElementById('store-filter');
                response.data.stores.forEach(store => {
                    const option = document.createElement('option');
                    option.value = store.id;
                    option.textContent = store.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load stores:', error);
        }
    },

    async loadProducts() {
        this.showLoading(true);
        try {
            const response = await API.call('get-products');
            if (response.success && response.data.products) {
                this.products = response.data.products;
                this.applyFilters();
                this.updateSubtitle();
            } else {
                this.showError('Failed to load products');
            }
        } catch (error) {
            console.error('Failed to load products:', error);
            this.showError('Failed to load products');
        } finally {
            this.showLoading(false);
        }
    },

    applyFilters() {
        let filtered = [...this.products];

        // Search filter
        if (this.filters.search) {
            filtered = filtered.filter(p =>
                p.name.toLowerCase().includes(this.filters.search) ||
                (p.sku && p.sku.toLowerCase().includes(this.filters.search))
            );
        }

        // Stock filter
        if (this.filters.stock) {
            filtered = filtered.filter(p => {
                const total = p.total_stock || 0;
                const reorder = p.reorder_point || 10;

                if (this.filters.stock === 'in-stock') return total > reorder;
                if (this.filters.stock === 'low-stock') return total > 0 && total <= reorder;
                if (this.filters.stock === 'out-of-stock') return total === 0;
                return true;
            });
        }

        // Store filter
        if (this.filters.store) {
            filtered = filtered.filter(p => {
                return p.store_stocks && p.store_stocks.some(s => s.outlet_id === this.filters.store);
            });
        }

        // Sort
        filtered.sort((a, b) => {
            switch (this.filters.sort) {
                case 'name-asc':
                    return a.name.localeCompare(b.name);
                case 'name-desc':
                    return b.name.localeCompare(a.name);
                case 'stock-asc':
                    return (a.total_stock || 0) - (b.total_stock || 0);
                case 'stock-desc':
                    return (b.total_stock || 0) - (a.total_stock || 0);
                case 'sku-asc':
                    return (a.sku || '').localeCompare(b.sku || '');
                default:
                    return 0;
            }
        });

        this.filteredProducts = filtered;
        this.currentPage = 1;
        this.render();
    },

    render() {
        if (this.filteredProducts.length === 0) {
            this.showEmptyState();
            return;
        }

        this.hideEmptyState();

        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const pageProducts = this.filteredProducts.slice(start, end);

        if (this.currentView === 'grid') {
            this.renderGrid(pageProducts);
        } else {
            this.renderList(pageProducts);
        }

        this.renderPagination();
    },

    renderGrid(products) {
        const grid = document.getElementById('products-grid');
        grid.innerHTML = products.map(product => this.createProductCard(product)).join('');

        // Bind checkbox events
        grid.querySelectorAll('.product-card-checkbox').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const productId = e.target.dataset.productId;
                if (e.target.checked) {
                    this.selectedProducts.add(productId);
                } else {
                    this.selectedProducts.delete(productId);
                }
                this.updateBulkActionsBar();
            });
        });
    },

    renderList(products) {
        const tbody = document.getElementById('products-list-body');
        tbody.innerHTML = products.map(product => this.createProductRow(product)).join('');

        // Bind checkbox events
        tbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const productId = e.target.dataset.productId;
                if (e.target.checked) {
                    this.selectedProducts.add(productId);
                } else {
                    this.selectedProducts.delete(productId);
                }
                this.updateBulkActionsBar();
            });
        });
    },

    createProductCard(product) {
        const stockStatus = this.getStockStatus(product.total_stock, product.reorder_point);
        const inStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count > (s.reorder_point || 10)).length : 0;
        const lowStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count > 0 && s.count <= (s.reorder_point || 10)).length : 0;
        const outStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count === 0).length : 0;

        return `
            <div class="product-card">
                <input
                    type="checkbox"
                    class="product-card-checkbox"
                    data-product-id="${product.id}"
                    ${this.selectedProducts.has(product.id) ? 'checked' : ''}
                >
                <div class="product-image">
                    ${product.image_url ?
                        `<img src="${product.image_url}" alt="${this.escapeHtml(product.name)}">` :
                        '<i class="bi bi-box-seam"></i>'
                    }
                </div>
                <div class="product-info">
                    <div class="product-sku">${product.sku || 'NO-SKU'}</div>
                    <div class="product-name">${this.escapeHtml(product.name)}</div>

                    <div class="product-stats">
                        <div class="stat-item">
                            <div class="stat-label">Total</div>
                            <div class="stat-value ${stockStatus.class}">${product.total_stock || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Stores</div>
                            <div class="stat-value">${product.store_count || 0}</div>
                        </div>
                    </div>

                    <div class="stock-indicator ${stockStatus.indicator}">
                        <i class="bi ${stockStatus.icon}"></i>
                        ${stockStatus.text}
                    </div>
                </div>
            </div>
        `;
    },

    createProductRow(product) {
        const stockStatus = this.getStockStatus(product.total_stock, product.reorder_point);
        const inStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count > (s.reorder_point || 10)).length : 0;
        const lowStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count > 0 && s.count <= (s.reorder_point || 10)).length : 0;
        const outStockCount = product.store_stocks ? product.store_stocks.filter(s => s.count === 0).length : 0;

        return `
            <tr>
                <td>
                    <input
                        type="checkbox"
                        data-product-id="${product.id}"
                        ${this.selectedProducts.has(product.id) ? 'checked' : ''}
                    >
                </td>
                <td>
                    <div class="product-mini-image">
                        ${product.image_url ?
                            `<img src="${product.image_url}" alt="${this.escapeHtml(product.name)}">` :
                            '<i class="bi bi-box-seam"></i>'
                        }
                    </div>
                </td>
                <td><strong>${this.escapeHtml(product.name)}</strong></td>
                <td><code>${product.sku || 'NO-SKU'}</code></td>
                <td><strong>${product.total_stock || 0}</strong></td>
                <td><span class="badge bg-success">${inStockCount}</span></td>
                <td><span class="badge bg-warning">${lowStockCount}</span></td>
                <td><span class="badge bg-danger">${outStockCount}</span></td>
                <td>
                    <span class="stock-indicator ${stockStatus.indicator}">
                        <i class="bi ${stockStatus.icon}"></i>
                        ${stockStatus.text}
                    </span>
                </td>
            </tr>
        `;
    },

    getStockStatus(stock, reorderPoint) {
        const total = stock || 0;
        const reorder = reorderPoint || 10;

        if (total === 0) {
            return {
                indicator: 'out-of-stock',
                icon: 'bi-x-circle-fill',
                text: 'Out of Stock',
                class: 'danger'
            };
        } else if (total <= reorder) {
            return {
                indicator: 'low-stock',
                icon: 'bi-exclamation-triangle-fill',
                text: 'Low Stock',
                class: 'warning'
            };
        } else {
            return {
                indicator: 'in-stock',
                icon: 'bi-check-circle-fill',
                text: 'In Stock',
                class: 'success'
            };
        }
    },

    renderPagination() {
        const totalPages = Math.ceil(this.filteredProducts.length / this.itemsPerPage);
        const start = (this.currentPage - 1) * this.itemsPerPage + 1;
        const end = Math.min(this.currentPage * this.itemsPerPage, this.filteredProducts.length);

        document.getElementById('showing-start').textContent = start;
        document.getElementById('showing-end').textContent = end;
        document.getElementById('total-products').textContent = this.filteredProducts.length;

        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a>`;
        if (this.currentPage > 1) {
            prevLi.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage--;
                this.render();
            });
        }
        pagination.appendChild(prevLi);

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === this.currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = i;
                this.render();
            });
            pagination.appendChild(li);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${this.currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a>`;
        if (this.currentPage < totalPages) {
            nextLi.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage++;
                this.render();
            });
        }
        pagination.appendChild(nextLi);

        document.getElementById('pagination-container').style.display = totalPages > 1 ? 'flex' : 'none';
    },

    switchView(view) {
        this.currentView = view;

        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        const grid = document.getElementById('products-grid');
        const list = document.getElementById('products-list');

        if (view === 'grid') {
            grid.classList.remove('hidden');
            list.classList.remove('active');
        } else {
            grid.classList.add('hidden');
            list.classList.add('active');
        }

        this.render();
    },

    toggleSelectAll(checked) {
        this.selectedProducts.clear();
        if (checked) {
            this.filteredProducts.forEach(p => this.selectedProducts.add(p.id));
        }
        this.render();
        this.updateBulkActionsBar();
    },

    clearSelection() {
        this.selectedProducts.clear();
        document.getElementById('select-all-checkbox').checked = false;
        document.getElementById('select-all-list').checked = false;
        this.updateBulkActionsBar();
        this.render();
    },

    updateBulkActionsBar() {
        const bar = document.getElementById('bulk-actions');
        const count = this.selectedProducts.size;

        if (count > 0) {
            bar.classList.add('active');
            document.getElementById('selected-count').textContent = count;
        } else {
            bar.classList.remove('active');
        }
    },

    async exportAll() {
        this.showLoading(true);
        try {
            const response = await API.call('export-products', {
                product_ids: this.filteredProducts.map(p => p.id)
            });

            if (response.success && response.data.download_url) {
                window.location.href = response.data.download_url;
            } else {
                alert('Export failed. Please try again.');
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        } finally {
            this.showLoading(false);
        }
    },

    async exportSelected() {
        if (this.selectedProducts.size === 0) {
            alert('Please select products to export');
            return;
        }

        this.showLoading(true);
        try {
            const response = await API.call('export-products', {
                product_ids: Array.from(this.selectedProducts)
            });

            if (response.success && response.data.download_url) {
                window.location.href = response.data.download_url;
            } else {
                alert('Export failed. Please try again.');
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        } finally {
            this.showLoading(false);
        }
    },

    clearFilters() {
        this.filters = {
            search: '',
            stock: '',
            store: '',
            sort: 'name-asc'
        };

        document.getElementById('search-input').value = '';
        document.getElementById('stock-filter').value = '';
        document.getElementById('store-filter').value = '';
        document.getElementById('sort-filter').value = 'name-asc';

        this.applyFilters();
    },

    updateSubtitle() {
        const total = this.products.length;
        const inStock = this.products.filter(p => (p.total_stock || 0) > (p.reorder_point || 10)).length;
        const lowStock = this.products.filter(p => {
            const stock = p.total_stock || 0;
            const reorder = p.reorder_point || 10;
            return stock > 0 && stock <= reorder;
        }).length;
        const outStock = this.products.filter(p => (p.total_stock || 0) === 0).length;

        document.getElementById('catalog-subtitle').textContent =
            `${total} products • ${inStock} in stock • ${lowStock} low • ${outStock} out`;
    },

    showEmptyState() {
        document.getElementById('empty-state').style.display = 'block';
        document.getElementById('products-grid').style.display = 'none';
        document.getElementById('products-list').style.display = 'none';
        document.getElementById('pagination-container').style.display = 'none';
    },

    hideEmptyState() {
        document.getElementById('empty-state').style.display = 'none';
        document.getElementById('products-grid').style.display = this.currentView === 'grid' ? 'grid' : 'none';
        document.getElementById('products-list').style.display = this.currentView === 'list' ? 'block' : 'none';
    },

    showLoading(show) {
        document.getElementById('loading-overlay').classList.toggle('active', show);
    },

    showError(message) {
        alert(message); // Could be improved with toast notifications
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    ProductCatalog.init();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
