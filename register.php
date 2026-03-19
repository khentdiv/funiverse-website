<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);

    $query = "INSERT INTO users (full_name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $full_name, $email, $password, $phone, $address);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Please login.";
        redirect('login.php');
    } else {
        $error = "Registration failed. Email might already exist.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up · Furniverse</title>
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
            right: -100px;
            animation: float 20s infinite;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: rgba(236, 72, 153, 0.15);
            bottom: -200px;
            left: -100px;
            animation: float 25s infinite reverse;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: rgba(59, 130, 246, 0.15);
            top: 30%;
            right: 20%;
            animation: pulse 15s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, 50px) scale(1.1); }
            50% { transform: translate(100px, -50px) scale(0.9); }
            75% { transform: translate(-50px, 100px) scale(1.05); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        /* Main container */
        .container {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        /* Left side - Info Cards */
        .info-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
            animation: slideInLeft 0.6s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            border-color: rgba(192, 132, 252, 0.3);
            box-shadow: 0 20px 40px -15px rgba(147, 51, 234, 0.3);
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .info-card:nth-child(2) {
            animation-delay: 0.2s;
            opacity: 0;
            animation-fill-mode: forwards;
        }

        .info-card:nth-child(3) {
            animation-delay: 0.4s;
            opacity: 0;
            animation-fill-mode: forwards;
        }

        .info-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(147, 51, 234, 0.2), rgba(236, 72, 153, 0.2));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .info-icon i {
            font-size: 2rem;
            color: #c084fc;
        }

        .info-card h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
        }

        /* Right side - Form */
        .form-side {
            flex: 1;
            background: rgba(15, 7, 25, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideInRight 0.6s ease;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h2 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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

        /* Form grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
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
            z-index: 1;
        }

        .input-wrapper input,
        .input-wrapper textarea {
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

        .input-wrapper textarea {
            min-height: 100px;
            resize: vertical;
            padding-top: 1rem;
        }

        .input-wrapper input:focus,
        .input-wrapper textarea:focus {
            outline: none;
            border-color: #c084fc;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(192, 132, 252, 0.1);
        }

        .input-wrapper input:focus + i,
        .input-wrapper textarea:focus + i {
            color: #c084fc;
        }

        .input-wrapper input::placeholder,
        .input-wrapper textarea::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Password strength */
        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bars {
            display: flex;
            gap: 0.3rem;
            margin-bottom: 0.3rem;
        }

        .strength-bar {
            height: 4px;
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            transition: all 0.3s;
        }

        .strength-bar.active:nth-child(1) { background: #ef4444; }
        .strength-bar.active:nth-child(2) { background: #f59e0b; }
        .strength-bar.active:nth-child(3) { background: #10b981; }
        .strength-bar.active:nth-child(4) { background: #3b82f6; }

        .strength-text {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Password match */
        .password-match {
            font-size: 0.8rem;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .match-success {
            color: #10b981;
        }

        .match-error {
            color: #ef4444;
        }

        /* Terms checkbox */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .terms-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #c084fc;
            cursor: pointer;
        }

        .terms-checkbox a {
            color: #c084fc;
            text-decoration: none;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
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

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
        }

        .login-link a {
            color: #c084fc;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
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
        <!-- Left side - Info Cards -->
        <div class="info-side">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-couch"></i>
                </div>
                <h3>Curated Collections</h3>
                <p>Access our exclusive catalog of hand-picked furniture pieces from top designers worldwide.</p>
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>Free Delivery</h3>
                <p>Enjoy free shipping on all orders above ₹50,000. Fast and reliable delivery to your doorstep.</p>
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>2-Year Warranty</h3>
                <p>All our products come with a comprehensive warranty for your peace of mind.</p>
            </div>
        </div>

        <!-- Right side - Registration Form -->
        <div class="form-side">
            <div class="form-header">
                <h2>Create Account</h2>
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <div class="input-wrapper">
                            <i class="far fa-user"></i>
                            <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <i class="far fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="john@example.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Create password" required onkeyup="checkPasswordStrength(this.value)">
                        </div>
                        <div class="password-strength">
                            <div class="strength-bars">
                                <div class="strength-bar" id="bar1"></div>
                                <div class="strength-bar" id="bar2"></div>
                                <div class="strength-bar" id="bar3"></div>
                                <div class="strength-bar" id="bar4"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Enter a password</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required onkeyup="checkPasswordMatch()">
                        </div>
                        <div class="password-match" id="passwordMatchIndicator"></div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" name="phone" placeholder="+91 98765 43210" required>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Delivery Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-map-marker-alt"></i>
                            <textarea id="address" name="address" placeholder="Enter your complete address" required></textarea>
                        </div>
                    </div>
                </div>

                <label class="terms-checkbox">
                    <input type="checkbox" required> I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </label>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-user-plus" style="margin-right: 8px;"></i>
                    Create Account
                </button>
            </form>

            <div class="login-link">
                <i class="fas fa-arrow-left" style="margin-right: 5px;"></i>
                Back to <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        function checkPasswordStrength(password) {
            const bars = [
                document.getElementById('bar1'),
                document.getElementById('bar2'),
                document.getElementById('bar3'),
                document.getElementById('bar4')
            ];
            const strengthText = document.getElementById('strengthText');
            
            bars.forEach(bar => bar.classList.remove('active'));
            
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/) || password.match(/[$@#&!]+/)) strength += 1;
            
            for (let i = 0; i < strength; i++) {
                if (bars[i]) bars[i].classList.add('active');
            }
            
            if (password.length === 0) {
                strengthText.textContent = 'Enter a password';
            } else if (strength === 1) {
                strengthText.textContent = 'Weak password';
            } else if (strength === 2) {
                strengthText.textContent = 'Fair password';
            } else if (strength === 3) {
                strengthText.textContent = 'Good password';
            } else if (strength === 4) {
                strengthText.textContent = 'Strong password';
            }
            
            checkPasswordMatch();
        }

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const indicator = document.getElementById('passwordMatchIndicator');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirm.length === 0) {
                indicator.innerHTML = '';
                if (submitBtn) submitBtn.disabled = false;
            } else if (password === confirm) {
                indicator.innerHTML = '<i class="fas fa-check-circle match-success"></i> <span class="match-success">Passwords match</span>';
                if (submitBtn) submitBtn.disabled = false;
            } else {
                indicator.innerHTML = '<i class="fas fa-exclamation-circle match-error"></i> <span class="match-error">Passwords do not match</span>';
                if (submitBtn) submitBtn.disabled = true;
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const terms = document.querySelector('.terms-checkbox input[type="checkbox"]');
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (!terms.checked) {
                e.preventDefault();
                alert('Please agree to the Terms of Service and Privacy Policy');
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="loading"></span> Creating Account...';
            submitBtn.disabled = true;
        });

        // Input focus effects
        document.querySelectorAll('.input-wrapper input, .input-wrapper textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,5})(\d{0,4})/);
            if (x) {
                e.target.value = !x[2] ? x[1] : x[1] + ' ' + x[2] + (x[3] ? ' ' + x[3] : '');
            }
        });
    </script>
</body>
</html>