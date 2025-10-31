# 03 - API Reference

**Complete API endpoint documentation for The Vape Shed Supplier Portal**

---

## Table of Contents

1. [Overview](#overview)
2. [Request Format](#request-format)
3. [Response Format](#response-format)
4. [Error Handling](#error-handling)
5. [Dashboard APIs](#dashboard-apis)
6. [Orders APIs](#orders-apis)
7. [Warranty APIs](#warranty-apis)
8. [Authentication APIs](#authentication-apis)

---

## Overview

All API requests go through the unified endpoint:

```
POST /api/endpoint.php
Content-Type: application/json
```

The system uses a **unified envelope pattern** where the action parameter routes to handler classes using `module.method` format.

### Authentication

All API endpoints (except auth.login) require an authenticated session. The session is validated via:
- PHP session with `supplier_id` set
- OR Bearer token in Authorization header

**Failed authentication returns:**
```json
{
  "success": false,
  "error": "Authentication required",
  "code": 401
}
```

---

## Request Format

### Standard Request Envelope

```json
{
  "action": "module.method",
  "params": {
    "key": "value"
  }
}
```

**Example:**
```json
{
  "action": "dashboard.getStats",
  "params": {
    "date_range": 30
  }
}
```

### Action Routing

The `action` parameter splits on `.` to determine:
- **Module**: First part (e.g., `dashboard`) → loads `api/handlers/dashboard.php`
- **Method**: Second part (e.g., `getStats`) → calls `Handler_Dashboard::getStats()`

**Valid modules:**
- `dashboard` - Dashboard statistics and charts
- `orders` - Purchase order management
- `warranty` - Warranty claim handling
- `auth` - Authentication and session management

---

## Response Format

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully",
  "meta": {
    "timestamp": "2025-10-26 14:30:00"
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": "Error message here",
  "code": 400,
  "meta": {
    "timestamp": "2025-10-26 14:30:00"
  }
}
```

### HTTP Status Codes

- `200` - Success
- `400` - Bad Request (validation error)
- `401` - Unauthorized (auth required)
- `403` - Forbidden (access denied)
- `404` - Not Found
- `500` - Internal Server Error

---

## Error Handling

The endpoint automatically catches exceptions and formats them as JSON responses:

```php
try {
    $result = $handler->$method($params);
    sendJsonResponse(true, $result, 'Success');
} catch (Exception $e) {
    sendJsonResponse(false, null, $e->getMessage(), $e->getCode() ?: 500);
}
```

**Common error codes:**
- `400` - Invalid parameters, validation failures
- `401` - Not authenticated
- `404` - Resource not found (order, claim, etc.)
- `500` - Database errors, unexpected failures

---

## Dashboard APIs

### dashboard.getStats

Get key performance statistics for dashboard overview.

**Request:**
```json
{
  "action": "dashboard.getStats",
  "params": {
    "date_range": 30
  }
}
```

**Parameters:**
- `date_range` (optional, int) - Days to include (default: 30)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_orders": 45,
    "total_orders_trend": 15.2,
    "total_revenue": 12450.50,
    "total_revenue_trend": -5.3,
    "active_products": 127,
    "active_products_trend": 2.1,
    "pending_claims": 3,
    "pending_claims_trend": 0.0
  },
  "message": "Statistics retrieved"
}
```

**Trends:**
- Positive percentage = increase from previous period
- Negative percentage = decrease from previous period

---

### dashboard.getChartData

Get time-series data for Chart.js visualizations.

**Request:**
```json
{
  "action": "dashboard.getChartData",
  "params": {
    "chart_type": "revenue",
    "period": "monthly",
    "months": 6
  }
}
```

**Parameters:**
- `chart_type` (required, string) - One of: `revenue`, `orders`, `products`
- `period` (optional, string) - `daily`, `weekly`, `monthly` (default: `monthly`)
- `months` (optional, int) - Number of months back (default: 6)

**Response (Revenue Chart):**
```json
{
  "success": true,
  "data": {
    "labels": ["May", "Jun", "Jul", "Aug", "Sep", "Oct"],
    "datasets": [{
      "label": "Revenue ($)",
      "data": [8500, 9200, 11300, 10500, 12000, 12450],
      "backgroundColor": "rgba(59, 130, 246, 0.5)",
      "borderColor": "rgb(59, 130, 246)",
      "borderWidth": 2
    }]
  },
  "message": "Chart data retrieved"
}
```

**Response (Orders Chart):**
```json
{
  "success": true,
  "data": {
    "labels": ["Week 1", "Week 2", "Week 3", "Week 4"],
    "datasets": [{
      "label": "Orders",
      "data": [12, 15, 10, 18],
      "backgroundColor": "rgba(16, 185, 129, 0.5)",
      "borderColor": "rgb(16, 185, 129)",
      "borderWidth": 2
    }]
  }
}
```

---

### dashboard.getRecentActivity

Get activity feed for dashboard sidebar.

**Request:**
```json
{
  "action": "dashboard.getRecentActivity",
  "params": {
    "limit": 10
  }
}
```

**Parameters:**
- `limit` (optional, int) - Max items to return (default: 10)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 142,
      "type": "order_shipped",
      "title": "Order #JCE-PO-12345 Shipped",
      "description": "Tracking: ABC123456789",
      "timestamp": "2025-10-26 10:15:00",
      "icon": "truck",
      "color": "success"
    },
    {
      "id": 141,
      "type": "claim_accepted",
      "title": "Warranty Claim #789 Accepted",
      "description": "Product: Vaporesso XROS",
      "timestamp": "2025-10-25 16:30:00",
      "icon": "check-circle",
      "color": "info"
    }
  ],
  "message": "Activity retrieved"
}
```

**Activity Types:**
- `order_shipped` - Tracking number added
- `order_received` - New order received
- `claim_accepted` - Warranty accepted
- `claim_declined` - Warranty declined
- `note_added` - Note added to order/claim

---

### dashboard.getQuickStats

Get compact stats for sidebar quick view.

**Request:**
```json
{
  "action": "dashboard.getQuickStats",
  "params": {}
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_orders": 8,
    "urgent_orders": 2,
    "pending_claims": 3,
    "low_stock_products": 5
  }
}
```

---

## Orders APIs

### orders.getPending

Get orders requiring immediate action (open, sent, processing).

**Request:**
```json
{
  "action": "orders.getPending",
  "params": {
    "limit": 10
  }
}
```

**Parameters:**
- `limit` (optional, int) - Max orders to return (default: 10)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1234,
      "po_number": "JCE-PO-12345",
      "status": "URGENT",
      "outlet_name": "Auckland Central",
      "created_at": "2025-10-20 09:00:00",
      "due_date": "2025-10-27 17:00:00",
      "total_value": 2450.50,
      "items_count": 12,
      "units_count": 45
    }
  ],
  "message": "Pending orders retrieved"
}
```

**Status Values:**
- `URGENT` - Due within 2 days (PO_URGENT_THRESHOLD_DAYS)
- `OPEN` - Awaiting supplier action
- `SENT` - Dispatched by supplier
- `PROCESSING` - Being processed

---

### orders.getOrders

Get paginated, filtered list of all orders.

**Request:**
```json
{
  "action": "orders.getOrders",
  "params": {
    "page": 1,
    "per_page": 25,
    "status": "OPEN",
    "search": "JCE-PO-123",
    "date_from": "2025-10-01",
    "date_to": "2025-10-31"
  }
}
```

**Parameters:**
- `page` (optional, int) - Page number (default: 1)
- `per_page` (optional, int) - Items per page (default: 25)
- `status` (optional, string) - Filter by status
- `search` (optional, string) - Search PO number or outlet name
- `date_from` (optional, date) - Start date (YYYY-MM-DD)
- `date_to` (optional, date) - End date (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 1234,
        "po_number": "JCE-PO-12345",
        "status": "OPEN",
        "outlet_name": "Auckland Central",
        "created_at": "2025-10-20 09:00:00",
        "due_date": "2025-10-27 17:00:00",
        "total_value": 2450.50,
        "tracking_number": null,
        "items_count": 12,
        "units_count": 45
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 25,
      "total": 142,
      "pages": 6
    }
  }
}
```

---

### orders.getOrderDetail

Get complete details for a single order including line items.

**Request:**
```json
{
  "action": "orders.getOrderDetail",
  "params": {
    "id": 1234
  }
}
```

**Parameters:**
- `id` (required, int) - Order ID

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1234,
    "public_id": "JCE-PO-12345",
    "status": "OPEN",
    "outlet_name": "Auckland Central",
    "outlet_address": "123 Queen St, Auckland",
    "outlet_email": "auckland@vapeshed.co.nz",
    "outlet_phone": "09-123-4567",
    "created_at": "2025-10-20 09:00:00",
    "due_date": "2025-10-27 17:00:00",
    "total_price": 2450.50,
    "notes": "Please expedite delivery",
    "line_items": [
      {
        "id": 567,
        "product_id": "abc-123",
        "product_name": "Vaporesso XROS 3",
        "sku": "VAP-XROS3-BLK",
        "supplier_code": "VS-001",
        "quantity": 10,
        "cost": 45.00,
        "current_stock": 5
      }
    ]
  },
  "message": "Order details retrieved"
}
```

---

### orders.updateTracking

Add tracking number and mark order as shipped.

**Request:**
```json
{
  "action": "orders.updateTracking",
  "params": {
    "order_id": 1234,
    "tracking_number": "ABC123456789",
    "carrier": "NZ Post"
  }
}
```

**Parameters:**
- `order_id` (required, int) - Order ID
- `tracking_number` (required, string) - Tracking number
- `carrier` (required, string) - Carrier name

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 1234,
    "public_id": "JCE-PO-12345",
    "tracking_number": "ABC123456789",
    "carrier": "NZ Post",
    "state_changed": true,
    "new_state": "SENT"
  },
  "message": "Tracking information updated successfully"
}
```

**Business Logic:**
- Auto-transitions OPEN → SENT when tracking added
- If already SENT, just updates tracking info
- Logs activity for audit trail

---

### orders.addNote

Add supplier note to an order.

**Request:**
```json
{
  "action": "orders.addNote",
  "params": {
    "order_id": 1234,
    "note": "Out of stock, will ship partial order on Monday"
  }
}
```

**Parameters:**
- `order_id` (required, int) - Order ID
- `note` (required, string) - Note text

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 1234,
    "public_id": "JCE-PO-12345"
  },
  "message": "Note added successfully"
}
```

