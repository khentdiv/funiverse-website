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

// Fetch product details
$product = $conn->query("
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_id = $product_id
")->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found";
    redirect('products.php');
}

// Fetch categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Fetch materials for dropdown (if material_id column exists)
$materials = null;
$check_material_column = $conn->query("SHOW COLUMNS FROM products LIKE 'material_id'");
if ($check_material_column && $check_material_column->num_rows > 0) {
    $materials = $conn->query("SELECT * FROM materials ORDER BY material_name");
}

// Fetch inventory data
$inventory = $conn->query("SELECT * FROM inventory WHERE product_id = $product_id")->fetch_assoc();
if (!$inventory) {
    $inventory = ['quantity' => 0, 'low_stock_threshold' => 5];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - <?php echo htmlspecialchars($product['product_name']); ?> - Furniverse Admin</title>
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

        .edit-product-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .product-form {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #f0e6d2;
            margin: 2rem 0 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #332d44;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .section-title i {
            color: #cfb087;
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

        .form-group input,
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
            min-height: 150px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #cfb087;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #161224;
            padding: 1rem 1.5rem;
            border-radius: 40px;
            border: 1px solid #332d44;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #cfb087;
        }

        .checkbox-group label {
            color: #f0e6d2;
            font-weight: 500;
            cursor: pointer;
        }

        /* Current Image Display */
        .current-image {
            background: #161224;
            padding: 2rem;
            border-radius: 32px;
            border: 1px solid #332d44;
            text-align: center;
            margin: 1.5rem 0;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 16px;
            border: 2px solid #332d44;
        }

        .current-image i {
            font-size: 4rem;
            color: #4a3f60;
        }

        .current-image p {
            color: #b2a6ca;
            margin-top: 1rem;
        }

        /* Image Preview */
        .image-preview {
            width: 100%;
            height: 250px;
            border: 2px dashed #332d44;
            border-radius: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 1rem 0;
            background: #161224;
            cursor: pointer;
            transition: 0.3s;
            overflow: hidden;
        }

        .image-preview:hover {
            border-color: #cfb087;
            background: #1e192c;
        }

        .image-preview i {
            font-size: 3rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }

        .image-preview p {
            color: #b2a6ca;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .btn-secondary {
            padding: 0.8rem 1.5rem;
            background: transparent;
            border: 1px solid #3d3452;
            border-radius: 40px;
            color: #b2a6ca;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .btn-primary {
            padding: 1rem 2rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #332d44;
        }

        /* Back button in header */
        .btn-back {
            padding: 0.6rem 1.5rem;
            background: transparent;
            border: 1px solid #3d3452;
            border-radius: 40px;
            color: #b2a6ca;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.3s;
        }

        .btn-back:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        /* Helper text */
        .help-text {
            color: #b2a6ca;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        /* Select styling */
        select option {
            background: #161224;
            color: #f0e6d2;
        }

        /* Number input styling */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 0;
                display: none;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .product-form {
                padding: 1.5rem;
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
                <li><a href="products.php" class="active"><i class="fas fa-chair"></i> Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="materials.php"><i class="fas fa-cube"></i> Materials</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-edit" style="margin-right: 10px; color: #cfb087;"></i>Edit Product</h1>
                <div class="admin-user">
                    <i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>

            <div class="admin-content">
                <div class="edit-product-container">
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

                    <form class="product-form" method="POST" action="edit-product-handler.php" enctype="multipart/form-data">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i> Basic Information
                        </h3>
                        
                        <div class="form-group">
                            <label for="product_name">Product Name <span style="color: #b84a6e;">*</span></label>
                            <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_id">Category <span style="color: #b84a6e;">*</span></label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    $categories->data_seek(0);
                                    while($cat = $categories->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $cat['category_id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="base_price">Base Price (₱) <span style="color: #b84a6e;">*</span></label>
                                <input type="number" id="base_price" name="base_price" step="0.01" min="0" value="<?php echo $product['base_price']; ?>" required>
                            </div>
                        </div>

                        <?php if ($materials): ?>
                        <div class="form-group">
                            <label for="material_id">Material</label>
                            <select id="material_id" name="material_id">
                                <option value="">Select Material (Optional)</option>
                                <?php 
                                $materials->data_seek(0);
                                while($material = $materials->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $material['material_id']; ?>" <?php echo (isset($product['material_id']) && $material['material_id'] == $product['material_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($material['material_name']); ?> (₱<?php echo number_format($material['cost_per_unit'], 2); ?> per <?php echo $material['unit']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="description">Description <span style="color: #b84a6e;">*</span></label>
                            <textarea id="description" name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="is_customizable" name="is_customizable" value="1" <?php echo $product['is_customizable'] ? 'checked' : ''; ?>>
                            <label for="is_customizable">This product can be customized</label>
                        </div>

                        <h3 class="section-title">
                            <i class="fas fa-image"></i> Product Image
                        </h3>
                        
                        <div class="current-image">
                            <?php if($product['image'] && file_exists("../images/" . $product['image'])): ?>
                                <img src="../images/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <p><i class="fas fa-check-circle" style="color: #4a9b6e;"></i> Current Image</p>
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                                <p>No image uploaded</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="new_image">Replace Image (Optional)</label>
                            <div class="image-preview" id="imagePreview" onclick="document.getElementById('new_image').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload new image</p>
                            </div>
                            <input type="file" id="new_image" name="new_image" accept="image/*" onchange="previewImage(this)" style="display: none;">
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Supported formats: JPG, PNG, GIF. Max size: 2MB
                            </div>
                        </div>

                        <h3 class="section-title">
                            <i class="fas fa-boxes"></i> Inventory Settings
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity">Current Stock Quantity</label>
                                <input type="number" id="quantity" name="quantity" step="1" min="0" value="<?php echo $inventory['quantity']; ?>">
                            </div>

                            <div class="form-group">
                                <label for="low_stock_threshold">Low Stock Threshold</label>
                                <input type="number" id="low_stock_threshold" name="low_stock_threshold" step="1" min="1" value="<?php echo $inventory['low_stock_threshold']; ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="products.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="edit_product" class="btn-primary">
                                <i class="fas fa-save"></i> Update Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            var preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 100%; object-fit: contain;">';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><p>Click to upload new image</p>';
            }
        }
    </script>
</body>
</html>