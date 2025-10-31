# 📦 Multi-Box Tracking Implementation Plan

**Date:** October 31, 2025
**Purpose:** Implement proper multi-parcel tracking using existing consignment infrastructure
**Status:** Planning Phase

---

## 🎯 Current Situation

### What Exists:
- ✅ `vend_consignments` - Main orders table (has single `tracking_number` field)
- ✅ `consignment_shipments` - Multiple shipments per consignment
- ✅ `consignment_parcels` - Multiple boxes per shipment
- ✅ Packing slip generation system
- ✅ Basic tracking update API

### What's Missing:
- ❌ UI to manage multiple tracking numbers
- ❌ Integration between orders and shipments/parcels tables
- ❌ Bulk tracking number upload
- ❌ Box-level status tracking
- ❌ Display of multiple tracking numbers on order detail page

---

## 📊 Database Structure (Existing)

### Table: `consignment_shipments`
```sql
CREATE TABLE consignment_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT,                    -- FK to vend_consignments.id
    delivery_mode VARCHAR(50),          -- 'courier', 'pickup', etc.
    tracking_number VARCHAR(100),       -- Shipment-level tracking
    carrier_name VARCHAR(50),           -- 'CourierPost', 'DHL', etc.
    status ENUM(...),                   -- 'pending', 'packed', 'in_transit', 'received'
    dest_name VARCHAR(100),             -- Delivery recipient
    dest_company VARCHAR(100),
    dest_addr1 VARCHAR(255),
    dest_addr2 VARCHAR(255),
    dest_suburb VARCHAR(100),
    dest_city VARCHAR(100),
    dest_postcode VARCHAR(20),
    packed_at DATETIME,
    shipped_at DATETIME,
    received_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transfer_id) REFERENCES vend_consignments(id)
);
```

### Table: `consignment_parcels`
```sql
CREATE TABLE consignment_parcels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT,                    -- FK to consignment_shipments.id
    box_number INT,                     -- 1, 2, 3, etc.
    parcel_number VARCHAR(50),          -- 'BOX-001', 'BOX-002'
    tracking_number VARCHAR(100),       -- Box-level tracking
    courier VARCHAR(50),                -- Carrier for this box
    status ENUM(...),                   -- 'pending', 'in_transit', 'received'
    weight_kg DECIMAL(10,2),
    dimensions VARCHAR(50),             -- '30x40x50cm'
    contents_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME,
    FOREIGN KEY (shipment_id) REFERENCES consignment_shipments(id)
);
```

---

## 🚀 Implementation Phases

### Phase 1: Display Multiple Tracking Numbers
**Files to modify:**
- `order-detail.php` - Show all parcels with tracking
- `orders.php` - Show parcel count in table

**New query for order detail:**
```php
// Get all shipments and parcels for this order
$stmt = $db->prepare("
    SELECT
        s.id as shipment_id,
        s.tracking_number as shipment_tracking,
        s.carrier_name,
        s.status as shipment_status,
        s.packed_at,
        s.shipped_at,
        s.received_at,
        p.id as parcel_id,
        p.box_number,
        p.parcel_number,
        p.tracking_number as parcel_tracking,
        p.courier as parcel_carrier,
        p.status as parcel_status,
        p.weight_kg,
        p.dimensions
    FROM consignment_shipments s
    LEFT JOIN consignment_parcels p ON s.id = p.shipment_id
    WHERE s.transfer_id = ?
    ORDER BY s.id, p.box_number
");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$parcels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
```

