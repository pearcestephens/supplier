/**
 * Downloads & Reports JavaScript
 * Supplier Portal - Downloads Page
 *
 * Functions:
 * - downloadWarrantyClaims() - Download warranty claims CSV
 * - generateMonthlyReport() - Generate monthly PDF report
 * - downloadPeriodReport(period) - Download report for specific period
 * - Custom report form handler
 */

// Warranty Claims CSV Download
function downloadWarrantyClaims() {
    console.log('Downloading warranty claims...');
    window.location.href = '/supplier/api/export-warranty-claims.php';
}

// Monthly Report PDF
function generateMonthlyReport() {
    const month = new Date().toISOString().slice(0, 7); // YYYY-MM format
    console.log('Generating monthly report for:', month);
    window.location.href = '/supplier/api/generate-report.php?period=month&date=' + month + '&format=pdf';
}

// Period Report Downloads
function downloadPeriodReport(period) {
    console.log('Downloading report for period:', period);
    window.location.href = '/supplier/api/generate-report.php?period=' + period + '&format=csv';
}

// Custom Report Form Handler
document.addEventListener('DOMContentLoaded', function() {
    const customReportForm = document.getElementById('custom-report-form');

    if (customReportForm) {
        customReportForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams(formData);

            console.log('Custom report requested:', Object.fromEntries(params));
            window.location.href = '/supplier/api/generate-report.php?' + params.toString();
        });
    }

    console.log('✅ Downloads.js loaded');
});
