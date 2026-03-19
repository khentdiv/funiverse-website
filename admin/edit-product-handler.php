<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    
    $product_id = (int)$_POST['product_id'];
    $product_name = sanitize($_POST['product_name']);
    $category_id = (int)$_POST['category_id'];
    $base_price = floatval($_POST['base_price']);
    $description = sanitize($_POST['description']);
    $is_customizable = isset($_POST['is_customizable']) ? 1 : 0;
    $material_id = isset($_POST['material_id']) && !empty($_POST['material_id']) ? (int)$_POST['material_id'] : 'NULL';
    
    // Handle image upload
    $image_update = "";
    if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
        $target_dir = "../images/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Delete old image if exists
        $old_image = $conn->query("SELECT image FROM products WHERE product_id = $product_id")->fetch_assoc();
        if ($old_image && !empty($old_image['image']) && file_exists($target_dir . $old_image['image'])) {
            unlink($target_dir . $old_image['image']);
        }
        
        $image = time() . '_' . basename($_FILES["new_image"]["name"]);
        $target_file = $target_dir . $image;
        
        if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
            $image_update = ", image = '$image'";
        }
    }
    
    // Check if material_id column exists
    $check_material_column = $conn->query("SHOW COLUMNS FROM products LIKE 'material_id'");
    $material_column_exists = ($check_material_column && $check_material_column->num_rows > 0);
    
    // Build update query based on available columns
    if ($material_column_exists) {
        $query = "UPDATE products SET 
                  product_name = '$product_name',
                  category_id = $category_id,
                  base_price = $base_price,
                  description = '$description',
                  is_customizable = $is_customizable,
                  material_id = " . ($material_id == 'NULL' ? 'NULL' : $material_id) . "
                  $image_update
                  WHERE product_id = $product_id";
    } else {
        $query = "UPDATE products SET 
                  product_name = '$product_name',
                  category_id = $category_id,
                  base_price = $base_price,
                  description = '$description',
                  is_customizable = $is_customizable
                  $image_update
                  WHERE product_id = $product_id";
    }
    
    if ($conn->query($query)) {
        
        // Update inventory if provided
        if (isset($_POST['quantity']) && isset($_POST['low_stock_threshold'])) {
            $quantity = (int)$_POST['quantity'];
            $threshold = (int)$_POST['low_stock_threshold'];
            
            // Check if inventory record exists
            $check_inventory = $conn->query("SELECT * FROM inventory WHERE product_id = $product_id");
            if ($check_inventory->num_rows > 0) {
                $conn->query("UPDATE inventory SET quantity = $quantity, low_stock_threshold = $threshold WHERE product_id = $product_id");
            } else {
                $conn->query("INSERT INTO inventory (product_id, quantity, low_stock_threshold) VALUES ($product_id, $quantity, $threshold)");
            }
        }
        
        // Log the action
        $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'edit_product', ?)";
        $log_stmt = $conn->prepare($log_query);
        $details = "Updated product: $product_name (ID: $product_id)";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        
        $_SESSION['success'] = "Product updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update product: " . $conn->error;
    }
    
    redirect("edit-product.php?id=$product_id");
    
} else {
    // If someone tries to access this file directly without POST
    redirect('products.php');
}
?>