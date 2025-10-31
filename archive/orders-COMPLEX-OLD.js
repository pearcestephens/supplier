/**
 * Orders Management JavaScript - Phase 3 Enhanced
 * 
 * Handles all client-side functionality for the enhanced orders tab
 * Integrates with API v2 endpoints for real-time operations
 * 
 * @package CIS\Supplier\Assets\JS
 * @version 3.0.0
 * @author CIS Development Team
 * @created October 23, 2025
 */

class OrdersManager {
    constructor() {
        this.apiBase = '/supplier/api/v2';
        this.currentPage = 1;
        this.currentSort = 'created_at';
        this.currentSortDir = 'desc';
        this.perPage = 25;
        this.currentFilters = {};
        this.selectedOrders = new Set();
        this.isLoading = false;
        this.viewMode = 'table'; // 'table' or 'cards'
        
        // Cached data
        this.outlets = [];
        this.lastRefresh = null;
        this.refreshInterval = null;
        
        // UI elements
        this.elements = {};
        
        console.log('OrdersManager initialized');
    }
    
    /**
     * Initialize the orders manager
     */
    async init() {
        console.log('Initializing OrdersManager...');
        
        try {
            // Cache DOM elements
            this.cacheElements();
            
            // Load outlets for filter dropdown
            await this.loadOutlets();
            
            // Load initial orders
            await this.loadOrders();
            
            // Set up event listeners
            this.setupEventListeners();
            
            // Set up auto-refresh (every 2 minutes)
            this.startAutoRefresh();
            
            console.log('OrdersManager initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize OrdersManager:', error);
            this.showError('Failed to initialize orders. Please refresh the page.');
        }
    }
    
    /**
     * Cache frequently used DOM elements
     */
    cacheElements() {
        this.elements = {
            loadingIndicator: document.getElementById('loadingIndicator'),
            tableView: document.getElementById('tableView'),
            cardsView: document.getElementById('cardsView'),
            emptyState: document.getElementById('emptyState'),
            ordersTableBody: document.getElementById('ordersTableBody'),
            ordersCardsContainer: document.getElementById('ordersCardsContainer'),
            paginationControls: document.getElementById('paginationControls'),
            tableSubtitle: document.getElementById('tableSubtitle'),
            
            // Stats elements
            totalOrdersCount: document.getElementById('totalOrdersCount'),
            totalOrdersBadge: document.getElementById('totalOrdersBadge'),
            totalValueAmount: document.getElementById('totalValueAmount'),
            activeOrdersCount: document.getElementById('activeOrdersCount'),
            orders30dCount: document.getElementById('orders30dCount'),
            avgOrderValue: document.getElementById('avgOrderValue'),
            selectedCount: document.getElementById('selectedCount'),
            selectedCountText: document.getElementById('selectedCountText'),
            
            // Filter elements
            filtersPanel: document.getElementById('filtersPanel'),
            dateFrom: document.getElementById('dateFrom'),
            dateTo: document.getElementById('dateTo'),
            statusFilter: document.getElementById('statusFilter'),
            outletFilter: document.getElementById('outletFilter'),
            valueMin: document.getElementById('valueMin'),
            valueMax: document.getElementById('valueMax'),
            searchTerm: document.getElementById('searchTerm'),
            
            // Bulk actions
            bulkActionsPanel: document.getElementById('bulkActionsPanel'),
            selectAll: document.getElementById('selectAll'),
            
            // Modals
            orderDetailsModal: document.getElementById('orderDetailsModal'),
            orderDetailsContent: document.getElementById('orderDetailsContent'),
            exportModal: document.getElementById('exportModal')
        };
    }
    
    /**
     * Load outlets for filter dropdown
     */
    async loadOutlets() {
        try {
            const response = await fetch(`${this.apiBase}/po-list.php?outlets_only=1`);
            const data = await response.json();
            
            if (data.success && data.data) {
                this.outlets = data.data;
                this.populateOutletFilter();
            }
            
        } catch (error) {
            console.error('Failed to load outlets:', error);
        }
    }
    
