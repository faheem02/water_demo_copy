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
    $customer_id = intval($_POST['customer_id']);
    $product_id = intval($_POST['product_id']);
    $bottles = intval($_POST['bottles_delivered']);
    $empties_returned = intval($_POST['empty_bottles_returned']);
    $rate = floatval($_POST['bottle_rate']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $datetime = date('Y-m-d H:i:s');

    // Get customer details
    $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT bottle_rate, outstanding_balance, empty_bottles_balance, customer_name FROM customers WHERE id=$customer_id"));
    
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
            mysqli_query($conn, "INSERT INTO bottle_tracking (customer_id, tracking_date, bottles_delivered, bottles_returned, pending_empties, notes, reference_id) 
                                 VALUES ($customer_id, '$datetime', $bottles, $empties_returned, $new_empties, '$notes', $delivery_id)");
            
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
$customers_list = mysqli_query($conn, "SELECT id, customer_name, mobile FROM customers WHERE status='Active' ORDER BY customer_name");

// Get active products with stock
$products_list = mysqli_query($conn, "SELECT id, product_name, sale_price, current_stock FROM products WHERE status='Active' AND current_stock > 0 ORDER BY product_name");

// Get recent deliveries with product info
$deliveries = mysqli_query($conn, "SELECT d.*, c.customer_name, c.mobile, p.product_name, p.sale_price 
                                   FROM water_deliveries d 
                                   JOIN customers c ON d.customer_id = c.id 
                                   LEFT JOIN products p ON d.product_id = p.id 
                                   ORDER BY d.delivery_datetime DESC LIMIT 50");

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

    <div class="row g-4">
        <!-- Delivery Form Column -->
        <div class="col-lg-5">
            <div class="card delivery-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2"></i> Record New Delivery
                </div>
                <div class="card-body p-4">
                    <form method="POST" class="delivery-form" id="deliveryForm">
                        <!-- Customer Selection with Search -->
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-user me-1"></i> Select Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customerId" class="form-select" required onchange="updateCustomerInfo()">
                                <option value="">-- Select Customer --</option>
                                <?php 
                                mysqli_data_seek($customers_list, 0);
                                while($c = mysqli_fetch_assoc($customers_list)): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" 
                                            data-mobile="<?php echo $c['mobile']; ?>">
                                        <?php echo htmlspecialchars($c['customer_name']); ?> - <?php echo $c['mobile']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="selectedCustomerInfo" class="selected-customer-info" style="display: none;">
                                <i class="fas fa-user-check me-1 text-success"></i>
                                Customer: <strong id="displayCustomerName"></strong>
                                <br><small id="displayCustomerMobile"></small>
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

        <!-- Recent Deliveries Column -->
        <div class="col-lg-7">
            <div class="card delivery-card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i> Recent Deliveries
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table delivery-table mb-0" id="deliveriesTable">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th class="text-center">Bottles</th>
                                    <th class="text-center">Returned</th>
                                    <th class="text-end">Rate (Rs)</th>
                                    <th class="text-end">Total (Rs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($deliveries) > 0): ?>
                                    <?php while($d = mysqli_fetch_assoc($deliveries)): ?>
                                        <tr>
                                            <td><i class="far fa-calendar-alt me-1 text-muted"></i> <?php echo date('d/m/y h:i A', strtotime($d['delivery_datetime'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($d['customer_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($d['product_name'] ?? 'N/A'); ?></td>
                                            <td class="text-center"><span class="badge bg-primary rounded-pill"><?php echo $d['bottles_delivered']; ?></span></td>
                                            <td class="text-center"><?php echo $d['empty_bottles_returned']; ?></td>
                                            <td class="text-end">Rs <?php echo number_format($d['bottle_rate'], 2); ?></td>
                                            <td class="text-end fw-bold text-success">Rs <?php echo number_format($d['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-truck fa-3x mb-3 d-block opacity-25"></i>
                                            No deliveries recorded yet.
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
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// DOM Elements
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

// Update customer info when customer is selected
function updateCustomerInfo() {
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    if(customerSelect.value) {
        const customerName = selectedOption.getAttribute('data-name');
        const customerMobile = selectedOption.getAttribute('data-mobile');
        displayCustomerName.innerText = customerName;
        displayCustomerMobile.innerText = 'Mobile: ' + customerMobile;
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
    const hasCustomer = customerSelect.value !== '';
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

// Initialize
calculateTotal();
</script>

<?php include '../includes/footer.php'; ?>