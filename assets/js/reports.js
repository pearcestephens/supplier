/**
 * Reports & Analytics JavaScript
 * Supplier Portal - Reports Page
 *
 * Functions:
 * - Initialize charts (revenue trend, status breakdown)
 * - exportReport() - Export report to PDF
 * - emailReport() - Email report to specified address
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Reports page initializing...');

    // Initialize charts if Chart.js data is available
    if (typeof monthlyTrend !== 'undefined') {
        initializeRevenueTrendChart();
    }

    if (typeof fulfillmentMetrics !== 'undefined') {
        initializeStatusBreakdownChart();
    }

    console.log('✅ Reports.js loaded');
});

function initializeRevenueTrendChart() {
    const revenueTrendCtx = document.getElementById('revenueTrendChart');
    if (!revenueTrendCtx) return;

    new Chart(revenueTrendCtx, {
        type: 'line',
        data: {
            labels: monthlyTrend.map(m => {
                const [year, month] = m.month.split('-');
                return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Revenue',
                data: monthlyTrend.map(m => parseFloat(m.revenue)),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function initializeStatusBreakdownChart() {
    const statusBreakdownCtx = document.getElementById('statusBreakdownChart');
    if (!statusBreakdownCtx) return;

    new Chart(statusBreakdownCtx, {
        type: 'doughnut',
        data: {
            labels: fulfillmentMetrics.map(f => f.state),
            datasets: [{
                data: fulfillmentMetrics.map(f => parseInt(f.count)),
                backgroundColor: [
                    '#3b82f6', // OPEN - blue
                    '#10b981', // SENT - green
                    '#f59e0b', // RECEIVING - yellow
                    '#22c55e', // RECEIVED - green
                    '#6b7280', // CLOSED - gray
                    '#ef4444'  // CANCELLED - red
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function exportReport() {
    alert('PDF export will generate a comprehensive report including:\n\n• Performance summary\n• Monthly trends\n• Top products\n• Store analysis\n• Fulfillment metrics');
    // TODO: Implementation - Generate PDF report
    console.log('Export report requested');
}

function emailReport() {
    const email = prompt('Enter email address to send report:');
    if (email) {
        alert('Report will be sent to: ' + email);
        // TODO: Implementation - Email report
        console.log('Email report to:', email);
    }
}
