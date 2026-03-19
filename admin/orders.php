<?php
require_once '../config.php';

if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = sanitize($_POST['order_id']);
    $order_status = sanitize($_POST['order_status']);
    $payment_status = sanitize($_POST['payment_status']);
    
    $query = "UPDATE orders SET order_status = ?, payment_status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $order_status, $payment_status, $order_id);
    
    if ($stmt->execute()) {
        // Get order details for notification
        $order_query = "SELECT o.*, u.phone, u.full_name FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.user_id 
                        WHERE o.order_id = ?";
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bind_param("i", $order_id);
        $order_stmt->execute();
        $order = $order_stmt->get_result()->fetch_assoc();
        
        // Get order number for better reference
        $order_number = $order['order_number'] ?? '#' . $order_id;
        
        $message = "Hello {$order['full_name']}, your order {$order_number} status has been updated to: $order_status. Payment status: $payment_status. Thank you for choosing Furniverse!";
        
        // Insert notification
        $notif_query = "INSERT INTO notifications (user_id, order_id, message, type, status, sent_at) VALUES (?, ?, ?, 'order', 'sent', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("iis", $order['user_id'], $order_id, $message);
        $notif_stmt->execute();
        
        // Insert into order status history
        $history_query = "INSERT INTO order_status_history (order_id, status, comment, changed_by, created_at) VALUES (?, ?, ?, ?, NOW())";
        $history_stmt = $conn->prepare($history_query);
        $comment = "Status updated to $order_status, Payment: $payment_status";
        $history_stmt->bind_param("issi", $order_id, $order_status, $comment, $_SESSION['user_id']);
        $history_stmt->execute();
        
        // Log action
        $log_query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'update_order', ?, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $details = "Updated order {$order_number} status to $order_status";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        
        $_SESSION['success'] = "Order {$order_number} updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order: " . $conn->error;
    }
    redirect('orders.php');
}

// Handle Delete Order
if (isset($_GET['delete'])) {
    $order_id = intval($_GET['delete']);
    
    // Check if order exists
    $check = $conn->query("SELECT order_number FROM orders WHERE order_id = $order_id");
    if ($check && $check->num_rows > 0) {
        $order = $check->fetch_assoc();
        $order_number = $order['order_number'] ?? '#' . $order_id;
        
        // Soft delete or hard delete? Using soft delete with status
        $conn->query("UPDATE orders SET order_status = 'cancelled' WHERE order_id = $order_id");
        $_SESSION['success'] = "Order {$order_number} has been cancelled";
    }
    redirect('orders.php');
}

// Filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build query
$query = "
    SELECT o.*, u.full_name, u.email, u.phone, p.product_name 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN customizations c ON o.customization_id = c.customization_id
    LEFT JOIN products p ON c.product_id = p.product_id
    WHERE 1=1
";

