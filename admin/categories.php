<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// First, let's check what columns exist in the categories table
$columns_exist = [
    'category_id' => false,
    'category_name' => false,
    'description' => false,
    'category_image' => false,
    'created_at' => false,
    'updated_at' => false
];

$check_table = $conn->query("SHOW TABLES LIKE 'categories'");
if ($check_table && $check_table->num_rows > 0) {
    // Table exists, check columns
    $columns = $conn->query("SHOW COLUMNS FROM categories");
    while ($column = $columns->fetch_assoc()) {
        $columns_exist[$column['Field']] = true;
    }
} else {
    // Create categories table with all needed columns
    $conn->query("
        CREATE TABLE IF NOT EXISTS categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            description TEXT,
            category_image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Set all columns as existing since we just created them
    foreach (array_keys($columns_exist) as $key) {
        $columns_exist[$key] = true;
    }
}

// Handle Add Category
if (isset($_POST['add_category'])) {
    $category_name = $conn->real_escape_string($_POST['category_name']);
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $target_dir = "../uploads/categories/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image = time() . '_' . basename($_FILES['category_image']['name']);
        $target_file = $target_dir . $image;
        
        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $target_file)) {
            // Image uploaded successfully
        }
    }
    
    // Build query based on existing columns
    if ($columns_exist['description']) {
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $query = "INSERT INTO categories (category_name, description, category_image) 
                  VALUES ('$category_name', '$description', '$image')";
    } else {
        $query = "INSERT INTO categories (category_name, category_image) 
                  VALUES ('$category_name', '$image')";
    }
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Category added successfully";
    } else {
        $_SESSION['error'] = "Error adding category: " . $conn->error;
    }
    redirect('categories.php');
}

// Handle Edit Category
if (isset($_POST['edit_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = $conn->real_escape_string($_POST['category_name']);
    
    // Build update query based on existing columns
    if ($columns_exist['description']) {
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $query = "UPDATE categories SET 
                  category_name = '$category_name',
                  description = '$description'
                  WHERE category_id = $category_id";
    } else {
        $query = "UPDATE categories SET 
                  category_name = '$category_name'
                  WHERE category_id = $category_id";
    }
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Category updated successfully";
    } else {
        $_SESSION['error'] = "Error updating category: " . $conn->error;
    }
    redirect('categories.php');
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has products
    $check = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id = $category_id");
    if ($check) {
        $result = $check->fetch_assoc();
        if ($result['count'] > 0) {
            $_SESSION['error'] = "Cannot delete category because it contains products";
        } else {
            // Delete category image if exists
            $category = $conn->query("SELECT category_image FROM categories WHERE category_id = $category_id")->fetch_assoc();
            if ($category && !empty($category['category_image']) && file_exists("../uploads/categories/" . $category['category_image'])) {
                unlink("../uploads/categories/" . $category['category_image']);
            }
            
            $query = "DELETE FROM categories WHERE category_id = $category_id";
            if ($conn->query($query)) {
                $_SESSION['success'] = "Category deleted successfully";
            } else {
                $_SESSION['error'] = "Error deleting category: " . $conn->error;
            }
        }
    }
    redirect('categories.php');
}

// Get all categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get category for editing if ID is provided
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM categories WHERE category_id = $edit_id");
    if ($result && $result->num_rows > 0) {
        $edit_category = $result->fetch_assoc();
    }
}

// Function to safely get category description
function getCategoryDescription($category, $columns_exist) {
    if ($columns_exist['description'] && isset($category['description']) && !empty($category['description'])) {
        return htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : '');
    }
    return '<span style="color: #999; font-style: italic;">No description</span>';
}

