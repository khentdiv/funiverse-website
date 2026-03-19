<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_draft'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $material_id = isset($_POST['material_id']) ? (int)$_POST['material_id'] : null;
    $dimensions = isset($_POST['dimensions']) ? sanitize($_POST['dimensions']) : '';
    $color = isset($_POST['color']) ? sanitize($_POST['color']) : '';
    $total_price = isset($_POST['total_price']) ? (float)$_POST['total_price'] : 0;

    $query = "INSERT INTO customizations (user_id, product_id, material_id, dimensions, color, total_price, status) 
              VALUES (?, ?, ?, ?, ?, ?, 'draft')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiissd", $user_id, $product_id, $material_id, $dimensions, $color, $total_price);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'customization_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>