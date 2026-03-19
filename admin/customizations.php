<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Define upload directory constant
define('UPLOAD_DIR', '../uploads/customizations/');
define('UPLOAD_URL', '/funiverses/uploads/customizations/'); // Adjusted to your path

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Function to get correct image URL
function getImageUrl($filename) {
    if (empty($filename)) {
        return null;
    }
    
    // Build full server path
    $server_path = UPLOAD_DIR . $filename;
    
    // Check if file exists
    if (file_exists($server_path)) {
        return UPLOAD_URL . $filename;
    }
    
    return null;
}

// Function to safely get array values
function safe_get($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

// First, let's check what columns exist in the customizations table
$customization_columns = [];
$check_table = $conn->query("SHOW TABLES LIKE 'customizations'");

if ($check_table && $check_table->num_rows > 0) {
    // Get existing columns
    $columns = $conn->query("SHOW COLUMNS FROM customizations");
    while ($column = $columns->fetch_assoc()) {
        $customization_columns[$column['Field']] = true;
    }
    
    // Check if we need to add missing columns
    $columns_to_add = [];
    
    // Check for image columns
    if (!isset($customization_columns['customization_image'])) {
        $columns_to_add[] = "ADD COLUMN customization_image VARCHAR(255) DEFAULT NULL";
    }
    
    if (!isset($customization_columns['reference_images'])) {
        $columns_to_add[] = "ADD COLUMN reference_images TEXT DEFAULT NULL";
    }
    
    // Check for material_id
    if (!isset($customization_columns['material_id'])) {
        $columns_to_add[] = "ADD COLUMN material_id INT DEFAULT NULL";
    }
    
    // Check for furniture_type
    if (!isset($customization_columns['furniture_type'])) {
        $columns_to_add[] = "ADD COLUMN furniture_type VARCHAR(100) DEFAULT NULL";
    }
    
    // Check for dimensions
    if (!isset($customization_columns['dimensions'])) {
        $columns_to_add[] = "ADD COLUMN dimensions VARCHAR(255) DEFAULT NULL";
    }
    
    // Check for color
    if (!isset($customization_columns['color'])) {
        $columns_to_add[] = "ADD COLUMN color VARCHAR(50) DEFAULT NULL";
    }
    
    // Check for accent_color
    if (!isset($customization_columns['accent_color'])) {
        $columns_to_add[] = "ADD COLUMN accent_color VARCHAR(50) DEFAULT NULL";
    }
    
    // Check for total_price
    if (!isset($customization_columns['total_price'])) {
        $columns_to_add[] = "ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0.00";
    }
    
    // Add any missing columns - do this one by one to avoid errors
    if (!empty($columns_to_add)) {
        foreach ($columns_to_add as $column_sql) {
            $single_query = "ALTER TABLE customizations " . $column_sql;
            $result = @$conn->query($single_query);
            if (!$result) {
                error_log("Error adding column: " . $conn->error);
            }
        }
        
        // Refresh column list
        $columns = $conn->query("SHOW COLUMNS FROM customizations");
        $customization_columns = [];
        while ($column = $columns->fetch_assoc()) {
            $customization_columns[$column['Field']] = true;
        }
    }
} else {
    // Create customizations table if it doesn't exist with all necessary columns
    $create_table = "CREATE TABLE IF NOT EXISTS customizations (
        customization_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        product_id INT,
        material_id INT DEFAULT NULL,
        furniture_type VARCHAR(100) DEFAULT NULL,
        dimensions VARCHAR(255) DEFAULT NULL,
        color VARCHAR(50) DEFAULT NULL,
        accent_color VARCHAR(50) DEFAULT NULL,
        quantity INT DEFAULT 1,
        total_price DECIMAL(10,2) DEFAULT 0.00,
        customization_image VARCHAR(255) DEFAULT NULL,
        reference_images TEXT DEFAULT NULL,
        specifications TEXT,
        special_instructions TEXT,
        admin_notes TEXT,
        status ENUM('draft', 'saved', 'pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
        FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE SET NULL
    )";
    
    $conn->query($create_table);
    
    // Get columns after creation
    $columns = $conn->query("SHOW COLUMNS FROM customizations");
    while ($column = $columns->fetch_assoc()) {
        $customization_columns[$column['Field']] = true;
    }
}

// Determine the price column in products table
$price_column = 'base_price';
$check_price = $conn->query("SHOW COLUMNS FROM products LIKE 'price'");
if ($check_price && $check_price->num_rows > 0) {
    $price_column = 'price';
} else {
    $check_base_price = $conn->query("SHOW COLUMNS FROM products LIKE 'base_price'");
    if ($check_base_price && $check_base_price->num_rows > 0) {
        $price_column = 'base_price';
    }
}

// Handle Update Status
if (isset($_POST['update_status'])) {
    $customization_id = intval($_POST['customization_id']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $update_fields = ["status = '$status'"];
    
    if (isset($customization_columns['updated_at'])) {
        $update_fields[] = "updated_at = NOW()";
    }
    
    if (isset($customization_columns['admin_notes']) && isset($_POST['admin_notes'])) {
        $admin_notes = $conn->real_escape_string($_POST['admin_notes']);
        $update_fields[] = "admin_notes = '$admin_notes'";
    }
    
    $query = "UPDATE customizations SET " . implode(", ", $update_fields) . " WHERE customization_id = $customization_id";
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Customization status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating status: " . $conn->error;
    }
    redirect('customizations.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $customization_id = intval($_GET['delete']);
    
    // First get the image filename to delete from server
    $result = $conn->query("SELECT customization_image FROM customizations WHERE customization_id = $customization_id");
    if ($result && $result->num_rows > 0) {
        $image = $result->fetch_assoc();
        if (!empty($image['customization_image'])) {
            $image_path = UPLOAD_DIR . $image['customization_image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
    
    $conn->query("DELETE FROM customizations WHERE customization_id = $customization_id");
    $_SESSION['success'] = "Customization request deleted";
    redirect('customizations.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build the main query
$query = "SELECT c.*, u.full_name, u.email, u.phone, 
                 p.product_name, p.$price_column as price,
                 m.material_name
          FROM customizations c
          LEFT JOIN users u ON c.user_id = u.user_id
          LEFT JOIN products p ON c.product_id = p.product_id
          LEFT JOIN materials m ON c.material_id = m.material_id
          WHERE 1=1";

if ($status_filter != 'all' && isset($customization_columns['status'])) {
    $query .= " AND c.status = '$status_filter'";
}

$query .= " ORDER BY c.created_at DESC";

$customizations = $conn->query($query);

// Get statistics
$stats = [
    'all' => 0,
    'draft' => 0,
    'saved' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];

if (isset($customization_columns['status'])) {
    $stats['all'] = $conn->query("SELECT COUNT(*) as count FROM customizations")->fetch_assoc()['count'] ?? 0;
    $stats['draft'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'draft'")->fetch_assoc()['count'] ?? 0;
    $stats['saved'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'saved'")->fetch_assoc()['count'] ?? 0;
    $stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
    $stats['in_progress'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'in_progress'")->fetch_assoc()['count'] ?? 0;
    $stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'completed'")->fetch_assoc()['count'] ?? 0;
    $stats['cancelled'] = $conn->query("SELECT COUNT(*) as count FROM customizations WHERE status = 'cancelled'")->fetch_assoc()['count'] ?? 0;
}

// Get customization for viewing if ID is provided
$view_customization = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    
    $result = $conn->query("
        SELECT c.*, u.full_name, u.email, u.phone, u.address, 
               p.product_name, p.$price_column as price,
               m.material_name
        FROM customizations c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN products p ON c.product_id = p.product_id
        LEFT JOIN materials m ON c.material_id = m.material_id
        WHERE c.customization_id = $view_id
    ");
    
    if ($result && $result->num_rows > 0) {
        $view_customization = $result->fetch_assoc();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customizations - Furniverse Admin</title>
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

        .success-message {
            background: #1a2d24;
            color: #b3ffb3;
            padding: 1rem 2rem;
            border-radius: 60px;
            margin-bottom: 2rem;
            border-left: 4px solid #4a9b6e;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
            text-align: center;
            transition: 0.3s;
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
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #f0e6d2;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .filter-btn {
            padding: 0.6rem 1.5rem;
            border: 1px solid #3d3452;
            border-radius: 40px;
            background: transparent;
            color: #b2a6ca;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .filter-btn:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .filter-btn.active {
            background: #cfb087;
            border-color: #cfb087;
            color: #0f0b17;
        }

        /* Customizations Grid */
        .customizations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .customization-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            overflow: hidden;
            transition: 0.3s;
        }

        .customization-card:hover {
            transform: translateY(-8px);
            border-color: #6b5b85;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .card-header {
            padding: 1.5rem 1.5rem 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header strong {
            font-size: 1.1rem;
            color: #b2a6ca;
        }

        .status-badge {
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.draft { background: #2d2640; color: #b3a4cb; }
        .status-badge.saved { background: #1a2d3a; color: #8bb9ff; }
        .status-badge.pending { background: #2d2a1a; color: #ffd966; }
        .status-badge.in_progress { background: #1a2d3a; color: #8bb9ff; }
        .status-badge.completed { background: #1a2d24; color: #7acfa2; }
        .status-badge.cancelled { background: #2d1a24; color: #ffb3b3; }

        .image-wrapper {
            height: 240px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 1.5rem 1.5rem;
            border-radius: 32px;
            cursor: pointer;
        }

        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .image-wrapper:hover img {
            transform: scale(1.05);
        }

        .image-wrapper i {
            font-size: 4rem;
            color: #4a3f60;
        }

        .card-body {
            padding: 0 1.5rem 1.5rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            color: #b2a6ca;
        }

        .info-row i {
            width: 20px;
            color: #cfb087;
        }

        .info-label {
            font-size: 0.9rem;
            min-width: 80px;
        }

        .info-value {
            color: #f0e6d2;
            font-weight: 500;
        }

        .specs-preview {
            background: #161224;
            padding: 1rem;
            border-radius: 24px;
            margin: 1rem 0;
            max-height: 100px;
            overflow-y: auto;
        }

        .specs-preview .detail-item {
            font-size: 0.85rem;
            color: #b2a6ca;
            margin-bottom: 0.3rem;
        }

        .specs-preview .detail-item strong {
            color: #cfb087;
            margin-right: 0.5rem;
        }

        .card-footer {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            background: #161224;
        }

        .btn-view {
            flex: 1;
            padding: 0.8rem;
            background: transparent;
            border: 1px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
        }

        .btn-view:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .btn-delete {
            padding: 0.8rem 1.2rem;
            background: transparent;
            border: 1px solid #b84a6e;
            border-radius: 40px;
            color: #ffb3b3;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-delete:hover {
            background: #b84a6e;
            color: #fff;
        }

        /* Detail View */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .action-bar h2 {
            font-size: 2rem;
            color: #f0e6d2;
        }

        .back-link {
            padding: 0.8rem 2rem;
            background: transparent;
            border: 1px solid #3d3452;
            border-radius: 40px;
            color: #b2a6ca;
            text-decoration: none;
            transition: 0.3s;
        }

        .back-link:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .detail-view {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2.5rem;
        }

        .detail-section {
            margin-bottom: 2.5rem;
        }

        .detail-section h3 {
            font-size: 1.4rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #332d44;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .detail-item .label {
            color: #b2a6ca;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .detail-item .value {
            color: #f0e6d2;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .customization-main-image {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 24px;
            margin-bottom: 1rem;
            cursor: pointer;
            border: 2px solid #332d44;
        }

        .reference-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        .reference-image-item {
            aspect-ratio: 1;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid #332d44;
        }

        .reference-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .reference-image-item:hover img {
            transform: scale(1.1);
        }

        .specs-box {
            background: #161224;
            padding: 1.5rem;
            border-radius: 32px;
        }

        .spec-item {
            display: flex;
            padding: 0.8rem 0;
            border-bottom: 1px solid #332d44;
        }

        .spec-item:last-child {
            border-bottom: none;
        }

        .spec-name {
            width: 150px;
            color: #cfb087;
            font-weight: 500;
        }

        .spec-value {
            flex: 1;
            color: #f0e6d2;
        }

        .color-swatch {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-left: 0.5rem;
            border: 2px solid #3d3452;
            vertical-align: middle;
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

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
        }

        .form-group textarea {
            border-radius: 24px;
            resize: vertical;
            min-height: 120px;
        }

        .form-group select {
            cursor: pointer;
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 5rem;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 60px;
        }

        .empty-state i {
            font-size: 5rem;
            color: #4a3f60;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #b2a6ca;
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 40px;
            color: #fff;
            font-size: 50px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .close-modal:hover {
            color: #cfb087;
        }

        .text-muted {
            color: #b2a6ca;
            font-style: italic;
        }

        /* Debug styles - remove in production */
        .debug-info {
            background: #1e1a2a;
            border: 1px solid #cfb087;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 1.5rem;
            font-size: 0.85rem;
            color: #b2a6ca;
            display: none;
        }
        
        .debug-info.visible {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

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
                <h1>Customization Studio</h1>
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

                <!-- Debug Toggle (remove in production) -->
                <div style="margin-bottom: 1rem;">
                    <button onclick="toggleDebug()" class="filter-btn" style="padding: 0.3rem 1rem;">
                        <i class="fas fa-bug"></i> Toggle Debug Info
                    </button>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total</h3>
                        <div class="value"><?php echo $stats['all']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Draft</h3>
                        <div class="value"><?php echo $stats['draft']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Saved</h3>
                        <div class="value"><?php echo $stats['saved']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending</h3>
                        <div class="value"><?php echo $stats['pending']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>In Progress</h3>
                        <div class="value"><?php echo $stats['in_progress']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Completed</h3>
                        <div class="value"><?php echo $stats['completed']; ?></div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?status=draft" class="filter-btn <?php echo $status_filter == 'draft' ? 'active' : ''; ?>">Draft</a>
                    <a href="?status=saved" class="filter-btn <?php echo $status_filter == 'saved' ? 'active' : ''; ?>">Saved</a>
                    <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?status=in_progress" class="filter-btn <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="?status=completed" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                    <a href="?status=cancelled" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                </div>

                <?php if ($view_customization): ?>
                    <!-- Detailed View -->
                    <div class="action-bar">
                        <h2><i class="fas fa-paint-brush"></i> Customization #<?php echo safe_get($view_customization, 'customization_id'); ?></h2>
                        <a href="customizations.php" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>

                    <div class="detail-view">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <span class="status-badge <?php echo safe_get($view_customization, 'status', 'draft'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', safe_get($view_customization, 'status', 'Draft'))); ?>
                            </span>
                        </div>

                        <!-- Images Section -->
                        <?php 
                        $main_image = safe_get($view_customization, 'customization_image', '');
                        $main_image_url = getImageUrl($main_image);
                        ?>
                        <?php if ($main_image_url): ?>
                        <div class="detail-section">
                            <h3>Design Image</h3>
                            <img src="<?php echo $main_image_url; ?>" 
                                 alt="Customization" 
                                 class="customization-main-image"
                                 onclick="openImageModal(this.src)">
                            
                            <!-- Debug info for this image -->
                            <div class="debug-info" id="debug-main-image">
                                <strong>Main Image Debug:</strong><br>
                                Filename: <?php echo $main_image; ?><br>
                                URL: <?php echo $main_image_url; ?><br>
                                Server Path: <?php echo UPLOAD_DIR . $main_image; ?><br>
                                File Exists: <?php echo file_exists(UPLOAD_DIR . $main_image) ? 'Yes' : 'No'; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        $ref_images = safe_get($view_customization, 'reference_images', '');
                        if (!empty($ref_images)):
                            $images = json_decode($ref_images, true);
                            if (is_array($images) && !empty($images)):
                        ?>
                        <div class="detail-section">
                            <h3>Reference Images</h3>
                            <div class="reference-images-grid">
                                <?php foreach ($images as $image): ?>
                                    <?php if (!empty($image)): 
                                        $ref_image_url = getImageUrl($image);
                                        if ($ref_image_url):
                                    ?>
                                    <div class="reference-image-item">
                                        <img src="<?php echo $ref_image_url; ?>" 
                                             alt="Reference" 
                                             onclick="openImageModal(this.src)">
                                    </div>
                                    <?php 
                                        endif;
                                    endif; 
                                endforeach; 
                                ?>
                            </div>
                        </div>
                        <?php 
                            endif;
                        endif; 
                        ?>

                        <!-- Customer Information -->
                        <div class="detail-section">
                            <h3>Customer</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="label">Name</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'full_name', 'N/A')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Email</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'email', 'N/A')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Phone</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'phone', 'N/A')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Address</div>
                                    <div class="value"><?php echo nl2br(htmlspecialchars(safe_get($view_customization, 'address', 'N/A'))); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="detail-section">
                            <h3>Product</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="label">Product</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'product_name', 'Custom Design')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Material</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'material_name', 'Not specified')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Type</div>
                                    <div class="value"><?php echo htmlspecialchars(ucfirst(safe_get($view_customization, 'furniture_type', 'N/A'))); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Dimensions</div>
                                    <div class="value"><?php echo htmlspecialchars(safe_get($view_customization, 'dimensions', 'N/A')); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Colors</div>
                                    <div class="value">
                                        Primary: <span class="color-swatch" style="background: <?php echo htmlspecialchars(safe_get($view_customization, 'color', '#000000')); ?>"></span>
                                        <?php if (!empty(safe_get($view_customization, 'accent_color'))): ?>
                                        Accent: <span class="color-swatch" style="background: <?php echo htmlspecialchars(safe_get($view_customization, 'accent_color')); ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Quantity</div>
                                    <div class="value"><?php echo safe_get($view_customization, 'quantity', 1); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Total Price</div>
                                    <div class="value">₱<?php echo number_format(safe_get($view_customization, 'total_price', 0), 2); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">Request Date</div>
                                    <div class="value"><?php echo date('F d, Y', strtotime(safe_get($view_customization, 'created_at', date('Y-m-d')))); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Specifications -->
                        <?php 
                        $specs_data = safe_get($view_customization, 'specifications', '');
                        if (!empty($specs_data)):
                            $specs = json_decode($specs_data, true);
                            if (is_array($specs) && !empty($specs)):
                        ?>
                        <div class="detail-section">
                            <h3>Specifications</h3>
                            <div class="specs-box">
                                <?php foreach ($specs as $key => $value): ?>
                                <div class="spec-item">
                                    <div class="spec-name"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</div>
                                    <div class="spec-value"><?php echo htmlspecialchars($value); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php 
                            endif;
                        endif; 
                        ?>

                        <!-- Special Instructions -->
                        <?php 
                        $instructions = safe_get($view_customization, 'special_instructions', '');
                        if (!empty($instructions)):
                        ?>
                        <div class="detail-section">
                            <h3>Special Instructions</h3>
                            <div class="specs-box">
                                <?php echo nl2br(htmlspecialchars($instructions)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Admin Notes -->
                        <?php 
                        $notes = safe_get($view_customization, 'admin_notes', '');
                        ?>
                        <div class="detail-section">
                            <h3>Admin Notes</h3>
                            <div class="specs-box">
                                <?php echo !empty($notes) ? nl2br(htmlspecialchars($notes)) : '<span class="text-muted">No admin notes yet</span>'; ?>
                            </div>
                        </div>

                        <!-- Update Status Form -->
                        <div class="detail-section">
                            <h3>Update Status</h3>
                            <form method="POST">
                                <input type="hidden" name="customization_id" value="<?php echo safe_get($view_customization, 'customization_id'); ?>">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" required>
                                        <option value="draft" <?php echo safe_get($view_customization, 'status') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="saved" <?php echo safe_get($view_customization, 'status') == 'saved' ? 'selected' : ''; ?>>Saved</option>
                                        <option value="pending" <?php echo safe_get($view_customization, 'status') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in_progress" <?php echo safe_get($view_customization, 'status') == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo safe_get($view_customization, 'status') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo safe_get($view_customization, 'status') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Admin Notes</label>
                                    <textarea name="admin_notes"><?php echo htmlspecialchars($notes); ?></textarea>
                                </div>
                                <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Grid View -->
                    <div class="customizations-grid">
                        <?php if ($customizations && $customizations->num_rows > 0): ?>
                            <?php while($cust = $customizations->fetch_assoc()): 
                                $status = safe_get($cust, 'status', 'draft');
                                $image = safe_get($cust, 'customization_image', '');
                                $image_url = getImageUrl($image);
                                $customization_id = safe_get($cust, 'customization_id');
                            ?>
                                <div class="customization-card">
                                    <div class="card-header">
                                        <strong>#<?php echo $customization_id; ?></strong>
                                        <span class="status-badge <?php echo $status; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </div>

                                    <div class="image-wrapper" <?php echo $image_url ? 'onclick="openImageModal(\'' . $image_url . '\')"' : ''; ?>>
                                        <?php if ($image_url): ?>
                                            <img src="<?php echo $image_url; ?>" 
                                                 alt="Customization">
                                        <?php else: ?>
                                            <i class="fas fa-couch"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Debug info for this card -->
                                    <div class="debug-info" id="debug-<?php echo $customization_id; ?>">
                                        <strong>Debug Info:</strong><br>
                                        Image filename: <?php echo $image ?: 'None'; ?><br>
                                        Image URL: <?php echo $image_url ?: 'None'; ?><br>
                                        Server Path: <?php echo UPLOAD_DIR . $image; ?><br>
                                        File exists: <?php echo ($image && file_exists(UPLOAD_DIR . $image)) ? 'Yes' : 'No'; ?>
                                    </div>

                                    <div class="card-body">
                                        <div class="info-row">
                                            <i class="fas fa-user"></i>
                                            <span class="info-label">Customer:</span>
                                            <span class="info-value"><?php echo htmlspecialchars(safe_get($cust, 'full_name', 'N/A')); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-chair"></i>
                                            <span class="info-label">Product:</span>
                                            <span class="info-value"><?php echo htmlspecialchars(safe_get($cust, 'product_name', 'Custom')); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-tree"></i>
                                            <span class="info-label">Material:</span>
                                            <span class="info-value"><?php echo htmlspecialchars(safe_get($cust, 'material_name', 'N/A')); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-palette"></i>
                                            <span class="info-label">Colors:</span>
                                            <span class="info-value">
                                                <span class="color-swatch" style="background: <?php echo htmlspecialchars(safe_get($cust, 'color', '#000000')); ?>"></span>
                                                <?php if (!empty(safe_get($cust, 'accent_color'))): ?>
                                                <span class="color-swatch" style="background: <?php echo htmlspecialchars(safe_get($cust, 'accent_color')); ?>"></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-tag"></i>
                                            <span class="info-label">Price:</span>
                                            <span class="info-value">₱<?php echo number_format(safe_get($cust, 'total_price', 0), 2); ?></span>
                                        </div>

                                        <?php 
                                        $specs_data = safe_get($cust, 'specifications', '');
                                        if (!empty($specs_data)):
                                            $specs = json_decode($specs_data, true);
                                            if (is_array($specs) && !empty($specs)):
                                        ?>
                                        <div class="specs-preview">
                                            <?php $count = 0; ?>
                                            <?php foreach($specs as $key => $value): ?>
                                                <?php if ($count < 2): ?>
                                                    <div class="detail-item">
                                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                                        <?php echo htmlspecialchars(substr($value, 0, 20)) . (strlen($value) > 20 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php $count++; ?>
                                            <?php endforeach; ?>
                                            <?php if (count($specs) > 2): ?>
                                                <div class="detail-item">+<?php echo count($specs) - 2; ?> more specs</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php 
                                            endif;
                                        endif; 
                                        ?>
                                    </div>

                                    <div class="card-footer">
                                        <a href="?view=<?php echo $customization_id; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="?delete=<?php echo $customization_id; ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Delete this customization request?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-paint-brush"></i>
                                <h3>No Customizations Found</h3>
                                <p>There are no customization requests matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function openImageModal(src) {
            document.getElementById('imageModal').style.display = "block";
            document.getElementById('modalImage').src = src;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = "none";
        }

        window.onclick = function(event) {
            var modal = document.getElementById('imageModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Toggle debug information
        function toggleDebug() {
            var debugElements = document.getElementsByClassName('debug-info');
            for (var i = 0; i < debugElements.length; i++) {
                if (debugElements[i].classList.contains('visible')) {
                    debugElements[i].classList.remove('visible');
                } else {
                    debugElements[i].classList.add('visible');
                }
            }
        }

        // Check if images are loading correctly
        document.addEventListener('DOMContentLoaded', function() {
            var images = document.querySelectorAll('.image-wrapper img');
            images.forEach(function(img) {
                img.onerror = function() {
                    console.log('Image failed to load:', this.src);
                    // Replace with icon on error
                    var wrapper = this.parentElement;
                    var icon = document.createElement('i');
                    icon.className = 'fas fa-couch';
                    wrapper.innerHTML = '';
                    wrapper.appendChild(icon);
                };
            });
        });
    </script>
</body>
</html>