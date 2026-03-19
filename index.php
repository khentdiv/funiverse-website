<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniverse · modern studio</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: more structured & elegant -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* reset & base — darker, richer background */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0b17;  /* deep base */
            color: #e2ddf2;
            line-height: 1.5;
            scroll-behavior: smooth;
        }

        h1, h2, h3, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        /* ===== DARK & PROFESSIONAL PALETTE ===== */
        :root {
            --deep-bg: #0f0b17;
            --surface-dark: #1e192c;
            --surface-medium: #2d2640;
            --accent-gold: #cfb087;
            --accent-blush: #e6b3b3;
            --accent-lavender: #bba6d9;
            --text-light: #f0ecf9;
            --text-soft: #cbc2e6;
            --border-glow: rgba(207, 176, 135, 0.15);
            --card-shadow: 0 25px 40px -15px rgba(0, 0, 0, 0.8);
        }

        /* ===== NAVBAR — glassmorphism deep ===== */
        .navbar {
            background: rgba(18, 14, 29, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(207, 176, 135, 0.25);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.6);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.8rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 2.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ece3f0, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
            list-style: none;
        }

        .nav-menu a {
            text-decoration: none;
            font-weight: 500;
            color: #d6cee8;
            font-size: 0.98rem;
            transition: 0.2s;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-menu a:not(.btn-register):not(.btn-dashboard):not(.btn-logout)::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 2px;
            transition: width 0.2s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .nav-menu a.active {
            color: #f0e6d2;
            font-weight: 600;
        }

        .btn-register {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
            box-shadow: 0 0 10px rgba(207, 176, 135, 0.1);
        }

        .btn-register:hover {
            background: #cfb087;
            color: #0f0b17 !important;
            border-color: #cfb087;
            box-shadow: 0 0 18px rgba(207, 176, 135, 0.5);
        }

        .btn-dashboard {
            background: #2d2640;
            border: 1.5px solid #6d5a8b;
            color: #e6dbf2 !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-dashboard:hover {
            background: #3f3260;
            border-color: #bba6d9;
            box-shadow: 0 0 15px #3a2e52;
        }

        .btn-logout {
            background: transparent;
            border: 1.5px solid #68587e;
            color: #c5b8dc !important;
            padding: 0.45rem 1.6rem;
            border-radius: 40px;
            font-weight: 500;
            transition: 0.2s;
        }

        .btn-logout:hover {
            background: #3d2e55;
            border-color: #b19cd1;
            color: #fff !important;
        }

        .hamburger {
            display: none;
            font-size: 2rem;
            color: #cfb087;
            cursor: pointer;
        }

        .user-greeting {
            color: #cfb087;
            font-weight: 400;
            font-size: 0.9rem;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            border: 1px solid #3d3452;
        }

        /* ===== HERO — darker, textured, professional ===== */
        .hero {
            min-height: 90vh;
            background: radial-gradient(ellipse at 70% 40%, #2f2642, #0a0713 80%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            isolation: isolate;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                repeating-linear-gradient(45deg, rgba(207, 176, 135, 0.02) 0px, rgba(207, 176, 135, 0.02) 2px, transparent 2px, transparent 8px),
                url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48cGF0aCBkPSJNMjAgMTBhMTAgMTAgMCAwIDEgMTAgMTAgMTAgMTAgMCAwIDEtMTAgMTAgMTAgMTAgMCAwIDEtMTAtMTAgMTAgMTAgMCAwIDEgMTAtMTB6IiBmaWxsPSIjZmZmIiBvcGFjaXR5PSIwLjAyIi8+PC9zdmc+');
            opacity = 0.4;
            z-index: 0;
        }

        .hero-content {
            max-width: 950px;
            padding: 2rem;
            position: relative;
            z-index: 2;
            animation: fadeUp 1.2s cubic-bezier(0.075, 0.82, 0.165, 1);
        }

        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .hero-content h1 {
            font-size: 4.8rem;
            line-height: 1.05;
            font-weight: 700;
            background: linear-gradient(135deg, #f0e6d2, #cfb087, #bba6d9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.2rem;
            text-shadow: 0 0 30px rgba(207, 176, 135, 0.3);
        }

        .hero-content p {
            font-size: 1.3rem;
            color: #cbc2e6;
            margin-bottom: 2.5rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 300;
            letter-spacing: 0.3px;
        }

        .btn-large {
            background: transparent;
            border: 2px solid #cfb087;
            color: #f0e6d2;
            font-size: 1.25rem;
            padding: 1rem 3.5rem;
            border-radius: 60px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px -5px #0b0813;
            transition: 0.25s;
            letter-spacing: 0.5px;
        }

        .btn-large:hover {
            background: #cfb087;
            color: #0f0b17;
            border-color: #cfb087;
            box-shadow: 0 20px 35px -5px #846b4b;
            transform: translateY(-3px);
        }

        /* ===== FEATURES section — darker cards, elegant ===== */
        .features {
            max-width: 1300px;
            margin: 7rem auto;
            padding: 0 2rem;
        }

        .features h2, .collections-preview h2 {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 3rem;
            font-weight: 600;
            background: linear-gradient(145deg, #ffffff, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }

        .features h2:after, .collections-preview h2:after {
            content: '';
            display: block;
            width: 120px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #9b84b5, #54456b);
            margin: 1.2rem auto 0;
            border-radius: 4px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2.5rem;
        }

        .feature-card {
            background: #1e192c;
            border: 1px solid #332d44;
            padding: 2.8rem 2rem;
            border-radius: 40px;
            box-shadow: 0 25px 40px -12px #010101;
            transition: 0.3s ease;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: #6b5b85;
            box-shadow: 0 35px 55px -15px #0f0b17, 0 0 0 1px rgba(207, 176, 135, 0.3);
        }

        .feature-card i {
            font-size: 2.8rem;
            background: linear-gradient(145deg, #342d48, #221e32);
            width: 90px;
            height: 90px;
            line-height: 90px !important;
            border-radius: 30px 30px 30px 8px;
            color: #cfb087;
            margin-bottom: 2rem;
            border: 1px solid #49405f;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.5);
        }

        .feature-card h3 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            color: #b2a6ca;
            font-size: 1rem;
            font-weight: 300;
        }

        /* ===== COLLECTIONS preview — dark luxurious ===== */
        .collections-preview {
            max-width: 1300px;
            margin: 7rem auto;
            padding: 0 2rem;
        }

        .collections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
        }

        .collection-card {
            background: #161224;
            border-radius: 48px;
            overflow: hidden;
            box-shadow: 0 30px 40px -12px black;
            transition: 0.3s;
            border: 1px solid #302944;
        }

        .collection-card:hover {
            transform: scale(1.02);
            border-color: #6a5688;
            box-shadow: 0 40px 60px -15px #020001, 0 0 0 1px #cfb08733;
        }

        .collection-card img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
            background: #251f33; /* fallback */
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b6a1d3;
        }

        .collection-card:nth-child(1) img {
            background: linear-gradient(145deg, #342d48, #201c2b);
        }
        .collection-card:nth-child(2) img {
            background: linear-gradient(145deg, #352e42, #221d30);
        }
        .collection-card:nth-child(3) img {
            background: linear-gradient(145deg, #2f2742, #1b1727);
        }

        .collection-card h3 {
            font-size: 2rem;
            margin: 1.5rem 1.8rem 0.3rem;
            color: #f0e2d3;
        }

        .collection-card p {
            margin: 0 1.8rem 1rem;
            color: #b9abcf;
            font-weight: 300;
        }

        .btn {
            display: inline-block;
            background: transparent;
            border: 1.5px solid #7b689b;
            border-radius: 40px;
            padding: 0.7rem 2rem;
            margin: 0 1.8rem 2rem;
            text-decoration: none;
            font-weight: 600;
            color: #dacfef;
            transition: 0.2s;
        }

        .btn:hover {
            background: #5a4a78;
            border-color: #bba6d9;
            color: #fff;
        }

        /* ===== FOOTER — deep elegant ===== */
        footer {
            background: #0c0818;
            border-top: 1px solid #332b44;
            padding: 4rem 2rem 2rem;
            margin-top: 8rem;
        }

        .footer-content {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 4rem;
        }

        .footer-section h3 {
            font-size: 1.8rem;
            margin-bottom: 1.2rem;
            color: #e3d5f0;
            font-weight: 600;
        }

        .footer-section p, .footer-section li {
            color: #b3a4cb;
            margin-bottom: 0.7rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section a {
            text-decoration: none;
            color: #b3a4cb;
            border-bottom: 1px dotted #5d4b78;
            transition: 0.2s;
        }

        .footer-section a:hover {
            border-bottom-color: #cfb087;
            color: #cfb087;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 3rem;
            margin-top: 3rem;
            border-top: 1px dashed #3f3655;
            color: #8e7daa;
            font-size: 0.95rem;
        }

        /* ===== responsive ===== */
        @media (max-width: 950px) {
            .nav-menu {
                position: fixed;
                top: 70px;
                left: -100%;
                background: #130e20f2;
                backdrop-filter: blur(18px);
                width: 100%;
                flex-direction: column;
                padding: 3rem 2rem;
                gap: 2rem;
                box-shadow: 0 50px 60px #00000080;
                transition: left 0.3s ease;
                border-bottom: 1px solid #6d5b86;
            }
            .nav-menu.active {
                left: 0;
            }
            .hamburger {
                display: block;
            }
            .hero-content h1 {
                font-size: 3.5rem;
            }
        }

        @media (max-width: 600px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .hero-content h1 {
                font-size: 2.6rem;
            }
        }

        /* placeholder icon styling */
        .collection-card img i {
            font-size: 4rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>Furniverse</h1>
            </div>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="active">Home</a></li>
          
                <li><a href="customize.php">Customize</a></li>
                <li><a href="collections.php">Collections</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <li><span class="user-greeting"><i class="fas fa-circle-user" style="margin-right: 5px;"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></li>
                    <?php endif; ?>
                    <li><a href="dashboard.php" class="btn-dashboard"><i class="fas fa-chart-pie" style="margin-right: 5px;"></i>Dashboard</a></li>
                    <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="btn-register"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Register</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger" id="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-content">
            <h1>Design without compromise</h1>
            <p>tailored furniture · dark & moody or light & airy — your space, your rules</p>
            <a href="customize.php" class="btn-large">Begin project →</a>
        </div>
    </header>

    <section class="features">
        <h2>why we're different</h2>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-cubes"></i>
                <h3>modular craft</h3>
                <p>precision engineering meets sculptural form</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-tree"></i>
                <h3>sustainable soul</h3>
                <p>reclaimed woods & low‑impact finishes</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-clock"></i>
                <h3>concierge delivery</h3>
                <p>white‑glove service, real‑time updates</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-gem"></i>
                <h3>timeless aesthetic</h3>
                <p>pieces that transcend seasons</p>
            </div>
        </div>
    </section>

    
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Furniverse atelier</h3>
                <p><i class="fas fa-location-dot" style="margin-right: 10px; color:#cfb087;"></i> Poblacion, Tupi, South Cotabato</p>
                <p><i class="fas fa-phone" style="margin-right: 10px; color:#cfb087;"></i> +63 912 345 6789</p>
                <p><i class="fas fa-envelope" style="margin-right: 10px; color:#cfb087;"></i> studio@furniverse.com</p>
            </div>
            <div class="footer-section">
                <h3>inside</h3>
                <ul>
                    <li><a href="about.php">the studio</a></li>
                    <li><a href="privacy.php">privacy</a></li>
                    <li><a href="terms.php">terms</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Furniverse · designed for the discerning</p>
        </div>
    </footer>

    <script>
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('navMenu');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>
</html>