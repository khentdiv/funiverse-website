<?php
require_once 'config.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to checkout";
    redirect('login.php');
}

$customization_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID in URL, try to get from customization_id parameter
if ($customization_id == 0 && isset($_GET['customization_id'])) {
    $customization_id = (int)$_GET['customization_id'];
}

if ($customization_id == 0) {
    $_SESSION['error'] = "No customization selected";
    redirect('customize.php');
}

// Fetch customization details with image
$query = "
    SELECT c.*, u.full_name, u.email, u.phone, u.address 
    FROM customizations c
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE c.customization_id = ? AND c.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $customization_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$customization = $result->fetch_assoc();

if (!$customization) {
    $_SESSION['error'] = "Customization not found";
    redirect('customize.php');
}

// Parse dimensions
$dimensions = explode('x', $customization['dimensions']);
$width = $dimensions[0] ?? 100;
$depth = $dimensions[1] ?? 50;
$height = $dimensions[2] ?? 75;

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $payment_method = sanitize($_POST['payment_method']);
    $delivery_address = sanitize($_POST['delivery_address']);
    $gcash_reference = isset($_POST['gcash_reference']) ? sanitize($_POST['gcash_reference']) : null;
    
    // Generate unique order number with timestamp and random string
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8)) . '-' . mt_rand(1000, 9999);
    
    // Set payment status based on method
    $payment_status = ($payment_method == 'cod') ? 'pending' : 'processing';
    
    // Check if orders table exists and has correct structure
    $check_table = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($check_table->num_rows == 0) {
        // Create orders table
        $create_orders = "CREATE TABLE IF NOT EXISTS orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            customization_id INT NULL,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_status VARCHAR(50) DEFAULT 'pending',
            order_status VARCHAR(50) DEFAULT 'pending',
            delivery_address TEXT NOT NULL,
            gcash_reference VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (customization_id) REFERENCES customizations(customization_id) ON DELETE SET NULL
        )";
        $conn->query($create_orders);
    }
    
    // Check if notifications table exists
    $check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($check_notif->num_rows == 0) {
        // Create notifications table
        $create_notif = "CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_id INT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )";
        $conn->query($create_notif);
    }
    
    // Insert order
    $query = "INSERT INTO orders (user_id, customization_id, order_number, total_amount, payment_method, payment_status, order_status, delivery_address, gcash_reference, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisdssss", 
        $_SESSION['user_id'], 
        $customization_id, 
        $order_number,
        $customization['total_price'], 
        $payment_method, 
        $payment_status, 
        $delivery_address, 
        $gcash_reference
    );
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Update customization status
        $conn->query("UPDATE customizations SET status = 'ordered' WHERE customization_id = $customization_id");
        
        // Send notification
        $message = "Your order #$order_number has been placed successfully. Thank you for shopping with Furniverse!";
        $notif_query = "INSERT INTO notifications (user_id, order_id, message, status, sent_at) VALUES (?, ?, ?, 'unread', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("iis", $_SESSION['user_id'], $order_id, $message);
        $notif_stmt->execute();
        
        $_SESSION['success'] = "Order placed successfully! Order number: $order_number";
        
        // Check if track-order.php exists, if not redirect to dashboard
        $track_file = 'track-order.php';
        if (file_exists($track_file)) {
            redirect("$track_file?id=$order_id");
        } else {
            redirect("dashboard.php?order=success&number=" . urlencode($order_number));
        }
    } else {
        $error = "Failed to place order. Please try again: " . $conn->error;
    }
}

