# Quick Start Guide - Advanced Reporting System

## For End Users

### Accessing Reports

1. Log in to the Supplier Portal
2. Navigate to **Reports** from the sidebar
3. The advanced reporting dashboard will load

### Viewing Sales Forecast

The ML-powered forecast appears at the top:
- **Blue dashed line**: Predicted sales (next 8 weeks)
- **Green solid line**: Historical sales
- **Light blue bands**: 95% confidence interval
- **Accuracy displayed**: Shows forecast quality

### Week-by-Week Navigation

1. Use **◀** and **▶** buttons to navigate weeks
2. Weekly stats update automatically:
   - Orders count
   - Units sold
   - Total revenue
   - Average order value

### Searching Products

1. Find the **Product Performance Analytics** section
2. Use the search box (top right)
3. Type product name or SKU
4. Table filters in real-time

### Exporting Reports

Click any export button in the toolbar:
- **CSV**: Spreadsheet-compatible, opens in Excel
- **Excel**: Native XLS format with formatting
- **PDF**: Print-ready with charts (opens in new tab)

Current filters apply to all exports.

### Understanding Product Indicators

**Lifecycle Badges:**
- 🟢 **Growth**: Sales trending up (+20% or more)
- 🔵 **Mature**: Stable sales performance
- 🔴 **Decline**: Sales trending down (-20% or more)
- 🟡 **New**: Less than 30 days of history

**Performance Bar:**
- Green (80-100): Excellent performer
- Blue (60-79): Good performer
- Orange (40-59): Average performer
- Red (0-39): Needs attention

### Date Range Selection

1. Choose **Start Date** and **End Date**
2. Click **Update** button
3. All metrics recalculate for new range
4. Forecast adjusts automatically

---

## For Developers

### API Quick Reference

#### Get Weekly Sales
```bash
GET /supplier/api/reports-sales-summary.php?start_date=2024-01-01&end_date=2024-12-31
```

#### Get Product Performance
```bash
GET /supplier/api/reports-product-performance.php?start_date=2024-01-01&end_date=2024-12-31&limit=50&sort_by=revenue
```

#### Get Forecast
```bash
GET /supplier/api/reports-forecast.php?weeks=8
```

#### Export Report
```bash
GET /supplier/api/reports-export.php?format=csv&type=sales_summary&start_date=2024-01-01&end_date=2024-12-31
```

### Using Forecasting Class

```php
require_once 'lib/Forecasting.php';

// Historical data (weekly sales)
$data = [100, 110, 120, 130, 140, 150];

// Generate 8-week forecast
$forecast = Forecasting::generateForecast($data, 8);

// Access predictions
$predictions = $forecast['predictions'];
$accuracy = $forecast['quality']['r_squared'];
$trend = $forecast['quality']['trend'];

// Get confidence intervals
$lower = $forecast['confidence_1sigma']['lower'];
$upper = $forecast['confidence_1sigma']['upper'];
```

### Using Export Generator

```php
require_once 'lib/ReportGenerator.php';

// Prepare data
$data = [
    ['Week 1', 100, 5000],
    ['Week 2', 110, 5500]
];
$headers = ['Week', 'Units', 'Revenue'];

// Export as CSV
ReportGenerator::exportCSV($data, $headers, 'sales_report.csv');

// Export as Excel
ReportGenerator::exportExcel($data, $headers, 'sales_report.xls');

// Export as PDF
$html = ReportGenerator::generateTableHTML($data, $headers, 'Sales Report');
ReportGenerator::exportPDF($html, 'sales_report.pdf');
```

### Customizing the UI

**Add custom filter:**
```javascript
// In assets/js/15-reports.js
state.filters.customField = 'value';
loadReportsData();
```

**Modify chart colors:**
```css
/* In assets/css/05-reports.css */
.forecast-line {
    stroke: #your-color;
}
```

**Change forecast period:**
```javascript
// In assets/js/15-reports.js
const response = await fetch('/supplier/api/reports-forecast.php?weeks=12');
```

### Database Queries

All queries filter by supplier ID for security:
```php
WHERE t.supplier_id = ?
  AND t.transfer_category = 'PURCHASE_ORDER'
  AND t.deleted_at IS NULL
```

Indexes recommended:
- `(supplier_id, created_at)`
- `(transfer_category, deleted_at)`

### Performance Tips

1. **Limit date ranges** to 90-365 days for optimal performance
2. **Use pagination** for large product lists
3. **Cache forecast results** if viewing frequently
4. **Add database indexes** on date/supplier columns
5. **Enable compression** for API responses

### Security Checklist

- ✅ Check `Auth::check()` on all endpoints
- ✅ Filter by `Auth::getSupplierId()`
- ✅ Use prepared statements
- ✅ Escape HTML output
- ✅ Validate input parameters
- ✅ Rate limit API calls (recommended)

---

## Troubleshooting

### "Insufficient historical data"
- Need at least 8 weeks of sales data
- Reduce forecast period or wait for more data

### Week navigation disabled
- Select a date range first
- Ensure date range contains data

### Chart not displaying
- Check Chart.js CDN is accessible
- Verify browser console for errors
- Try hard refresh (Ctrl+F5)

### Export downloads empty
- Verify data exists for selected range
- Check browser console for errors
- Ensure proper authentication

### Slow performance
- Reduce date range
- Add database indexes
- Clear browser cache
- Check server resources

---

## Support

📖 Full docs: `REPORTING_SYSTEM_DOCS.md`
🧪 Run tests: `php test-forecasting.php`
🔍 Debug: Enable `DEBUG_MODE` in `config.php`

---

**Version**: 1.0.0  
**Last Updated**: October 31, 2025  
**Status**: Production Ready ✅
