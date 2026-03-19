<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// First, let's check if the materials table exists, if not create it
$conn->query("
    CREATE TABLE IF NOT EXISTS materials (
        material_id INT AUTO_INCREMENT PRIMARY KEY,
        material_name VARCHAR(100) NOT NULL,
        description TEXT,
        unit VARCHAR(50) NOT NULL,
        cost_per_unit DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Check if material_id column exists in products table
$material_column_exists = false;
$check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'material_id'");
if ($check_column && $check_column->num_rows > 0) {
    $material_column_exists = true;
}

// Handle Add Material
if (isset($_POST['add_material'])) {
    $material_name = $conn->real_escape_string($_POST['material_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $unit = $conn->real_escape_string($_POST['unit']);
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    
    $query = "INSERT INTO materials (material_name, description, unit, cost_per_unit) 
              VALUES ('$material_name', '$description', '$unit', $cost_per_unit)";
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Material added successfully";
    } else {
        $_SESSION['error'] = "Error adding material: " . $conn->error;
    }
    redirect('materials.php');
}

// Handle Edit Material
if (isset($_POST['edit_material'])) {
    $material_id = intval($_POST['material_id']);
    $material_name = $conn->real_escape_string($_POST['material_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $unit = $conn->real_escape_string($_POST['unit']);
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    
    $query = "UPDATE materials SET 
              material_name = '$material_name',
              description = '$description',
              unit = '$unit',
              cost_per_unit = $cost_per_unit
              WHERE material_id = $material_id";
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Material updated successfully";
    } else {
        $_SESSION['error'] = "Error updating material: " . $conn->error;
    }
    redirect('materials.php');
}

// Handle Delete Material
if (isset($_GET['delete'])) {
    $material_id = intval($_GET['delete']);
    
    // Check if material is used in products (only if column exists)
    $can_delete = true;
    if ($material_column_exists) {
        $check = $conn->query("SELECT COUNT(*) as count FROM products WHERE material_id = $material_id");
        if ($check) {
            $result = $check->fetch_assoc();
            if ($result['count'] > 0) {
                $can_delete = false;
                $_SESSION['error'] = "Cannot delete material because it is used in products";
            }
        }
    }
    
    if ($can_delete) {
        $query = "DELETE FROM materials WHERE material_id = $material_id";
        if ($conn->query($query)) {
            $_SESSION['success'] = "Material deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting material: " . $conn->error;
        }
    }
    redirect('materials.php');
}

// Get all materials
$materials = $conn->query("SELECT * FROM materials ORDER BY material_name");

// Get material for editing if ID is provided
$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM materials WHERE material_id = $edit_id");
    if ($result && $result->num_rows > 0) {
        $edit_material = $result->fetch_assoc();
    }
}

// Function to get product count for a material (if relationship exists)
function getProductCountForMaterial($conn, $material_id, $column_exists) {
    if ($column_exists) {
        $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE material_id = $material_id");
        if ($result) {
            $row = $result->fetch_assoc();
            return $row ? $row['count'] : 0;
        }
    }
    return 0; // Return 0 if column doesn't exist or query fails
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials Management - Furniverse Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .materials-container {
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
        .btn-delete:hover, .btn-edit:hover {
            opacity: 0.9;
        }
        .materials-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .materials-table th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: left;
        }
        .materials-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        .materials-table tr:hover {
            background: #f8f9fa;
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
        .form-group input, .form-group textarea, .form-group select {
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
        .unit-badge {
            background: #e67e22;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .info-message {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .info-message i {
            color: #2196f3;
            margin-right: 10px;
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
                <li><a href="materials.php" class="active"><i class="fas fa-cube"></i> Materials</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
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
                <h1>Materials Management</h1>
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

                <?php if (!$material_column_exists): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> The relationship between materials and products hasn't been set up yet. 
                    To enable product tracking by material, run this SQL command:
                    <code style="display: block; background: #f8f9fa; padding: 10px; margin-top: 10px; border-radius: 5px;">
                        ALTER TABLE products ADD COLUMN material_id INT NULL AFTER category_id;
                    </code>
                </div>
                <?php endif; ?>

                <div class="materials-container">
                    <div class="action-bar">
                        <h2>Materials List</h2>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add New Material
                        </button>
                    </div>

                    <table class="materials-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Material Name</th>
                                <th>Description</th>
                                <th>Unit</th>
                                <th>Cost per Unit</th>
                                <th>Products Using</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($materials && $materials->num_rows > 0): ?>
                                <?php while($material = $materials->fetch_assoc()): 
                                    $product_count = getProductCountForMaterial($conn, $material['material_id'], $material_column_exists);
                                ?>
                                    <tr>
                                        <td>#<?php echo $material['material_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($material['material_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($material['description'] ?? '', 0, 50)) . (strlen($material['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><span class="unit-badge"><?php echo htmlspecialchars($material['unit'] ?? ''); ?></span></td>
                                        <td>₱<?php echo number_format($material['cost_per_unit'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php if ($material_column_exists): ?>
                                                <?php echo $product_count; ?> products
                                            <?php else: ?>
                                                <span style="color: #999;">Not linked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?edit=<?php echo $material['material_id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="?delete=<?php echo $material['material_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this material?')"><i class="fas fa-trash"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">
                                        <i class="fas fa-cube" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                        No materials found. Click "Add New Material" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Material</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="material_name">Material Name *</label>
                    <input type="text" id="material_name" name="material_name" required placeholder="e.g., Wood, Metal, Fabric">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the material..."></textarea>
                </div>
                <div class="form-group">
                    <label for="unit">Unit of Measurement *</label>
                    <select id="unit" name="unit" required>
                        <option value="">Select unit</option>
                        <option value="piece">Piece (pc)</option>
                        <option value="board foot">Board Foot (bd ft)</option>
                        <option value="square meter">Square Meter (m²)</option>
                        <option value="cubic meter">Cubic Meter (m³)</option>
                        <option value="meter">Meter (m)</option>
                        <option value="kilogram">Kilogram (kg)</option>
                        <option value="liter">Liter (L)</option>
                        <option value="set">Set</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cost_per_unit">Cost per Unit (₱) *</label>
                    <input type="number" id="cost_per_unit" name="cost_per_unit" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <button type="submit" name="add_material" class="btn-primary" style="width: 100%;">Add Material</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <?php if ($edit_material && is_array($edit_material)): ?>
    <div id="editModal" class="modal" style="display: block;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Material</h2>
                <a href="materials.php" class="close">&times;</a>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="material_id" value="<?php echo $edit_material['material_id'] ?? ''; ?>">
                <div class="form-group">
                    <label for="edit_material_name">Material Name *</label>
                    <input type="text" id="edit_material_name" name="material_name" required value="<?php echo htmlspecialchars($edit_material['material_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"><?php echo htmlspecialchars($edit_material['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_unit">Unit of Measurement *</label>
                    <select id="edit_unit" name="unit" required>
                        <option value="piece" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'piece') ? 'selected' : ''; ?>>Piece (pc)</option>
                        <option value="board foot" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'board foot') ? 'selected' : ''; ?>>Board Foot (bd ft)</option>
                        <option value="square meter" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'square meter') ? 'selected' : ''; ?>>Square Meter (m²)</option>
                        <option value="cubic meter" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'cubic meter') ? 'selected' : ''; ?>>Cubic Meter (m³)</option>
                        <option value="meter" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'meter') ? 'selected' : ''; ?>>Meter (m)</option>
                        <option value="kilogram" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'kilogram') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                        <option value="liter" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'liter') ? 'selected' : ''; ?>>Liter (L)</option>
                        <option value="set" <?php echo (isset($edit_material['unit']) && $edit_material['unit'] == 'set') ? 'selected' : ''; ?>>Set</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_cost_per_unit">Cost per Unit (₱) *</label>
                    <input type="number" id="edit_cost_per_unit" name="cost_per_unit" step="0.01" min="0" required value="<?php echo $edit_material['cost_per_unit'] ?? '0.00'; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" name="edit_material" class="btn-primary" style="width: 100%;">Update Material</button>
                </div>
                <div class="form-group">
                    <a href="materials.php" class="btn-primary" style="background: #95a5a6; text-align: center; width: 100%; display: inline-block; text-decoration: none;">Cancel</a>
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            var addModal = document.getElementById('addModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            
            var editModal = document.getElementById('editModal');
            if (event.target == editModal) {
                window.location.href = 'materials.php';
            }
        }
    </script>
</body>
</html>