**Notes:**
- Appends timestamped note to order.notes field
- Format: `\n\n[YYYY-MM-DD HH:MM:SS] Supplier:\n{note}`
- Logs activity

---

### orders.updateStatus

Update order status (supplier-initiated state changes).

**Request:**
```json
{
  "action": "orders.updateStatus",
  "params": {
    "order_id": 1234,
    "new_status": "SENT"
  }
}
```

**Parameters:**
- `order_id` (required, int) - Order ID
- `new_status` (required, string) - New status: `SENT` or `CANCELLED`

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 1234,
    "public_id": "JCE-PO-12345",
    "old_status": "OPEN",
    "new_status": "SENT"
  },
  "message": "Order status updated successfully"
}
```

**Validation:**
- Can only change from OPEN status (except CANCELLED works anytime)
- Returns 400 if invalid state transition

---

### orders.requestInfo

Request additional information from Vape Shed staff.

**Request:**
```json
{
  "action": "orders.requestInfo",
  "params": {
    "order_id": 1234,
    "message": "Need clarification on delivery address - is this residential?"
  }
}
```

**Parameters:**
- `order_id` (required, int) - Order ID
- `message` (required, string) - Request message (max 1000 chars)

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 1234,
    "public_id": "JCE-PO-12345",
    "request_id": 78,
    "status": "pending"
  },
  "message": "Information request submitted successfully"
}
```

