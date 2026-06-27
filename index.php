<?php
require_once 'includes/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

/* ================================
   DASHBOARD DATA
================================ */

// Total Customers
$total_customers = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as total FROM customers WHERE status='Active'")
)['total'] ?? 0;

// Total Outstanding
$total_outstanding = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(outstanding_balance) as total FROM customers")
)['total'] ?? 0;

// Today Deliveries
$today_deliveries = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(bottles_delivered) as bottles FROM water_deliveries WHERE DATE(delivery_datetime)=CURDATE()")
)['bottles'] ?? 0;

// Today Sales Amount
$today_sales = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(total_amount) as total FROM water_deliveries WHERE DATE(delivery_datetime)=CURDATE()")
)['total'] ?? 0;

// Total Empty Bottles Out
$total_bottles_out = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(empty_bottles_balance) as empties FROM customers")
)['empties'] ?? 0;

// Monthly Sales
$monthly_sales = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(total_amount) as total FROM water_deliveries WHERE MONTH(delivery_datetime)=MONTH(CURDATE()) AND YEAR(delivery_datetime)=YEAR(CURDATE())")
)['total'] ?? 0;

// Total Expenses This Month
$monthly_expenses = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock_in WHERE MONTH(stock_date)=MONTH(CURDATE()) AND YEAR(stock_date)=YEAR(CURDATE())")
)['total'] ?? 0;

// Current Stock Level
$current_stock = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(current_stock) as total FROM products WHERE status='Active'")
)['total'] ?? 0;

// Low Stock Alert Count
$low_stock_count = mysqli_num_rows(
    mysqli_query($conn, "SELECT * FROM products WHERE current_stock <= min_stock_level AND status='Active'")
) ?? 0;

// Today Empty Bottle Returns
$today_returns = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(bottles_returned) as total FROM bottle_tracking WHERE DATE(tracking_date)=CURDATE()")
)['total'] ?? 0;

// Recent Deliveries
$recent_deliveries = mysqli_query($conn, "SELECT d.*, c.customer_name FROM water_deliveries d JOIN customers c ON d.customer_id = c.id ORDER BY d.delivery_datetime DESC LIMIT 5");

// Recent Payments
$recent_payments = mysqli_query($conn, "SELECT p.*, c.customer_name FROM customer_payments p JOIN customers c ON p.customer_id = c.id ORDER BY p.payment_datetime DESC LIMIT 5");

// Recent Production
$recent_production = mysqli_query($conn, "SELECT si.*, p.product_name FROM stock_in si JOIN products p ON si.product_id = p.id ORDER BY si.stock_date DESC LIMIT 5");

// Calculation Rate
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM water_deliveries WHERE MONTH(delivery_datetime)=MONTH(CURDATE())"))['total'] ?? 0;
$collection_rate = ($total_sales > 0) ? (($total_sales - $total_outstanding) / $total_sales * 100) : 0;
?>

<?php include 'includes/header.php'; ?>

<style>
/* Dashboard Modern Styles */
.dashboard-wrapper {
    padding: 25px 30px;
    min-height: calc(100vh - 115px);
    background: #f5f7fb;
}

/* Top Bar */
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.welcome-section h2 {
    font-size: 24px;
    font-weight: 700;
    color: #1e2a3a;
    margin: 0;
    font-family: 'Quicksand', sans-serif;
}

.welcome-section p {
    color: #6c7a89;
    margin: 5px 0 0;
    font-size: 14px;
}

.date-badge {
    background: white;
    padding: 10px 20px;
    border-radius: 40px;
    border: 1px solid #e9ecef;
    font-weight: 500;
    color: #2c3e50;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.date-badge i {
    color: #A04657;
    margin-right: 8px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
    border-color: #A04657;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #A04657;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: rgba(160,70,87,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.stat-icon i {
    font-size: 24px;
    color: #A04657;
}

.stat-title {
    font-size: 13px;
    color: #8e9eae;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e2a3a;
    line-height: 1.2;
    margin-bottom: 5px;
    font-family: 'Quicksand', sans-serif;
}

.stat-sub {
    font-size: 11px;
    color: #8e9eae;
}

.stat-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.stat-badge.positive {
    background: #e8f5e9;
    color: #4caf50;
}

.stat-badge.warning {
    background: #fff3e0;
    color: #ff9800;
}

.stat-badge.danger {
    background: #ffebee;
    color: #f44336;
}

/* Chart Cards */
.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    color: #1e2a3a;
    font-family: 'Quicksand', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #A04657;
}

