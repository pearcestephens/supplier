# 🎯 SUPPLIER PORTAL - COMPLETE FEATURE BLUEPRINT
**Generated:** October 25, 2025
**Purpose:** Comprehensive feature analysis and UI design for each tab

---

## 📊 TAB 1: DASHBOARD (Homepage)

### Primary Goal
Give suppliers instant visibility into their business performance and urgent actions needed.

### Key Metrics Widgets (Top Row)
1. **Active Orders** - Open POs requiring action
2. **Monthly Revenue** - Last 30 days sales value
3. **Pending Claims** - Warranty claims awaiting response
4. **Product Performance** - Top selling products this month
5. **On-Time Delivery** - Fulfillment performance score
6. **Avg Order Value** - Revenue per order trend

### Charts & Visualizations
1. **Revenue Trend** (Line chart) - 6 month revenue history
2. **Order Status Breakdown** (Donut chart) - Open/Receiving/Complete
3. **Top 10 Products** (Bar chart) - Units sold this month
4. **Fulfillment Timeline** (Area chart) - Days to fulfill orders

### Action Items Widget
- Urgent: Orders overdue (red)
- Important: Claims pending >7 days (orange)
- Info: Low stock alerts on top products (blue)

### Quick Actions
- ✅ View All Orders
- ✅ Submit New Claim
- ✅ Download Monthly Report
- ✅ Update Product Catalog

---

## 🛒 TAB 2: ORDERS (Purchase Orders)

### Primary Goal
Complete order management from creation to fulfillment with tracking.

### Summary Stats Row
1. **Total Orders** - All time count
2. **Active Orders** - Currently processing
3. **This Month Value** - Revenue last 30 days
4. **Avg Fulfillment Time** - Days to complete
5. **On-Time Rate** - % delivered on schedule

### Filters & Search
- **Status Filter**: All / Open / Receiving / Complete / Cancelled
- **Date Range**: Last 7/30/90 days, Custom range
- **Outlet Filter**: By store location
- **Search**: PO number, reference, product name

### Orders Table (Full Width)
Columns:
1. PO Number (clickable to detail)
2. Reference/Note
3. Date Created
4. Expected Delivery
5. Destination Store
6. Items Count
7. Total Value
8. Status (badge with color)
9. Actions (View / Track / Update / Export)

### Order Detail Modal
When clicking a PO:
- Header: PO# | Status | Created | Destination
- Line Items Table: Product | SKU | Qty Ordered | Qty Sent | Unit Cost | Total
- Timeline: Created → Sent → Received (with dates)
- Notes Section: Internal supplier notes
- Attachments: Invoices, delivery confirmations
- Actions: Mark as Sent / Add Tracking / Upload Invoice / Contact Support

### Bulk Actions
- Select multiple orders
- Bulk export to CSV
- Bulk status update
- Bulk invoice upload

### Quick Actions (Top Right)
- 📊 Export to CSV/Excel
- 🖨️ Print Orders
- 🔔 Enable Notifications
- ➕ Create New Order (if enabled)

---

## 🔧 TAB 3: WARRANTY CLAIMS

### Primary Goal
Efficient warranty claim management with photo uploads and status tracking.

### Summary Stats
1. **Total Claims** - All time
2. **Pending Review** - Awaiting supplier response
3. **Avg Response Time** - Hours to first response
4. **Approval Rate** - % claims approved
5. **Open Claims Value** - Total $ of active claims

### Filters
- Status: All / New / Pending / Open / Approved / Rejected / Closed
- Priority: All / Urgent / Normal / Low
- Date Range
- Product Category
- Store Location

### Claims Table
Columns:
1. Claim ID (clickable)
2. Date Submitted
3. Product Name + SKU
4. Issue Description (truncated)
5. Customer Impact (store reported)
6. Status (badge)
7. Days Open
8. Priority
9. Actions

### Claim Detail Modal
- Header: Claim# | Product | Status | Submitted
- Customer Issue: Full description from store
- Product Details: SKU, batch#, purchase date
- Photo Gallery: Customer uploaded images (zoom, download)
- Supplier Response Form:
  - Decision: Approve / Reject / Need More Info
  - Resolution: Replace / Refund / Repair
  - Notes: Internal response
  - Attachments: Upload RMA, shipping label
- Timeline: Submitted → Review → Decision → Resolution
- Communication Log: All messages between supplier & store

### Quick Actions
- 📤 Bulk Approve/Reject
- 📊 Export Claims Report
- 📧 Send Update to Multiple Claims
- 🔔 Set Reminder for Follow-up