**Business Logic:**
- Creates ticket in `supplier_info_requests` table
- Staff receives notification (optional email integration)
- Supplier can view responses in their account

---

### orders.bulkExport

Export multiple orders to CSV.

**Request:**
```json
{
  "action": "orders.bulkExport",
  "params": {
    "order_ids": [1234, 1235, 1236],
    "format": "csv"
  }
}
```

**Parameters:**
- `order_ids` (required, array) - Array of order IDs
- `format` (optional, string) - Export format (default: `csv`)

**Response:**
```json
{
  "success": true,
  "data": {
    "filename": "orders_export_2025-10-26_143000.csv",
    "url": "/uploads/exports/orders_export_2025-10-26_143000.csv",
    "size": 8192,
    "count": 3
  },
  "message": "Export generated successfully"
}
```

**CSV Columns:**
- po_number, status, outlet, order_date, due_date, total, tracking_number, items, units

---

## Warranty APIs

### warranty.getList

Get paginated list of warranty claims.

**Request:**
```json
{
  "action": "warranty.getList",
  "params": {
    "page": 1,
    "per_page": 25,
    "status": "pending",
    "search": "XROS"
  }
}
```

**Parameters:**
- `page` (optional, int) - Page number (default: 1)
- `per_page` (optional, int) - Items per page (default: 25, max: 100)
- `status` (optional, string) - Filter: `all`, `pending`, `accepted`, `declined` (default: `all`)
- `search` (optional, string) - Search product name, SKU, or claim ID

