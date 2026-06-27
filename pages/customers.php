<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';

// ---- Route Management (Add/Edit/Delete) ----
if (isset($_GET['delete_route'])) {
    $route_id = intval($_GET['delete_route']);
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM customers WHERE route_id = $route_id");
    $has_customers = mysqli_fetch_assoc($check)['count'];
    if ($has_customers > 0) {
        $message = "<div class='alert alert-danger'>Cannot delete route. It has $has_customers customer(s) assigned.</div>";
    } else {
        mysqli_query($conn, "DELETE FROM routes WHERE id = $route_id");
        $message = "<div class='alert alert-success'>Route deleted successfully!</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_route'])) {
    $route_name = mysqli_real_escape_string($conn, $_POST['route_name']);
    $description = mysqli_real_escape_string($conn, $_POST['route_description']);
    $datetime = date('Y-m-d H:i:s');
    mysqli_query($conn, "INSERT INTO routes (route_name, description, created_datetime) VALUES ('$route_name', '$description', '$datetime')");
    $message = "<div class='alert alert-success'>Route added successfully!</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_route'])) {
    $route_id = intval($_POST['route_id']);
    $route_name = mysqli_real_escape_string($conn, $_POST['edit_route_name']);
    $description = mysqli_real_escape_string($conn, $_POST['edit_route_description']);
    mysqli_query($conn, "UPDATE routes SET route_name='$route_name', description='$description' WHERE id=$route_id");
    $message = "<div class='alert alert-success'>Route updated successfully!</div>";
}

// ---- DELETE Customer ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
    header("Location: customers.php?msg=deleted");
    exit();
}

// ---- ADD / EDIT Customer ----
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $route_id = intval($_POST['route_id']);
        $rate = floatval($_POST['bottle_rate']);
        $deposit = floatval($_POST['security_deposit']);
        $opening = floatval($_POST['opening_balance']);
        $empties = intval($_POST['empty_bottles_balance']);
        $status = $_POST['status'];
        $datetime = date('Y-m-d H:i:s');

        $query = "INSERT INTO customers (customer_name, mobile, address, route_id, bottle_rate, security_deposit, opening_balance, empty_bottles_balance, outstanding_balance, status, created_datetime) 
                  VALUES ('$name', '$mobile', '$address', $route_id, $rate, $deposit, $opening, $empties, $opening, '$status', '$datetime')";
        if (mysqli_query($conn, $query)) {
            $cid = mysqli_insert_id($conn);
            if ($opening != 0) {
                $desc = "Opening Balance";
                $debit = ($opening > 0) ? $opening : 0;
                $credit = ($opening < 0) ? abs($opening) : 0;
                $balance = $opening;
                mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance) 
                                     VALUES ($cid, '$datetime', '$desc', $debit, $credit, $balance)");
            }
            $message = "<div class='alert alert-success'>Customer added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
    elseif (isset($_POST['edit_customer'])) {
        $id = intval($_POST['customer_id']);
        $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $route_id = intval($_POST['route_id']);
        $rate = floatval($_POST['bottle_rate']);
        $deposit = floatval($_POST['security_deposit']);
        $empties = intval($_POST['empty_bottles_balance']);
        $status = $_POST['status'];
        $sql = "UPDATE customers SET customer_name='$name', mobile='$mobile', address='$address', route_id=$route_id, bottle_rate=$rate, security_deposit=$deposit, empty_bottles_balance=$empties, status='$status' WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success'>Customer updated!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Update failed: " . mysqli_error($conn) . "</div>";
        }
    }
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "<div class='alert alert-success'>Customer deleted.</div>";

// ---- Fetch routes ----
$routes = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
$has_routes = mysqli_num_rows($routes) > 0;
if (!$has_routes) {
    $message .= "<div class='alert alert-warning'>⚠️ No routes defined. Please add routes below before adding customers.</div>";
}

// ---- Fetch customers ----
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$route_filter = isset($_GET['route_filter']) ? intval($_GET['route_filter']) : 0;
$where = " WHERE 1=1";
if ($search) $where .= " AND (c.customer_name LIKE '%$search%' OR c.mobile LIKE '%$search%')";
if ($route_filter) $where .= " AND c.route_id = $route_filter";

$customers_query = "SELECT c.*, r.route_name FROM customers c LEFT JOIN routes r ON c.route_id = r.id $where ORDER BY c.customer_name";
$customers = mysqli_query($conn, $customers_query);

