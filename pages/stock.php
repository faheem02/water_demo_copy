<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

// Handle Add New Product with Sale Price
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $sale_price = floatval($_POST['sale_price']);
    $opening_stock = intval($_POST['opening_stock']);
    $datetime = date('Y-m-d H:i:s');
    
    $insert_query = "INSERT INTO products (product_name, sale_price, current_stock, status, created_datetime) 
                     VALUES ('$product_name', $sale_price, $opening_stock, 'Active', '$datetime')";
    
    if(mysqli_query($conn, $insert_query)) {
        $product_id = mysqli_insert_id($conn);
        
        // Add opening stock to stock ledger
        if($opening_stock > 0) {
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$datetime', 'IN', 'opening', $opening_stock, $opening_stock, 'Opening stock: $opening_stock bottles added', '$datetime')");
        }
        
        $success = "Product added successfully! Sale Price: Rs " . number_format($sale_price, 2) . " | Opening Stock: " . number_format($opening_stock) . " bottles!";
    } else {
        $error = "Error adding product: " . mysqli_error($conn);
    }
}

// Handle Stock In (Production)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock_in'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $production_date = mysqli_real_escape_string($conn, $_POST['production_date']);
    $datetime = date('Y-m-d H:i:s');
    
    // Get current stock
    $product_query = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE id=$product_id");
    if($product_query && mysqli_num_rows($product_query) > 0) {
        $product = mysqli_fetch_assoc($product_query);
        $new_stock = $product['current_stock'] + $quantity;
        
        // Insert stock in record
        $insert_query = "INSERT INTO stock_in (product_id, quantity, stock_date, created_by, created_datetime) 
                         VALUES ($product_id, $quantity, '$production_date', {$_SESSION['admin_id']}, '$datetime')";
        
        if(mysqli_query($conn, $insert_query)) {
            $stock_in_id = mysqli_insert_id($conn);
            
            // Update product stock
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            $running_stock = $new_stock;
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$production_date', 'IN', 'production', $stock_in_id, $quantity, $running_stock, 'Production: $quantity {$product['product_name']} produced on $production_date', '$datetime')");
            
            $success = "Production recorded successfully! New stock level: " . number_format($new_stock) . " bottles";
        } else {
            $error = "Error recording production: " . mysqli_error($conn);
        }
    } else {
        $error = "Product not found!";
    }
}

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['adjust_quantity']);
    $adjust_type = $_POST['adjust_type'];
    $notes = mysqli_real_escape_string($conn, $_POST['adjust_notes']);
    $datetime = date('Y-m-d H:i:s');
    
    $product_query = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE id=$product_id");
    if($product_query && mysqli_num_rows($product_query) > 0) {
        $product = mysqli_fetch_assoc($product_query);
        
        if($adjust_type == 'remove') {
            if($quantity > $product['current_stock']) {
                $error = "Cannot remove $quantity bottles. Current stock is only " . $product['current_stock'] . " bottles.";
            } else {
                $new_stock = $product['current_stock'] - $quantity;
                mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
                
                // Add to stock ledger
                $running_stock = $new_stock;
                mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_out, running_stock, description, created_datetime) 
                                     VALUES ($product_id, '$datetime', 'OUT', 'adjustment', $quantity, $running_stock, 'Stock adjustment: Removed $quantity bottles. $notes', '$datetime')");
                $success = "Stock reduced successfully! New stock level: " . number_format($new_stock) . " bottles";
            }
        } else {
            $new_stock = $product['current_stock'] + $quantity;
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            $running_stock = $new_stock;
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$datetime', 'IN', 'adjustment', $quantity, $running_stock, 'Stock adjustment: Added $quantity bottles. $notes', '$datetime')");
            $success = "Stock increased successfully! New stock level: " . number_format($new_stock) . " bottles";
        }
    } else {
        $error = "Product not found!";
    }
}

// Get all products
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY product_name");

// Get stock summary
$total_query = mysqli_query($conn, "SELECT SUM(current_stock) as total FROM products");
$total_stock = ($total_query && mysqli_num_rows($total_query) > 0) ? mysqli_fetch_assoc($total_query)['total'] : 0;

$low_query = mysqli_query($conn, "SELECT * FROM products WHERE current_stock <= min_stock_level");
$low_stock_count = ($low_query) ? mysqli_num_rows($low_query) : 0;

