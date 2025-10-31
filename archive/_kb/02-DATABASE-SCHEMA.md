# 🗄️ Database Schema & Relationships

## Core Tables Reference

### 1. vend_suppliers (Supplier Master)
```sql
CREATE TABLE vend_suppliers (
  id VARCHAR(100) PRIMARY KEY,           -- UUID format (not binary!)
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  claim_email VARCHAR(150),
  phone VARCHAR(45),
  contact_name VARCHAR(45),
  bank_account VARCHAR(45),
  deleted_at VARCHAR(45),                -- Soft delete marker
  show_in_system INT DEFAULT 1,
  automatic_ordering INT DEFAULT 0,
  notification_eligible INT DEFAULT 1,
  enable_product_returns INT DEFAULT 1,
  -- ... other config fields
);
```

**Key Patterns:**
- **Primary Key:** VARCHAR(100) UUID (not binary UUID!)
- **Soft Delete:** `deleted_at IS NULL` or `!= '0000-00-00 00:00:00'`
- **Active Check:** `show_in_system = 1`

**Example Query:**
```php
$pdo = pdo();
$stmt = $pdo->prepare("
    SELECT id, name, email, phone 
    FROM vend_suppliers 
    WHERE id = ? 
    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
    LIMIT 1
");
$stmt->execute([$supplierId]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

### 2. vend_consignments (Purchase Orders/Transfers)
```sql
CREATE TABLE vend_consignments (
  id INT AUTO_INCREMENT PRIMARY KEY,         -- Internal DB ID
  public_id VARCHAR(40) NOT NULL UNIQUE,     -- Display ID: 'JCE-PO-12345'
  supplier_id VARCHAR(100),                  -- FK to vend_suppliers.id
  
  -- Status & Tracking
  state ENUM('DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING',
             'PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED'),
  tracking_number VARCHAR(100),
  tracking_carrier VARCHAR(50),
  tracking_url VARCHAR(255),
  tracking_updated_at TIMESTAMP,
  
  -- Dates
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expected_delivery_date DATE,
  sent_at DATETIME,
  received_at DATETIME,
  supplier_sent_at TIMESTAMP,
  supplier_acknowledged_at TIMESTAMP,
  
  -- Outlets
  outlet_from VARCHAR(100),                  -- Source outlet UUID
  outlet_to VARCHAR(100),                    -- Destination outlet UUID
  
  -- Financial
  total_cost DECIMAL(10,2) DEFAULT 0.00,
  supplier_invoice_number VARCHAR(100),
  
  -- Soft Delete
  deleted_at TIMESTAMP NULL,
  
  -- Indexes
  KEY idx_supplier_id (supplier_id),
  KEY idx_state (state),
  KEY idx_public_id (public_id),
  KEY idx_tracking_number (tracking_number)
);
```

**Key Patterns:**
- **Public ID:** Use for display (`JCE-PO-12345`), never internal `id`
- **State Machine:** OPEN → SENT → RECEIVING → RECEIVED → CLOSED
- **Multi-tenancy:** ALWAYS filter by `supplier_id`
- **Soft Delete:** Check `deleted_at IS NULL`

**Example: Get Supplier Orders**
```php
$stmt = pdo()->prepare("
    SELECT 
        c.id,
        c.public_id,
        c.state,
        c.tracking_number,
        c.total_cost,
        c.created_at,
        o.name as outlet_name
    FROM vend_consignments c
    LEFT JOIN vend_outlets o ON c.outlet_to = o.id
    WHERE c.supplier_id = ?
    AND c.deleted_at IS NULL
    AND c.state IN ('OPEN', 'SENT', 'RECEIVING')
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$supplierId, $perPage, $offset]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

### 3. vend_products (Product Catalog)
```sql
CREATE TABLE vend_products (
  id VARCHAR(100) PRIMARY KEY,               -- UUID format
  supplier_id VARCHAR(200),                  -- FK to vend_suppliers.id
  
  -- Identity
  name VARCHAR(255),
  sku VARCHAR(200),
  variant_name VARCHAR(255),
  handle VARCHAR(200),
  
  -- Status
  active INT DEFAULT 0,                      -- 1 = active
  is_active INT DEFAULT 0,                   -- Duplicate field
  has_inventory INT DEFAULT 0,
  deleted_at TIMESTAMP DEFAULT '0000-00-00 00:00:00',
  
  -- Pricing
  price_excluding_tax DECIMAL(13,5),
  price_including_tax DECIMAL(13,5),
  supply_price DECIMAL(13,5),
  
  -- Categorization
  brand VARCHAR(255),
  brand_id VARCHAR(200),
  supplier VARCHAR(200),                     -- Text name (legacy)
  
  -- Metadata
  description LONGTEXT,
  image_url TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  -- Indexes
  KEY idx_supplier_active (supplier_id, is_active, active, deleted_at),
  FULLTEXT KEY ProductFullSearch (name)
);
```

