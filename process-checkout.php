<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to checkout";
    header('Location: login.php');
    exit;
}

// Get POST data
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validate input
if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product";
    header('Location: collections.php');
    exit;
}

if ($quantity < 1 || $quantity > 99) {
    $_SESSION['error'] = "Invalid quantity";
    header('Location: collections.php');
    exit;
}

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Product not found";
    header('Location: collections.php');
    exit;
}

$product = $result->fetch_assoc();

// Store in session for checkout
$_SESSION['checkout_product'] = [
    'product_id' => $product_id,
    'quantity' => $quantity,
    'product_name' => $product['product_name'],
    'base_price' => $product['base_price'],
    'total' => $product['base_price'] * $quantity
];

// Redirect to checkout page
header('Location: checkout.php');
exit;
?>