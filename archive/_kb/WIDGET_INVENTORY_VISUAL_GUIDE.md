# 🎨 Widget Inventory & Visual Guide
## Exact Components to Migrate from Demo to Production

**Purpose:** Visual reference showing EVERY widget, card, and UI element to preserve  
**User Requirement:** "I SPENT ALOT OF TIME ON CHOOSING ALL OF THOS WIDGETS AND STYLING SO ITS OFFENSIVE TO NOT HAVE IT AT ALL"

---

## 📊 Dashboard Page (demo/index.html)

### Top Section - Statistics Grid

```
┌─────────────────────────────────────────────────────────────────┐
│                    DASHBOARD STATISTICS                         │
├─────────────────┬─────────────────┬─────────────────┬───────────┤
│                 │                 │                 │           │
│  📦 Total Orders│  ⏰ Pending    │  💰 Revenue     │  📦 Active│
│                 │                 │                 │           │
│      247        │      18         │   $45,670.50    │    156    │
│  Total Orders   │ Pending Orders  │ Revenue (30 Days│  Active   │
│  ↑ +12.5%       │                 │  ↑ +8.3%        │  Products │
│                 │                 │                 │           │
└─────────────────┴─────────────────┴─────────────────┴───────────┘
         BLUE             ORANGE          GREEN            CYAN
    (Primary Card)    (Warning Card)  (Success Card)   (Info Card)
```

**CSS Classes:**
- `.stat-card` - Base card
- `.stat-card-primary` / `.stat-card-warning` / `.stat-card-success` / `.stat-card-info`
- `.stat-card-icon` - Icon container (56x56px, rounded)
- `.stat-card-content` - Text content
- `.stat-card-value` - Large number (32px, bold)
- `.stat-card-label` - Small label text
- `.stat-card-change` - Change indicator with arrow icon

**Data Points:**
- Total Orders: `GET /supplier/api/dashboard-stats.php → data.total_orders`
- Orders Change: `→ data.orders_change` (percentage)
- Pending Orders: `→ data.pending_orders`
- Revenue: `→ data.revenue_30d`
- Revenue Change: `→ data.revenue_change`
- Active Products: `→ data.active_products`

---

### Middle Section - Charts Grid

```
┌────────────────────────────────┬──────────────────────────────────┐
│  📈 Revenue Trend              │  📊 Top Products                 │
│  ───────────────────────────   │  ────────────────────────────    │
│                                │                                  │
│  [Chart.js Line Chart]         │  [Chart.js Bar Chart]            │
│  - Last 30 days                │  - Top 10 products by units      │
│  - Blue gradient fill          │  - Horizontal bars               │
│  - Smooth curves               │  - Color gradient                │
│  - Grid lines                  │  - Value labels                  │
│  - Tooltips on hover           │  - Tooltips on hover             │
│                                │                                  │
│  [Refresh Button]              │  [View All Button]               │
└────────────────────────────────┴──────────────────────────────────┘
```

**Chart Configuration (Chart.js):**

**Revenue Trend Chart:**
```javascript
{
    type: 'line',
    data: {
        labels: // GET /supplier/api/dashboard-revenue-chart.php → data.labels
        datasets: [{
            label: 'Revenue',
            data: // → data.values
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                backgroundColor: '#1f2937',
                titleColor: '#ffffff',
                bodyColor: '#ffffff'
            }
        }
    }
}
```

**Top Products Chart:**
```javascript
{
    type: 'bar',
    data: {
        labels: // GET /supplier/api/dashboard-top-products.php → data[].product_name
        datasets: [{
            label: 'Units Sold',
            data: // → data[].units_sold
            backgroundColor: [
                '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', 
                '#10b981', '#06b6d4', '#6366f1', '#f43f5e'
            ]
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false
    }
}
```

**CSS Classes:**
- `.chart-card` - Chart container
- `.chart-card-header` - Header with title and actions
- `.chart-card-title` - Chart title with icon
- `.chart-card-actions` - Button container
- `.chart-card-body` - Chart canvas container

---

### Bottom Section - Recent Orders Timeline

