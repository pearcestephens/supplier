# 🚀 IMMEDIATE ACTION PLAN - Multi-Box Tracking Integration

**Date:** October 31, 2025
**Status:** READY TO IMPLEMENT
**Estimated Time:** 2-3 hours for Phase 1

---

## ✅ VERIFIED - Database Already Has Everything!

### Tables Confirmed:
```
✅ consignment_shipments    - 12,261 records
✅ consignment_parcels       - 8,192 records
✅ Unique tracking numbers   - 8,185 tracking codes
```

### Sample Data Structure:
```
Order JCE-26914 (PO: JT-4557-6c9b8681)
  └─ Shipment #12462
      └─ Box 1: JCE-26914-MIGRATED (CourierPost) - Status: pending

Order JCE-26913 (PO: JT-4556-d92f4d98)
  └─ Shipment #12461
      └─ Box 1: JCE-26913-MIGRATED (CourierPost) - Status: received
```

**KEY INSIGHT:** Most orders currently have 1 shipment with 1 box, but the infrastructure supports multiple boxes per shipment!

---

## 🎯 Phase 1: Display Existing Tracking (START HERE - 2 hours)

### Step 1: Update Order Detail Page (60 min)

**File:** `order-detail.php`

**Add after line ~150 (after order info section):**

```php
<?php
// ========================================
// SHIPMENTS & TRACKING SECTION
// ========================================

// Get all shipments and parcels for this order
$shipments_query = "
    SELECT
        s.id as shipment_id,
        s.tracking_number as shipment_tracking,
        s.carrier_name,
        s.status as shipment_status,
        s.packed_at,
        s.dispatched_at,
        s.received_at,
        p.id as parcel_id,
        p.box_number,
        p.parcel_number,
        p.tracking_number as parcel_tracking,
        p.courier as parcel_carrier,
        p.status as parcel_status,
        p.weight_kg,
        p.length_mm,
        p.width_mm,
        p.height_mm,
        p.received_at as parcel_received_at
    FROM consignment_shipments s
    LEFT JOIN consignment_parcels p ON s.id = p.shipment_id AND p.deleted_at IS NULL
    WHERE s.transfer_id = ?
      AND s.deleted_at IS NULL
    ORDER BY s.id, p.box_number
";

$stmt = $db->prepare($shipments_query);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$shipments_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group parcels by shipment
$shipments = [];
foreach ($shipments_data as $row) {
    $sid = $row['shipment_id'];
    if (!isset($shipments[$sid])) {
        $shipments[$sid] = [
            'id' => $row['shipment_id'],
            'tracking' => $row['shipment_tracking'],
            'carrier' => $row['carrier_name'],
            'status' => $row['shipment_status'],
            'packed_at' => $row['packed_at'],
            'dispatched_at' => $row['dispatched_at'],
            'received_at' => $row['received_at'],
            'parcels' => []
        ];
    }

    if ($row['parcel_id']) {
        $shipments[$sid]['parcels'][] = [
            'id' => $row['parcel_id'],
            'box_number' => $row['box_number'],
            'parcel_number' => $row['parcel_number'],
            'tracking' => $row['parcel_tracking'],
            'carrier' => $row['parcel_carrier'] ?: $row['carrier_name'],
            'status' => $row['parcel_status'],
            'weight_kg' => $row['weight_kg'],
            'dimensions' => $row['length_mm'] && $row['width_mm'] && $row['height_mm']
                ? sprintf('%dx%dx%d mm', $row['length_mm'], $row['width_mm'], $row['height_mm'])
                : null,
            'received_at' => $row['parcel_received_at']
        ];
    }
}
?>

<?php if (!empty($shipments)): ?>
<!-- Shipments & Parcels Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-boxes"></i> Shipments & Tracking</h5>
        <button type="button" class="btn btn-sm btn-primary" onclick="addTrackingNumber(<?= $orderId ?>)">
            <i class="fas fa-plus"></i> Add Tracking
        </button>
    </div>
    <div class="card-body">
        <?php foreach ($shipments as $shipment): ?>
        <div class="shipment-block mb-4 p-3 border rounded">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h6 class="mb-1">Shipment #<?= $shipment['id'] ?></h6>
                    <span class="badge badge-<?= getShipmentStatusColor($shipment['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $shipment['status'])) ?>
                    </span>
                </div>
                <div class="col-md-6 text-right">
                    <?php if ($shipment['carrier']): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-truck"></i> <?= htmlspecialchars($shipment['carrier']) ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($shipment['dispatched_at']): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-calendar"></i> Dispatched: <?= date('d M Y', strtotime($shipment['dispatched_at'])) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($shipment['parcels'])): ?>
            <div class="row">
                <?php foreach ($shipment['parcels'] as $parcel): ?>
                <div class="col-md-4 mb-3">
                    <div class="parcel-card border rounded p-3 h-100" style="background: #f8f9fa;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>Box <?= $parcel['box_number'] ?></strong>
                            <span class="badge badge-<?= getParcelStatusColor($parcel['status']) ?>">
                                <?= ucfirst($parcel['status']) ?>
                            </span>
                        </div>

                        <?php if ($parcel['tracking']): ?>
                        <div class="tracking-info mb-2">
                            <small class="text-muted d-block">Tracking Number:</small>
                            <code class="d-inline-block text-break" style="font-size: 0.85em;">
                                <?= htmlspecialchars($parcel['tracking']) ?>
                            </code>
                            <button type="button" class="btn btn-sm btn-link p-0 ml-1"
                                    onclick="copyToClipboard('<?= htmlspecialchars($parcel['tracking']) ?>')"
                                    title="Copy tracking number">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if ($parcel['carrier']): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-shipping-fast"></i> <?= htmlspecialchars($parcel['carrier']) ?>
                        </small>
                        <?php endif; ?>

                        <?php if ($parcel['weight_kg']): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-weight"></i> <?= $parcel['weight_kg'] ?> kg
                        </small>
                        <?php endif; ?>

                        <?php if ($parcel['dimensions']): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-cube"></i> <?= $parcel['dimensions'] ?>
                        </small>
                        <?php endif; ?>

                        <?php if ($parcel['received_at']): ?>
                        <small class="text-success d-block mt-2">
                            <i class="fas fa-check-circle"></i> Received: <?= date('d M Y', strtotime($parcel['received_at'])) ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> No parcels recorded for this shipment yet.
                <button type="button" class="btn btn-sm btn-primary ml-2" onclick="addParcelToShipment(<?= $shipment['id'] ?>)">
                    Add Parcel
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Helper functions for status colors
function getShipmentStatusColor($status) {
    $colors = [
        'packed' => 'info',
        'in_transit' => 'primary',
        'partial' => 'warning',
        'received' => 'success',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getParcelStatusColor($status) {
    $colors = [
        'pending' => 'secondary',
        'labelled' => 'info',
        'manifested' => 'info',
        'in_transit' => 'primary',
        'received' => 'success',
        'missing' => 'danger',
        'damaged' => 'warning',
        'cancelled' => 'danger',
        'exception' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}
?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success toast
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Tracking number copied!',
            showConfirmButton: false,
            timer: 2000
        });
    });
}

function addTrackingNumber(orderId) {
    // TO BE IMPLEMENTED IN PHASE 2
    Swal.fire({
        title: 'Add Tracking Number',
        html: 'This feature will be available soon!',
        icon: 'info'
    });
}

function addParcelToShipment(shipmentId) {
    // TO BE IMPLEMENTED IN PHASE 2
    Swal.fire({
        title: 'Add Parcel',
        html: 'This feature will be available soon!',
        icon: 'info'
    });
}
</script>

<?php endif; ?>
```

