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
    $block = mysqli_real_escape_string($conn, $_POST['route_block']);
    $area = mysqli_real_escape_string($conn, $_POST['route_area']);
    $salesman = mysqli_real_escape_string($conn, $_POST['route_salesman']);
    $datetime = date('Y-m-d H:i:s');
    $res = mysqli_query($conn, "INSERT INTO routes (route_name, block, area, salesman, created_datetime) VALUES ('$route_name', '$block', '$area', '$salesman', '$datetime')");
    if ($res) {
        $message = "<div class='alert alert-success'>Route added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to add route: " . mysqli_error($conn) . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_route'])) {
    $route_id = intval($_POST['route_id']);
    $route_name = mysqli_real_escape_string($conn, $_POST['edit_route_name']);
    $block = mysqli_real_escape_string($conn, $_POST['edit_route_block']);
    $area = mysqli_real_escape_string($conn, $_POST['edit_route_area']);
    $salesman = mysqli_real_escape_string($conn, $_POST['edit_route_salesman']);
    $res = mysqli_query($conn, "UPDATE routes SET route_name='$route_name', block='$block', area='$area', salesman='$salesman' WHERE id=$route_id");
    if ($res) {
        $message = "<div class='alert alert-success'>Route updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to update route: " . mysqli_error($conn) . "</div>";
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.route-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
    font-size: 13px;
}
.route-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-route me-2" style="color: #A04657;"></i> Route Management
        </h2>
        <a href="customer_view.php" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-users me-2"></i> View Customers
        </a>
    </div>

    <?php echo $message; ?>

    <!-- Route Management Card -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2" style="color: #A04657;"></i> Add New Route</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="route_name" class="form-control" placeholder="Route Name" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="route_block" class="form-control" placeholder="Block">
                </div>
                <div class="col-md-2">
                    <input type="text" name="route_area" class="form-control" placeholder="Area">
                </div>
                <div class="col-md-3">
                    <input type="text" name="route_salesman" class="form-control" placeholder="Salesman">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_route" class="btn btn-primary w-100 rounded-pill">
                        <i class="fas fa-plus me-2"></i> Add Route
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Routes Table -->
    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> All Routes</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover route-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Route Name</th>
                            <th>Block</th>
                            <th>Area</th>
                            <th>Salesman</th>
                            <th style="width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $routes_list = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");
                        if($routes_list && mysqli_num_rows($routes_list) > 0){
                            $i = 1;
                            while($rt = mysqli_fetch_assoc($routes_list)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($rt['route_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rt['block'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rt['area'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rt['salesman'] ?? '-'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning editRouteBtn" 
                                        data-id="<?php echo $rt['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($rt['route_name']); ?>"
                                        data-block="<?php echo htmlspecialchars($rt['block'] ?? ''); ?>"
                                        data-area="<?php echo htmlspecialchars($rt['area'] ?? ''); ?>"
                                        data-salesman="<?php echo htmlspecialchars($rt['salesman'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete_route=<?php echo $rt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this route?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile;
                        } else { ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No routes added yet</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
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
                        <label class="form-label fw-semibold">Block</label>
                        <input type="text" name="edit_route_block" id="edit_route_block" class="form-control" placeholder="Block">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area</label>
                        <input type="text" name="edit_route_area" id="edit_route_area" class="form-control" placeholder="Area">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Salesman</label>
                        <input type="text" name="edit_route_salesman" id="edit_route_salesman" class="form-control" placeholder="Salesman">
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
document.addEventListener('DOMContentLoaded', function() {
    const editRouteBtns = document.querySelectorAll('.editRouteBtn');
    editRouteBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_route_id').value = this.getAttribute('data-id');
            document.getElementById('edit_route_name').value = this.getAttribute('data-name');
            document.getElementById('edit_route_block').value = this.getAttribute('data-block');
            document.getElementById('edit_route_area').value = this.getAttribute('data-area');
            document.getElementById('edit_route_salesman').value = this.getAttribute('data-salesman');
            
            var editRouteModal = new bootstrap.Modal(document.getElementById('editRouteModal'));
            editRouteModal.show();
        });
    });
});

if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
        $('.editRouteBtn').off('click').on('click', function() {
            $('#edit_route_id').val($(this).data('id'));
            $('#edit_route_name').val($(this).data('name'));
            $('#edit_route_block').val($(this).data('block'));
            $('#edit_route_area').val($(this).data('area'));
            $('#edit_route_salesman').val($(this).data('salesman'));
            $('#editRouteModal').modal('show');
        });
    });
}
</script>

<?php include '../includes/footer.php'; ?>
