<?php
/**
 * The Vape Shed - Supplier Portal Claims Dashboard
 * Claims management with integrated template and branding
 * 
 * @file supplier-claims.php
 * @purpose Supplier claims management interface
 * @author Pearce Stephens
 * @last_modified 2025-10-07
 */

$pageTitle = 'Claims Management - The Vape Shed Supplier Portal';
$supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 12345;

// Include the updated header with logo and menu
include_once 'supplier-header-updated.php';
?>

<!-- CLAIMS DASHBOARD CONTENT -->
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-invoice-dollar text-primary mr-2"></i>
                Claims Management
            </h1>
            <p class="mb-0 text-muted">Manage warranty claims and returns for The Vape Shed</p>
        </div>
        <div class="col-lg-4 text-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#newClaimModal">
                    <i class="fas fa-plus mr-1"></i> New Claim
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="exportClaims()">
                    <i class="fas fa-download mr-1"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CLAIMS STATS ROW -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Total Claims</div>
                    <div class="stats-number">247</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-file-invoice fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card warning">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Pending Review</div>
                    <div class="stats-number">18</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card success">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Approved</div>
                    <div class="stats-number">189</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card danger">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Rejected</div>
                    <div class="stats-number">40</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-times-circle fa-2x text-danger"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CLAIMS FILTERS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form id="claimsFilterForm" class="row">
                    <div class="col-md-3">
                        <label for="statusFilter">Status</label>
                        <select class="form-control" id="statusFilter" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="processing">Processing</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dateRange">Date Range</label>
                        <select class="form-control" id="dateRange" name="dateRange">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="claimType">Claim Type</label>
                        <select class="form-control" id="claimType" name="claimType">
                            <option value="">All Types</option>
                            <option value="warranty">Warranty</option>
                            <option value="return">Return</option>
                            <option value="defective">Defective Product</option>
                            <option value="damage">Shipping Damage</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-search mr-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-redo mr-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CLAIMS TABLE -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list mr-2"></i>Claims List
                </h6>
                <span class="badge badge-info">Showing 25 of 247 claims</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="claimsTable">
                        <thead>
                            <tr>
                                <th>Claim #</th>
                                <th>Date Submitted</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>CL-2025-001</strong></td>
                                <td>Oct 7, 2025</td>
                                <td>SMOK Nord 4 Kit</td>
                                <td><span class="badge badge-info">Warranty</span></td>
                                <td>Device not charging</td>
                                <td>$45.99</td>
                                <td><span class="badge badge-warning">Pending Review</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewClaim('CL-2025-001')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="approveClaim('CL-2025-001')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="rejectClaim('CL-2025-001')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>CL-2025-002</strong></td>
                                <td>Oct 6, 2025</td>
                                <td>Vaporesso Gen S Mod</td>
                                <td><span class="badge badge-secondary">Return</span></td>
                                <td>Customer changed mind</td>
                                <td>$89.99</td>
                                <td><span class="badge badge-success">Approved</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewClaim('CL-2025-002')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="printLabel('CL-2025-002')">
                                            <i class="fas fa-shipping-fast"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>CL-2025-003</strong></td>
                                <td>Oct 5, 2025</td>
                                <td>Geekvape Aegis Legend 2</td>
                                <td><span class="badge badge-warning">Defective</span></td>
                                <td>Screen not working</td>
                                <td>$119.99</td>
                                <td><span class="badge badge-info">Processing</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewClaim('CL-2025-003')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateClaim('CL-2025-003')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- PAGINATION -->
                <nav aria-label="Claims pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- NEW CLAIM MODAL -->
