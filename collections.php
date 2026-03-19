<?php
require_once 'config.php';

$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Fetch collections based on type
$query = "SELECT p.*, c.category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id";

if ($type == 'eco') {
    // Eco-friendly products (using eco materials)
    $query = "SELECT DISTINCT p.*, c.category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.category_id
              WHERE p.product_id IN (
                  SELECT DISTINCT product_id FROM customizations 
                  WHERE material_id IN (SELECT material_id FROM materials WHERE material_type = 'eco-friendly')
              )";
} elseif ($type == 'modern') {
    // Modern collection (by category)
    $query .= " WHERE c.category_name IN ('Sofas', 'Tables')";
} elseif ($type == 'premium') {
    // Premium collection (by material)
    $query = "SELECT DISTINCT p.*, c.category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.category_id
              WHERE p.product_id IN (
                  SELECT DISTINCT product_id FROM customizations 
                  WHERE material_id IN (SELECT material_id FROM materials WHERE material_type = 'premium')
              )";
}

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections · Furniverse modern studio</title>
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

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* ===== COLLECTION TABS — refined ===== */
        .collection-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 3rem 0 4rem;
        }

        .collection-tab {
            text-decoration: none;
            padding: 0.9rem 2.5rem;
            border-radius: 60px;
            font-weight: 500;
            font-size: 1rem;
            transition: 0.3s;
            background: #1e192c;
            border: 1px solid #3d3452;
            color: #cbc2e6;
            letter-spacing: 0.3px;
            backdrop-filter: blur(4px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }

        .collection-tab i {
            margin-right: 8px;
            color: #cfb087;
        }

        .collection-tab:hover {
            border-color: #cfb087;
            color: #f0e6d2;
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -8px #000, 0 0 0 1px rgba(207, 176, 135, 0.3);
        }

        .collection-tab.active {
            background: #2d2640;
            border-color: #cfb087;
            color: #f0e6d2;
            font-weight: 600;
            box-shadow: 0 10px 20px -5px #000, 0 0 15px rgba(207, 176, 135, 0.4);
        }

        .collection-tab.active i {
            color: #f0e6d2;
        }

        /* ===== COLLECTION BANNER — dramatic ===== */
        .collection-banner {
            background: linear-gradient(145deg, #1e192c, #120e1f);
            border-radius: 60px;
            padding: 2.5rem 3rem;
            margin: 2rem 0 4rem;
            display: flex;
            align-items: center;
            gap: 2.5rem;
            flex-wrap: wrap;
            border: 1px solid #3d3452;
            box-shadow: 0 30px 40px -20px black;
            position: relative;
            overflow: hidden;
        }

        .collection-banner::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: radial-gradient(circle at 70% 50%, rgba(207, 176, 135, 0.1), transparent 70%);
            pointer-events: none;
        }

        .banner-icon {
            width: 100px;
            height: 100px;
            background: #2d2640;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #cfb087;
            border: 1px solid #5a4a78;
            box-shadow: 0 15px 25px -8px #000;
            position: relative;
            z-index: 2;
        }

        .banner-content {
            flex: 1;
            position: relative;
            z-index: 2;
        }

        .banner-content h2 {
            font-size: 2.5rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .banner-content p {
            color: #b3a4cb;
            font-size: 1.1rem;
            font-weight: 300;
            max-width: 600px;
        }

        /* ===== PRODUCTS GRID — dark luxurious ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2.5rem;
            margin-bottom: 5rem;
        }

        .product-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            overflow: hidden;
            box-shadow: 0 25px 40px -12px #010101;
            transition: 0.3s ease;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(5px);
        }

        .product-card:hover {
            transform: translateY(-8px);
            border-color: #6b5b85;
            box-shadow: 0 35px 55px -15px #0f0b17, 0 0 0 1px rgba(207, 176, 135, 0.3);
        }

        .product-card .image-wrapper {
            height: 280px;
            background: #161224;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .product-card .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .image-wrapper img {
            transform: scale(1.08);
        }

        .product-card .image-wrapper i {
            font-size: 5rem;
            color: #4a3f60;
            transition: 0.3s;
        }

        .product-card:hover .image-wrapper i {
            color: #cfb087;
            transform: scale(1.05);
        }

        .product-card h3 {
            font-size: 2rem;
            color: #f0e6d2;
            margin: 1.8rem 1.8rem 0.3rem;
        }

        .product-card .category {
            display: inline-block;
            background: #2d2640;
            color: #b3a4cb;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.3rem 1.2rem;
            border-radius: 40px;
            margin: 0 1.8rem 1rem;
            width: fit-content;
            border: 1px solid #49405f;
        }

        .product-card .price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #cfb087;
            margin: 0 1.8rem 0.5rem;
        }

        .product-card .description {
            color: #b2a6ca;
            margin: 0 1.8rem 1.5rem;
            line-height: 1.6;
            flex: 1;
            font-weight: 300;
        }

        .product-actions {
            display: flex;
            gap: 1rem;
            margin: 0 1.8rem 2rem;
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
            cursor: pointer;
        }

        .btn i {
            margin-right: 6px;
        }

        .btn:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2 !important;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17 !important;
            border-color: #cfb087;
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        /* ===== IMPROVED QUANTITY MODAL STYLES ===== */
        .quantity-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .quantity-modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(145deg, #1e192c, #161224);
            border: 1px solid rgba(207, 176, 135, 0.3);
            border-radius: 48px;
            padding: 2.5rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 40px 60px -20px #000, 0 0 0 1px rgba(207, 176, 135, 0.2) inset;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2d2640;
            border: 1px solid #5a4a78;
            color: #b3a4cb;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .modal-close:hover {
            background: #3f3260;
            color: #cfb087;
            border-color: #cfb087;
            transform: rotate(90deg);
        }

        .modal-product-preview {
            width: 120px;
            height: 120px;
            background: #2d2640;
            border-radius: 40px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #cfb087;
            box-shadow: 0 15px 30px -10px #000;
        }

        .modal-product-preview i {
            font-size: 3.5rem;
            color: #cfb087;
        }

        .modal-product-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 38px;
        }

        .modal-content h3 {
            font-size: 2rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .modal-price-info {
            background: #2d2640;
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            display: inline-block;
            margin-bottom: 2rem;
            border: 1px solid #5a4a78;
        }

        .modal-price-info span {
            color: #cfb087;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .quantity-input-group {
            margin: 2rem 0;
            background: #161224;
            padding: 2rem;
            border-radius: 40px;
            border: 1px solid #332d44;
        }

        .quantity-input-group label {
            display: block;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }

        .quantity-btn {
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: #2d2640;
            border: 2px solid #5a4a78;
            color: #f0e6d2;
            font-size: 2rem;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .quantity-btn:hover {
            background: #3f3260;
            border-color: #cfb087;
            color: #cfb087;
            transform: scale(1.05);
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }

        .quantity-input {
            width: 120px;
            text-align: center;
            background: #1e192c;
            border: 2px solid #5a4a78;
            border-radius: 40px;
            padding: 1rem;
            color: #f0e6d2;
            font-size: 2rem;
            font-weight: 700;
            outline: none;
        }

        .quantity-input:focus {
            border-color: #cfb087;
            box-shadow: 0 0 0 3px rgba(207, 176, 135, 0.2);
        }

        .modal-total {
            background: linear-gradient(145deg, #161224, #1e192c);
            padding: 1.5rem;
            border-radius: 40px;
            margin: 2rem 0;
            border: 1px solid #cfb087;
        }

        .modal-total p {
            margin: 0;
            font-size: 1.1rem;
            color: #b3a4cb;
            margin-bottom: 0.5rem;
        }

        .modal-total .total-price {
            font-size: 3rem;
            color: #cfb087;
            font-weight: 700;
            line-height: 1.2;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-modal {
            flex: 1;
            padding: 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            border: 1.5px solid;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-cancel {
            background: transparent;
            border-color: #5a4a78;
            color: #b3a4cb;
        }

        .btn-cancel:hover {
            background: #2d2640;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-checkout {
            background: #cfb087;
            border-color: #cfb087;
            color: #0f0b17;
        }

        .btn-checkout:hover {
            background: #e6cba8;
            border-color: #e6cba8;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(207, 176, 135, 0.3);
        }

        /* ===== IMPROVED DETAILS BUTTON STYLES ===== */
        .btn-details {
            background: transparent;
            border: 1.5px solid #5a4a78;
            color: #dacfef;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            flex: 1;
        }

        .btn-details i {
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-details:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-details:hover i {
            transform: translateX(3px);
        }

        .btn-order {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            flex: 1;
            cursor: pointer;
        }

        .btn-order i {
            font-size: 1rem;
        }

        .btn-order:hover {
            background: #cfb087;
            color: #0f0b17;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(207, 176, 135, 0.3);
        }

        .btn-order:hover i {
            animation: bounce 0.5s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(3px); }
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 5rem 3rem;
            background: #1e192c;
            border-radius: 60px;
            color: #b3a4cb;
            font-size: 1.2rem;
            margin: 4rem 0;
            border: 1px solid #3d3452;
            box-shadow: 0 30px 40px -20px black;
        }

        .empty-state i {
            font-size: 5rem;
            color: #4a3f60;
            margin-bottom: 1.5rem;
            display: block;
        }

        .empty-state .btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 1rem 3.5rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
            font-size: 1.1rem;
        }

        .empty-state .btn:hover {
            background: #cfb087;
            color: #0f0b17;
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
            .collection-banner {
                flex-direction: column;
                text-align: center;
            }
            .banner-content p {
                margin-left: auto;
                margin-right: auto;
            }
            .modal-content {
                padding: 2rem;
            }
            .quantity-controls {
                gap: 1rem;
            }
            .quantity-btn {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            .quantity-input {
                width: 80px;
                font-size: 1.5rem;
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
            .collection-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            .collection-tab {
                text-align: center;
            }
            .product-actions {
                flex-direction: column;
            }
            .modal-actions {
                flex-direction: column;
            }
            .modal-total .total-price {
                font-size: 2.5rem;
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
                <li><a href="collections.php" class="active">Collections</a></li>
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
        <h1>Styled collections</h1>
        <p>Discover furniture stories told in texture, tone & timber</p>
    </header>

    <div class="container">
        <!-- Collection Tabs -->
        <div class="collection-tabs">
            <a href="collections.php" class="collection-tab <?php echo !$type ? 'active' : ''; ?>">
                <i class="fas fa-compass"></i>All collections
            </a>
        </div>

        <!-- Products Showcase -->
        <div class="collections-showcase">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="products-grid">
                    <?php while($product = $result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="image-wrapper">
                            <?php if (!empty($product['image']) && file_exists("images/".$product['image'])): ?>
                                <img src="images/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-chair"></i>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <span class="category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></span>
                        <p class="price">₱<?php echo number_format($product['base_price'], 2); ?></p>
                        <p class="description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                        <div class="product-actions">
                            <a href="product-details.php?id=<?php echo $product['product_id']; ?>" class="btn-details">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <button onclick="openQuantityModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', <?php echo $product['base_price']; ?>, '<?php echo addslashes($product['image'] ?? ''); ?>')" class="btn-order">
                                <i class="fas fa-shopping-cart"></i> Order Now
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-paint-brush"></i>
                    <p>This collection is being curated.</p>
                    <p style="font-size: 1rem; margin-top: 0.5rem; color:#8e7daa;">New pieces arriving soon.</p>
                    <a href="collections.php" class="btn">Browse all collections</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Improved Quantity Selection Modal -->
    <div id="quantityModal" class="quantity-modal">
        <div class="modal-content">
            <div class="modal-close" onclick="closeQuantityModal()">
                <i class="fas fa-times"></i>
            </div>
            
            <div class="modal-product-preview" id="modalProductPreview">
                <i class="fas fa-couch"></i>
            </div>
            
            <h3 id="modalProductName">Select Quantity</h3>
            
            <div class="modal-price-info">
                <span id="modalProductPrice"></span>
            </div>
            
            <form id="checkoutForm" method="GET" action="checkout-product.php">
                <input type="hidden" name="id" id="modalProductId">
                <input type="hidden" name="quantity" id="quantityInput" value="1">
                
                <div class="quantity-input-group">
                    <label>Choose quantity:</label>
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn" onclick="updateQuantity(-1)">−</button>
                        <input type="number" class="quantity-input" id="quantityDisplay" value="1" min="1" max="99" readonly>
                        <button type="button" class="quantity-btn" onclick="updateQuantity(1)">+</button>
                    </div>
                </div>

                <div class="modal-total">
                    <p>Total Amount</p>
                    <p class="total-price" id="totalPrice">₱0.00</p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeQuantityModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-modal btn-checkout">
                        <i class="fas fa-check-circle"></i> Checkout
                    </button>
                </div>
            </form>
        </div>
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

        // Quantity Modal Functions
        let currentProductId = 0;
        let currentPrice = 0;

        function openQuantityModal(productId, productName, price, image) {
            <?php if (!isLoggedIn()): ?>
            window.location.href = 'login.php?redirect=collections.php';
            return;
            <?php endif; ?>
            
            currentProductId = productId;
            currentPrice = price;
            
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('modalProductPrice').textContent = '₱' + price.toFixed(2) + ' each';
            document.getElementById('modalProductId').value = productId;
            
            // Update product preview
            const preview = document.getElementById('modalProductPreview');
            if (image) {
                preview.innerHTML = '<img src="images/' + image + '" alt="' + productName + '">';
            } else {
                preview.innerHTML = '<i class="fas fa-couch"></i>';
            }
            
            // Reset quantity
            document.getElementById('quantityInput').value = 1;
            document.getElementById('quantityDisplay').value = 1;
            
            updateTotalPrice();
            
            document.getElementById('quantityModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeQuantityModal() {
            document.getElementById('quantityModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function updateQuantity(change) {
            const hiddenInput = document.getElementById('quantityInput');
            const displayInput = document.getElementById('quantityDisplay');
            let newValue = parseInt(hiddenInput.value) + change;
            
            if (newValue >= 1 && newValue <= 99) {
                hiddenInput.value = newValue;
                displayInput.value = newValue;
                updateTotalPrice();
                
                // Add animation effect
                const totalElement = document.querySelector('.total-price');
                totalElement.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    totalElement.style.transform = 'scale(1)';
                }, 150);
            }
        }

        function updateTotalPrice() {
            const quantity = parseInt(document.getElementById('quantityInput').value);
            const total = quantity * currentPrice;
            document.getElementById('totalPrice').textContent = '₱' + total.toFixed(2);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('quantityModal');
            if (event.target == modal) {
                closeQuantityModal();
            }
        }

        // Keyboard support for modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('quantityModal').classList.contains('active')) {
                closeQuantityModal();
            }
        });
    </script>
</body>
</html>