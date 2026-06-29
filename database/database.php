<?php
/**
 * Database Schema for Water Supply Management System
 * 
 * Run this file once to create all required tables.
 * Execute: http://localhost/water_demo_copy/database/database.php
 */

// Include database connection
require_once __DIR__ . '/../includes/db.php';

// SQL to create all tables
$sql = "

-- -----------------------------------------------------------
-- 1. Users table (Admin Login)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 2. Routes table (Delivery Areas)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `routes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `route_name` VARCHAR(200) NOT NULL,
    `block` VARCHAR(200) DEFAULT NULL,
    `area` VARCHAR(200) DEFAULT NULL,
    `salesman` VARCHAR(200) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 3. Customers table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_name` VARCHAR(200) NOT NULL,
    `mobile` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `route_id` INT(11) DEFAULT NULL,
    `bottle_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `security_deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `empty_bottles_balance` INT(11) NOT NULL DEFAULT 0,
    `outstanding_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `block` VARCHAR(200) DEFAULT NULL,
    `area` VARCHAR(200) DEFAULT NULL,
    `salesman` VARCHAR(200) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `route_id` (`route_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 4. Customer Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `debit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reference_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 5. Customer Payments table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_type` VARCHAR(50) DEFAULT 'Cash',
    `notes` TEXT DEFAULT NULL,
    `payment_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 6. Water Deliveries table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `water_deliveries` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `product_id` INT(11) DEFAULT NULL,
    `bottles_delivered` INT(11) NOT NULL DEFAULT 0,
    `empty_bottles_returned` INT(11) NOT NULL DEFAULT 0,
    `bottle_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `delivery_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `product_id` (`product_id`),
    KEY `delivery_datetime` (`delivery_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 7. Bottle Tracking table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bottle_tracking` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `tracking_date` DATE NOT NULL,
    `bottles_delivered` INT(11) NOT NULL DEFAULT 0,
    `bottles_returned` INT(11) NOT NULL DEFAULT 0,
    `bottles_broken` INT(11) NOT NULL DEFAULT 0,
    `pending_empties` INT(11) NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `tracking_date` (`tracking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 8. Products table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_name` VARCHAR(200) NOT NULL,
    `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_stock` INT(11) NOT NULL DEFAULT 0,
    `min_stock_level` INT(11) NOT NULL DEFAULT 10,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 9. Stock In (Production) table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_in` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 0,
    `stock_date` DATE NOT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 10. Stock Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(20) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `quantity_in` INT(11) NOT NULL DEFAULT 0,
    `quantity_out` INT(11) NOT NULL DEFAULT 0,
    `running_stock` INT(11) NOT NULL DEFAULT 0,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 11. Expense Categories table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 12. Expenses table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `expense_date` DATE NOT NULL,
    `expense_category` INT(11) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `receipt_no` VARCHAR(100) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `expense_category` (`expense_category`),
    KEY `expense_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 13. Cashbook table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cashbook` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(20) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `transaction_date` (`transaction_date`),
    KEY `transaction_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 14. Suppliers table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_name` VARCHAR(200) NOT NULL,
    `contact_person` VARCHAR(200) DEFAULT NULL,
    `mobile` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `ntn_no` VARCHAR(100) DEFAULT NULL,
    `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 15. Supplier Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `debit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reference_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 16. Supplier Payments table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_id` INT(11) NOT NULL,
    `purchase_id` INT(11) NOT NULL,
    `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_type` VARCHAR(50) DEFAULT 'Cash',
    `cheque_no` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `payment_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`),
    KEY `purchase_id` (`purchase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 17. Raw Materials table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_materials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_name` VARCHAR(200) NOT NULL,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'Pieces',
    `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `opening_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_stock_level` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 18. Raw Material Purchases table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_purchases` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_date` DATETIME NOT NULL,
    `invoice_no` VARCHAR(100) DEFAULT NULL,
    `supplier_id` INT(11) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Credit',
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 19. Raw Material Purchase Items table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_purchase_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_id` INT(11) NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `purchase_id` (`purchase_id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 20. Raw Material Issuance table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_issuance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `issuance_date` DATETIME NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `purpose` VARCHAR(200) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 21. Raw Material Stock Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_stock_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(50) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `quantity_in` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity_out` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Execute multi-query
if (mysqli_multi_query($conn, $sql)) {
    echo "<div style='font-family: Arial; padding: 20px; max-width: 800px; margin: 50px auto; background: #f8f9fa; border-radius: 10px;'>";
    echo "<h2 style='color: #28a745;'>✓ Database Setup Complete</h2>";
    echo "<p>All 21 tables have been created successfully.</p>";
    echo "<ul>";
    
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));
    
    // Migrate routes table if it still has old description column
    $routes_cols = mysqli_query($conn, "SHOW COLUMNS FROM routes LIKE 'description'");
    if (mysqli_num_rows($routes_cols) > 0) {
        mysqli_query($conn, "ALTER TABLE routes DROP COLUMN description");
        mysqli_query($conn, "ALTER TABLE routes ADD COLUMN block VARCHAR(200) DEFAULT NULL AFTER route_name");
        mysqli_query($conn, "ALTER TABLE routes ADD COLUMN area VARCHAR(200) DEFAULT NULL AFTER block");
        mysqli_query($conn, "ALTER TABLE routes ADD COLUMN salesman VARCHAR(200) DEFAULT NULL AFTER area");
        echo "<li>✓ Routes table migrated (added block, area, salesman)</li>";
    } else {
        // Ensure columns exist even if table was created fresh
        $check_block = mysqli_query($conn, "SHOW COLUMNS FROM routes LIKE 'block'");
        if (mysqli_num_rows($check_block) == 0) {
            mysqli_query($conn, "ALTER TABLE routes ADD COLUMN block VARCHAR(200) DEFAULT NULL AFTER route_name");
            mysqli_query($conn, "ALTER TABLE routes ADD COLUMN area VARCHAR(200) DEFAULT NULL AFTER block");
            mysqli_query($conn, "ALTER TABLE routes ADD COLUMN salesman VARCHAR(200) DEFAULT NULL AFTER area");
            echo "<li>✓ Routes table updated (added block, area, salesman)</li>";
        }
    }
    
    // Migrate customers table to add block/area/salesman columns
    $check_cust_block = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'block'");
    if (mysqli_num_rows($check_cust_block) == 0) {
        mysqli_query($conn, "ALTER TABLE customers ADD COLUMN block VARCHAR(200) DEFAULT NULL AFTER outstanding_balance");
        mysqli_query($conn, "ALTER TABLE customers ADD COLUMN area VARCHAR(200) DEFAULT NULL AFTER block");
        mysqli_query($conn, "ALTER TABLE customers ADD COLUMN salesman VARCHAR(200) DEFAULT NULL AFTER area");
        echo "<li>✓ Customers table updated (added block, area, salesman)</li>";
    }
    
    // Migrate bottle_tracking table to add bottles_broken column
    $check_broken = mysqli_query($conn, "SHOW COLUMNS FROM bottle_tracking LIKE 'bottles_broken'");
    if ($check_broken && mysqli_num_rows($check_broken) == 0) {
        mysqli_query($conn, "ALTER TABLE bottle_tracking ADD COLUMN bottles_broken INT(11) NOT NULL DEFAULT 0 AFTER bottles_returned");
        echo "<li>✓ Bottle tracking table updated (added bottles_broken)</li>";
    }

    // Insert default admin user
    $check_admin = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
    $admin_row = mysqli_fetch_assoc($check_admin);
    
    if ($admin_row['cnt'] == 0) {
        mysqli_query($conn, "INSERT INTO users (username, password, full_name) VALUES ('admin', 'admin123', 'Administrator')");
        echo "<li>✓ Default admin user created (username: <b>admin</b>, password: <b>admin123</b>)</li>";
    }
    
    // Insert default expense categories
    $check_cats = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expense_categories");
    $cats_row = mysqli_fetch_assoc($check_cats);
    
    if ($cats_row['cnt'] == 0) {
        $default_cats = [
            "Electricity Bill", "Water Bill", "Employee Salary", "Transportation",
            "Maintenance", "Office Supplies", "Marketing", "Other"
        ];
        foreach ($default_cats as $cat) {
            mysqli_query($conn, "INSERT INTO expense_categories (category_name) VALUES ('$cat')");
        }
        echo "<li>✓ Default expense categories created</li>";
    }
    
    echo "<li>✓ You can now <a href='../login.php' style='color: #A04657;'>login to the system</a></li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='font-family: Arial; padding: 20px; max-width: 800px; margin: 50px auto; background: #f8d7da; border-radius: 10px; color: #721c24;'>";
    echo "<h2>✗ Database Setup Failed</h2>";
    echo "<p>" . mysqli_error($conn) . "</p>";
    echo "</div>";
}
