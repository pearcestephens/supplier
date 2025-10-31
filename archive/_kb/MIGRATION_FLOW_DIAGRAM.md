# 📐 Demo to Production Migration - Visual Flow Diagram
## Complete Architecture & Data Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│                     DEMO TO PRODUCTION MIGRATION FLOW                       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘


┌──────────────────────┐
│   DEMO FILES         │
│   (Static HTML)      │
├──────────────────────┤
│  demo/index.html     │  ────┐
│  demo/orders.html    │      │
│  demo/warranty.html  │      │  EXTRACTION
│  demo/reports.html   │      │  (HTML + CSS + JS)
│  demo/downloads.html │      │
│  demo/account.html   │      │
└──────────────────────┘  ────┘
                              │
                              ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                         INTERFACE LAYER (PHP)                               │
│                         (1:1 HTML Structure)                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  tabs/tab-dashboard.php  ┌──────────────────────────────────────┐          │
│  ├─ 4 Stat Cards         │  <div class="stat-card">             │          │
│  ├─ 2 Charts (Chart.js)  │    <div class="stat-card-icon">      │          │
│  ├─ Recent Orders        │      <i class="fa-shopping-cart"></i>│          │
│  └─ Sidebar Widgets      │    </div>                            │          │
│                          │    <div class="stat-card-value"       │          │
│  tabs/tab-orders.php     │         id="stat-total-orders">      │          │
│  ├─ Search Toolbar       │      <!-- API loads data here -->    │          │
│  ├─ Data Table           │    </div>                            │          │
│  ├─ Status Badges        │  </div>                              │          │
│  └─ Bulk Actions Bar     └──────────────────────────────────────┘          │
│                                                                             │
│  tabs/tab-warranty.php                                                      │
│  ├─ KPI Cards (4x)                                                          │
│  ├─ Claims Table                                                            │
│  └─ Priority Badges                                                         │
│                                                                             │
│  tabs/tab-reports.php                                                       │
│  tabs/tab-downloads.php                                                     │
│  tabs/tab-account.php                                                       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                              │
                              │ AJAX Calls (fetch API)
                              ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                            API LAYER (PHP)                                  │
│                        (RESTful JSON Endpoints)                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  api/dashboard-stats.php                                                    │
│  ├─ GET /supplier/api/dashboard-stats.php                                  │
│  ├─ Returns: {success: true, data: {...}}                                  │
│  └─ Data: total_orders, pending_orders, revenue_30d, active_products       │
│                                                                             │
│  api/dashboard-revenue-chart.php                                            │
│  ├─ GET /supplier/api/dashboard-revenue-chart.php                          │
│  ├─ Returns: {success: true, data: {labels: [...], values: [...]}}         │
│  └─ Used by: Chart.js line chart                                           │
│                                                                             │
│  api/dashboard-top-products.php                                             │
│  ├─ GET /supplier/api/dashboard-top-products.php                           │
│  ├─ Returns: {success: true, data: [{product_name, units_sold}, ...]}      │
│  └─ Used by: Chart.js bar chart                                            │
│                                                                             │
│  api/dashboard-recent-orders.php                                            │
│  ├─ GET /supplier/api/dashboard-recent-orders.php                          │
│  ├─ Returns: {success: true, data: [{po_number, status, total}, ...]}      │
│  └─ Used by: Timeline widget                                               │
│                                                                             │
│  api/orders-list.php                                                        │
│  ├─ GET /supplier/api/orders-list.php?status=pending&page=1                │
│  ├─ Returns: {success: true, data: {orders: [...], total, page}}           │
│  └─ Used by: Orders data table                                             │
│                                                                             │
│  api/warranty-stats.php                                                     │
│  ├─ GET /supplier/api/warranty-stats.php                                   │
│  ├─ Returns: {success: true, data: {total_claims, pending, avg_response}}  │
│  └─ Used by: Warranty KPI cards                                            │
│                                                                             │
│  ... 6 more API endpoints ...                                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                              │
                              │ PDO Queries (Prepared Statements)
                              ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DATABASE LAYER                                    │
