<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';

// ---- DELETE Customer ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
    header("Location: customer_view.php?msg=deleted");
    exit();
}

// ---- ADD / EDIT Customer ----
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $route_id = intval($_POST['route_id']);
        $block = mysqli_real_escape_string($conn, $_POST['customer_block']);
        $area = mysqli_real_escape_string($conn, $_POST['customer_area']);
        $salesman = mysqli_real_escape_string($conn, $_POST['customer_salesman']);
        $rate = floatval($_POST['bottle_rate']);
        $deposit = floatval($_POST['security_deposit']);
        $opening = floatval($_POST['opening_balance']);
        $empties = intval($_POST['empty_bottles_balance']);
        $status = $_POST['status'];
        $datetime = date('Y-m-d H:i:s');

        $query = "INSERT INTO customers (customer_name, mobile, address, route_id, bottle_rate, security_deposit, opening_balance, empty_bottles_balance, outstanding_balance, block, area, salesman, status, created_datetime) 
                  VALUES ('$name', '$mobile', '$address', $route_id, $rate, $deposit, $opening, $empties, $opening, '$block', '$area', '$salesman', '$status', '$datetime')";
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
        $block = mysqli_real_escape_string($conn, $_POST['customer_block']);
        $area = mysqli_real_escape_string($conn, $_POST['customer_area']);
        $salesman = mysqli_real_escape_string($conn, $_POST['customer_salesman']);
        $rate = floatval($_POST['bottle_rate']);
        $deposit = floatval($_POST['security_deposit']);
        $empties = intval($_POST['empty_bottles_balance']);
        $status = $_POST['status'];
        $sql = "UPDATE customers SET customer_name='$name', mobile='$mobile', address='$address', route_id=$route_id, bottle_rate=$rate, security_deposit=$deposit, empty_bottles_balance=$empties, block='$block', area='$area', salesman='$salesman', status='$status' WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success'>Customer updated!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Update failed: " . mysqli_error($conn) . "</div>";
        }
    }
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "<div class='alert alert-success'>Customer deleted.</div>";