**Response:**
```json
{
  "success": true,
  "data": {
    "claims": [
      {
        "id": 789,
        "product_id": "abc-123",
        "product_name": "Vaporesso XROS 3",
        "sku": "VAP-XROS3-BLK",
        "fault_description": "Device not charging",
        "customer_name": "John Smith",
        "outlet_id": 5,
        "outlet_name": "Auckland Central",
        "created_at": "2025-10-20 10:00:00",
        "supplier_status": 0,
        "supplier_status_label": "Pending",
        "notes_count": 2,
        "media_count": 3
      }
    ],
    "total": 142,
    "page": 1,
    "pages": 6,
    "per_page": 25
  }
}
```

**Supplier Status Values:**
- `0` - Pending (awaiting supplier response)
- `1` - Accepted (warranty approved)
- `2` - Declined (warranty rejected)

---

### warranty.getDetail

Get complete warranty claim details with notes and media.

**Request:**
```json
{
  "action": "warranty.getDetail",
  "params": {
    "fault_id": 789
  }
}
```

**Parameters:**
- `fault_id` (required, int) - Warranty claim ID

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "product_id": "abc-123",
    "product_name": "Vaporesso XROS 3",
    "sku": "VAP-XROS3-BLK",
    "supplier_id": "supplier-uuid-123",
    "fault_description": "Device not charging properly after 2 weeks",
    "customer_name": "John Smith",
    "customer_email": "john@example.com",
    "customer_phone": "021-123-4567",
    "purchase_date": "2025-10-05",
    "fault_date": "2025-10-18",
    "outlet_name": "Auckland Central",
    "outlet_code": "AKL-01",
    "supplier_status": 0,
    "time_created": "2025-10-20 10:00:00",
    "notes": [
      {
        "id": 45,
        "faulty_product_id": 789,
        "supplier_id": null,
        "note": "Customer states LED not lighting during charge",
        "author_type": "staff",
        "created_at": "2025-10-20 10:05:00"
      },
      {
        "id": 46,
        "faulty_product_id": 789,
        "supplier_id": "supplier-uuid-123",
        "note": "Requires device serial number for verification",
        "action": null,
        "internal_ref": null,
        "author_type": "supplier",
        "created_at": "2025-10-20 14:30:00"
      }
    ],
    "media": [
      {
        "id": 12,
        "faulty_product_id": 789,
        "file_path": "/uploads/warranty/789/device_front.jpg",
        "file_type": "image/jpeg",
        "created_at": "2025-10-20 10:02:00"
      }
    ]
  },
  "message": "Claim details retrieved"
}
```

---

### warranty.addNote

Add supplier note to warranty claim.

**Request:**
```json
{
  "action": "warranty.addNote",
  "params": {
    "fault_id": 789,
    "note": "Requires device serial number verification before approval",
    "action": "request_info",
    "internal_ref": "WC-2025-10-789"
  }
}
```

**Parameters:**
- `fault_id` (required, int) - Warranty claim ID
- `note` (required, string) - Note text
- `action` (optional, string) - Action type (e.g., `request_info`, `investigating`)
- `internal_ref` (optional, string) - Internal reference number

**Response:**
```json
{
  "success": true,
  "data": {
    "fault_id": 789,
    "note_preview": "Requires device serial number verification bef..."
  },
  "message": "Note added successfully"
}
```

**Business Logic:**
- Sets `supplier_update_status = 1` to notify staff
- Note stored in `faulty_product_notes` table
- Transaction-wrapped for data integrity

---

### warranty.processAction

Accept or decline warranty claim.

**Request (Accept):**
```json
{
  "action": "warranty.processAction",
  "params": {
    "action": "accept",
    "fault_id": 789,
    "resolution": "Replacing device under warranty. New unit will ship within 3 business days."
  }
}
```

**Request (Decline):**
```json
{
  "action": "warranty.processAction",
  "params": {
    "action": "decline",
    "fault_id": 789,
    "reason": "Device shows signs of liquid damage, not covered under warranty terms."
  }
}
```

**Parameters:**
- `action` (required, string) - `accept` or `decline`
- `fault_id` (required, int) - Warranty claim ID
- `resolution` (required if accepting, string) - Resolution notes
- `reason` (required if declining, string) - Decline reason

**Response:**
```json
{
  "success": true,
  "data": {
    "fault_id": 789,
    "action": "accept",
    "new_status": 1,
    "new_status_label": "Accepted"
  },
  "message": "Warranty claim accepted successfully"
}
```

**Validation:**
- Can only process claims with `supplier_status = 0` (pending)
- Returns 400 if already processed
- Transaction-wrapped: updates status + adds note atomically
- Logs activity with product details

---

## Authentication APIs

### auth.login

Authenticate supplier and create session.

**Request:**
```json
{
  "action": "auth.login",
  "params": {
    "email": "supplier@example.com",
    "password": "secret123"
  }
}
```

**Parameters:**
- `email` (required, string) - Supplier email
- `password` (required, string) - Password

**Response:**
```json
{
  "success": true,
  "data": {
    "supplier": {
      "id": "supplier-uuid-123",
      "name": "Vaporesso NZ",
      "email": "supplier@example.com"
    },
    "session_token": "abc123def456..."
  },
  "message": "Login successful"
}
```

**Notes:**
- Creates 24-hour session in `supplier_portal_sessions` table
- Stores `supplier_id` and `session_token` in PHP session
- TODO: Currently accepts any password - implement proper bcrypt hashing

---

### auth.logout

End session and destroy authentication.

**Request:**
```json
{
  "action": "auth.logout",
  "params": {}
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Logout successful"
}
```

**Actions:**
- Deletes session from `supplier_portal_sessions` table
- Calls `session_destroy()`
- Logs activity

---

### auth.getSession

Get current authenticated supplier details.

**Request:**
```json
{
  "action": "auth.getSession",
  "params": {}
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "supplier": {
      "id": "supplier-uuid-123",
      "name": "Vaporesso NZ",
      "email": "supplier@example.com",
      "phone": "09-123-4567",
      "contact_name": "Jane Smith",
      "brand_logo_url": "/uploads/logos/vaporesso.png",
      "primary_color": "#ff0000",
      "secondary_color": "#000000"
    },
    "authenticated": true
  },
  "message": "Session retrieved successfully"
}
```

**Usage:**
- Called on page load to verify session status
- Returns 401 if not authenticated
- Includes branding info for UI customization

---

## Common Patterns

### Multi-tenancy Filtering

ALL database queries must filter by `supplier_id`:

```sql
WHERE supplier_id = :supplier_id
  AND deleted_at IS NULL