```
┌─────────────────────────────────────────────────────────────┐
│  📋 Recent Orders                                           │
│  ──────────────────────────────────────────────────────     │
│                                                             │
│  ● PO-2025-1234                                             │
│  │ Smok Nord 4 Kit x 50 units                              │
│  │ Status: ✓ Sent  •  Total: $1,250.00  •  3 hours ago    │
│  │                                                          │
│  ● PO-2025-1233                                             │
│  │ Vaporesso GTX Coils x 200 packs                         │
│  │ Status: ⏰ Processing  •  Total: $850.00  •  5 hours ago│
│  │                                                          │
│  ● PO-2025-1232                                             │
│  │ Geekvape Aegis Legend 2 x 30 units                      │
│  │ Status: ⏰ Pending  •  Total: $2,400.00  •  1 day ago   │
│  │                                                          │
│  [View All Orders →]                                        │
└─────────────────────────────────────────────────────────────┘
```

**Timeline Structure:**
- Each item has colored dot (● blue/green/orange)
- Vertical line connecting dots
- PO number in monospace font (bold)
- Product description (gray text)
- Status badge (colored pill)
- Total amount (bold)
- Relative time (gray, small)

**CSS Classes:**
- `.timeline-item` - Container for each order
- `.timeline-dot` - Colored dot
- `.timeline-dot.bg-primary` / `.bg-success` / `.bg-warning`
- `.po-number` - PO number styling
- `.status-badge` - Status pill
- `.status-sent` / `.status-processing` / `.status-pending`

**Data Source:**
```javascript
GET /supplier/api/dashboard-recent-orders.php
Response: {
    success: true,
    data: [
        {
            po_number: "PO-2025-1234",
            product_summary: "Smok Nord 4 Kit x 50 units",
            status: "sent",
            status_label: "Sent",
            total: 1250.00,
            time_ago: "3 hours ago",
            dot_color: "primary"
        },
        // ... more orders
    ]
}
```

---

### Sidebar Widgets (Right Side)

```
┌───────────────────────────┐
│  RECENT ACTIVITY          │
│  ────────────────────────  │
│                           │
│  ● New Order              │
│    3h ago                 │
│                           │
│  ● Warranty Claim         │
│    5h ago                 │
│                           │
│  ● Order Shipped          │
│    1d ago                 │
│                           │
└───────────────────────────┘

┌───────────────────────────┐
│  QUICK STATS              │
│  ────────────────────────  │
│                           │
│  Active Orders            │
│  18                       │
│                           │
│  Stock Health             │
│  94%                      │
│  ▓▓▓▓▓▓▓▓▓░  94%          │
│                           │
│  This Month               │
│  $45,670                  │
│                           │
└───────────────────────────┘
```

**CSS Classes:**
- `.sidebar-widget` - Widget container
- `.sidebar-widget-title` - Widget header
- `.sidebar-activity` - Activity list container
- `.sidebar-activity-item` - Each activity item
- `.activity-dot` - Colored dot
- `.activity-text` - Text content
- `.activity-title` - Activity title
- `.activity-time` - Timestamp
- `.quick-stats` - Stats container
- `.stat-item` - Each stat
- `.stat-label` - Stat label
- `.stat-value` - Stat value

**Data Sources:**
```javascript
// Recent Activity
GET /supplier/api/dashboard-activity.php
Response: {
    success: true,
    data: [
        {
            type: "order",
            message: "New Order",
            time: "3h ago",
            dot_color: "primary"
        },
        // ...
    ]
}

// Quick Stats (same as main dashboard stats)
GET /supplier/api/dashboard-stats.php
```

---

## 🛒 Orders Page (demo/orders.html)

### Top Toolbar - Search & Filters

```
┌─────────────────────────────────────────────────────────────────┐
│  🔍 Search orders...     |  📅 Last 30 Days  ▼  |  Export CSV    │
│                                                                  │
│  [All] [Pending] [Processing] [Sent] [Completed]                │
│   25    18        12          45      192                       │
└─────────────────────────────────────────────────────────────────┘
```

**Components:**
1. **Search Box:**
   - `.search-box` container
   - Input with left-padding for icon
   - Magnifying glass icon (absolute positioned)

