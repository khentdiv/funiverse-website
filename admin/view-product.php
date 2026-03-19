<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product ID";
    redirect('products.php');
}

// Fetch complete product details
$product = $conn->query("
    SELECT p.*, c.category_name, c.category_id
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_id = $product_id
")->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found";
    redirect('products.php');
}

// Fetch inventory data
$inventory = $conn->query("SELECT * FROM inventory WHERE product_id = $product_id")->fetch_assoc();
if (!$inventory) {
    $inventory = ['quantity' => 0, 'low_stock_threshold' => 5, 'last_updated' => 'Never'];
}

// Fetch material data if available
$material = null;
$check_material_column = $conn->query("SHOW COLUMNS FROM products LIKE 'material_id'");
if ($check_material_column && $check_material_column->num_rows > 0 && isset($product['material_id']) && $product['material_id']) {
    $material = $conn->query("SELECT * FROM materials WHERE material_id = " . $product['material_id'])->fetch_assoc();
}

// Fetch order statistics for this product
$order_stats = $conn->query("
    SELECT 
        COUNT(DISTINCT oi.order_id) as total_orders,
        IFNULL(SUM(oi.quantity), 0) as total_sold,
        IFNULL(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM order_items oi
    WHERE oi.product_id = $product_id
")->fetch_assoc();

if (!$order_stats) {
    $order_stats = ['total_orders' => 0, 'total_sold' => 0, 'total_revenue' => 0];
}

// Check which date column exists in orders table
$date_column = 'created_at'; // Default column
$check_order_date = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_date'");
if ($check_order_date && $check_order_date->num_rows > 0) {
    $date_column = 'order_date';
} else {
    $check_created_at = $conn->query("SHOW COLUMNS FROM orders LIKE 'created_at'");
    if ($check_created_at && $check_created_at->num_rows > 0) {
        $date_column = 'created_at';
    }
}

// Fetch recent orders containing this product
$recent_orders = $conn->query("
    SELECT 
        oi.*, 
        o.$date_column as order_date, 
        o.order_status, 
        o.total_amount as order_total,
        COALESCE(u.full_name, 'Guest') as customer_name
    FROM order_items oi
    LEFT JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE oi.product_id = $product_id
    ORDER BY o.$date_column DESC
    LIMIT 10
");

// If the query fails, create an empty result
if (!$recent_orders) {
    $recent_orders = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product - <?php echo htmlspecialchars($product['product_name']); ?> - Furniverse Admin</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0b17;
            color: #e2ddf2;
            line-height: 1.5;
        }

        h1, h2, h3, h4, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: rgba(18, 14, 29, 0.95);
            backdrop-filter: blur(16px);
            border-right: 1px solid rgba(207, 176, 135, 0.25);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
        }

        .sidebar-header h2 {
            font-size: 2rem;
            background: linear-gradient(135deg, #ece3f0, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            list-style: none;
            padding: 2rem 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1.5rem;
            color: #d6cee8;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a i {
            width: 20px;
            color: #cfb087;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(207, 176, 135, 0.1);
            border-left-color: #cfb087;
            color: #f0e6d2;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            background: #0f0b17;
        }

        .admin-header {
            background: rgba(18, 14, 29, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(207, 176, 135, 0.25);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-header h1 {
            font-size: 1.8rem;
            color: #f0e6d2;
        }

        .admin-user {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            border: 1px solid #3d3452;
            color: #cfb087;
        }

        .admin-content {
            padding: 2rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 500;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .alert-error {
            background: #2d1a24;
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        .view-product-container {
            width: 100%;
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #332d44;
        }

        .product-header h2 {
            font-size: 2rem;
            color: #f0e6d2;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-primary {
            padding: 0.8rem 2rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .btn-secondary {
            padding: 0.8rem 2rem;
            background: transparent;
            border: 1.5px solid #3d3452;
            border-radius: 40px;
            color: #b2a6ca;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
        }

        .btn-secondary:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            background: transparent;
            border: 1px solid #3d3452;
            border-radius: 40px;
            color: #b2a6ca;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-icon:hover {
            border-color: #cfb087;
            color: #cfb087;
            transform: scale(1.1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #cfb087, #8b6f4c);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #6b5b85;
        }

        .stat-card h3 {
            color: #b2a6ca;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .stat-card .sub {
            color: #b2a6ca;
            font-size: 0.85rem;
        }

        .stat-card i {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 2rem;
            color: #cfb087;
            opacity: 0.3;
        }

        /* Product Detail Grid */
        .product-detail-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .product-image-section {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
        }

        .product-image {
            width: 100%;
            height: 350px;
            background: #161224;
            border-radius: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-image:hover img {
            transform: scale(1.05);
        }

        .product-image i {
            font-size: 5rem;
            color: #4a3f60;
        }

        .stock-status {
            text-align: center;
        }

        .badge {
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge.success {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .badge.warning {
            background: #2d2a1a;
            color: #ffd966;
            border: 1px solid #b88a4a;
        }

        .badge.secondary {
            background: #2d2640;
            color: #b3a4cb;
            border: 1px solid #5a4b7a;
        }

        .stock-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .stock-high { background: #4a9b6e; box-shadow: 0 0 10px #4a9b6e; }
        .stock-medium { background: #ffd966; box-shadow: 0 0 10px #ffd966; }
        .stock-low { background: #b84a6e; box-shadow: 0 0 10px #b84a6e; }

        .product-info-section {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
        }

        .product-title {
            font-size: 2.2rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
        }

        .product-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .product-meta span {
            background: #161224;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            color: #b2a6ca;
            font-size: 0.9rem;
            border: 1px solid #332d44;
        }

        .product-meta i {
            color: #cfb087;
            margin-right: 0.5rem;
        }

        .product-price {
            font-size: 3rem;
            color: #cfb087;
            font-weight: 700;
            margin-bottom: 2rem;
            background: #161224;
            padding: 1.5rem;
            border-radius: 42px;
            display: inline-block;
            border: 1px solid #332d44;
        }

        .product-description {
            background: #161224;
            padding: 1.5rem;
            border-radius: 32px;
            color: #d6cee8;
            line-height: 1.8;
            margin-bottom: 2rem;
            border: 1px solid #332d44;
        }

        .section-title {
            font-size: 1.5rem;
            color: #f0e6d2;
            margin: 2.5rem 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #332d44;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            color: #cfb087;
        }

        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            background: #161224;
            border-radius: 32px;
            overflow: hidden;
            border: 1px solid #332d44;
        }

        .info-table tr {
            border-bottom: 1px solid #332d44;
        }

        .info-table tr:last-child {
            border-bottom: none;
        }

        .info-table td {
            padding: 1.2rem 1.5rem;
        }

        .info-table td:first-child {
            font-weight: 600;
            color: #b2a6ca;
            width: 200px;
            background: #1e192c;
        }

        .info-table td:last-child {
            color: #f0e6d2;
        }

        .info-table i {
            color: #cfb087;
            margin-right: 0.8rem;
        }

        /* Recent Orders Table */
        .recent-orders-table {
            width: 100%;
            border-collapse: collapse;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 32px;
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .recent-orders-table th {
            background: #161224;
            padding: 1.2rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #b2a6ca;
            border-bottom: 1px solid #332d44;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .recent-orders-table td {
            padding: 1.2rem 1.5rem;
            color: #d6cee8;
            border-bottom: 1px solid #332d44;
        }

        .recent-orders-table tr:last-child td {
            border-bottom: none;
        }

        .recent-orders-table tr:hover td {
            background: #161224;
        }

        .recent-orders-table strong {
            color: #f0e6d2;
        }

        .badge.processing {
            background: #1a2d3a;
            color: #8bb9ff;
            border: 1px solid #3a6ea5;
        }

        .badge.completed {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .badge.cancelled {
            background: #2d1a24;
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        .badge.pending {
            background: #2d2a1a;
            color: #ffd966;
            border: 1px solid #b88a4a;
        }

        .no-data {
            text-align: center;
            padding: 4rem;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 60px;
        }

        .no-data i {
            font-size: 4rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }

        .no-data h3 {
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .no-data p {
            color: #b2a6ca;
        }

        .text-muted {
            color: #b2a6ca;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 0;
                display: none;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .product-detail-grid,
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <h2>Furniverse</h2>
            </div>
            <ul class="sidebar-menu">
                         <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-chair"></i> Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-eye" style="margin-right: 10px; color: #cfb087;"></i>Product Details</h1>
                <div class="admin-user">
                    <i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="view-product-container">
                    <!-- Header with actions -->
                    <div class="product-header">
                        <h2><i class="fas fa-box" style="margin-right: 10px; color: #cfb087;"></i><?php echo htmlspecialchars($product['product_name']); ?></h2>
                        <div class="action-buttons">
                            <a href="edit-product.php?id=<?php echo $product_id; ?>" class="btn-primary">
                                <i class="fas fa-edit"></i> Edit Product
                            </a>
                            <a href="products.php" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Products
                            </a>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-shopping-bag"></i>
                            <h3><i class="fas fa-shopping-bag" style="margin-right: 8px;"></i>Total Orders</h3>
                            <div class="value"><?php echo number_format($order_stats['total_orders']); ?></div>
                            <div class="sub">orders containing this product</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-chart-line"></i>
                            <h3><i class="fas fa-chart-line" style="margin-right: 8px;"></i>Units Sold</h3>
                            <div class="value"><?php echo number_format($order_stats['total_sold']); ?></div>
                            <div class="sub">total quantity sold</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-dollar-sign"></i>
                            <h3><i class="fas fa-dollar-sign" style="margin-right: 8px;"></i>Total Revenue</h3>
                            <div class="value">₱<?php echo number_format($order_stats['total_revenue'], 2); ?></div>
                            <div class="sub">from this product</div>
                        </div>
                    </div>

                    <!-- Main Product Details -->
                    <div class="product-detail-grid">
                        <!-- Image Section -->
                        <div class="product-image-section">
                            <div class="product-image">
                                <?php if(!empty($product['image']) && file_exists("../images/" . $product['image'])): ?>
                                    <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-chair"></i>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Stock Status -->
                            <div class="stock-status">
                                <?php 
                                $stock_status = 'high';
                                $stock_text = 'In Stock';
                                $stock_class = 'success';
                                
                                if ($inventory['quantity'] <= $inventory['low_stock_threshold']) {
                                    $stock_status = 'low';
                                    $stock_text = 'Low Stock';
                                    $stock_class = 'warning';
                                } elseif ($inventory['quantity'] <= $inventory['low_stock_threshold'] * 2) {
                                    $stock_status = 'medium';
                                    $stock_text = 'Limited Stock';
                                    $stock_class = 'secondary';
                                }
                                
                                if ($inventory['quantity'] == 0) {
                                    $stock_text = 'Out of Stock';
                                    $stock_class = 'warning';
                                }
                                ?>
                                <span class="badge <?php echo $stock_class; ?>" style="font-size: 1rem; padding: 1rem 2rem;">
                                    <span class="stock-indicator stock-<?php echo $stock_status; ?>"></span>
                                    <?php echo $stock_text; ?> (<?php echo number_format($inventory['quantity']); ?> units)
                                </span>
                            </div>
                        </div>

                        <!-- Info Section -->
                        <div class="product-info-section">
                            <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                            
                            <div class="product-meta">
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></span>
                                <span><i class="fas fa-hashtag"></i> #<?php echo $product['product_id']; ?></span>
                                <span>
                                    <i class="fas fa-<?php echo !empty($product['is_customizable']) ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo !empty($product['is_customizable']) ? '#4a9b6e' : '#b84a6e'; ?>"></i>
                                    <?php echo !empty($product['is_customizable']) ? 'Customizable' : 'Not Customizable'; ?>
                                </span>
                            </div>

                            <div class="product-price">
                                ₱<?php echo number_format($product['base_price'], 2); ?>
                            </div>

                            <h3 style="margin-bottom: 1rem; color: #f0e6d2;"><i class="fas fa-align-left" style="margin-right: 8px; color: #cfb087;"></i>Description</h3>
                            <div class="product-description">
                                <?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : '<span class="text-muted">No description provided.</span>'; ?>
                            </div>

                            <!-- Additional Details Table -->
                            <h3 class="section-title"><i class="fas fa-info-circle"></i>Product Information</h3>
                            <table class="info-table">
                                <?php if ($material): ?>
                                <tr>
                                    <td><i class="fas fa-cube"></i>Material</td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($material['material_name']); ?></strong>
                                        <div style="color: #b2a6ca; font-size: 0.85rem; margin-top: 0.3rem;">
                                            ₱<?php echo number_format($material['cost_per_unit'], 2); ?> per <?php echo $material['unit']; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><i class="fas fa-boxes"></i>Inventory Status</td>
                                    <td>
                                        <div><strong>Current Stock:</strong> <?php echo number_format($inventory['quantity']); ?> units</div>
                                        <div style="color: #b2a6ca; font-size: 0.85rem; margin-top: 0.3rem;">
                                            <strong>Low Stock Threshold:</strong> <?php echo $inventory['low_stock_threshold']; ?> units
                                        </div>
                                        <div style="color: #b2a6ca; font-size: 0.85rem; margin-top: 0.3rem;">
                                            <strong>Last Updated:</strong> <?php echo $inventory['last_updated'] != 'Never' ? date('M d, Y h:i A', strtotime($inventory['last_updated'])) : 'Never'; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-calendar-plus"></i>Added On</td>
                                    <td><?php echo isset($product['created_at']) ? date('F d, Y h:i A', strtotime($product['created_at'])) : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-calendar-check"></i>Last Updated</td>
                                    <td><?php echo isset($product['updated_at']) ? date('F d, Y h:i A', strtotime($product['updated_at'])) : 'N/A'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Orders Section -->
                    <h3 class="section-title"><i class="fas fa-history"></i>Recent Orders Containing This Product</h3>
                    
                    <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                        <table class="recent-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td>
                                        <i class="fas fa-user" style="margin-right: 5px; color: #cfb087;"></i>
                                        <?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?>
                                    </td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td>₱<?php echo number_format($order['price'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($order['quantity'] * $order['price'], 2); ?></strong></td>
                                    <td>
                                        <i class="fas fa-calendar-alt" style="margin-right: 5px; color: #b2a6ca;"></i>
                                        <?php echo isset($order['order_date']) ? date('M d, Y', strtotime($order['order_date'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'secondary';
                                        if (isset($order['order_status'])) {
                                            switch(strtolower($order['order_status'])) {
                                                case 'completed':
                                                case 'delivered':
                                                    $status_class = 'completed';
                                                    break;
                                                case 'processing':
                                                case 'shipped':
                                                    $status_class = 'processing';
                                                    break;
                                                case 'cancelled':
                                                case 'refunded':
                                                    $status_class = 'cancelled';
                                                    break;
                                                case 'pending':
                                                    $status_class = 'pending';
                                                    break;
                                            }
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo isset($order['order_status']) ? ucfirst($order['order_status']) : 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn-icon" title="View Order Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>No Orders Found</h3>
                            <p>This product hasn't been ordered yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>