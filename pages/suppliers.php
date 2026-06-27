<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';
$error = '';
$success = '';

// Handle supplier deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $supplier_id = intval($_GET['delete']);
    
    // Check if suppliers table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
    if (mysqli_num_rows($table_check) == 0) {
        $error = "Suppliers table does not exist! Please run database setup.";
    } else {
        $check_query = "SELECT COUNT(*) as total FROM raw_material_purchases WHERE supplier_id = $supplier_id";
        $check_result = mysqli_query($conn, $check_query);
        $check_row = mysqli_fetch_assoc($check_result);
        
        if ($check_row['total'] > 0) {
            $error = "Cannot delete this supplier because they have " . $check_row['total'] . " purchase record(s)!";
        } else {
            $delete_query = "DELETE FROM suppliers WHERE id = $supplier_id";
            if (mysqli_query($conn, $delete_query)) {
                $success = "Supplier deleted successfully!";
                echo "<script>setTimeout(function(){ window.location.href = 'suppliers.php'; }, 1500);</script>";
            } else {
                $error = "Error deleting supplier: " . mysqli_error($conn);
            }
        }
    }
}

// Check if suppliers table exists
$suppliers_table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
if (mysqli_num_rows($suppliers_table_exists) == 0) {
    $error = "Suppliers table does not exist! Please create the table first.";
    $result = false;
    $total_suppliers = 0;
    $active_suppliers = 0;
    $total_credit = 0;
    $total_purchases = 0;
} else {
    // Get all suppliers
    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM raw_material_purchases WHERE supplier_id = s.id) as total_purchases,
              (SELECT COALESCE(SUM(total_amount), 0) FROM raw_material_purchases WHERE supplier_id = s.id) as total_purchase_amount
              FROM suppliers s 
              ORDER BY s.supplier_name ASC";
    $result = mysqli_query($conn, $query);
    
    // Stats
    $total_suppliers = mysqli_num_rows($result);
    
    $active_query = "SELECT COUNT(*) as total FROM suppliers WHERE status = 'Active'";
    $active_result = mysqli_query($conn, $active_query);
    $active_row = mysqli_fetch_assoc($active_result);
    $active_suppliers = $active_row ? $active_row['total'] : 0;
    
    $balance_query = "SELECT COALESCE(SUM(current_balance), 0) as total FROM suppliers WHERE current_balance > 0";
    $balance_result = mysqli_query($conn, $balance_query);
    $balance_row = mysqli_fetch_assoc($balance_result);
    $total_credit = $balance_row ? $balance_row['total'] : 0;
    
    $purchase_query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM raw_material_purchases";
    $purchase_result = mysqli_query($conn, $purchase_query);
    $purchase_row = mysqli_fetch_assoc($purchase_result);
    $total_purchases = $purchase_row ? $purchase_row['total'] : 0;
}