│                      MariaDB 10.5 (jcepnzzkmj)                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Tables:                                                                    │
│  ├─ purchase_orders         (PO headers)                                   │
│  ├─ purchase_order_items    (PO line items)                                │
│  ├─ products                (Product catalog)                              │
│  ├─ warranty_claims         (Warranty requests)                            │
│  ├─ suppliers               (Supplier accounts)                            │
│  └─ ... other tables ...                                                   │
│                                                                             │
│  Queries (All use prepared statements):                                    │
│  ├─ SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?            │
│  ├─ SELECT SUM(total) FROM purchase_orders WHERE created_at >= ? AND ...   │
│  ├─ SELECT * FROM warranty_claims WHERE supplier_id = ? ORDER BY ...       │
│  └─ ... all queries parameterized for security ...                         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                          CSS LAYER (Styling)                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Bootstrap 5.3 CDN                                                          │
│  ├─ Grid system (row, col-md-*)                                            │
│  ├─ Utility classes (mb-4, text-muted, etc.)                               │
│  └─ Components (cards, buttons, badges)                                    │
│                                                                             │
│  /supplier/assets/css/professional-black.css  (EXISTING)                   │
│  ├─ Dark sidebar (#1a1d1e)                                                 │
│  ├─ Navigation styles                                                       │
│  ├─ Header/footer                                                           │
│  └─ Global theme                                                            │
│                                                                             │
│  /supplier/demo/assets/css/demo-additions.css  (TO MERGE)                  │
│  ├─ .stat-card, .stat-card-primary, .stat-card-icon                        │
│  ├─ .chart-card, .chart-card-header, .chart-card-body                      │
│  ├─ .timeline-item, .timeline-dot                                          │
│  ├─ .orders-toolbar, .search-box, .filter-badge                            │
│  ├─ .status-badge, .status-pending, .status-processing, etc.               │
│  ├─ .bulk-actions-bar                                                      │
│  └─ ... 80+ more classes ...                                               │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                        JAVASCRIPT LAYER (Behavior)                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Chart.js 3.9.1 CDN                                                         │
│  ├─ Line charts (revenue trend)                                            │
│  ├─ Bar charts (top products)                                              │
│  └─ Configuration objects                                                   │
│                                                                             │
│  Font Awesome 6.0 CDN                                                       │
│  ├─ Icons (fa-shopping-cart, fa-clock, etc.)                               │
│  └─ 200+ icons used throughout                                             │
│                                                                             │
│  Custom JavaScript (In each tab file)                                      │
│  ├─ async function loadDashboardStats() { fetch('/api/...') }              │
│  ├─ async function loadRevenueChart() { new Chart(...) }                   │
│  ├─ async function loadRecentOrders() { fetch('/api/...') }                │
│  ├─ Event handlers (click, change, submit)                                 │
│  ├─ Loading spinners (show/hide)                                           │
│  ├─ Error states (try/catch)                                               │
│  └─ DOM manipulation (innerHTML, textContent)                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                      SECURITY LAYER (Protection)                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  bootstrap.php                                                              │
│  ├─ requireAuth() - Verify user logged in                                  │
│  ├─ getSupplierID() - Extract supplier_id from session                     │
│  ├─ pdo() - Return PDO connection                                          │
│  └─ Error handlers (set_error_handler, set_exception_handler)              │
│                                                                             │
│  Authentication Flow                                                        │
│  ├─ 1. User logs in via login.php                                          │
│  ├─ 2. Session created with supplier_id                                    │
│  ├─ 3. Every API/tab calls requireAuth()                                   │
│  ├─ 4. If not auth'd → redirect to login                                   │
│  └─ 5. If auth'd → proceed with supplier_id filter                         │
│                                                                             │
│  SQL Injection Prevention                                                   │
│  ├─ All queries use PDO prepared statements                                │
│  ├─ Parameters bound with execute([...])                                   │
│  ├─ No string concatenation in SQL                                         │
│  └─ Example: $stmt->execute([$supplierID])                                 │
│                                                                             │
│  XSS Prevention                                                             │
│  ├─ All user input escaped with htmlspecialchars()                         │
│  ├─ JSON responses properly encoded                                        │
│  └─ No raw echo of user data                                               │
│                                                                             │
│  CSRF Protection                                                            │
│  ├─ Token in session                                                        │
│  ├─ Token in all forms                                                      │
│  └─ Validated on POST requests                                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                        DATA FLOW EXAMPLE: Dashboard Stats                  │
└─────────────────────────────────────────────────────────────────────────────┘

1. USER OPENS DASHBOARD
   ├─ Browser requests: /supplier/index.php?tab=dashboard
   └─ Server returns: tab-dashboard.php content

2. PAGE LOADS WITH LOADING SPINNERS
   ├─ HTML renders stat cards with: <div class="spinner-border"></div>
   └─ JavaScript: document.addEventListener('DOMContentLoaded', ...)

3. JAVASCRIPT CALLS API
   ├─ fetch('/supplier/api/dashboard-stats.php')
   └─ Headers: Cookie: PHPSESSID=xyz123

4. API AUTHENTICATES REQUEST
   ├─ requireAuth() checks session
   ├─ If no session → return 401
   └─ If valid → getSupplierID() returns supplier_id

5. API QUERIES DATABASE
   ├─ $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?")
   ├─ $stmt->execute([$supplierID])
   └─ $result = $stmt->fetchColumn()

6. API RETURNS JSON
   ├─ echo json_encode([
   │     'success' => true,
   │     'data' => [
   │       'total_orders' => 247,
   │       'pending_orders' => 18,
   │       'revenue_30d' => 45670.50,
   │       'active_products' => 156
   │     ]
   │   ])
   └─ Content-Type: application/json

7. JAVASCRIPT RECEIVES RESPONSE
   ├─ const result = await response.json()
   ├─ if (result.success) { ... }
   └─ Parse data object

8. JAVASCRIPT UPDATES DOM
   ├─ document.getElementById('stat-total-orders-value').textContent = 247
   ├─ document.getElementById('stat-pending-orders-value').textContent = 18
   ├─ Remove loading spinner
   └─ Show values with CSS transitions

9. USER SEES UPDATED STATS
   ├─ Spinners gone
   ├─ Real numbers displayed
   ├─ Change indicators shown (↑ +12.5%)
   └─ All within 500ms


┌─────────────────────────────────────────────────────────────────────────────┐
│                         FILE STRUCTURE (After Migration)                    │
└─────────────────────────────────────────────────────────────────────────────┘

/home/master/applications/jcepnzzkmj/public_html/supplier/
│
├── index.php                         (Main router - loads tabs)
├── login.php                         (Authentication)
├── logout.php                        (Session destroy)
├── bootstrap.php                     (Shared initialization)
│
├── tabs/                             (Page content - NEW VERSIONS)
│   ├── tab-dashboard.php             ✨ NEW - 1:1 from demo/index.html
│   ├── tab-orders.php                ✨ NEW - 1:1 from demo/orders.html
│   ├── tab-warranty.php              ✨ NEW - 1:1 from demo/warranty.html
│   ├── tab-reports.php               ✨ NEW - 1:1 from demo/reports.html
│   ├── tab-downloads.php             ✨ NEW - 1:1 from demo/downloads.html
│   ├── tab-account.php               ✨ NEW - 1:1 from demo/account.html
│   │
│   ├── tab-dashboard.php_backup      📦 OLD VERSION (backup)
│   ├── tab-orders.php_backup         📦 OLD VERSION (backup)
│   ├── tab-warranty.php_backup       📦 OLD VERSION (backup)
│   ├── tab-reports.php_backup        📦 OLD VERSION (backup)
│   ├── tab-downloads.php_backup      📦 OLD VERSION (backup)
│   └── tab-account.php_backup        📦 OLD VERSION (backup)
│
├── api/                              (JSON endpoints)
│   ├── dashboard-stats.php           ✨ NEW
│   ├── dashboard-revenue-chart.php   ✨ NEW
│   ├── dashboard-top-products.php    ✨ NEW
│   ├── dashboard-recent-orders.php   ✨ NEW
│   ├── orders-list.php               ✨ NEW
│   ├── order-detail.php              ✨ NEW
│   ├── warranty-stats.php            ✨ NEW
│   ├── warranty-list.php             ✨ NEW
│   ├── reports-summary.php           ✨ NEW
│   ├── downloads-list.php            ✨ NEW
│   ├── account-settings.php          ✨ NEW
│   ├── dashboard-activity.php        ✨ NEW
│   │
│   ├── update-po-status.php          ✅ EXISTING (keep)
│   ├── add-order-note.php            ✅ EXISTING (keep)
│   ├── warranty-action.php           ✅ EXISTING (keep)
│   └── ... other existing APIs ...   ✅ EXISTING (keep)
│
├── assets/
│   ├── css/
│   │   ├── professional-black.css    ✅ EXISTING (keep)
│   │   ├── demo-additions.css        📥 MERGE INTO MAIN
│   │   └── supplier-portal-enhanced.css  ✨ NEW (merged)
│   │
│   ├── js/
│   │   └── ... existing JS ...       ✅ EXISTING (keep)
│   │
│   └── images/
│       └── logo.jpg                  ✅ EXISTING (keep)
│
├── components/
│   ├── header-top.php                ✅ EXISTING (keep)
│   ├── sidebar.php                   ✅ EXISTING (keep)
│   └── header-bottom.php             ✅ EXISTING (keep)
│
├── demo/                             (Reference files - keep)
│   ├── index.html                    📖 REFERENCE
│   ├── orders.html                   📖 REFERENCE
│   ├── warranty.html                 📖 REFERENCE
│   ├── reports.html                  📖 REFERENCE
│   ├── downloads.html                📖 REFERENCE
│   ├── account.html                  📖 REFERENCE
│   └── assets/
│       └── css/
│           └── demo-additions.css    📖 REFERENCE (to be merged)
│
├── docs/
│   ├── DEMO_TO_PRODUCTION_MIGRATION_PLAN.md        📋 PLANNING
│   ├── WIDGET_INVENTORY_VISUAL_GUIDE.md            📋 REFERENCE
│   ├── STEP_BY_STEP_IMPLEMENTATION.md              📋 EXECUTION
│   ├── MIGRATION_READY_SUMMARY.md                  📋 SUMMARY
│   └── MIGRATION_FLOW_DIAGRAM.md                   📋 THIS FILE
│
└── ... other files ...


Legend:
✨ NEW - Files to be created
✅ EXISTING - Keep as-is
📦 BACKUP - Old versions (renamed)
📖 REFERENCE - Demo files (keep for reference)
📋 DOCUMENTATION - Planning/reference docs
📥 ACTION REQUIRED - Need to merge/process


┌─────────────────────────────────────────────────────────────────────────────┐
│                         TIMELINE & DEPENDENCIES                             │
└─────────────────────────────────────────────────────────────────────────────┘

PHASE 1: BACKUP (5 min)
└─ Rename all 6 tab files to _backup suffix

PHASE 2: DASHBOARD (3 hours)
├─ Create tab-dashboard.php              [HTML + JS] ─┐
├─ Create dashboard-stats.php            [API] ───────┼─ Can work in parallel
├─ Create dashboard-revenue-chart.php    [API] ───────┤
├─ Create dashboard-top-products.php     [API] ───────┤
└─ Create dashboard-recent-orders.php    [API] ───────┘
   └─ Test Dashboard (30 min)

PHASE 3: ORDERS (2-3 hours)
├─ Create tab-orders.php                 [HTML + JS] ─┐
├─ Create orders-list.php                [API] ───────┼─ Can work in parallel
└─ Create order-detail.php               [API] ───────┘
   └─ Test Orders (30 min)

PHASE 4: WARRANTY (1-2 hours)
├─ Create tab-warranty.php               [HTML + JS] ─┐
├─ Create warranty-stats.php             [API] ───────┼─ Can work in parallel
└─ Create warranty-list.php              [API] ───────┘
   └─ Test Warranty (30 min)

PHASE 5: OTHER PAGES (2-3 hours)
├─ Create tab-reports.php + API
├─ Create tab-downloads.php + API
└─ Create tab-account.php + API
   └─ Test Each Page (15 min each)

PHASE 6: CSS CONSOLIDATION (1 hour)
├─ Extract all classes from demo-additions.css
├─ Create supplier-portal-enhanced.css
├─ Test all pages render correctly
└─ Verify responsive breakpoints

PHASE 7: FINAL QA (2 hours)
├─ Complete quality checklist
├─ Test all widgets
├─ Test all APIs
├─ Test responsive layouts
├─ Check browser console for errors
└─ User acceptance testing

TOTAL: 10-14 hours


┌─────────────────────────────────────────────────────────────────────────────┐
│                         ROLLBACK STRATEGY                                   │
└─────────────────────────────────────────────────────────────────────────────┘

IF SOMETHING BREAKS:

1. Restore Individual Page:
   cd /home/master/applications/jcepnzzkmj/public_html/supplier/tabs/
   mv tab-dashboard.php tab-dashboard.php_NEW
   mv tab-dashboard.php_backup tab-dashboard.php

2. Restore All Pages:
   for file in *_backup; do
       mv "$file" "${file%_backup}"
   done

3. Remove New APIs:
   rm api/dashboard-*.php
   rm api/orders-list.php
   rm api/warranty-*.php

4. Check Error Logs:
   tail -200 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

5. Restore Works Because:
   - Original files backed up with _backup suffix
   - New APIs don't interfere with existing code
   - CSS changes are additive (don't break existing)
   - Database unchanged (only reads, no schema changes)


┌─────────────────────────────────────────────────────────────────────────────┐
│                         SUCCESS INDICATORS                                  │
└─────────────────────────────────────────────────────────────────────────────┘

DASHBOARD SUCCESS:
✅ 4 stat cards show real numbers (not spinners)
✅ Revenue line chart renders with blue gradient
✅ Top products bar chart shows 10 products
✅ Recent orders timeline shows last 10 orders
✅ Sidebar widgets show activity + quick stats
✅ No console errors
✅ Page loads in < 2 seconds

ORDERS SUCCESS:
✅ Search box filters table
✅ Status badges show correct colors
✅ Table populates with real orders
✅ Pagination works
✅ Bulk select works
✅ Bulk actions bar appears when items selected

WARRANTY SUCCESS:
✅ 4 KPI cards show real metrics
✅ Claims table populates
✅ Priority badges color-coded
✅ Action buttons trigger modals/actions

OVERALL SUCCESS:
✅ User says: "This is exactly what I wanted!"
✅ All 50+ widgets present and functional
✅ No more "offensive" missing features
✅ Production matches demo 1:1


┌─────────────────────────────────────────────────────────────────────────────┐
│                         QUICK REFERENCE                                     │
└─────────────────────────────────────────────────────────────────────────────┘

DEMO FILES LOCATION:
/home/master/applications/jcepnzzkmj/public_html/supplier/demo/

PRODUCTION TABS LOCATION:
/home/master/applications/jcepnzzkmj/public_html/supplier/tabs/

API LOCATION:
/home/master/applications/jcepnzzkmj/public_html/supplier/api/

CSS FILES:
/home/master/applications/jcepnzzkmj/public_html/supplier/assets/css/

ERROR LOGS:
/home/master/applications/jcepnzzkmj/logs/apache_*.error.log

TEST URL:
https://staff.vapeshed.co.nz/supplier/

DOCUMENTATION:
/home/master/applications/jcepnzzkmj/public_html/supplier/docs/

BROWSER CONSOLE:
F12 → Console tab (check for errors)
F12 → Network tab (check API calls)


┌─────────────────────────────────────────────────────────────────────────────┐
│                         STATUS: READY TO EXECUTE                            │
└─────────────────────────────────────────────────────────────────────────────┘

✅ All planning complete
✅ All documentation written
✅ All code examples provided
✅ All APIs spec'd
✅ All widgets inventoried
✅ Migration path clear
✅ Rollback strategy defined
✅ Success criteria established

AWAITING: Your approval to proceed

```

**END OF DIAGRAM**