// Get product name for display
$product_name = "Custom " . ucfirst($customization['furniture_type'] ?? 'Furniture Piece');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout · Furniverse modern studio</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
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
        }

        /* ===== NAVBAR ===== */
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
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 3rem;
            background: linear-gradient(135deg, #f0e6d2, #cfb087, #bba6d9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .page-header p {
            color: #cbc2e6;
            font-size: 1.2rem;
            font-weight: 300;
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

        .alert.error {
            background: #2d1a24;
            color: #ffb3b3;
            border-color: #b84a6e;
        }

        .alert.success {
            background: #1a2d24;
            color: #b3ffb3;
            border-color: #4a9b6e;
        }

        /* ===== CHECKOUT GRID ===== */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2rem;
            margin: 2rem 0;
        }

        /* ===== CHECKOUT CARD ===== */
        .checkout-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .checkout-card h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .checkout-card h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .checkout-card h2 i {
            color: #cfb087;
            margin-right: 10px;
        }

        /* ===== CUSTOMIZED IMAGE PREVIEW ===== */
        .customized-preview {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 36px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .preview-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.2rem;
            color: #f0e6d2;
            font-size: 1.1rem;
        }

        .preview-title i {
            color: #cfb087;
        }

        .image-container {
            width: 100%;
            height: 250px;
            background: #0f0b17;
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #332d44;
        }

        .customized-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .customization-badge {
            display: inline-block;
            background: #2d2640;
            border: 1px solid #49405f;
            border-radius: 30px;
            padding: 0.3rem 1rem;
            margin-top: 0.8rem;
            font-size: 0.85rem;
            color: #cfb087;
        }

        .customization-badge i {
            margin-right: 5px;
        }

        /* ===== PRODUCT SUMMARY ===== */
        .product-summary {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 36px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #2d2640;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #b3a4cb;
            font-size: 1rem;
        }

        .summary-value {
            color: #f0e6d2;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .summary-value.total {
            color: #cfb087;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .product-details {
            background: #1e192c;
            border-radius: 30px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .product-detail-item {
            display: flex;
            gap: 1rem;
            padding: 0.5rem 0;
        }

        .product-detail-item i {
            color: #cfb087;
            width: 24px;
        }

        .product-detail-item span {
            color: #b3a4cb;
        }

        .product-detail-item strong {
            color: #f0e6d2;
            font-weight: 600;
        }

        /* ===== PAYMENT METHODS ===== */
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .payment-option {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.5rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .payment-option:hover {
            border-color: #6b5b85;
        }

        .payment-option.selected {
            border-color: #cfb087;
            box-shadow: 0 0 0 1px rgba(207, 176, 135, 0.3);
        }

        .payment-option input[type="radio"] {
            display: none;
        }

        .payment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-icon {
            width: 50px;
            height: 50px;
            background: #2d2640;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #cfb087;
        }

        .payment-info {
            flex: 1;
        }

        .payment-info h3 {
            color: #f0e6d2;
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }

        .payment-info p {
            color: #b3a4cb;
            font-size: 0.9rem;
        }

        .payment-badge {
            background: #1a2d24;
            color: #7acfa2;
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* ===== GCASH SECTION ===== */
        .gcash-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #2d2640;
        }

        .gcash-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .gcash-qr {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }

        .gcash-qr h4 {
            color: #f0e6d2;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 20px;
            position: relative;
            min-height: 170px;
        }

        #qrcode canvas {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 100%;
            height: auto;
        }

        .qr-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            pointer-events: none;
            border: 2px solid #cfb087;
            z-index: 10;
        }

        .qr-logo i {
            color: #cfb087;
            font-size: 1.8rem;
        }

        .gcash-amount {
            color: #cfb087;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 1rem;
        }

        .gcash-info {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.5rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 0;
            border-bottom: 1px solid #2d2640;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row i {
            color: #cfb087;
            width: 24px;
        }

        .info-row span {
            color: #b3a4cb;
        }

        .info-row strong {
            color: #f0e6d2;
            margin-left: auto;
        }

        .gcash-reference {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.2rem;
        }

        .gcash-reference input {
            width: 100%;
            padding: 1rem 1.5rem;
            background: #2d2640;
            border: 1px solid #49405f;
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            outline: none;
            transition: 0.2s;
        }

        .gcash-reference input:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.2);
        }

        .gcash-reference input::placeholder {
            color: #8e7daa;
        }

        /* ===== DELIVERY ADDRESS ===== */
        .address-section {
            margin: 2rem 0;
        }

        .address-section label {
            display: block;
            color: #f0e6d2;
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .address-section textarea {
            width: 100%;
            padding: 1.2rem 1.5rem;
            background: #2d2640;
            border: 1px solid #49405f;
            border-radius: 30px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            min-height: 120px;
            resize: vertical;
            outline: none;
            transition: 0.2s;
        }

        .address-section textarea:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.2);
        }

        .use-default {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.8rem;
            color: #b3a4cb;
            cursor: pointer;
        }

        .use-default input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #cfb087;
        }

        /* ===== ORDER SUMMARY ===== */
        .order-summary {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
            position: sticky;
            top: 100px;
        }

        .order-summary h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .order-summary h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #2d2640;
        }

        .summary-row:last-of-type {
            border-bottom: none;
        }

        .summary-row.total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px dashed #4a3f60;
        }

        .summary-row.total .summary-value {
            color: #cfb087;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .order-items {
            background: #161224;
            border-radius: 30px;
            padding: 1.2rem;
            margin: 1.5rem 0;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: #2d2640;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image i {
            font-size: 2rem;
            color: #cfb087;
        }

        .item-details {
            flex: 1;
        }

        .item-details h4 {
            color: #f0e6d2;
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
        }

        .item-details p {
            color: #b3a4cb;
            font-size: 0.9rem;
        }

        .item-dimensions {
            display: flex;
            gap: 1rem;
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #8e7daa;
        }

        .item-dimensions span {
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-block;
            background: transparent;
            border: 1.5px solid #5a4a78;
            border-radius: 40px;
            padding: 1rem 2rem;
            text-decoration: none;
            font-weight: 500;
            color: #dacfef;
            transition: 0.2s;
            font-size: 1rem;
            text-align: center;
            cursor: pointer;
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
            flex: 2;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
            border-color: #cfb087;
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        .btn-large {
            flex: 1;
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* ===== FOOTER ===== */
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
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .gcash-details {
                grid-template-columns: 1fr;
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
            .page-header h1 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 600px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .action-buttons {
                flex-direction: column;
            }
            .payment-header {
                flex-wrap: wrap;
            }
        }

        /* Hidden class for conditional display */
        .hidden {
            display: none;
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
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <li><span class="user-greeting"><i class="fas fa-circle-user" style="margin-right: 5px;"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></li>
                    <?php endif; ?>
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
            <h1>Complete your order</h1>
            <p>Review your customized piece and choose payment method</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="checkout-form">
            <div class="checkout-grid">
                <!-- Left Column - Customized Image, Payment & Details -->
                <div class="checkout-card">
                    <!-- Customized Image Preview -->
                    <div class="customized-preview">
                        <div class="preview-title">
                            <i class="fas fa-paint-brush"></i>
                            <span>Your customized design</span>
                        </div>
                        
                        <div class="image-container">
                            <?php if (!empty($customization['image_path'])): ?>
                                <img src="<?php echo $customization['image_path']; ?>" alt="Customized Furniture" class="customized-image">
                            <?php else: ?>
                                <i class="fas fa-couch" style="font-size: 5rem; color: <?php echo $customization['color']; ?>;"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="customization-badge">
                            <i class="fas fa-check-circle"></i> 
                            Customized: <?php echo htmlspecialchars($customization['color']); ?> · <?php echo $width; ?>×<?php echo $depth; ?>×<?php echo $height; ?>cm
                        </div>
                    </div>

                    <h2><i class="fas fa-credit-card"></i>Payment method</h2>
                    
                    <!-- Payment Methods -->
                    <div class="payment-methods">
                        <!-- GCash Option -->
                        <label class="payment-option" id="gcash-option">
                            <input type="radio" name="payment_method" value="gcash" checked>
                            <div class="payment-header">
                                <div class="payment-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="payment-info">
                                    <h3>GCash</h3>
                                    <p>Pay instantly via GCash</p>
                                </div>
                                <span class="payment-badge">Fast & secure</span>
                            </div>
                            
                            <!-- GCash Details (shown when selected) -->
                            <div class="gcash-section" id="gcash-details">
                                <div class="gcash-details">
                                    <div class="gcash-qr">
                                        <h4>Scan to pay</h4>
                                        <div id="qrcode">
                                            <!-- QR code will be generated here -->
                                        </div>
                                        <div class="qr-logo">
                                            <i class="fas fa-couch"></i>
                                        </div>
                                        <p class="gcash-amount">₱<?php echo number_format($customization['total_price'], 2); ?></p>
                                    </div>
                                    <div class="gcash-info">
                                        <div class="info-row">
                                            <i class="fas fa-user"></i>
                                            <span>Account name:</span>
                                            <strong>Furniverse Studio</strong>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-mobile-alt"></i>
                                            <span>GCash number:</span>
                                            <strong>0912 345 6789</strong>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-clock"></i>
                                            <span>Payment expires:</span>
                                            <strong>15 minutes</strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="gcash-reference">
                                    <input type="text" name="gcash_reference" id="gcash_reference" placeholder="Enter GCash reference number (e.g., GC123456789)" pattern="[A-Za-z0-9]{8,20}" title="Please enter a valid reference number (8-20 alphanumeric characters)">
                                </div>
                                <p style="color: #b3a4cb; font-size: 0.85rem; margin-top: 0.5rem;">
                                    <i class="fas fa-info-circle" style="color: #cfb087;"></i> 
                                    Enter the reference number from your GCash transaction
                                </p>
                            </div>
                        </label>

                        <!-- Cash on Delivery Option -->
                        <label class="payment-option" id="cod-option">
                            <input type="radio" name="payment_method" value="cod">
                            <div class="payment-header">
                                <div class="payment-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="payment-info">
                                    <h3>Cash on Delivery</h3>
                                    <p>Pay when you receive your order</p>
                                </div>
                                <span class="payment-badge">No extra fees</span>
                            </div>
                            
                            <!-- COD Details (shown when selected) -->
                            <div class="gcash-section hidden" id="cod-details">
                                <div style="background: #1e192c; border: 1px solid #332d44; border-radius: 30px; padding: 1.5rem; text-align: center;">
                                    <i class="fas fa-truck" style="font-size: 3rem; color: #cfb087; margin-bottom: 1rem;"></i>
                                    <h4 style="color: #f0e6d2; margin-bottom: 0.5rem;">Cash on Delivery</h4>
                                    <p style="color: #b3a4cb;">Pay exactly <strong style="color: #cfb087;">₱<?php echo number_format($customization['total_price'], 2); ?></strong> when your order arrives</p>
                                    <p style="color: #8e7daa; font-size: 0.9rem; margin-top: 1rem;">
                                        <i class="fas fa-check-circle" style="color: #7acfa2;"></i> 
                                        No additional fees for COD
                                    </p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Delivery Address -->
                    <div class="address-section">
                        <label><i class="fas fa-map-marker-alt" style="color: #cfb087; margin-right: 8px;"></i>Delivery address</label>
                        <textarea name="delivery_address" id="delivery_address" placeholder="Enter your complete delivery address" required><?php echo htmlspecialchars($customization['address'] ?? ''); ?></textarea>
                        
                        <label class="use-default">
                            <input type="checkbox" id="use_default" checked>
                            <i class="fas fa-check-circle" style="color: #cfb087;"></i>
                            Use my default address
                        </label>
                    </div>

                    <!-- Product Summary (Mobile view) -->
                    <div class="product-summary" style="display: none;" id="mobile-summary">
                        <h3 style="color: #f0e6d2; margin-bottom: 1rem;">Order summary</h3>
                        <div class="order-item">
                            <div class="item-image">
                                <?php if (!empty($customization['image_path'])): ?>
                                    <img src="<?php echo $customization['image_path']; ?>" alt="Customized piece" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-couch"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($product_name); ?></h4>
                                <p>Customized: <?php echo htmlspecialchars($customization['color']); ?></p>
                                <div class="item-dimensions">
                                    <span><i class="fas fa-arrows-alt-h"></i> <?php echo $width; ?>cm</span>
                                    <span><i class="fas fa-arrows-alt-v"></i> <?php echo $height; ?>cm</span>
                                    <span><i class="fas fa-arrows-alt"></i> <?php echo $depth; ?>cm</span>
                                </div>
                            </div>
                            <div style="color: #cfb087; font-weight: 700;">₱<?php echo number_format($customization['total_price'], 2); ?></div>
                        </div>
                        <div class="summary-row total">
                            <span class="summary-label">Total</span>
                            <span class="summary-value total">₱<?php echo number_format($customization['total_price'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" name="place_order" class="btn btn-primary btn-large" id="place-order-btn">
                            <i class="fas fa-check-circle"></i> Place order
                        </button>
                        <a href="customize.php" class="btn btn-large">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <!-- Right Column - Order Summary with Customized Image -->
                <div class="order-summary" id="order-summary">
                    <h2><i class="fas fa-shopping-bag"></i>Your order</h2>
                    
                    <div class="order-items">
                        <div class="order-item">
                            <div class="item-image">
                                <?php if (!empty($customization['image_path'])): ?>
                                    <img src="<?php echo $customization['image_path']; ?>" alt="Customized Furniture">
                                <?php else: ?>
                                    <i class="fas fa-couch"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($product_name); ?></h4>
                                <p>Customized: <span style="display: inline-block; width: 16px; height: 16px; background: <?php echo $customization['color']; ?>; border-radius: 50%; margin-left: 5px; border: 2px solid #3d3452; vertical-align: middle;"></span> <?php echo htmlspecialchars($customization['color']); ?></p>
                                <div class="item-dimensions">
                                    <span><i class="fas fa-arrows-alt-h"></i> <?php echo $width; ?>cm</span>
                                    <span><i class="fas fa-arrows-alt-v"></i> <?php echo $height; ?>cm</span>
                                    <span><i class="fas fa-arrows-alt"></i> <?php echo $depth; ?>cm</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">₱<?php echo number_format($customization['total_price'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Shipping</span>
                        <span class="summary-value">Free</span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">Total</span>
                        <span class="summary-value total">₱<?php echo number_format($customization['total_price'], 2); ?></span>
                    </div>

                    <!-- Customization Details -->
                    <div style="margin-top: 2rem; background: #161224; border-radius: 30px; padding: 1.2rem;">
                        <p style="color: #b3a4cb; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-paint-roller" style="color: #cfb087;"></i>
                            <strong style="color: #f0e6d2;">Customization details:</strong>
                        </p>
                        <ul style="list-style: none; color: #b3a4cb; font-size: 0.9rem;">
                            <li style="margin-bottom: 0.4rem;">• Dimensions: <?php echo $width; ?>cm × <?php echo $depth; ?>cm × <?php echo $height; ?>cm</li>
                            <li style="margin-bottom: 0.4rem;">• Color: <span style="display: inline-block; width: 16px; height: 16px; background: <?php echo $customization['color']; ?>; border-radius: 50%; margin-left: 5px; border: 2px solid #3d3452; vertical-align: middle;"></span> <?php echo htmlspecialchars($customization['color']); ?></li>
                            <li style="margin-bottom: 0.4rem;">• Material: <?php echo htmlspecialchars($customization['material']); ?></li>
                            <li style="margin-bottom: 0.4rem;">• Finish: <?php echo htmlspecialchars($customization['finish']); ?></li>
                            <?php if (!empty($customization['style'])): ?>
                            <li style="margin-bottom: 0.4rem;">• Style: <?php echo htmlspecialchars($customization['style']); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div style="margin-top: 2rem; padding: 1rem; background: #161224; border-radius: 30px;">
                        <p style="color: #b3a4cb; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-shield-alt" style="color: #cfb087;"></i>
                            Secure checkout powered by Furniverse
                        </p>
                    </div>
                </div>
            </div>
        </form>
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

        // Payment method switching
        const gcashOption = document.getElementById('gcash-option');
        const codOption = document.getElementById('cod-option');
        const gcashDetails = document.getElementById('gcash-details');
        const codDetails = document.getElementById('cod-details');
        const gcashRadio = document.querySelector('input[value="gcash"]');
        const codRadio = document.querySelector('input[value="cod"]');
        const gcashReference = document.getElementById('gcash_reference');

        function togglePaymentMethods() {
            if (gcashRadio.checked) {
                gcashOption.classList.add('selected');
                codOption.classList.remove('selected');
                gcashDetails.classList.remove('hidden');
                codDetails.classList.add('hidden');
                if (gcashReference) gcashReference.required = true;
            } else {
                codOption.classList.add('selected');
                gcashOption.classList.remove('selected');
                codDetails.classList.remove('hidden');
                gcashDetails.classList.add('hidden');
                if (gcashReference) gcashReference.required = false;
            }
        }

        gcashRadio.addEventListener('change', togglePaymentMethods);
        codRadio.addEventListener('change', togglePaymentMethods);
        
        // Initial state
        togglePaymentMethods();

        // Generate QR Code with custom logo
        const totalAmount = <?php echo $customization['total_price']; ?>;
        const orderRef = 'FURN-' + Math.random().toString(36).substring(2, 8).toUpperCase();
        const qrData = `GCASH-PAYMENT: Furniverse\nAmount: ₱${totalAmount}\nAccount: 09123456789\nOrder: ${orderRef}`;

        // Clear the QR code container
        const qrContainer = document.getElementById("qrcode");
        qrContainer.innerHTML = '';

        // Create a canvas element
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Set canvas size
        const size = 200;
        canvas.width = size;
        canvas.height = size;

        // Generate QR code using qrcode-generator library
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
        script.onload = function() {
            // Generate QR code
            const qr = qrcode(0, 'M');
            qr.addData(qrData);
            qr.make();

            // Get QR code matrix
            const moduleCount = qr.getModuleCount();
            const cellSize = size / moduleCount;

            // Draw QR code background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, size, size);
            
            // Draw QR code modules
            ctx.fillStyle = '#000000';
            for (let row = 0; row < moduleCount; row++) {
                for (let col = 0; col < moduleCount; col++) {
                    if (qr.isDark(row, col)) {
                        ctx.fillRect(
                            col * cellSize,
                            row * cellSize,
                            cellSize - 0.5, // Small gap for cleaner look
                            cellSize - 0.5
                        );
                    }
                }
            }

            // Add white background for the logo (to ensure QR code remains scannable)
            const logoSize = size * 0.2; // Logo takes 20% of QR code size
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(size/2, size/2, logoSize/2 + 5, 0, 2 * Math.PI);
            ctx.fill();
            
            // Add a gold border
            ctx.strokeStyle = '#cfb087';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(size/2, size/2, logoSize/2 + 2, 0, 2 * Math.PI);
            ctx.stroke();

            // Add Furniverse logo text in the center
            ctx.fillStyle = '#cfb087';
            ctx.font = 'bold 20px "Playfair Display", serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('F', size/2, size/2 - 5);
            ctx.font = 'bold 10px "Inter", sans-serif';
            ctx.fillText('Furniverse', size/2, size/2 + 15);
            
            // Append canvas to container
            qrContainer.appendChild(canvas);
        };

        document.head.appendChild(script);

        // Address auto-fill
        const defaultAddress = `<?php echo addslashes($customization['address'] ?? ''); ?>`;
        const addressTextarea = document.getElementById('delivery_address');
        const useDefaultCheckbox = document.getElementById('use_default');

        useDefaultCheckbox.addEventListener('change', function() {
            if (this.checked) {
                addressTextarea.value = defaultAddress;
                addressTextarea.readOnly = true;
                addressTextarea.style.backgroundColor = '#1e192c';
            } else {
                addressTextarea.value = '';
                addressTextarea.readOnly = false;
                addressTextarea.style.backgroundColor = '#2d2640';
                addressTextarea.focus();
            }
        });

        // Initial address state
        if (useDefaultCheckbox.checked && defaultAddress) {
            addressTextarea.value = defaultAddress;
            addressTextarea.readOnly = true;
            addressTextarea.style.backgroundColor = '#1e192c';
        }

        // Form validation
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            if (gcashRadio.checked) {
                const ref = gcashReference.value.trim();
                if (!ref) {
                    e.preventDefault();
                    alert('Please enter your GCash reference number');
                    gcashReference.focus();
                } else if (!/^[A-Za-z0-9]{8,20}$/.test(ref)) {
                    e.preventDefault();
                    alert('Please enter a valid reference number (8-20 alphanumeric characters)');
                    gcashReference.focus();
                }
            }
            
            const address = addressTextarea.value.trim();
            if (!address) {
                e.preventDefault();
                alert('Please enter your delivery address');
                addressTextarea.focus();
            }
        });

        // Responsive summary
        function checkScreenSize() {
            if (window.innerWidth <= 1000) {
                document.getElementById('mobile-summary').style.display = 'block';
                document.getElementById('order-summary').style.display = 'none';
            } else {
                document.getElementById('mobile-summary').style.display = 'none';
                document.getElementById('order-summary').style.display = 'block';
            }
        }

        window.addEventListener('resize', checkScreenSize);
        checkScreenSize();

        // Place order button animation
        const placeOrderBtn = document.getElementById('place-order-btn');
        placeOrderBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    </script>
</body>
</html>