### Step 2: Update Orders List Page (30 min)

**File:** `orders.php`

**Modify the main query to include parcel count:**

Find this section (around line 80):
```php
$query = "
    SELECT
        t.id,
        t.public_id,
        ...
    FROM vend_consignments t
    ...
";
```

**Replace with:**
```php
$query = "
    SELECT
        t.id,
        t.public_id,
        t.vend_number,
        t.state,
        t.created_at,
        t.expected_delivery_date,
        o.name as outlet_name,
        COUNT(DISTINCT ti.id) as item_count,
        COALESCE(SUM(ti.quantity), 0) as total_quantity,
        COALESCE(SUM(ti.quantity * ti.unit_cost), 0) as total_value,
        (SELECT COUNT(*) FROM consignment_parcels p
         JOIN consignment_shipments s ON p.shipment_id = s.id
         WHERE s.transfer_id = t.id
           AND p.deleted_at IS NULL
           AND s.deleted_at IS NULL) as parcel_count,
        (SELECT COUNT(DISTINCT p.tracking_number) FROM consignment_parcels p
         JOIN consignment_shipments s ON p.shipment_id = s.id
         WHERE s.transfer_id = t.id
           AND p.tracking_number IS NOT NULL
           AND p.deleted_at IS NULL
           AND s.deleted_at IS NULL) as tracking_count
    FROM vend_consignments t
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
    WHERE t.supplier_id = ?
      AND t.deleted_at IS NULL
      {$filters}
    GROUP BY t.id
    ORDER BY {$orderBy} {$orderDir}
    LIMIT ? OFFSET ?
";
```