// ---- Stats for cards ----
$stats_query = mysqli_query($conn, "SELECT 
    COUNT(*) as total_customers,
    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active_customers,
    COALESCE(SUM(outstanding_balance),0) as total_outstanding,
    COALESCE(SUM(empty_bottles_balance),0) as total_empty_bottles
    FROM customers");
$stats = mysqli_fetch_assoc($stats_query);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Table Styles */
.customers-table {
    font-size: 14px;
}
.customers-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.customers-table td {
    padding: 12px 10px;
    vertical-align: middle;
    word-break: break-word;
}
.customers-table td:first-child,
.customers-table th:first-child {
    text-align: center;
}
.address-cell {
    max-width: 200px;
    min-width: 150px;
}
.date-cell {
    white-space: nowrap;
    font-size: 12px;
}
.badge-status {
    font-size: 11px;
    padding: 5px 10px;
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
    .customers-table {
        font-size: 12px;
    }
    .customers-table td, 
    .customers-table th {
        padding: 8px 6px;
    }
    .address-cell {
        max-width: 120px;
    }
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-users me-2" style="color: #A04657;"></i> Customers
        </h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-plus-circle me-2"></i> Add New Customer
        </button>
    </div>

    <?php echo $message; ?>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-users fa-2x mb-2" style="color: #A04657;"></i>
                    <h5 class="text-muted mb-1">Total Customers</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_customers']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-user-check fa-2x mb-2" style="color: #28a745;"></i>
                    <h5 class="text-muted mb-1">Active Customers</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['active_customers']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-rupee-sign fa-2x mb-2" style="color: #ffc107;"></i>
                    <h5 class="text-muted mb-1">Total Outstanding</h5>
                    <h2 class="mb-0">Rs <?php echo number_format($stats['total_outstanding'], 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-cube fa-2x mb-2" style="color: #17a2b8;"></i>
                    <h5 class="text-muted mb-1">Empty Bottles</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_empty_bottles']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search by Name</label>
                    <input type="text" name="search" class="form-control" placeholder="Enter customer name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-route me-1"></i> Filter by Route</label>
                    <select name="route_filter" class="form-select">
                        <option value="0">All Routes</option>
                        <?php 
                        $routes_filter = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                        if($routes_filter){
                            while($rt = mysqli_fetch_assoc($routes_filter)) { 
                                $selected = ($route_filter == $rt['id']) ? 'selected' : '';
                                echo "<option value='{$rt['id']}' $selected>" . htmlspecialchars($rt['route_name']) . "</option>";
                            } 
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100 rounded-pill">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Route Management Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-route me-2" style="color: #A04657;"></i> Route Management</h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-5 mb-3 mb-md-0">
                    <form method="POST" class="d-flex gap-2">
                        <input type="text" name="route_name" class="form-control" placeholder="New Route Name" required>
                        <input type="text" name="route_description" class="form-control" placeholder="Description">
                        <button type="submit" name="add_route" class="btn btn-primary">Add</button>
                    </form>
                </div>
                <div class="col-md-7">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th>Route Name</th><th>Description</th><th style="width:100px">Actions</th>
                            </thead>
                            <tbody>
                                <?php 
                                $routes_list = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                                if($routes_list && mysqli_num_rows($routes_list) > 0){
                                    while($rt = mysqli_fetch_assoc($routes_list)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rt['route_name']); ?></td>
                                        <td><?php echo htmlspecialchars($rt['description']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning editRouteBtn" 
                                                data-id="<?php echo $rt['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($rt['route_name']); ?>"
                                                data-desc="<?php echo htmlspecialchars($rt['description']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete_route=<?php echo $rt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this route?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile;
                                } else { ?>
                                    <tr><td colspan="3" class="text-center text-muted">No routes added yet</td><td></td><td></td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table - All Fields -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover customers-table mb-0" id="customersTable">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:140px">Name</th>
                            <th style="min-width:110px">Mobile</th>
                            <th style="min-width:160px">Address</th>
                            <th style="min-width:100px">Route</th>
                            <th style="min-width:90px">Rate (Rs)</th>
                            <th style="min-width:110px">Security Deposit (Rs)</th>
                            <th style="min-width:110px">Opening Balance (Rs)</th>
                            <th style="min-width:100px">Outstanding (Rs)</th>
                            <th style="min-width:90px">Empty Bottles</th>
                            <th style="min-width:120px">Created Date</th>
                            <th style="min-width:80px">Status</th>
                            <th style="min-width:90px; text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers && mysqli_num_rows($customers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($customers)): ?>
                                <tr>
                                    <td class="text-center fw-semibold"><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                    <td class="address-cell"><?php echo nl2br(htmlspecialchars($row['address'] ?? '-')); ?></td>
                                    <td><?php echo $row['route_name'] ?? '<span class="badge bg-secondary">No Route</span>'; ?></td>
                                    <td>Rs <?php echo number_format($row['bottle_rate'], 2); ?></td>
                                    <td>Rs <?php echo number_format($row['security_deposit'], 2); ?></td>
                                    <td>Rs <?php echo number_format($row['opening_balance'], 2); ?></td>
                                    <td class="text-danger fw-bold">Rs <?php echo number_format($row['outstanding_balance'], 2); ?></td>
                                    <td><?php echo number_format($row['empty_bottles_balance']); ?></td>
                                    <td class="date-cell"><?php echo date('d-m-Y H:i', strtotime($row['created_datetime'])); ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Active'): ?>
                                            <span class="badge bg-success rounded-pill badge-status">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill badge-status">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons text-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning editCustomerBtn" 
                                            data-id="<?php echo $row['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                            data-mobile="<?php echo $row['mobile']; ?>"
                                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                            data-route="<?php echo $row['route_id']; ?>"
                                            data-rate="<?php echo $row['bottle_rate']; ?>"
                                            data-deposit="<?php echo $row['security_deposit']; ?>"
                                            data-opening="<?php echo $row['opening_balance']; ?>"
                                            data-empties="<?php echo $row['empty_bottles_balance']; ?>"
                                            data-status="<?php echo $row['status']; ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete customer? This will remove all related deliveries, payments, and ledger entries.')" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3 d-block"></i>
                                    No customers found. Click <strong>"Add New Customer"</strong> to get started.
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" class="form-control" placeholder="e.g., 9876543210">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Route *</label>
                            <select name="route_id" class="form-select" required>
                                <option value="">Select Route</option>
                                <?php 
                                $routes_add = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                                if($routes_add){
                                    while($r = mysqli_fetch_assoc($routes_add)) {
                                        echo "<option value='{$r['id']}'>" . htmlspecialchars($r['route_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bottle Rate (Rs) *</label>
                            <input type="number" step="0.01" name="bottle_rate" class="form-control" placeholder="Rate per bottle" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (Rs)</label>
                            <input type="number" step="0.01" name="security_deposit" class="form-control" placeholder="Deposit amount" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opening Balance (Rs)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" placeholder="If any" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empty Bottles Balance</label>
                            <input type="number" name="empty_bottles_balance" class="form-control" placeholder="Number of empty bottles" value="0">
                        </div>
                        <div class="col-md-6">
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
                    <button type="submit" name="add_customer" class="btn btn-primary rounded-pill px-4">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Edit Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="customer_id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="customer_name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" id="edit_mobile" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Route *</label>
                            <select name="route_id" id="edit_route" class="form-select">
                                <?php 
                                $routes_edit = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                                if($routes_edit){
                                    while($r = mysqli_fetch_assoc($routes_edit)) {
                                        echo "<option value='{$r['id']}'>" . htmlspecialchars($r['route_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bottle Rate (Rs) *</label>
                            <input type="number" step="0.01" name="bottle_rate" id="edit_rate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (Rs)</label>
                            <input type="number" step="0.01" name="security_deposit" id="edit_deposit" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empty Bottles Balance</label>
                            <input type="number" name="empty_bottles_balance" id="edit_empties" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-primary rounded-pill px-4">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Route Modal -->
<div class="modal fade" id="editRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="route_id" id="edit_route_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Route Name *</label>
                        <input type="text" name="edit_route_name" id="edit_route_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="edit_route_description" id="edit_route_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_route" class="btn btn-warning rounded-pill px-4">Update Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Wait for document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Edit Customer Button Handler
    const editButtons = document.querySelectorAll('.editCustomerBtn');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_mobile').value = this.getAttribute('data-mobile');
            document.getElementById('edit_address').value = this.getAttribute('data-address');
            document.getElementById('edit_route').value = this.getAttribute('data-route');
            document.getElementById('edit_rate').value = this.getAttribute('data-rate');
            document.getElementById('edit_deposit').value = this.getAttribute('data-deposit');
            document.getElementById('edit_empties').value = this.getAttribute('data-empties');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            
            var editModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
            editModal.show();
        });
    });
    
    // Edit Route Button Handler
    const editRouteBtns = document.querySelectorAll('.editRouteBtn');
    editRouteBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_route_id').value = this.getAttribute('data-id');
            document.getElementById('edit_route_name').value = this.getAttribute('data-name');
            document.getElementById('edit_route_description').value = this.getAttribute('data-desc');
            
            var editRouteModal = new bootstrap.Modal(document.getElementById('editRouteModal'));
            editRouteModal.show();
        });
    });
});

// jQuery fallback
if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
        $('.editCustomerBtn').off('click').on('click', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_mobile').val($(this).data('mobile'));
            $('#edit_address').val($(this).data('address'));
            $('#edit_route').val($(this).data('route'));
            $('#edit_rate').val($(this).data('rate'));
            $('#edit_deposit').val($(this).data('deposit'));
            $('#edit_empties').val($(this).data('empties'));
            $('#edit_status').val($(this).data('status'));
            $('#editCustomerModal').modal('show');
        });
        
        $('.editRouteBtn').off('click').on('click', function() {
            $('#edit_route_id').val($(this).data('id'));
            $('#edit_route_name').val($(this).data('name'));
            $('#edit_route_description').val($(this).data('desc'));
            $('#editRouteModal').modal('show');
        });
    });
}
</script>

<?php include '../includes/footer.php'; ?>