<?php
require_once '../config.php';

// Define constants at the top
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds

// Redirect if already logged in as admin
if (isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
    redirect('dashboard.php');
}



// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = sanitize($_COOKIE['remember_token']);
    $query = "SELECT u.* FROM users u JOIN user_tokens t ON u.user_id = t.user_id 
              WHERE t.token = ? AND t.expires_at > NOW() AND u.user_type = 'admin' AND u.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['login_time'] = time();
        
        // Log auto-login
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Check if admin_logs table exists before inserting
        $check_table = $conn->query("SHOW TABLES LIKE 'admin_logs'");
        if ($check_table->num_rows > 0) {
            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                         VALUES (?, 'auto_login', 'Auto-login via remember me', ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("iss", $user['user_id'], $ip, $user_agent);
            $log_stmt->execute();
        }
        
        redirect('dashboard.php');
    } else {
        // Clear invalid cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check rate limiting
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $_SESSION['first_attempt_time'] < LOCKOUT_TIME) {
            $error = "Too many failed login attempts. Please try again after " . 
                     ceil((LOCKOUT_TIME - (time() - $_SESSION['first_attempt_time'])) / 60) . " minutes.";
        } else {
            // Reset attempts
            $_SESSION['login_attempts'] = 0;
            $_SESSION['first_attempt_time'] = time();
        }
    }
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request origin.";
    } elseif (!isset($error)) {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $_SESSION['login_attempts']++;
        } else {
            // Check if account is locked - with error handling for missing column
            try {
                // First check if locked_until column exists
                $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'locked_until'");
                if ($check_column->num_rows > 0) {
                    $lock_check = "SELECT locked_until FROM users WHERE email = ? AND user_type = 'admin'";
                    $lock_stmt = $conn->prepare($lock_check);
                    $lock_stmt->bind_param("s", $email);
                    $lock_stmt->execute();
                    $lock_result = $lock_stmt->get_result();
                    
                    if ($lock_result->num_rows == 1) {
                        $lock_data = $lock_result->fetch_assoc();
                        if ($lock_data['locked_until'] && strtotime($lock_data['locked_until']) > time()) {
                            $lock_time = strtotime($lock_data['locked_until']) - time();
                            $minutes = ceil($lock_time / 60);
                            $error = "Account is locked. Please try again after $minutes minutes.";
                        }
                    }
                }
            } catch (Exception $e) {
                // Column doesn't exist, continue without lock check
                error_log("Locked_until column check failed: " . $e->getMessage());
            }
            
            if (!isset($error)) {
                $query = "SELECT * FROM users WHERE email = ? AND user_type = 'admin'";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if account is active (if status column exists)
                    try {
                        if (isset($user['status']) && $user['status'] == 'inactive') {
                            $error = "Your account has been deactivated. Please contact super admin.";
                        }
                    } catch (Exception $e) {
                        // Status column doesn't exist, continue
                    }
                    
                    if (!isset($error) && password_verify($password, $user['password'])) {
                        // Check if password needs rehash (using default algorithm if ARGON2ID not available)
                        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            $rehash_query = "UPDATE users SET password = ? WHERE user_id = ?";
                            $rehash_stmt = $conn->prepare($rehash_query);
                            $rehash_stmt->bind_param("si", $new_hash, $user['user_id']);
                            $rehash_stmt->execute();
                        }
                        
                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;
                        
                        // Update last login info (if columns exist)
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        
                        try {
                            $update_query = "UPDATE users SET last_login = NOW()";
                            
                            // Check which columns exist
                            $columns = $conn->query("SHOW COLUMNS FROM users");
                            $existing_columns = [];
                            while ($col = $columns->fetch_assoc()) {
                                $existing_columns[] = $col['Field'];
                            }
                            
                            if (in_array('last_login_ip', $existing_columns)) {
                                $update_query .= ", last_login_ip = ?";
                            }
                            if (in_array('last_login_user_agent', $existing_columns)) {
                                $update_query .= ", last_login_user_agent = ?";
                            }
                            
                            $update_query .= " WHERE user_id = ?";
                            
                            $update_stmt = $conn->prepare($update_query);
                            
                            if (in_array('last_login_ip', $existing_columns) && in_array('last_login_user_agent', $existing_columns)) {
                                $update_stmt->bind_param("ssi", $ip, $user_agent, $user['user_id']);
                            } elseif (in_array('last_login_ip', $existing_columns)) {
                                $update_stmt->bind_param("si", $ip, $user['user_id']);
                            } elseif (in_array('last_login_user_agent', $existing_columns)) {
                                $update_stmt->bind_param("si", $user_agent, $user['user_id']);
                            } else {
                                $update_stmt->bind_param("i", $user['user_id']);
                            }
                            
                            $update_stmt->execute();
                        } catch (Exception $e) {
                            // Columns don't exist, continue without update
                            error_log("Last login update failed: " . $e->getMessage());
                        }
                        
                        // Set session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['login_time'] = time();
                        
                        // Set remember me
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                            
                            // Check if user_tokens table exists
                            $check_table = $conn->query("SHOW TABLES LIKE 'user_tokens'");
                            if ($check_table->num_rows > 0) {
                                // Delete old tokens
                                $delete_query = "DELETE FROM user_tokens WHERE user_id = ?";
                                $delete_stmt = $conn->prepare($delete_query);
                                $delete_stmt->bind_param("i", $user['user_id']);
                                $delete_stmt->execute();
                                
                                // Insert new token
                                $token_query = "INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
                                $token_stmt = $conn->prepare($token_query);
                                $token_stmt->bind_param("iss", $user['user_id'], $token, $expires);
                                $token_stmt->execute();
                                
                                // Set cookie
                                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', isset($_SERVER['HTTPS']), true);
                            }
                        }
                        
                        // Log successful login
                        $check_table = $conn->query("SHOW TABLES LIKE 'admin_logs'");
                        if ($check_table->num_rows > 0) {
                            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                                         VALUES (?, 'login', 'Admin logged in successfully', ?, ?)";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param("iss", $user['user_id'], $ip, $user_agent);
                            $log_stmt->execute();
                        }
                        
                        // Redirect to dashboard
                        $_SESSION['success'] = "Welcome back, " . $user['full_name'] . "!";
                        redirect('dashboard.php');
                    } else {
                        $error = "Invalid password!";
                        $_SESSION['login_attempts']++;
                        
                        // Log failed attempt
                        $check_table = $conn->query("SHOW TABLES LIKE 'admin_logs'");
                        if ($check_table->num_rows > 0) {
                            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                                         VALUES (?, 'failed_login', 'Failed login attempt', ?, ?)";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param("iss", $user['user_id'], $ip, $user_agent);
                            $log_stmt->execute();
                        }
                        
                        // Lock account after too many attempts
                        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                            try {
                                $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                                $lock_query = "UPDATE users SET locked_until = ? WHERE user_id = ?";
                                $lock_stmt = $conn->prepare($lock_query);
                                $lock_stmt->bind_param("si", $lock_until, $user['user_id']);
                                $lock_stmt->execute();
                            } catch (Exception $e) {
                                // locked_until column doesn't exist
                                error_log("Failed to lock account: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $error = "Admin account not found!";
                    $_SESSION['login_attempts']++;
                }
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Furniverse</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Copy all the CSS from the previous response here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0a0f1e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 20% 50%, rgba(42, 82, 152, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 50%, rgba(0, 168, 150, 0.1) 0%, transparent 50%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .admin-login-container {
            width: 100%;
            max-width: 1400px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(18, 25, 40, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Left side - Branding */
        .brand-side {
            background: linear-gradient(145deg, #1a2639, #0f172a);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 255, 213, 0.03) 0%, transparent 70%);
            animation: pulse 10s ease infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            margin-bottom: 60px;
        }

        .brand-logo i {
            font-size: 50px;
            color: #00ffd5;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(0, 255, 213, 0.3));
        }

        .brand-logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .brand-logo span {
            color: #00ffd5;
        }

        .brand-logo p {
            color: #8a9bb5;
            font-size: 1rem;
            font-weight: 300;
        }

        .brand-quote {
            margin: 60px 0;
        }

        .brand-quote h2 {
            font-size: 2rem;
            font-weight: 600;
            color: white;
            line-height: 1.3;
            margin-bottom: 20px;
        }

        .brand-quote p {
            color: #8a9bb5;
            font-size: 1rem;
            line-height: 1.6;
        }

        .feature-list {
            margin: 40px 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #b4c0d0;
        }

        .feature-item i {
            width: 40px;
            height: 40px;
            background: rgba(0, 255, 213, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #00ffd5;
            font-size: 1.2rem;
        }

        .brand-footer {
            color: #5a6b89;
            font-size: 0.9rem;
            display: flex;
            gap: 30px;
        }

        .brand-footer a {
            color: #8a9bb5;
            text-decoration: none;
            transition: color 0.3s;
        }

        .brand-footer a:hover {
            color: #00ffd5;
        }

        /* Right side - Login form */
        .login-side {
            padding: 60px;
            background: #0f172a;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 2rem;
            color: white;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #8a9bb5;
            font-size: 0.95rem;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            color: #fecaca;
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            color: #a7f3d0;
        }

        .alert.warning {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            color: #fde68a;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #d1d9e8;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            background: #1a2639;
            border: 2px solid #2a3650;
            border-radius: 16px;
            font-size: 1rem;
            color: white;
            transition: all 0.3s;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #00ffd5;
            box-shadow: 0 0 0 4px rgba(0, 255, 213, 0.1);
            background: #1f2c42;
        }

        .input-wrapper input::placeholder {
            color: #5a6b89;
        }

        .input-wrapper i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #5a6b89;
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        .input-wrapper input:focus + i {
            color: #00ffd5;
        }

        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #5a6b89;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #00ffd5;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            background: #1a2639;
            border: 2px solid #2a3650;
            border-radius: 6px;
            accent-color: #00ffd5;
        }

        .checkbox-group label {
            color: #b4c0d0;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, #00ffd5, #00b8a9);
            border: none;
            border-radius: 16px;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 255, 213, 0.3);
        }

        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .login-footer {
            margin-top: 30px;
            text-align: center;
        }

        .login-footer a {
            color: #00ffd5;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .login-footer a:hover {
            color: white;
            transform: translateX(5px);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #2a3650;
            margin: 25px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #2a3650;
        }

        .attempts-counter {
            margin-top: 20px;
            padding: 12px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 12px;
            color: #fecaca;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            padding: 20px;
            background: #1a2639;
            border-radius: 16px;
            border: 1px solid #2a3650;
        }

        .badge-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #8a9bb5;
            font-size: 0.9rem;
        }

        .badge-item i {
            color: #00ffd5;
        }

        @media (max-width: 1024px) {
            .admin-login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .brand-side {
                display: none;
            }
            
            .login-side {
                padding: 40px;
            }
        }

        @media (max-width: 640px) {
            .login-side {
                padding: 30px 20px;
            }
            
            .security-badge {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <!-- Left side - Branding -->
        <div class="brand-side">
            <div class="brand-content">
                <div class="brand-logo">
                    <i class="fas fa-cubes"></i>
                    <h1>Furni<span>Verse</span></h1>
                    <p>Enterprise Administration</p>
                </div>

                <div class="brand-quote">
                    <h2>Welcome Back,<br>Administrator</h2>
                    <p>Access the complete suite of management tools and analytics to power your business forward.</p>
                </div>

                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Real-time analytics & reporting</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Advanced security protocols</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-rocket"></i>
                        <span>High-performance dashboard</span>
                    </div>
                </div>
            </div>

            <div class="brand-footer">
                <a href="#"><i class="far fa-question-circle"></i> Help Center</a>
                <a href="#"><i class="far fa-file-alt"></i> Documentation</a>
                <a href="#"><i class="fas fa-lock"></i> Privacy</a>
            </div>
        </div>

        <!-- Right side - Login form -->
        <div class="login-side">
            <div class="login-header">
                <h2>Secure Access</h2>
                <p>Enter your credentials to access the admin panel</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0): ?>
                <div class="alert warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed attempts: <?php echo $_SESSION['login_attempts']; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <input type="email" 
                               name="email" 
                               id="email" 
                               required 
                               placeholder="admin@furniverse.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               autocomplete="email"
                               autofocus>
                        <i class="far fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>PASSWORD</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required 
                               placeholder="Enter your password"
                               autocomplete="current-password">
                        <i class="fas fa-lock"></i>
                        <i class="far fa-eye toggle-password" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Keep me signed in on this device</label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-arrow-right"></i> Access Dashboard
                </button>

                <div class="login-footer">
                    <a href="forgot-password.php"><i class="fas fa-key"></i> Forgot password?</a>
                    
                    <div class="divider">
                        <span>New Administrator?</span>
                    </div>
                    
                    <a href="register.php" style="font-size: 1rem;"><i class="fas fa-user-plus"></i> Create admin account</a>
                    
                    <div style="margin-top: 20px;">
                        <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to main site</a>
                    </div>
                </div>

                <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 2): ?>
                <div class="attempts-counter">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts']; ?> attempts remaining</span>
                </div>
                <?php endif; ?>
            </form>

            <div class="security-badge">
                <div class="badge-item">
                    <i class="fas fa-lock"></i>
                    <span>256-bit SSL</span>
                </div>
                <div class="badge-item">
                    <i class="fas fa-fingerprint"></i>
                    <span>2FA Ready</span>
                </div>
                <div class="badge-item">
                    <i class="fas fa-clock"></i>
                    <span>30m Session</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Loading state on form submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Basic validation
            if (!email || !password) {
                e.preventDefault();
                showNotification('Please fill in all fields', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showNotification('Please enter a valid email address', 'error');
                return;
            }
            
            // Add loading state
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
        });

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert ${type}`;
            notification.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>${message}`;
            
            const loginSide = document.querySelector('.login-side');
            loginSide.insertBefore(notification, loginSide.firstChild.nextSibling);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto-focus email field
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Enter key submits form
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }
        });

        // Smooth hover effects
        const inputs = document.querySelectorAll('.input-wrapper input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Dynamic background effect
        document.addEventListener('mousemove', function(e) {
            const moveX = (e.clientX / window.innerWidth) * 20;
            const moveY = (e.clientY / window.innerHeight) * 20;
            
            document.querySelector('.brand-side').style.transform = 
                `translate(${moveX}px, ${moveY}px)`;
        });