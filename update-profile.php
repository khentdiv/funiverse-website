<?php
require_once 'config.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to update your profile";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    // Check if email already exists for another user
    $check_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Email is already in use by another account";
    }
    
    // If no errors, update the database
    if (empty($errors)) {
        $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
        
        if ($update_stmt->execute()) {
            // Update session name
            $_SESSION['user_name'] = $full_name;
            
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update profile. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("\\n", $errors);
    }
    
    // Redirect back to dashboard with the profile tab active
    redirect('dashboard.php#profile');
} else {
    // If not POST request, redirect to dashboard
    redirect('dashboard.php');
}
?>