    /**
     * Populate outlet filter dropdown
     */
    populateOutletFilter() {
        const select = this.elements.outletFilter;
        if (!select) return;
        
        select.innerHTML = '';
        
        this.outlets.forEach(outlet => {
            const option = document.createElement('option');
            option.value = outlet.id;
            option.textContent = `${outlet.name} (${outlet.outlet_code})`;
            select.appendChild(option);
        });
        
        // Initialize multi-select if available
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(select).select2({
                placeholder: 'Select outlets...',
                allowClear: true
            });
        }
    }
    
    /**
     * Load orders with current filters and pagination
     */
    async loadOrders(showLoading = true) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        if (showLoading) {
            this.showLoading();
        }
        
        try {
            const params = this.buildApiParams();
            const response = await fetch(`${this.apiBase}/po-list.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderOrders(data.data);
                this.updateStats(data.meta);
                this.renderPagination(data.meta);
                this.updateTableSubtitle(data.meta);
                this.lastRefresh = new Date();
            } else {
                throw new Error(data.error?.message || 'Failed to load orders');
            }
            
        } catch (error) {
            console.error('Failed to load orders:', error);
            this.showError('Failed to load orders. Please try again.');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    /**
     * Build API parameters from current state
     */
    buildApiParams() {
        const params = new URLSearchParams();
        
        // Pagination
        params.append('page', this.currentPage.toString());
        params.append('per_page', this.perPage.toString());
        
        // Sorting
        params.append('sort', this.currentSort);
        params.append('sort_dir', this.currentSortDir);
        
        // Filters
        Object.keys(this.currentFilters).forEach(key => {
            const value = this.currentFilters[key];
            if (value !== null && value !== undefined && value !== '') {
                if (Array.isArray(value)) {
                    value.forEach(v => params.append(`${key}[]`, v));
                } else {
                    params.append(key, value.toString());
                }
            }
        });
        
        return params.toString();
    }
    
    /**
     * Render orders in the current view mode
     */
    renderOrders(orders) {
        if (this.viewMode === 'table') {
            this.renderTableView(orders);
        } else {
            this.renderCardsView(orders);
        }
        
        // Show appropriate view
        this.elements.tableView.style.display = this.viewMode === 'table' ? 'block' : 'none';
        this.elements.cardsView.style.display = this.viewMode === 'cards' ? 'block' : 'none';
        this.elements.emptyState.style.display = orders.length === 0 ? 'block' : 'none';
    }
    
    /**
     * Render orders in table view
     */
    renderTableView(orders) {
        const tbody = this.elements.ordersTableBody;
        tbody.innerHTML = '';
        
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = this.getOrderRowHTML(order);
            tbody.appendChild(row);
        });
        
        // Update sort indicators
        this.updateSortIndicators();
    }
    
    /**
     * Render orders in cards view
     */
    renderCardsView(orders) {
        const container = this.elements.ordersCardsContainer;
        container.innerHTML = '';
        
        orders.forEach(order => {
            const colDiv = document.createElement('div');
            colDiv.className = 'col-md-6 col-lg-4 mb-3';
            colDiv.innerHTML = this.getOrderCardHTML(order);
            container.appendChild(colDiv);
        });
    }
    
    /**
     * Get HTML for order table row
     */
    getOrderRowHTML(order) {
        const statusBadge = this.getStatusBadge(order.state);
        const progressBar = this.getProgressBar(order);
        const isSelected = this.selectedOrders.has(order.id);
        
        return `
            <td>
                <input type="checkbox" class="order-checkbox" 
                       value="${order.id}" 
                       ${isSelected ? 'checked' : ''}
                       onchange="window.ordersManager.toggleOrderSelection(${order.id}, this.checked)">
            </td>
            <td>
                <strong>${this.escapeHtml(order.public_id)}</strong>
                ${order.vend_number ? `<br><small class="text-muted">${this.escapeHtml(order.vend_number)}</small>` : ''}
            </td>
            <td>
                ${this.formatDate(order.created_at)}
                ${order.expected_delivery_date ? `<br><small class="text-muted"><i class="fas fa-truck"></i> ${this.formatDate(order.expected_delivery_date, 'short')}</small>` : ''}
            </td>
            <td>
                ${this.escapeHtml(order.outlet_name || 'Unknown')}
                ${order.outlet_code ? `<br><span class="badge badge-light">${this.escapeHtml(order.outlet_code)}</span>` : ''}
            </td>
            <td class="text-center">
                <span class="badge badge-info">${order.items_count}</span>
                <br><small class="text-muted">${order.total_qty} units</small>
            </td>
            <td class="text-center">
                ${progressBar}
            </td>
            <td class="text-right">
                <strong>$${this.formatNumber(order.total_value_inc_gst)}</strong>
                <br><small class="text-muted">$${this.formatNumber(order.total_value_ex_gst)} ex GST</small>
            </td>
            <td class="text-center">${statusBadge}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="window.ordersManager.viewOrderDetails(${order.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.getActionButtons(order)}
                </div>
            </td>
        `;
    }
    
    /**
     * Get HTML for order card
     */
    getOrderCardHTML(order) {
        const statusBadge = this.getStatusBadge(order.state);
        const progressBar = this.getProgressBar(order);
        const isSelected = this.selectedOrders.has(order.id);
        
        return `
            <div class="card order-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <input type="checkbox" class="order-checkbox mr-2" 
                               value="${order.id}" 
                               ${isSelected ? 'checked' : ''}
                               onchange="window.ordersManager.toggleOrderSelection(${order.id}, this.checked)">
                        <strong>${this.escapeHtml(order.public_id)}</strong>
                    </div>
                    ${statusBadge}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <small class="text-muted">Outlet:</small><br>
                            <strong>${this.escapeHtml(order.outlet_name || 'Unknown')}</strong>
                            ${order.outlet_code ? `<br><span class="badge badge-light">${this.escapeHtml(order.outlet_code)}</span>` : ''}
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted">Created:</small><br>
                            ${this.formatDate(order.created_at)}
                            ${order.expected_delivery_date ? `<br><small class="text-muted"><i class="fas fa-truck"></i> ${this.formatDate(order.expected_delivery_date, 'short')}</small>` : ''}
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-6">
                            <small class="text-muted">Items:</small><br>
                            <span class="badge badge-info">${order.items_count}</span> items, ${order.total_qty} units
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted">Value:</small><br>
                            <strong>$${this.formatNumber(order.total_value_inc_gst)}</strong>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Progress:</small>
                        ${progressBar}
                    </div>
                </div>
                <div class="card-footer">
                    <div class="btn-group btn-group-sm w-100">
                        <button class="btn btn-outline-primary" onclick="window.ordersManager.viewOrderDetails(${order.id})">
                            <i class="fas fa-eye"></i> Details
                        </button>
                        ${this.getActionButtons(order)}
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Get status badge HTML
     */
    getStatusBadge(state) {
        const statusConfig = {
            'DRAFT': { class: 'secondary', text: 'Draft' },
            'OPEN': { class: 'primary', text: 'Open' },
            'PACKING': { class: 'info', text: 'Packing' },
            'PACKAGED': { class: 'info', text: 'Packaged' },
            'SENT': { class: 'warning', text: 'Sent' },
            'RECEIVING': { class: 'warning', text: 'Receiving' },
            'PARTIAL': { class: 'warning', text: 'Partial' },
            'RECEIVED': { class: 'success', text: 'Received' },
            'CLOSED': { class: 'success', text: 'Closed' },
            'CANCELLED': { class: 'danger', text: 'Cancelled' },
            'ARCHIVED': { class: 'dark', text: 'Archived' }
        };
        
        const config = statusConfig[state] || { class: 'secondary', text: state };
        return `<span class="badge badge-${config.class} status-badge">${config.text}</span>`;
    }
    
    /**
     * Get progress bar HTML
     */
    getProgressBar(order) {
        const progress = order.completion_percentage || 0;
        const progressClass = progress >= 100 ? 'success' : progress >= 50 ? 'warning' : 'info';
        
        return `
            <div class="progress">
                <div class="progress-bar bg-${progressClass}" 
                     style="width: ${progress}%" 
                     title="${progress}% complete">
                </div>
            </div>
            <small class="text-muted">${progress}%</small>
        `;
    }
    
    /**
     * Get action buttons based on order state
     */
    getActionButtons(order) {
        let buttons = '';
        
        if (order.state === 'OPEN' && !order.supplier_acknowledged_at) {
            buttons += `
                <button class="btn btn-success" onclick="window.ordersManager.acknowledgeOrder(${order.id})" title="Acknowledge">
                    <i class="fas fa-check"></i>
                </button>
            `;
        }
        
        if (['OPEN', 'PACKING', 'PACKAGED'].includes(order.state)) {
            buttons += `
                <button class="btn btn-warning" onclick="window.ordersManager.markOrderSent(${order.id})" title="Mark as Sent">
                    <i class="fas fa-truck"></i>
                </button>
            `;
        }
        
        buttons += `
            <button class="btn btn-outline-secondary" onclick="window.ordersManager.exportOrder(${order.id})" title="Export">
                <i class="fas fa-download"></i>
            </button>
        `;
        
        return buttons;
    }
    
    /**
     * Update stats display
     */
    updateStats(meta) {
        if (!meta) return;
        
        if (this.elements.totalOrdersCount) {
            this.elements.totalOrdersCount.textContent = this.formatNumber(meta.total || 0);
        }
        if (this.elements.totalOrdersBadge) {
            this.elements.totalOrdersBadge.textContent = this.formatNumber(meta.total || 0);
        }
        if (this.elements.totalValueAmount && meta.total_value !== undefined) {
            this.elements.totalValueAmount.textContent = '$' + this.formatNumber(meta.total_value);
        }
    }
    
    /**
     * Render pagination controls
     */
    renderPagination(meta) {
        if (!meta || !this.elements.paginationControls) return;
        
        const { current_page, last_page, total } = meta;
        
        if (last_page <= 1) {
            this.elements.paginationControls.innerHTML = '';
            return;
        }
        
        let html = '';
        
        // Previous button
        html += `
            <li class="page-item ${current_page <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.ordersManager.goToPage(${current_page - 1}); return false;">
                    Previous
                </a>
            </li>
        `;
        
        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);
        
        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="window.ordersManager.goToPage(1); return false;">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="window.ordersManager.goToPage(${i}); return false;">
                        ${i}
                    </a>
                </li>
            `;
        }
        
        if (endPage < last_page) {
            if (endPage < last_page - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" onclick="window.ordersManager.goToPage(${last_page}); return false;">${last_page}</a></li>`;
        }
        
        // Next button
        html += `
            <li class="page-item ${current_page >= last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.ordersManager.goToPage(${current_page + 1}); return false;">
                    Next
                </a>
            </li>
        `;
        
        this.elements.paginationControls.innerHTML = html;
    }
    
    /**
     * Update table subtitle
     */
    updateTableSubtitle(meta) {
        if (!meta || !this.elements.tableSubtitle) return;
        
        const { current_page, per_page, total, from, to } = meta;
        this.elements.tableSubtitle.textContent = `Showing ${from || 1} to ${to || per_page} of ${this.formatNumber(total || 0)} orders`;
    }
    
    /**
     * Show loading indicator
     */
    showLoading() {
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.style.display = 'block';
        }
        if (this.elements.tableView) {
            this.elements.tableView.style.display = 'none';
        }
        if (this.elements.cardsView) {
            this.elements.cardsView.style.display = 'none';
        }
        if (this.elements.emptyState) {
            this.elements.emptyState.style.display = 'none';
        }
    }
    
    /**
     * Hide loading indicator
     */
    hideLoading() {
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.style.display = 'none';
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        // You could implement a toast notification system here
        console.error(message);
        alert(message); // Simple fallback
    }
    
    /**
     * Go to specific page
     */
    async goToPage(page) {
        if (page < 1 || this.isLoading) return;
        
        this.currentPage = page;
        await this.loadOrders();
    }
    
    /**
     * Sort by column
     */
    async sortBy(column) {
        if (this.currentSort === column) {
            this.currentSortDir = this.currentSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort = column;
            this.currentSortDir = 'desc';
        }
        
        this.currentPage = 1; // Reset to first page
        await this.loadOrders();
    }
    
    /**
     * Update sort indicators
     */
    updateSortIndicators() {
        // Clear all indicators
        document.querySelectorAll('[id^="sort_"]').forEach(el => {
            el.className = 'fas fa-sort';
        });
        
        // Set current sort indicator
        const indicator = document.getElementById(`sort_${this.currentSort}`);
        if (indicator) {
            indicator.className = `fas fa-sort-${this.currentSortDir === 'asc' ? 'up' : 'down'}`;
        }
    }
    
    /**
     * Apply current filters
     */
    async applyFilters() {
        // Read filter values from UI
        this.currentFilters = {
            date_from: this.elements.dateFrom?.value || null,
            date_to: this.elements.dateTo?.value || null,
            states: this.getMultiSelectValues('statusFilter'),
            outlets: this.getMultiSelectValues('outletFilter'),
            value_min: this.elements.valueMin?.value || null,
            value_max: this.elements.valueMax?.value || null,
            search: this.elements.searchTerm?.value || null
        };
        
        this.currentPage = 1; // Reset to first page
        await this.loadOrders();
    }
    
    /**
     * Clear all filters
     */
    async clearFilters() {
        this.currentFilters = {};
        this.currentPage = 1;
        
        // Clear UI
        if (this.elements.dateFrom) this.elements.dateFrom.value = '';
        if (this.elements.dateTo) this.elements.dateTo.value = '';
        if (this.elements.valueMin) this.elements.valueMin.value = '';
        if (this.elements.valueMax) this.elements.valueMax.value = '';
        if (this.elements.searchTerm) this.elements.searchTerm.value = '';
        
        // Clear multi-selects
        this.clearMultiSelect('statusFilter');
        this.clearMultiSelect('outletFilter');
        
        await this.loadOrders();
    }
    
    /**
     * Get values from multi-select element
     */
    getMultiSelectValues(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return [];
        
        return Array.from(element.selectedOptions).map(option => option.value);
    }
    
    /**
     * Clear multi-select element
     */
    clearMultiSelect(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        Array.from(element.options).forEach(option => {
            option.selected = false;
        });
        
        // Trigger Select2 update if available
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(`#${elementId}`).trigger('change');
        }
    }
    
    /**
     * Toggle order selection
     */
    toggleOrderSelection(orderId, isSelected) {
        if (isSelected) {
            this.selectedOrders.add(orderId);
        } else {
            this.selectedOrders.delete(orderId);
        }
        
        this.updateSelectionUI();
    }
    
    /**
     * Toggle select all orders
     */
    toggleSelectAll(selectAll) {
        const checkboxes = document.querySelectorAll('.order-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll;
            const orderId = parseInt(checkbox.value);
            if (selectAll) {
                this.selectedOrders.add(orderId);
            } else {
                this.selectedOrders.delete(orderId);
            }
        });
        
        this.updateSelectionUI();
    }
    
    /**
     * Update selection UI elements
     */
    updateSelectionUI() {
        const count = this.selectedOrders.size;
        
        if (this.elements.selectedCount) {
            this.elements.selectedCount.textContent = count.toString();
        }
        if (this.elements.selectedCountText) {
            this.elements.selectedCountText.textContent = count.toString();
        }
        
        // Update select all checkbox
        const checkboxes = document.querySelectorAll('.order-checkbox');
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        if (this.elements.selectAll) {
            this.elements.selectAll.checked = allChecked;
            this.elements.selectAll.indeterminate = count > 0 && !allChecked;
        }
    }
    
    /**
     * View order details
     */
    async viewOrderDetails(orderId) {
        try {
            this.showOrderDetailsLoading();
            
            const response = await fetch(`${this.apiBase}/po-detail.php?id=${orderId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderOrderDetails(data.data);
                this.showOrderDetailsModal();
            } else {
                throw new Error(data.error?.message || 'Failed to load order details');
            }
            
        } catch (error) {
            console.error('Failed to load order details:', error);
            this.showError('Failed to load order details. Please try again.');
        }
    }
    
    /**
     * Render order details in modal
     */
    renderOrderDetails(orderData) {
        const { order, items, logs, timeline } = orderData;
        
        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Order Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>PO Number:</strong></td><td>${this.escapeHtml(order.public_id)}</td></tr>
                        <tr><td><strong>Status:</strong></td><td>${this.getStatusBadge(order.state)}</td></tr>
                        <tr><td><strong>Created:</strong></td><td>${this.formatDate(order.created_at)}</td></tr>
                        <tr><td><strong>Expected Delivery:</strong></td><td>${order.expected_delivery_date ? this.formatDate(order.expected_delivery_date) : 'Not set'}</td></tr>
                        <tr><td><strong>Outlet:</strong></td><td>${this.escapeHtml(order.outlet_name)} (${this.escapeHtml(order.outlet_code)})</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Summary</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Items:</strong></td><td>${order.items_count}</td></tr>
                        <tr><td><strong>Total Units:</strong></td><td>${order.total_qty}</td></tr>
                        <tr><td><strong>Total Value:</strong></td><td>$${this.formatNumber(order.total_value_inc_gst)}</td></tr>
                        <tr><td><strong>Progress:</strong></td><td>${order.completion_percentage}%</td></tr>
                    </table>
                </div>
            </div>
            
            <hr>
            
            <h6>Items (${items.length})</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th class="text-center">Requested</th>
                            <th class="text-center">Sent</th>
                            <th class="text-center">Received</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td>${this.escapeHtml(item.sku || 'N/A')}</td>
                                <td>${this.escapeHtml(item.product_name)}</td>
                                <td class="text-center">${item.qty_requested}</td>
                                <td class="text-center">${item.qty_sent_total || 0}</td>
                                <td class="text-center">${item.qty_received_total || 0}</td>
                                <td class="text-right">$${this.formatNumber(item.line_total || 0)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            ${timeline.length > 0 ? `
                <hr>
                <h6>Timeline</h6>
                <div class="timeline">
                    ${timeline.map(event => `
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">${this.escapeHtml(event.event_type)}</h6>
                                <p class="timeline-description">${this.escapeHtml(event.description || '')}</p>
                                <small class="text-muted">${this.formatDate(event.created_at)}</small>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        `;
        
        if (this.elements.orderDetailsContent) {
            this.elements.orderDetailsContent.innerHTML = html;
        }
    }
    
    /**
     * Show order details modal
     */
    showOrderDetailsModal() {
        if (typeof bootstrap !== 'undefined' && this.elements.orderDetailsModal) {
            new bootstrap.Modal(this.elements.orderDetailsModal).show();
        }
    }
    
    /**
     * Show loading in order details modal
     */
    showOrderDetailsLoading() {
        if (this.elements.orderDetailsContent) {
            this.elements.orderDetailsContent.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="mt-2">Loading order details...</div>
                </div>
            `;
        }
    }
    
    /**
     * Acknowledge order
     */
    async acknowledgeOrder(orderId) {
        if (!confirm('Acknowledge this purchase order?')) return;
        
        try {
            const response = await fetch(`${this.apiBase}/po-update.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    action: 'acknowledge'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Order acknowledged successfully');
                await this.loadOrders(false); // Refresh without loading indicator
            } else {
                throw new Error(data.error?.message || 'Failed to acknowledge order');
            }
            
        } catch (error) {
            console.error('Failed to acknowledge order:', error);
            this.showError('Failed to acknowledge order. Please try again.');
        }
    }
    
    /**
     * Mark order as sent
     */
    async markOrderSent(orderId) {
        if (!confirm('Mark this order as sent?')) return;
        
        try {
            const response = await fetch(`${this.apiBase}/po-update.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    action: 'mark_sent'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Order marked as sent');
                await this.loadOrders(false);
            } else {
                throw new Error(data.error?.message || 'Failed to mark order as sent');
            }
            
        } catch (error) {
            console.error('Failed to mark order as sent:', error);
            this.showError('Failed to mark order as sent. Please try again.');
        }
    }
    
    /**
     * Export single order
     */
    async exportOrder(orderId) {
        try {
            const url = `${this.apiBase}/po-export.php?po_ids=${orderId}&format=csv&type=detailed`;
            window.open(url, '_blank');
        } catch (error) {
            console.error('Failed to export order:', error);
            this.showError('Failed to export order. Please try again.');
        }
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        // You could implement a toast notification system here
        console.log(message);
        // Simple fallback - you might want to implement a better notification system
        if (window.toastr) {
            toastr.success(message);
        } else {
            alert(message);
        }
    }
    
    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        // Refresh every 2 minutes
        this.refreshInterval = setInterval(() => {
            if (!this.isLoading) {
                this.loadOrders(false); // Refresh without loading indicator
            }
        }, 120000);
    }
    
    /**
     * Refresh orders manually
     */
    async refresh() {
        await this.loadOrders();
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Filter form submission
        if (this.elements.searchTerm) {
            this.elements.searchTerm.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.applyFilters();
                }
            });
        }
        
        // Auto-apply filters when selects change (debounced)
        ['statusFilter', 'outletFilter'].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', this.debounce(() => {
                    this.applyFilters();
                }, 500));
            }
        });
    }
    
    /**
     * Utility: Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Utility: Format number
     */
    formatNumber(num, decimals = 0) {
        if (num === null || num === undefined) return '0';
        return parseFloat(num).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    /**
     * Utility: Format date
     */
    formatDate(dateStr, format = 'full') {
        if (!dateStr) return '';
        
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        
        if (format === 'short') {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    /**
     * Utility: Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Global functions for inline event handlers
window.toggleFilters = function() {
    if (window.ordersManager) {
        window.ordersManager.toggleFilters();
    }
};

window.toggleBulkActions = function() {
    if (window.ordersManager) {
        window.ordersManager.toggleBulkActions();
    }
};

window.applyFilters = function() {
    if (window.ordersManager) {
        window.ordersManager.applyFilters();
    }
};

window.clearFilters = function() {
    if (window.ordersManager) {
        window.ordersManager.clearFilters();
    }
};

window.sortBy = function(column) {
    if (window.ordersManager) {
        window.ordersManager.sortBy(column);
    }
};

window.toggleSelectAll = function(checked) {
    if (window.ordersManager) {
        window.ordersManager.toggleSelectAll(checked);
    }
};

window.toggleView = function(viewMode) {
    if (window.ordersManager) {
        window.ordersManager.viewMode = viewMode;
        window.ordersManager.renderOrders(window.ordersManager.lastOrdersData || []);
        
        // Update view buttons
        document.getElementById('viewTable').classList.toggle('btn-primary', viewMode === 'table');
        document.getElementById('viewTable').classList.toggle('btn-outline-secondary', viewMode !== 'table');
        document.getElementById('viewCards').classList.toggle('btn-primary', viewMode === 'cards');
        document.getElementById('viewCards').classList.toggle('btn-outline-secondary', viewMode !== 'cards');
    }
};

window.showExportModal = function() {
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(document.getElementById('exportModal')).show();
    }
};

window.executeExport = function() {
    if (window.ordersManager) {
        const type = document.getElementById('exportType').value;
        const format = document.getElementById('exportFormat').value;
        const limit = document.getElementById('exportLimit').value;
        const applyFilters = document.getElementById('exportCurrentFilters').checked;
        
        let url = `${window.ordersManager.apiBase}/po-export.php?type=${type}&format=${format}&limit=${limit}`;
        
        if (applyFilters) {
            const params = window.ordersManager.buildApiParams();
            url += '&' + params;
        }
        
        window.open(url, '_blank');
        
        // Close modal
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
        }
    }
};

window.refreshOrders = function() {
    if (window.ordersManager) {
        window.ordersManager.refresh();
    }
};

console.log('Orders JavaScript loaded successfully');