if ($status_filter) {
    $query .= " AND o.order_status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($date_from) {
    $query .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $query .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

$query .= " ORDER BY o.created_at DESC";

$orders = $conn->query($query);

// Check if query was successful
if (!$orders) {
    $error = "Database error: " . $conn->error;
}

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as unpaid,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value
FROM orders";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get monthly revenue
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%b') as month,
    SUM(total_amount) as revenue
FROM orders 
WHERE payment_status = 'paid' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY created_at DESC";
$monthly_result = $conn->query($monthly_query);
$monthly_data = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Furniverse Admin</title>
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
            transition: all 0.3s;
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
            -webkit-backdrop-filter: blur(16px);
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
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(207, 176, 135, 0.1));
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            border: 1px solid #cfb087;
            color: #cfb087;
            backdrop-filter: blur(5px);
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
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert.success {
            background: rgba(26, 45, 36, 0.9);
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .alert.error {
            background: rgba(45, 26, 36, 0.9);
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e192c 0%, #2a2340 100%);
            border: 1px solid rgba(207, 176, 135, 0.15);
            border-radius: 42px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #8f7b5c);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(207, 176, 135, 0.3);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-header h3 {
            color: #b2a6ca;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-header i {
            font-size: 1.5rem;
            color: #cfb087;
            opacity: 0.8;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #f0e6d2;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: #8f7b9e;
            font-size: 0.85rem;
        }

        /* Filters Bar */
        .filters-bar {
            background: linear-gradient(135deg, #1e192c 0%, #231e32 100%);
            border: 1px solid rgba(207, 176, 135, 0.2);
            border-radius: 42px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .filters-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #cfb087, #8f7b5c, #cfb087, transparent);
            border-radius: 42px 42px 0 0;
        }

        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            color: #cfb087;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.8rem 1.2rem;
            background: rgba(22, 18, 36, 0.8);
            border: 1px solid rgba(207, 176, 135, 0.2);
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.1);
        }

        .filter-group select option {
            background: #1e192c;
            color: #f0e6d2;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #cfb087, #b89a6a);
            border: 1px solid #cfb087;
            color: #0f0b17;
        }

        .btn-primary:hover {
            background: #cfb087;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(207, 176, 135, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #3d3452;
            color: #b2a6ca;
        }

        .btn-secondary:hover {
            border-color: #cfb087;
            color: #f0e6d2;
            background: rgba(207, 176, 135, 0.1);
        }

        /* Table Styles */
        .table-responsive {
            background: linear-gradient(135deg, #1e192c 0%, #231e32 100%);
            border: 1px solid rgba(207, 176, 135, 0.2);
            border-radius: 42px;
            padding: 1.5rem;
            overflow-x: auto;
            position: relative;
        }

        .table-responsive::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #cfb087, #8f7b5c, #cfb087, transparent);
            border-radius: 42px 42px 0 0;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            text-align: left;
            padding: 1rem 1rem 1rem 0;
            color: #cfb087;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
        }

        .admin-table td {
            padding: 1rem 1rem 1rem 0;
            border-bottom: 1px solid rgba(207, 176, 135, 0.1);
            color: #e2ddf2;
            font-size: 0.95rem;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background: rgba(207, 176, 135, 0.05);
        }

        .order-number {
            font-family: 'Inter', monospace;
            font-weight: 600;
            color: #cfb087;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }

        .status-badge.pending { 
            background: rgba(45, 42, 26, 0.9); 
            color: #ffd966; 
            border: 1px solid rgba(255, 217, 102, 0.3);
        }
        .status-badge.processing { 
            background: rgba(26, 45, 61, 0.9); 
            color: #8bb9ff; 
            border: 1px solid rgba(139, 185, 255, 0.3);
        }
        .status-badge.shipped { 
            background: rgba(45, 26, 61, 0.9); 
            color: #c08bff; 
            border: 1px solid rgba(192, 139, 255, 0.3);
        }
        .status-badge.delivered { 
            background: rgba(26, 45, 36, 0.9); 
            color: #7acfa2; 
            border: 1px solid rgba(122, 207, 162, 0.3);
        }
        .status-badge.cancelled { 
            background: rgba(45, 26, 36, 0.9); 
            color: #ff8b8b; 
            border: 1px solid rgba(255, 139, 139, 0.3);
        }
        .status-badge.paid { 
            background: rgba(26, 45, 36, 0.9); 
            color: #7acfa2; 
            border: 1px solid rgba(122, 207, 162, 0.3);
        }
        .status-badge.unpaid { 
            background: rgba(45, 42, 26, 0.9); 
            color: #ffd966; 
            border: 1px solid rgba(255, 217, 102, 0.3);
        }
        .status-badge.failed { 
            background: rgba(45, 26, 36, 0.9); 
            color: #ff8b8b; 
            border: 1px solid rgba(255, 139, 139, 0.3);
        }
        .status-badge.refunded { 
            background: rgba(61, 45, 26, 0.9); 
            color: #ffb366; 
            border: 1px solid rgba(255, 179, 102, 0.3);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #b2a6ca;
            text-decoration: none;
            transition: all 0.3s;
            background: rgba(22, 18, 36, 0.8);
            border: 1px solid rgba(207, 176, 135, 0.2);
        }

        .btn-icon:hover {
            background: #cfb087;
            color: #0f0b17;
            border-color: #cfb087;
            transform: translateY(-2px);
        }

        .btn-icon.delete:hover {
            background: #b84a6e;
            border-color: #b84a6e;
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: linear-gradient(135deg, #1e192c 0%, #231e32 100%);
            border: 1px solid rgba(207, 176, 135, 0.3);
            border-radius: 42px;
            max-width: 500px;
            margin: 5% auto;
            position: relative;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            color: #f0e6d2;
            font-size: 1.5rem;
        }

        .close {
            color: #b2a6ca;
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s;
            line-height: 1;
        }

        .close:hover {
            color: #cfb087;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #cfb087;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group select {
            width: 100%;
            padding: 1rem 1.5rem;
            background: rgba(22, 18, 36, 0.8);
            border: 1px solid rgba(207, 176, 135, 0.2);
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s;
        }

        .form-group select:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.1);
        }

        .form-group select option {
            background: #1e192c;
            color: #f0e6d2;
        }

        .text-center {
            text-align: center;
            padding: 3rem;
            color: #b2a6ca;
            font-style: italic;
        }

        /* Small text */
        small {
            color: #8f7b9e;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 80px;
            }
            
            .sidebar-header h2 {
                font-size: 1.2rem;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .admin-main {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .modal-content {
                margin: 10% 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-content {
                padding: 1rem;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1e192c;
        }

        ::-webkit-scrollbar-thumb {
            background: #3d3452;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #cfb087;
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
                <h1><i class="fas fa-shopping-cart"></i> Order Management</h1>
                <div class="admin-user">
                    <i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Order Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Total Orders</h3>
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                        <div class="stat-label">All time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Total Revenue</h3>
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-value">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                        <div class="stat-label">From paid orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Average Order</h3>
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value">₱<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
                        <div class="stat-label">Per order</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Pending Orders</h3>
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                        <div class="stat-label">Awaiting processing</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Processing</h3>
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['processing'] ?? 0); ?></div>
                        <div class="stat-label">In progress</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Delivered</h3>
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['delivered'] ?? 0); ?></div>
                        <div class="stat-label">Completed orders</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-bar">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label><i class="fas fa-filter"></i> Status</label>
                            <select name="status">
                                <option value="">All Orders</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> From</label>
                            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> To</label>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </form>
                </div>

                <!-- Orders Table -->
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Order Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders && $orders->num_rows > 0): ?>
                                <?php while($order = $orders->fetch_assoc()): 
                                    $order_number = $order['order_number'] ?? 'ORD-' . str_pad($order['order_id'], 5, '0', STR_PAD_LEFT);
                                ?>
                                <tr>
                                    <td><span class="order-number">#<?php echo $order['order_id']; ?></span></td>
                                    <td><span class="order-number"><?php echo $order_number; ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['full_name'] ?? 'Guest Customer'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['product_name'] ?? 'Custom Order'); ?></td>
                                    <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge <?php echo $order['order_status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($order['order_status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $order['payment_status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="#" onclick="showUpdateModal(<?php echo $order['order_id']; ?>, '<?php echo $order['order_status'] ?? 'pending'; ?>', '<?php echo $order['payment_status'] ?? 'pending'; ?>')" class="btn-icon" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="invoice.php?id=<?php echo $order['order_id']; ?>" class="btn-icon" title="Generate Invoice">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <a href="?delete=<?php echo $order['order_id']; ?>" class="btn-icon delete" title="Cancel Order" onclick="return confirm('Are you sure you want to cancel this order?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #3d3452; margin-bottom: 1rem;"></i>
                                        <p>No orders found matching your criteria</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Order Modal -->
    <div id="updateOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Order Status</h2>
                <span class="close" onclick="hideUpdateModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="modal_order_id">
                    
                    <div class="form-group">
                        <label for="order_status">Order Status</label>
                        <select id="modal_order_status" name="order_status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_status">Payment Status</label>
                        <select id="modal_payment_status" name="payment_status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideUpdateModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showUpdateModal(orderId, currentStatus, paymentStatus) {
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_order_status').value = currentStatus;
            document.getElementById('modal_payment_status').value = paymentStatus;
            document.getElementById('updateOrderModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function hideUpdateModal() {
            document.getElementById('updateOrderModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateOrderModal');
            if (event.target == modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Add loading animation to buttons
        document.querySelectorAll('.btn-primary, .btn-secondary').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.type === 'submit' || this.tagName === 'A') {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                }
            });
        });

        // Smooth scroll to top when applying filters
        document.querySelector('.filters-form').addEventListener('submit', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>