<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_type'] == 'admin') {
        redirect('dashboard.php');
    } else {
        redirect('../index.php');
    }
}

// Secret admin registration key (store in environment variable in production)
define('ADMIN_SECRET_KEY', 'FURNIVERSE_ADMIN_2024');



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check rate limiting
    if ($_SESSION['register_attempts'] >= MAX_REGISTER_ATTEMPTS) {
        if (time() - $_SESSION['first_attempt_time'] < REGISTER_LOCKOUT_TIME) {
            $error = "Too many registration attempts. Please try again after " . 
                     ceil((REGISTER_LOCKOUT_TIME - (time() - $_SESSION['first_attempt_time'])) / 60) . " minutes.";
        } else {
            // Reset attempts
            $_SESSION['register_attempts'] = 0;
            $_SESSION['first_attempt_time'] = time();
        }
    }
    
    if (!isset($error)) {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid request origin.";
        } else {
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $admin_key = $_POST['admin_key'];
            $phone = sanitize($_POST['phone']);
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
                $_SESSION['register_attempts']++;
            }
            // Validate phone number (Philippine format)
            elseif (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
                $error = "Please enter a valid Philippine mobile number (e.g., 09123456789).";
                $_SESSION['register_attempts']++;
            }
            // Validate admin key
            elseif ($admin_key !== ADMIN_SECRET_KEY) {
                $error = "Invalid admin registration key!";
                $_SESSION['register_attempts']++;
            }
            // Validate password match
            elseif ($password !== $confirm_password) {
                $error = "Passwords do not match!";
                $_SESSION['register_attempts']++;
            }
            // Validate password strength
            elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long!";
            }
            elseif (!preg_match("/[A-Z]/", $password)) {
                $error = "Password must contain at least one uppercase letter!";
            }
            elseif (!preg_match("/[a-z]/", $password)) {
                $error = "Password must contain at least one lowercase letter!";
            }
            elseif (!preg_match("/[0-9]/", $password)) {
                $error = "Password must contain at least one number!";
            }
            elseif (!preg_match("/[^a-zA-Z0-9]/", $password)) {
                $error = "Password must contain at least one special character!";
            }
            else {
                // Check if email already exists
                $check_query = "SELECT user_id FROM users WHERE email = ? OR phone = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("ss", $email, $phone);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Email or phone number already registered!";
                } else {
                    // Hash password and create admin user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $user_type = 'admin';
                    $email_verified = 1; // Admin accounts auto-verified
                    
                    $query = "INSERT INTO users (full_name, email, password, phone, user_type, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssssi", $full_name, $email, $hashed_password, $phone, $user_type, $email_verified);
                    
                    if ($stmt->execute()) {
                        $admin_id = $conn->insert_id;
                        
                        // Reset attempts on success
                        $_SESSION['register_attempts'] = 0;
                        
                        // Get IP and user agent for logging
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        
                        // Check if admin_logs table exists before inserting
                        $check_table = $conn->query("SHOW TABLES LIKE 'admin_logs'");
                        if ($check_table->num_rows > 0) {
                            // Log the registration
                            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, 'register', 'New admin registered', ?, ?)";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param("iss", $admin_id, $ip, $user_agent);
                            $log_stmt->execute();
                        }
                        
                        // Send welcome email (if mail function is configured)
                        $to = $email;
                        $subject = "Welcome to Furniverse Admin Team";
                        $message = "
                        <html>
                        <head>
                            <title>Welcome to Furniverse Admin</title>
                            <style>
                                body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0a0f1e; color: #fff; }
                                .container { max-width: 600px; margin: 0 auto; padding: 40px; background: #0f172a; border-radius: 20px; }
                                h1 { color: #00ffd5; }
                                .btn { background: #00ffd5; color: #0f172a; padding: 12px 30px; text-decoration: none; border-radius: 10px; display: inline-block; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h1>Welcome to Furniverse Admin Team!</h1>
                                <p>Dear $full_name,</p>
                                <p>Your admin account has been successfully created. You can now log in to the admin panel using your credentials.</p>
                                <p><strong>Important Security Information:</strong></p>
                                <ul>
                                    <li>Never share your password with anyone</li>
                                    <li>Use a strong, unique password</li>
                                    <li>Enable two-factor authentication for additional security</li>
                                    <li>All admin actions are logged for security purposes</li>
                                </ul>
                                <p><a href='" . (defined('SITE_URL') ? SITE_URL : 'http://localhost/funiverses') . "/admin/login.php' class='btn'>Access Admin Panel</a></p>
                                <p>Best regards,<br>Furniverse Security Team</p>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        $headers .= "From: security@furniverse.com" . "\r\n";
                        
                        // Try to send email but don't stop if it fails
                        @mail($to, $subject, $message, $headers);
                        
                        $_SESSION['success'] = "Admin registration successful! You can now login.";
                        redirect('login.php');
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
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
    <title>Admin Registration | Furniverse</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
            overflow-x: hidden;
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

        .admin-register-container {
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

        /* Right side - Registration form */
        .register-side {
            padding: 40px;
            background: #0f172a;
            overflow-y: auto;
            max-height: 800px;
        }

        .register-header {
            margin-bottom: 30px;
        }

        .register-header h2 {
            font-size: 2rem;
            color: white;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .register-header p {
            color: #8a9bb5;
            font-size: 0.95rem;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 25px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #d1d9e8;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            background: #1a2639;
            border: 2px solid #2a3650;
            border-radius: 12px;
            font-size: 0.95rem;
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
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #5a6b89;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .input-wrapper input:focus + i {
            color: #00ffd5;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #5a6b89;
            transition: color 0.3s;
            z-index: 2;
        }

        .toggle-password:hover {
            color: #00ffd5;
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 4px;
            background: #2a3650;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s;
            border-radius: 2px;
        }

        .strength-text {
            font-size: 0.85rem;
            color: #8a9bb5;
        }

        /* Password requirements */
        .password-requirements {
            background: #1a2639;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
            border: 1px solid #2a3650;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #8a9bb5;
            font-size: 0.85rem;
        }

        .requirement i {
            width: 18px;
            font-size: 0.9rem;
        }

        .requirement.valid {
            color: #10b981;
        }

        .requirement.valid i {
            color: #10b981;
        }

        .requirement.invalid {
            color: #ef4444;
        }

        .requirement.invalid i {
            color: #ef4444;
        }

        /* Password match indicator */
        .password-match {
            margin-top: 8px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-match.match {
            color: #10b981;
        }

        .password-match.no-match {
            color: #ef4444;
        }

        /* Checkbox group */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 25px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: #00ffd5;
        }

        .checkbox-group label {
            color: #b4c0d0;
            font-size: 0.9rem;
            line-height: 1.5;
            cursor: pointer;
        }

        .checkbox-group a {
            color: #00ffd5;
            text-decoration: none;
            font-weight: 500;
        }

        .checkbox-group a:hover {
            text-decoration: underline;
        }

        /* Register button */
        .btn-register {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, #00ffd5, #00b8a9);
            border: none;
            border-radius: 12px;
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

        .btn-register::before {
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

        .btn-register:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 255, 213, 0.3);
        }

        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Register footer */
        .register-footer {
            margin-top: 25px;
            text-align: center;
        }

        .register-footer a {
            color: #00ffd5;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .register-footer a:hover {
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #0f172a;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid #2a3650;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #2a3650;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #1a2639;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header h2 {
            color: white;
            font-size: 1.5rem;
        }

        .modal-header .close {
            font-size: 28px;
            color: #8a9bb5;
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-header .close:hover {
            color: #00ffd5;
        }

        .modal-body {
            padding: 30px;
        }

        .terms-section {
            margin-bottom: 25px;
        }

        .terms-section h3 {
            color: #00ffd5;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .terms-section p, .terms-section ul {
            color: #b4c0d0;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .terms-section ul {
            padding-left: 20px;
        }

        .terms-section li {
            margin-bottom: 5px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #2a3650;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            background: #1a2639;
            position: sticky;
            bottom: 0;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #00ffd5;
            color: #0f172a;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 213, 0.3);
        }

        .btn-secondary {
            background: #2a3650;
            color: #b4c0d0;
        }

        .btn-secondary:hover {
            background: #3a4660;
        }

        /* Scrollbar styling */
        .register-side::-webkit-scrollbar,
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .register-side::-webkit-scrollbar-track,
        .modal-content::-webkit-scrollbar-track {
            background: #1a2639;
        }

        .register-side::-webkit-scrollbar-thumb,
        .modal-content::-webkit-scrollbar-thumb {
            background: #2a3650;
            border-radius: 4px;
        }

        .register-side::-webkit-scrollbar-thumb:hover,
        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #3a4660;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .admin-register-container {
                grid-template-columns: 1fr;
                max-width: 600px;
            }
            
            .brand-side {
                display: none;
            }
            
            .register-side {
                padding: 30px;
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 10px;
            }

            .register-side {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .register-header h2 {
                font-size: 1.75rem;
            }

            .modal-footer {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-register-container">
        <!-- Left side - Branding -->
        <div class="brand-side">
            <div class="brand-content">
                <div class="brand-logo">
                    <i class="fas fa-cubes"></i>
                    <h1>Furni<span>Verse</span></h1>
                    <p>Enterprise Administration</p>
                </div>

                <div class="brand-quote">
                    <h2>Become an<br>Administrator</h2>
                    <p>Join the elite team of Furniverse administrators and help shape the future of furniture e-commerce.</p>
                </div>

                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Full analytics access</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users-cog"></i>
                        <span>User management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-boxes"></i>
                        <span>Inventory control</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Order processing</span>
                    </div>
                </div>
            </div>

            <div class="brand-footer">
                <a href="#"><i class="far fa-question-circle"></i> Help Center</a>
                <a href="#"><i class="far fa-file-alt"></i> Documentation</a>
                <a href="#"><i class="fas fa-shield-alt"></i> Security</a>
            </div>
        </div>

        <!-- Right side - Registration form -->
        <div class="register-side">
            <div class="register-header">
                <h2>Create Admin Account</h2>
                <p>Fill in the details to register as an administrator</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['register_attempts']) && $_SESSION['register_attempts'] > 0): ?>
                <div class="alert warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Registration attempts: <?php echo $_SESSION['register_attempts']; ?>/<?php echo MAX_REGISTER_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>FULL NAME</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="full_name" 
                                   id="fullName" 
                                   required 
                                   placeholder="John Doe"
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                   pattern="[A-Za-z\s]{2,50}"
                                   title="Please enter a valid name (2-50 characters, letters and spaces only)">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>PHONE NUMBER</label>
                        <div class="input-wrapper">
                            <input type="tel" 
                                   name="phone" 
                                   id="phone" 
                                   required 
                                   placeholder="09123456789"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   pattern="^(09|\+639)\d{9}$"
                                   title="Please enter a valid Philippine mobile number (e.g., 09123456789)">
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <input type="email" 
                               name="email" 
                               id="email" 
                               required 
                               placeholder="admin@furniverse.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="far fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>ADMIN REGISTRATION KEY</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               name="admin_key" 
                               id="adminKey" 
                               required 
                               placeholder="Enter admin registration key">
                        <i class="fas fa-key"></i>
                        <i class="far fa-eye toggle-password" onclick="toggleKeyVisibility()"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>PASSWORD</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   required 
                                   placeholder="Create a strong password">
                            <i class="fas fa-lock"></i>
                            <i class="far fa-eye toggle-password" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                        </div>
                        
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-bar-fill" id="strengthBarFill"></div>
                            </div>
                            <span class="strength-text" id="strengthText">Enter a password</span>
                        </div>

                        <div class="password-requirements" id="passwordRequirements">
                            <div class="requirement" id="reqLength">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="reqUppercase">
                                <i class="fas fa-circle"></i> At least one uppercase letter
                            </div>
                            <div class="requirement" id="reqLowercase">
                                <i class="fas fa-circle"></i> At least one lowercase letter
                            </div>
                            <div class="requirement" id="reqNumber">
                                <i class="fas fa-circle"></i> At least one number
                            </div>
                            <div class="requirement" id="reqSpecial">
                                <i class="fas fa-circle"></i> At least one special character
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>CONFIRM PASSWORD</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirmPassword" 
                                   required 
                                   placeholder="Confirm your password">
                            <i class="fas fa-lock"></i>
                            <i class="far fa-eye toggle-password" onclick="toggleConfirmVisibility()"></i>
                        </div>
                        <div class="password-match" id="passwordMatch">
                            <i class="fas fa-info-circle"></i>
                            <span>Re-enter your password</span>
                        </div>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms">
                        I agree to the <a href="#" onclick="showTermsModal(); return false;">Terms and Conditions</a> and 
                        <a href="#" onclick="showPrivacyModal(); return false;">Privacy Policy</a>. I understand that all admin actions are logged and monitored.
                    </label>
                </div>

                <button type="submit" class="btn-register" id="registerBtn">
                    <i class="fas fa-user-shield"></i> Create Admin Account
                </button>

                <div class="register-footer">
                    <div class="divider">
                        <span>Already have an account?</span>
                    </div>
                    
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign in to admin panel</a>
                    
                    <div style="margin-top: 15px;">
                        <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to main site</a>
                    </div>
                </div>

                <?php if (isset($_SESSION['register_attempts']) && $_SESSION['register_attempts'] > 2): ?>
                <div class="attempts-counter">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo MAX_REGISTER_ATTEMPTS - $_SESSION['register_attempts']; ?> registration attempts remaining</span>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal" id="termsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Administrator Terms & Conditions</h2>
                <span class="close" onclick="hideTermsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="terms-section">
                    <h3>1. Account Security</h3>
                    <p>As an administrator, you are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to:</p>
                    <ul>
                        <li>Use a strong, unique password that is not shared with any other service</li>
                        <li>Enable two-factor authentication when available</li>
                        <li>Immediately report any unauthorized access to your account</li>
                        <li>Not share your login credentials with anyone</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>2. Acceptable Use</h3>
                    <p>Admin accounts may only be used for legitimate business purposes related to managing the Furniverse platform. Prohibited activities include:</p>
                    <ul>
                        <li>Accessing or modifying user data without authorization</li>
                        <li>Using admin privileges for personal gain</li>
                        <li>Sharing confidential business information</li>
                        <li>Attempting to bypass security measures</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>3. Data Protection & Privacy</h3>
                    <p>You agree to protect all user data in accordance with data protection laws and Furniverse policies:</p>
                    <ul>
                        <li>Handle all user information with confidentiality</li>
                        <li>Only access user data when necessary for legitimate business purposes</li>
                        <li>Report any data breaches or security incidents immediately</li>
                        <li>Comply with all applicable privacy laws and regulations</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>4. Monitoring & Accountability</h3>
                    <p>All admin actions are logged and monitored for security and accountability purposes. This includes:</p>
                    <ul>
                        <li>Login attempts and session activities</li>
                        <li>Changes to user accounts, products, and orders</li>
                        <li>Access to sensitive information</li>
                        <li>System configuration changes</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>5. Compliance & Legal</h3>
                    <p>You must comply with all applicable laws, regulations, and Furniverse policies regarding:</p>
                    <ul>
                        <li>E-commerce regulations and consumer protection</li>
                        <li>Data protection and privacy laws</li>
                        <li>Intellectual property rights</li>
                        <li>Anti-fraud and security measures</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>6. Account Termination</h3>
                    <p>Super administrators reserve the right to suspend or terminate admin accounts for:</p>
                    <ul>
                        <li>Violation of these terms and conditions</li>
                        <li>Suspicious or unauthorized activities</li>
                        <li>Extended periods of inactivity</li>
                        <li>Security concerns or policy violations</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="acceptTerms()">Accept Terms</button>
                <button class="btn btn-secondary" onclick="hideTermsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal" id="privacyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Privacy Policy for Administrators</h2>
                <span class="close" onclick="hidePrivacyModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="terms-section">
                    <h3>Information We Collect</h3>
                    <p>As an administrator, we collect and process the following information:</p>
                    <ul>
                        <li><strong>Personal Information:</strong> Full name, email address, phone number</li>
                        <li><strong>Account Credentials:</strong> Hashed password, security questions, 2FA settings</li>
                        <li><strong>Activity Logs:</strong> Login times, IP addresses, actions performed, pages accessed</li>
                        <li><strong>Device Information:</strong> Browser type, operating system, device identifiers</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>How We Use Your Information</h3>
                    <p>Your information is used for the following purposes:</p>
                    <ul>
                        <li>Verify your identity and authorization level</li>
                        <li>Maintain security and audit trails for compliance</li>
                        <li>Communicate important system updates and security alerts</li>
                        <li>Investigate security incidents or policy violations</li>
                        <li>Improve and optimize admin panel functionality</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>Data Security Measures</h3>
                    <p>We implement industry-standard security measures to protect your information:</p>
                    <ul>
                        <li>256-bit SSL/TLS encryption for all data transmission</li>
                        <li>Argon2id password hashing with unique salts</li>
                        <li>Regular security audits and penetration testing</li>
                        <li>Strict access controls and authentication requirements</li>
                        <li>Real-time monitoring for suspicious activities</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>Data Retention</h3>
                    <p>We retain admin account information and activity logs for:</p>
                    <ul>
                        <li>Active accounts: Indefinitely while account is active</li>
                        <li>Activity logs: Minimum of 3 years for security and compliance</li>
                        <li>Deleted accounts: Anonymized data retained for 1 year</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>Your Rights</h3>
                    <p>As an administrator, you have the following rights regarding your data:</p>
                    <ul>
                        <li>Access and review your personal information</li>
                        <li>Request corrections to inaccurate data</li>
                        <li>Export your data in a portable format</li>
                        <li>Request account deletion (subject to legal requirements)</li>
                        <li>Opt-out of non-essential communications</li>
                    </ul>
                </div>

                <div class="terms-section">
                    <h3>Contact Information</h3>
                    <p>For privacy-related concerns or questions, contact our Data Protection Officer:</p>
                    <ul>
                        <li>Email: privacy@furniverse.com</li>
                        <li>Phone: +63 (2) 1234 5678</li>
                        <li>Address: 123 Business Park, Makati City, Philippines</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="acceptPrivacy()">I Understand</button>
                <button class="btn btn-secondary" onclick="hidePrivacyModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBarFill = document.getElementById('strengthBarFill');
        const strengthText = document.getElementById('strengthText');
        
        const reqLength = document.getElementById('reqLength');
        const reqUppercase = document.getElementById('reqUppercase');
        const reqLowercase = document.getElementById('reqLowercase');
        const reqNumber = document.getElementById('reqNumber');
        const reqSpecial = document.getElementById('reqSpecial');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);
            
            // Update requirement icons
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUppercase, hasUppercase);
            updateRequirement(reqLowercase, hasLowercase);
            updateRequirement(reqNumber, hasNumber);
            updateRequirement(reqSpecial, hasSpecial);
            
            // Calculate strength
            const requirements = [hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial];
            const strength = requirements.filter(Boolean).length * 20;
            
            // Update strength bar
            strengthBarFill.style.width = strength + '%';
            
            // Update strength color and text
            if (strength === 0) {
                strengthBarFill.style.background = '#ef4444';
                strengthText.textContent = 'Very Weak';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 40) {
                strengthBarFill.style.background = '#ef4444';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 60) {
                strengthBarFill.style.background = '#f59e0b';
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#f59e0b';
            } else if (strength <= 80) {
                strengthBarFill.style.background = '#3b82f6';
                strengthText.textContent = 'Good';
                strengthText.style.color = '#3b82f6';
            } else {
                strengthBarFill.style.background = '#10b981';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#10b981';
            }
        });

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                icon.className = 'fas fa-check-circle';
            } else {
                element.classList.add('invalid');
                element.classList.remove('valid');
                icon.className = 'fas fa-circle';
            }
        }

        // Password match checker
        const confirmPassword = document.getElementById('confirmPassword');
        const passwordMatch = document.getElementById('passwordMatch');

        confirmPassword.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirm = this.value;
            
            if (confirm.length === 0) {
                passwordMatch.className = 'password-match';
                passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i><span>Re-enter your password</span>';
            } else if (password === confirm) {
                passwordMatch.className = 'password-match match';
                passwordMatch.innerHTML = '<i class="fas fa-check-circle"></i><span>Passwords match</span>';
            } else {
                passwordMatch.className = 'password-match no-match';
                passwordMatch.innerHTML = '<i class="fas fa-times-circle"></i><span>Passwords do not match</span>';
            }
        });

        // Toggle password visibility functions
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('#togglePassword');
            
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

        function toggleConfirmVisibility() {
            const confirmInput = document.getElementById('confirmPassword');
            const toggleIcon = document.querySelectorAll('.toggle-password')[2];
            
            if (confirmInput.type === 'password') {
                confirmInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                confirmInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function toggleKeyVisibility() {
            const keyInput = document.getElementById('adminKey');
            const toggleIcon = document.querySelectorAll('.toggle-password')[0];
            
            if (keyInput.type === 'password') {
                keyInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                keyInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmPassword.value;
            const terms = document.getElementById('terms').checked;
            
            // Check password match
            if (password !== confirm) {
                e.preventDefault();
                showNotification('Passwords do not match!', 'error');
                return;
            }
            
            // Check password strength
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);
            
            if (!(hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial)) {
                e.preventDefault();
                showNotification('Please meet all password requirements!', 'error');
                return;
            }
            
            // Check terms
            if (!terms) {
                e.preventDefault();
                showNotification('You must agree to the terms and conditions', 'error');
                return;
            }
            
            // Add loading state
            const registerBtn = document.getElementById('registerBtn');
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
        });

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert ${type}`;
            notification.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>${message}`;
            
            const registerSide = document.querySelector('.register-side');
            registerSide.insertBefore(notification, registerSide.firstChild.nextSibling);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Modal functions
        function showTermsModal() {
            document.getElementById('termsModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideTermsModal() {
            document.getElementById('termsModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function showPrivacyModal() {
            document.getElementById('privacyModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hidePrivacyModal() {
            document.getElementById('privacyModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function acceptTerms() {
            document.getElementById('terms').checked = true;
            hideTermsModal();
            showNotification('Terms accepted successfully', 'success');
        }

        function acceptPrivacy() {
            hidePrivacyModal();
            showNotification('Privacy policy acknowledged', 'success');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const termsModal = document.getElementById('termsModal');
            const privacyModal = document.getElementById('privacyModal');
            
            if (event.target === termsModal) {
                hideTermsModal();
            }
            if (event.target === privacyModal) {
                hidePrivacyModal();
            }
        }

        // Input validation for phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d+]/g, '');
            if (value.length > 13) value = value.slice(0, 13);
            e.target.value = value;
        });

        // Input validation for name
        document.getElementById('fullName').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^A-Za-z\s]/g, '');
            e.target.value = value;
        });

        // Auto-focus first field
        window.addEventListener('load', function() {
            document.getElementById('fullName').focus();
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
    </script>
</body>
</html>