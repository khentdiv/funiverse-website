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
if ($check_columns) {
    while ($column = $check_columns->fetch_assoc()) {
        $product_columns[$column['Field']] = true;
    }
}

// Check what columns exist in the orders table
$order_columns = [];
$check_order_columns = $conn->query("SHOW COLUMNS FROM orders");
if ($check_order_columns) {
    while ($column = $check_order_columns->fetch_assoc()) {
        $order_columns[$column['Field']] = true;
    }
}

// Determine the price column name in products table
$price_column = 'base_price'; // Default
if (isset($product_columns['price'])) {
    $price_column = 'price';
} elseif (isset($product_columns['base_price'])) {
    $price_column = 'base_price';
}

// Determine the payment method column name in orders table
$payment_column = null;
$possible_payment_columns = ['payment_method', 'payment_type', 'payment_option', 'method'];
foreach ($possible_payment_columns as $col) {
    if (isset($order_columns[$col])) {
        $payment_column = $col;
        break;
    }
}

// Determine the payment status column name
$payment_status_column = 'payment_status'; // Default
if (!isset($order_columns['payment_status'])) {
    $possible_status_columns = ['status', 'order_status', 'payment_state'];
    foreach ($possible_status_columns as $col) {
        if (isset($order_columns[$col])) {
            $payment_status_column = $col;
            break;
        }
    }
}

// Check if order_items table exists and has necessary columns
$order_items_columns = [];
$check_order_items = $conn->query("SHOW TABLES LIKE 'order_items'");
if ($check_order_items && $check_order_items->num_rows > 0) {
    $cols = $conn->query("SHOW COLUMNS FROM order_items");
    while ($col = $cols->fetch_assoc()) {
        $order_items_columns[$col['Field']] = true;
    }
}

// Get date range
$date_range = isset($_GET['range']) ? $_GET['range'] : '30';
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$date_range days"));