---

## 📥 TAB 4: DOWNLOADS & ARCHIVES

### Primary Goal
Access to all reports, invoices, and historical data.

### Categories
1. **Monthly Reports** - Sales, inventory, performance
2. **Invoices** - All PO invoices
3. **Product Data** - Catalog exports
4. **Warranty Data** - Claims history
5. **Custom Reports** - Build your own

### Monthly Reports Section
Grid of cards (last 12 months):
- Month/Year
- Orders: X
- Revenue: $X
- Claims: X
- Download: PDF / Excel / CSV

### Invoice Archive
Table:
- Invoice# | PO# | Date | Amount | Status | Download

### Product Catalog Exports
- Full Catalog (all products)
- Active Products Only
- Price List
- Stock Levels (if shared)
- Format: Excel / CSV / PDF

### Custom Report Builder
Form:
1. Select Report Type: Sales / Claims / Products / Performance
2. Date Range
3. Filters: Category, Store, Status
4. Output Format: PDF / Excel / CSV
5. Schedule: One-time / Monthly / Weekly
6. Generate Report

---

## 📈 TAB 5: REPORTS & ANALYTICS

### Primary Goal
Deep insights into performance with actionable intelligence.

### Report Categories

#### 5.1 Sales Performance
- Revenue trend (daily/weekly/monthly)
- Top 10 products by revenue
- Top 10 products by units
- Revenue by store location
- Average order value trend
- Order frequency analysis

#### 5.2 Fulfillment Performance
- Avg days to fulfill orders
- On-time delivery rate
- Late order analysis (why/where)
- Fulfillment time by product category
- Best/worst performing fulfillment windows

#### 5.3 Product Performance
- Best sellers (units + revenue)
- Slow movers (inventory risk)
- Product return rate
- Warranty claim rate by product
- Customer satisfaction scores (if available)

#### 5.4 Store Performance
- Revenue by store location
- Order frequency by store
- Avg order size by store
- Top products per store
- Store preferences/patterns

#### 5.5 Financial Summary
- Monthly revenue summary
- YoY comparison
- Gross margin analysis (if cost shared)
- Payment status summary
- Outstanding invoices

### Interactive Dashboard
- Date range selector (last 7/30/90/365 days, custom)
- Compare periods (vs last period, vs last year)
- Export any chart/table
- Schedule automated reports

---

## ⚙️ TAB 6: ACCOUNT SETTINGS

### Primary Goal
Manage supplier profile, users, notifications, and preferences.

### Sections

#### 6.1 Company Profile
- Company Name
- Contact Person
- Email
- Phone
- Address
- Business Registration#
- Tax ID / GST Number
- Bank Details (for payments)
- Logo Upload

#### 6.2 User Management (if multi-user)
- List of users with access
- Add/Remove users
- Set permissions (Admin / View Only / Claims Only)
- Reset passwords
- Activity log

#### 6.3 Notification Settings
- Email notifications: On/Off for each type
  - New orders
  - Warranty claims
  - Low stock alerts
  - Payment received
- SMS notifications (optional)
- In-app notifications
- Notification frequency: Immediate / Daily Digest / Weekly

#### 6.4 Product Catalog Settings
- Auto-sync enabled
- Last sync date
- Sync frequency
- Product visibility settings
- Pricing rules

#### 6.5 Integration Settings
- API access (if provided)
- Webhook endpoints
- Third-party integrations
- Data export schedules

#### 6.6 Preferences
- Default currency
- Date format
- Time zone
- Language
- Dashboard layout preferences
- Default filters

#### 6.7 Security
- Change password
- Two-factor authentication
- Login history
- Active sessions
- IP whitelist (optional)

---

## 🎨 DESIGN SYSTEM

### Color Palette
- **Primary Blue**: #3b82f6 (buttons, links, active states)
- **Success Green**: #10b981 (approved, completed)
- **Warning Orange**: #f59e0b (pending, attention needed)
- **Danger Red**: #ef4444 (rejected, overdue, urgent)
- **Dark Gray**: #1f2937 (sidebar, headers)
- **Light Gray**: #f3f4f6 (backgrounds, cards)

### Typography
- **Font**: Inter (modern, readable)
- **Headings**: 600-800 weight
- **Body**: 400-500 weight
- **Small text**: 400 weight, 85% size