**UI Design:**
```html
<!-- Shipments & Parcels Section -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5><i class="fas fa-boxes"></i> Shipments & Tracking</h5>
    </div>
    <div class="card-body">
        <?php foreach ($shipments as $shipment): ?>
            <div class="shipment-block">
                <h6>Shipment #<?= $shipment['id'] ?> - <?= $shipment['carrier_name'] ?></h6>
                <div class="row">
                    <?php foreach ($shipment['parcels'] as $parcel): ?>
                        <div class="col-md-4">
                            <div class="parcel-card">
                                <div class="parcel-number">Box <?= $parcel['box_number'] ?></div>
                                <div class="tracking">
                                    <code><?= $parcel['tracking_number'] ?></code>
                                    <button onclick="copyTracking('<?= $parcel['tracking_number'] ?>')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div class="status-badge <?= $parcel['status'] ?>">
                                    <?= ucfirst($parcel['status']) ?>
                                </div>
                                <?php if ($parcel['weight_kg']): ?>
                                    <small><?= $parcel['weight_kg'] ?>kg</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

---

### Phase 2: Add Multiple Tracking Numbers
**New API:** `api/add-tracking-numbers.php`

**Features:**
- Add single tracking number
- Bulk upload (CSV/paste multiple)
- Auto-create shipment and parcel records
- Update consignment status

**UI Modal:**
```javascript
function addTrackingNumbers() {
    Swal.fire({
        title: 'Add Tracking Numbers',
        html: `
            <div class="form-group">
                <label>Method:</label>
                <select id="tracking_method" class="form-control" onchange="toggleTrackingInput()">
                    <option value="single">Single Tracking Number</option>
                    <option value="multiple">Multiple (One per line)</option>
                    <option value="csv">Upload CSV</option>
                </select>
            </div>

            <!-- Single Input -->
            <div id="single_input" class="mt-3">
                <input type="text" id="tracking_single" class="form-control" placeholder="Tracking number">
                <input type="text" id="carrier_single" class="form-control mt-2" placeholder="Carrier (e.g., CourierPost)">
            </div>

            <!-- Multiple Input -->
            <div id="multiple_input" class="mt-3" style="display:none;">
                <textarea id="tracking_multiple" class="form-control" rows="5"
                    placeholder="One tracking number per line&#10;ABC123456789&#10;XYZ987654321"></textarea>
                <input type="text" id="carrier_multiple" class="form-control mt-2" placeholder="Carrier (same for all)">
            </div>

            <!-- CSV Upload -->
            <div id="csv_input" class="mt-3" style="display:none;">
                <input type="file" id="tracking_csv" class="form-control" accept=".csv">
                <small class="text-muted">CSV format: tracking_number,carrier,weight_kg,dimensions</small>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Tracking',
        width: 600,
        preConfirm: () => {
            // Collect data and submit
        }
    });
}
```

**Backend Processing:**
```php
// api/add-tracking-numbers.php
$orderId = $input['order_id'];
$trackingNumbers = $input['tracking_numbers']; // Array of tracking numbers
$carrier = $input['carrier'];

// Create shipment record
$stmt = $db->prepare("
    INSERT INTO consignment_shipments (
        transfer_id,
        delivery_mode,
        carrier_name,
        status,
        created_at
    ) VALUES (?, 'courier', ?, 'pending', NOW())
");
$stmt->bind_param('is', $orderId, $carrier);
$stmt->execute();
$shipmentId = $db->insert_id;

// Create parcel records
$boxNumber = 1;
foreach ($trackingNumbers as $tracking) {
    $stmt = $db->prepare("
        INSERT INTO consignment_parcels (
            shipment_id,
            box_number,
            parcel_number,
            tracking_number,
            courier,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $parcelNumber = 'BOX-' . str_pad($boxNumber, 3, '0', STR_PAD_LEFT);
    $stmt->bind_param('iisss', $shipmentId, $boxNumber, $parcelNumber, $tracking, $carrier);
    $stmt->execute();
    $boxNumber++;
}

// Update main consignment status
$stmt = $db->prepare("
    UPDATE vend_consignments
    SET state = 'SENT', sent_at = NOW()
    WHERE id = ?
");
$stmt->bind_param('i', $orderId);
$stmt->execute();
```

---

### Phase 3: Update Tracking Status
**New API:** `api/update-parcel-status.php`

**Features:**
- Mark individual box as received
- Bulk mark all boxes
- Auto-update consignment when all boxes received

**Logic:**
```php
// Update parcel status
$stmt = $db->prepare("
    UPDATE consignment_parcels
    SET status = ?, received_at = NOW()
    WHERE id = ?
");
$stmt->bind_param('si', $status, $parcelId);
$stmt->execute();

// Check if all parcels in shipment are received
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received
    FROM consignment_parcels
    WHERE shipment_id = ?
");
$stmt->bind_param('i', $shipmentId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['total'] == $result['received']) {
    // All parcels received, update shipment
    $stmt = $db->prepare("
        UPDATE consignment_shipments
        SET status = 'received', received_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();

    // Check if all shipments received, update consignment
    // ... similar logic
}
```

---

### Phase 4: Bulk Actions
**Features:**
- Select multiple orders
- Generate packing slips for selected
- Export tracking numbers CSV
- Bulk mark as shipped

**UI Integration:**
```javascript
// Already have checkboxes in orders.php
// Add toolbar with bulk actions

function bulkGeneratePackingSlips() {
    const selected = getSelectedOrders();
    if (selected.length === 0) {
        alert('Please select orders first');
        return;
    }

    window.open('/supplier/api/generate-packing-slips.php?orders=' + selected.join(','), '_blank');
}

function bulkExportTracking() {
    const selected = getSelectedOrders();
    if (selected.length === 0) {
        alert('Please select orders first');
        return;
    }

    window.location.href = '/supplier/api/export-tracking-csv.php?orders=' + selected.join(',');
}

function bulkAddTracking() {
    const selected = getSelectedOrders();
    if (selected.length === 0) {
        alert('Please select orders first');
        return;
    }

    // Show modal to upload CSV with format:
    // order_id,tracking_number,carrier
    Swal.fire({
        title: 'Bulk Add Tracking',
        html: `
            <input type="file" id="bulk_csv" class="form-control" accept=".csv">
            <p class="mt-2">CSV Format: order_id, tracking_number, carrier</p>
        `,
        showCancelButton: true,
        confirmButtonText: 'Upload'
    });
}
```

---

### Phase 5: Notes & Substitutions
**New API:** `api/add-order-note.php`

**Features:**
- Supplier can add notes to order
- Notify of quantity changes
- Suggest product substitutions
- Upload supporting documents

**Database:**
```sql
CREATE TABLE consignment_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consignment_id INT,
    supplier_id VARCHAR(100),
    note_type ENUM('general', 'quantity_change', 'substitution', 'delay', 'other'),
    note_text TEXT,
    product_id VARCHAR(100),            -- If related to specific product
    quantity_change INT,                -- +/- change
    substitution_product_id VARCHAR(100), -- Suggested replacement
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consignment_id) REFERENCES vend_consignments(id)
);
```

**UI:**
```html
<button onclick="addOrderNote(<?= $orderId ?>)" class="btn btn-warning">
    <i class="fas fa-sticky-note"></i> Add Note / Report Issue
</button>

<script>
function addOrderNote(orderId) {
    Swal.fire({
        title: 'Add Note to Order',
        html: `
            <select id="note_type" class="form-control mb-2">
                <option value="general">General Note</option>
                <option value="quantity_change">Quantity Change</option>
                <option value="substitution">Product Substitution</option>
                <option value="delay">Delivery Delay</option>
            </select>
            <textarea id="note_text" class="form-control" rows="4" placeholder="Your message..."></textarea>
            <div id="product_select" style="display:none;">
                <select id="product_id" class="form-control mt-2">
                    <!-- Populated with order line items -->
                </select>
                <input type="number" id="qty_change" class="form-control mt-2" placeholder="Quantity change (+/-)">
            </div>
        `,
        showCancelButton: true
    });
}
</script>
```

---

### Phase 6: Invoice Upload
**New API:** `api/upload-invoice.php`

**Features:**
- Upload PDF invoice
- Link to order
- Store in secure location
- Display on order detail page

**Database:**
```sql
CREATE TABLE consignment_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consignment_id INT,
    supplier_id VARCHAR(100),
    invoice_number VARCHAR(100),
    invoice_date DATE,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consignment_id) REFERENCES vend_consignments(id)
);
```

**Upload Handler:**
```php
// api/upload-invoice.php
$orderId = $_POST['order_id'];
$invoiceNumber = $_POST['invoice_number'];
$invoiceDate = $_POST['invoice_date'];

if (isset($_FILES['invoice']) && $_FILES['invoice']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '/home/master/applications/jcepnzzkmj/private_html/invoices/';
    $fileName = uniqid('INV_') . '_' . basename($_FILES['invoice']['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['invoice']['tmp_name'], $filePath)) {
        $stmt = $db->prepare("
            INSERT INTO consignment_invoices
            (consignment_id, supplier_id, invoice_number, invoice_date, file_name, file_path, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $fileSize = filesize($filePath);
        $stmt->bind_param('isssssi', $orderId, $supplierId, $invoiceNumber, $invoiceDate, $fileName, $filePath, $fileSize);
        $stmt->execute();

        sendJsonResponse(true, ['invoice_id' => $db->insert_id], 'Invoice uploaded successfully');
    }
}
```

---

## 📋 Implementation Checklist

### Week 1: Display & Basic Tracking
- [ ] Update `order-detail.php` to show all parcels
- [ ] Update `orders.php` to show parcel count
- [ ] Test with existing data
- [ ] Create `api/add-tracking-numbers.php`
- [ ] Build UI modal for adding tracking

### Week 2: Status Updates & Bulk Actions
- [ ] Create `api/update-parcel-status.php`
- [ ] Add parcel status checkboxes on order detail
- [ ] Implement bulk packing slip generation
- [ ] Implement bulk tracking export
- [ ] Add bulk upload tracking CSV

### Week 3: Notes & Communication
- [ ] Create `consignment_notes` table
- [ ] Build `api/add-order-note.php`
- [ ] Add note display on order detail
- [ ] Implement quantity change notifications
- [ ] Add substitution suggestion system

### Week 4: Invoice & Polish
- [ ] Create `consignment_invoices` table
- [ ] Build `api/upload-invoice.php`
- [ ] Add invoice display on order detail
- [ ] Polish all UIs
- [ ] Comprehensive testing
- [ ] Documentation

---

## 🎯 Success Metrics

- ✅ Suppliers can add multiple tracking numbers per order
- ✅ Each box has individual tracking
- ✅ Status updates per box
- ✅ Bulk operations save time
- ✅ Clear communication channel via notes
- ✅ Invoice management integrated
- ✅ Packing slips match actual shipments

---

## 📞 Next Steps

1. **Review this plan** with stakeholders
2. **Prioritize features** (what's most urgent?)
3. **Start with Phase 1** (display existing tracking data)
4. **Iterate** based on supplier feedback

**Ready to start implementation when approved!** 🚀