// Get recent production records
$recent_production = mysqli_query($conn, "SELECT si.*, p.product_name FROM stock_in si JOIN products p ON si.product_id = p.id ORDER BY si.stock_date DESC LIMIT 10");

// Get stock ledger entries
$stock_ledger = mysqli_query($conn, "SELECT sl.*, p.product_name FROM stock_ledger sl JOIN products p ON sl.product_id = p.id ORDER BY sl.transaction_date DESC LIMIT 20");

// Get monthly in and out
$month_in_query = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock_in WHERE MONTH(stock_date)=MONTH(CURDATE()) AND YEAR(stock_date)=YEAR(CURDATE())");
$month_in = ($month_in_query && mysqli_num_rows($month_in_query) > 0) ? mysqli_fetch_assoc($month_in_query)['total'] : 0;

$month_out_query = mysqli_query($conn, "SELECT SUM(bottles_delivered) as total FROM water_deliveries WHERE MONTH(delivery_datetime)=MONTH(CURDATE()) AND YEAR(delivery_datetime)=YEAR(CURDATE())");
$month_out = ($month_out_query && mysqli_num_rows($month_out_query) > 0) ? mysqli_fetch_assoc($month_out_query)['total'] : 0;
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.stock-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.stock-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.summary-card {
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: transform 0.2s;
}
.summary-card.total {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.summary-card.low {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}
.summary-card.in {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}
.summary-card.out {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}
.stock-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}
.stock-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.low-stock {
    background-color: #fff3cd;
}
.price-cell {
    font-weight: 600;
    color: #28a745;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-boxes me-2" style="color: #A04657;"></i> Stock Management
        </h2>
        <div>
            <button class="btn btn-primary rounded-pill px-4 me-2" data-bs-toggle="modal" data-bs-target="#addStockInModal">
                <i class="fas fa-arrow-down me-2"></i> Production
            </button>
            <button class="btn btn-warning rounded-pill px-4 me-2" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                <i class="fas fa-sliders-h me-2"></i> Adjust Stock
            </button>
            <button class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus-circle me-2"></i> New Product
            </button>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="summary-card total">
                <i class="fas fa-boxes fa-2x mb-2 opacity-50"></i>
                <h6>Total Stock</h6>
                <h2 class="mb-0"><?php echo number_format($total_stock); ?> Bottles</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card low">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 opacity-50"></i>
                <h6>Low Stock Items</h6>
                <h2 class="mb-0"><?php echo $low_stock_count; ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card in">
                <i class="fas fa-arrow-down fa-2x mb-2 opacity-50"></i>
                <h6>Production (This Month)</h6>
                <h2 class="mb-0"><?php echo number_format($month_in); ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card out">
                <i class="fas fa-arrow-up fa-2x mb-2 opacity-50"></i>
                <h6>Stock Out (This Month)</h6>
                <h2 class="mb-0"><?php echo number_format($month_out); ?></h2>
            </div>
        </div>
    </div>

    <!-- Products Stock Table -->
    <div class="card stock-card mb-4">
        <div class="card-header">
            <i class="fas fa-list-alt me-2"></i> Current Stock Levels
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table stock-table mb-0">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th class="text-center">Sale Price (Rs)</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Min Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                
                    <tbody>
                
                        <?php if($products && mysqli_num_rows($products) > 0): ?>
                
                            <?php while($p = mysqli_fetch_assoc($products)): 
                                $is_low = $p['current_stock'] <= $p['min_stock_level'];
                            ?>
                
                                <tr class="<?php echo $is_low ? 'low-stock' : ''; ?>">
                
                                    <!-- Product Name -->
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($p['product_name']); ?>
                                        </strong>
                                    </td>
                
                                    <!-- Sale Price -->
                                    <td class="text-center price-cell">
                                        Rs <?php echo number_format($p['sale_price'], 2); ?>
                                    </td>
                
                                    <!-- Current Stock -->
                                    <td class="text-center">
                                        <span class="badge <?php echo $is_low ? 'bg-danger' : 'bg-success'; ?> rounded-pill px-3">
                                            <?php echo number_format($p['current_stock']); ?>
                                        </span>
                                    </td>
                
                                    <!-- Min Level -->
                                    <td class="text-center">
                                        <?php echo number_format($p['min_stock_level']); ?>
                                    </td>
                
                                    <!-- Status -->
                                    <td>
                                        <?php if($is_low): ?>
                                            <span class="badge bg-warning text-dark">
                                                Low Stock!
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                
                                </tr>
                
                            <?php endwhile; ?>
                
                        <?php else: ?>
                
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    No products found. Click "New Product" to add.
                                </td>
                            </tr>
                
                        <?php endif; ?>
                
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Production Records -->
    <div class="card stock-card mb-4">
        <div class="card-header">
            <i class="fas fa-arrow-down me-2"></i> Recent Production Records
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table stock-table mb-0">
                    <thead>
                        <tr><th>Production Date</th><th>Product</th><th class="text-center">Quantity Produced</th></tr>
                    </thead>
                    <tbody>
                        <?php if($recent_production && mysqli_num_rows($recent_production) > 0): ?>
                            <?php while($si = mysqli_fetch_assoc($recent_production)): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($si['stock_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($si['product_name']); ?></td>
                                    <td class="text-center fw-bold"><?php echo number_format($si['quantity']); ?> bottles</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4">No production records yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Ledger -->
    <div class="card stock-card">
        <div class="card-header">
            <i class="fas fa-history me-2"></i> Stock Ledger (Transaction History)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table stock-table mb-0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th class="text-center">IN</th>
                            <th class="text-center">OUT</th>
                            <th class="text-center">Running Stock</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($stock_ledger && mysqli_num_rows($stock_ledger) > 0): ?>
                            <?php while($sl = mysqli_fetch_assoc($stock_ledger)): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y h:i A', strtotime($sl['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sl['product_name']); ?></td>
                                    <td>
                                        <?php if($sl['transaction_type'] == 'IN'): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-arrow-down me-1"></i> Stock In</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-arrow-up me-1"></i> Stock Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-success fw-bold"><?php echo $sl['quantity_in'] > 0 ? number_format($sl['quantity_in']) : '—'; ?></td>
                                    <td class="text-center text-danger fw-bold"><?php echo $sl['quantity_out'] > 0 ? number_format($sl['quantity_out']) : '—'; ?></td>
                                    <td class="text-center fw-bold"><?php echo number_format($sl['running_stock']); ?></td>
                                    <td><small><?php echo htmlspecialchars($sl['description']); ?></small></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4">No stock transactions found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Add Product Modal with Sale Price -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" required placeholder="e.g., 20 Liter Water Bottle">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sale Price (Rs) *</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="sale_price" class="form-control" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-text text-muted">This price will be used for customer billing.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening Stock *</label>
                        <input type="number" name="opening_stock" class="form-control" required value="0" placeholder="Initial stock quantity">
                        <div class="form-text text-muted">Enter the initial stock quantity for this product.</div>
                    </div>
                    <div class="mt-3 text-muted small">
                        <i class="fas fa-info-circle"></i> You can add more stock later using the "Production" button.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Production Modal -->
<div class="modal fade" id="addStockInModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-industry me-2"></i> Add Production</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php 
                            $prod_query = mysqli_query($conn, "SELECT * FROM products WHERE status='Active' ORDER BY product_name");
                            if($prod_query && mysqli_num_rows($prod_query) > 0):
                                while($p = mysqli_fetch_assoc($prod_query)): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Sale: Rs <?php echo number_format($p['sale_price'], 2); ?> | Stock: <?php echo number_format($p['current_stock']); ?> bottles)</option>
                            <?php 
                                endwhile;
                            else: ?>
                                <option value="" disabled>No products found. Please add a product first.</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Production Date *</label>
                        <input type="datetime-local" name="production_date" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Produced *</label>
                        <input type="number" name="quantity" class="form-control" required placeholder="Number of bottles produced">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_stock_in" class="btn btn-primary">Add Production</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-sliders-h me-2"></i> Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php 
                            $prod_query2 = mysqli_query($conn, "SELECT * FROM products WHERE status='Active' ORDER BY product_name");
                            if($prod_query2 && mysqli_num_rows($prod_query2) > 0):
                                while($p = mysqli_fetch_assoc($prod_query2)): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Sale: Rs <?php echo number_format($p['sale_price'], 2); ?> | Stock: <?php echo number_format($p['current_stock']); ?> bottles)</option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type *</label>
                        <select name="adjust_type" class="form-select" required>
                            <option value="add">Add Stock (+) Increase</option>
                            <option value="remove">Remove Stock (-) Decrease</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="adjust_quantity" class="form-control" required placeholder="Number of bottles">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason / Notes *</label>
                        <textarea name="adjust_notes" class="form-control" rows="2" required placeholder="Why is this adjustment needed?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-warning">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>