2. **Date Filter:**
   - Dropdown button
   - Options: Today, Last 7 Days, Last 30 Days, Custom Range

3. **Export Button:**
   - Primary button with icon
   - Triggers CSV download

4. **Status Filter Badges:**
   - `.filter-badge` - Base badge
   - `.filter-badge.active` - Selected state
   - Shows count for each status
   - Clickable to filter table

**CSS:**
```css
.orders-toolbar {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}

.search-box input {
    padding-left: 40px;
    border-radius: 6px;
    height: 38px;
}

.filter-badge {
    display: inline-flex;
    padding: 6px 12px;
    background: #f3f4f6;
    border-radius: 6px;
    cursor: pointer;
}

.filter-badge.active {
    background: #3b82f6;
    color: white;
}
```

---

### Data Table - Orders List

```
┌────────────────────────────────────────────────────────────────────────────┐
│  ☑  PO NUMBER      DATE         PRODUCTS  STATUS       TOTAL      ACTIONS  │
├────────────────────────────────────────────────────────────────────────────┤
│  □  PO-2025-1234   Oct 24, 2025   12      [Pending]    $1,250.00  [View]  │
│  □  PO-2025-1233   Oct 23, 2025   8       [Processing] $850.00    [View]  │
│  □  PO-2025-1232   Oct 22, 2025   25      [Sent]       $2,400.00  [View]  │
│  □  PO-2025-1231   Oct 21, 2025   15      [Completed]  $1,675.00  [View]  │
└────────────────────────────────────────────────────────────────────────────┘
                          [1] [2] [3] [4] [5] ... [24]
                       Showing 1-25 of 592 orders
```

**Table Structure:**
- `.orders-table` - Table container
- White background, rounded corners
- Border on all sides
- Gray header background (`#f9fafb`)
- Uppercase column headers (11px, letter-spacing)
- Hover effect on rows
- Alternating row colors (subtle)

**Status Badge Colors:**
- **Pending:** Yellow background (`#fef3c7`), brown text (`#92400e`)
- **Processing:** Blue background (`#dbeafe`), dark blue text (`#1e40af`)
- **Sent:** Green background (`#d1fae5`), dark green text (`#065f46`)
- **Completed:** Indigo background (`#e0e7ff`), purple text (`#3730a3`)

**Checkbox Column:**
- Allows bulk selection
- "Select All" in header
- Individual checkboxes per row

**Action Buttons:**
- `.action-btn` - Base button (transparent, hover gray)
- Icons: View (eye), Edit (pencil), Download (arrow-down)

---

### Bulk Actions Bar (Fixed Bottom)

```
                    ┌──────────────────────────────────────┐
                    │  ✓ 5 selected                        │
                    │  [Export] [Mark as Processed] [✕]    │
                    └──────────────────────────────────────┘
```

**Position:** Fixed at bottom center of viewport  
**Visibility:** Hidden by default, shows when items selected  
**Background:** Dark gray (`#1f2937`)  
**Shadow:** Large drop shadow  
**Animation:** Slide up from bottom

**CSS:**
```css
.bulk-actions-bar {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    padding: 10px 16px;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    display: none;
    z-index: 1000;
}

.bulk-actions-bar.active {
    display: flex;
}
```

---

## 🔧 Warranty Page (demo/warranty.html)

### KPI Cards Grid

```
┌──────────────────┬──────────────────┬──────────────────┬──────────────────┐
│  Total Claims    │  Pending Review  │  Avg Response    │  Resolution Rate │
│                  │                  │                  │                  │
│     147          │      5           │    2.3 days      │      96.7%       │
│  ↓ -8.3%         │  Requires Response│  ↓ -0.8 days    │  ↑ +2.1%         │
└──────────────────┴──────────────────┴──────────────────┴──────────────────┘
```

**Same structure as Dashboard stat cards, but different metrics**

**Data Source:**
```javascript
GET /supplier/api/warranty-stats.php
Response: {
    success: true,
    data: {
        total_claims: 147,
        claims_change: -8.3,
        pending_review: 5,
        avg_response_days: 2.3,
        response_change: -0.8,
        resolution_rate: 96.7,
        resolution_change: 2.1
    }
}
```