<div class="modal fade" id="newClaimModal" tabindex="-1" role="dialog" aria-labelledby="newClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="newClaimModalLabel">
                    <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="VS" style="width: 24px; height: 24px; border-radius: 4px; margin-right: 8px;">
                    Create New Claim
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="newClaimForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="claimProduct">Product</label>
                                <select class="form-control" id="claimProduct" name="product" required>
                                    <option value="">Select Product</option>
                                    <option value="smok-nord-4">SMOK Nord 4 Kit</option>
                                    <option value="vaporesso-gen-s">Vaporesso Gen S Mod</option>
                                    <option value="geekvape-aegis">Geekvape Aegis Legend 2</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="claimTypeNew">Claim Type</label>
                                <select class="form-control" id="claimTypeNew" name="claimType" required>
                                    <option value="">Select Type</option>
                                    <option value="warranty">Warranty</option>
                                    <option value="return">Return</option>
                                    <option value="defective">Defective Product</option>
                                    <option value="damage">Shipping Damage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="claimReason">Reason</label>
                        <textarea class="form-control" id="claimReason" name="reason" rows="3" required placeholder="Describe the issue or reason for the claim..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="claimAmount">Claim Amount</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="claimAmount" name="amount" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="claimPriority">Priority</label>
                                <select class="form-control" id="claimPriority" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNewClaim()">
                    <i class="fas fa-save mr-1"></i> Submit Claim
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CLAIMS JAVASCRIPT -->
<script>
$(document).ready(function() {
    // Set active menu item
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('claims');
    }
    
    // Initialize claims dashboard
    initClaimsDashboard();
    
    function initClaimsDashboard() {
        // Initialize table
        $('#claimsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [7] }
            ]
        });
        
        // Filter form handler
        $('#claimsFilterForm').on('submit', function(e) {
            e.preventDefault();
            filterClaims();
        });
    }
});

function filterClaims() {
    var formData = $('#claimsFilterForm').serialize();
    console.log('Filtering claims with:', formData);
    
    // Simulate API call
    showSupplierNotification('Claims filtered successfully!', 'info');
}

function resetFilters() {
    $('#claimsFilterForm')[0].reset();
    filterClaims();
}

function viewClaim(claimId) {
    console.log('Viewing claim:', claimId);
    showSupplierNotification('Opening claim details for ' + claimId, 'info');
}

function approveClaim(claimId) {
    if (confirm('Are you sure you want to approve claim ' + claimId + '?')) {
        console.log('Approving claim:', claimId);
        showSupplierNotification('Claim ' + claimId + ' has been approved!', 'success');
        
        // Update row status
        updateClaimStatus(claimId, 'approved');
    }
}

function rejectClaim(claimId) {
    if (confirm('Are you sure you want to reject claim ' + claimId + '?')) {
        console.log('Rejecting claim:', claimId);
        showSupplierNotification('Claim ' + claimId + ' has been rejected.', 'warning');
        
        // Update row status
        updateClaimStatus(claimId, 'rejected');
    }
}

function updateClaimStatus(claimId, status) {
    // Find and update the row in the table
    var statusBadges = {
        'approved': '<span class="badge badge-success">Approved</span>',
        'rejected': '<span class="badge badge-danger">Rejected</span>',
        'pending': '<span class="badge badge-warning">Pending Review</span>'
    };
    
    // Update the status column for the claim
    console.log('Updating claim status:', claimId, 'to', status);
}

function submitNewClaim() {
    var form = $('#newClaimForm');
    if (form[0].checkValidity()) {
        console.log('Submitting new claim...');
        
        // Simulate API call
        setTimeout(function() {
            $('#newClaimModal').modal('hide');
            showSupplierNotification('New claim has been submitted successfully!', 'success');
            
            // Reset form
            form[0].reset();
        }, 1000);
    } else {
        form[0].reportValidity();
    }
}

function exportClaims() {
    console.log('Exporting claims...');
    showSupplierNotification('Claims export started. Download will begin shortly.', 'info');
}

function printLabel(claimId) {
    console.log('Printing shipping label for claim:', claimId);
    showSupplierNotification('Shipping label for ' + claimId + ' sent to printer.', 'success');
}

function updateClaim(claimId) {
    console.log('Updating claim:', claimId);
    showSupplierNotification('Opening claim editor for ' + claimId, 'info');
}
</script>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<?php
// Include the updated footer
include_once 'supplier-footer-updated.php';
?>