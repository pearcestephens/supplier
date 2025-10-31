<?php
/**
 * Notifications Page
 * 
 * View all notifications with read/unread status
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_notifications');

// Handle mark as read action
if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all') {
    $mark_sql = "UPDATE supplier_portal_notifications 
                 SET is_read = 1, read_at = NOW() 
                 WHERE supplier_id = ? AND is_read = 0";
    $mark_stmt = $conn->prepare($mark_sql);
    $mark_stmt->bind_param('s', $supplier_id);
    $mark_stmt->execute();
    $mark_stmt->close();
    
    header('Location: ?page=notifications');
    exit;
}

// Get filter
$show_unread_only = isset($_GET['unread']) && $_GET['unread'] === '1';

// Get notifications
$notif_sql = "SELECT * FROM supplier_portal_notifications 
              WHERE supplier_id = ?";

if ($show_unread_only) {
    $notif_sql .= " AND is_read = 0";
}

$notif_sql .= " ORDER BY created_at DESC LIMIT 100";

$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param('s', $supplier_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

// Count unread
$unread_count = $stats['unread_notifications'];
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="badge badge-danger"><?php echo $unread_count; ?> new</span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Notifications</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Actions Row -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group">
                    <a href="?page=notifications" class="btn btn-<?php echo !$show_unread_only ? 'primary' : 'default'; ?>">
                        <i class="fas fa-list"></i> All
                    </a>
                    <a href="?page=notifications&unread=1" class="btn btn-<?php echo $show_unread_only ? 'primary' : 'default'; ?>">
                        <i class="fas fa-envelope"></i> Unread (<?php echo $unread_count; ?>)
                    </a>
                </div>
                
                <?php if ($unread_count > 0): ?>
                    <a href="?page=notifications&mark_read=all" class="btn btn-success float-right">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="row">
            <div class="col-md-12">
                <?php if (empty($notifications)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle"></i>
                                <?php if ($show_unread_only): ?>
                                    No unread notifications.
                                <?php else: ?>
                                    No notifications yet.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="card <?php echo $notif['is_read'] ? '' : 'bg-light'; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-1 text-center">
                                        <?php
                                        $type_icons = [
                                            'purchase_order' => 'fas fa-file-invoice text-primary',
                                            'warranty_claim' => 'fas fa-exclamation-triangle text-warning',
                                            'product' => 'fas fa-box text-info',
                                            'system' => 'fas fa-cog text-secondary',
                                            'info' => 'fas fa-info-circle text-info'
                                        ];
                                        $icon = $type_icons[$notif['type']] ?? 'fas fa-bell text-secondary';
                                        ?>
                                        <i class="<?php echo $icon; ?> fa-2x"></i>
                                    </div>
                                    <div class="col-md-10">
                                        <h5>
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                            <?php if (!$notif['is_read']): ?>
                                                <span class="badge badge-danger ml-2">NEW</span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                        <p class="text-muted mb-0">
                                            <small>
                                                <i class="fas fa-clock"></i> 
                                                <?php echo time_ago($notif['created_at']); ?>
                                                (<?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>)
                                            </small>
                                        </p>
                                    </div>
                                    <div class="col-md-1 text-right">
                                        <?php if ($notif['link']): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-arrow-right"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>
