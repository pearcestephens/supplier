# 09 - Code Snippets & Templates

**Reusable code patterns for rapid development**

---

## Quick Reference

**Copy-paste ready templates for common tasks**

### Contents
1. [New API Handler](#1-new-api-handler)
2. [New Page Tab](#2-new-page-tab)
3. [Database Queries](#3-database-queries)
4. [AJAX Calls](#4-ajax-calls)
5. [Authentication Checks](#5-authentication-checks)
6. [Chart.js Widgets](#6-chartjs-widgets)
7. [Form Handling](#7-form-handling)
8. [Pagination](#8-pagination)
9. [Activity Logging](#9-activity-logging)
10. [Error Handling](#10-error-handling)

---

## 1. New API Handler

**Create:** `api/handlers/yourmodule.php`

```php
<?php
/**
 * API Handler: YourModule
 * 
 * Purpose: Describe what this module handles
 * Endpoints: list.main.methods
 */

declare(strict_types=1);

class Handler_YourModule
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Route requests to appropriate methods
     */
    public function handle(string $method, array $params): array
    {
        return match($method) {
            'getList' => $this->getList($params),
            'getDetail' => $this->getDetail($params),
            'create' => $this->create($params),
            'update' => $this->update($params),
            'delete' => $this->delete($params),
            default => throw new Exception("Unknown method: $method")
        };
    }
    
    /**
     * Get list with pagination and filtering
     */
    private function getList(array $params): array
    {
        $supplierId = getSupplierID();
        
        // Pagination
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 25);
        $offset = ($page - 1) * $perPage;
        
        // Filtering
        $filters = [];
        $values = [$supplierId];
        
        if (!empty($params['status'])) {
            $filters[] = "status = ?";
            $values[] = $params['status'];
        }
        
        if (!empty($params['search'])) {
            $filters[] = "(name LIKE ? OR code LIKE ?)";
            $search = '%' . $params['search'] . '%';
            $values[] = $search;
            $values[] = $search;
        }
        
        $whereClause = "WHERE supplier_id = ? AND deleted_at IS NULL";
        if (!empty($filters)) {
            $whereClause .= " AND " . implode(" AND ", $filters);
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM your_table 
            $whereClause
        ");
        $countStmt->execute($values);
        $total = $countStmt->fetchColumn();
        
        // Get results
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM your_table 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($values, [$perPage, $offset]));
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $items,
            'message' => 'Retrieved ' . count($items) . ' items',
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => ceil($total / $perPage)
            ]
        ];
    }
    
    /**
     * Get single item detail
     */
    private function getDetail(array $params): array
    {
        $supplierId = getSupplierID();
        
        if (empty($params['id'])) {
            throw new Exception("ID parameter required");
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM your_table 
            WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$params['id'], $supplierId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception("Item not found");
        }
        
        return [
            'data' => $item,
            'message' => 'Item retrieved'
        ];
    }
    
    /**
     * Create new item
     */
    private function create(array $params): array
    {
        $supplierId = getSupplierID();
        
        // Validate required fields
        $required = ['name', 'value'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO your_table (supplier_id, name, value, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $supplierId,
                $params['name'],
                $params['value']
            ]);
            
            $newId = $this->pdo->lastInsertId();
            
            // Log activity
            $this->logActivity('create', $newId, 'Created new item: ' . $params['name']);
            
            $this->pdo->commit();
            
            return [
                'data' => ['id' => $newId],
                'message' => 'Item created successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Update existing item
     */
    private function update(array $params): array
    {
        $supplierId = getSupplierID();
        
        if (empty($params['id'])) {
            throw new Exception("ID parameter required");
        }
        
        // Verify ownership
        $checkStmt = $this->pdo->prepare("
            SELECT id FROM your_table 
            WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL
        ");
        $checkStmt->execute([$params['id'], $supplierId]);
        if (!$checkStmt->fetch()) {
            throw new Exception("Item not found or access denied");
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $updates = [];
            $values = [];
            
            if (isset($params['name'])) {
                $updates[] = "name = ?";
                $values[] = $params['name'];
            }
            
            if (isset($params['value'])) {
                $updates[] = "value = ?";
                $values[] = $params['value'];
            }
            
            if (empty($updates)) {
                throw new Exception("No fields to update");
            }
            
            $updates[] = "updated_at = NOW()";
            $values[] = $params['id'];
            $values[] = $supplierId;
            
            $stmt = $this->pdo->prepare("
                UPDATE your_table 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND supplier_id = ?
            ");
            $stmt->execute($values);
            
            // Log activity
            $this->logActivity('update', $params['id'], 'Updated item');
            
            $this->pdo->commit();
            
            return [
                'data' => null,
                'message' => 'Item updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Soft delete item
     */
    private function delete(array $params): array
    {
        $supplierId = getSupplierID();
        
        if (empty($params['id'])) {
            throw new Exception("ID parameter required");
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE your_table 
                SET deleted_at = NOW()
                WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$params['id'], $supplierId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Item not found or already deleted");
            }
            
            // Log activity
            $this->logActivity('delete', $params['id'], 'Deleted item');
            
            $this->pdo->commit();
            
            return [
                'data' => null,
                'message' => 'Item deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Log supplier activity
     */
    private function logActivity(string $action, int $itemId, string $details): void
    {
        $supplierId = getSupplierID();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO supplier_activity_log 
            (supplier_id, action, item_type, item_id, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $supplierId,
            $action,
            'your_module',
            $itemId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
```

**Register in** `api/endpoint.php`:
```php
case 'yourmodule':
    $handler = new Handler_YourModule($pdo);
    $result = $handler->handle($method, $params);
    break;
```

---

## 2. New Page Tab

**Create:** `tabs/tab-yourpage.php`

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
requireAuth();

$pageTitle = 'Your Page';
$currentTab = 'yourpage';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../components/header-top.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
        <div class="app-content">
            <?php include __DIR__ . '/../components/header-bottom.php'; ?>
            
            <main class="main-content">
                <div class="container-fluid">
                    <!-- Breadcrumb -->
                    <div class="page-header">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/supplier/">Home</a></li>
                                <li class="breadcrumb-item active">Your Page</li>
                            </ol>
                        </nav>
                        <h1 class="h3 mb-0">Your Page</h1>
                    </div>
                    
                    <!-- Content -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Your Content</h5>
                                </div>
                                <div class="card-body">
                                    <p>Page content goes here</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Page-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Your page loaded');
        });
    </script>
</body>
</html>
```

**Add to sidebar** in `components/sidebar.php`:
```php
<li class="nav-item <?php echo $currentTab === 'yourpage' ? 'active' : ''; ?>">
    <a class="nav-link" href="/supplier/tabs/tab-yourpage.php">
        <i class="fas fa-your-icon"></i>
        <span>Your Page</span>
    </a>
</li>
```

---

## 3. Database Queries

### Simple Select with Multi-Tenancy
```php
$supplierId = getSupplierID();
$pdo = pdo();

$stmt = $pdo->prepare("
    SELECT * FROM your_table 
    WHERE supplier_id = ? AND deleted_at IS NULL
    ORDER BY created_at DESC
");
$stmt->execute([$supplierId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Select with Join
```php
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        o.name AS outlet_name
    FROM vend_consignments c
    LEFT JOIN vend_outlets o ON c.outlet_id = o.id
    WHERE c.supplier_id = ? AND c.deleted_at IS NULL
    ORDER BY c.created_at DESC
");
$stmt->execute([$supplierId]);
```

### Insert with Last Insert ID
```php
$stmt = $pdo->prepare("
    INSERT INTO your_table (supplier_id, name, value, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$supplierId, $name, $value]);
$newId = $pdo->lastInsertId();
```

### Update with Verification
```php
$stmt = $pdo->prepare("
    UPDATE your_table 
    SET value = ?, updated_at = NOW()
    WHERE id = ? AND supplier_id = ?
");
$stmt->execute([$newValue, $id, $supplierId]);

if ($stmt->rowCount() === 0) {
    throw new Exception("Item not found or access denied");
}
```

### Delete (Soft Delete)
```php
$stmt = $pdo->prepare("
    UPDATE your_table 
    SET deleted_at = NOW()
    WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL
");
$stmt->execute([$id, $supplierId]);
```

### Transaction Pattern
```php
try {
    $pdo->beginTransaction();
    
    // Multiple queries...
    $stmt1 = $pdo->prepare("INSERT INTO table1 ...");
    $stmt1->execute([...]);
    
    $stmt2 = $pdo->prepare("UPDATE table2 ...");
    $stmt2->execute([...]);
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## 4. AJAX Calls

### Fetch API (Modern)
```javascript
// GET request (no auth needed for session-debug)
fetch('/api/session-debug.php', {
    credentials: 'same-origin'
})
.then(response => response.json())
.then(data => {
    console.log('Session:', data);
})
.catch(error => {
    console.error('Error:', error);
});

// POST request with JSON
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'yourmodule.getList',
        params: {
            page: 1,
            per_page: 25,
            status: 'active'
        }
    })
})
.then(response => {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
})
.then(data => {
    if (data.success) {
        console.log('Data:', data.data);
    } else {
        console.error('API Error:', data.error);
    }
})
.catch(error => {
    console.error('Network Error:', error);
});
```

### jQuery AJAX (Legacy)
```javascript
$.ajax({
    url: '/api/endpoint.php',
    method: 'POST',
    contentType: 'application/json',
    xhrFields: {
        withCredentials: true
    },
    data: JSON.stringify({
        action: 'yourmodule.getList',
        params: {page: 1}
    }),
    success: function(response) {
        if (response.success) {
            console.log('Data:', response.data);
        } else {
            console.error('Error:', response.error);
        }
    },
    error: function(xhr, status, error) {
        console.error('AJAX Error:', error);
    }
});
```

### With Loading State
```javascript
const button = document.getElementById('loadButton');
const spinner = button.querySelector('.spinner-border');
const text = button.querySelector('.text');

button.disabled = true;
spinner.classList.remove('d-none');
text.textContent = 'Loading...';

fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'yourmodule.getList', params: {}})
})
.then(r => r.json())
.then(data => {
    // Handle response
})
.finally(() => {
    button.disabled = false;
    spinner.classList.add('d-none');
    text.textContent = 'Load Data';
});
```

---

## 5. Authentication Checks

### In PHP Files
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Redirect to login if not authenticated
requireAuth();

// Get authenticated supplier ID
$supplierId = getSupplierID();

// Get supplier name (optional)
$supplierName = Auth::getSupplierName();
?>
```

### In API Handlers
```php
class Handler_YourModule
{
    public function handle(string $method, array $params): array
    {
        // Auth already checked by api/endpoint.php
        $supplierId = getSupplierID();
        
        // Your code...
    }
}
```

### Manual Check
```php
if (!Auth::check()) {
    // For API
    sendJsonResponse(false, null, 'Unauthorized', 401);
    
    // For pages
    header('Location: /supplier/login.php');
    exit;
}
```

---

## 6. Chart.js Widgets

### Revenue Line Chart
```javascript
const ctx = document.getElementById('revenueChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Revenue',
            data: [1200, 1900, 1500, 2100, 1800, 2400],
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
            legend: {
                display: true,
                labels: {
                    color: '#8b949e'
                }
            },
            tooltip: {
                backgroundColor: '#161b22',
                titleColor: '#c9d1d9',
                bodyColor: '#8b949e',
                borderColor: '#30363d',
                borderWidth: 1
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#21262d'
                },
                ticks: {
                    color: '#8b949e',
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#8b949e'
                }
            }
        }
    }
});
```

### Bar Chart with API Data
```javascript
// Fetch data from API
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'dashboard.getChartData',
        params: {type: 'orders', period: '7days'}
    })
})
.then(r => r.json())
.then(response => {
    if (!response.success) {
        console.error('API Error:', response.error);
        return;
    }
    
    const chartData = response.data;
    
    const ctx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Orders',
                data: chartData.values,
                backgroundColor: '#3b82f6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false}
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {color: '#21262d'},
                    ticks: {color: '#8b949e'}
                },
                x: {
                    grid: {display: false},
                    ticks: {color: '#8b949e'}
                }
            }
        }
    });
});
```

---

## 7. Form Handling

### HTML Form with CSRF
```html
<form id="myForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <div class="mb-3">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" required>
    </div>
    
    <div class="mb-3">
        <label for="value" class="form-label">Value</label>
        <input type="number" class="form-control" id="value" name="value" required>
    </div>
    
    <button type="submit" class="btn btn-primary">
        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
        <span class="text">Submit</span>
    </button>
</form>
```

### JavaScript Form Submission
```javascript
document.getElementById('myForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    const spinner = button.querySelector('.spinner-border');
    const text = button.querySelector('.text');
    
    // Disable form
    button.disabled = true;
    spinner.classList.remove('d-none');
    text.textContent = 'Submitting...';
    
    // Get form data
    const formData = new FormData(form);
    const data = {
        name: formData.get('name'),
        value: formData.get('value')
    };
    
    try {
        const response = await fetch('/api/endpoint.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'yourmodule.create',
                params: data
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success
            alert(result.message);
            form.reset();
        } else {
            // Error
            alert('Error: ' + result.error);
        }
        
    } catch (error) {
        alert('Network error: ' + error.message);
    } finally {
        // Re-enable form
        button.disabled = false;
        spinner.classList.add('d-none');
        text.textContent = 'Submit';
    }
});
```

---

## 8. Pagination

### Backend (PHP)
```php
// In your handler
private function getList(array $params): array
{
    $supplierId = getSupplierID();
    
    $page = max(1, (int)($params['page'] ?? 1));
    $perPage = min(100, max(10, (int)($params['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    
    // Get total
    $countStmt = $this->pdo->prepare("
        SELECT COUNT(*) FROM your_table 
        WHERE supplier_id = ? AND deleted_at IS NULL
    ");
    $countStmt->execute([$supplierId]);
    $total = (int)$countStmt->fetchColumn();
    
    // Get page
    $stmt = $this->pdo->prepare("
        SELECT * FROM your_table 
        WHERE supplier_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$supplierId, $perPage, $offset]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $items,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int)ceil($total / $perPage),
            'has_next' => $page < ceil($total / $perPage),
            'has_prev' => $page > 1
        ]
    ];
}
```

### Frontend (JavaScript)
```javascript
class Paginator {
    constructor(containerId, apiAction) {
        this.container = document.getElementById(containerId);
        this.apiAction = apiAction;
        this.currentPage = 1;
        this.perPage = 25;
    }
    
    async loadPage(page = 1) {
        this.currentPage = page;
        
        const response = await fetch('/api/endpoint.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: this.apiAction,
                params: {
                    page: this.currentPage,
                    per_page: this.perPage
                }
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.renderItems(result.data);
            this.renderPagination(result.meta);
        }
    }
    
    renderItems(items) {
        // Render your items
        const html = items.map(item => `
            <div class="item">${item.name}</div>
        `).join('');
        
        this.container.querySelector('.items').innerHTML = html;
    }
    
    renderPagination(meta) {
        const nav = this.container.querySelector('.pagination');
        
        let html = '';
        
        // Previous button
        if (meta.has_prev) {
            html += `<button class="btn btn-sm" onclick="paginator.loadPage(${meta.page - 1})">Previous</button>`;
        }
        
        // Page numbers
        html += `<span class="mx-3">Page ${meta.page} of ${meta.pages}</span>`;
        
        // Next button
        if (meta.has_next) {
            html += `<button class="btn btn-sm" onclick="paginator.loadPage(${meta.page + 1})">Next</button>`;
        }
        
        nav.innerHTML = html;
    }
}

// Usage
const paginator = new Paginator('dataContainer', 'yourmodule.getList');
paginator.loadPage(1);
```

---

## 9. Activity Logging

### Log Function
```php
/**
 * Log supplier activity
 */
function logSupplierActivity(
    string $action,
    string $itemType,
    ?int $itemId,
    string $details
): void {
    $pdo = pdo();
    $supplierId = getSupplierID();
    
    $stmt = $pdo->prepare("
        INSERT INTO supplier_activity_log 
        (supplier_id, action, item_type, item_id, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $supplierId,
        $action,
        $itemType,
        $itemId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
```

### Usage Examples
```php
// After creating order
logSupplierActivity('create', 'order', $orderId, "Created order JCE-PO-12345");

// After updating tracking
logSupplierActivity('update', 'order', $orderId, "Updated tracking: TRACK123");

// After viewing detail
logSupplierActivity('view', 'order', $orderId, "Viewed order detail");

// General action
logSupplierActivity('login', 'auth', null, "Logged in via magic link");
```

---

## 10. Error Handling

### API Error Response
```php
try {
    // Your code...
    
    sendJsonResponse(true, $data, 'Success');
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Database error occurred', 500);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendJsonResponse(false, null, $e->getMessage(), 400);
}
```

### Frontend Error Handling
```javascript
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'yourmodule.getList', params: {}})
})
.then(response => {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    return response.json();
})
.then(data => {
    if (!data.success) {
        throw new Error(data.error || 'Unknown error');
    }
    
    // Success - use data.data
    console.log('Data:', data.data);
})
.catch(error => {
    console.error('Error:', error);
    
    // Show user-friendly message
    const alertDiv = document.getElementById('errorAlert');
    alertDiv.textContent = 'Error: ' + error.message;
    alertDiv.classList.remove('d-none');
});
```

---

## Bonus: Complete CRUD Widget

**Copy-paste ready widget with list, create, edit, delete:**

See [WIDGET_INVENTORY_VISUAL_GUIDE.md](../../WIDGET_INVENTORY_VISUAL_GUIDE.md) for complete example.

---

## Next Steps

- **Return to Documentation Index:** [DOCUMENTATION_INDEX.md](../../DOCUMENTATION_INDEX.md)
- **Architecture Overview:** [01-ARCHITECTURE.md](01-ARCHITECTURE.md)
- **API Reference:** [03-API-REFERENCE.md](03-API-REFERENCE.md)

---

**Last Updated:** 2025-10-26  
**Related:** All KB documents
