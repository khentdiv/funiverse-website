<?php
// Start session at the VERY BEGINNING before any output
session_start();

// Check if logout is confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get user data before any HTML output
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

// Now it's safe to output HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout · Furniverse modern studio</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: structured & elegant -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0b17;
            color: #e2ddf2;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(207, 176, 135, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(186, 166, 217, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .logout-container {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 60px;
            padding: 3rem;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 60px -20px #000000;
            position: relative;
            z-index: 10;
            animation: fadeInUp 0.6s cubic-bezier(0.075, 0.82, 0.165, 1);
            backdrop-filter: blur(10px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            background: #2d2640;
            border: 2px solid #cfb087;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 0 30px rgba(207, 176, 135, 0.2);
            position: relative;
        }

        .logout-icon::before {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            background: linear-gradient(135deg, #cfb087, #bba6d9);
            opacity: 0.3;
            z-index: -1;
        }

        .logout-icon i {
            font-size: 3.5rem;
            color: #cfb087;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, #f0e6d2, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: #b3a4cb;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            font-weight: 300;
            line-height: 1.6;
        }

        .user-card {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 40px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2d2640, #1e192c);
            border: 2px solid #cfb087;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar i {
            font-size: 2rem;
            color: #cfb087;
        }

        .user-info {
            flex: 1;
        }

        .user-label {
            color: #8e7daa;
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-email {
            color: #f0e6d2;
            font-size: 1rem;
            font-weight: 500;
            word-break: break-all;
        }

        .warning-message {
            background: rgba(207, 176, 135, 0.05);
            border: 1px solid rgba(207, 176, 135, 0.125);
            border-radius: 30px;
            padding: 1rem 1.5rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
            color: #b3a4cb;
            font-size: 0.95rem;
        }

        .warning-message i {
            color: #cfb087;
            font-size: 1.2rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            flex: 1;
            letter-spacing: 0.3px;
        }

        .btn-logout {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
        }

        .btn-logout:hover {
            background: #cfb087;
            color: #0f0b17;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(207, 176, 135, 0.4);
        }

        .btn-cancel {
            background: transparent;
            border: 1.5px solid #5a4a78;
            color: #dacfef;
        }

        .btn-cancel:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-3px);
        }

        .footer-note {
            margin-top: 2rem;
            color: #5d4b78;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .footer-note i {
            color: #cfb087;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 500px) {
            .logout-container {
                padding: 2rem;
            }

            h2 {
                font-size: 2rem;
            }

            .button-group {
                flex-direction: column;
            }

            .user-card {
                flex-direction: column;
                text-align: center;
            }

            .warning-message {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Decorative elements */
        .dots {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(207, 176, 135, 0.0625) 1px, transparent 1px);
            background-size: 30px 30px;
            pointer-events: none;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="dots"></div>
    
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h2>Leaving so soon?</h2>
        <p class="subtitle">Your session will end and you'll need to log in again</p>
        
        <?php if ($user_email || $user_name): ?>
        <div class="user-card">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <div class="user-label">Currently logged in as</div>
                <div class="user-email">
                    <?php 
                    if ($user_name) {
                        echo htmlspecialchars($user_name);
                    } else {
                        echo htmlspecialchars($user_email);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
      
        
        <div class="button-group">
            <a href="?confirm=yes" class="btn btn-logout">
                <i class="fas fa-check"></i> Yes, log me out
            </a>
            <a href="javascript:history.back()" class="btn btn-cancel">
                <i class="fas fa-arrow-left"></i> Stay logged in
            </a>
        </div>
        
        <div class="footer-note">
            <i class="fas fa-lock"></i>
            <span>Secure session · Furniverse studio</span>
        </div>
    </div>
</body>
</html>
<?php

?>