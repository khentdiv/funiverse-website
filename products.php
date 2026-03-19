<?php
require_once 'config.php';

// Fetch products
$query = "SELECT p.*, c.category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items · Furniverse modern studio</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: structured & elegant -->
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
            scroll-behavior: smooth;
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

        /* ===== PAGE HEADER — dramatic ===== */
        .page-header {
            background: radial-gradient(ellipse at 70% 30%, #2f2642, #0a0713 80%);
            padding: 5rem 2rem;
            text-align: center;
            position: relative;
            isolation: isolate;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(45deg, rgba(207, 176, 135, 0.02) 0px, rgba(207, 176, 135, 0.02) 2px, transparent 2px, transparent 8px);
            z-index: 0;
        }

        .page-header h1 {
            font-size: 4.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f0e6d2, #cfb087, #bba6d9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 0 30px rgba(207, 176, 135, 0.3);
        }

        .page-header p {
            font-size: 1.3rem;
            color: #cbc2e6;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            font-weight: 300;
            letter-spacing: 0.3px;
        }

        /* ===== FILTERS SECTION ===== */
        .filters-section {
            max-width: 1400px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .filters-container {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 60px;
            padding: 2rem 2.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
            justify-content: center;
            box-shadow: 0 25px 40px -15px #010101;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group label {
            font-weight: 500;
            color: #cfb087;
            background: #2d2640;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.95rem;
            border: 1px solid #49405f;
            letter-spacing: 0.3px;
        }

        .filter-group label i {
            margin-right: 6px;
            color: #b3a4cb;
        }

        .filter-select {
            padding: 0.8rem 2rem 0.8rem 1.5rem;
            border: 1px solid #3d3452;
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: #f0e6d2;
            background: #2d2640;
            cursor: pointer;
            outline: none;
            transition: 0.2s;
            min-width: 220px;
        }

        .filter-select option {
            background: #1e192c;
            color: #f0e6d2;
        }

        .filter-select:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.2);
        }

        /* ===== ITEMS GRID — dark luxurious ===== */
        .items-section {
            max-width: 1400px;
            margin: 3rem auto 5rem;
            padding: 0 2rem;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2.5rem;
        }

        .item-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            overflow: hidden;
            box-shadow: 0 25px 40px -12px #010101;
            transition: 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .item-card:hover {
            transform: translateY(-8px);
            border-color: #6b5b85;
            box-shadow: 0 35px 55px -15px #0f0b17, 0 0 0 1px rgba(207, 176, 135, 0.3);
        }

        .item-image {
            height: 260px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .item-image i {
            font-size: 5rem;
            color: #4a3f60;
            transition: 0.3s;
        }

        .item-card:hover .item-image i {
            color: #cfb087;
            transform: scale(1.05);
        }

        /* Different gradient overlays for cards */
        .item-card:nth-child(3n+1) .item-image {
            background: linear-gradient(145deg, #161224, #221e32);
        }
        .item-card:nth-child(3n+2) .item-image {
            background: linear-gradient(145deg, #1a1528, #241f35);
        }
        .item-card:nth-child(3n+3) .item-image {
            background: linear-gradient(145deg, #131023, #1d182b);
        }

        .item-content {
            padding: 2rem 2rem 1.8rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .item-content h3 {
            font-size: 2rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .item-category {
            display: inline-block;
            background: #2d2640;
            color: #b3a4cb;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            margin-bottom: 1rem;
            width: fit-content;
            border: 1px solid #49405f;
            letter-spacing: 0.3px;
        }

        .item-category i {
            color: #cfb087;
        }

        .item-price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #cfb087;
            margin-bottom: 1rem;
        }

        .item-price small {
            font-size: 1rem;
            font-weight: 400;
            color: #8e7daa;
        }

        .item-description {
            color: #b2a6ca;
            margin-bottom: 1.8rem;
            line-height: 1.6;
            flex: 1;
            font-weight: 300;
        }

        .item-actions {
            display: flex;
            gap: 1rem;
            margin-top: auto;
        }

        .btn {
            display: inline-block;
            background: transparent;
            border: 1.5px solid #5a4a78;
            border-radius: 40px;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            font-weight: 500;
            color: #dacfef;
            transition: 0.2s;
            font-size: 0.95rem;
            text-align: center;
            flex: 1;
        }

        .btn i {
            margin-right: 6px;
        }

        .btn:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
        }

        .btn-primary {
            background: #2d2640;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
        }

        .btn-primary:hover {
            background: #3f3260;
            border-color: #cfb087;
            box-shadow: 0 0 15px #3a2e52;
        }

        /* Empty state */
        .no-items {
            grid-column: 1 / -1;
            text-align: center;
            padding: 5rem 3rem;
            background: #1e192c;
            border-radius: 60px;
            color: #b3a4cb;
            font-size: 1.2rem;
            border: 1px solid #3d3452;
            box-shadow: 0 30px 40px -20px black;
        }

        .no-items i {
            font-size: 5rem;
            color: #4a3f60;
            margin-bottom: 1.5rem;
            display: block;
        }

        .no-items p:last-child {
            color: #8e7daa;
            margin-top: 0.5rem;
        }

        /* ===== FOOTER — deep elegant ===== */
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

        /* ===== RESPONSIVE ===== */
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
            .page-header h1 {
                font-size: 3rem;
            }
            .filters-container {
                border-radius: 40px;
                padding: 1.8rem;
            }
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 2.4rem;
            }
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-select {
                width: 100%;
            }
            .item-actions {
                flex-direction: column;
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
                <li><a href="products.php" class="active">Items</a></li>
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

    <header class="page-header">
        <h1>Items</h1>
        <p>Designed for living · crafted to endure</p>
    </header>

    <div class="filters-section">
        <div class="filters-container">
            <div class="filter-group">
                <label for="category-filter"><i class="fas fa-tag"></i>Category</label>
                <select id="category-filter" class="filter-select">
                    <option value="all">All categories</option>
                    <?php
                    $cats = $conn->query("SELECT * FROM categories ORDER BY category_name");
                    while($cat = $cats->fetch_assoc()) {
                        echo "<option value='{$cat['category_id']}'>{$cat['category_name']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <section class="items-section">
        <div class="items-grid" id="items-grid">
            <?php 
            if ($result && $result->num_rows > 0):
                while($product = $result->fetch_assoc()): 
            ?>
            <div class="item-card" data-category="<?php echo $product['category_id']; ?>">
                <div class="item-image">
                    <?php if (!empty($product['image']) && file_exists("images/".$product['image'])): ?>
                        <img src="images/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-chair"></i>
                    <?php endif; ?>
                </div>
                <div class="item-content">
                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <span class="item-category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></span>
                    <div class="item-price">
                        ₱<?php echo number_format($product['base_price'], 2); ?> <small>PHP</small>
                    </div>
                    <p class="item-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                    <div class="item-actions">
                        <a href="product-details.php?id=<?php echo $product['product_id']; ?>" class="btn"><i class="fas fa-eye"></i>Details</a>
                        <a href="customize.php?product=<?php echo $product['product_id']; ?>" class="btn btn-primary"><i class="fas fa-paint-brush"></i>Customize</a>
                    </div>
                </div>
            </div>
            <?php 
                endwhile; 
            else:
            ?>
            <div class="no-items">
                <i class="fas fa-couch"></i>
                <p>No items available</p>
                <p>New pieces arriving soon</p>
            </div>
            <?php endif; ?>
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
        // Hamburger menu toggle
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('navMenu');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        // Filter functionality
        const categoryFilter = document.getElementById('category-filter');
        const items = document.querySelectorAll('.item-card');

        function filterItems() {
            const selectedCategory = categoryFilter.value;

            items.forEach(item => {
                const itemCategory = item.dataset.category;
                const categoryMatch = selectedCategory === 'all' || itemCategory === selectedCategory;

                if (categoryMatch) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Event listeners
        if (categoryFilter) {
            categoryFilter.addEventListener('change', filterItems);
        }
    </script>
</body>
</html>