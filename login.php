<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            redirect('index.php');
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Email not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · Furniverse</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0f0719;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated gradient background */
        .gradient-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(125deg, #0f0719 0%, #1a1035 50%, #2d1b4e 100%);
            z-index: -2;
        }

        /* Animated orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: rgba(147, 51, 234, 0.15);
            top: -250px;
            left: -100px;
            animation: float 20s infinite;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: rgba(236, 72, 153, 0.15);
            bottom: -200px;
            right: -100px;
            animation: float 25s infinite reverse;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: rgba(59, 130, 246, 0.15);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 15s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, 50px) scale(1.1); }
            50% { transform: translate(100px, -50px) scale(0.9); }
            75% { transform: translate(-50px, 100px) scale(1.05); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.5; transform: translate(-50%, -50%) scale(1.2); }
        }

        /* Main container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .split-layout {
            display: flex;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Left side - Branding */
        .brand-side {
            flex: 1;
            padding: 3rem;
            background: linear-gradient(145deg, rgba(147, 51, 234, 0.1), rgba(236, 72, 153, 0.1));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
            top: -50%;
            left: -50%;
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo {
            position: relative;
            z-index: 1;
        }

        .logo h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #e0b0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .brand-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: white;
            margin-bottom: 1.5rem;
        }

        .brand-content p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .feature-list li i {
            width: 24px;
            color: #c084fc;
        }

        .testimonial {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 2rem;
        }

        .testimonial p {
            color: white;
            font-style: italic;
            margin-bottom: 0.5rem;
        }

        .testimonial-author {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Right side - Form */
        .form-side {
            flex: 1;
            padding: 3rem;
            background: rgba(15, 7, 25, 0.7);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h2 {
            color: white;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-header p a {
            color: #c084fc;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .form-header p a:hover {
            color: #e0b0ff;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert i {
            font-size: 1.1rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .input-wrapper input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #c084fc;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(192, 132, 252, 0.1);
        }

        .input-wrapper input:focus + i {
            color: #c084fc;
        }

        .input-wrapper input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Form options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #c084fc;
            cursor: pointer;
        }

        .forgot-link {
            color: #c084fc;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: #e0b0ff;
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #9333ea, #ec4899);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(147, 51, 234, 0.5);
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        /* Divider */
        .divider {
            text-align: center;
            position: relative;
            margin: 2rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: calc(50% - 60px);
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .divider span {
            background: rgba(15, 7, 25, 0.7);
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Social login */
        .social-login {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .social-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #c084fc;
            color: #c084fc;
            transform: translateY(-3px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .split-layout {
                flex-direction: column;
            }
            
            .brand-side {
                padding: 2rem;
            }
            
            .brand-content h1 {
                font-size: 2.5rem;
            }
            
            .form-side {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="gradient-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="container">
        <div class="split-layout">
            <!-- Left side - Branding -->
            <div class="brand-side">
                <div class="logo">
                    <h2>✦ FURNIVERSE</h2>
                </div>
                
                <div class="brand-content">
                    <h1>Welcome Back to Your Dream Space</h1>
                    <p>Sign in to continue designing your perfect interior with our curated collection of modern furniture.</p>
                    
                    <ul class="feature-list">
                        <li><i class="fas fa-check-circle"></i> Exclusive member discounts</li>
                        <li><i class="fas fa-check-circle"></i> Save your favorite designs</li>
                        <li><i class="fas fa-check-circle"></i> Track your orders in real-time</li>
                        <li><i class="fas fa-check-circle"></i> Early access to new collections</li>
                    </ul>

                    <div class="testimonial">
                        <p>"Furniverse transformed my home into a sanctuary. The quality and design are unmatched."</p>
                        <div class="testimonial-author">— Sarah J., Interior Designer</div>
                    </div>
                </div>
            </div>

            <!-- Right side - Form -->
            <div class="form-side">
                <div class="form-header">
                    <h2>Sign In</h2>
                    <p>Don't have an account? <a href="register.php">Create one here</a></p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="far fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox"> Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-arrow-right-to-bracket" style="margin-right: 8px;"></i>
                        Sign In
                    </button>
                </form>

                <div class="divider">
                    <span>Or continue with</span>
                </div>

                <div class="social-login">
                    <button class="social-btn" onclick="window.location.href='#'"><i class="fab fa-google"></i></button>
                    <button class="social-btn" onclick="window.location.href='#'"><i class="fab fa-facebook-f"></i></button>
                    <button class="social-btn" onclick="window.location.href='#'"><i class="fab fa-apple"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add focus effects
        document.querySelectorAll('.input-wrapper input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>