---

### Claims Table

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  CLAIM ID       STORE          PRODUCT         ISSUE      STATUS    PRIORITY │
├──────────────────────────────────────────────────────────────────────────────┤
│  WC-2025-1049   Hamilton       Smok RPM 5      [DOA]      [Pending] [Urgent]│
│                 Central        Kit                                           │
│                                                                               │
│  WC-2025-1048   Auckland CBD   Vaporesso       [Battery   [Pending] [High]  │
│                                Gen 200          Door]                         │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Badge Types:**

**Issue Badges:**
- **DOA (Dead on Arrival):** Red background, white text
- **Defective:** Orange background
- **Damaged:** Red background
- **Battery Issue:** Yellow background

**Status Badges:**
- **Pending Review:** Orange/yellow
- **In Progress:** Blue
- **Approved:** Green
- **Rejected:** Red
- **Resolved:** Purple

**Priority Badges:**
- **Urgent:** Red background
- **High:** Orange background
- **Medium:** Blue background
- **Low:** Gray background

---

## 🎨 Shared Layout Components

### Sidebar (All Pages)

```
┌─────────────────────┐
│  [LOGO IMAGE]       │
│                     │
├─────────────────────┤
│  📊 Dashboard       │ ← Active (blue highlight)
│  🛒 Purchase Orders │
│  🔧 Warranty Claims │
│     5               │ ← Badge (red)
│  📥 Downloads       │
│  📈 30-Day Reports  │
│  ⚙️  Account Settings│
└─────────────────────┘
```

**CSS Properties:**
- Background: Dark gray/black (`#1a1d1e`)
- Width: 260px fixed
- Full height: 100vh
- Logo: 180px max-width, centered
- Nav items: 48px height
- Active state: Blue left border + blue background
- Badge: Positioned absolute right

---

### Header (All Pages)

```
┌──────────────────────────────────────────────────────────────────┐
│  Supplier Portal              🔔(3)  👤 John Doe  ▼              │
└──────────────────────────────────────────────────────────────────┘
```

**Components:**
- Left: Page title or breadcrumbs
- Right: Notifications bell, user menu
- Background: White
- Border bottom: Light gray
- Height: 64px

---

## 📐 Responsive Breakpoints

```
Desktop (≥1200px):  Full layout, 4 stat cards per row, sidebar visible
Tablet (768-1199px): 2 stat cards per row, collapsible sidebar
Mobile (<768px):    1 stat card per row, mobile menu, stacked charts
```

---

## ✅ Widget Migration Checklist

### Dashboard:
- [ ] 4 stat cards with icons
- [ ] Revenue trend line chart
- [ ] Top products bar chart
- [ ] Recent orders timeline
- [ ] Sidebar recent activity widget
- [ ] Sidebar quick stats widget
- [ ] All charts use Chart.js
- [ ] All loading spinners
- [ ] All tooltips
- [ ] All hover effects

### Orders:
- [ ] Search toolbar
- [ ] Date filter dropdown
- [ ] Status filter badges with counts
- [ ] Export button
- [ ] Data table with hover
- [ ] Checkbox column (bulk select)
- [ ] Status badge colors
- [ ] Action buttons column
- [ ] Bulk actions bar (fixed bottom)
- [ ] Pagination controls

### Warranty:
- [ ] 4 KPI cards
- [ ] Claims data table
- [ ] Issue badges (color-coded)
- [ ] Status badges
- [ ] Priority badges
- [ ] Action buttons

### Shared:
- [ ] Sidebar with logo
- [ ] Active nav highlighting
- [ ] Badge counts on nav items
- [ ] Header with notifications
- [ ] User dropdown menu
- [ ] Responsive layout
- [ ] Professional Black theme CSS

---

**Total Widgets to Migrate:** 50+ unique components  
**CSS Classes to Preserve:** 80+ classes  
**Chart.js Configurations:** 2 charts  
**API Endpoints Needed:** 12 endpoints  

**Status:** ✅ DOCUMENTED - Ready for implementation  
**Next:** Begin coding tab-dashboard.php with exact HTML structure

