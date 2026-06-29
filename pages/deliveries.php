<?php
// Handle AJAX request for product price and stock
if(isset($_GET['ajax_product_price']) && isset($_GET['pid'])){
    require_once '../includes/db.php';
    $pid = intval($_GET['pid']);
    $res = mysqli_query($conn, "SELECT sale_price, current_stock FROM products WHERE id=$pid AND status='Active'");
    if($row = mysqli_fetch_assoc($res)){
        echo json_encode(['price' => $row['sale_price'], 'stock' => $row['current_stock']]);
    } else {
        echo json_encode(['price' => 0, 'stock' => 0]);
    }
    exit;
}

require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_delivery'])) {
    $product_id = intval($_POST['product_id']);
    $bottles = intval($_POST['bottles_delivered']);
    $empties_returned = intval($_POST['empty_bottles_returned']);
    $rate = floatval($_POST['bottle_rate']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $datetime = date('Y-m-d H:i:s');

    // Handle walk-in customer
    if ($_POST['customer_id'] === 'new') {
        $w_name = mysqli_real_escape_string($conn, $_POST['walkin_name']);
        $w_mobile = mysqli_real_escape_string($conn, $_POST['walkin_mobile']);
        $w_address = mysqli_real_escape_string($conn, $_POST['walkin_address']);
        $w_route = intval($_POST['walkin_route']) ?: 'NULL';
        $w_block = mysqli_real_escape_string($conn, $_POST['walkin_block']);
        $w_area = mysqli_real_escape_string($conn, $_POST['walkin_area']);
        $w_salesman = mysqli_real_escape_string($conn, $_POST['walkin_salesman']);
        mysqli_query($conn, "INSERT INTO customers (customer_name, mobile, address, route_id, bottle_rate, outstanding_balance, block, area, salesman, status, created_datetime) VALUES ('$w_name', '$w_mobile', '$w_address', $w_route, $rate, 0, '$w_block', '$w_area', '$w_salesman', 'Active', '$datetime')");
        $customer_id = mysqli_insert_id($conn);
        $cust = ['customer_name' => $w_name, 'outstanding_balance' => 0, 'empty_bottles_balance' => 0, 'bottle_rate' => $rate, 'block' => $w_block, 'area' => $w_area, 'salesman' => $w_salesman, 'route_name' => ''];
        if ($w_route !== 'NULL') {
            $route_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT route_name FROM routes WHERE id=$w_route"));
            $cust['route_name'] = $route_row['route_name'] ?? '';
        }
    } else {
        $customer_id = intval($_POST['customer_id']);
        // Get customer details
        $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.bottle_rate, c.outstanding_balance, c.empty_bottles_balance, c.customer_name, c.block, c.area, c.salesman, r.route_name FROM customers c LEFT JOIN routes r ON c.route_id = r.id WHERE c.id=$customer_id"));
    }
    
    // Get product details
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_name, sale_price, current_stock, min_stock_level FROM products WHERE id=$product_id AND status='Active'"));
    
    if($cust && $product) {
        $total = $bottles * $rate;
        $new_outstanding = $cust['outstanding_balance'] + $total;
        $new_empties = $cust['empty_bottles_balance'] + $empties_returned - $bottles;
        
        // CHECK STOCK AVAILABILITY
        if($bottles > $product['current_stock']) {
            $error = "Insufficient stock! Available stock: " . $product['current_stock'] . " bottles. Required: $bottles bottles.";
        } else {
            // Insert delivery record with product_id
            mysqli_query($conn, "INSERT INTO water_deliveries (customer_id, product_id, bottles_delivered, empty_bottles_returned, bottle_rate, total_amount, notes, delivery_datetime) 
                                 VALUES ($customer_id, $product_id, $bottles, $empties_returned, $rate, $total, '$notes', '$datetime')");
            
            $delivery_id = mysqli_insert_id($conn);
            
            // Update customer outstanding and empty bottles
            mysqli_query($conn, "UPDATE customers SET outstanding_balance = $new_outstanding, empty_bottles_balance = $new_empties WHERE id=$customer_id");
            
            // Ledger entry: Debit (sale)
            $running = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_balance FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id DESC LIMIT 1"))['running_balance'] ?? 0;
            $new_balance = $running + $total;
            mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                 VALUES ($customer_id, '$datetime', 'Water Delivery - $bottles bottles of {$product['product_name']} @ Rs $rate', $total, 0, $new_balance, $delivery_id, 'delivery')");
            
            // Bottle tracking entry
            mysqli_query($conn, "INSERT INTO bottle_tracking (customer_id, tracking_date, bottles_delivered, bottles_returned, bottles_broken, pending_empties, notes, reference_id) 
                                 VALUES ($customer_id, '$datetime', $bottles, $empties_returned, 0, $new_empties, '$notes', $delivery_id)");
            
            // ========== STOCK MANAGEMENT: DEDUCT STOCK ==========
            $new_stock = $product['current_stock'] - $bottles;
            
            // Update product stock
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_out, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$datetime', 'OUT', 'delivery', $delivery_id, $bottles, $new_stock, 'Stock out: $bottles bottles of {$product['product_name']} delivered to customer: " . htmlspecialchars($cust['customer_name']) . "', '$datetime')");
            
            // Check for low stock alert
            if($new_stock <= $product['min_stock_level']) {
                $success = " <span class='text-warning'>⚠️ Low stock alert! Only $new_stock bottles remaining.</span>";
            }
            // ========== END STOCK MANAGEMENT ==========
            
            $success = "Delivery recorded successfully for " . htmlspecialchars($cust['customer_name']) . "! Total: Rs " . number_format($total, 2) . $success;
        }
    } else {
        $error = "Customer or Product not found!";
    }
}

// Get customer list for dropdown
$customers_list = mysqli_query($conn, "SELECT c.id, c.customer_name, c.mobile, c.block, c.area, c.salesman, r.route_name FROM customers c LEFT JOIN routes r ON c.route_id = r.id WHERE c.status='Active' ORDER BY c.customer_name");

// Get routes for walk-in form
$routes_list = mysqli_query($conn, "SELECT * FROM routes ORDER BY route_name");

// Get active products with stock
$products_list = mysqli_query($conn, "SELECT id, product_name, sale_price, current_stock FROM products WHERE status='Active' AND current_stock > 0 ORDER BY product_name");

// Get today's sales summary
$today_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) as total, COALESCE(SUM(bottles_delivered),0) as bottles FROM water_deliveries WHERE DATE(delivery_datetime)=CURDATE()"));

// Get current stock info for all products
$products_stock = mysqli_query($conn, "SELECT id, product_name, current_stock, min_stock_level, sale_price FROM products WHERE status='Active' ORDER BY product_name");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.delivery-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.delivery-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.delivery-form .form-label {
    font-weight: 500;
    font-size: 13px;
    color: #555;
    margin-bottom: 5px;
}
.delivery-form .form-control,
.delivery-form .form-select {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}
.delivery-form .form-control:focus,
.delivery-form .form-select:focus {
    border-color: #A04657;
    box-shadow: 0 0 0 0.2rem rgba(160,70,87,0.1);
}
.amount-display {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px 15px;
    text-align: center;
}
.amount-display label {
    font-size: 12px;
    color: #888;
    margin-bottom: 5px;
}
.amount-display .amount-value {
    font-size: 24px;
    font-weight: 700;
    color: #A04657;
}
.rate-editable {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 12px 15px;
    text-align: center;
    transition: all 0.2s;
}
.rate-editable:hover {
    border-color: #A04657;
    box-shadow: 0 0 0 2px rgba(160,70,87,0.1);
}
.rate-editable input {
    border: none;
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    color: #A04657;
    width: 100%;
    background: transparent;
}
.rate-editable input:focus {
    outline: none;
}
.delivery-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}
.delivery-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.summary-card {
    border-radius: 15px;
    padding: 15px;
    color: white;
    text-align: center;
}
.summary-card.today {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
}
.summary-card.stock {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.search-box {
    position: relative;
}
.search-box input {
    padding-left: 40px;
}
.search-box i {
    position: absolute;
    left: 15px;
    top: 12px;
    color: #999;
}
.product-info {
    background: #e8f4f8;
    border-radius: 10px;
    padding: 10px;
    margin-top: 10px;
    font-size: 13px;
}
.low-stock {
    color: #dc3545;
    font-weight: bold;
}
.selected-customer-info {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 8px 12px;
    border-radius: 8px;
    margin-top: 8px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-truck me-2" style="color: #A04657;"></i> Daily Water Delivery
        </h2>
        <div class="d-flex gap-2">
            <div class="summary-card stock">
                <small>Available Stock</small>
                <h4 class="mb-0">
                    <?php 
                    $total_stock = 0;
                    $stock_display = [];
                    mysqli_data_seek($products_stock, 0);
                    while($ps = mysqli_fetch_assoc($products_stock)){
                        $total_stock += $ps['current_stock'];
                        $stock_display[] = $ps['product_name'] . ': ' . number_format($ps['current_stock']);
                    }
                    echo number_format($total_stock); ?> Bottles
                </h4>
                <small><?php echo implode(' | ', array_slice($stock_display, 0, 2)); ?></small>
            </div>
            <div class="summary-card today">
                <small>Today's Sales</small>
                <h4 class="mb-0">Rs <?php echo number_format($today_total['total'], 2); ?></h4>
                <small><?php echo $today_total['bottles']; ?> bottles</small>
            </div>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <!-- Delivery Form Column -->
        <div class="col-lg-6">
            <div class="card delivery-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2"></i> Record New Delivery
                </div>
                <div class="card-body p-4">
                    <form method="POST" class="delivery-form" id="deliveryForm">
                        <!-- Walk-in Toggle -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="walkinToggle" onchange="toggleWalkin()">
                                <label class="form-check-label fw-semibold" for="walkinToggle">
                                    <i class="fas fa-walking me-1"></i> Walk-in Customer
                                </label>
                            </div>
                        </div>

                        <!-- Existing Customer Selection -->
                        <div class="mb-3" id="existingCustomerSection">
                            <label class="form-label"><i class="fas fa-user me-1"></i> Select Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customerId" class="form-select" onchange="updateCustomerInfo()">
                                <option value="">-- Select Customer --</option>
                                <?php 
                                mysqli_data_seek($customers_list, 0);
                                while($c = mysqli_fetch_assoc($customers_list)): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" 
                                            data-mobile="<?php echo $c['mobile']; ?>"
                                            data-route="<?php echo htmlspecialchars($c['route_name'] ?? ''); ?>"
                                            data-block="<?php echo htmlspecialchars($c['block'] ?? ''); ?>"
                                            data-area="<?php echo htmlspecialchars($c['area'] ?? ''); ?>"
                                            data-salesman="<?php echo htmlspecialchars($c['salesman'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($c['customer_name']); ?> - <?php echo $c['mobile']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="selectedCustomerInfo" class="selected-customer-info" style="display: none;">
                                <i class="fas fa-user-check me-1 text-success"></i>
                                Customer: <strong id="displayCustomerName"></strong>
                                <br><small id="displayCustomerMobile"></small>
                                <br><small><strong>Route:</strong> <span id="displayRoute"></span> | <strong>Block:</strong> <span id="displayBlock"></span> | <strong>Area:</strong> <span id="displayArea"></span> | <strong>Salesman:</strong> <span id="displaySalesman"></span></small>
                            </div>
                        </div>

                        <!-- Walk-in Customer Fields (hidden by default) -->
                        <div id="walkinSection" style="display: none;">
                            <div class="card mb-3" style="background: #fff8e1; border: 1px solid #ffe082; border-radius: 12px;">
                                <div class="card-body p-3">
                                    <h6 class="mb-3" style="color: #f57f17;"><i class="fas fa-walking me-2"></i> New Walk-in Customer Details</h6>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" name="walkin_name" id="walkinName" class="form-control" placeholder="Customer name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Mobile</label>
                                            <input type="text" name="walkin_mobile" id="walkinMobile" class="form-control" placeholder="Mobile number">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" style="font-size: 12px;">Address</label>
                                            <input type="text" name="walkin_address" id="walkinAddress" class="form-control" placeholder="Address">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Route</label>
                                            <select name="walkin_route" id="walkinRoute" class="form-select" onchange="prefillWalkinRoute()">
                                                <option value="">-- Select --</option>
                                                <?php
                                                mysqli_data_seek($routes_list, 0);
                                                while($rt = mysqli_fetch_assoc($routes_list)):
                                                ?>
                                                <option value="<?php echo $rt['id']; ?>" data-block="<?php echo htmlspecialchars($rt['block'] ?? ''); ?>" data-area="<?php echo htmlspecialchars($rt['area'] ?? ''); ?>" data-salesman="<?php echo htmlspecialchars($rt['salesman'] ?? ''); ?>"><?php echo htmlspecialchars($rt['route_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Block</label>
                                            <input type="text" name="walkin_block" id="walkinBlock" class="form-control" placeholder="Block">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Area</label>
                                            <input type="text" name="walkin_area" id="walkinArea" class="form-control" placeholder="Area">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size: 12px;">Salesman</label>
                                            <input type="text" name="walkin_salesman" id="walkinSalesman" class="form-control" placeholder="Salesman">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Selection -->
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-wine-bottle me-1"></i> Select Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="productId" class="form-select" required onchange="updateProductInfo()">
                                <option value="">-- Select Product --</option>
                                <?php 
                                mysqli_data_seek($products_list, 0);
                                while($p = mysqli_fetch_assoc($products_list)): ?>
                                    <option value="<?php echo $p['id']; ?>" 
                                            data-price="<?php echo $p['sale_price']; ?>" 
                                            data-stock="<?php echo $p['current_stock']; ?>"
                                            data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
                                        <?php echo htmlspecialchars($p['product_name']); ?> - Rs <?php echo number_format($p['sale_price'], 2); ?> (Stock: <?php echo number_format($p['current_stock']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="productInfo" class="product-info" style="display: none;">
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="productStockInfo"></span>
                            </div>
                        </div>

                        <!-- Delivery Details -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-wine-bottle me-1"></i> Bottles Delivered <span class="text-danger">*</span></label>
                                <input type="number" name="bottles_delivered" id="bottlesDelivered" class="form-control" placeholder="0" required min="1" onkeyup="calculateTotal(); validateStock()" onchange="calculateTotal(); validateStock()">
                                <small id="stockHint" class="text-muted"></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-undo-alt me-1"></i> Empty Bottles Returned</label>
                                <input type="number" name="empty_bottles_returned" id="emptyReturned" class="form-control" placeholder="0" value="0" onkeyup="calculateTotal()">
                            </div>
                        </div>

                        <!-- Rate and Amount Display - Editable Rate -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <div class="rate-editable">
                                    <label><i class="fas fa-tag me-1"></i> Bottle Rate (Rs) <span class="text-muted" style="font-size: 11px;">(Editable)</span></label>
                                    <input type="number" step="0.01" name="bottle_rate" id="bottleRate" class="form-control" value="0" onkeyup="calculateTotal()" onchange="calculateTotal()">
                                    <small class="text-muted" style="font-size: 10px;">You can change the rate</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="amount-display" style="background: #A04657; color: white;">
                                    <label style="color: rgba(255,255,255,0.8);"><i class="fas fa-calculator me-1"></i> Total Amount (Rs)</label>
                                    <div class="amount-value" id="totalDisplay" style="color: white;">0.00</div>
                                    <input type="hidden" name="total_amount" id="totalAmount" value="0">
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-3 mt-3">
                            <label class="form-label"><i class="fas fa-pen me-1"></i> Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any special notes about this delivery..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="add_delivery" class="btn btn-primary w-100 rounded-pill py-2 mt-2" id="submitBtn" disabled>
                            <i class="fas fa-save me-2"></i> Save Delivery
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// DOM Elements
let isWalkin = false;
const customerSelect = document.getElementById('customerId');
const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');
const displayCustomerName = document.getElementById('displayCustomerName');
const displayCustomerMobile = document.getElementById('displayCustomerMobile');
const productSelect = document.getElementById('productId');
const bottleRateInput = document.getElementById('bottleRate');
const bottlesInput = document.getElementById('bottlesDelivered');
const totalDisplaySpan = document.getElementById('totalDisplay');
const totalAmountHidden = document.getElementById('totalAmount');
const submitBtn = document.getElementById('submitBtn');
const stockHint = document.getElementById('stockHint');
const productInfo = document.getElementById('productInfo');
const productStockInfo = document.getElementById('productStockInfo');

let currentProductStock = 0;
let currentProductPrice = 0;

// Toggle between walk-in and existing customer
function toggleWalkin() {
    isWalkin = document.getElementById('walkinToggle').checked;
    document.getElementById('existingCustomerSection').style.display = isWalkin ? 'none' : 'block';
    document.getElementById('walkinSection').style.display = isWalkin ? 'block' : 'none';
    if (isWalkin) {
        customerSelect.value = 'new';
        customerSelect.removeAttribute('required');
        selectedCustomerInfo.style.display = 'none';
    } else {
        customerSelect.value = '';
        customerSelect.setAttribute('required', 'required');
    }
    enableSubmit();
}

// Prefill walk-in fields from selected route
function prefillWalkinRoute() {
    const sel = document.getElementById('walkinRoute');
    const opt = sel.options[sel.selectedIndex];
    if (sel.value) {
        document.getElementById('walkinBlock').value = opt.getAttribute('data-block') || '';
        document.getElementById('walkinArea').value = opt.getAttribute('data-area') || '';
        document.getElementById('walkinSalesman').value = opt.getAttribute('data-salesman') || '';
    }
}

// Update customer info when customer is selected
function updateCustomerInfo() {
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    if(customerSelect.value) {
        const customerName = selectedOption.getAttribute('data-name');
        const customerMobile = selectedOption.getAttribute('data-mobile');
        const customerRoute = selectedOption.getAttribute('data-route');
        const customerBlock = selectedOption.getAttribute('data-block');
        const customerArea = selectedOption.getAttribute('data-area');
        const customerSalesman = selectedOption.getAttribute('data-salesman');
        displayCustomerName.innerText = customerName;
        displayCustomerMobile.innerText = 'Mobile: ' + customerMobile;
        document.getElementById('displayRoute').innerText = customerRoute || '-';
        document.getElementById('displayBlock').innerText = customerBlock || '-';
        document.getElementById('displayArea').innerText = customerArea || '-';
        document.getElementById('displaySalesman').innerText = customerSalesman || '-';
        selectedCustomerInfo.style.display = 'block';
    } else {
        selectedCustomerInfo.style.display = 'none';
    }
    enableSubmit();
}

// Update product info when product is selected
function updateProductInfo() {
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    if(productSelect.value) {
        currentProductPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        currentProductStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        const productName = selectedOption.getAttribute('data-name');
        
        // Set bottle rate to product price
        bottleRateInput.value = currentProductPrice;
        
        // Show product info
        productInfo.style.display = 'block';
        let stockClass = currentProductStock <= 10 ? 'low-stock' : '';
        productStockInfo.innerHTML = `Product: <strong>${productName}</strong> | Sale Price: <strong>Rs ${currentProductPrice.toFixed(2)}</strong> | Available Stock: <span class="${stockClass}">${currentProductStock} bottles</span>`;
        
        if(currentProductStock <= 10) {
            productStockInfo.innerHTML += ' <i class="fas fa-exclamation-triangle text-warning"></i> Low stock!';
        }
        
        // Validate stock
        validateStock();
        calculateTotal();
    } else {
        productInfo.style.display = 'none';
        currentProductStock = 0;
        currentProductPrice = 0;
        bottleRateInput.value = 0;
        calculateTotal();
    }
    enableSubmit();
}

// Validate stock availability
function validateStock() {
    const bottles = parseInt(bottlesInput.value) || 0;
    if(currentProductStock === 0 && productSelect.value) {
        stockHint.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> Please select a product first';
        stockHint.style.color = '#dc3545';
        return false;
    }
    if(bottles > currentProductStock) {
        stockHint.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> Not enough stock! Available: ' + currentProductStock + ' bottles';
        stockHint.style.color = '#dc3545';
        return false;
    } else if(bottles > 0 && bottles <= currentProductStock) {
        stockHint.innerHTML = '<i class="fas fa-check-circle text-success"></i> Stock available: ' + currentProductStock + ' bottles';
        stockHint.style.color = '#28a745';
        return true;
    } else {
        stockHint.innerHTML = '';
        return false;
    }
}

function calculateTotal() {
    const bottles = parseInt(bottlesInput.value) || 0;
    const rate = parseFloat(bottleRateInput.value) || 0;
    const total = bottles * rate;
    totalDisplaySpan.innerText = total.toFixed(2);
    totalAmountHidden.value = total.toFixed(2);
    enableSubmit();
    validateStock();
}

function enableSubmit() {
    const hasCustomer = isWalkin ? document.getElementById('walkinName').value.trim() !== '' : customerSelect.value !== '';
    const hasProduct = productSelect.value !== '';
    const hasBottles = parseInt(bottlesInput.value) > 0;
    const hasRate = parseFloat(bottleRateInput.value) > 0;
    const stockOk = (parseInt(bottlesInput.value) || 0) <= currentProductStock;
    submitBtn.disabled = !(hasCustomer && hasProduct && hasBottles && hasRate && stockOk);
}

// Event Listeners
customerSelect.addEventListener('change', function() {
    updateCustomerInfo();
    enableSubmit();
});

bottlesInput.addEventListener('keyup', function() {
    calculateTotal();
    validateStock();
});
bottlesInput.addEventListener('change', function() {
    calculateTotal();
    validateStock();
});

bottleRateInput.addEventListener('keyup', calculateTotal);
bottleRateInput.addEventListener('change', calculateTotal);

// Walk-in fields trigger enableSubmit
document.getElementById('walkinName').addEventListener('keyup', enableSubmit);

// Initialize
calculateTotal();
</script>

<?php include '../includes/footer.php'; ?>