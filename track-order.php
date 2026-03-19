<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order details - FIXED: Removed the comment inside SQL
$query = "
    SELECT o.*, 
           c.customization_id, 
           c.dimensions, 
           c.color, 
           c.image_path as customization_image,
           p.product_name, 
           p.image as product_image
    FROM orders o
    LEFT JOIN customizations c ON o.customization_id = c.customization_id
    LEFT JOIN products p ON c.product_id = p.product_id
    WHERE o.order_id = ? AND o.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = "Order not found";
    redirect('dashboard.php');
}

// Fetch notifications for this order
$notifications = $conn->query("
    SELECT * FROM notifications 
    WHERE order_id = $order_id 
    ORDER BY sent_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order · Furniverse modern studio</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: structured & elegant -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== DARK PROFESSIONAL BASE ===== */
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
            letter-spacing: -0.01em;
        }

        :root {
            --deep-bg: #0f0b17;
            --surface-dark: #1e192c;
            --surface-medium: #2d2640;
            --accent-gold: #cfb087;
            --accent-blush: #e6b3b3;
            --accent-lavender: #bba6d9;
            --text-light: #f0ecf9;
            --text-soft: #cbc2e6;
            --border-glow: rgba(207, 176, 135, 0.15);
            --card-shadow: 0 25px 40px -15px rgba(0, 0, 0, 0.8);
            --status-pending: #b85c1a;
            --status-processing: #2980b9;
            --status-shipped: #8e44ad;
            --status-delivered: #27ae60;
            --status-cancelled: #c0392b;
        }

        /* ===== NAVBAR — glassmorphism deep ===== */
        .navbar {
            background: rgba(18, 14, 29, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(207, 176, 135, 0.25);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.6);
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
            font-weight: 700;
            background: linear-gradient(135deg, #ece3f0, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
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

        .nav-menu a:not(.btn-register):not(.btn-dashboard):not(.btn-logout)::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 2px;
            transition: width 0.2s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .nav-menu a.active {
            color: #f0e6d2;
            font-weight: 600;
        }

        .btn-register {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
            box-shadow: 0 0 10px rgba(207, 176, 135, 0.1);
        }

        .btn-register:hover {
            background: #cfb087;
            color: #0f0b17 !important;
            border-color: #cfb087;
            box-shadow: 0 0 18px rgba(207, 176, 135, 0.5);
        }

        .btn-dashboard {
            background: #2d2640;
            border: 1.5px solid #6d5a8b;
            color: #e6dbf2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-dashboard:hover {
            background: #3f3260;
            border-color: #bba6d9;
            box-shadow: 0 0 15px #3a2e52;
        }

        .btn-logout {
            background: transparent;
            border: 1.5px solid #68587e;
            color: #c5b8dc !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 500;
            transition: 0.2s;
        }

        .btn-logout:hover {
            background: #3d2e55;
            border-color: #b19cd1;
            color: #fff !important;
        }

        .hamburger {
            display: none;
            font-size: 2rem;
            color: #cfb087;
            cursor: pointer;
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

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #332b44;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2.8rem;
            background: linear-gradient(135deg, #f0e6d2, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .order-badge {
            background: #1e192c;
            border: 1px solid #cfb087;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            color: #f0e6d2;
        }

        .order-badge i {
            color: #cfb087;
            margin-right: 8px;
        }

        /* ===== ALERTS ===== */
        .alert {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 500;
            text-align: center;
            border: 1px solid;
        }

        .alert.success {
            background: #1a2d24;
            color: #b3ffb3;
            border-color: #4a9b6e;
        }

        .alert.error {
            background: #2d1a24;
            color: #ffb3b3;
            border-color: #b84a6e;
        }

        /* ===== TRACKING CONTAINER ===== */
        .tracking-container {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 2rem;
            margin: 2rem 0;
        }

        /* ===== ORDER DETAILS ===== */
        .order-details {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .order-details h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .order-details h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .detail-card {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid #2d2640;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .label {
            color: #b3a4cb;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .value {
            color: #f0e6d2;
            font-weight: 500;
        }

        .value.price {
            color: #cfb087;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .value.status {
            padding: 0.3rem 1.2rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #2d2640;
            border: 1px solid;
        }

        .value.status.pending {
            border-color: var(--status-pending);
            color: #ffb266;
        }

        .value.status.processing {
            border-color: var(--status-processing);
            color: #7fb3ff;
        }

        .value.status.shipped {
            border-color: var(--status-shipped);
            color: #d9b3ff;
        }

        .value.status.delivered {
            border-color: var(--status-delivered);
            color: #7acfa2;
        }

        .value.status.cancelled {
            border-color: var(--status-cancelled);
            color: #ff9999;
        }

        .color-swatch {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 3px solid #3d3452;
            vertical-align: middle;
        }

        /* ===== CUSTOMIZATION IMAGE STYLES ===== */
        .customization-image {
            margin: 1.5rem 0;
            text-align: center;
            background: #161224;
            border-radius: 30px;
            padding: 1.5rem;
            border: 1px solid #332d44;
        }

        .customization-image img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 3px solid #3d3452;
        }

        .customization-image p {
            margin-top: 1rem;
            color: #cfb087;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* ===== TRACKING TIMELINE ===== */
        .tracking-timeline {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .tracking-timeline h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .tracking-timeline h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 15px;
            bottom: 15px;
            width: 2px;
            background: linear-gradient(180deg, #cfb087, #4a3f60);
            border-radius: 2px;
        }

        .timeline-step {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .timeline-step:last-child {
            margin-bottom: 0;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: #1e192c;
            border: 2px solid #3d3452;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #b3a4cb;
            z-index: 2;
            position: relative;
            transition: 0.3s;
        }

        .timeline-step.completed .step-icon {
            background: #1a2d24;
            border-color: #27ae60;
            color: #7acfa2;
        }

        .timeline-step.active .step-icon {
            border-color: #cfb087;
            color: #cfb087;
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        .step-content {
            flex: 1;
            padding-top: 0.5rem;
        }

        .step-content h4 {
            color: #f0e6d2;
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
        }

        .status-message {
            color: #b3a4cb;
            font-size: 0.95rem;
            line-height: 1.6;
            background: #161224;
            padding: 1rem 1.5rem;
            border-radius: 30px;
            margin-top: 0.5rem;
            border: 1px solid #332d44;
        }

        /* ===== NOTIFICATIONS TIMELINE ===== */
        .notifications-timeline {
            grid-column: 1 / -1;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .notifications-timeline h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .notifications-timeline h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .notification-timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-message {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.5rem;
            transition: 0.2s;
        }

        .notification-message:hover {
            border-color: #6b5b85;
            box-shadow: 0 0 0 1px rgba(207, 176, 135, 0.2);
        }

        .notif-time {
            color: #cfb087;
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notif-time i {
            color: #b3a4cb;
        }

        .notif-content {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .notif-content i {
            color: #cfb087;
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }

        .notif-content p {
            color: #f0e6d2;
            font-size: 1rem;
            line-height: 1.6;
            flex: 1;
        }

        .no-notifications {
            text-align: center;
            padding: 3rem;
            color: #8e7daa;
            font-size: 1.1rem;
            background: #161224;
            border-radius: 40px;
            border: 1px dashed #3d3452;
        }

        .no-notifications i {
            font-size: 3rem;
            color: #4a3f60;
            margin-bottom: 1rem;
            display: block;
        }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 3rem;
            justify-content: center;
        }

        .btn {
            display: inline-block;
            background: transparent;
            border: 1.5px solid #5a4a78;
            border-radius: 40px;
            padding: 1rem 2.5rem;
            text-decoration: none;
            font-weight: 500;
            color: #dacfef;
            transition: 0.2s;
            font-size: 1rem;
            text-align: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
            border-color: #cfb087;
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        /* ===== FOOTER — deep elegant ===== */
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
            margin-bottom: 1.2rem;
            color: #e3d5f0;
            font-weight: 600;
        }

        .footer-section p, .footer-section li {
            color: #b3a4cb;
            margin-bottom: 0.7rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section a {
            text-decoration: none;
            color: #b3a4cb;
            border-bottom: 1px dotted #5d4b78;
            transition: 0.2s;
        }

        .footer-section a:hover {
            border-bottom-color: #cfb087;
            color: #cfb087;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 3rem;
            margin-top: 3rem;
            border-top: 1px dashed #3f3655;
            color: #8e7daa;
            font-size: 0.95rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1000px) {
            .tracking-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 950px) {
            .nav-menu {
                position: fixed;
                top: 70px;
                left: -100%;
                background: #130e20f2;
                backdrop-filter: blur(18px);
                width: 100%;
                flex-direction: column;
                padding: 3rem 2rem;
                gap: 2rem;
                box-shadow: 0 50px 60px #00000080;
                transition: left 0.3s ease;
                border-bottom: 1px solid #6d5b86;
            }
            .nav-menu.active {
                left: 0;
            }
            .hamburger {
                display: block;
            }
            .page-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 600px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .action-buttons {
                flex-direction: column;
            }
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
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
                    <li><a href="dashboard.php" class="btn-dashboard"><i class="fas fa-chart-pie" style="margin-right: 5px;"></i>Dashboard</a></li>
                    <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="btn-register"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Register</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger" id="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Track order</h1>
            <div class="order-badge">
                <i class="fas fa-hashtag"></i> #<?php echo $order_id; ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="tracking-container">
            <!-- Order Details -->
            <div class="order-details">
                <h2><i class="fas fa-clipboard-list" style="margin-right: 10px; color:#cfb087;"></i>Order details</h2>
                <div class="detail-card">
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-couch"></i> Product:</span>
                        <span class="value"><?php echo htmlspecialchars($order['product_name'] ?? 'Standard product'); ?></span>
                    </div>
                    <?php if (!empty($order['dimensions'])): ?>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-ruler"></i> Dimensions:</span>
                        <span class="value"><?php echo htmlspecialchars($order['dimensions']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['color'])): ?>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-palette"></i> Color:</span>
                        <span class="value">
                            <span class="color-swatch" style="background: <?php echo htmlspecialchars($order['color']); ?>;"></span>
                            <?php echo htmlspecialchars($order['color']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-calendar"></i> Order date:</span>
                        <span class="value">
                            <?php 
                            if (isset($order['created_at']) && !empty($order['created_at'])) {
                                echo date('F j, Y g:i A', strtotime($order['created_at']));
                            } else {
                                echo 'Date not available';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-coins"></i> Total amount:</span>
                        <span class="value price">₱<?php echo number_format($order['total_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-credit-card"></i> Payment method:</span>
                        <span class="value"><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-credit-card"></i> Payment status:</span>
                        <span class="value status <?php echo $order['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-map-marker-alt"></i> Delivery address:</span>
                        <span class="value"><?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($order['gcash_reference'])): ?>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-receipt"></i> GCash ref:</span>
                        <span class="value"><?php echo htmlspecialchars($order['gcash_reference']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Display Customization Image if exists -->
                    <?php if (!empty($order['customization_image'])): ?>
                    <div class="customization-image">
                        <img src="<?php echo htmlspecialchars($order['customization_image']); ?>" alt="Customized Product Design">
                        <p><i class="fas fa-check-circle" style="color: #27ae60;"></i> Your custom design</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tracking Timeline -->
            <div class="tracking-timeline">
                <h2><i class="fas fa-truck" style="margin-right: 10px; color:#cfb087;"></i>Order status</h2>
                <div class="timeline">
                    <?php
                    $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                    $current_status = $order['order_status'] ?? 'pending';
                    $current_index = array_search($current_status, $statuses);
                    if ($current_index === false) $current_index = -1;
                    
                    foreach ($statuses as $index => $status):
                        $completed = $index <= $current_index;
                        $active = $index == $current_index;
                    ?>
                    <div class="timeline-step <?php echo $completed ? 'completed' : ''; ?> <?php echo $active ? 'active' : ''; ?>">
                        <div class="step-icon">
                            <?php if ($completed): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php else: ?>
                                <i class="far fa-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <h4><?php echo ucfirst($status); ?></h4>
                            <?php if ($active): ?>
                                <div class="status-message">
                                    <i class="fas fa-info-circle" style="margin-right: 8px; color:#cfb087;"></i>
                                    <?php
                                    switch($status) {
                                        case 'pending':
                                            echo "Your order has been placed and is awaiting processing.";
                                            break;
                                        case 'processing':
                                            echo "Your order is being prepared by our craftsmen.";
                                            break;
                                        case 'shipped':
                                            echo "Your order is on the way to your address.";
                                            break;
                                        case 'delivered':
                                            echo "Your order has been delivered. Enjoy your new furniture!";
                                            break;
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($current_status == 'cancelled'): ?>
                    <div class="timeline-step active">
                        <div class="step-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="step-content">
                            <h4>Cancelled</h4>
                            <div class="status-message">
                                <i class="fas fa-info-circle" style="margin-right: 8px; color:#cfb087;"></i>
                                This order has been cancelled.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications Timeline -->
            <div class="notifications-timeline">
                <h2><i class="fas fa-bell" style="margin-right: 10px; color:#cfb087;"></i>SMS updates</h2>
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <div class="notification-timeline">
                        <?php while($notif = $notifications->fetch_assoc()): ?>
                        <div class="notification-message">
                            <div class="notif-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('M j, Y g:i A', strtotime($notif['sent_at'] ?? 'now')); ?>
                            </div>
                            <div class="notif-content">
                                <i class="fas fa-sms"></i>
                                <p><?php echo htmlspecialchars($notif['message'] ?? 'No message'); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p>No SMS notifications yet</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem; color:#8e7daa;">You'll receive updates via SMS as your order progresses</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="action-buttons">
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
            <?php if (isset($order['order_status']) && $order['order_status'] == 'delivered'): ?>
                <a href="rate-order.php?id=<?php echo $order_id; ?>" class="btn btn-primary"><i class="fas fa-star"></i> Rate your order</a>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Furniverse atelier</h3>
                <p><i class="fas fa-location-dot" style="margin-right: 10px; color:#cfb087;"></i> Poblacion, Tupi, South Cotabato</p>
                <p><i class="fas fa-phone" style="margin-right: 10px; color:#cfb087;"></i> +63 912 345 6789</p>
                <p><i class="fas fa-envelope" style="margin-right: 10px; color:#cfb087;"></i> studio@furniverse.com</p>
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
        // Hamburger menu toggle
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('navMenu');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>