```

This prevents data leaks between suppliers.

### Pagination

Standard pagination parameters:
- `page` - Page number (1-indexed)
- `per_page` - Items per page (default from PAGINATION_PER_PAGE constant)

Response includes:
```json
{
  "pagination": {
    "page": 1,
    "per_page": 25,
    "total": 142,
    "pages": 6
  }
}
```

### Activity Logging

Private helper method pattern:
```php
private function logActivity(string $action, int $resourceId, array $meta = []): void
{
    $sql = "INSERT INTO supplier_activity_log ...";
    // Log to database with timestamp, IP, user agent
}
```

Called after successful operations for audit trail.

### Transaction Safety

Critical operations use transactions:
```php
$this->pdo->beginTransaction();
try {
    // Multiple database operations
    $this->pdo->commit();
} catch (Exception $e) {
    $this->pdo->rollBack();
    throw $e;
}
```

Used in: `warranty.addNote`, `warranty.processAction`

---

## Testing APIs

### Using cURL

```bash
# Login
curl -X POST https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"auth.login","params":{"email":"test@example.com","password":"test123"}}'

# Get dashboard stats (with session cookie)
curl -X POST https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=abc123..." \
  -d '{"action":"dashboard.getStats","params":{"date_range":30}}'
```

### Using JavaScript (Frontend)

```javascript
async function callAPI(action, params = {}) {
    const response = await fetch('/api/endpoint.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action, params })
    });
    
    const data = await response.json();
    
    if (!data.success) {
        throw new Error(data.error);
    }
    
    return data.data;
}

// Usage
const stats = await callAPI('dashboard.getStats', { date_range: 30 });
```

---

## Rate Limiting

Currently NOT implemented but planned:
- 100 requests per minute per supplier
- 1000 requests per hour per supplier
- Returns `429 Too Many Requests` when exceeded

---

## Changelog

**Version 4.0.0** (Current)
- Unified envelope pattern across all handlers
- Standardized error codes
- Transaction safety for critical operations
- Activity logging for all mutations

**Version 3.0.0**
- PDO migration complete
- Multi-tenancy enforcement
- Magic link authentication

---

**Last Updated:** 2025-10-26  
**Next:** [04-AUTHENTICATION.md](04-AUTHENTICATION.md) - Detailed auth flow
