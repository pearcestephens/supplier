# 🗄️ SUPPLIER PORTAL - DATABASE MASTER REFERENCE
**Generated:** October 25, 2025  
**Database:** jcepnzzkmj  
**Application:** The Vape Shed Supplier Portal v3.0  

---

## 📋 TABLE OF CONTENTS
1. [Table Summary](#table-summary)
2. [Core Tables](#core-tables)
3. [Relationship Diagram](#relationship-diagram)
4. [Field Usage Guide](#field-usage-guide)
5. [Critical Fields Reference](#critical-fields-reference)
6. [Index Strategy](#index-strategy)

---

## 📊 TABLE SUMMARY

| Table Name | Purpose | Row Est. | Primary Key | Foreign Keys |
|-----------|---------|----------|-------------|--------------|
| `vend_suppliers` | Supplier master data | ~500 | `id` (varchar UUID) | - |
| `vend_consignments` | Purchase orders/transfers | ~50K | `id` (int) | `supplier_id`, `outlet_id` |
| `vend_products` | Product catalog | ~15K | `id` (varchar UUID) | `supplier_id` |
| `vend_inventory` | Stock levels per outlet | ~200K | Composite | `product_id`, `outlet_id` |
| `vend_outlets` | Store/warehouse locations | ~20 | `id` (varchar UUID) | - |
| `purchase_order_line_items` | PO line items | ~150K | `id` (int) | `consignment_id`, `product_id` |
| `faulty_products` | Warranty/damage claims | ~2K | `id` (int) | `product_id`, `supplier_id` |
| `supplier_portal_sessions` | Auth sessions | ~100 | `id` (int) | `supplier_id` |
| `supplier_activity_log` | Audit trail | ~10K | `id` (int) | `supplier_id` |

**Total Tables:** 9  
**Estimated Total Rows:** ~428,620

---

## 🔑 CORE TABLES

### 1️⃣ vend_suppliers
**Purpose:** Master supplier registry  
**Authentication:** Used for portal login via `id` UUID  

**Critical Fields (6-Point Check):**
```
✓ id              - varchar(100) PRIMARY KEY, UUID format
✓ name            - varchar(255), supplier company name
✓ email           - varchar(255), login email (magic link target)
✓ deleted_at      - datetime, soft delete marker
✓ created_at      - datetime, record creation
✓ updated_at      - datetime, last modification
```

**Query Pattern:**
```sql
-- Auth lookup
SELECT id, name, email FROM vend_suppliers 
WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
```

---

### 2️⃣ vend_consignments
**Purpose:** Purchase orders and stock transfers  
**Type Filter:** `transfer_category = 'PURCHASE_ORDER'`  

**Critical Fields:**
```
✓ id                    - int(11) PRIMARY KEY AUTO_INCREMENT
✓ supplier_id           - varchar(100), FK to vend_suppliers.id
✓ outlet_id             - varchar(100), FK to vend_outlets.id (destination)
✓ transfer_category     - varchar(50), 'PURCHASE_ORDER' or 'STOCK_TRANSFER'
✓ status                - varchar(50), 'OPEN', 'SENT', 'RECEIVING', 'RECEIVED', 'CLOSED'
✓ total_cost            - decimal(10,2), supplier cost (USE THIS FOR REVENUE!)
✓ tracking_number       - varchar(100), shipment tracking
✓ created_at            - datetime, order date
✓ deleted_at            - datetime, soft delete
```

**Key Statuses:**
- `OPEN` - Created, not yet sent
- `SENT` - Dispatched by supplier
- `RECEIVING` - Being received at store
- `RECEIVED` - Fully received
- `CLOSED` - Completed/archived

**Query Pattern:**
```sql
-- Supplier's orders
SELECT * FROM vend_consignments 
WHERE supplier_id = ? 
  AND transfer_category = 'PURCHASE_ORDER'
  AND deleted_at IS NULL
ORDER BY created_at DESC
```

---

### 3️⃣ vend_products
**Purpose:** Product catalog  
**Supplier Filter:** Products belong to suppliers  

**Critical Fields:**
```
✓ id              - varchar(100) PRIMARY KEY, UUID
✓ supplier_id     - varchar(100), FK to vend_suppliers.id
✓ name            - varchar(255), product name
✓ sku             - varchar(100), unique product code
✓ handle          - varchar(255), URL-friendly identifier
✓ supply_price    - decimal(10,2), cost from supplier
✓ retail_price    - decimal(10,2), selling price
✓ deleted_at      - datetime, soft delete
```

**Query Pattern:**
```sql
-- Active products for supplier
SELECT id, name, sku, supply_price 
FROM vend_products 
WHERE supplier_id = ? 
  AND deleted_at IS NULL
```

---

### 4️⃣ vend_inventory
**Purpose:** Stock levels per product per outlet  
**Composite Key:** product_id + outlet_id  

**Critical Fields:**
```
✓ product_id        - varchar(100), FK to vend_products.id
✓ outlet_id         - varchar(100), FK to vend_outlets.id
✓ inventory_level   - int(11), current stock count
✓ reorder_point     - int(11), low stock threshold
✓ restock_level     - int(11), target stock level
```

**Query Pattern:**
```sql
-- Stock health check
SELECT 
    COUNT(DISTINCT p.id) as total_products,
    COUNT(DISTINCT CASE WHEN i.inventory_level >= 10 THEN p.id END) as healthy
FROM vend_products p
LEFT JOIN vend_inventory i ON p.id = i.product_id
WHERE p.supplier_id = ?
```

---

### 5️⃣ purchase_order_line_items
**Purpose:** Individual items within purchase orders  
**Links:** Consignment → Products  

**Critical Fields:**
```
✓ id                - int(11) PRIMARY KEY AUTO_INCREMENT
✓ consignment_id    - int(11), FK to vend_consignments.id
✓ product_id        - varchar(100), FK to vend_products.id
✓ quantity          - int(11), ordered quantity
✓ received_quantity - int(11), actual received (can differ)
✓ cost              - decimal(10,2), unit cost
✓ sequence_number   - int(11), line number in PO
```

**Query Pattern:**
```sql
-- PO details with products
SELECT 
    li.id, li.quantity, li.cost,
    p.name, p.sku,
    i.inventory_level as current_stock
FROM purchase_order_line_items li
LEFT JOIN vend_products p ON li.product_id = p.id
LEFT JOIN vend_inventory i ON p.id = i.product_id
WHERE li.consignment_id = ?
ORDER BY li.sequence_number
```

---

### 6️⃣ faulty_products
**Purpose:** Warranty claims and damage reports  

**Critical Fields:**
```
✓ id            - int(11) PRIMARY KEY AUTO_INCREMENT
✓ product_id    - varchar(100), FK to vend_products.id
✓ supplier_id   - varchar(100), FK to vend_suppliers.id
✓ status        - varchar(50), claim status
✓ fault_type    - varchar(100), damage/defect category
✓ description   - text, detailed issue
✓ created_at    - datetime, claim date
```

**Query Pattern:**
```sql
-- Open warranty claims
SELECT COUNT(*) FROM faulty_products
WHERE supplier_id = ?
  AND status IN ('PENDING', 'IN_REVIEW')
```

---

### 7️⃣ supplier_portal_sessions
**Purpose:** Authentication session management  

**Critical Fields:**
```
✓ id              - int(11) PRIMARY KEY AUTO_INCREMENT
✓ supplier_id     - varchar(100), FK to vend_suppliers.id
✓ session_token   - varchar(255), unique session identifier
✓ expires_at      - datetime, session expiry
✓ created_at      - datetime, login time
✓ last_activity   - datetime, last action timestamp
```

**Query Pattern:**
```sql
-- Validate active session
SELECT supplier_id FROM supplier_portal_sessions
WHERE session_token = ?
  AND expires_at > NOW()
  AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
```

---

### 8️⃣ supplier_activity_log
**Purpose:** Audit trail for supplier actions  

**Critical Fields:**
```
✓ id              - int(11) PRIMARY KEY AUTO_INCREMENT
✓ supplier_id     - varchar(100), FK to vend_suppliers.id
✓ action          - varchar(100), action type
✓ reference_id    - varchar(100), related record ID
✓ reference_type  - varchar(50), record type
✓ ip_address      - varchar(45), user IP
✓ user_agent      - text, browser info
✓ created_at      - datetime, action timestamp
```

**Common Actions:**
- `LOGIN` - User authenticated
- `UPDATE_TRACKING` - Tracking number updated
- `VIEW_ORDER` - Order details viewed
- `EXPORT_DATA` - Data download

**Query Pattern:**
```sql
-- Recent activity
SELECT action, reference_type, created_at
FROM supplier_activity_log
WHERE supplier_id = ?
ORDER BY created_at DESC
LIMIT 10
```

---

### 9️⃣ vend_outlets
**Purpose:** Store/warehouse locations  

**Critical Fields:**
```
✓ id              - varchar(100) PRIMARY KEY, UUID
✓ name            - varchar(255), location name
✓ outlet_code     - varchar(50), short code
✓ deleted_at      - datetime, soft delete
```

**Query Pattern:**
```sql
-- Active outlets
SELECT id, name, outlet_code
FROM vend_outlets
WHERE deleted_at IS NULL
```

---

## 🔗 RELATIONSHIP DIAGRAM

```
vend_suppliers (id:UUID)
    ↓ supplier_id
    ├─→ vend_consignments (POs)
    │       ↓ id
    │       └─→ purchase_order_line_items
    │               ↓ product_id
    │               └─→ vend_products
    │
    ├─→ vend_products
    │       ↓ id + outlet_id
    │       └─→ vend_inventory (stock levels)
    │
    ├─→ faulty_products (warranty claims)
    ├─→ supplier_portal_sessions (auth)
    └─→ supplier_activity_log (audit)

vend_outlets (id:UUID)
    ↓ outlet_id
    ├─→ vend_consignments (destination)
    └─→ vend_inventory (location)
```

---

## 📖 FIELD USAGE GUIDE

### 🔴 CRITICAL: Use Correct Revenue Field
**❌ WRONG:** `vend_consignments.total_price` (doesn't exist)  
**❌ WRONG:** `vend_consignments.supply_price` (doesn't exist)  
**✅ CORRECT:** `vend_consignments.total_cost`

```sql
-- Revenue calculation
SELECT COALESCE(SUM(total_cost), 0) as revenue
FROM vend_consignments
WHERE supplier_id = ?
  AND transfer_category = 'PURCHASE_ORDER'
  AND DATE(created_at) >= ?
```

### 🔑 UUID vs Integer Keys
- **UUID (varchar 100):** `vend_suppliers.id`, `vend_products.id`, `vend_outlets.id`
- **Integer (auto_increment):** `vend_consignments.id`, `purchase_order_line_items.id`, `faulty_products.id`

**Always bind UUIDs as PDO::PARAM_STR:**
```php
$stmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_STR); // UUID
$stmt->bindValue(':consignment_id', $consignmentId, PDO::PARAM_INT); // Integer
```

### 🗑️ Soft Deletes
**All main tables use `deleted_at`:**
```sql
WHERE deleted_at IS NULL                          -- Active records
  OR deleted_at = '0000-00-00 00:00:00'          -- Also active (legacy)
  OR deleted_at = ''                              -- Also active (legacy)
```

### 📊 Stock Health Threshold
**Healthy stock = inventory_level >= 10**
```sql
CASE WHEN i.inventory_level >= 10 THEN 'HEALTHY' ELSE 'LOW' END
```

---

## 🎯 CRITICAL FIELDS REFERENCE

### Authentication
| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| vend_suppliers | id | varchar(100) | **Login identifier (UUID)** |
| vend_suppliers | email | varchar(255) | **Magic link target** |
| supplier_portal_sessions | session_token | varchar(255) | **Active session key** |

### Revenue Tracking
| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| vend_consignments | total_cost | decimal(10,2) | **Total PO value** |
| purchase_order_line_items | cost | decimal(10,2) | **Line item cost** |
| purchase_order_line_items | quantity | int(11) | **Units ordered** |

### Order Management
| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| vend_consignments | status | varchar(50) | **Order lifecycle state** |
| vend_consignments | transfer_category | varchar(50) | **'PURCHASE_ORDER' filter** |
| vend_consignments | tracking_number | varchar(100) | **Shipment tracking** |

### Inventory Control
| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| vend_inventory | inventory_level | int(11) | **Current stock** |
| vend_inventory | reorder_point | int(11) | **Low stock alert** |
| vend_inventory | restock_level | int(11) | **Target stock** |

---

## 🚀 INDEX STRATEGY

### High-Performance Indexes (MUST HAVE)
```sql
-- Supplier lookups (auth)
CREATE INDEX idx_supplier_email ON vend_suppliers(email);

-- PO queries
CREATE INDEX idx_consignment_supplier_category ON vend_consignments(supplier_id, transfer_category, status);
CREATE INDEX idx_consignment_created ON vend_consignments(created_at);

-- Product lookups
CREATE INDEX idx_product_supplier ON vend_products(supplier_id);
CREATE INDEX idx_product_sku ON vend_products(sku);

-- Inventory queries
CREATE INDEX idx_inventory_product ON vend_inventory(product_id);
CREATE INDEX idx_inventory_outlet ON vend_inventory(outlet_id);

-- Line items
CREATE INDEX idx_line_items_consignment ON purchase_order_line_items(consignment_id);

-- Sessions
CREATE INDEX idx_session_token ON supplier_portal_sessions(session_token);
CREATE INDEX idx_session_expires ON supplier_portal_sessions(expires_at);

-- Activity log
CREATE INDEX idx_activity_supplier ON supplier_activity_log(supplier_id, created_at);
```

---

## ✅ 6-POINT CHECK SUMMARY

Every field has been validated with:
1. **TYPE** - Data type and size
2. **NULL** - Nullable or required
3. **KEY** - Primary/Foreign/Index
4. **DEFAULT** - Default value
5. **EXTRA** - Auto-increment/Generated
6. **COMMENT** - Documentation

**Full 6-point details:** See `DATABASE_SCHEMA_6POINT_CHECK.txt`  
**CREATE statements:** See `DATABASE_CREATE_STATEMENTS.sql`

---

## 🔍 QUICK QUERIES

### Supplier Dashboard Stats
```sql
-- Total orders (30 days)
SELECT COUNT(*) FROM vend_consignments 
WHERE supplier_id = ? 
  AND transfer_category = 'PURCHASE_ORDER'
  AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);

-- Pending orders
SELECT COUNT(*) FROM vend_consignments
WHERE supplier_id = ?
  AND transfer_category = 'PURCHASE_ORDER'
  AND status IN ('OPEN', 'SENT');

-- Revenue (30 days)
SELECT COALESCE(SUM(total_cost), 0) FROM vend_consignments
WHERE supplier_id = ?
  AND transfer_category = 'PURCHASE_ORDER'
  AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);

-- Active products
SELECT COUNT(*) FROM vend_products
WHERE supplier_id = ? AND deleted_at IS NULL;

-- Stock health
SELECT 
    ROUND((COUNT(CASE WHEN i.inventory_level >= 10 THEN 1 END) / COUNT(*)) * 100, 1) as health_pct
FROM vend_products p
LEFT JOIN vend_inventory i ON p.id = i.product_id
WHERE p.supplier_id = ? AND p.deleted_at IS NULL;
```

---

**Last Updated:** October 25, 2025  
**Maintained By:** CIS Development Team  
**Version:** 3.0.0
