# ✅ UNIFIED API ARCHITECTURE - COMPLETE

## 🎯 Single Endpoint Design (DRY & Modular)

### Overview
- **ONE** entry point: `/supplier/api/index.php`
- **Modular** actions in `/supplier/api/modules/`
- **Standard** JSON envelope for all responses
- **Professional** error modal display
- **Consistent** error handling across all API calls

## �� Standard JSON Envelope

### Success Response
```json
{
  "success": true,
  "data": {...},
  "message": "Operation completed successfully",
  "timestamp": "2025-10-30T12:00:00Z",
  "request_id": "req_67432abc..."
}
```

### Error Response
```json
{
  "success": false,
  "message": "User-friendly error message",
  "error": {
    "code": "ERROR_CODE",
    "message": "Detailed error description",
    "details": "Technical details (if DEBUG_MODE)",
    "field": "fieldName"
  },
  "timestamp": "2025-10-30T12:00:00Z",
  "request_id": "req_67432abc..."
}
```

## 🔌 API Usage

### JavaScript (Frontend)
```javascript
// Using unified API handler
const data = await API.call('dashboard-stats', {}, {
    loadingElement: '#my-button',
    showSuccess: true
});

// With form data
const result = await API.call('update-profile', {
    name: 'John Doe',
    email: 'john@example.com'
}, {
    showSuccess: true,
    loadingElement: '#save-button'
});
```

### cURL (Testing)
```bash
# Health check (GET)
curl "https://staff.vapeshed.co.nz/supplier/api/?action=health"

# API call (POST)
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats" \
  -H "Content-Type: application/json" \
  -b cookies.txt

# With JSON body
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/" \
  -H "Content-Type: application/json" \
  -d '{"action": "update-profile", "name": "John Doe"}' \
  -b cookies.txt
```

## 📁 Directory Structure

```
api/
├── index.php                 ← SINGLE ENTRY POINT
├── modules/                  ← MODULAR ACTIONS
│   ├── dashboard-stats.php
│   ├── dashboard-charts.php
│   ├── dashboard-orders-table.php
│   ├── update-profile.php
│   ├── add-order-note.php
│   └── ... (more modules)
```

## 🛠️ Creating New API Modules

### Step 1: Create Module File
Create `/supplier/api/modules/my-action.php`:

```php
<?php
/**
 * My Action Module
 * Description of what this module does
 */

// Auth check (if required)
requireAuth();

// Get data
$pdo = pdo();
$supplierID = getSupplierID();

// Validate input
$name = $_POST['name'] ?? null;
if (!$name) {
    sendApiResponse(false, null, 'Validation failed', [
        'code' => 'VALIDATION_ERROR',
        'message' => 'Name is required',
        'field' => 'name'
    ], 400);
}

// Process request
$stmt = $pdo->prepare("UPDATE suppliers SET name = ? WHERE id = ?");
$stmt->execute([$name, $supplierID]);

// Send success response
sendApiResponse(true, [
    'updated' => true,
    'name' => $name
], 'Profile updated successfully');
```

### Step 2: Call from JavaScript
```javascript
const result = await API.call('my-action', {
    name: 'New Name'
}, {
    showSuccess: true,
    loadingElement: '#save-btn'
});
```

## 🎨 Professional Error Modals

Errors automatically display in professional Bootstrap modals with:
- ✅ User-friendly error titles
- ✅ Detailed error messages
- ✅ Error codes for debugging
- ✅ Request ID for tracking
- ✅ Reload page button
- ✅ Technical details (if DEBUG_MODE)

### Error Modal Example
![Error Modal](docs/error-modal-example.png)

Features:
- Red danger header with icon
- Clear error message
- Technical details (collapsible)
- Error code + Request ID
- Close and Reload buttons

## 📊 Available Actions

### Dashboard
- `dashboard-stats` - Metric cards data
- `dashboard-charts` - Chart data
- `dashboard-orders-table` - Recent orders table
- `dashboard-stock-alerts` - Low stock alerts

### Orders
- `add-order-note` - Add note to order
- `update-tracking` - Update tracking number
- `request-info` - Request more info from customer

### Profile
- `update-profile` - Update supplier profile
- `update-password` - Change password

### Sidebar
- `sidebar-stats` - Sidebar notification counts

## 🔒 Security Features

1. **Method Validation**: Only accepts POST (except health check)
2. **Action Name Sanitization**: Alphanumeric, hyphens, underscores only
3. **Module Isolation**: Each module is a separate file
4. **Request ID Tracking**: Unique ID for every request
5. **Error Logging**: All errors logged with context
6. **CORS Protection**: Same-origin credentials required

## 🎯 Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `SUCCESS` | 200 | Request successful |
| `VALIDATION_ERROR` | 400 | Input validation failed |
| `METHOD_NOT_ALLOWED` | 405 | Wrong HTTP method |
| `MISSING_ACTION` | 400 | No action parameter |
| `INVALID_ACTION` | 400 | Invalid action name |
| `ACTION_NOT_FOUND` | 404 | Module doesn't exist |
| `AUTH_ERROR` | 401 | Authentication failed |
| `PERMISSION_ERROR` | 403 | Insufficient permissions |
| `DATABASE_ERROR` | 500 | Database query failed |
| `SERVER_ERROR` | 500 | General server error |
| `NETWORK_ERROR` | - | Client-side network issue |

## ✅ Migration Complete

### Old Structure (22 separate files)
```
api/
├── dashboard-stats.php
├── dashboard-charts.php
├── update-profile.php
├── add-order-note.php
├── ... (18 more files)
```

### New Structure (1 entry point + modules)
```
api/
├── index.php              ← Single entry point
└── modules/               ← Modular actions
    ├── dashboard-stats.php
    └── ... (modules)
```

### Benefits
- ✅ **DRY**: No code duplication
- ✅ **Consistent**: Standard envelope everywhere
- ✅ **Maintainable**: Easy to add new actions
- ✅ **Debuggable**: Request ID tracking
- ✅ **Professional**: Beautiful error modals
- ✅ **Reliable**: Comprehensive error handling

## 🧪 Testing

```bash
# Health check
curl "https://staff.vapeshed.co.nz/supplier/api/?action=health"

# Test dashboard stats
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats" \
  -b cookies.txt

# Test error handling (invalid action)
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=nonexistent"

# Test validation error
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=update-profile" \
  -H "Content-Type: application/json" \
  -d '{"invalid": "data"}' \
  -b cookies.txt
```

## 📚 Next Steps

1. ✅ Convert remaining API files to modules
2. ✅ Update all JavaScript files to use API.call()
3. ✅ Test all endpoints with new handler
4. ✅ Archive old API files
5. ✅ Update documentation

---

**Status**: ✅ UNIFIED API COMPLETE  
**Version**: 2.0.0  
**Date**: October 30, 2025
