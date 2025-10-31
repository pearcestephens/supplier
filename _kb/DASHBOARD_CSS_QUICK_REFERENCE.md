# Dashboard CSS Quick Reference

**Purpose:** Quick lookup guide for all dashboard CSS classes
**File:** `/supplier/assets/css/style.css` (lines 445-795)
**Last Updated:** 2025-01-XX

---

## Metric Cards (KPIs)

### HTML Structure:
```html
<div class="col-md-6 col-xl-4">
    <div class="card metric-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <p class="text-muted mb-1 small">Label</p>
                    <h3 class="mb-0 fw-bold" id="metric-id">Value</h3>
                </div>
                <div class="metric-icon bg-primary">
                    <i class="fas fa-icon"></i>
                </div>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary"></div>
            </div>
        </div>
    </div>
</div>
```

### CSS Classes:
```css
.metric-card              /* Main card container */
.metric-card:hover        /* Lift + shadow on hover */
.metric-card.clickable    /* Cursor pointer variant */
.metric-icon              /* Circular icon container (48x48px) */
```

### Icon Colors (Gradients):
```css
.bg-primary   /* Blue gradient: #0d6efd → #0a58ca */
.bg-info      /* Blue gradient: #3b82f6 → #2563eb */
.bg-success   /* Green gradient: #10b981 → #059669 */
.bg-warning   /* Orange gradient: #f59e0b → #d97706 */
.bg-cyan      /* Cyan gradient: #06b6d4 → #0891b2 */
.bg-purple    /* Purple gradient: #a855f7 → #9333ea */
```

---

## Compact Table (Orders)

### HTML Structure:
```html
<div class="table-responsive">
    <table class="table table-sm table-hover compact-table mb-0">
        <thead class="table-header-sticky">
            <tr>
                <th>Column 1</th>
                <th>Column 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
            </tr>
        </tbody>
    </table>
</div>
```

### CSS Classes:
```css
.compact-table            /* Compact font size (0.875rem) */
.compact-table thead th   /* Uppercase, gray background */
.compact-table tbody td   /* Proper padding, borders */
.compact-table tbody tr:hover /* Highlight row */
.table-header-sticky      /* Sticky header (position: sticky) */
```

---

## Stock Alerts Grid

### HTML Structure:
```html
<div class="stock-alerts-grid">
    <div class="stock-alert-card">
        <div class="store-name">Store Name</div>
        <div class="alert-count">123</div>
        <div class="alert-label">Low Stock Items</div>
    </div>
</div>
```

### CSS Classes:
```css
.stock-alerts-grid        /* CSS Grid: auto-fill minmax(280px, 1fr) */
.stock-alert-card         /* Individual store card */
.stock-alert-card:hover   /* Hover: warning border + shadow + lift */
.store-name               /* Store name typography */
.alert-count              /* Large number display */
.alert-label              /* Label below count */
```

---

## Card Headers & Footers

### HTML Structure:
```html
<div class="card">
    <div class="card-header">
        <h5>Title</h5>
        <small class="text-muted">Subtitle</small>
    </div>
    <div class="card-body">
        Content
    </div>
    <div class="card-footer">
        Footer content
    </div>
</div>
```

### CSS Classes:
```css
.card-header              /* Light gray background, border-bottom */
.card-header h5           /* Title typography */
.card-header small        /* Subtitle typography */
.card-footer              /* Light gray background, border-top */
.card-footer.bg-white     /* White background variant */
.card-footer.bg-light     /* Light gray background variant */
```

---

## Loading States

### HTML Structure:
```html
<!-- Spinner -->
<div class="spinner-border spinner-border-sm" role="status"></div>

<!-- Skeleton -->
<h3 class="skeleton">--</h3>
```

### CSS Classes:
```css
.spinner-border-sm        /* Small spinner (1rem x 1rem) */
.skeleton                 /* Animated loading placeholder */
@keyframes loading        /* Shimmer animation */
```

---

## Responsive Breakpoints

### Desktop (> 1200px):
```css
/* Default styles apply */
- 3-column metric cards (col-xl-4)
- Stock alerts: ~4 cards per row
- Full-size icons (48px)
```

### Laptop (768px - 1200px):
```css
.stock-alerts-grid {
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}
- 2-column metric cards (col-md-6)
- Stock alerts: ~3 cards per row
```

### Mobile (< 768px):
```css
.metric-card .card-body { padding: 1rem; }
.metric-icon { width: 40px; height: 40px; }
.metric-card h3 { font-size: 1.5rem; }
.stock-alerts-grid { grid-template-columns: 1fr; }
.compact-table { font-size: 0.8rem; }
- 1-column metric cards
- 1 stock alert per row
- Smaller fonts throughout
```

---

## Utility Classes