.activity-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #f0f0f0;
    overflow: hidden;
    height: 100%;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
}

.activity-header h4 {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    color: #1e2a3a;
}

.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #f5f5f5;
    transition: background 0.2s;
}

.activity-list li:hover {
    background: #fafafa;
}

.activity-list li:last-child {
    border-bottom: none;
}

.activity-info {
    flex: 1;
}

.activity-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
    margin-bottom: 4px;
}

.activity-meta {
    font-size: 11px;
    color: #8e9eae;
}

.activity-amount {
    font-weight: 700;
    font-size: 14px;
    color: #A04657;
}

.activity-amount.income {
    color: #4caf50;
}

.activity-amount.expense {
    color: #f44336;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #b0bec5;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.quick-btn {
    flex: 1;
    min-width: 110px;
    background: white;
    border: 1px solid #e9ecef;
    padding: 14px 12px;
    border-radius: 16px;
    text-align: center;
    text-decoration: none;
    color: #2c3e50;
    font-weight: 500;
    transition: all 0.2s ease;
    font-size: 13px;
}

.quick-btn i {
    display: block;
    font-size: 22px;
    margin-bottom: 8px;
    color: #A04657;
}

.quick-btn:hover {
    background: #A04657;
    color: white;
    border-color: #A04657;
    transform: translateY(-3px);
}

.quick-btn:hover i {
    color: white;
}

/* Progress Bar */
.progress-container {
    margin-top: 10px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 5px;
    color: #666;
}

