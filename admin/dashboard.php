<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get statistics with date filters
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'")->fetch_assoc()['count'];
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

// Get today's statistics
$today_orders = $conn->query("
    SELECT COUNT(*) as count, SUM(total_amount) as total 
    FROM orders 
    WHERE DATE(created_at) = CURDATE()
")->fetch_assoc();
$today_revenue = $today_orders['total'] ?? 0;
$today_order_count = $today_orders['count'] ?? 0;

// Get monthly statistics
$monthly_stats = $conn->query("
    SELECT 
        COUNT(*) as order_count,
        SUM(total_amount) as revenue,
        AVG(total_amount) as avg_order_value
    FROM orders 
    WHERE MONTH(created_at) = $current_month AND YEAR(created_at) = $current_year
")->fetch_assoc();

// Get sales data for chart (last 7 months)
$sales_chart_data = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        MONTH(created_at) as month_num,
        SUM(total_amount) as total
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 MONTH)
        AND payment_status = 'paid'
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at DESC
    LIMIT 7
");

$months = [];
$sales_data = [];
if ($sales_chart_data) {
    while ($row = $sales_chart_data->fetch_assoc()) {
        array_unshift($months, $row['month']);
        array_unshift($sales_data, $row['total']);
    }
}

// If less than 7 months of data, fill with empty data
while (count($months) < 7) {
    array_unshift($months, 'N/A');
    array_unshift($sales_data, 0);
}

// Get order status distribution
$order_status_data = $conn->query("
    SELECT 
        order_status,
        COUNT(*) as count
    FROM orders
    GROUP BY order_status
");

$status_labels = [];
$status_counts = [];
if ($order_status_data) {
    while ($row = $order_status_data->fetch_assoc()) {
        $status_labels[] = ucfirst($row['order_status']);
        $status_counts[] = $row['count'];
    }
}

// Get recent orders
$recent_orders = $conn->query("
    SELECT o.*, u.full_name, u.email 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    ORDER BY o.created_at DESC 
    LIMIT 10
");

// Get low stock products
$low_stock = $conn->query("
    SELECT p.product_name, i.quantity, i.low_stock_threshold
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.product_id
    WHERE i.quantity <= i.low_stock_threshold
    ORDER BY i.quantity ASC
");

// Get recent messages
$recent_messages = $conn->query("
    SELECT m.*, u.full_name as user_name
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.user_id
    WHERE m.status = 'unread' 
    ORDER BY m.created_at DESC 
    LIMIT 5
");

// Check if order_items table exists
$order_items_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'order_items'");
if ($check_table && $check_table->num_rows > 0) {
    $order_items_exists = true;
}

// Get top selling products
if ($order_items_exists) {
    // Check if price column exists in products table
    $price_column_exists = false;
    $check_price = $conn->query("SHOW COLUMNS FROM products LIKE 'base_price'");
    if ($check_price && $check_price->num_rows > 0) {
        $price_column_exists = true;
    }

    if ($price_column_exists) {
        $top_products = $conn->query("
            SELECT 
                p.product_name,
                p.base_price as price,
                COUNT(oi.order_id) as order_count,
                SUM(oi.quantity) as total_sold
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            GROUP BY oi.product_id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
    } else {
        // If price column doesn't exist, query without price
        $top_products = $conn->query("
            SELECT 
                p.product_name,
                COUNT(oi.order_id) as order_count,
                SUM(oi.quantity) as total_sold
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            GROUP BY oi.product_id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
    }
} else {
    // If order_items table doesn't exist, set top_products to empty result
    $top_products = null;
}

// Get recent activities
$recent_activities = $conn->query("
    (SELECT 'order' as type, order_id as reference_id, CONCAT('New order #', order_id) as description, created_at as activity_date 
     FROM orders ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'user' as type, user_id as reference_id, CONCAT('New user registered: ', full_name) as description, created_at as activity_date 
     FROM users WHERE user_type = 'customer' ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'message' as type, message_id as reference_id, CONCAT('New message from: ', name) as description, created_at as activity_date 
     FROM messages ORDER BY created_at DESC LIMIT 5)
    ORDER BY activity_date DESC LIMIT 10
");

// Get comparison with previous month
$prev_month_stats = $conn->query("
    SELECT 
        COUNT(*) as order_count,
        SUM(total_amount) as revenue
    FROM orders 
    WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
")->fetch_assoc();

$revenue_growth = 0;
$order_growth = 0;
if ($prev_month_stats['revenue'] > 0 && isset($monthly_stats['revenue']) && $monthly_stats['revenue'] > 0) {
    $revenue_growth = (($monthly_stats['revenue'] - $prev_month_stats['revenue']) / $prev_month_stats['revenue']) * 100;
}
if ($prev_month_stats['order_count'] > 0 && isset($monthly_stats['order_count']) && $monthly_stats['order_count'] > 0) {
    $order_growth = (($monthly_stats['order_count'] - $prev_month_stats['order_count']) / $prev_month_stats['order_count']) * 100;
}

// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Furniverse</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .admin-user span:first-child {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            border: 1px solid #3d3452;
            color: #cfb087;
        }

        .admin-user .date {
            background: #161224;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.9rem;
            color: #b2a6ca;
            border: 1px solid #332d44;
        }

        .admin-content {
            padding: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
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

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            font-size: 1.8rem;
            background: #161224;
            border: 1px solid #332d44;
            color: #cfb087;
        }

        .stat-details {
            flex: 1;
        }

        .stat-details h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.3rem;
            color: #f0e6d2;
        }

        .stat-details p {
            margin: 0 0 0.5rem;
            color: #b2a6ca;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-growth {
            font-size: 0.8rem;
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            background: #161224;
            border: 1px solid #332d44;
        }

        .stat-growth.positive { 
            color: #b3ffb3;
            border-color: #4a9b6e;
        }
        .stat-growth.negative { 
            color: #ffb3b3;
            border-color: #b84a6e;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
        }

        .chart-container h3 {
            margin: 0 0 1rem;
            font-size: 1.2rem;
            color: #f0e6d2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
        }

        .dashboard-card h3 {
            margin: 0 0 1.5rem;
            font-size: 1.2rem;
            color: #f0e6d2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .text-muted {
            color: #b2a6ca;
            font-size: 0.9rem;
        }

        /* Tables */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            text-align: left;
            padding: 1rem 0.8rem;
            color: #b2a6ca;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #332d44;
        }

        .admin-table td {
            padding: 1rem 0.8rem;
            border-bottom: 1px solid #332d44;
            color: #d6cee8;
            font-size: 0.9rem;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background: #161224;
        }

        .font-bold {
            font-weight: 600;
            color: #f0e6d2;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.pending { 
            background: #2d2a1a; 
            color: #ffd966;
            border: 1px solid #b88a4a;
        }
        .status-badge.processing { 
            background: #1a2d3a; 
            color: #8bb9ff;
            border: 1px solid #3a6ea5;
        }
        .status-badge.shipped { 
            background: #1a2d3a; 
            color: #8bb9ff;
            border: 1px solid #3a6ea5;
        }
        .status-badge.delivered { 
            background: #1a2d24; 
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }
        .status-badge.cancelled { 
            background: #2d1a24; 
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        /* Buttons */
        .btn-small {
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            background: transparent;
            border: 1px solid #cfb087;
            border-radius: 30px;
            color: #f0e6d2;
            text-decoration: none;
            transition: 0.3s;
            cursor: pointer;
        }

        .btn-small:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            background: transparent;
            border: 1px solid #332d44;
            border-radius: 30px;
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
        }

        .btn-outline {
            padding: 0.4rem 1.2rem;
            font-size: 0.8rem;
            background: transparent;
            border: 1px solid #332d44;
            border-radius: 30px;
            color: #b2a6ca;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-outline:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #332d44;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
            background: #161224;
            border: 1px solid #332d44;
        }

        .activity-icon.order { color: #8bb9ff; }
        .activity-icon.user { color: #b3ffb3; }
        .activity-icon.message { color: #ffd966; }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0 0 0.3rem;
            font-size: 0.9rem;
            color: #f0e6d2;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #b2a6ca;
        }

        /* Product List */
        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #332d44;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info h4 {
            margin: 0 0 0.3rem;
            font-size: 1rem;
            color: #f0e6d2;
        }

        .product-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #b2a6ca;
        }

        .product-stats {
            text-align: right;
        }

        .product-stats .sold {
            font-weight: 600;
            color: #b3ffb3;
            font-size: 1rem;
        }

        .product-stats small {
            color: #b2a6ca;
            font-size: 0.8rem;
            display: block;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #b2a6ca;
        }

        .empty-state i {
            font-size: 3rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }

        .empty-state p {
            margin: 0;
        }

        /* Flex Utilities */
        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .gap-4 {
            gap: 1rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .admin-sidebar {
                width: 80px;
            }
            
            .sidebar-header h2 {
                font-size: 1.2rem;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .admin-main {
                margin-left: 80px;
            }
            
            .charts-row,
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .admin-main {
                padding: 1rem;
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
                <li><a href="customizations.php" class="active"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-chart-pie" style="margin-right: 10px; color: #cfb087;"></i>Dashboard Overview</h1>
                <div class="admin-user">
                    <span><i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <span class="date"><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></span>
                </div>
            </div>

            <div class="admin-content">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($total_users); ?></h3>
                            <p>Total Users</p>
                            <span class="stat-growth positive">
                                <i class="fas fa-arrow-up"></i> Active customers
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chair"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($total_products); ?></h3>
                            <p>Total Products</p>
                            <span class="stat-growth">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $low_stock ? $low_stock->num_rows : 0; ?> low stock
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($total_orders); ?></h3>
                            <p>Total Orders</p>
                            <span class="stat-growth <?php echo $order_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="fas fa-<?php echo $order_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo number_format(abs($order_growth), 1); ?>% vs last month
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-details">
                            <h3>₱<?php echo number_format($total_revenue, 2); ?></h3>
                            <p>Total Revenue</p>
                            <span class="stat-growth <?php echo $revenue_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="fas fa-<?php echo $revenue_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo number_format(abs($revenue_growth), 1); ?>% vs last month
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $today_order_count; ?></h3>
                            <p>Orders Today</p>
                            <span class="stat-growth">
                                ₱<?php echo number_format($today_revenue, 2); ?> revenue
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($monthly_stats['order_count'] ?? 0); ?></h3>
                            <p>Orders This Month</p>
                            <span class="stat-growth">
                                ₱<?php echo number_format($monthly_stats['revenue'] ?? 0, 2); ?> revenue
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-details">
                            <h3>₱<?php echo number_format($monthly_stats['avg_order_value'] ?? 0, 2); ?></h3>
                            <p>Avg Order Value</p>
                            <span class="stat-growth">This month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $recent_messages ? $recent_messages->num_rows : 0; ?></h3>
                            <p>Unread Messages</p>
                            <span class="stat-growth">Requires attention</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3>
                            <span><i class="fas fa-chart-line" style="color: #cfb087;"></i> Sales Overview</span>
                            <span class="text-muted">Last 7 Months</span>
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h3>
                            <span><i class="fas fa-chart-pie" style="color: #cfb087;"></i> Order Status</span>
                            <span class="text-muted">Distribution</span>
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Recent Orders -->
                    <div class="dashboard-card">
                        <h3>
                            <span><i class="fas fa-clock" style="color: #cfb087;"></i> Recent Orders</span>
                            <a href="orders.php" class="btn-outline">View All</a>
                        </h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                                    <?php while($order = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="font-bold"><?php echo $order['order_number'] ?? '#' . $order['order_id']; ?></span></td>
                                        <td><?php echo htmlspecialchars($order['full_name'] ?? 'Guest'); ?></td>
                                        <td><span class="font-bold">₱<?php echo number_format($order['total_amount'], 2); ?></span></td>
                                        <td><span class="status-badge <?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span></td>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>No recent orders</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dashboard-card">
                        <h3>
                            <span><i class="fas fa-bolt" style="color: #cfb087;"></i> Recent Activity</span>
                            <span class="text-muted">Live feed</span>
                        </h3>
                        <ul class="activity-list">
                            <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                                <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                <li class="activity-item">
                                    <div class="activity-icon <?php echo $activity['type']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $activity['type'] == 'order' ? 'shopping-cart' : 
                                                ($activity['type'] == 'user' ? 'user' : 'envelope'); 
                                        ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <span class="activity-time"><?php echo timeAgo($activity['activity_date']); ?></span>
                                    </div>
                                </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="activity-item" style="justify-content: center;">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No recent activities</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Second Grid Row -->
                <div class="dashboard-grid">
                    <!-- Top Selling Products -->
                    <div class="dashboard-card">
                        <h3>
                            <span><i class="fas fa-crown" style="color: #cfb087;"></i> Top Selling Products</span>
                            <span class="text-muted">By units sold</span>
                        </h3>
                        <ul class="product-list">
                            <?php if (isset($top_products) && $top_products && $top_products->num_rows > 0): ?>
                                <?php while($product = $top_products->fetch_assoc()): ?>
                                <li class="product-item">
                                    <div class="product-info">
                                        <h4><?php echo htmlspecialchars($product['product_name'] ?? 'Unknown Product'); ?></h4>
                                        <?php if (isset($product['price'])): ?>
                                            <p>₱<?php echo number_format($product['price'], 2); ?> per unit</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-stats">
                                        <div class="sold"><?php echo $product['total_sold'] ?? 0; ?> sold</div>
                                        <small><?php echo $product['order_count'] ?? 0; ?> orders</small>
                                    </div>
                                </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="product-item" style="justify-content: center;">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <p>No sales data available</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Low Stock Alerts -->
                    <div class="dashboard-card">
                        <h3>
                            <span><i class="fas fa-exclamation-triangle" style="color: #cfb087;"></i> Low Stock Alerts</span>
                            <a href="inventory.php" class="btn-outline">Manage</a>
                        </h3>
                        <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                            <ul class="product-list">
                                <?php while($stock = $low_stock->fetch_assoc()): ?>
                                <li class="product-item">
                                    <div class="product-info">
                                        <h4><?php echo htmlspecialchars($stock['product_name']); ?></h4>
                                        <p>Threshold: <?php echo $stock['low_stock_threshold']; ?> units</p>
                                    </div>
                                    <div class="product-stats">
                                        <div class="sold" style="color: #ffb3b3;"><?php echo $stock['quantity']; ?> left</div>
                                        <small>Low stock</small>
                                    </div>
                                </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle" style="color: #4a9b6e;"></i>
                                <h4 style="color: #b3ffb3; margin-bottom: 0.5rem;">All Stock Healthy</h4>
                                <p>All products are well-stocked</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unread Messages -->
                <?php if ($recent_messages && $recent_messages->num_rows > 0): ?>
                <div class="dashboard-card" style="margin-top: 1.5rem;">
                    <h3>
                        <span><i class="fas fa-envelope" style="color: #cfb087;"></i> Unread Messages</span>
                        <a href="messages.php" class="btn-outline">View All</a>
                    </h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($msg = $recent_messages->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-user-circle" style="color: #cfb087;"></i>
                                        <span class="font-bold"><?php echo htmlspecialchars($msg['name'] ?? $msg['user_name'] ?? 'Anonymous'); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></td>
                                <td>
                                    <a href="view-message.php?id=<?php echo $msg['message_id']; ?>" class="btn-icon" title="Read Message">
                                        <i class="fas fa-envelope-open"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const ctx1 = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: <?php echo json_encode($sales_data); ?>,
                    borderColor: '#cfb087',
                    backgroundColor: 'rgba(207, 176, 135, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#cfb087',
                    pointBorderColor: '#1e192c',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e192c',
                        titleColor: '#f0e6d2',
                        bodyColor: '#d6cee8',
                        borderColor: '#332d44',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#332d44'
                        },
                        ticks: {
                            color: '#b2a6ca',
                            callback: function(value, index, values) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#b2a6ca'
                        }
                    }
                }
            }
        });

        // Order Status Chart
        const ctx2 = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: [
                        '#ffd966', // pending
                        '#8bb9ff', // processing
                        '#b3a4cb', // shipped
                        '#b3ffb3', // delivered
                        '#ffb3b3'  // cancelled
                    ],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#b2a6ca'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e192c',
                        titleColor: '#f0e6d2',
                        bodyColor: '#d6cee8',
                        borderColor: '#332d44',
                        borderWidth: 1
                    }
                }
            }
        });
    </script>
</body>
</html>