<?php
session_start();

// Log the logout if user was admin
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
    require_once '../config.php';
    
    $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'logout', 'Admin logged out')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("i", $_SESSION['user_id']);
    $log_stmt->execute();
}

// Destroy session
session_destroy();

// Redirect to admin login
header("Location: login.php");
exit();
?>