// Sales Overview - using correct column names
$sales_overview = $conn->query("
    SELECT 
        DATE(order_date) as date,
        COUNT(*) as order_count,
        SUM(total_amount) as revenue
    FROM orders 
    WHERE order_date BETWEEN '$start_date' AND '$end_date'
        AND $payment_status_column = 'paid'
    GROUP BY DATE(order_date)
    ORDER BY date
");

$sales_dates = [];
$sales_counts = [];
$sales_revenues = [];
if ($sales_overview) {
    while ($row = $sales_overview->fetch_assoc()) {
        $sales_dates[] = date('M d', strtotime($row['date']));
        $sales_counts[] = $row['order_count'];
        $sales_revenues[] = $row['revenue'];
    }
}

// Top Products - with dynamic column names
$top_products = null;
if (isset($order_items_columns['product_id'])) {
    $top_products_query = "
        SELECT 
            p.product_name,
            p.$price_column as price,
            COUNT(oi.order_id) as order_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 10
    ";
    $top_products = $conn->query($top_products_query);
}

// Category Performance
$category_performance = null;
if (isset($order_items_columns['product_id'])) {
    $category_performance_query = "
        SELECT 
            c.category_name,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY c.category_id
        ORDER BY revenue DESC
    ";
    $category_performance = $conn->query($category_performance_query);
}

// Customer Stats
$customer_stats_query = "
    SELECT 
        COUNT(DISTINCT user_id) as active_customers,
        COALESCE(AVG(order_count), 0) as avg_orders_per_customer,
        COALESCE(AVG(total_spent), 0) as avg_order_value
    FROM (
        SELECT 
            user_id,
            COUNT(*) as order_count,
            AVG(total_amount) as total_spent
        FROM orders
        WHERE order_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY user_id
    ) as customer_orders
";
$customer_stats_result = $conn->query($customer_stats_query);
$customer_stats = $customer_stats_result ? $customer_stats_result->fetch_assoc() : ['active_customers' => 0, 'avg_orders_per_customer' => 0, 'avg_order_value' => 0];

// Daily Averages
$daily_averages_query = "
    SELECT 
        COALESCE(AVG(daily_orders), 0) as avg_daily_orders,
        COALESCE(AVG(daily_revenue), 0) as avg_daily_revenue
    FROM (
        SELECT 
            DATE(order_date) as date,
            COUNT(*) as daily_orders,
            SUM(total_amount) as daily_revenue
        FROM orders
        WHERE order_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(order_date)
    ) as daily_stats
";
$daily_averages_result = $conn->query($daily_averages_query);
$daily_averages = $daily_averages_result ? $daily_averages_result->fetch_assoc() : ['avg_daily_orders' => 0, 'avg_daily_revenue' => 0];

// Payment Methods - only if payment column exists
$payment_methods = null;
$payment_labels = [];
$payment_counts = [];
if ($payment_column) {
    $payment_methods_query = "
        SELECT 
            $payment_column as payment_method,
            COUNT(*) as count,
            SUM(total_amount) as total
        FROM orders
        WHERE order_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY $payment_column
    ";
    $payment_methods = $conn->query($payment_methods_query);
    
    if ($payment_methods) {
        while ($row = $payment_methods->fetch_assoc()) {
            $payment_labels[] = ucfirst($row['payment_method'] ?: 'Other');
            $payment_counts[] = $row['count'];
        }
        $payment_methods->data_seek(0); // Reset pointer for later use
    }
}

// Hourly Distribution
$hourly_distribution = $conn->query("
    SELECT 
        HOUR(order_date) as hour,
        COUNT(*) as order_count
    FROM orders
    WHERE order_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY HOUR(order_date)
    ORDER BY hour
");

$hours = [];
$hourly_orders = [];
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf("%02d:00", $i);
    $hourly_orders[$i] = 0;
}
if ($hourly_distribution) {
    while ($row = $hourly_distribution->fetch_assoc()) {
        $hourly_orders[$row['hour']] = $row['order_count'];
    }
}

// Get comparison with previous period
$prev_start = date('Y-m-d', strtotime("-$date_range days", strtotime($start_date)));
$prev_end = date('Y-m-d', strtotime("-1 day", strtotime($start_date)));

$previous_period_query = "
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM orders
    WHERE order_date BETWEEN '$prev_start' AND '$prev_end'
        AND $payment_status_column = 'paid'
";
$previous_period_result = $conn->query($previous_period_query);
$previous_period = $previous_period_result ? $previous_period_result->fetch_assoc() : ['order_count' => 0, 'revenue' => 0];

$current_period_query = "
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM orders
    WHERE order_date BETWEEN '$start_date' AND '$end_date'
        AND $payment_status_column = 'paid'
";
$current_period_result = $conn->query($current_period_query);
$current_period = $current_period_result ? $current_period_result->fetch_assoc() : ['order_count' => 0, 'revenue' => 0];

$order_growth = 0;
$revenue_growth = 0;
if ($previous_period['order_count'] > 0) {
    $order_growth = (($current_period['order_count'] - $previous_period['order_count']) / $previous_period['order_count']) * 100;
}
if ($previous_period['revenue'] > 0) {
    $revenue_growth = (($current_period['revenue'] - $previous_period['revenue']) / $previous_period['revenue']) * 100;
}

// Helper function to safely get numeric values
function safe_num($value, $default = 0) {
    return is_numeric($value) ? $value : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Furniverse Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            padding: 20px;
        }
        .date-range-bar {
            margin-bottom: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .range-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            color: #666;
            cursor: pointer;
            text-decoration: none;
        }
        .range-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
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
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .stat-card .growth {
            font-size: 14px;
            margin-top: 5px;
        }
        .growth.positive { color: #28a745; }
        .growth.negative { color: #dc3545; }
        
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        .chart-wrapper {
            height: 300px;
            position: relative;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .analytics-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .analytics-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 10px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: #e67e22;
            transition: width 0.3s;
        }
        
        .hourly-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .hour-block {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .hour-label {
            font-size: 12px;
            color: #666;
        }
        .hour-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .info-message {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .text-muted {
            color: #999;
            font-style: italic;
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
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="analytics.php" class="active"><i class="fas fa-chart-line"></i> Analytics</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1>Analytics</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>

            <div class="admin-content">
                <div class="analytics-container">
                    <!-- Date Range Selector -->
                    <div class="date-range-bar">
                        <a href="?range=7" class="range-btn <?php echo $date_range == '7' ? 'active' : ''; ?>">Last 7 Days</a>
                        <a href="?range=30" class="range-btn <?php echo $date_range == '30' ? 'active' : ''; ?>">Last 30 Days</a>
                        <a href="?range=90" class="range-btn <?php echo $date_range == '90' ? 'active' : ''; ?>">Last 90 Days</a>
                        <a href="?range=365" class="range-btn <?php echo $date_range == '365' ? 'active' : ''; ?>">Last Year</a>
                    </div>

                    <!-- Key Metrics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Orders</h3>
                            <div class="value"><?php echo number_format(safe_num($current_period['order_count'])); ?></div>
                            <div class="growth <?php echo $order_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $order_growth >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($order_growth), 1); ?>% vs previous period
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Revenue</h3>
                            <div class="value">₱<?php echo number_format(safe_num($current_period['revenue']), 2); ?></div>
                            <div class="growth <?php echo $revenue_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $revenue_growth >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($revenue_growth), 1); ?>% vs previous period
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>Avg Daily Orders</h3>
                            <div class="value"><?php echo number_format(safe_num($daily_averages['avg_daily_orders']), 1); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Avg Daily Revenue</h3>
                            <div class="value">₱<?php echo number_format(safe_num($daily_averages['avg_daily_revenue']), 2); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Avg Order Value</h3>
                            <div class="value">₱<?php echo number_format(safe_num($customer_stats['avg_order_value']), 2); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Customers</h3>
                            <div class="value"><?php echo number_format(safe_num($customer_stats['active_customers'])); ?></div>
                        </div>
                    </div>

                    <?php if (!$payment_column): ?>
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Payment method tracking is not available. The orders table doesn't have a payment method column.
                    </div>
                    <?php endif; ?>

                    <?php if (!$top_products || !$category_performance): ?>
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Some analytics features require order items data. Make sure you have orders with items to see product and category performance.
                    </div>
                    <?php endif; ?>

                    <!-- Sales Chart -->
                    <div class="charts-row">
                        <div class="chart-container">
                            <h3>Sales Overview</h3>
                            <div class="chart-wrapper">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-container">
                            <h3>Payment Methods</h3>
                            <div class="chart-wrapper">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Grid -->
                    <div class="analytics-grid">
                        <!-- Top Products -->
                        <div class="analytics-card">
                            <h3>Top Selling Products</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($top_products && $top_products->num_rows > 0): ?>
                                        <?php while($product = $top_products->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['product_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo safe_num($product['total_sold']); ?> units</td>
                                                <td>₱<?php echo number_format(safe_num($product['revenue']), 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-muted" style="text-align: center;">No sales data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Category Performance -->
                        <div class="analytics-card">
                            <h3>Category Performance</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($category_performance && $category_performance->num_rows > 0): ?>
                                        <?php 
                                        // Calculate total revenue for percentages
                                        $total_revenue = 0;
                                        $cat_data = [];
                                        $category_performance->data_seek(0);
                                        while($cat = $category_performance->fetch_assoc()) {
                                            $cat_data[] = $cat;
                                            $total_revenue += safe_num($cat['revenue']);
                                        }
                                        foreach($cat_data as $category): 
                                            $percentage = $total_revenue > 0 ? (safe_num($category['revenue']) / $total_revenue) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['category_name'] ?: 'Uncategorized'); ?></td>
                                                <td><?php echo safe_num($category['order_count']); ?></td>
                                                <td>
                                                    ₱<?php echo number_format(safe_num($category['revenue']), 2); ?>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-muted" style="text-align: center;">No category data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Hourly Distribution -->
                    <div class="analytics-card">
                        <h3>Order Distribution by Hour</h3>
                        <div class="hourly-grid">
                            <?php for ($i = 0; $i < 24; $i+=2): ?>
                                <div class="hour-block">
                                    <div class="hour-label"><?php echo sprintf("%02d:00", $i); ?></div>
                                    <div class="hour-value"><?php echo $hourly_orders[$i]; ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="chart-wrapper" style="height: 200px; margin-top: 20px;">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const ctx1 = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($sales_dates); ?>,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: <?php echo json_encode($sales_revenues); ?>,
                    borderColor: '#e67e22',
                    backgroundColor: 'rgba(230, 126, 34, 0.1)',
                    tension: 0.1,
                    fill: true,
                    yAxisID: 'y-revenue'
                }, {
                    label: 'Orders',
                    data: <?php echo json_encode($sales_counts); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.1,
                    fill: true,
                    yAxisID: 'y-orders'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label.includes('Revenue')) {
                                    return context.dataset.label + ': ₱' + context.raw.toLocaleString();
                                }
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    'y-revenue': {
                        type: 'linear',
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    'y-orders': {
                        type: 'linear',
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        const ctx2 = document.getElementById('paymentChart').getContext('2d');
        const paymentLabels = <?php echo json_encode($payment_labels); ?>;
        const paymentData = <?php echo json_encode($payment_counts); ?>;
        
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: paymentLabels.length ? paymentLabels : ['No Data'],
                datasets: [{
                    data: paymentData.length ? paymentData : [1],
                    backgroundColor: [
                        '#e67e22',
                        '#3498db',
                        '#2ecc71',
                        '#9b59b6',
                        '#f1c40f'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Hourly Chart
        const ctx3 = document.getElementById('hourlyChart').getContext('2d');
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($hours); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_values($hourly_orders)); ?>,
                    backgroundColor: '#e67e22',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>