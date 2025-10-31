/**
 * Table Sorting System
 * Client-side table sorting with visual indicators
 *
 * Usage:
 * <table class="table sortable-table">
 *     <thead>
 *         <th data-sortable data-sort-type="string">Name</th>
 *         <th data-sortable data-sort-type="number">Price</th>
 *         <th data-sortable data-sort-type="date">Date</th>
 *     </thead>
 * </table>
 */

function initSortableTable(table) {
    const headers = table.querySelectorAll('thead th[data-sortable]');

    headers.forEach((header, index) => {
        // Add sort icon
        if (!header.querySelector('.sort-icon')) {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.innerHTML += ' <i class="fas fa-sort sort-icon ms-1 text-muted"></i>';
        }

        header.addEventListener('click', function() {
            sortTable(table, index, this);
        });
    });
}

function sortTable(table, columnIndex, header) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const sortType = header.getAttribute('data-sort-type') || 'string';
    const currentDirection = header.getAttribute('data-sort-direction') || 'asc';
    const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';

    // Update header state
    table.querySelectorAll('thead th').forEach(th => {
        th.removeAttribute('data-sort-direction');
        const icon = th.querySelector('.sort-icon');
        if (icon) {
            icon.className = 'fas fa-sort sort-icon ms-1 text-muted';
        }
    });

    header.setAttribute('data-sort-direction', newDirection);
    const icon = header.querySelector('.sort-icon');
    if (icon) {
        icon.className = `fas fa-sort-${newDirection === 'asc' ? 'up' : 'down'} sort-icon ms-1`;
    }

    // Sort rows
    rows.sort((a, b) => {
        const aCell = a.cells[columnIndex];
        const bCell = b.cells[columnIndex];

        let aValue = aCell.textContent.trim();
        let bValue = bCell.textContent.trim();

        // Get value from data attribute if exists
        if (aCell.hasAttribute('data-sort-value')) {
            aValue = aCell.getAttribute('data-sort-value');
        }
        if (bCell.hasAttribute('data-sort-value')) {
            bValue = bCell.getAttribute('data-sort-value');
        }

        let comparison = 0;

        switch (sortType) {
            case 'number':
                const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, '')) || 0;
                const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, '')) || 0;
                comparison = aNum - bNum;
                break;

            case 'date':
                const aDate = new Date(aValue);
                const bDate = new Date(bValue);
                comparison = aDate - bDate;
                break;

            case 'string':
            default:
                comparison = aValue.localeCompare(bValue);
                break;
        }

        return newDirection === 'asc' ? comparison : -comparison;
    });

    // Re-append rows
    rows.forEach(row => tbody.appendChild(row));

    // Add animation
    tbody.style.opacity = '0.5';
    setTimeout(() => {
        tbody.style.opacity = '1';
    }, 100);
}

// Initialize all sortable tables
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sortable-table, table.table').forEach(table => {
        if (table.querySelector('[data-sortable]')) {
            initSortableTable(table);
        }
    });
});