### Component Library
- **Cards**: White bg, subtle shadow, rounded corners
- **Badges**: Small colored pills for status
- **Buttons**: Rounded, clear hover states
- **Tables**: Striped rows, hover highlight, sticky headers
- **Modals**: Centered, backdrop blur, smooth animation
- **Charts**: Chart.js with consistent color scheme
- **Icons**: FontAwesome 6 (solid + brands)

### Responsive Breakpoints
- **Desktop**: 1200px+ (full 3-column layout)
- **Tablet**: 768-1199px (2-column layout)
- **Mobile**: <768px (single column, stacked)

---

## 🚀 PHASE 1 PRIORITY FEATURES (MVP)

### Must Have (Launch Blockers)
✅ Dashboard with 6 key metrics
✅ Orders table with filters & search
✅ Order detail modal
✅ Warranty claims table
✅ Claim detail modal with photos
✅ Basic downloads (monthly reports)
✅ Account settings (profile, notifications)

### Nice to Have (Post-Launch)
- Custom report builder
- Advanced analytics charts
- Bulk actions for orders
- API access for integrations
- Multi-user management
- Automated email reports

---

## 📱 MOBILE OPTIMIZATION

### Responsive Adaptations
- Sidebar → Hamburger menu
- Horizontal stats → Vertical stack
- Wide tables → Horizontal scroll + key columns only
- Charts → Simplified mobile versions
- Modals → Full screen on mobile
- Touch-friendly tap targets (min 44px)

---

## 🔔 NOTIFICATION SYSTEM

### Event Triggers
1. **New Order Created** → Email + In-app
2. **Warranty Claim Submitted** → Email + In-app + SMS (if enabled)
3. **Claim Aging** (>7 days no response) → Email reminder
4. **Order Overdue** (past expected delivery) → Email alert
5. **Payment Received** → Email confirmation
6. **Low Stock Alert** (on top products) → Email notification
7. **System Announcement** → In-app banner

### Notification Preferences
- Per-event enable/disable
- Frequency: Real-time / Hourly digest / Daily digest
- Channels: Email / SMS / In-app / Push (future)

---

## 📊 METRICS & KPIs TO TRACK

### Supplier Success Metrics
1. **Order Fulfillment Time** - Avg days from PO to delivery
2. **On-Time Delivery Rate** - % orders delivered by expected date
3. **Claim Response Time** - Avg hours to first response
4. **Claim Approval Rate** - % claims approved vs rejected
5. **Revenue Growth** - MoM and YoY trends
6. **Customer Satisfaction** - Store feedback scores (if available)
7. **Product Return Rate** - % products returned/claimed
8. **Average Order Value** - Revenue per PO

### Platform Engagement Metrics
1. **Login Frequency** - Days active per month
2. **Feature Usage** - Which tabs used most
3. **Response Rate** - % claims responded within 24h
4. **Export Usage** - Reports downloaded
5. **Portal Satisfaction** - Internal NPS surveys

---

## 🎯 SUCCESS CRITERIA

### For Suppliers
✅ Can see all orders in one place
✅ Can respond to warranty claims quickly
✅ Can download reports without asking staff
✅ Can track performance metrics
✅ Can update contact info easily

### For The Vape Shed
✅ Reduced support emails (suppliers self-serve)
✅ Faster warranty claim resolution
✅ Better supplier communication
✅ Automated reporting (less manual work)
✅ Data-driven supplier performance reviews

---

## 🛠️ TECHNICAL REQUIREMENTS

### Frontend
- Bootstrap 5.3 (grid, components)
- Chart.js 3.9 (visualizations)
- FontAwesome 6 (icons)
- Vanilla JS (interactions, AJAX)
- Responsive CSS (mobile-first)

### Backend
- PHP 8.1+ (strict types)
- MariaDB 10.5+ (database)
- Prepared statements (security)
- API endpoints (JSON responses)
- File uploads (PDFs, images)

### Infrastructure
- Cloudways hosting
- Apache + PHP-FPM
- SSL/HTTPS
- Daily backups
- Error logging

---

## 📝 NEXT STEPS

1. **Review this blueprint** - Confirm features & priorities
2. **Create demo pages** - Static HTML with fake data
3. **Get feedback** - What to keep/change/add
4. **Build Phase 1 (MVP)** - Core features first
5. **Test with suppliers** - Real user feedback
6. **Iterate** - Add nice-to-have features
7. **Launch** - Roll out to all suppliers

---

**Blueprint Status:** DRAFT - Awaiting Review
**Next Action:** Create static demo pages for each tab