// ---- Fetch customers with search and date filter ----
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$route_filter = isset($_GET['route_filter']) ? intval($_GET['route_filter']) : 0;
$block_filter = isset($_GET['block_filter']) ? mysqli_real_escape_string($conn, $_GET['block_filter']) : '';
$area_filter = isset($_GET['area_filter']) ? mysqli_real_escape_string($conn, $_GET['area_filter']) : '';
$salesman_filter = isset($_GET['salesman_filter']) ? mysqli_real_escape_string($conn, $_GET['salesman_filter']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$where = " WHERE 1=1";
if ($search) $where .= " AND (c.customer_name LIKE '%$search%' OR c.mobile LIKE '%$search%' OR c.id LIKE '%$search%')";
if ($route_filter) $where .= " AND c.route_id = $route_filter";
if ($block_filter) $where .= " AND c.block LIKE '%$block_filter%'";
if ($area_filter) $where .= " AND c.area LIKE '%$area_filter%'";
if ($salesman_filter) $where .= " AND c.salesman LIKE '%$salesman_filter%'";
if ($from_date) $where .= " AND DATE(c.created_datetime) >= '$from_date'";
if ($to_date) $where .= " AND DATE(c.created_datetime) <= '$to_date'";

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
            <i class="fas fa-users me-2" style="color: #A04657;"></i> View Customers
        </h2>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-plus-circle me-2"></i> Add New Customer
            </button>
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="printCustomers()">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
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
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search by Name or ID</label>
                    <input type="text" name="search" class="form-control" placeholder="Enter name, ID, mobile..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-route me-1"></i> Route</label>
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
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-cube me-1"></i> Block</label>
                    <input type="text" name="block_filter" class="form-control" placeholder="Block" value="<?php echo htmlspecialchars($block_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-map-marker-alt me-1"></i> Area</label>
                    <input type="text" name="area_filter" class="form-control" placeholder="Area" value="<?php echo htmlspecialchars($area_filter); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-user-tie me-1"></i> Salesman</label>
                    <input type="text" name="salesman_filter" class="form-control" placeholder="Salesman" value="<?php echo htmlspecialchars($salesman_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                    <a href="customer_view.php" class="btn btn-outline-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
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
                            <th style="min-width:90px">Block</th>
                            <th style="min-width:90px">Area</th>
                            <th style="min-width:120px">Salesman</th>
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
                                    <td><?php echo htmlspecialchars($row['block'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['area'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['salesman'] ?? '-'); ?></td>
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
                                            data-block="<?php echo htmlspecialchars($row['block'] ?? ''); ?>"
                                            data-area="<?php echo htmlspecialchars($row['area'] ?? ''); ?>"
                                            data-salesman="<?php echo htmlspecialchars($row['salesman'] ?? ''); ?>"
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
                                <td colspan="16" class="text-center py-5 text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3 d-block"></i>
                                    No customers found.
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
                            <select name="route_id" class="form-select" required onchange="prefillCustomerFields(this, 'add')">
                                <option value="">Select Route</option>
                                <?php 
                                $routes_add = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                                if($routes_add){
                                    while($r = mysqli_fetch_assoc($routes_add)) {
                                        echo "<option value='{$r['id']}' data-block='" . htmlspecialchars($r['block'] ?? '') . "' data-area='" . htmlspecialchars($r['area'] ?? '') . "' data-salesman='" . htmlspecialchars($r['salesman'] ?? '') . "'>" . htmlspecialchars($r['route_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Block</label>
                            <input type="text" name="customer_block" id="add_customer_block" class="form-control" placeholder="Block">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Area</label>
                            <input type="text" name="customer_area" id="add_customer_area" class="form-control" placeholder="Area">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Salesman</label>
                            <input type="text" name="customer_salesman" id="add_customer_salesman" class="form-control" placeholder="Salesman">
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
                            <select name="route_id" id="edit_route" class="form-select" onchange="prefillCustomerFields(this, 'edit')">
                                <?php 
                                $routes_edit = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                                if($routes_edit){
                                    while($r = mysqli_fetch_assoc($routes_edit)) {
                                        echo "<option value='{$r['id']}' data-block='" . htmlspecialchars($r['block'] ?? '') . "' data-area='" . htmlspecialchars($r['area'] ?? '') . "' data-salesman='" . htmlspecialchars($r['salesman'] ?? '') . "'>" . htmlspecialchars($r['route_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Block</label>
                            <input type="text" name="customer_block" id="edit_customer_block" class="form-control" placeholder="Block">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Area</label>
                            <input type="text" name="customer_area" id="edit_customer_area" class="form-control" placeholder="Area">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Salesman</label>
                            <input type="text" name="customer_salesman" id="edit_customer_salesman" class="form-control" placeholder="Salesman">
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

<script>
function prefillCustomerFields(routeSelect, mode) {
    var selected = routeSelect.options[routeSelect.selectedIndex];
    var block = selected.getAttribute('data-block') || '';
    var area = selected.getAttribute('data-area') || '';
    var salesman = selected.getAttribute('data-salesman') || '';
    if (mode === 'add') {
        document.getElementById('add_customer_block').value = block;
        document.getElementById('add_customer_area').value = area;
        document.getElementById('add_customer_salesman').value = salesman;
    } else {
        document.getElementById('edit_customer_block').value = block;
        document.getElementById('edit_customer_area').value = area;
        document.getElementById('edit_customer_salesman').value = salesman;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.editCustomerBtn');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_mobile').value = this.getAttribute('data-mobile');
            document.getElementById('edit_address').value = this.getAttribute('data-address');
            document.getElementById('edit_route').value = this.getAttribute('data-route');
            document.getElementById('edit_customer_block').value = this.getAttribute('data-block');
            document.getElementById('edit_customer_area').value = this.getAttribute('data-area');
            document.getElementById('edit_customer_salesman').value = this.getAttribute('data-salesman');
            document.getElementById('edit_rate').value = this.getAttribute('data-rate');
            document.getElementById('edit_deposit').value = this.getAttribute('data-deposit');
            document.getElementById('edit_empties').value = this.getAttribute('data-empties');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            
            var editModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
            editModal.show();
        });
    });
});

if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
        $('.editCustomerBtn').off('click').on('click', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_mobile').val($(this).data('mobile'));
            $('#edit_address').val($(this).data('address'));
            $('#edit_route').val($(this).data('route'));
            $('#edit_customer_block').val($(this).data('block'));
            $('#edit_customer_area').val($(this).data('area'));
            $('#edit_customer_salesman').val($(this).data('salesman'));
            $('#edit_rate').val($(this).data('rate'));
            $('#edit_deposit').val($(this).data('deposit'));
            $('#edit_empties').val($(this).data('empties'));
            $('#edit_status').val($(this).data('status'));
            $('#editCustomerModal').modal('show');
        });
    });
}
</script>

<!-- Print Overlay -->
<div id="print-overlay">
    <div id="print-area">
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle">
                    <i class="fas fa-tint"></i>
                </div>
                <div class="print-brand-text">
                    <div class="print-owner-name"><?php echo htmlspecialchars($owner_name); ?></div>
                    <div class="print-company"><?php echo htmlspecialchars($company_name); ?></div>
                    <div class="print-address"><?php echo htmlspecialchars($owner_address); ?></div>
                    <div class="print-phone"><?php echo htmlspecialchars($owner_phone); ?></div>
                </div>
            </div>
            <div class="print-divider"></div>
            <div class="print-title-row">
                <span class="print-doc-title">Customers List</span>
                <?php if($from_date || $to_date): ?>
                    <span class="print-date-range">
                        <?php echo $from_date ?: 'Start'; ?> to <?php echo $to_date ?: 'End'; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Route</th>
                    <th>Block</th>
                    <th>Area</th>
                    <th>Salesman</th>
                    <th style="width:100px;" class="text-end">Rate</th>
                    <th style="width:110px;" class="text-end">Outstanding</th>
                    <th style="width:80px;" class="text-end">Empties</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $print_customers = mysqli_query($conn, $customers_query);
                $sno = 1;
                if($print_customers && mysqli_num_rows($print_customers) > 0):
                    while($row = mysqli_fetch_assoc($print_customers)): 
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo $row['route_name'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($row['block'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['area'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['salesman'] ?? '-'); ?></td>
                        <td class="text-end"><?php echo number_format($row['bottle_rate'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($row['outstanding_balance'], 2); ?></td>
                        <td class="text-end"><?php echo $row['empty_bottles_balance']; ?></td>
                        <td><?php echo $row['status']; ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
</div>

<style>
#print-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #fff;
    z-index: 999999;
    overflow: auto;
}
#print-area {
    width: 794px;
    margin: 0 auto;
    padding: 35px 40px;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.print-header {
    margin-bottom: 22px;
}
.print-brand-row {
    display: flex;
    align-items: center;
    gap: 18px;
}
.print-logo-circle {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #A04657, #c96b7e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    flex-shrink: 0;
}
.print-brand-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.print-company {
    font-size: 18px;
    font-weight: 700;
    color: #A04657;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-owner-name {
    font-size: 22px;
    font-weight: 800;
    color: #222;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-address {
    font-size: 13px;
    color: #666;
}
.print-phone {
    font-size: 14px;
    font-weight: 600;
    color: #A04657;
}
.print-divider {
    height: 2px;
    background: linear-gradient(to right, #A04657, #e0a0ab);
    margin: 14px 0 10px;
    border-radius: 2px;
}
.print-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.print-doc-title {
    font-size: 15px;
    font-weight: 700;
    color: #444;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-date-range {
    font-size: 12px;
    color: #888;
    background: #f5f5f5;
    padding: 5px 14px;
    border-radius: 20px;
}
.print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.print-table th {
    background: #A04657;
    color: #fff;
    padding: 10px 12px;
    font-weight: 600;
    font-size: 12px;
    text-align: left;
}
.print-table th.text-end,
.print-table td.text-end {
    text-align: right;
}
.print-table td {
    padding: 9px 12px;
    border-bottom: 1px solid #e6e6e6;
    color: #333;
}
.print-table tbody tr:nth-child(even) {
    background: #f9f9f9;
}
.print-table tbody tr:last-child td {
    border-bottom: 2px solid #A04657;
}
.print-footer {
    margin-top: 18px;
    text-align: center;
    font-size: 11px;
    color: #aaa;
    padding-top: 12px;
    border-top: 1px solid #eee;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
function printCustomers() {
    const overlay = document.getElementById('print-overlay');
    const printArea = document.getElementById('print-area');
    overlay.style.display = 'block';

    setTimeout(function() {
        html2canvas(printArea, {
            scale: 3,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: printArea.scrollWidth,
            height: printArea.scrollHeight
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const w = window.open('', '_blank');
            w.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Customers List</title>
                    <style>
                        @page { margin: 0; size: A4 landscape; }
                        body { margin: 0; display: flex; justify-content: center; padding: 20px; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src="${imgData}" />
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 300);
                        }
                    <\/script>
                </body>
                </html>
            `);
            w.document.close();
            overlay.style.display = 'none';
        }).catch(err => {
            console.error(err);
            alert('Print failed. Please try again.');
            overlay.style.display = 'none';
        });
    }, 200);
}
</script>

<?php include '../includes/footer.php'; ?>