**Key Patterns:**
- **Active Check:** `active = 1 AND is_active = 1 AND deleted_at = '0000-00-00 00:00:00'`
- **Supplier Filter:** `supplier_id = ?`
- **Full Text Search:** Use `MATCH(name) AGAINST(? IN BOOLEAN MODE)`

**Example: Get Active Products**
```php
$stmt = pdo()->prepare("
    SELECT 
        id, 
        name, 
        sku, 
        supply_price,
        brand
    FROM vend_products
    WHERE supplier_id = ?
    AND active = 1
    AND is_active = 1
    AND deleted_at = '0000-00-00 00:00:00'
    ORDER BY name ASC
");
$stmt->execute([$supplierId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

### 4. vend_inventory (Stock Levels)
```sql
CREATE TABLE vend_inventory (
  id VARCHAR(100) PRIMARY KEY,
  product_id VARCHAR(100),                   -- FK to vend_products.id
  outlet_id VARCHAR(100),                    -- FK to vend_outlets.id
  
  -- Stock Levels
  current_amount INT NOT NULL,               -- Current stock qty
  inventory_level INT NOT NULL,              -- Same as current_amount
  reorder_point INT,                         -- Min stock trigger
  reorder_amount INT,                        -- Qty to reorder
  average_cost DECIMAL(16,6),
  
  deleted_at TIMESTAMP NULL,
  version BIGINT,
  
  KEY product_id (product_id),
  KEY outlet_id (outlet_id),
  KEY idx_inventory_stock_level (current_amount, reorder_point)
);
```

**Key Patterns:**
- **Stock Health:**
  - `current_amount >= 50` = Healthy (green)
  - `current_amount >= 10` = Medium (yellow)
  - `current_amount >= 1` = Low (orange)
  - `current_amount < 1` = Out of Stock (red)
- **Alert Threshold:** `current_amount < reorder_point`

**Example: Low Stock Report**
```php
$stmt = pdo()->prepare("
    SELECT 
        p.name,
        p.sku,
        i.current_amount,
        i.reorder_point,
        o.name as outlet_name
    FROM vend_inventory i
    JOIN vend_products p ON i.product_id = p.id
    JOIN vend_outlets o ON i.outlet_id = o.id
    WHERE p.supplier_id = ?
    AND i.current_amount < i.reorder_point
    AND i.current_amount > 0
    AND i.deleted_at IS NULL
    ORDER BY i.current_amount ASC
    LIMIT 20
");
$stmt->execute([$supplierId]);
$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

### 5. faulty_products (Warranty Claims)
```sql
CREATE TABLE faulty_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(45),                    -- FK to vend_products.id
  
  -- Claim Details
  serial_number VARCHAR(100),
  fault_desc MEDIUMTEXT NOT NULL,
  store_location VARCHAR(100),
  staff_member VARCHAR(45),
  
  -- Status
  status INT DEFAULT 1,                      -- Internal status
  supplier_status INT DEFAULT 0,             -- 0=Pending, 1=Accepted
  supplier_update_status INT DEFAULT 0,
  
  -- Timestamps
  time_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  supplier_status_timestamp TIMESTAMP NULL,  -- When supplier responded
  
  KEY idx_product (product_id),
  KEY idx_supplier_status (supplier_status)
);
```

**Key Patterns:**
- **Pending Claims:** `supplier_status = 0`
- **Accepted Claims:** `supplier_status = 1`
- **Response Time:** `DATEDIFF(supplier_status_timestamp, time_created)`
- **Join to Products:** Get supplier_id via vend_products

**Example: Pending Warranty Claims**
```php
$stmt = pdo()->prepare("
    SELECT 
        fp.id,
        fp.fault_desc,
        fp.serial_number,
        fp.time_created,
        fp.store_location,
        p.name as product_name,
        p.sku
    FROM faulty_products fp
    JOIN vend_products p ON fp.product_id = p.id
    WHERE p.supplier_id = ?
    AND fp.supplier_status = 0
    ORDER BY fp.time_created DESC
");
$stmt->execute([$supplierId]);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

### 6. vend_outlets (Store Locations)
```sql
CREATE TABLE vend_outlets (
  id VARCHAR(100) PRIMARY KEY,               -- UUID
  name VARCHAR(100) NOT NULL,
  store_code VARCHAR(45),                    -- Short code: 'AKL', 'WLG'
  
  -- Address
  physical_address_1 VARCHAR(100),
  physical_city VARCHAR(255),
  physical_postcode VARCHAR(100),
  physical_phone_number VARCHAR(45),
  
  -- Settings
  is_warehouse INT DEFAULT 0,                -- 1 if supplier warehouse
  deleted_at TIMESTAMP NULL,
  
  KEY idx_store_code (store_code),
  KEY idx_warehouse (is_warehouse)
);
```

**Example: Get Outlet Details**
```php
$stmt = pdo()->prepare("
    SELECT id, name, store_code, physical_city 
    FROM vend_outlets 
    WHERE id = ? 
    AND deleted_at IS NULL
");
$stmt->execute([$outletId]);
$outlet = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

### 7. supplier_portal_sessions (Session Tracking)
```sql
CREATE TABLE supplier_portal_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id VARCHAR(100) NOT NULL,         -- FK to vend_suppliers.id
  session_token VARCHAR(64) UNIQUE NOT NULL,
  
  -- Security
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  
  -- Lifecycle
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,             -- Typically +24 hours
  last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  KEY idx_session_token (session_token),
  KEY idx_supplier_active (supplier_id, expires_at),
  FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE
);
```

**Example: Validate Session**
```php
$stmt = pdo()->prepare("
    SELECT supplier_id 
    FROM supplier_portal_sessions 
    WHERE session_token = ? 
    AND expires_at > NOW()
");
$stmt->execute([$sessionToken]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## Table Relationships

```
┌─────────────────┐
│ vend_suppliers  │
│ (id = UUID)     │
└────────┬────────┘
         │ 1
         │
         │ many
    ┌────┴─────────────────────┬─────────────────────┐
    │                          │                     │
┌───▼────────────┐    ┌────────▼────────┐   ┌──────▼──────────┐
│ vend_products  │    │vend_consignments│   │ supplier_portal │
│ (supplier_id)  │    │ (supplier_id)   │   │    _sessions    │
└───┬────────────┘    └─────────────────┘   └─────────────────┘
    │ 1                                      
    │
    │ many
┌───▼─────────────┐
│ vend_inventory  │
│ (product_id)    │
└─────────────────┘

┌────────────────┐
│ vend_products  │
│ (id = UUID)    │
└────┬───────────┘
     │ 1
     │
     │ many
┌────▼────────────┐
│faulty_products  │
│ (product_id)    │
└─────────────────┘
```

## Common Query Patterns

### Pattern 1: Supplier Dashboard Stats
```php
// Total orders (30 days)
$stmt = pdo()->prepare("
    SELECT COUNT(*) as total
    FROM vend_consignments
    WHERE supplier_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND deleted_at IS NULL
");
$stmt->execute([$supplierId]);
$totalOrders = $stmt->fetchColumn();

// Revenue (30 days)
$stmt = pdo()->prepare("
    SELECT SUM(total_cost) as revenue
    FROM vend_consignments
    WHERE supplier_id = ?
    AND state IN ('RECEIVED', 'CLOSED')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND deleted_at IS NULL
");
$stmt->execute([$supplierId]);
$revenue = $stmt->fetchColumn() ?? 0;
```

### Pattern 2: Product Search with Stock
```php
$searchTerm = "%{$search}%";
$stmt = pdo()->prepare("
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.supply_price,
        SUM(i.current_amount) as total_stock
    FROM vend_products p
    LEFT JOIN vend_inventory i ON p.id = i.product_id AND i.deleted_at IS NULL
    WHERE p.supplier_id = ?
    AND p.active = 1
    AND p.deleted_at = '0000-00-00 00:00:00'
    AND (p.name LIKE ? OR p.sku LIKE ?)
    GROUP BY p.id, p.name, p.sku, p.supply_price
    ORDER BY p.name ASC
    LIMIT 50
");
$stmt->execute([$supplierId, $searchTerm, $searchTerm]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Pattern 3: Order Details with Line Items
```php
$stmt = pdo()->prepare("
    SELECT 
        c.*,
        o.name as outlet_name,
        COUNT(li.product_id) as item_count
    FROM vend_consignments c
    LEFT JOIN vend_outlets o ON c.outlet_to = o.id
    LEFT JOIN purchase_order_line_items li ON c.id = li.purchase_order_id
    WHERE c.id = ?
    AND c.supplier_id = ?
    GROUP BY c.id
");
$stmt->execute([$orderId, $supplierId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
```

## Index Strategy

### Compound Indexes for Performance
```sql
-- Multi-tenancy queries (supplier + filters)
KEY idx_supplier_state (supplier_id, state, created_at)
KEY idx_supplier_active (supplier_id, active, deleted_at)

-- Date range queries
KEY idx_created_at (created_at)
KEY idx_date_range (created_at, state)

-- Search & Lookup
KEY idx_public_id (public_id)
KEY idx_tracking (tracking_number)
FULLTEXT KEY ProductSearch (name)
```

### Query Optimization Tips
1. **Always use supplier_id first** in WHERE clause (compound index)
2. **Use EXPLAIN** to verify index usage
3. **Limit result sets** with pagination
4. **Avoid SELECT *** - specify needed columns
5. **Use JOIN instead of subqueries** for better performance

---
**Related:**
- Architecture: `01-ARCHITECTURE.md`
- API Reference: `03-API-REFERENCE.md`
- Code Snippets: `09-CODE-SNIPPETS.md`