.progress-bar-custom {
    height: 6px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #A04657;
    border-radius: 10px;
    transition: width 0.5s ease;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .dashboard-wrapper {
        padding: 15px;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .welcome-section h2 {
        font-size: 20px;
    }
    
    .date-badge {
        width: 100%;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 22px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
    }
    
    .stat-icon i {
        font-size: 18px;
    }
    
    .quick-actions {
        gap: 10px;
    }
    
    .quick-btn {
        min-width: calc(33.33% - 10px);
        padding: 10px;
        font-size: 11px;
    }
    
    .quick-btn i {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-value {
        font-size: 24px;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card, .activity-card {
    animation: fadeInUp 0.4s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }
</style>

<div class="dashboard-wrapper">

    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-section">
            <h2>Welcome back, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?>!</h2>
            <p>Here's what's happening with your business today.</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('l, d M Y'); ?>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        
        <!-- Total Customers -->
        <div class="stat-card" onclick="window.location.href='pages/customers.php'">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-title">Total Customers</div>
            <div class="stat-value"><?php echo number_format($total_customers); ?></div>
            <div class="stat-sub">Active water customers</div>
        </div>

        <!-- Total Outstanding -->
        <div class="stat-card" onclick="window.location.href='pages/reports.php?type=outstanding'">
            <div class="stat-icon">
                <i class="fas fa-rupee-sign"></i>
            </div>
            <div class="stat-title">Total Outstanding</div>
            <div class="stat-value">Rs <?php echo number_format($total_outstanding, 2); ?></div>
            <div class="stat-sub">Pending payments</div>
            <div class="stat-badge <?php echo $collection_rate > 70 ? 'positive' : 'warning'; ?>">
                <?php echo number_format($collection_rate, 0); ?>% collected
            </div>
        </div>

        <!-- Today's Sales -->
        <div class="stat-card" onclick="window.location.href='pages/deliveries.php'">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-title">Today's Sales</div>
            <div class="stat-value">Rs <?php echo number_format($today_sales, 2); ?></div>
            <div class="stat-sub"><?php echo number_format($today_deliveries); ?> bottles delivered</div>
        </div>

        <!-- Current Stock -->
        <div class="stat-card" onclick="window.location.href='pages/stock.php'">
            <div class="stat-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-title">Current Stock</div>
            <div class="stat-value"><?php echo number_format($current_stock); ?></div>
            <div class="stat-sub">Bottles in inventory</div>
            <?php if($low_stock_count > 0): ?>
                <div class="stat-badge danger">
                    ⚠️ <?php echo $low_stock_count; ?> low stock
                </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Sales -->
        <div class="stat-card" onclick="window.location.href='pages/reports.php?type=monthly'">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-title">Monthly Sales</div>
            <div class="stat-value">Rs <?php echo number_format($monthly_sales, 2); ?></div>
            <div class="stat-sub"><?php echo date('F Y'); ?></div>
            <div class="progress-container">
                <div class="progress-label">
                    <span>Monthly target</span>
                    <span>Rs <?php echo number_format($monthly_sales, 0); ?></span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: <?php echo min(100, ($monthly_sales / 50000) * 100); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Empty Bottles -->
        <div class="stat-card" onclick="window.location.href='pages/bottle_tracking.php'">
            <div class="stat-icon">
                <i class="fas fa-wine-bottle"></i>
            </div>
            <div class="stat-title">Empty Bottles Out</div>
            <div class="stat-value"><?php echo number_format($total_bottles_out); ?></div>
            <div class="stat-sub"><?php echo number_format($today_returns); ?> returned today</div>
        </div>
    </div>

    <!-- RECENT ACTIVITIES ROW -->
    <div class="row g-4">
        
        <!-- Recent Deliveries -->
        <div class="col-md-6 col-xl-4">
            <div class="activity-card">
                <div class="activity-header">
                    <h4><i class="fas fa-truck"></i> Recent Deliveries</h4>
                    <a href="pages/deliveries.php" style="color: #A04657; font-size: 12px; text-decoration: none;">View All →</a>
                </div>
                <ul class="activity-list">
                    <?php if (mysqli_num_rows($recent_deliveries) > 0): ?>
                        <?php while($del = mysqli_fetch_assoc($recent_deliveries)): ?>
                        <li>
                            <div class="activity-info">
                                <div class="activity-name"><?php echo htmlspecialchars($del['customer_name']); ?></div>
                                <div class="activity-meta"><?php echo date('d M, h:i A', strtotime($del['delivery_datetime'])); ?></div>
                            </div>
                            <div class="activity-amount expense"><?php echo $del['bottles_delivered']; ?> bottles</div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-truck"></i>
                            <p>No deliveries today</p>
                        </div>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-md-6 col-xl-4">
            <div class="activity-card">
                <div class="activity-header">
                    <h4><i class="fas fa-money-bill-wave"></i> Recent Payments</h4>
                    <a href="pages/payments.php" style="color: #A04657; font-size: 12px; text-decoration: none;">View All →</a>
                </div>
                <ul class="activity-list">
                    <?php if (mysqli_num_rows($recent_payments) > 0): ?>
                        <?php while($pay = mysqli_fetch_assoc($recent_payments)): ?>
                        <li>
                            <div class="activity-info">
                                <div class="activity-name"><?php echo htmlspecialchars($pay['customer_name']); ?></div>
                                <div class="activity-meta"><?php echo date('d M, h:i A', strtotime($pay['payment_datetime'])); ?></div>
                            </div>
                            <div class="activity-amount income">Rs <?php echo number_format($pay['payment_amount'], 2); ?></div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No recent payments</p>
                        </div>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Recent Production -->
        <div class="col-md-12 col-xl-4">
            <div class="activity-card">
                <div class="activity-header">
                    <h4><i class="fas fa-industry"></i> Recent Production</h4>
                    <a href="pages/stock.php" style="color: #A04657; font-size: 12px; text-decoration: none;">View All →</a>
                </div>
                <ul class="activity-list">
                    <?php if (mysqli_num_rows($recent_production) > 0): ?>
                        <?php while($prod = mysqli_fetch_assoc($recent_production)): ?>
                        <li>
                            <div class="activity-info">
                                <div class="activity-name"><?php echo htmlspecialchars($prod['product_name']); ?></div>
                                <div class="activity-meta"><?php echo date('d M, h:i A', strtotime($prod['stock_date'])); ?></div>
                            </div>
                            <div class="activity-amount">+<?php echo number_format($prod['quantity']); ?> bottles</div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-boxes"></i>
                            <p>No production recorded</p>
                        </div>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="pages/customers.php" class="quick-btn">
            <i class="fas fa-user-plus"></i>
            Add Customer
        </a>
        <a href="pages/deliveries.php" class="quick-btn">
            <i class="fas fa-truck"></i>
            New Delivery
        </a>
        <a href="pages/empty_bottle_return.php" class="quick-btn">
            <i class="fas fa-undo-alt"></i>
            Bottle Return
        </a>
        <a href="pages/payments.php" class="quick-btn">
            <i class="fas fa-receipt"></i>
            Receive Payment
        </a>
        <a href="pages/stock.php" class="quick-btn">
            <i class="fas fa-boxes"></i>
            Add Production
        </a>
        <a href="pages/reports.php" class="quick-btn">
            <i class="fas fa-chart-simple"></i>
            Reports
        </a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>