// Function to safely display description in edit form
function getEditDescription($category, $columns_exist) {
    if ($columns_exist['description'] && isset($category['description'])) {
        return htmlspecialchars($category['description']);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - Furniverse Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .categories-container {
            padding: 20px;
        }
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background: #d35400;
        }
        .btn-edit {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            margin-right: 5px;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
        }
        .btn-view {
            background: #2ecc71;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            margin-right: 5px;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .category-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .category-image {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .category-image i {
            font-size: 48px;
            color: #ccc;
        }
        .category-info {
            padding: 20px;
        }
        .category-info h3 {
            margin: 0 0 10px;
            color: #333;
            font-size: 20px;
        }
        .category-info p {
            margin: 0 0 15px;
            color: #666;
            line-height: 1.6;
            min-height: 60px;
        }
        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
        }
        .product-count {
            background: #e67e22;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .category-actions {
            display: flex;
            gap: 5px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 500px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .form-group input[type="file"] {
            padding: 8px;
            background: #f8f9fa;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .image-preview {
            width: 100%;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
            background: #f8f9fa;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .info-message {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
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
                <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1>Categories Management</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if (!$columns_exist['description']): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> The 'description' field doesn't exist in your categories table. 
                    To add description support, run this SQL command:
                    <code style="display: block; background: #f8f9fa; padding: 10px; margin-top: 10px; border-radius: 5px;">
                        ALTER TABLE categories ADD COLUMN description TEXT AFTER category_name;
                    </code>
                </div>
                <?php endif; ?>

                <div class="categories-container">
                    <div class="action-bar">
                        <h2>Product Categories</h2>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add New Category
                        </button>
                    </div>

                    <div class="categories-grid">
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php while($category = $categories->fetch_assoc()): 
                                $product_count_result = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id = " . $category['category_id']);
                                $product_count = $product_count_result ? $product_count_result->fetch_assoc()['count'] : 0;
                            ?>
                                <div class="category-card">
                                    <div class="category-image">
                                        <?php if (!empty($category['category_image']) && file_exists("../uploads/categories/" . $category['category_image'])): ?>
                                            <img src="../uploads/categories/<?php echo $category['category_image']; ?>" alt="<?php echo htmlspecialchars($category['category_name'] ?? ''); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-tags"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['category_name'] ?? ''); ?></h3>
                                        <p><?php echo getCategoryDescription($category, $columns_exist); ?></p>
                                        <div class="category-stats">
                                            <span class="product-count"><?php echo $product_count; ?> Products</span>
                                            <div class="category-actions">
                                                <a href="?edit=<?php echo $category['category_id']; ?>" class="btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="products.php?category=<?php echo $category['category_id']; ?>" class="btn-view" title="View Products"><i class="fas fa-eye"></i></a>
                                                <a href="?delete=<?php echo $category['category_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this category?')" title="Delete"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 10px;">
                                <i class="fas fa-tags" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                <h3>No Categories Found</h3>
                                <p>Click "Add New Category" to create your first category.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Category</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="category_name">Category Name *</label>
                    <input type="text" id="category_name" name="category_name" required placeholder="e.g., Chairs, Tables, Sofas">
                </div>
                
                <?php if ($columns_exist['description']): ?>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe this category..."></textarea>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="category_image">Category Image</label>
                    <div class="image-preview" id="imagePreview">
                        <i class="fas fa-image" style="font-size: 32px; color: #ccc;"></i>
                    </div>
                    <input type="file" id="category_image" name="category_image" accept="image/*" onchange="previewImage(this)">
                </div>
                <div class="form-group">
                    <button type="submit" name="add_category" class="btn-primary" style="width: 100%;">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <?php if ($edit_category && is_array($edit_category)): ?>
    <div id="editModal" class="modal" style="display: block;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <a href="categories.php" class="close">&times;</a>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id'] ?? ''; ?>">
                <div class="form-group">
                    <label for="edit_category_name">Category Name *</label>
                    <input type="text" id="edit_category_name" name="category_name" required value="<?php echo htmlspecialchars($edit_category['category_name'] ?? ''); ?>">
                </div>
                
                <?php if ($columns_exist['description']): ?>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" name="edit_category" class="btn-primary" style="width: 100%;">Update Category</button>
                </div>
                <div class="form-group">
                    <a href="categories.php" class="btn-primary" style="background: #95a5a6; text-align: center; width: 100%; display: inline-block; text-decoration: none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function previewImage(input) {
            var preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 100%; object-fit: contain;">';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '<i class="fas fa-image" style="font-size: 32px; color: #ccc;"></i>';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var addModal = document.getElementById('addModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            
            var editModal = document.getElementById('editModal');
            if (event.target == editModal) {
                window.location.href = 'categories.php';
            }
        }
    </script>
</body>
</html>