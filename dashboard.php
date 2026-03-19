<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// First, check if material_id column exists
$check_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'material_id'");
$has_material_column = $check_column && $check_column->num_rows > 0;

// Check if image_path column exists
$check_image_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'image_path'");
$has_image_path = $check_image_column && $check_image_column->num_rows > 0;

if ($has_material_column && $has_image_path) {
    // Try the query with material join and image_path
    $customizations = $conn->query("
        SELECT c.*, p.product_name, p.image as product_image, 
               COALESCE(m.material_name, 'Not specified') as material_name
        FROM customizations c
        LEFT JOIN products p ON c.product_id = p.product_id
        LEFT JOIN materials m ON c.material_id = m.material_id
        WHERE c.user_id = $user_id
        ORDER BY c.created_at DESC
    ");
    
    // If that fails, try without material join
    if (!$customizations) {
        error_log("Query with material join failed: " . $conn->error);
        $customizations = $conn->query("
            SELECT c.*, p.product_name, p.image as product_image
            FROM customizations c
            LEFT JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = $user_id
            ORDER BY c.created_at DESC
        ");
    }
} elseif ($has_image_path) {
    // Material column doesn't exist but image_path does
    $customizations = $conn->query("
        SELECT c.*, p.product_name, p.image as product_image
        FROM customizations c
        LEFT JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = $user_id
        ORDER BY c.created_at DESC
    ");
} else {
    // Neither material nor image_path columns exist
    $customizations = $conn->query("
        SELECT c.*, p.product_name, p.image as product_image
        FROM customizations c
        LEFT JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = $user_id
        ORDER BY c.created_at DESC
    ");
}

if (!$customizations) {
    die("Error loading customizations: " . $conn->error);
}

// Fetch user's orders
$orders = $conn->query("
    SELECT o.*, c.customization_id, p.product_name 
    FROM orders o
    LEFT JOIN customizations c ON o.customization_id = c.customization_id
    LEFT JOIN products p ON c.product_id = p.product_id
    WHERE o.user_id = $user_id
    ORDER BY o.created_at DESC
");

if (!$orders) {
    die("Error loading orders: " . $conn->error);
}

// Handle SMS notification simulation
if (isset($_POST['send_sms'])) {
    $order_id = sanitize($_POST['order_id']);
    $message = sanitize($_POST['message']);
    
    $query = "INSERT INTO notifications (user_id, order_id, message, status) VALUES (?, ?, ?, 'sent')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $user_id, $order_id, $message);
    
    if ($stmt->execute()) {
        $sms_success = "SMS notification sent successfully!";
    } else {
        $sms_error = "Failed to send SMS: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · Furniverse modern studio</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: structured & elegant -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Copy all your existing CSS here */
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

        h1, h2, h3, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }

        .navbar {
            background: rgba(18, 14, 29, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(207, 176, 135, 0.25);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.8rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 2.1rem;
            background: linear-gradient(135deg, #ece3f0, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
            list-style: none;
        }

        .nav-menu a {
            text-decoration: none;
            font-weight: 500;
            color: #d6cee8;
            font-size: 0.98rem;
            transition: 0.2s;
            position: relative;
            padding: 0.5rem 0;
        }

        .btn-register {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
        }

        .btn-dashboard {
            background: #2d2640;
            border: 1.5px solid #6d5a8b;
            color: #e6dbf2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
        }

        .btn-logout {
            background: transparent;
            border: 1.5px solid #68587e;
            color: #c5b8dc !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
        }

        .user-greeting {
            color: #cfb087;
            font-weight: 400;
            font-size: 0.9rem;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            border: 1px solid #3d3452;
        }

        .page-header {
            background: radial-gradient(ellipse at 70% 30%, #2f2642, #0a0713 80%);
            padding: 5rem 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
        }

        .page-header h1 {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #f0e6d2, #cfb087, #bba6d9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .alert {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 500;
            text-align: center;
        }

        .alert.error {
            background: #2d1a24;
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        .alert.success {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .dashboard-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 3rem 0;
        }

        .tab-btn {
            text-decoration: none;
            padding: 0.9rem 2.2rem;
            border-radius: 60px;
            font-weight: 500;
            font-size: 1rem;
            transition: 0.3s;
            background: #1e192c;
            border: 1px solid #3d3452;
            color: #cbc2e6;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }

        .tab-btn i {
            margin-right: 8px;
            color: #cfb087;
        }

        .tab-btn:hover {
            border-color: #cfb087;
            color: #f0e6d2;
            transform: translateY(-3px);
        }

        .tab-btn.active {
            background: #2d2640;
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        .tab-content h2 {
            font-size: 2.2rem;
            margin-bottom: 2rem;
            color: #f0e6d2;
        }

        .customizations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .customization-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            overflow: hidden;
            box-shadow: 0 25px 40px -12px #010101;
            transition: 0.3s ease;
        }

        .customization-card:hover {
            transform: translateY(-8px);
            border-color: #6b5b85;
        }

        .customization-card .image-wrapper {
            height: 220px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .customization-card .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .customization-card:hover .image-wrapper img {
            transform: scale(1.05);
        }

        .customization-card .image-wrapper i {
            font-size: 4rem;
            color: #4a3f60;
        }

        .customization-card h3 {
            font-size: 1.5rem;
            color: #f0e6d2;
            margin: 1.5rem 1.5rem 0.8rem;
        }

        .customization-card p {
            color: #b2a6ca;
            margin: 0 1.5rem 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .customization-card p i {
            width: 20px;
            color: #cfb087;
        }

        .status {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #2d2640;
            border: 1px solid #49405f;
            color: #b3a4cb;
        }

        .status.saved {
            background: #1a2d3a;
            border-color: #4a6fa5;
            color: #8bb9ff;
        }

        .status.ordered {
            background: #1a2d24;
            border-color: #2e7d5e;
            color: #7acfa2;
        }

        .card-actions {
            display: flex;
            gap: 0.8rem;
            margin: 1.5rem 1.5rem 2rem;
        }

        .btn-small {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            flex: 1;
            text-align: center;
            text-decoration: none;
            border-radius: 30px;
            background: #2d2640;
            border: 1px solid #49405f;
            color: #cbc2e6;
            transition: 0.2s;
        }

        .btn-small:hover {
            background: #3d3452;
            border-color: #6b5b85;
            color: #f0e6d2;
        }

        .btn-primary {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
            padding: 0.6rem 1rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
            text-align: center;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17 !important;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .order-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-header h3 {
            font-size: 1.6rem;
            color: #f0e6d2;
        }

        .order-status {
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #2d2640;
            border: 1px solid;
        }

        .order-status.processing {
            border-color: #b85c1a;
            color: #ffb266;
        }

        .order-card p {
            color: #b2a6ca;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sms-input-group {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }

        .sms-input-group input {
            flex: 1;
            min-width: 250px;
            padding: 0.9rem 1.5rem;
            border: 1px solid #3d3452;
            border-radius: 40px;
            background: #2d2640;
            color: #f0e6d2;
            outline: none;
        }

        .sms-input-group button {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
            padding: 0.9rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .sms-input-group button:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .profile-info {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2.5rem;
            max-width: 700px;
        }

        .form-group {
            margin-bottom: 1.8rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #cfb087;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 1px solid #3d3452;
            border-radius: 40px;
            background: #2d2640;
            color: #f0e6d2;
            outline: none;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-item {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 40px;
            padding: 1.5rem;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 3rem;
            background: #1e192c;
            border-radius: 60px;
            color: #b3a4cb;
            border: 1px solid #3d3452;
            margin: 2rem 0;
        }

        .empty-state i {
            font-size: 5rem;
            color: #4a3f60;
            margin-bottom: 1.5rem;
        }

        .empty-state a {
            display: inline-block;
            margin-top: 2rem;
            padding: 1rem 3rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
            text-decoration: none;
            border-radius: 40px;
            transition: 0.2s;
        }

        .empty-state a:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        footer {
            background: #0c0818;
            border-top: 1px solid #332b44;
            padding: 4rem 2rem 2rem;
            margin-top: 6rem;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 4rem;
        }

        .footer-section h3 {
            font-size: 1.8rem;
            color: #e3d5f0;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section a {
            text-decoration: none;
            color: #b3a4cb;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 3rem;
            margin-top: 3rem;
            border-top: 1px dashed #3f3655;
            color: #8e7daa;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>Furniverse</h1>
            </div>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php">Home</a></li>
             
                <li><a href="customize.php">Customize</a></li>
                <li><a href="collections.php">Collections</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <li><span class="user-greeting"><i class="fas fa-circle-user"></i> <?php echo htmlspecialchars(getUserName()); ?></span></li>
                    <li><a href="dashboard.php" class="active btn-dashboard"><i class="fas fa-chart-pie"></i>Dashboard</a></li>
                    <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i>Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <header class="page-header">
        <h1>Welcome back, <?php echo htmlspecialchars(getUserName()); ?></h1>
        <p>Your studio · your creations · your space</p>
    </header>

    <div class="container">
        <?php displayMessage(); ?>
        <?php if (isset($sms_success)): ?>
            <div class="alert success"><?php echo $sms_success; ?></div>
        <?php endif; ?>
       

        <div class="dashboard-tabs">
            <button class="tab-btn active" onclick="showTab('customizations', this)"><i class="fas fa-paint-brush"></i>Customizations</button>
            <button class="tab-btn" onclick="showTab('orders', this)"><i class="fas fa-shopping-bag"></i>Orders</button>
            <button class="tab-btn" onclick="showTab('profile', this)"><i class="fas fa-user"></i>Profile</button>
            <button class="tab-btn" onclick="showTab('notifications', this)"><i class="fas fa-bell"></i>Notifications</button>
        </div>

        <!-- Customizations Tab -->
        <div id="customizations" class="tab-content active">
            <h2><i class="fas fa-paint-brush"></i> My customizations</h2>
            <?php if ($customizations && $customizations->num_rows > 0): ?>
                <div class="customizations-grid">
                    <?php while($cust = $customizations->fetch_assoc()): ?>
                    <div class="customization-card">
                        <div class="image-wrapper">
                            <?php 
                            // Check for image in order of priority: customization image_path, product image, or default
                            if (!empty($cust['image_path']) && file_exists($cust['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($cust['image_path']); ?>" alt="Custom design">
                            <?php elseif (!empty($cust['product_image']) && file_exists("images/".$cust['product_image'])): ?>
                                <img src="images/<?php echo htmlspecialchars($cust['product_image']); ?>" alt="<?php echo htmlspecialchars($cust['product_name'] ?? 'Product'); ?>">
                            <?php else: ?>
                                <i class="fas fa-couch"></i>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($cust['product_name'] ?? 'Custom ' . ucfirst($cust['furniture_type'] ?? 'Product')); ?></h3>
                        <?php if (isset($cust['material_name'])): ?>
                        <p><i class="fas fa-tree"></i>Material: <?php echo htmlspecialchars($cust['material_name']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($cust['furniture_type'])): ?>
                        <p><i class="fas fa-chair"></i>Type: <?php echo htmlspecialchars(ucfirst($cust['furniture_type'])); ?></p>
                        <?php endif; ?>
                        <p><i class="fas fa-ruler"></i>Dimensions: <?php echo htmlspecialchars($cust['dimensions'] ?? 'N/A'); ?></p>
                        <p><i class="fas fa-palette"></i>Color: 
                            <span style="display:inline-block; width:20px; height:20px; background:<?php echo htmlspecialchars($cust['color'] ?? '#000000'); ?>; border-radius:50%; margin-left: 5px; border: 2px solid #3d3452; vertical-align: middle;"></span>
                            <?php if (!empty($cust['accent_color'])): ?>
                            <span style="display:inline-block; width:20px; height:20px; background:<?php echo htmlspecialchars($cust['accent_color']); ?>; border-radius:50%; margin-left: 5px; border: 2px solid #3d3452; vertical-align: middle;"></span>
                            <?php endif; ?>
                        </p>
                        <p><i class="fas fa-tag"></i>Price: <?php echo formatCurrency($cust['total_price'] ?? 0); ?></p>
                        <p><i class="fas fa-info-circle"></i>Status: <span class="status <?php echo htmlspecialchars($cust['status'] ?? 'draft'); ?>"><?php echo ucfirst($cust['status'] ?? 'Draft'); ?></span></p>
                        <div class="card-actions">
                            <a href="customize.php?load=<?php echo $cust['customization_id']; ?>" class="btn-small"><i class="fas fa-edit"></i> Edit</a>
                            <?php if(isset($cust['status']) && $cust['status'] == 'saved'): ?>
                                <a href="checkout.php?customization_id=<?php echo $cust['customization_id']; ?>" class="btn-primary"><i class="fas fa-shopping-cart"></i> Order</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-paint-brush"></i>
                    <p>No customizations yet</p>
                    <p>Start designing your perfect piece</p>
                    <a href="customize.php">Begin creating</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content">
            <h2><i class="fas fa-shopping-bag"></i> My orders</h2>
            <?php if ($orders && $orders->num_rows > 0): ?>
                <div class="orders-list">
                    <?php while($order = $orders->fetch_assoc()): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <h3><i class="fas fa-hashtag"></i> Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['order_id']); ?></h3>
                            <span class="order-status <?php echo htmlspecialchars($order['order_status'] ?? 'pending'); ?>"><?php echo ucfirst($order['order_status'] ?? 'Pending'); ?></span>
                        </div>
                        <p><i class="fas fa-couch"></i><?php echo htmlspecialchars($order['product_name'] ?? 'Custom Product'); ?></p>
                        <p><i class="fas fa-calendar"></i><?php echo date('F j, Y', strtotime($order['created_at'] ?? 'now')); ?></p>
                        <p><i class="fas fa-coins"></i><?php echo formatCurrency($order['total_amount'] ?? 0); ?></p>
                        <p><i class="fas fa-credit-card"></i>Payment: <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></p>
                        
                        <form method="POST" class="sms-form">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <div class="sms-input-group">
                                <input type="text" name="message" placeholder="Update via SMS..." required>
                                <button type="submit" name="send_sms"><i class="fas fa-paper-plane"></i> Send</button>
                            </div>
                        </form>
                        
                        <a href="track-order.php?id=<?php echo $order['order_id']; ?>" class="btn-small"><i class="fas fa-map-marker-alt"></i> Track</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>No orders placed</p>
                    <p>Browse our collection and find your piece</p>
                    <a href="products.php">Explore products</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content">
            <h2><i class="fas fa-user"></i> Profile information</h2>
            <?php
            $user = getUserData($user_id);
            ?>
            <div class="profile-info">
                <form method="POST" action="update-profile.php">
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i>Full name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i>Address</label>
                        <textarea name="address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update profile</button>
                </form>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications" class="tab-content">
            <h2><i class="fas fa-bell"></i> SMS notifications</h2>
            <?php
            $notifications = $conn->query("
                SELECT n.*, o.order_number 
                FROM notifications n
                LEFT JOIN orders o ON n.order_id = o.order_id
                WHERE n.user_id = $user_id
                ORDER BY n.sent_at DESC
            ");
            ?>
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <div class="notifications-list">
                    <?php while($notif = $notifications->fetch_assoc()): ?>
                    <div class="notification-item">
                        <div class="notification-header">
                            <span class="notification-date"><i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($notif['sent_at'] ?? 'now')); ?></span>
                            <span class="notification-status <?php echo htmlspecialchars($notif['status'] ?? 'sent'); ?>"><?php echo ucfirst($notif['status'] ?? 'Sent'); ?></span>
                        </div>
                        <p class="notification-message"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
                        <p class="notification-order"><i class="fas fa-hashtag"></i> Order #<?php echo htmlspecialchars($notif['order_number'] ?? $notif['order_id']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell"></i>
                    <p>No notifications</p>
                    <p>SMS updates will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Furniverse atelier</h3>
                <p><i class="fas fa-location-dot"></i> Poblacion, Tupi, South Cotabato</p>
                <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
                <p><i class="fas fa-envelope"></i> studio@furniverse.com</p>
            </div>
            <div class="footer-section">
                <h3>inside</h3>
                <ul>
                    <li><a href="about.php">the studio</a></li>
                    <li><a href="privacy.php">privacy</a></li>
                    <li><a href="terms.php">terms</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Furniverse · designed for the discerning</p>
        </div>
    </footer>

    <script>
        function showTab(tabName, button) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            button.classList.add('active');
            history.pushState(null, null, '#' + tabName);
        }

        window.addEventListener('load', function() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                const tabButton = document.querySelector(`[onclick*="'${hash}'"]`);
                if (tabButton) {
                    showTab(hash, tabButton);
                }
            }
        });
    </script>
</body>
</html>