// Combine messages
if ($error) $message = "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
if ($success) $message = "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>";
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Table Styles matching customers.php */
.suppliers-table {
    font-size: 14px;
}
.suppliers-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.suppliers-table td {
    padding: 12px 10px;
    vertical-align: middle;
    word-break: break-word;
}
.suppliers-table td:first-child,
.suppliers-table th:first-child {
    text-align: center;
}
.balance-positive {
    color: #28a745;
    font-weight: bold;
}
.balance-negative {
    color: #dc3545;
    font-weight: bold;
}
.action-buttons {
    white-space: nowrap;
}
.action-buttons .btn {
    margin: 0 2px;
    padding: 5px 8px;
}
.table-responsive {
    overflow-x: auto;
}
@media (max-width: 768px) {
    .suppliers-table {
        font-size: 12px;
    }
    .suppliers-table td, 
    .suppliers-table th {
        padding: 8px 6px;
    }
}
/* Stats Cards */
.dash-card {
    border-radius: 16px;
    transition: transform 0.2s;
    border: none;
}
.dash-card:hover {
    transform: translateY(-5px);
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-truck me-2" style="color: #A04657;"></i> Suppliers
        </h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fas fa-plus-circle me-2"></i> Add New Supplier
        </button>
    </div>

    <?php echo $message; ?>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3 shadow-sm">
                <div class="card-body">
                    <i class="fas fa-truck fa-2x mb-2" style="color: #A04657;"></i>
                    <h5 class="text-muted mb-1">Total Suppliers</h5>
                    <h2 class="mb-0"><?php echo number_format($total_suppliers); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3 shadow-sm">
                <div class="card-body">
                    <i class="fas fa-user-check fa-2x mb-2" style="color: #28a745;"></i>
                    <h5 class="text-muted mb-1">Active Suppliers</h5>
                    <h2 class="mb-0"><?php echo number_format($active_suppliers); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3 shadow-sm">
                <div class="card-body">
                    <i class="fas fa-credit-card fa-2x mb-2" style="color: #ffc107;"></i>
                    <h5 class="text-muted mb-1">Total Credit</h5>
                    <h2 class="mb-0">Rs <?php echo number_format($total_credit, 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3 shadow-sm">
                <div class="card-body">
                    <i class="fas fa-chart-line fa-2x mb-2" style="color: #17a2b8;"></i>
                    <h5 class="text-muted mb-1">Total Purchases</h5>
                    <h2 class="mb-0">Rs <?php echo number_format($total_purchases, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Suppliers Table Card -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover suppliers-table mb-0" id="suppliersTable">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:160px">Supplier Name</th>
                            <th style="min-width:120px">Contact Person</th>
                            <th style="min-width:110px">Mobile</th>
                            <th style="min-width:180px">Email</th>
                            <th style="min-width:130px">Current Balance</th>
                            <th style="min-width:140px">Total Purchases</th>
                            <th style="min-width:80px">Status</th>
                            <th style="min-width:100px; text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): 
                            $counter = 1;
                            while($row = mysqli_fetch_assoc($result)): 
                        ?>
                            <tr>
                                <td class="text-center fw-semibold"><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                                <td>
                                    <span class="<?php echo $row['current_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                        Rs <?php echo number_format(abs($row['current_balance']), 2); ?>
                                    </span>
                                    <?php if($row['current_balance'] > 0): ?>
                                        <span class="badge bg-warning rounded-pill">Credit</span>
                                    <?php elseif($row['current_balance'] < 0): ?>
                                        <span class="badge bg-success rounded-pill">Advance</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill">Zero</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['total_purchase_amount'] > 0): ?>
                                        <strong>Rs <?php echo number_format($row['total_purchase_amount'], 2); ?></strong>
                                        <br><small class="text-muted">(<?php echo $row['total_purchases']; ?> purchases)</small>
                                    <?php else: ?>
                                        <span class="text-muted">No purchases</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'Active'): ?>
                                        <span class="badge bg-success rounded-pill">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons text-center">
                                    <a href="supplier_details.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_supplier.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="Delete"
                                            onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['supplier_name']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-truck fa-3x mb-3 d-block"></i>
                                    No suppliers found. Click <strong>"Add New Supplier"</strong> to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_supplier.php">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Supplier Name *</label>
                            <input type="text" name="supplier_name" class="form-control" placeholder="Enter supplier name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Contact person name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mobile Number *</label>
                            <input type="text" name="mobile" class="form-control" placeholder="e.g., 03XXXXXXXXX" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone (Optional)</label>
                            <input type="text" name="phone" class="form-control" placeholder="Landline number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">NTN Number</label>
                            <input type="text" name="ntn_no" class="form-control" placeholder="NTN number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Opening Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs</span>
                                <input type="number" name="opening_balance" class="form-control" step="0.01" value="0">
                            </div>
                            <small class="text-muted">Positive = Credit | Negative = Advance</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_supplier" class="btn btn-primary rounded-pill px-4">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p>Are you sure you want to delete supplier: <strong id="deleteSupplierName"></strong>?</p>
                <p class="text-danger mt-2"><i class="fas fa-exclamation-triangle me-2"></i> Note: Suppliers with purchase records cannot be deleted.</p>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger rounded-pill px-4">Delete</a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    <?php if($result && mysqli_num_rows($result) > 0): ?>
    $('#suppliersTable').DataTable({
        pageLength: 25,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ suppliers"
        }
    });
    <?php endif; ?>
});

function confirmDelete(id, name) {
    $('#deleteSupplierName').text(name);
    $('#confirmDeleteBtn').attr('href', 'suppliers.php?delete=' + id);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>