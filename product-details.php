<?php
require_once 'config.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id == 0) {
    $_SESSION['error'] = "No product selected";
    redirect('collections.php');
}

// Fetch product details
$query = "SELECT p.*, c.category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id
          WHERE p.product_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found";
    redirect('collections.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> · Furniverse</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== DARK PROFESSIONAL BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0b17;
            color: #e2ddf2;
            line-height: 1.5;
        }

        h1, h2, h3, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

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

        /* ===== NAVBAR ===== */
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

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ===== BREADCRUMB ===== */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: #b3a4cb;
            font-size: 0.95rem;
        }

        .breadcrumb a {
            color: #b3a4cb;
            text-decoration: none;
            transition: 0.2s;
        }

        .breadcrumb a:hover {
            color: #cfb087;
        }

        .breadcrumb i {
            font-size: 0.8rem;
            color: #5a4a78;
        }

        /* ===== PRODUCT DETAILS ===== */
        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        /* Product Gallery */
        .product-gallery {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .main-image {
            width: 100%;
            height: 500px;
            background: #161224;
            border-radius: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #332d44;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .main-image i {
            font-size: 8rem;
            color: #4a3f60;
        }

        /* Product Info */
        .product-info {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2.5rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .product-category {
            display: inline-block;
            background: #2d2640;
            color: #b3a4cb;
            font-size: 0.9rem;
            padding: 0.4rem 1.5rem;
            border-radius: 40px;
            margin-bottom: 1.5rem;
            border: 1px solid #49405f;
        }

        .product-info h1 {
            font-size: 3rem;
            color: #f0e6d2;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #cfb087;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #332d44;
        }

        .product-description {
            margin-bottom: 2rem;
        }

        .product-description h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-description h3 i {
            color: #cfb087;
        }

        .product-description p {
            color: #b3a4cb;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        /* Product Specifications Table */
        .specs-container {
            background: #161224;
            border-radius: 40px;
            padding: 2rem;
            margin: 2rem 0;
            border: 1px solid #332d44;
        }

        .specs-container h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .specs-container h3 i {
            color: #cfb087;
        }

        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .spec-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 1.5rem;
            transition: 0.2s;
        }

        .spec-card:hover {
            border-color: #cfb087;
            transform: translateY(-2px);
        }

        .spec-icon {
            width: 50px;
            height: 50px;
            background: #2d2640;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .spec-icon i {
            font-size: 1.5rem;
            color: #cfb087;
        }

        .spec-label {
            color: #b3a4cb;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .spec-value {
            color: #f0e6d2;
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Material Details */
        .material-details {
            background: linear-gradient(145deg, #1e192c, #161224);
            border-radius: 40px;
            padding: 2rem;
            margin: 2rem 0;
            border: 1px solid #cfb087;
        }

        .material-details h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .material-details h3 i {
            color: #cfb087;
        }

        .material-info {
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .material-badge {
            background: #2d2640;
            border: 1px solid #cfb087;
            border-radius: 40px;
            padding: 0.8rem 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .material-badge i {
            color: #cfb087;
        }

        .material-badge span {
            color: #f0e6d2;
            font-weight: 600;
        }

        .material-description {
            color: #b3a4cb;
            flex: 1;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            border: 1.5px solid;
            cursor: pointer;
            font-size: 1rem;
            flex: 1;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: transparent;
            border-color: #cfb087;
            color: #f0e6d2;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        .btn-secondary {
            background: transparent;
            border-color: #5a4a78;
            color: #dacfef;
        }

        .btn-secondary:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-2px);
        }

        /* Related Products */
        .related-products {
            margin-top: 5rem;
        }

        .related-products h2 {
            font-size: 2.5rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 1rem;
        }

        .related-products h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }

        .related-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 40px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
        }

        .related-card:hover {
            transform: translateY(-5px);
            border-color: #cfb087;
        }

        .related-image {
            height: 200px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .related-image i {
            font-size: 3rem;
            color: #4a3f60;
        }

        .related-info {
            padding: 1.5rem;
        }

        .related-info h4 {
            color: #f0e6d2;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .related-price {
            color: #cfb087;
            font-weight: 600;
            font-size: 1.3rem;
        }

        /* ===== FOOTER ===== */
        footer {
            background: #0c0818;
            border-top: 1px solid #332b44;
            padding: 4rem 2rem 2rem;
            margin-top: 6rem;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 4rem;
        }

        .footer-section h3 {
            font-size: 1.8rem;
            margin-bottom: 1.2rem;
            color: #e3d5f0;
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
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1000px) {
            .product-details {
                grid-template-columns: 1fr;
            }
            .specs-grid {
                grid-template-columns: 1fr;
            }
        }

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
        }

        @media (max-width: 600px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .product-info h1 {
                font-size: 2.5rem;
            }
            .product-price {
                font-size: 2rem;
            }
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
                <li><a href="index.php">Home</a></li>
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

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="collections.php">Collections</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
        </div>

        <!-- Product Details -->
        <div class="product-details">
            <!-- Product Gallery -->
            <div class="product-gallery">
                <div class="main-image">
                    <?php if (!empty($product['image']) && file_exists("images/".$product['image'])): ?>
                        <img src="images/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-couch"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-info">
                <span class="product-category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></span>
                
                <h1><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <div class="product-price">₱<?php echo number_format($product['base_price'], 2); ?></div>
                
                <div class="product-description">
                    <h3><i class="fas fa-info-circle"></i> About this piece</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>

                <!-- Specifications Grid -->
                <div class="specs-container">
                    <h3><i class="fas fa-clipboard-list"></i> Specifications</h3>
                    <div class="specs-grid">
                        <?php if (!empty($product['dimensions'])): ?>
                        <div class="spec-card">
                            <div class="spec-icon">
                                <i class="fas fa-ruler"></i>
                            </div>
                            <div class="spec-label">Dimensions</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['dimensions']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($product['weight'])): ?>
                        <div class="spec-card">
                            <div class="spec-icon">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div class="spec-label">Weight</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['weight']); ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="spec-card">
                            <div class="spec-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div class="spec-label">Color</div>
                            <div class="spec-value"><?php echo !empty($product['color']) ? htmlspecialchars($product['color']) : 'Various'; ?></div>
                        </div>

                        <div class="spec-card">
                            <div class="spec-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="spec-label">Warranty</div>
                            <div class="spec-value">2 Years</div>
                        </div>
                    </div>
                </div>

                <!-- Material Details -->
                <?php if (!empty($product['material'])): ?>
                <div class="material-details">
                    <h3><i class="fas fa-cube"></i> Material Information</h3>
                    <div class="material-info">
                        <div class="material-badge">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($product['material']); ?></span>
                        </div>
                        <div class="material-description">
                            Premium quality <?php echo htmlspecialchars($product['material']); ?> construction for durability and elegance.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button onclick="goToCheckout()" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Order Now
                    </button>
                    <a href="collections.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php
        // Fetch related products (same category, excluding current)
        $related_query = "SELECT p.* FROM products p 
                         WHERE p.category_id = ? AND p.product_id != ? 
                         LIMIT 4";
        $stmt = $conn->prepare($related_query);
        $stmt->bind_param("ii", $product['category_id'], $product_id);
        $stmt->execute();
        $related_result = $stmt->get_result();
        
        if ($related_result->num_rows > 0):
        ?>
        <div class="related-products">
            <h2>You might also like</h2>
            <div class="related-grid">
                <?php while($related = $related_result->fetch_assoc()): ?>
                <a href="product-details.php?id=<?php echo $related['product_id']; ?>" class="related-card">
                    <div class="related-image">
                        <?php if (!empty($related['image']) && file_exists("images/".$related['image'])): ?>
                            <img src="images/<?php echo $related['image']; ?>" alt="<?php echo htmlspecialchars($related['product_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-chair"></i>
                        <?php endif; ?>
                    </div>
                    <div class="related-info">
                        <h4><?php echo htmlspecialchars($related['product_name']); ?></h4>
                        <div class="related-price">₱<?php echo number_format($related['base_price'], 2); ?></div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

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
        // Hamburger menu toggle
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('navMenu');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        // Go to checkout with single quantity
        function goToCheckout() {
            <?php if (!isLoggedIn()): ?>
            window.location.href = 'login.php?redirect=product-details.php?id=<?php echo $product_id; ?>';
            return;
            <?php endif; ?>
            
            window.location.href = 'checkout-product.php?id=<?php echo $product_id; ?>&quantity=1';
        }
    </script>
</body>
</html>