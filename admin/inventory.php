<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// First, let's check what columns exist in the products table
$product_columns = [];
$check_columns = $conn->query("SHOW COLUMNS FROM products");
while ($column = $check_columns->fetch_assoc()) {
    $product_columns[$column['Field']] = true;
}

// Determine the price column name (could be 'price' or 'base_price')
$price_column = 'base_price'; // Default
if (isset($product_columns['price'])) {
    $price_column = 'price';
} elseif (isset($product_columns['base_price'])) {
    $price_column = 'base_price';
}

// Check if inventory table exists, if not create it
$conn->query("
    CREATE TABLE IF NOT EXISTS inventory (
        inventory_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT DEFAULT 0,
        low_stock_threshold INT DEFAULT 5,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
        UNIQUE KEY unique_product (product_id)
    )
");

// Check if inventory_log table exists, if not create it
$conn->query("
    CREATE TABLE IF NOT EXISTS inventory_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity_change INT NOT NULL,
        updated_by INT,
        log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
        FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
    )
");

// Handle Update Stock
if (isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    
    // Check if inventory record exists
    $check = $conn->query("SELECT * FROM inventory WHERE product_id = $product_id");
    
    if ($check->num_rows > 0) {
        $query = "UPDATE inventory SET 
                  quantity = quantity + $quantity,
                  low_stock_threshold = $low_stock_threshold,
                  last_updated = NOW()
                  WHERE product_id = $product_id";
    } else {
        $query = "INSERT INTO inventory (product_id, quantity, low_stock_threshold) 
                  VALUES ($product_id, $quantity, $low_stock_threshold)";
    }
    
    if ($conn->query($query)) {
        // Log the stock update
        $conn->query("INSERT INTO inventory_log (product_id, quantity_change, updated_by) 
                      VALUES ($product_id, $quantity, $admin_id)");
        
        $_SESSION['success'] = "Inventory updated successfully";
    } else {
        $_SESSION['error'] = "Error updating inventory: " . $conn->error;
    }
    redirect('inventory.php');
}

// Handle Bulk Update
if (isset($_POST['bulk_update'])) {
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    foreach ($products as $index => $product_id) {
        $product_id = intval($product_id);
        $quantity = intval($quantities[$index]);
        
        // Check if inventory record exists
        $check = $conn->query("SELECT * FROM inventory WHERE product_id = $product_id");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE inventory SET quantity = quantity + $quantity WHERE product_id = $product_id");
        } else {
            $conn->query("INSERT INTO inventory (product_id, quantity) VALUES ($product_id, $quantity)");
        }
        
        $conn->query("INSERT INTO inventory_log (product_id, quantity_change, updated_by) VALUES ($product_id, $quantity, $admin_id)");
    }
    
    $_SESSION['success'] = "Bulk inventory update completed";
    redirect('inventory.php');
}

// Handle Restock Alert Settings
if (isset($_POST['update_threshold'])) {
    $product_id = intval($_POST['product_id']);
    $threshold = intval($_POST['threshold']);
    
    $conn->query("UPDATE inventory SET low_stock_threshold = $threshold WHERE product_id = $product_id");
    $_SESSION['success'] = "Low stock threshold updated";
    redirect('inventory.php');
}

// Get inventory with product details (using the correct price column)
$inventory_query = "
    SELECT i.*, p.product_name, p.$price_column as price, c.category_name,
           CASE 
               WHEN i.quantity <= i.low_stock_threshold THEN 'Low Stock'
               WHEN i.quantity <= i.low_stock_threshold * 2 THEN 'Medium Stock'
               ELSE 'In Stock'
           END as stock_status
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY 
        CASE 
            WHEN i.quantity <= i.low_stock_threshold THEN 1
            WHEN i.quantity <= i.low_stock_threshold * 2 THEN 2
            ELSE 3
        END,
        i.quantity ASC
";

$inventory = $conn->query($inventory_query);

// Get products without inventory records
$products_without_inventory = $conn->query("
    SELECT p.product_id, p.product_name 
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE i.inventory_id IS NULL
");

// Get inventory logs
$inventory_logs = $conn->query("
    SELECT l.*, p.product_name, u.full_name as updated_by_name
    FROM inventory_log l
    LEFT JOIN products p ON l.product_id = p.product_id
    LEFT JOIN users u ON l.updated_by = u.user_id
    ORDER BY l.log_date DESC
    LIMIT 20
");

// Get low stock count
$low_stock_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM inventory 
    WHERE quantity <= low_stock_threshold
");
$low_stock_count = $low_stock_result ? $low_stock_result->fetch_assoc()['count'] : 0;

// Get total stock value (using the correct price column)
$stock_value_query = "
    SELECT SUM(i.quantity * p.$price_column) as total
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.product_id
";
$stock_value_result = $conn->query($stock_value_query);
$stock_value = $stock_value_result ? $stock_value_result->fetch_assoc()['total'] : 0;

// Get all products for bulk update dropdown
$all_products = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name");

// Check if last_updated column exists in inventory table
$last_updated_exists = false;
$check_last_updated = $conn->query("SHOW COLUMNS FROM inventory LIKE 'last_updated'");
if ($check_last_updated && $check_last_updated->num_rows > 0) {
    $last_updated_exists = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Furniverse Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .inventory-container {
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        .stat-card .sub {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background: #d35400;
        }
        .btn-secondary {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-edit {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            margin-right: 5px;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        .btn-history {
            background: #2ecc71;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        .inventory-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .inventory-table th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: left;
        }
        .inventory-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        .inventory-table tr:hover {
            background: #f8f9fa;
        }
        .stock-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .stock-badge.low {
            background: #f8d7da;
            color: #721c24;
        }
        .stock-badge.medium {
            background: #fff3cd;
            color: #856404;
        }
        .stock-badge.high {
            background: #d4edda;
            color: #155724;
        }
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #e67e22;
            transition: width 0.3s;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 500px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #ecf0f1;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            color: #666;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: #e67e22;
            border-bottom-color: #e67e22;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .log-item {
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-date {
            color: #999;
            font-size: 12px;
        }
        .log-details {
            margin-top: 5px;
        }
        .log-change {
            font-weight: 600;
        }
        .log-change.positive {
            color: #28a745;
        }
        .log-change.negative {
            color: #dc3545;
        }
        .bulk-edit-row {
            display: grid;
            grid-template-columns: 1fr 100px 50px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .info-message {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <h2>Furniverse Admin</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-chair"></i> Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="materials.php"><i class="fas fa-cube"></i> Materials</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="inventory.php" class="active"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1>Inventory Management</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if (!$inventory): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Inventory tables have been created. You can now start managing your inventory.
                </div>
                <?php endif; ?>

                <div class="inventory-container">
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Products</h3>
                            <div class="value"><?php echo $inventory ? $inventory->num_rows : 0; ?></div>
                            <div class="sub">Items in inventory</div>
                        </div>
                        <div class="stat-card">
                            <h3>Low Stock Items</h3>
                            <div class="value" style="color: #e74c3c;"><?php echo $low_stock_count; ?></div>
                            <div class="sub">Need reordering</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Stock Value</h3>
                            <div class="value">₱<?php echo number_format($stock_value, 2); ?></div>
                            <div class="sub">Current inventory value</div>
                        </div>
                        <div class="stat-card">
                            <h3>Products Without Stock</h3>
                            <div class="value"><?php echo $products_without_inventory ? $products_without_inventory->num_rows : 0; ?></div>
                            <div class="sub">Need initial stock setup</div>
                        </div>
                    </div>

                    <?php if ($products_without_inventory && $products_without_inventory->num_rows > 0): ?>
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Notice:</strong> 
                        <?php echo $products_without_inventory->num_rows; ?> products don't have inventory records. 
                        <a href="#initialize" onclick="openInitModal()" style="color: #856404; text-decoration: underline;">Initialize now</a>
                    </div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tab active" onclick="showTab('inventory')">Current Stock</div>
                        <div class="tab" onclick="showTab('logs')">Inventory Logs</div>
                        <div class="tab" onclick="showTab('bulk')">Bulk Update</div>
                    </div>

                    <!-- Current Stock Tab -->
                    <div id="inventory-tab" class="tab-content active">
                        <div class="action-bar">
                            <h2>Current Stock Levels</h2>
                            <button class="btn-primary" onclick="openAddStockModal()">
                                <i class="fas fa-plus"></i> Update Stock
                            </button>
                        </div>

                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                    <th>Stock Level</th>
                                    <th>Threshold</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($inventory && $inventory->num_rows > 0): ?>
                                    <?php while($item = $inventory->fetch_assoc()): 
                                        $stock_percentage = ($item['quantity'] / max($item['low_stock_threshold'] * 3, 1)) * 100;
                                        if ($stock_percentage > 100) $stock_percentage = 100;
                                        $last_updated = isset($item['last_updated']) ? date('M d, Y h:i A', strtotime($item['last_updated'])) : 'Never';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                            <td><strong><?php echo $item['quantity'] ?? 0; ?></strong> units</td>
                                            <td>
                                                <span class="stock-badge <?php 
                                                    echo ($item['stock_status'] ?? '') == 'Low Stock' ? 'low' : 
                                                        (($item['stock_status'] ?? '') == 'Medium Stock' ? 'medium' : 'high'); 
                                                ?>">
                                                    <?php echo $item['stock_status'] ?? 'Unknown'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $stock_percentage; ?>%;"></div>
                                                </div>
                                            </td>
                                            <td><?php echo $item['low_stock_threshold'] ?? 5; ?> units</td>
                                            <td><?php echo $last_updated; ?></td>
                                            <td>
                                                <button class="btn-edit" onclick="openUpdateModal(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['product_name'] ?? '')); ?>', <?php echo $item['quantity'] ?? 0; ?>, <?php echo $item['low_stock_threshold'] ?? 5; ?>)">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                                <button class="btn-history" onclick="openThresholdModal(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['product_name'] ?? '')); ?>', <?php echo $item['low_stock_threshold'] ?? 5; ?>)">
                                                    <i class="fas fa-sliders-h"></i> Threshold
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 30px;">
                                            <i class="fas fa-boxes" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                            No inventory records found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Inventory Logs Tab -->
                    <div id="logs-tab" class="tab-content">
                        <h2>Recent Inventory Updates</h2>
                        <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <?php if ($inventory_logs && $inventory_logs->num_rows > 0): ?>
                                <?php while($log = $inventory_logs->fetch_assoc()): ?>
                                    <div class="log-item">
                                        <div class="log-date"><?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?></div>
                                        <div class="log-details">
                                            <strong><?php echo htmlspecialchars($log['product_name'] ?? 'Unknown Product'); ?></strong> - 
                                            <span class="log-change <?php echo ($log['quantity_change'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo ($log['quantity_change'] ?? 0) >= 0 ? '+' : ''; ?><?php echo $log['quantity_change'] ?? 0; ?> units
                                            </span>
                                            <span style="color: #666; font-size: 12px; margin-left: 10px;">
                                                by <?php echo htmlspecialchars($log['updated_by_name'] ?? 'System'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #999;">No inventory logs found</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bulk Update Tab -->
                    <div id="bulk-tab" class="tab-content">
                        <h2>Bulk Inventory Update</h2>
                        <form method="POST" action="" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <div id="bulk-rows">
                                <!-- Bulk rows will be added here dynamically -->
                            </div>
                            <button type="button" class="btn-secondary" onclick="addBulkRow()" style="margin: 10px 0;">
                                <i class="fas fa-plus"></i> Add Another Product
                            </button>
                            <div>
                                <button type="submit" name="bulk_update" class="btn-primary">
                                    <i class="fas fa-save"></i> Update All
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Stock</h2>
                <span class="close" onclick="closeUpdateModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="product_id" id="update_product_id">
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="update_product_name" readonly style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity to Add (+) / Remove (-)</label>
                    <input type="number" id="quantity" name="quantity" required step="1" placeholder="Enter quantity">
                    <small style="color: #666;">Use negative numbers to remove stock</small>
                </div>
                <div class="form-group">
                    <label for="low_stock_threshold">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" required step="1" min="1">
                </div>
                <div class="form-group">
                    <button type="submit" name="update_stock" class="btn-primary" style="width: 100%;">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Initialize Stock Modal -->
    <div id="initStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Initialize Stock</h2>
                <span class="close" onclick="closeInitModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="init_product">Select Product</label>
                    <select id="init_product" name="product_id" required>
                        <option value="">Choose a product...</option>
                        <?php 
                        if ($products_without_inventory) {
                            $products_without_inventory->data_seek(0);
                            while($product = $products_without_inventory->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="init_quantity">Initial Quantity</label>
                    <input type="number" id="init_quantity" name="quantity" required step="1" min="0">
                </div>
                <div class="form-group">
                    <label for="init_threshold">Low Stock Threshold</label>
                    <input type="number" id="init_threshold" name="low_stock_threshold" required step="1" min="1" value="5">
                </div>
                <div class="form-group">
                    <button type="submit" name="update_stock" class="btn-primary" style="width: 100%;">Initialize Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Threshold Modal -->
    <div id="thresholdModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Threshold</h2>
                <span class="close" onclick="closeThresholdModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="product_id" id="threshold_product_id">
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="threshold_product_name" readonly style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label for="threshold">Low Stock Threshold</label>
                    <input type="number" id="threshold" name="threshold" required step="1" min="1">
                </div>
                <div class="form-group">
                    <button type="submit" name="update_threshold" class="btn-primary" style="width: 100%;">Update Threshold</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let bulkRowCount = 0;
        let allProducts = <?php 
            $products = [];
            if ($all_products) {
                $all_products->data_seek(0);
                while($p = $all_products->fetch_assoc()) {
                    $products[] = $p;
                }
            }
            echo json_encode($products);
        ?>;

        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'inventory') {
                document.querySelector('.tab:nth-child(1)').classList.add('active');
                document.getElementById('inventory-tab').classList.add('active');
            } else if (tab === 'logs') {
                document.querySelector('.tab:nth-child(2)').classList.add('active');
                document.getElementById('logs-tab').classList.add('active');
            } else if (tab === 'bulk') {
                document.querySelector('.tab:nth-child(3)').classList.add('active');
                document.getElementById('bulk-tab').classList.add('active');
                if (bulkRowCount === 0) addBulkRow();
            }
        }

        function openAddStockModal() {
            document.getElementById('update_product_id').value = '';
            document.getElementById('update_product_name').value = '';
            document.getElementById('quantity').value = '';
            document.getElementById('low_stock_threshold').value = '5';
            document.getElementById('updateStockModal').style.display = 'block';
        }

        function closeUpdateModal() {
            document.getElementById('updateStockModal').style.display = 'none';
        }

        function openUpdateModal(productId, productName, currentQty, threshold) {
            document.getElementById('update_product_id').value = productId;
            document.getElementById('update_product_name').value = productName;
            document.getElementById('quantity').value = '';
            document.getElementById('low_stock_threshold').value = threshold;
            document.getElementById('updateStockModal').style.display = 'block';
        }

        function openInitModal() {
            document.getElementById('initStockModal').style.display = 'block';
        }

        function closeInitModal() {
            document.getElementById('initStockModal').style.display = 'none';
        }

        function openThresholdModal(productId, productName, currentThreshold) {
            document.getElementById('threshold_product_id').value = productId;
            document.getElementById('threshold_product_name').value = productName;
            document.getElementById('threshold').value = currentThreshold;
            document.getElementById('thresholdModal').style.display = 'block';
        }

        function closeThresholdModal() {
            document.getElementById('thresholdModal').style.display = 'none';
        }

        function addBulkRow() {
            bulkRowCount++;
            const div = document.createElement('div');
            div.className = 'bulk-edit-row';
            div.id = 'bulk-row-' + bulkRowCount;
            
            let selectHTML = '<select name="products[]" required style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;"><option value="">Select Product</option>';
            
            allProducts.forEach(function(product) {
                selectHTML += '<option value="' + product.product_id + '">' + product.product_name.replace(/'/g, "\\'") + '</option>';
            });
            
            selectHTML += '</select>';
            
            div.innerHTML = `
                ${selectHTML}
                <input type="number" name="quantities[]" placeholder="Qty" required style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                <button type="button" onclick="removeBulkRow(${bulkRowCount})" style="background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 5px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.getElementById('bulk-rows').appendChild(div);
        }

        function removeBulkRow(id) {
            document.getElementById('bulk-row-' + id).remove();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            var updateModal = document.getElementById('updateStockModal');
            var initModal = document.getElementById('initStockModal');
            var thresholdModal = document.getElementById('thresholdModal');
            
            if (event.target == updateModal) {
                updateModal.style.display = 'none';
            }
            if (event.target == initModal) {
                initModal.style.display = 'none';
            }
            if (event.target == thresholdModal) {
                thresholdModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>