**Then update the table to display parcel info:**

Find the table section and add a new column:
```php
<th>Items</th>
<th>Parcels</th> <!-- NEW COLUMN -->
<th>Total Value</th>
```

And in the data row:
```php
<td><?= $order['item_count'] ?> items (<?= $order['total_quantity'] ?> units)</td>
<td>
    <?php if ($order['parcel_count'] > 0): ?>
        <span class="badge badge-info">
            <?= $order['parcel_count'] ?> box<?= $order['parcel_count'] > 1 ? 'es' : '' ?>
        </span>
        <?php if ($order['tracking_count'] > 0): ?>
        <br>
        <small class="text-muted">
            <i class="fas fa-barcode"></i> <?= $order['tracking_count'] ?> tracking
        </small>
        <?php endif; ?>
    <?php else: ?>
        <span class="text-muted">-</span>
    <?php endif; ?>
</td>
<td>$<?= number_format($order['total_value'], 2) ?></td>
```

### Step 3: Add CSS Styling (10 min)

**File:** `assets/css/supplier-portal.css` (or create if doesn't exist)

```css
/* Shipments & Parcels Styling */
.shipment-block {
    background: #ffffff;
    transition: all 0.2s;
}

.shipment-block:hover {
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.parcel-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.parcel-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.tracking-info code {
    background: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

/* Status badge colors */
.badge-packed { background: #17a2b8; }
.badge-in_transit { background: #007bff; }
.badge-partial { background: #ffc107; }
.badge-received { background: #28a745; }
.badge-cancelled { background: #dc3545; }
.badge-pending { background: #6c757d; }
.badge-labelled { background: #17a2b8; }
.badge-manifested { background: #17a2b8; }
.badge-missing { background: #dc3545; }
.badge-damaged { background: #ffc107; }
.badge-exception { background: #ffc107; }
```

### Step 4: Test the Implementation (20 min)

**Test Checklist:**

1. **View orders list:**
   ```bash
   curl -I https://staff.vapeshed.co.nz/supplier/orders.php
   ```
   - Should show HTTP 200
   - Should display parcel counts
   - Should show tracking counts

2. **Click into order detail:**
   ```bash
   # Find an order with tracking
   mysql -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "
   SELECT c.id, c.public_id
   FROM vend_consignments c
   JOIN consignment_shipments s ON s.transfer_id = c.id
   JOIN consignment_parcels p ON p.shipment_id = s.id
   WHERE c.deleted_at IS NULL
     AND s.deleted_at IS NULL
     AND p.deleted_at IS NULL
   LIMIT 1;"
   ```
   - Should display shipments section
   - Should show all boxes with tracking
   - Should display status badges
   - Copy tracking button should work

3. **Check for SQL errors:**
   ```bash
   tail -50 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log
   ```

4. **Check different order states:**
   - Order with multiple boxes
   - Order with no parcels yet
   - Order with received parcels
   - Order with in-transit parcels

---

## 📊 Success Metrics for Phase 1

- ✅ Orders list shows parcel count for each order
- ✅ Order detail page displays all shipments
- ✅ Each box/parcel shows its tracking number
- ✅ Status badges display correctly
- ✅ Copy tracking number works
- ✅ No SQL errors in logs
- ✅ Page loads in < 2 seconds

---

## 🚀 What Happens After Phase 1

Once Phase 1 is complete and tested, you'll be able to:

1. **See existing tracking data** - View all the 8,000+ parcels already in system
2. **Verify data quality** - Confirm tracking numbers are correct
3. **Gather feedback** - Show suppliers and get their input
4. **Plan Phase 2** - Based on what users need most:
   - Add new tracking numbers
   - Update parcel status
   - Bulk operations
   - Notes & communication

---

## 💬 Questions Before Starting?

1. **Do you want to start with Phase 1 now?**
2. **Should I create backup files first?**
3. **Any specific orders you want to test with?**
4. **Any UI/design preferences?**

**Ready to implement when you are!** 🚀
