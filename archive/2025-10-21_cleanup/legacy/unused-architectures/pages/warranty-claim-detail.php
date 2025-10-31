<?php
/**
 * Warranty Claim Detail Page
 * 
 * View full warranty claim details with media gallery,
 * supplier notes, and action buttons
 */

// Get claim ID
$fault_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$fault_id) {
    echo '<div class="alert alert-danger">Invalid warranty claim ID.</div>';
    exit;
}

// Get claim details
$claim = get_warranty_claim_details($conn, $supplier_id, $fault_id);

if (!$claim) {
    echo '<div class="alert alert-danger">Warranty claim not found or you do not have access to it.</div>';
    exit;
}

// Get media files
$media_files = get_warranty_claim_media($conn, $fault_id);

// Get supplier notes
$notes_sql = "SELECT * FROM supplier_warranty_notes WHERE fault_id = ? ORDER BY created_at DESC";
$notes_stmt = $conn->prepare($notes_sql);
$notes_stmt->bind_param('i', $fault_id);
$notes_stmt->execute();
$supplier_notes = $notes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notes_stmt->close();

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_warranty_claim_detail', 'faulty_product', $fault_id);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    Warranty Claim #<?php echo htmlspecialchars($claim['id']); ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="?page=warranty-claims">Warranty Claims</a></li>
                    <li class="breadcrumb-item active">#<?php echo $claim['id']; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Action Buttons Row -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group float-right">
                    <button onclick="window.print()" class="btn btn-default">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="?page=warranty-claims" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Claim Details -->
            <div class="col-md-8">
                
                <!-- Product Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-box"></i> Product Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">Product:</dt>
                                    <dd class="col-sm-7">
                                        <strong><?php echo htmlspecialchars($claim['product_name']); ?></strong>
                                    </dd>
                                    
                                    <dt class="col-sm-5">SKU:</dt>
                                    <dd class="col-sm-7">
                                        <code><?php echo htmlspecialchars($claim['sku']); ?></code>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Serial Number:</dt>
                                    <dd class="col-sm-7">
                                        <?php if ($claim['serial_number']): ?>
                                            <code><?php echo htmlspecialchars($claim['serial_number']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Batch Number:</dt>
                                    <dd class="col-sm-7">
                                        <?php if ($claim['batch_number']): ?>
                                            <code><?php echo htmlspecialchars($claim['batch_number']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                            
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">Store:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo htmlspecialchars($claim['store_location'] ?? 'Unknown'); ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Submitted:</dt>
                                    <dd class="col-sm-7">
                                        <?php echo date('F j, Y g:i A', strtotime($claim['time_created'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo time_ago($claim['time_created']); ?></small>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Claim Status:</dt>
                                    <dd class="col-sm-7">
                                        <?php if ($claim['supplier_status'] == 0): ?>
                                            <span class="badge badge-warning badge-lg">
                                                <i class="fas fa-clock"></i> Pending Review
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success badge-lg">
                                                <i class="fas fa-check-circle"></i> Resolved
                                            </span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fault Description -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-circle"></i> Fault Description
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($claim['fault_desc']): ?>
                            <div class="alert alert-warning">
                                <?php echo nl2br(htmlspecialchars($claim['fault_desc'])); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No fault description provided.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Media Gallery -->
                <?php if (!empty($media_files)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-images"></i> Media Files
                                <span class="badge badge-info ml-2"><?php echo count($media_files); ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($media_files as $media): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <?php
                                            $file_path = htmlspecialchars($media['file_path']);
                                            $file_ext = strtolower(pathinfo($media['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $is_video = in_array($file_ext, ['mp4', 'mov', 'avi', 'webm']);
                                            ?>
                                            
                                            <?php if ($is_image): ?>
                                                <img src="<?php echo $file_path; ?>" 
                                                     class="card-img-top" 
                                                     alt="<?php echo htmlspecialchars($media['file_name']); ?>"
                                                     style="height: 200px; object-fit: cover; cursor: pointer;"
                                                     onclick="viewImage('<?php echo $file_path; ?>')">
                                            <?php elseif ($is_video): ?>
                                                <video controls class="card-img-top" style="height: 200px;">
                                                    <source src="<?php echo $file_path; ?>" type="video/<?php echo $file_ext; ?>">
                                                    Your browser does not support video.
                                                </video>
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                    <i class="fas fa-file fa-4x text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="card-body p-2">
                                                <p class="card-text small mb-1">
                                                    <strong><?php echo htmlspecialchars($media['file_name']); ?></strong>
                                                </p>
                                                <p class="card-text small text-muted mb-0">
                                                    <?php echo time_ago($media['uploaded_at']); ?>
                                                </p>
                                                <a href="api/download-media.php?id=<?php echo $media['id']; ?>" 
                                                   class="btn btn-sm btn-primary btn-block mt-2" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Supplier Notes Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-comments"></i> Supplier Notes
                            <span class="badge badge-secondary ml-2"><?php echo count($supplier_notes); ?></span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($supplier_notes)): ?>
                            <p class="text-muted">No notes have been added yet.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($supplier_notes as $note): ?>
                                    <div>
                                        <i class="fas fa-comment bg-blue"></i>
                                        <div class="timeline-item">
                                            <span class="time">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo time_ago($note['created_at']); ?>
                                            </span>
                                            <h3 class="timeline-header">
                                                <?php if ($note['action_taken']): ?>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars($note['action_taken']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($note['internal_ref']): ?>
                                                    <span class="badge badge-secondary ml-2">
                                                        Ref: <?php echo htmlspecialchars($note['internal_ref']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="timeline-body">
                                                <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column - Actions -->
            <div class="col-md-4">
                
                <!-- Quick Actions -->
                <?php if ($claim['supplier_status'] == 0): ?>
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tasks"></i> Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-success btn-block mb-2" 
                                    onclick="updateClaimStatus(<?php echo $fault_id; ?>, 1, 'APPROVED')">
                                <i class="fas fa-check-circle"></i> Approve Claim
                            </button>
                            <button class="btn btn-danger btn-block mb-2" 
                                    onclick="updateClaimStatus(<?php echo $fault_id; ?>, 1, 'DECLINED')">
                                <i class="fas fa-times-circle"></i> Decline Claim
                            </button>
                            <button class="btn btn-info btn-block" 
                                    onclick="updateClaimStatus(<?php echo $fault_id; ?>, 0, 'MORE_INFO_REQUESTED')">
                                <i class="fas fa-question-circle"></i> Request More Info
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-check-circle"></i> Claim Resolved
                            </h3>
                        </div>
                        <div class="card-body">
                            <p>This warranty claim has been marked as resolved.</p>
                            <button class="btn btn-warning btn-block" 
                                    onclick="updateClaimStatus(<?php echo $fault_id; ?>, 0, 'REOPENED')">
                                <i class="fas fa-undo"></i> Reopen Claim
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Note Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus"></i> Add Note
                        </h3>
                    </div>
                    <div class="card-body">
                        <form id="addNoteForm" onsubmit="addNote(event, <?php echo $fault_id; ?>)">
                            <div class="form-group">
                                <label for="note">Note:</label>
                                <textarea class="form-control" id="note" name="note" rows="4" 
                                          placeholder="Enter your note here..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="action">Action Taken:</label>
                                <select class="form-control" id="action" name="action">
                                    <option value="">Select action...</option>
                                    <option value="APPROVED">Approved</option>
                                    <option value="DECLINED">Declined</option>
                                    <option value="MORE_INFO_REQUESTED">More Info Requested</option>
                                    <option value="REPLACEMENT_SENT">Replacement Sent</option>
                                    <option value="REFUND_ISSUED">Refund Issued</option>
                                    <option value="UNDER_REVIEW">Under Review</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="internal_ref">Internal Reference:</label>
                                <input type="text" class="form-control" id="internal_ref" name="internal_ref" 
                                       placeholder="e.g., RMA-12345">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Add Note
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Claim Info -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i> Claim Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <dl>
                            <dt>Claim ID:</dt>
                            <dd><strong>#<?php echo $claim['id']; ?></strong></dd>
                            
                            <dt>Media Files:</dt>
                            <dd><?php echo count($media_files); ?> file(s)</dd>
                            
                            <dt>Notes:</dt>
                            <dd><?php echo count($supplier_notes); ?> note(s)</dd>
                            
                            <dt>Last Updated:</dt>
                            <dd>
                                <?php 
                                $last_updated = !empty($supplier_notes) ? 
                                    $supplier_notes[0]['created_at'] : 
                                    $claim['time_created'];
                                echo time_ago($last_updated);
                                ?>
                            </dd>
                        </dl>
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <button type="button" class="close position-absolute" 
                        style="right: 10px; top: 10px; z-index: 1000; color: white; text-shadow: 0 0 5px black;" 
                        data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <img id="modalImage" src="" class="img-fluid w-100">
            </div>
        </div>
    </div>
</div>

<script>
function viewImage(src) {
    document.getElementById('modalImage').src = src;
    $('#imageModal').modal('show');
}

function updateClaimStatus(faultId, status, action) {
    const confirmMessages = {
        'APPROVED': 'Approve this warranty claim?',
        'DECLINED': 'Decline this warranty claim?',
        'MORE_INFO_REQUESTED': 'Request more information for this claim?',
        'REOPENED': 'Reopen this warranty claim?'
    };
    
    if (!confirm(confirmMessages[action] || 'Update this claim?')) {
        return;
    }
    
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    fetch('api/update-warranty-claim.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fault_id: faultId,
            status: status,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Warranty claim updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update claim'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function addNote(event, faultId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('api/add-warranty-note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fault_id: faultId,
            note: formData.get('note'),
            action: formData.get('action'),
            internal_ref: formData.get('internal_ref')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Note added successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to add note'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
}
</script>

<style>
.timeline {
    position: relative;
    margin: 0 0 30px 0;
    padding: 0;
    list-style: none;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #ddd;
    left: 31px;
    margin: 0;
    border-radius: 2px;
}

.timeline > div {
    margin-bottom: 15px;
    position: relative;
}

.timeline > div > .fas {
    width: 30px;
    height: 30px;
    font-size: 15px;
    line-height: 30px;
    position: absolute;
    color: #666;
    background: #d2d6de;
    border-radius: 50%;
    text-align: center;
    left: 18px;
    top: 0;
}

.timeline > div > .fas.bg-blue {
    background-color: #007bff !important;
    color: white;
}

.timeline > div > .timeline-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin-left: 60px;
    margin-right: 15px;
    padding: 10px;
    position: relative;
}

.timeline > div > .timeline-item > .time {
    color: #999;
    float: right;
    font-size: 12px;
}

.timeline > div > .timeline-item > .timeline-header {
    font-size: 14px;
    line-height: 1.8;
    margin: 0 0 10px;
}

.timeline > div > .timeline-item > .timeline-body {
    padding: 10px 0;
}
</style>
