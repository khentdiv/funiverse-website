<?php
require_once '../config.php';

if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $product_name = sanitize($_POST['product_name']);
    $category_id = sanitize($_POST['category_id']);
    $base_price = sanitize($_POST['base_price']);
    $description = sanitize($_POST['description']);
    $is_customizable = isset($_POST['is_customizable']) ? 1 : 0;
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../images/";
        $image = time() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image;
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
    }
    
    $query = "INSERT INTO products (product_name, category_id, base_price, description, image, is_customizable) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sidssi", $product_name, $category_id, $base_price, $description, $image, $is_customizable);
    
    if ($stmt->execute()) {
        $product_id = $conn->insert_id;
        // Add to inventory
        $conn->query("INSERT INTO inventory (product_id, quantity) VALUES ($product_id, 10)");
        
        // Log action
        $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'add_product', ?)";
        $log_stmt = $conn->prepare($log_query);
        $details = "Added product: $product_name";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        
        $_SESSION['success'] = "Product added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add product";
    }
    redirect('products.php');
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    // Get product name for log
    $prod = $conn->query("SELECT product_name, image FROM products WHERE product_id = $product_id")->fetch_assoc();
    
    // Delete image file if exists
    if (!empty($prod['image']) && file_exists("../images/" . $prod['image'])) {
        unlink("../images/" . $prod['image']);
    }
    
    $query = "DELETE FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        // Log action
        $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'delete_product', ?)";
        $log_stmt = $conn->prepare($log_query);
        $details = "Deleted product: " . $prod['product_name'];
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        
        $_SESSION['success'] = "Product deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete product";
    }
    redirect('products.php');
}

// Fetch all products with categories
$products = $conn->query("
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");

// Fetch categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0,
    'customizable' => $conn->query("SELECT COUNT(*) as count FROM products WHERE is_customizable = 1")->fetch_assoc()['count'] ?? 0,
    'categories' => $conn->query("SELECT COUNT(DISTINCT category_id) as count FROM products WHERE category_id IS NOT NULL")->fetch_assoc()['count'] ?? 0,
    'low_stock' => $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 10")->fetch_assoc()['count'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Furniverse Admin</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            overflow: hidden;
            transition: 0.3s;
        }

        .product-card:hover {
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

        .badge {
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge.customizable {
            background: #1a2d3a;
            color: #8bb9ff;
        }

        .badge.standard {
            background: #2d2640;
            color: #b3a4cb;
        }

        .image-wrapper {
            height: 220px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 1.5rem 1.5rem;
            border-radius: 32px;
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

        .product-title {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1rem;
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

        .price-tag {
            font-size: 1.5rem;
            font-weight: 700;
            color: #cfb087;
            margin: 1rem 0;
        }

        .description-preview {
            background: #161224;
            padding: 1rem;
            border-radius: 24px;
            margin: 1rem 0;
            max-height: 80px;
            overflow-y: auto;
            color: #b2a6ca;
            font-size: 0.9rem;
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

        .btn-edit {
            padding: 0.8rem 1.2rem;
            background: transparent;
            border: 1px solid #8bb9ff;
            border-radius: 40px;
            color: #8bb9ff;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-edit:hover {
            background: #8bb9ff;
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
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background: #1e192c;
            margin: 5% auto;
            padding: 2rem;
            border: 1px solid #332d44;
            border-radius: 42px;
            width: 90%;
            max-width: 600px;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #332d44;
        }

        .modal-header h2 {
            color: #f0e6d2;
            font-size: 1.8rem;
        }

        .close {
            color: #b2a6ca;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .close:hover {
            color: #cfb087;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #332d44;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #cfb087;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
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
            transition: 0.3s;
        }

        .form-group textarea {
            border-radius: 24px;
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #cfb087;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 1rem;
            background: #161224;
            border: 1px dashed #332d44;
            border-radius: 40px;
            color: #b2a6ca;
            cursor: pointer;
        }

        .form-group input[type="file"]::-webkit-file-upload-button {
            background: #cfb087;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            color: #0f0b17;
            font-weight: 500;
            margin-right: 1rem;
            cursor: pointer;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #f0e6d2;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #cfb087;
        }

        .btn {
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .btn-secondary {
            background: transparent;
            border-color: #3d3452;
            color: #b2a6ca;
        }

        .btn-secondary:hover {
            border-color: #cfb087;
            color: #f0e6d2;
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

        .text-muted {
            color: #b2a6ca;
            font-style: italic;
        }

        /* Image Preview */
        .image-preview {
            margin-top: 1rem;
            max-width: 200px;
            border-radius: 24px;
            overflow: hidden;
            border: 2px solid #332d44;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            display: block;
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
                <h1>Products Management</h1>
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

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Products</h3>
                        <div class="value"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Customizable</h3>
                        <div class="value"><?php echo $stats['customizable']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Categories</h3>
                        <div class="value"><?php echo $stats['categories']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Low Stock</h3>
                        <div class="value"><?php echo $stats['low_stock']; ?></div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <h2><i class="fas fa-chair"></i> All Products</h2>
                    <button class="btn-primary" onclick="showAddProductModal()">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>

                <!-- Products Grid -->
                <div class="products-grid">
                    <?php if ($products && $products->num_rows > 0): ?>
                        <?php while($product = $products->fetch_assoc()): ?>
                            <div class="product-card">
                                <div class="card-header">
                                    <strong>#<?php echo $product['product_id']; ?></strong>
                                    <span class="badge <?php echo $product['is_customizable'] ? 'customizable' : 'standard'; ?>">
                                        <?php echo $product['is_customizable'] ? 'Customizable' : 'Standard'; ?>
                                    </span>
                                </div>

                                <div class="image-wrapper">
                                    <?php if (!empty($product['image']) && file_exists("../images/" . $product['image'])): ?>
                                        <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-couch"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    
                                    <div class="info-row">
                                        <i class="fas fa-tag"></i>
                                        <span class="info-label">Category:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                    </div>
                                    
                                    <div class="price-tag">₱<?php echo number_format($product['base_price'], 2); ?></div>

                                    <div class="description-preview">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <a href="edit-product.php?id=<?php echo $product['product_id']; ?>" class="btn-edit" title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $product['product_id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Are you sure you want to delete this product?')"
                                       title="Delete Product">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="view-product.php?id=<?php echo $product['product_id']; ?>" class="btn-view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chair"></i>
                            <h3>No Products Found</h3>
                            <p>Get started by adding your first product to the catalog.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <span class="close" onclick="hideAddProductModal()">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" placeholder="Enter product name" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php 
                        $categories->data_seek(0);
                        while($cat = $categories->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="base_price">Base Price (₱)</label>
                    <input type="number" id="base_price" name="base_price" step="0.01" min="0" placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter product description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img src="" alt="Preview">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_customizable" checked>
                        <span>This product can be customized</span>
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideAddProductModal()">Cancel</button>
                    <button type="submit" name="add_product" class="btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddProductModal() {
            document.getElementById('addProductModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function hideAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                previewImg.src = '';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addProductModal');
            if (event.target == modal) {
                hideAddProductModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideAddProductModal();
            }
        });
    </script>
</body>
</html>