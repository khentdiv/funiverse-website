<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'agay'); // Fixed: changed from furniversesss to furniverse_db

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 (better for emojis and special characters)
$conn->set_charset("utf8mb4");

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim(htmlspecialchars($data)));
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to get user data
function getUserData($user_id) {
    global $conn;
    try {
        $query = "SELECT user_id, full_name, email, phone, address, created_at, user_type FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log("Error in getUserData: " . $e->getMessage());
    }
    return null;
}

// Helper function for time ago
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) return 'N/A';
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M d, Y', $time);
        }
    }
}

// Function to get user customization count
function getUserCustomizationCount($user_id) {
    global $conn;
    try {
        $query = "SELECT COUNT(*) as count FROM customizations WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getUserCustomizationCount: " . $e->getMessage());
        return 0;
    }
}

// Function to get user order count
function getUserOrderCount($user_id) {
    global $conn;
    try {
        $query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getUserOrderCount: " . $e->getMessage());
        return 0;
    }
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount ?? 0, 2);
}

// Function to get user's full name or return 'User'
function getUserName() {
    if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        return $_SESSION['user_name'];
    } elseif (isset($_SESSION['user_id'])) {
        $user = getUserData($_SESSION['user_id']);
        if ($user && isset($user['full_name'])) {
            return $user['full_name'];
        }
    }
    return 'User';
}

// Function to display success/error messages
function displayMessage() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert error"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
}

// Function to check if table exists
function tableExists($table_name) {
    global $conn;
    try {
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to get site settings
function getSiteSettings() {
    return [
        'site_name' => 'Furniverse',
        'contact_email' => 'studio@furniverse.com',
        'contact_phone' => '+63 912 345 6789',
        'address' => 'Poblacion, Tupi, South Cotabato',
        'currency' => '₱',
        'gcash_number' => '0912 345 6789',
        'gcash_name' => 'Furniverse Studio'
    ];
}

// Function to get user by email
function getUserByEmail($email) {
    global $conn;
    try {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log("Error in getUserByEmail: " . $e->getMessage());
    }
    return null;
}

// Function to update last login
function updateLastLogin($user_id) {
    global $conn;
    try {
        $query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error in updateLastLogin: " . $e->getMessage());
    }
}

// Error handler for database errors
function handleDatabaseError($error) {
    error_log("Database Error: " . $error);
    if (isset($_SESSION)) {
        $_SESSION['error'] = "A database error occurred. Please try again later.";
    }
}

// Set timezone
date_default_timezone_set('Asia/Manila');
?>