### Spacing:
```css
.mb-4                     /* margin-bottom: 1.5rem (Bootstrap) */
.g-3                      /* gap: 1rem (Bootstrap grid) */
.p-0                      /* padding: 0 (Bootstrap) */
```

### Typography:
```css
.text-muted               /* color: #6b7280 */
.small                    /* font-size: 0.875rem */
.fw-bold                  /* font-weight: 700 */
```

### Layout:
```css
.d-flex                   /* display: flex */
.justify-content-between  /* justify-content: space-between */
.align-items-center       /* align-items: center */
```

---

## Color Palette

### Backgrounds:
```css
#ffffff   /* White (cards, backgrounds) */
#f9fafb   /* Very light gray (headers, footers) */
#000000   /* Black (sidebar) */
```

### Borders:
```css
#e5e7eb   /* Light gray (card borders) */
#f3f4f6   /* Very light gray (table rows) */
#d1d5db   /* Medium gray (dividers) */
```

### Text:
```css
#111827   /* Near black (headings) */
#374151   /* Dark gray (body text) */
#6b7280   /* Medium gray (muted text) */
#9ca3af   /* Light gray (subtle text) */
```

### Accents:
```css
#0d6efd   /* Primary blue (links, buttons) */
#f59e0b   /* Warning orange (alerts) */
#10b981   /* Success green (positive values) */
#dc2626   /* Danger red (errors) */
```

---

## Typography Scale

### Dashboard Sizes:
```css
h1 (Page Title):          2rem (32px)
h3 (Metric Value):        1.75rem (28px) desktop, 1.5rem (24px) mobile
h5 (Card Header):         1rem (16px)
Body Text:                0.875rem (14px)
Small Text:               0.75rem (12px)
Table Text:               0.875rem (14px) desktop, 0.8rem (13px) mobile
```

### Font Weights:
```css
Regular:                  400
Medium:                   500
Semibold:                 600
Bold:                     700
```

---

## Animation Durations

```css
Transitions:              0.3s ease (cards, buttons)
Hover Effects:            0.2s ease (links)
Loading Animation:        1.5s ease-in-out infinite
Progress Bars:            0.6s ease
```

---

## Shadow System

```css
/* Light shadow (default cards) */
box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);

/* Medium shadow (hover state) */
box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);

/* Top bar shadow */
box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
```

---

## Z-Index Layers

```css
Sidebar:                  1100
Page Header:              1000
Sticky Table Header:      10
Footer:                   1000
```

---

## Border Radius

```css
Cards:                    0.5rem (8px)
Metric Icons:             50% (circular)
Buttons:                  0.375rem (6px) Bootstrap default
Badges:                   0.25rem (4px)
Progress Bars:            3px
```

---

## Common Patterns

### Hover Effect (Card):
```css
.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}
```

### Hover Effect (Row):
```css
tr:hover {
    background: #f9fafb;
}
```

### Sticky Header:
```css
.table-header-sticky {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f9fafb;
}
```

### Responsive Grid:
```css
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
```

---

## Usage Examples

### Example 1: Add new metric card
```html
<div class="col-md-6 col-xl-4">
    <div class="card metric-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <p class="text-muted mb-1 small">New Metric</p>
                    <h3 class="mb-0 fw-bold">$1,234</h3>
                </div>
                <div class="metric-icon bg-purple">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Example 2: Add new table
```html
<div class="card">
    <div class="card-header">
        <h5>Table Title</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover compact-table mb-0">
                <thead class="table-header-sticky">
                    <tr>
                        <th>Column 1</th>
                        <th>Column 2</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Data 1</td>
                        <td>Data 2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

### Example 3: Add new grid section
```html
<div class="card">
    <div class="card-header">
        <h5>Grid Section</h5>
    </div>
    <div class="card-body p-0">
        <div class="stock-alerts-grid">
            <div class="stock-alert-card">
                <div class="store-name">Item Name</div>
                <div class="alert-count">42</div>
                <div class="alert-label">Count Label</div>
            </div>
        </div>
    </div>
</div>
```

---

## Browser DevTools Tips

### Inspect Metric Card:
1. Open DevTools (F12)
2. Select metric card element
3. Look for classes: `.metric-card`, `.metric-icon`
4. Check hover state in `:hov` panel

### Debug Grid Layout:
1. Select `.stock-alerts-grid` element
2. Go to Layout tab in DevTools
3. See CSS Grid overlay
4. Adjust `minmax()` values to change card size

### Test Responsive:
1. Open DevTools
2. Toggle device toolbar (Ctrl+Shift+M)
3. Test at: 375px (mobile), 768px (tablet), 1366px (laptop), 1920px (desktop)
4. Verify breakpoint changes apply correctly

---

**End of Quick Reference**
