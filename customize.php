<?php
require_once 'config.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to customize furniture";
    redirect('login.php');
}

// First, ensure the customizations table has the correct structure
$check_table_query = "SHOW TABLES LIKE 'customizations'";
$table_exists = $conn->query($check_table_query);

if ($table_exists->num_rows == 0) {
    // Create the table with all necessary columns including image_path
    $create_table = "CREATE TABLE IF NOT EXISTS customizations (
        customization_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NULL,
        dimensions VARCHAR(50) NOT NULL,
        color VARCHAR(20) NOT NULL,
        material VARCHAR(50) NOT NULL,
        finish VARCHAR(30) NOT NULL,
        style VARCHAR(30) NOT NULL,
        pattern VARCHAR(30) NOT NULL,
        accent_color VARCHAR(20) NOT NULL,
        fabric_type VARCHAR(30) DEFAULT 'standard',
        cushion_fill VARCHAR(30) DEFAULT 'foam',
        leg_style VARCHAR(30) DEFAULT 'standard',
        backrest_style VARCHAR(30) DEFAULT 'standard',
        door_style VARCHAR(30) DEFAULT 'standard',
        furniture_type VARCHAR(50) DEFAULT 'cabinet',
        image_path VARCHAR(255) NULL,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'saved',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($create_table)) {
        die("Error creating table: " . $conn->error);
    }
} else {
    // Check if furniture_type column exists
    $check_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'furniture_type'");
    if ($check_column->num_rows == 0) {
        $alter_table = "ALTER TABLE customizations 
                       ADD COLUMN furniture_type VARCHAR(50) DEFAULT 'cabinet' 
                       AFTER door_style";
        $conn->query($alter_table);
    }
    
    // Check if image_path column exists
    $check_image_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'image_path'");
    if ($check_image_column->num_rows == 0) {
        $alter_table = "ALTER TABLE customizations 
                       ADD COLUMN image_path VARCHAR(255) NULL 
                       AFTER furniture_type";
        $conn->query($alter_table);
    }
}

$customization_id = isset($_GET['load']) ? (int)$_GET['load'] : 0;

// Load existing customization if ID provided
$loaded_customization = null;
if ($customization_id > 0) {
    $query = "SELECT * FROM customizations WHERE customization_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $customization_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $loaded_customization = $result->fetch_assoc();
}

// Handle AI recommendation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['get_ai_recommendation'])) {
    header('Content-Type: application/json');
    
    $preferences = json_decode($_POST['preferences'], true);
    
    // Generate AI recommendation based on preferences
    $result = generateAIRecommendation($preferences);
    
    echo json_encode([
        'success' => true,
        'recommendations' => $result
    ]);
    exit;
}

// Handle customization save with image
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_customization'])) {
    $dimensions = sanitize($_POST['dimensions']);
    $color = sanitize($_POST['color']);
    $material = sanitize($_POST['material']);
    $finish = sanitize($_POST['finish']);
    $style = sanitize($_POST['style']);
    $pattern = sanitize($_POST['pattern']);
    $accent_color = sanitize($_POST['accent_color']);
    $fabric_type = sanitize($_POST['fabric_type'] ?? 'standard');
    $cushion_fill = sanitize($_POST['cushion_fill'] ?? 'foam');
    $leg_style = sanitize($_POST['leg_style'] ?? 'standard');
    $backrest_style = sanitize($_POST['backrest_style'] ?? 'standard');
    $door_style = sanitize($_POST['door_style'] ?? 'standard');
    $furniture_type = sanitize($_POST['furniture_type'] ?? 'cabinet');
    $total_price = sanitize($_POST['total_price']);
    $user_id = $_SESSION['user_id'];
    $status = 'saved';
    
    // Handle image upload
    $image_path = null;
    if (isset($_POST['design_image_data']) && !empty($_POST['design_image_data'])) {
        $image_data = $_POST['design_image_data'];
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/customizations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $filename = 'custom_' . time() . '_' . uniqid() . '.png';
        $filepath = $upload_dir . $filename;
        
        // Remove the data URL prefix and save the image
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $image_data = base64_decode($image_data);
        
        if (file_put_contents($filepath, $image_data)) {
            $image_path = $filepath;
        }
    }

    // First, check which columns exist
    $check_furniture = $conn->query("SHOW COLUMNS FROM customizations LIKE 'furniture_type'");
    $has_furniture_type = $check_furniture->num_rows > 0;
    
    $check_image = $conn->query("SHOW COLUMNS FROM customizations LIKE 'image_path'");
    $has_image_path = $check_image->num_rows > 0;
    
    // Build query dynamically based on existing columns
    $fields = [
        'user_id',
        'product_id',
        'dimensions',
        'color',
        'material',
        'finish',
        'style',
        'pattern',
        'accent_color',
        'fabric_type',
        'cushion_fill',
        'leg_style',
        'backrest_style',
        'door_style'
    ];
    
    $values = [
        $user_id,
        null, // product_id is always NULL
        $dimensions,
        $color,
        $material,
        $finish,
        $style,
        $pattern,
        $accent_color,
        $fabric_type,
        $cushion_fill,
        $leg_style,
        $backrest_style,
        $door_style
    ];
    
    $types = "i"; // user_id is integer
    for ($i = 1; $i <= 13; $i++) {
        $types .= "s"; // 13 strings for the above fields
    }
    
    // Add furniture_type if column exists
    if ($has_furniture_type) {
        $fields[] = 'furniture_type';
        $values[] = $furniture_type;
        $types .= "s";
    }
    
    // Add image_path if column exists and image was uploaded
    if ($has_image_path) {
        $fields[] = 'image_path';
        $values[] = $image_path;
        $types .= "s";
    }
    
    // Always add total_price and status
    $fields[] = 'total_price';
    $fields[] = 'status';
    $values[] = $total_price;
    $values[] = $status;
    $types .= "ss";
    
    // Build the query
    $field_list = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    
    $query = "INSERT INTO customizations ($field_list) VALUES ($placeholders)";
    
    $stmt = $conn->prepare($query);
    
    // Create an array of references for bind_param
    $bind_params = array($types);
    foreach ($values as $key => $value) {
        $bind_params[] = &$values[$key];
    }
    
    // Call bind_param with dynamic parameters
    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    
    if ($stmt->execute()) {
        $customization_id = $conn->insert_id;
        $_SESSION['success'] = "Custom design saved successfully!";
        
        // FIXED: Always redirect to checkout page, not preview
        redirect("checkout.php?customization_id=$customization_id");
    } else {
        $error = "Failed to save customization: " . $stmt->error;
    }
}

// Handle AJAX image capture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['capture_design_image'])) {
    header('Content-Type: application/json');
    
    $image_data = $_POST['image_data'];
    $customization_id = isset($_POST['customization_id']) ? (int)$_POST['customization_id'] : 0;
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/customizations/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $filename = 'design_' . time() . '_' . uniqid() . '.png';
    $filepath = $upload_dir . $filename;
    
    // Remove the data URL prefix and save the image
    $image_data = str_replace('data:image/png;base64,', '', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    $image_data = base64_decode($image_data);
    
    if (file_put_contents($filepath, $image_data)) {
        // Update the customization record with image path
        if ($customization_id > 0) {
            $update_query = "UPDATE customizations SET image_path = ? WHERE customization_id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sii", $filepath, $customization_id, $_SESSION['user_id']);
            $update_stmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'image_path' => $filepath
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save image'
        ]);
    }
    exit;
}

function generateAIRecommendation($preferences) {
    $style = $preferences['style'] ?? 'modern';
    $room_type = $preferences['room_type'] ?? 'living';
    $color_preference = $preferences['color_preference'] ?? 'warm';
    $material_preference = $preferences['material_preference'] ?? 'wood';
    
    $recommendations = [];
    
    // Base prices per furniture type
    $base_prices = [
        'cabinet' => 900,
        'door' => 600,
        'chair' => 300,
        'bench' => 450,
        'table' => 400
    ];
    
    // Color recommendations based on style and preference
    $color_palettes = [
        'modern' => [
            'warm' => ['#D2691E', '#8B4513', '#CD853F', '#DEB887', '#F4A460'],
            'cool' => ['#2C3E50', '#34495E', '#7F8C8D', '#95A5A6', '#BDC3C7'],
            'neutral' => ['#F5F5F5', '#ECF0F1', '#B0BEC5', '#78909C', '#546E7A']
        ],
        'classic' => [
            'warm' => ['#8B4513', '#A0522D', '#D2691E', '#CD853F', '#F4A460'],
            'cool' => ['#2C3E50', '#2980B9', '#3498DB', '#5DADE2', '#85C1E9'],
            'neutral' => ['#F8F9F9', '#E5E7E9', '#D0D3D4', '#B3B6B7', '#979A9A']
        ],
        'scandinavian' => [
            'warm' => ['#F5F5DC', '#FAEBD7', '#FFE4C4', '#FFDEAD', '#F0E68C'],
            'cool' => ['#E0FFFF', '#AFEEEE', '#B0E0E6', '#ADD8E6', '#87CEEB'],
            'neutral' => ['#FFFFFF', '#F8F9F9', '#E5E7E9', '#D0D3D4', '#B3B6B7']
        ],
        'industrial' => [
            'warm' => ['#6D4C41', '#5D4037', '#4E342E', '#3E2723', '#8D6E63'],
            'cool' => ['#37474F', '#455A64', '#546E7A', '#607D8B', '#78909C'],
            'neutral' => ['#B0BEC5', '#CFD8DC', '#ECEFF1', '#90A4AE', '#78909C']
        ]
    ];
    
    // Furniture type recommendations based on room
    $furniture_recommendations = [
        'living' => ['cabinet', 'chair', 'table', 'bench'],
        'bedroom' => ['cabinet', 'bench', 'chair'],
        'dining' => ['table', 'chair', 'bench'],
        'office' => ['chair', 'table', 'cabinet']
    ];
    
    // Material recommendations
    $material_recommendations = [
        'living' => ['wood', 'leather', 'fabric'],
        'bedroom' => ['wood', 'fabric', 'upholstered'],
        'dining' => ['wood', 'marble', 'glass'],
        'office' => ['metal', 'wood', 'leather']
    ];
    
    // Pattern recommendations
    $pattern_recommendations = [
        'modern' => ['geometric', 'abstract', 'stripes'],
        'classic' => ['floral', 'herringbone', 'damask'],
        'scandinavian' => ['stripes', 'geometric', 'none'],
        'industrial' => ['none', 'abstract', 'geometric']
    ];
    
    // Generate main color palette
    $main_palette = $color_palettes[$style][$color_preference] ?? $color_palettes['modern']['neutral'];
    
    // Generate accent colors
    $accent_colors = [];
    foreach ($main_palette as $color) {
        $accent_colors[] = adjustBrightness($color, 30);
    }
    
    // Generate material suggestions
    $materials = $material_recommendations[$room_type] ?? ['wood', 'metal', 'fabric'];
    
    // Generate pattern suggestions
    $patterns = $pattern_recommendations[$style] ?? ['none', 'geometric', 'stripes'];
    
    // Generate furniture type suggestions with preview images
    $furniture_types = [];
    $suggested_types = $furniture_recommendations[$room_type] ?? ['cabinet', 'chair', 'table'];
    
    foreach ($suggested_types as $type) {
        $furniture_types[] = [
            'type' => $type,
            'name' => ucfirst($type),
            'base_price' => $base_prices[$type] ?? 500,
            'preview_image' => getFurniturePreviewImage($type, $style)
        ];
    }
    
    // Style-specific design tips
    $design_tips = [
        'modern' => [
            'Clean lines and minimal ornamentation',
            'Focus on functionality and simplicity',
            'Use neutral colors with bold accent pieces',
            'Incorporate metal and glass elements'
        ],
        'classic' => [
            'Rich wood tones and traditional details',
            'Symmetrical arrangements',
            'Ornate hardware and trim work',
            'Plush upholstery with classic patterns'
        ],
        'scandinavian' => [
            'Light, airy color palette',
            'Natural materials like light wood',
            'Simple, functional design',
            'Cozy textiles and warm accents'
        ],
        'industrial' => [
            'Exposed materials and raw finishes',
            'Metal accents and hardware',
            'Dark, moody color schemes',
            'Vintage and reclaimed elements'
        ]
    ];
    
    return [
        'main_palette' => $main_palette,
        'accent_palette' => $accent_colors,
        'suggested_materials' => array_slice($materials, 0, 3),
        'suggested_patterns' => array_slice($patterns, 0, 3),
        'suggested_furniture' => $furniture_types,
        'design_tips' => $design_tips[$style] ?? $design_tips['modern'],
        'style_confidence' => rand(75, 95),
        'recommended_finish' => getRecommendedFinish($style)
    ];
}

function getFurniturePreviewImage($type, $style) {
    // Return a data URL for a simple colored preview based on furniture type and style
    $colors = [
        'modern' => ['#3498db', '#2980b9'],
        'classic' => ['#8B4513', '#A0522D'],
        'scandinavian' => ['#F5F5F5', '#E0E0E0'],
        'industrial' => ['#616161', '#424242']
    ];
    
    $color = $colors[$style][0] ?? '#8B4513';
    $accent = $colors[$style][1] ?? '#A0522D';
    
    // Create a simple SVG representation
    $svg = '';
    switch($type) {
        case 'cabinet':
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="10" width="80" height="80" fill="' . $color . '" stroke="' . $accent . '" stroke-width="2"/>
                <rect x="25" y="25" width="20" height="40" fill="' . $accent . '" opacity="0.5"/>
                <rect x="55" y="25" width="20" height="40" fill="' . $accent . '" opacity="0.5"/>
                <circle cx="45" cy="45" r="3" fill="#FFD700"/>
                <circle cx="75" cy="45" r="3" fill="#FFD700"/>
            </svg>';
            break;
        case 'door':
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="10" width="60" height="80" fill="' . $color . '" stroke="' . $accent . '" stroke-width="2"/>
                <rect x="35" y="25" width="30" height="20" fill="' . $accent . '" opacity="0.3"/>
                <circle cx="70" cy="50" r="5" fill="#FFD700"/>
            </svg>';
            break;
        case 'chair':
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="25" y="40" width="50" height="10" fill="' . $color . '"/>
                <rect x="30" y="20" width="40" height="20" fill="' . $color . '"/>
                <rect x="35" y="50" width="10" height="30" fill="' . $accent . '"/>
                <rect x="55" y="50" width="10" height="30" fill="' . $accent . '"/>
            </svg>';
            break;
        case 'bench':
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="15" y="40" width="70" height="15" fill="' . $color . '"/>
                <rect x="20" y="20" width="60" height="20" fill="' . $color . '"/>
                <rect x="25" y="55" width="10" height="25" fill="' . $accent . '"/>
                <rect x="65" y="55" width="10" height="25" fill="' . $accent . '"/>
                <rect x="45" y="55" width="10" height="25" fill="' . $accent . '"/>
            </svg>';
            break;
        case 'table':
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="30" width="80" height="10" fill="' . $color . '"/>
                <rect x="20" y="40" width="10" height="30" fill="' . $accent . '"/>
                <rect x="70" y="40" width="10" height="30" fill="' . $accent . '"/>
                <rect x="45" y="40" width="10" height="30" fill="' . $accent . '"/>
            </svg>';
            break;
        default:
            $svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="20" width="60" height="60" fill="' . $color . '"/>
            </svg>';
    }
    
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));
    
    return '#' . sprintf("%02x%02x%02x", $r, $g, $b);
}

function getRecommendedFinish($style) {
    $finishes = [
        'modern' => 'glossy',
        'classic' => 'polished',
        'scandinavian' => 'matte',
        'industrial' => 'textured'
    ];
    return $finishes[$style] ?? 'matte';
}

// Fetch user's saved customizations for quick load
$saved_customizations = [];
if (isLoggedIn()) {
    // First check if furniture_type column exists for the SELECT query
    $check_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'furniture_type'");
    $has_furniture_type = $check_column->num_rows > 0;
    
    $check_image_column = $conn->query("SHOW COLUMNS FROM customizations LIKE 'image_path'");
    $has_image_path = $check_image_column->num_rows > 0;
    
    if ($has_furniture_type && $has_image_path) {
        $query = "SELECT * FROM customizations 
                  WHERE user_id = ? AND product_id IS NULL 
                  ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $saved_customizations[] = $row;
        }
    } elseif ($has_furniture_type) {
        $query = "SELECT customization_id, user_id, product_id, dimensions, color, material, 
                         finish, style, pattern, accent_color, fabric_type, cushion_fill, 
                         leg_style, backrest_style, door_style, furniture_type, total_price, status, 
                         created_at, updated_at 
                  FROM customizations 
                  WHERE user_id = ? AND product_id IS NULL 
                  ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $row['furniture_type'] = $row['furniture_type'] ?? 'cabinet';
            $saved_customizations[] = $row;
        }
    } else {
        $query = "SELECT customization_id, user_id, product_id, dimensions, color, material, 
                         finish, style, pattern, accent_color, fabric_type, cushion_fill, 
                         leg_style, backrest_style, door_style, total_price, status, 
                         created_at, updated_at 
                  FROM customizations 
                  WHERE user_id = ? AND product_id IS NULL 
                  ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $row['furniture_type'] = 'cabinet';
            $saved_customizations[] = $row;
        }
    }
}

// Base price is now 0 - price only added when customizing
$base_price = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Custom Furniture Designer · Furniverse</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Three.js and addons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <!-- html2canvas for capturing 3D view (fallback only) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
            line-height: 1.5;
            overflow-x: hidden;
        }

        h1, h2, h3, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight:600;
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

        /* Navbar styles */
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
            max-width: 1600px;
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

        /* Page Header */
        .page-header {
            background: radial-gradient(ellipse at 70% 30%, #2f2642, #0a0713 80%);
            padding: 4rem 2rem;
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
            font-size: 3.5rem;
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
            font-size: 1.2rem;
            color: #cbc2e6;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            font-weight: 300;
        }

        /* Quick Load Bar */
        .quick-load {
            max-width: 1600px;
            margin: 2rem auto 0;
            padding: 0 2rem;
        }

        .saved-items {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding: 1rem 0;
            scrollbar-width: thin;
            scrollbar-color: #cfb087 #1e192c;
        }

        .saved-items::-webkit-scrollbar {
            height: 6px;
        }

        .saved-items::-webkit-scrollbar-track {
            background: #1e192c;
            border-radius: 10px;
        }

        .saved-items::-webkit-scrollbar-thumb {
            background: #cfb087;
            border-radius: 10px;
        }

        .saved-item {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 30px;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: 0.2s;
            min-width: 200px;
        }

        .saved-item:hover {
            border-color: #cfb087;
            transform: translateY(-2px);
            background: #2d2640;
        }

        .saved-item .color-dot {
            width: 30px;
            height: 30px;
            border-radius: 15px;
            border: 2px solid #3d3452;
        }

        .saved-item-info h4 {
            color: #f0e6d2;
            font-size: 1rem;
            margin-bottom: 0.2rem;
        }

        .saved-item-info p {
            color: #b3a4cb;
            font-size: 0.8rem;
        }

        /* Container */
        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Alerts */
        .alert {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 500;
            text-align: center;
            border: 1px solid;
        }

        .alert.error {
            background: #2d1a24;
            color: #ffb3b3;
            border-color: #b84a6e;
        }

        .alert.success {
            background: #1a2d24;
            color: #b3ffb3;
            border-color: #4a9b6e;
        }

        /* Welcome Banner */
        .welcome-banner {
            max-width: 1600px;
            margin: 2rem auto 0;
            padding: 0 2rem;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1e192c, #2a1f3a);
            border: 1px solid #cfb08733;
            border-radius: 50px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 15px 30px -10px rgba(0,0,0,0.5);
        }

        .welcome-card h2 {
            font-size: 2.5rem;
            color: #f0e6d2;
            margin-bottom: 1rem;
        }

        .welcome-card p {
            color: #b3a4cb;
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Capture Buttons */
        .capture-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
            border-radius: 40px;
            padding: 0.8rem 1.5rem;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .capture-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .preview-capture-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 40px;
            padding: 0.8rem 1.5rem;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-capture-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Customization Interface */
        .customization-interface {
            margin: 2rem 0 5rem;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2rem;
        }

        /* 3D Preview Section */
        .preview-section {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .preview-header h2 {
            font-size: 2rem;
            color: #f0e6d2;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .preview-header h2 i {
            color: #cfb087;
        }

        .ai-badge {
            background: linear-gradient(135deg, #2d2640, #1e192c);
            border: 1px solid #cfb087;
            border-radius: 40px;
            padding: 0.5rem 1.2rem;
            font-size: 0.9rem;
            color: #cfb087;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-box {
            background: #161224;
            border-radius: 42px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #332d44;
            position: relative;
        }

        #canvas-container {
            width: 100%;
            height: 500px;
            border-radius: 32px;
            overflow: hidden;
            position: relative;
            background: #0f0b17;
            cursor: grab;
        }

        #canvas-container:active {
            cursor: grabbing;
        }

        /* View Controls */
        .view-controls {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .view-btn {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
            color: #dacfef;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .view-btn:hover,
        .view-btn.active {
            background: #2d2640;
            border-color: #cfb087;
            color: #f0e6d2;
        }

        /* Furniture Type Selector */
        .furniture-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .type-btn {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 40px;
            padding: 1rem 2rem;
            color: #dacfef;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1rem;
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }

        .type-btn:hover,
        .type-btn.active {
            background: #2d2640;
            border-color: #cfb087;
            color: #f0e6d2;
            transform: translateY(-2px);
        }

        .type-btn i {
            color: #cfb087;
            font-size: 1.2rem;
        }

        /* AI Recommender Panel */
        .ai-recommender-panel {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 36px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .ai-recommender-panel h3 {
            color: #f0e6d2;
            font-size: 1.3rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ai-recommender-panel h3 i {
            color: #cfb087;
        }

        .preference-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .preference-group {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 20px;
            padding: 1rem;
        }

        .preference-group label {
            display: block;
            color: #b3a4cb;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .preference-group select {
            width: 100%;
            background: #161224;
            border: 1px solid #3d3452;
            border-radius: 15px;
            padding: 0.7rem;
            color: #f0e6d2;
            font-size: 0.9rem;
        }

        .recommend-btn {
            width: 100%;
            background: linear-gradient(135deg, #cfb087, #bba6d9);
            border: none;
            border-radius: 40px;
            padding: 1rem;
            color: #0f0b17;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .recommend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(207, 176, 135, 0.3);
        }

        .recommend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .recommendation-loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .recommendation-loading i {
            font-size: 2.5rem;
            color: #cfb087;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .recommendation-results {
            display: none;
            margin-top: 1.5rem;
        }

        .color-palette {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            justify-content: center;
        }

        .color-chip {
            width: 50px;
            height: 50px;
            border-radius: 25px;
            border: 2px solid #3d3452;
            cursor: pointer;
            transition: 0.2s;
        }

        .color-chip:hover {
            transform: scale(1.1);
            border-color: #cfb087;
        }

        .material-chips, .pattern-chips, .furniture-chips {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }

        .material-chip, .pattern-chip, .furniture-chip {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 30px;
            padding: 0.5rem 1rem;
            color: #dacfef;
            cursor: pointer;
            transition: 0.2s;
        }

        .material-chip:hover, .pattern-chip:hover, .furniture-chip:hover {
            background: #2d2640;
            border-color: #cfb087;
            transform: translateY(-2px);
        }

        .confidence-bar {
            background: #1e192c;
            height: 8px;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }

        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #cfb087, #bba6d9);
            border-radius: 4px;
        }

        .design-tips {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 20px;
            padding: 1rem;
            margin: 1rem 0;
            list-style: none;
        }

        .design-tips li {
            color: #b3a4cb;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .design-tips li i {
            color: #cfb087;
        }

        /* Furniture Preview Cards */
        .furniture-preview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .furniture-preview-card {
            background: #1e192c;
            border: 2px solid #3d3452;
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .furniture-preview-card:hover,
        .furniture-preview-card.active {
            border-color: #cfb087;
            background: #2d2640;
            transform: translateY(-2px);
        }

        .furniture-preview-card img {
            width: 80px;
            height: 80px;
            margin-bottom: 0.5rem;
            border-radius: 10px;
        }

        .furniture-preview-card h4 {
            color: #f0e6d2;
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }

        .furniture-preview-card p {
            color: #cfb087;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Controls Panel */
        .controls-panel {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 40px -12px #010101;
        }

        .controls-header {
            margin-bottom: 2rem;
        }

        .controls-header h2 {
            font-size: 2rem;
            color: #f0e6d2;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        /* Accordion Sections */
        .accordion-section {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 30px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .accordion-header {
            padding: 1.2rem 1.5rem;
            background: #1e192c;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #f0e6d2;
            font-weight: 500;
            transition: 0.2s;
        }

        .accordion-header:hover {
            background: #2d2640;
        }

        .accordion-header i {
            color: #cfb087;
            margin-right: 0.8rem;
        }

        .accordion-header .toggle-icon {
            transition: transform 0.3s;
        }

        .accordion-header.active .toggle-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            padding: 1.5rem;
            display: none;
        }

        .accordion-content.active {
            display: block;
        }

        /* Dimension Controls */
        .dimension-sliders {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .slider-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .slider-group label {
            color: #b3a4cb;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .slider-group input[type="range"] {
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #4a3f60, #cfb087);
            border-radius: 3px;
            -webkit-appearance: none;
        }

        .slider-group input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #cfb087;
            border: 3px solid #2d2640;
            border-radius: 50%;
            cursor: pointer;
        }

        .slider-values {
            display: flex;
            justify-content: space-between;
            color: #cfb087;
            font-size: 0.9rem;
        }

        /* Material Grid */
        .material-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
        }

        .material-card {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 20px;
            padding: 1rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .material-card:hover,
        .material-card.active {
            border-color: #cfb087;
            background: #2d2640;
            transform: translateY(-2px);
        }

        .material-card i {
            color: #cfb087;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .material-card span {
            display: block;
            font-size: 0.9rem;
            color: #e2ddf2;
        }

        /* Finish Options */
        .finish-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .finish-btn {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 30px;
            padding: 0.8rem;
            color: #dacfef;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
        }

        .finish-btn:hover,
        .finish-btn.active {
            background: #2d2640;
            border-color: #cfb087;
            color: #f0e6d2;
        }

        /* Color Grid */
        .color-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .color-swatch {
            aspect-ratio: 1;
            border-radius: 20px;
            border: 3px solid #3d3452;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
        }

        .color-swatch:hover {
            transform: scale(1.05);
            border-color: #cfb087;
        }

        .color-swatch.active::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            font-size: 1.2rem;
            font-weight: bold;
        }

        .custom-color-picker {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .custom-color-picker input[type="color"] {
            width: 50px;
            height: 50px;
            border: 3px solid #3d3452;
            border-radius: 25px;
            cursor: pointer;
        }

        /* Door Styles */
        .door-style-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.8rem;
        }

        .door-style-option {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .door-style-option:hover,
        .door-style-option.active {
            border-color: #cfb087;
            background: #2d2640;
        }

        .door-style-option i {
            color: #cfb087;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .door-style-option span {
            display: block;
            font-size: 0.9rem;
            color: #e2ddf2;
        }

        /* Pattern Grid */
        .pattern-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
        }

        .pattern-option {
            background: #1e192c;
            border: 1px solid #3d3452;
            border-radius: 20px;
            padding: 1rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .pattern-option:hover,
        .pattern-option.active {
            border-color: #cfb087;
            background: #2d2640;
            transform: translateY(-2px);
        }

        .pattern-option i {
            color: #cfb087;
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
        }

        .pattern-option span {
            display: block;
            font-size: 0.8rem;
            color: #e2ddf2;
        }

        /* Price Calculator - Simplified */
        .price-calculator {
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 36px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .price-breakdown {
            background: #1e192c;
            border-radius: 30px;
            padding: 1.2rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            color: #b3a4cb;
        }

        .price-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            border-top: 1px dashed #4a3f60;
            padding-top: 1rem;
            margin-top: 0.5rem;
            color: #cfb087;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-primary {
            background: transparent;
            border: 1.5px solid #cfb087;
            color: #f0e6d2;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(207, 176, 135, 0.5);
        }

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #5a4a78;
            border-radius: 40px;
            padding: 1rem;
            font-weight: 500;
            color: #dacfef;
            cursor: pointer;
            transition: 0.2s;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: #3d2e55;
            border-color: #bba6d9;
            color: #fff;
        }

        /* Capture Preview Modal */
        .capture-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .capture-modal-content {
            background: #1e192c;
            padding: 2rem;
            border-radius: 30px;
            max-width: 600px;
            width: 90%;
            border: 1px solid #cfb087;
        }

        .capture-modal-content img {
            width: 100%;
            border-radius: 20px;
            border: 3px solid #cfb087;
            margin-bottom: 1.5rem;
        }

        .capture-modal-buttons {
            display: flex;
            gap: 1rem;
        }

        .capture-modal-buttons button {
            flex: 1;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .capture-close-btn {
            background: #2d2640;
            border: 1px solid #5a4a78;
            color: #f0e6d2;
        }

        .capture-close-btn:hover {
            background: #3d2e55;
        }

        .capture-save-btn {
            background: #cfb087;
            border: none;
            color: #0f0b17;
        }

        .capture-save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(207, 176, 135, 0.3);
        }

        .capture-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #27ae60;
            color: white;
            padding: 1rem 2rem;
            border-radius: 40px;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Footer */
        footer {
            background: #0c0818;
            border-top: 1px solid #332b44;
            padding: 4rem 2rem 2rem;
            margin-top: 6rem;
        }

        .footer-content {
            max-width: 1600px;
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

        /* Responsive */
        @media (max-width: 1400px) {
            .main-grid {
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
            .page-header h1 {
                font-size: 2.5rem;
            }
            .preview-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 2rem;
            }
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .action-buttons {
                grid-template-columns: 1fr;
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

                <li><a href="customize.php" class="active">Customize</a></li>
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
        <h1>Custom Furniture Design</h1>
        <p>Create your dream furniture from scratch </p>
    </header>

    <?php if (!empty($saved_customizations)): ?>
    <div class="quick-load">
        <div class="saved-items">
            <?php foreach($saved_customizations as $saved): ?>
                <?php 
                $dims = explode('x', $saved['dimensions']); 
                $color = $saved['color'] ?? '#8B4513';
                ?>
                <div class="saved-item" onclick="loadCustomization(<?php echo htmlspecialchars(json_encode($saved)); ?>)">
                    <?php if (!empty($saved['image_path'])): ?>
                        <img src="<?php echo $saved['image_path']; ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover;">
                    <?php else: ?>
                        <div class="color-dot" style="background: <?php echo $color; ?>;"></div>
                    <?php endif; ?>
                    <div class="saved-item-info">
                        <h4><?php echo ucfirst($saved['furniture_type'] ?? 'Custom Piece'); ?></h4>
                        <p><?php echo ucfirst($saved['style']); ?> · <?php echo ucfirst($saved['material']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
       

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-card">
                <h2><i class="fas fa-pencil-ruler"></i> Start Your Custom Design</h2>
                <p>Choose a furniture type below and customize every detail - dimensions, materials, colors, and more.</p>
            </div>
        </div>

        <!-- AI-Powered Customization Interface -->
        <div class="customization-interface">
            <div class="main-grid">
                <!-- Left Column: 3D Preview & AI Recommender -->
                <div class="preview-section">
                    <div class="preview-header">
                        <h2><i class="fas fa-cube"></i> 3D Preview</h2>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button class="preview-capture-btn" id="previewCaptureBtn" onclick="manualCaptureWithPreview()">
                                <i class="fas fa-eye"></i> Preview Capture
                            </button>
                            <button class="capture-btn" id="captureDesign" onclick="captureDesignImage(false)">
                                <i class="fas fa-camera"></i> Capture Design
                            </button>
                            
                        </div>
                    </div>
                    
                    <!-- Furniture Type Selector -->
                    <div class="furniture-type-selector">
                        <button class="type-btn" data-type="cabinet">
                            <i class="fas fa-cabinet-filing"></i> Cabinet (₱900)
                        </button>
                        <button class="type-btn" data-type="door">
                            <i class="fas fa-door-open"></i> Door (₱600)
                        </button>
                        <button class="type-btn" data-type="chair">
                            <i class="fas fa-chair"></i> Chair (₱300)
                        </button>
                        <button class="type-btn" data-type="bench">
                            <i class="fas fa-chair"></i> Bench (₱450)
                        </button>
                        <button class="type-btn" data-type="table">
                            <i class="fas fa-table"></i> Table (₱400)
                        </button>
                    </div>
                    
                    <div class="preview-box">
                        <div id="canvas-container"></div>
                        
                        <!-- View Controls -->
                        <div class="view-controls">
                            <button class="view-btn" id="view-front"><i class="fas fa-arrow-left"></i> Front</button>
                            <button class="view-btn" id="view-side"><i class="fas fa-arrow-right"></i> Side</button>
                            <button class="view-btn" id="view-top"><i class="fas fa-arrow-up"></i> Top</button>
                            <button class="view-btn" id="view-rotate"><i class="fas fa-sync"></i> Rotate</button>
                            <button class="view-btn" id="view-reset"><i class="fas fa-home"></i> Reset</button>
                            <button class="view-btn" id="view-enhance"><i class="fas fa-sun"></i> Enhance</button>
                        </div>
                    </div>
                    
                    <!-- AI Recommender Panel -->
                    <div class="ai-recommender-panel">
                        <h3><i class="fas fa-robot"></i> AI Design Recommender</h3>
                        
                        <div class="preference-selector">
                            <div class="preference-group">
                                <label><i class="fas fa-palette"></i> Style</label>
                                <select id="pref-style">
                                    <option value="modern">Modern</option>
                                    <option value="classic">Classic</option>
                                    <option value="scandinavian">Scandinavian</option>
                                    <option value="industrial">Industrial</option>
                                </select>
                            </div>
                            <div class="preference-group">
                                <label><i class="fas fa-door-open"></i> Room</label>
                                <select id="pref-room">
                                    <option value="living">Living Room</option>
                                    <option value="bedroom">Bedroom</option>
                                    <option value="dining">Dining Room</option>
                                    <option value="office">Office</option>
                                </select>
                            </div>
                            <div class="preference-group">
                                <label><i class="fas fa-fill-drip"></i> Color Mood</label>
                                <select id="pref-color">
                                    <option value="warm">Warm & Cozy</option>
                                    <option value="cool">Cool & Calm</option>
                                    <option value="neutral">Neutral</option>
                                </select>
                            </div>
                            <div class="preference-group">
                                <label><i class="fas fa-tree"></i> Material</label>
                                <select id="pref-material">
                                    <option value="wood">Wood</option>
                                    
                                </select>
                            </div>
                        </div>
                        
                        <button class="recommend-btn" id="getRecommendation">
                            <i class="fas fa-magic"></i> Get AI Recommendations
                        </button>
                        
                        <div id="recommendation-loading" class="recommendation-loading">
                            <i class="fas fa-circle-notch"></i>
                            <p style="margin-top: 1rem; color: #b3a4cb;">AI analyzing your preferences...</p>
                        </div>
                        
                        <div id="recommendation-results" class="recommendation-results">
                            <h4 style="color: #f0e6d2; margin-bottom: 1rem;">AI Recommended Furniture</h4>
                            <div id="furniture-suggestions" class="furniture-preview-grid"></div>
                            
                            <h4 style="color: #f0e6d2; margin-bottom: 1rem;">Recommended Color Palette</h4>
                            <div id="color-palette" class="color-palette"></div>
                            
                            <h4 style="color: #f0e6d2; margin: 1rem 0;">Suggested Materials</h4>
                            <div id="material-suggestions" class="material-chips"></div>
                            
                            <h4 style="color: #f0e6d2; margin: 1rem 0;">Pattern Ideas</h4>
                            <div id="pattern-suggestions" class="pattern-chips"></div>
                            
                            <div class="confidence-bar">
                                <div id="confidence-fill" class="confidence-fill" style="width: 85%"></div>
                            </div>
                            <p style="color: #b3a4cb; text-align: center;">AI confidence: <span id="confidence-value">85%</span></p>
                            
                            <h4 style="color: #f0e6d2; margin: 1rem 0;">Design Tips</h4>
                            <ul id="design-tips" class="design-tips"></ul>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Controls -->
                <div class="controls-panel">
                    <div class="controls-header">
                        <h2><i class="fas fa-sliders-h"></i> Design Your Furniture</h2>
                        <p style="color: #cfb087;">Start with a plain design and customize each element</p>
                    </div>
                    
                    <!-- Dimensions Accordion -->
                    <div class="accordion-section">
                        <div class="accordion-header active">
                            <span><i class="fas fa-arrows-alt"></i> Dimensions</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content active">
                            <div class="dimension-sliders">
                                <div class="slider-group">
                                    <label><i class="fas fa-arrows-alt-h"></i> Width (cm)</label>
                                    <input type="range" id="width" min="50" max="200" value="100" step="5">
                                    <div class="slider-values">
                                        <span>50cm</span>
                                        <span id="width-value">100cm</span>
                                        <span>200cm</span>
                                    </div>
                                </div>
                                <div class="slider-group">
                                    <label><i class="fas fa-arrows-alt-h" style="transform: rotate(90deg);"></i> Height (cm)</label>
                                    <input type="range" id="depth" min="40" max="150" value="50" step="5">
                                    <div class="slider-values">
                                        <span>40cm</span>
                                        <span id="depth-value">50cm</span>
                                        <span>150cm</span>
                                    </div>
                                </div>
                                <div class="slider-group">
                                    <label><i class="fas fa-arrows-alt-v"></i> Thick (cm)</label>
                                    <input type="range" id="height" min="40" max="250" value="200" step="5">
                                    <div class="slider-values">
                                        <span>40cm</span>
                                        <span id="height-value">200cm</span>
                                        <span>250cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Material Accordion -->
                    <div class="accordion-section">
                        <div class="accordion-header">
                            <span><i class="fas fa-palette"></i> Material & Finish</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content">
                            <h4 style="color: #f0e6d2; margin-bottom: 1rem;">Material</h4>
                            <div class="material-grid">
                                <div class="material-card" data-material="wood">
                                    <i class="fas fa-tree"></i>
                                    <span>Wood</span>
                                </div>
                                <div class="material-card" data-material="oak">
                                    <i class="fas fa-tree"></i>
                                    <span>Oak</span>
                                </div>
                                <div class="material-card" data-material="walnut">
                                    <i class="fas fa-tree"></i>
                                    <span>Walnut</span>
                                </div>
                                <div class="material-card" data-material="mahogany">
                                    <i class="fas fa-tree"></i>
                                    <span>Mahogany</span>
                                </div>
                                <div class="material-card" data-material="metal">
                                    <i class="fas fa-cog"></i>
                                    <span>Metal</span>
                                </div>
                                <div class="material-card" data-material="brass">
                                    <i class="fas fa-cog"></i>
                                    <span>Brass</span>
                                </div>
                                <div class="material-card" data-material="copper">
                                    <i class="fas fa-cog"></i>
                                    <span>Copper</span>
                                </div>
                                <div class="material-card" data-material="marble">
                                    <i class="fas fa-mountain"></i>
                                    <span>Marble</span>
                                </div>
                                <div class="material-card" data-material="leather">
                                    <i class="fas fa-cow"></i>
                                    <span>Leather</span>
                                </div>
                            </div>
                            
                            <h4 style="color: #f0e6d2; margin: 1.5rem 0 1rem;">Finish</h4>
                            <div class="finish-options">
                                <button class="finish-btn" data-finish="matte">Matte</button>
                                <button class="finish-btn" data-finish="glossy">Glossy</button>
                                <button class="finish-btn" data-finish="textured">Textured</button>
                                <button class="finish-btn" data-finish="polished">Polished</button>
                                <button class="finish-btn" data-finish="distressed">Distressed</button>
                                <button class="finish-btn" data-finish="antique">Antique</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Color Accordion -->
                    <div class="accordion-section">
                        <div class="accordion-header">
                            <span><i class="fas fa-paint-brush"></i> Color</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content">
                            <h4 style="color: #f0e6d2; margin-bottom: 1rem;">Main Color</h4>
                            <div class="color-grid">
                                <div class="color-swatch" style="background: #8B4513;" data-color="#8B4513"></div>
                                <div class="color-swatch" style="background: #A0522D;" data-color="#A0522D"></div>
                                <div class="color-swatch" style="background: #D2691E;" data-color="#D2691E"></div>
                                <div class="color-swatch" style="background: #2C3E50;" data-color="#2C3E50"></div>
                                <div class="color-swatch" style="background: #8E44AD;" data-color="#8E44AD"></div>
                                <div class="color-swatch" style="background: #2980B9;" data-color="#2980B9"></div>
                                <div class="color-swatch" style="background: #27AE60;" data-color="#27AE60"></div>
                                <div class="color-swatch" style="background: #E67E22;" data-color="#E67E22"></div>
                                <div class="color-swatch" style="background: #F5F5F5;" data-color="#F5F5F5"></div>
                                <div class="color-swatch" style="background: #000000;" data-color="#000000"></div>
                                <div class="color-swatch" style="background: #C0C0C0;" data-color="#C0C0C0"></div>
                                <div class="color-swatch" style="background: #FFD700;" data-color="#FFD700"></div>
                            </div>
                            
                            <div class="custom-color-picker">
                                <input type="color" id="custom-color" value="#8B4513">
                                <span style="color: #b3a4cb;">Custom color</span>
                            </div>
                            
                            <h4 style="color: #f0e6d2; margin: 1.5rem 0 1rem;">Accent Color</h4>
                            <div class="custom-color-picker">
                                <input type="color" id="accent-color" value="#d4a373">
                                <span style="color: #b3a4cb;">Secondary color for details</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Door Styles (only relevant for cabinets/doors) -->
                    <div class="accordion-section" id="door-styles-section">
                        <div class="accordion-header">
                            <span><i class="fas fa-door-open"></i> Door Styles</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="door-style-grid">
                                <div class="door-style-option" data-door="standard">
                                    <i class="fas fa-door-closed"></i>
                                    <span>Standard</span>
                                </div>
                                <div class="door-style-option" data-door="paneled">
                                    <i class="fas fa-border-all"></i>
                                    <span>Paneled</span>
                                </div>
                                <div class="door-style-option" data-door="french">
                                    <i class="fas fa-door-open"></i>
                                    <span>French</span>
                                </div>
                                <div class="door-style-option" data-door="sliding">
                                    <i class="fas fa-arrows-alt-h"></i>
                                    <span>Sliding</span>
                                </div>
                                <div class="door-style-option" data-door="pocket">
                                    <i class="fas fa-arrow-right"></i>
                                    <span>Pocket</span>
                                </div>
                                <div class="door-style-option" data-door="barn">
                                    <i class="fas fa-warehouse"></i>
                                    <span>Barn</span>
                                </div>
                            </div>
                            
                            <h4 style="color: #f0e6d2; margin: 1.5rem 0 1rem;">Door Details</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label style="color: #b3a4cb; display: block; margin-bottom: 0.5rem;">Number of Panels</label>
                                    <select id="door-panels" class="select-style">
                                        <option value="1">1 Panel</option>
                                        <option value="2">2 Panels</option>
                                        <option value="3">3 Panels</option>
                                        <option value="4">4 Panels</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="color: #b3a4cb; display: block; margin-bottom: 0.5rem;">Handle Style</label>
                                    <select id="door-handle" class="select-style">
                                        <option value="knob">Knob</option>
                                        <option value="lever">Lever</option>
                                        <option value="pull">Pull Handle</option>
                                        <option value="bar">Bar Handle</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pattern/Design Accordion -->
                    <div class="accordion-section">
                        <div class="accordion-header">
                            <span><i class="fas fa-border-all"></i> Design Patterns</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="pattern-grid">
                                <div class="pattern-option" data-pattern="none">
                                    <i class="fas fa-ban"></i>
                                    <span>Plain</span>
                                </div>
                                <div class="pattern-option" data-pattern="stripes">
                                    <i class="fas fa-bars"></i>
                                    <span>Stripes</span>
                                </div>
                                <div class="pattern-option" data-pattern="checker">
                                    <i class="fas fa-border-all"></i>
                                    <span>Checker</span>
                                </div>
                                <div class="pattern-option" data-pattern="dots">
                                    <i class="fas fa-circle"></i>
                                    <span>Dots</span>
                                </div>
                                <div class="pattern-option" data-pattern="herringbone">
                                    <i class="fas fa-grip-lines"></i>
                                    <span>Herringbone</span>
                                </div>
                                <div class="pattern-option" data-pattern="floral">
                                    <i class="fas fa-leaf"></i>
                                    <span>Floral</span>
                                </div>
                                <div class="pattern-option" data-pattern="geometric">
                                    <i class="fas fa-shapes"></i>
                                    <span>Geometric</span>
                                </div>
                                <div class="pattern-option" data-pattern="abstract">
                                    <i class="fas fa-brush"></i>
                                    <span>Abstract</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Price Calculator - Simplified -->
                    <div class="price-calculator">
                        <h3 style="color: #f0e6d2; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calculator" style="color: #cfb087;"></i> Price Breakdown
                        </h3>
                        <div class="price-breakdown">
                            <div class="price-row">
                                <span>Base price:</span>
                                <span id="base-price">₱900.00</span>
                            </div>
                            <div class="price-row">
                                <span>Size addition (₱5 per 10cm):</span>
                                <span id="size-addition">+₱0</span>
                            </div>
                            <div class="price-row total">
                                <span>Total:</span>
                                <span id="total-price">₱900.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="btn-primary" id="save-customization">
                            <i class="fas fa-check"></i> Save & Checkout
                        </button>
                        <button class="btn-secondary" id="save-draft">
                            <i class="fas fa-save"></i> Save Draft
                        </button>
                    </div>
                    
                    <form method="POST" action="" id="customization-form" style="display: none;">
                        <input type="hidden" name="total_price" id="total-price-input" value="900">
                        <input type="hidden" name="color" id="main-color-input" value="#8B4513">
                        <input type="hidden" name="accent_color" id="accent-color-input" value="#d4a373">
                        <input type="hidden" name="material" id="material-input" value="wood">
                        <input type="hidden" name="finish" id="finish-input" value="matte">
                        <input type="hidden" name="style" id="style-input" value="modern">
                        <input type="hidden" name="pattern" id="pattern-input" value="none">
                        <input type="hidden" name="fabric_type" id="fabric-type-input" value="standard">
                        <input type="hidden" name="cushion_fill" id="cushion-fill-input" value="foam">
                        <input type="hidden" name="leg_style" id="leg-style-input" value="standard">
                        <input type="hidden" name="backrest_style" id="backrest-style-input" value="standard">
                        <input type="hidden" name="door_style" id="door-style-input" value="standard">
                        <input type="hidden" name="furniture_type" id="furniture-type-input" value="cabinet">
                        <input type="hidden" name="dimensions" id="dimensions-hidden" value="100x50x200">
                        <input type="hidden" name="design_image_data" id="design-image-data" value="">
                        <input type="hidden" name="save_customization" value="1">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Furniverse AI Studio</h3>
                <p><i class="fas fa-location-dot" style="margin-right: 10px; color:#cfb087;"></i> Poblacion, Tupi, South Cotabato</p>
                <p><i class="fas fa-phone" style="margin-right: 10px; color:#cfb087;"></i> +63 912 345 6789</p>
                <p><i class="fas fa-envelope" style="margin-right: 10px; color:#cfb087;"></i> studio@furniverse.com</p>
            </div>
            <div class="footer-section">
                <h3>Inside</h3>
                <ul>
                    <li><a href="about.php">The Studio</a></li>
                    <li><a href="privacy.php">Privacy</a></li>
                    <li><a href="terms.php">Terms</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Furniverse · AI-Powered Custom Furniture Designer</p>
        </div>
    </footer>

<script>
// Base prices per furniture type
const basePrices = {
    cabinet: 900,
    door: 600,
    chair: 300,
    bench: 450,
    table: 400
};

// Three.js setup
let scene, camera, renderer, controls;
let furnitureGroup;
let currentFurnitureType = 'cabinet';
let currentMaterial = 'wood';
let currentFinish = 'matte';
let currentColor = '#8B4513';
let currentAccentColor = '#d4a373';
let currentStyle = 'modern';
let currentPattern = 'none';
let currentFabricType = 'standard';
let currentCushionFill = 'foam';
let currentLegStyle = 'standard';
let currentBackrestStyle = 'standard';
let currentDoorStyle = 'standard';
let currentDoorPanels = '2';
let currentDoorHandle = 'knob';
let autoRotate = false;

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    init3D();
    initEventListeners();
    calculatePrice();
});

// Initialize event listeners
function initEventListeners() {
    // Hamburger menu
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }

    // Furniture type switching
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFurnitureType = this.dataset.type;
            document.getElementById('furniture-type-input').value = currentFurnitureType;
            document.getElementById('base-price').textContent = '₱' + basePrices[currentFurnitureType].toFixed(2);
            redrawFurniture();
            calculatePrice();
        });
    });
    
    // Material switching
    document.querySelectorAll('.material-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.material-card').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentMaterial = this.dataset.material;
            document.getElementById('material-input').value = currentMaterial;
            redrawFurniture();
        });
    });
    
    // Finish switching
    document.querySelectorAll('.finish-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.finish-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFinish = this.dataset.finish;
            document.getElementById('finish-input').value = currentFinish;
            redrawFurniture();
        });
    });
    
    // Door style switching
    document.querySelectorAll('.door-style-option').forEach(opt => {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.door-style-option').forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            currentDoorStyle = this.dataset.door;
            document.getElementById('door-style-input').value = currentDoorStyle;
            redrawFurniture();
        });
    });
    
    // Color swatches
    document.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.addEventListener('click', function() {
            document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            currentColor = this.dataset.color;
            document.getElementById('custom-color').value = currentColor;
            document.getElementById('main-color-input').value = currentColor;
            redrawFurniture();
        });
    });
    
    // Custom color picker
    document.getElementById('custom-color').addEventListener('input', function() {
        currentColor = this.value;
        document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
        document.getElementById('main-color-input').value = currentColor;
        redrawFurniture();
    });
    
    // Accent color
    document.getElementById('accent-color').addEventListener('input', function() {
        currentAccentColor = this.value;
        document.getElementById('accent-color-input').value = currentAccentColor;
        redrawFurniture();
    });
    
    // Pattern options
    document.querySelectorAll('.pattern-option').forEach(opt => {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.pattern-option').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentPattern = this.dataset.pattern;
            document.getElementById('pattern-input').value = currentPattern;
            redrawFurniture();
        });
    });
    
    // Accordion headers
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', function() {
            this.classList.toggle('active');
            const content = this.nextElementSibling;
            content.classList.toggle('active');
        });
    });
    
    // View controls
    document.getElementById('view-front').addEventListener('click', function() {
        camera.position.set(0, 2, 10);
        controls.target.set(0, 1.5, 0);
        controls.update();
    });
    
    document.getElementById('view-side').addEventListener('click', function() {
        camera.position.set(10, 2, 0);
        controls.target.set(0, 1.5, 0);
        controls.update();
    });
    
    document.getElementById('view-top').addEventListener('click', function() {
        camera.position.set(0, 10, 0);
        controls.target.set(0, 1.5, 0);
        controls.update();
    });
    
    document.getElementById('view-rotate').addEventListener('click', function() {
        autoRotate = !autoRotate;
        controls.autoRotate = autoRotate;
        this.classList.toggle('active', autoRotate);
    });
    
    document.getElementById('view-reset').addEventListener('click', function() {
        camera.position.set(5, 3, 10);
        controls.target.set(0, 1.5, 0);
        controls.autoRotate = autoRotate;
        controls.update();
    });
    
    document.getElementById('view-enhance').addEventListener('click', function() {
        renderer.toneMappingExposure = renderer.toneMappingExposure === 1.2 ? 1.8 : 1.2;
        alert('Lighting enhanced!');
    });
    
    // Dimension controls
    const widthInput = document.getElementById('width');
    const depthInput = document.getElementById('depth');
    const heightInput = document.getElementById('height');
    
    widthInput.addEventListener('input', function() {
        document.getElementById('width-value').textContent = this.value + 'cm';
        updateDimensions();
        calculatePrice();
    });
    
    depthInput.addEventListener('input', function() {
        document.getElementById('depth-value').textContent = this.value + 'cm';
        updateDimensions();
        calculatePrice();
    });
    
    heightInput.addEventListener('input', function() {
        document.getElementById('height-value').textContent = this.value + 'cm';
        updateDimensions();
        calculatePrice();
    });
    
    // AI Recommendation
    document.getElementById('getRecommendation').addEventListener('click', getAIRecommendation);
    
    // Save customization
    document.getElementById('save-customization').addEventListener('click', function(e) {
        e.preventDefault();
        saveDesign('checkout');
    });
    
    // Save draft
    document.getElementById('save-draft').addEventListener('click', function() {
        saveDesign('draft');
    });
    
    // Door details
    document.getElementById('door-panels').addEventListener('change', function() {
        currentDoorPanels = this.value;
        redrawFurniture();
    });
    
    document.getElementById('door-handle').addEventListener('change', function() {
        currentDoorHandle = this.value;
        redrawFurniture();
    });
    
    // Fabric type
    document.getElementById('fabric-type').addEventListener('change', function() {
        currentFabricType = this.value;
        document.getElementById('fabric-type-input').value = currentFabricType;
        redrawFurniture();
    });
    
    // Cushion fill
    document.getElementById('cushion-fill').addEventListener('change', function() {
        currentCushionFill = this.value;
        document.getElementById('cushion-fill-input').value = currentCushionFill;
    });
    
    // Leg style
    document.getElementById('leg-style').addEventListener('change', function() {
        currentLegStyle = this.value;
        document.getElementById('leg-style-input').value = currentLegStyle;
        redrawFurniture();
    });
    
    // Backrest style
    document.getElementById('backrest-style').addEventListener('change', function() {
        currentBackrestStyle = this.value;
        document.getElementById('backrest-style-input').value = currentBackrestStyle;
        redrawFurniture();
    });
}

// Save design function
async function saveDesign(type) {
    const saveBtn = type === 'checkout' ? document.getElementById('save-customization') : document.getElementById('save-draft');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    try {
        await captureDesignImage(true);
        const width = document.getElementById('width').value;
        const depth = document.getElementById('depth').value;
        const height = document.getElementById('height').value;
        document.getElementById('dimensions-hidden').value = `${width}x${depth}x${height}`;
        document.getElementById('customization-form').submit();
    } catch (error) {
        console.error('Save failed:', error);
        alert('Failed to save design. Please try again.');
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// Capture design image
async function captureDesignImage(autoSave = false) {
    if (!renderer) {
        console.error('Renderer not initialized');
        if (!autoSave) showCaptureNotification('Error: Renderer not initialized');
        return false;
    }
    
    try {
        renderer.render(scene, camera);
        const glCanvas = renderer.domElement;
        const canvas = document.createElement('canvas');
        canvas.width = glCanvas.width;
        canvas.height = glCanvas.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(glCanvas, 0, 0);
        const imageData = canvas.toDataURL('image/png');
        document.getElementById('design-image-data').value = imageData;
        
        if (!autoSave) {
            showCaptureNotification('Design captured successfully!');
        }
        return true;
    } catch (error) {
        console.error('Capture failed:', error);
        return false;
    }
}

// Manual capture with preview
function manualCaptureWithPreview() {
    captureDesignImage(false).then(success => {
        if (success) {
            const imageData = document.getElementById('design-image-data').value;
            
            const modal = document.createElement('div');
            modal.className = 'capture-modal';
            
            const content = document.createElement('div');
            content.className = 'capture-modal-content';
            
            const img = document.createElement('img');
            img.src = imageData;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '400px';
            img.style.objectFit = 'contain';
            
            const title = document.createElement('h3');
            title.style.color = '#f0e6d2';
            title.style.marginBottom = '1rem';
            title.innerHTML = '<i class="fas fa-camera"></i> Design Captured';
            
            const buttonContainer = document.createElement('div');
            buttonContainer.style.display = 'flex';
            buttonContainer.style.gap = '1rem';
            buttonContainer.style.marginTop = '1.5rem';
            
            const closeBtn = document.createElement('button');
            closeBtn.className = 'capture-close-btn';
            closeBtn.innerHTML = 'Close';
            closeBtn.onclick = () => modal.remove();
            
            const saveBtn = document.createElement('button');
            saveBtn.className = 'capture-save-btn';
            saveBtn.innerHTML = 'Save & Continue';
            saveBtn.onclick = () => {
                modal.remove();
                saveDesign('checkout');
            };
            
            buttonContainer.appendChild(closeBtn);
            buttonContainer.appendChild(saveBtn);
            
            content.appendChild(title);
            content.appendChild(img);
            content.appendChild(buttonContainer);
            modal.appendChild(content);
            document.body.appendChild(modal);
        }
    });
}

// Show capture notification
function showCaptureNotification(message) {
    const existing = document.querySelector('.capture-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'capture-notification';
    notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Update door styles section visibility
function updateDoorStylesVisibility() {
    const doorStylesSection = document.getElementById('door-styles-section');
    if (doorStylesSection) {
        if (currentFurnitureType === 'cabinet' || currentFurnitureType === 'door') {
            doorStylesSection.style.display = 'block';
        } else {
            doorStylesSection.style.display = 'none';
        }
    }
}

function adjustColor(hex, percent) {
    let R = parseInt(hex.substring(1,3), 16);
    let G = parseInt(hex.substring(3,5), 16);
    let B = parseInt(hex.substring(5,7), 16);
    
    R = Math.min(255, Math.max(0, R + percent));
    G = Math.min(255, Math.max(0, G + percent));
    B = Math.min(255, Math.max(0, B + percent));
    
    return '#' + ((1 << 24) + (R << 16) + (G << 8) + B).toString(16).slice(1);
}

// Get material texture
function getMaterialTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 1024;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = currentColor;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Add subtle noise for realism
    for (let i = 0; i < 2000; i++) {
        ctx.fillStyle = `rgba(0,0,0,${Math.random() * 0.02})`;
        ctx.fillRect(Math.random() * canvas.width, Math.random() * canvas.height, 2, 2);
    }
    
    // Apply material-specific textures
    if (currentMaterial === 'wood' || currentMaterial === 'oak' || currentMaterial === 'walnut' || currentMaterial === 'mahogany') {
        for (let i = 0; i < 100; i++) {
            const x = Math.random() * canvas.width;
            const y = Math.random() * canvas.height;
            const length = 100 + Math.random() * 200;
            const angle = Math.random() * Math.PI;
            
            ctx.beginPath();
            ctx.strokeStyle = `rgba(0,0,0,${0.1 + Math.random() * 0.1})`;
            ctx.lineWidth = 1 + Math.random() * 2;
            
            for (let j = 0; j < length; j += 10) {
                const xOffset = Math.sin(j * 0.05 + i) * 5;
                const yOffset = Math.cos(j * 0.03 + i) * 3;
                
                if (j === 0) {
                    ctx.moveTo(x + xOffset, y + yOffset);
                } else {
                    ctx.lineTo(x + j * Math.cos(angle) + xOffset, 
                              y + j * Math.sin(angle) + yOffset);
                }
            }
            ctx.stroke();
        }
    } else if (currentMaterial === 'metal' || currentMaterial === 'brass' || currentMaterial === 'copper') {
        for (let i = 0; i < canvas.height; i += 10) {
            ctx.beginPath();
            ctx.moveTo(0, i);
            ctx.lineTo(canvas.width, i);
            ctx.strokeStyle = `rgba(255,255,255,${0.02 + Math.random() * 0.03})`;
            ctx.lineWidth = 1 + Math.random();
            ctx.stroke();
        }
    }
    
    // Apply pattern if selected
    if (currentPattern && currentPattern !== 'none') {
        ctx.fillStyle = currentAccentColor;
        ctx.strokeStyle = currentAccentColor;
        ctx.lineWidth = 8;
        ctx.globalAlpha = 0.3;
        
        switch(currentPattern) {
            case 'stripes':
                for (let i = 0; i < canvas.width; i += 100) {
                    ctx.fillRect(i, 0, 20, canvas.height);
                }
                for (let j = 0; j < canvas.height; j += 100) {
                    ctx.fillRect(0, j, canvas.width, 20);
                }
                break;
            case 'checker':
                const size = 80;
                for (let i = 0; i < canvas.width; i += size) {
                    for (let j = 0; j < canvas.height; j += size) {
                        if ((i + j) % (size * 2) === 0) {
                            ctx.fillRect(i, j, size, size);
                        }
                    }
                }
                break;
            case 'dots':
                for (let i = 0; i < canvas.width; i += 50) {
                    for (let j = 0; j < canvas.height; j += 50) {
                        ctx.beginPath();
                        ctx.arc(i + 25, j + 25, 10, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
                break;
        }
        ctx.globalAlpha = 1.0;
    }
    
    // Apply finish effects
    if (currentFinish === 'glossy') {
        const gradient = ctx.createLinearGradient(0, 0, canvas.width, 0);
        gradient.addColorStop(0, 'rgba(255,255,255,0.05)');
        gradient.addColorStop(0.5, 'rgba(255,255,255,0.1)');
        gradient.addColorStop(1, 'rgba(255,255,255,0.05)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    
    return new THREE.CanvasTexture(canvas);
}

// Initialize 3D scene
function init3D() {
    const container = document.getElementById('canvas-container');
    
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a2a);
    scene.fog = new THREE.Fog(0x1a1a2a, 10, 30);
    
    camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.set(5, 3, 10);
    camera.lookAt(0, 1.5, 0);
    
    renderer = new THREE.WebGLRenderer({ 
        antialias: true, 
        powerPreference: "high-performance",
        preserveDrawingBuffer: true
    });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    container.innerHTML = '';
    container.appendChild(renderer.domElement);
    
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.autoRotate = autoRotate;
    controls.autoRotateSpeed = 1.0;
    controls.enableZoom = true;
    controls.maxPolarAngle = Math.PI / 2;
    controls.target.set(0, 1.5, 0);
    
    // Lighting
    const ambientLight = new THREE.AmbientLight(0x404060);
    scene.add(ambientLight);
    
    const mainLight = new THREE.DirectionalLight(0xfff5e6, 1.2);
    mainLight.position.set(5, 10, 7);
    mainLight.castShadow = true;
    mainLight.shadow.mapSize.width = 2048;
    mainLight.shadow.mapSize.height = 2048;
    mainLight.shadow.camera.near = 0.5;
    mainLight.shadow.camera.far = 50;
    mainLight.shadow.camera.left = -10;
    mainLight.shadow.camera.right = 10;
    mainLight.shadow.camera.top = 10;
    mainLight.shadow.camera.bottom = -10;
    scene.add(mainLight);
    
    const fillLight = new THREE.DirectionalLight(0xccddff, 0.5);
    fillLight.position.set(-5, 5, 5);
    scene.add(fillLight);
    
    const backLight = new THREE.DirectionalLight(0xffeedd, 0.3);
    backLight.position.set(0, 3, -10);
    scene.add(backLight);
    
    // Ground
    const groundGeometry = new THREE.CircleGeometry(15, 32);
    const groundMaterial = new THREE.MeshStandardMaterial({ 
        color: 0x222233,
        roughness: 0.8,
        metalness: 0.1
    });
    const ground = new THREE.Mesh(groundGeometry, groundMaterial);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = 0;
    ground.receiveShadow = true;
    scene.add(ground);
    
    const gridHelper = new THREE.GridHelper(15, 30, 0xcfb087, 0x444455);
    gridHelper.position.y = 0.01;
    scene.add(gridHelper);
    
    furnitureGroup = new THREE.Group();
    scene.add(furnitureGroup);
    
    redrawFurniture();
    updateDoorStylesVisibility();
    
    animate();
}

// Animation loop
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}

// Handle resize
window.addEventListener('resize', function() {
    const container = document.getElementById('canvas-container');
    if (container && camera && renderer) {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    }
});

// Create cabinet
function createCabinet() {
    const group = new THREE.Group();
    
    const texture = getMaterialTexture();
    
    const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.7,
        metalness: 0.1
    });
    
    const accentMaterial = new THREE.MeshStandardMaterial({ 
        color: currentAccentColor,
        roughness: 0.6
    });
    
    const metalMaterial = new THREE.MeshStandardMaterial({
        color: 0xcccccc,
        roughness: 0.3,
        metalness: 0.7
    });
    
    // Main body
    const body = new THREE.Mesh(
        new THREE.BoxGeometry(2.4, 3.0, 1.6),
        material
    );
    body.position.y = 1.5;
    body.castShadow = true;
    body.receiveShadow = true;
    group.add(body);
    
    // Doors
    const doorLeft = new THREE.Mesh(
        new THREE.BoxGeometry(1.0, 2.4, 0.1),
        material
    );
    doorLeft.position.set(-0.6, 1.5, 0.75);
    doorLeft.castShadow = true;
    group.add(doorLeft);
    
    const doorRight = new THREE.Mesh(
        new THREE.BoxGeometry(1.0, 2.4, 0.1),
        material
    );
    doorRight.position.set(0.6, 1.5, 0.75);
    doorRight.castShadow = true;
    group.add(doorRight);
    
    // Handles
    const handleLeft = new THREE.Mesh(
        new THREE.CylinderGeometry(0.1, 0.1, 0.3, 8),
        metalMaterial
    );
    handleLeft.rotation.z = Math.PI / 2;
    handleLeft.position.set(-0.6, 1.5, 0.85);
    handleLeft.castShadow = true;
    group.add(handleLeft);
    
    const handleRight = new THREE.Mesh(
        new THREE.CylinderGeometry(0.1, 0.1, 0.3, 8),
        metalMaterial
    );
    handleRight.rotation.z = Math.PI / 2;
    handleRight.position.set(0.6, 1.5, 0.85);
    handleRight.castShadow = true;
    group.add(handleRight);
    
    return group;
}

// Create door
function createDoor() {
    const group = new THREE.Group();
    
    const texture = getMaterialTexture();
    
    const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.7,
        metalness: 0.1
    });
    
    const metalMaterial = new THREE.MeshStandardMaterial({
        color: 0xcccccc,
        roughness: 0.3,
        metalness: 0.7
    });
    
    // Door slab
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(2.0, 4.0, 0.15),
        material
    );
    slab.position.y = 2.0;
    slab.castShadow = true;
    slab.receiveShadow = true;
    group.add(slab);
    
    // Handle
    const handle = new THREE.Mesh(
        new THREE.SphereGeometry(0.1, 16),
        metalMaterial
    );
    handle.position.set(0.5, 2.0, 0.12);
    handle.castShadow = true;
    group.add(handle);
    
    return group;
}

// Create chair
function createChair() {
    const group = new THREE.Group();
    
    const texture = getMaterialTexture();
    
    const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.7,
        metalness: 0.1
    });
    
    const legMaterial = new THREE.MeshStandardMaterial({
        color: adjustColor(currentColor, -30),
        roughness: 0.6
    });
    
    // Seat
    const seat = new THREE.Mesh(
        new THREE.BoxGeometry(1.6, 0.15, 1.6),
        material
    );
    seat.position.y = 0.6;
    seat.castShadow = true;
    seat.receiveShadow = true;
    group.add(seat);
    
    // Backrest
    const backrest = new THREE.Mesh(
        new THREE.BoxGeometry(1.6, 1.2, 0.15),
        material
    );
    backrest.position.set(0, 1.2, -0.7);
    backrest.rotation.x = 0.1;
    backrest.castShadow = true;
    group.add(backrest);
    
    // Legs
    const legPositions = [
        [-0.7, 0.3, -0.7],
        [0.7, 0.3, -0.7],
        [-0.7, 0.3, 0.7],
        [0.7, 0.3, 0.7]
    ];
    
    legPositions.forEach(pos => {
        const leg = new THREE.Mesh(
            new THREE.CylinderGeometry(0.1, 0.12, 0.6, 8),
            legMaterial
        );
        leg.position.set(pos[0], pos[1], pos[2]);
        leg.castShadow = true;
        group.add(leg);
    });
    
    return group;
}

// Create bench
function createBench() {
    const group = new THREE.Group();
    
    const texture = getMaterialTexture();
    
    const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.7,
        metalness: 0.1
    });
    
    const legMaterial = new THREE.MeshStandardMaterial({
        color: adjustColor(currentColor, -30),
        roughness: 0.6
    });
    
    // Seat
    const seat = new THREE.Mesh(
        new THREE.BoxGeometry(2.8, 0.2, 1.2),
        material
    );
    seat.position.y = 0.6;
    seat.castShadow = true;
    seat.receiveShadow = true;
    group.add(seat);
    
    // Backrest
    const backrest = new THREE.Mesh(
        new THREE.BoxGeometry(2.8, 0.8, 0.15),
        material
    );
    backrest.position.set(0, 1.1, -0.55);
    backrest.castShadow = true;
    group.add(backrest);
    
    // Legs
    const legPositions = [
        [-1.2, 0.3, -0.4],
        [1.2, 0.3, -0.4],
        [-1.2, 0.3, 0.4],
        [1.2, 0.3, 0.4]
    ];
    
    legPositions.forEach(pos => {
        const leg = new THREE.Mesh(
            new THREE.CylinderGeometry(0.15, 0.18, 0.6, 8),
            legMaterial
        );
        leg.position.set(pos[0], pos[1], pos[2]);
        leg.castShadow = true;
        group.add(leg);
    });
    
    return group;
}

// Create table
function createTable() {
    const group = new THREE.Group();
    
    const texture = getMaterialTexture();
    
    const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.7,
        metalness: 0.1
    });
    
    const legMaterial = new THREE.MeshStandardMaterial({
        color: adjustColor(currentColor, -20),
        roughness: 0.6
    });
    
    // Top
    const top = new THREE.Mesh(
        new THREE.BoxGeometry(2.8, 0.1, 1.8),
        material
    );
    top.position.y = 1.5;
    top.castShadow = true;
    top.receiveShadow = true;
    group.add(top);
    
    // Legs
    const legPositions = [
        [-1.2, 0.8, -0.8],
        [1.2, 0.8, -0.8],
        [-1.2, 0.8, 0.8],
        [1.2, 0.8, 0.8]
    ];
    
    legPositions.forEach(pos => {
        const leg = new THREE.Mesh(
            new THREE.CylinderGeometry(0.15, 0.2, 1.4, 8),
            legMaterial
        );
        leg.position.set(pos[0], pos[1], pos[2]);
        leg.castShadow = true;
        leg.receiveShadow = true;
        group.add(leg);
    });
    
    return group;
}

// Redraw furniture
function redrawFurniture() {
    if (!furnitureGroup) return;
    
    while(furnitureGroup.children.length > 0) {
        furnitureGroup.remove(furnitureGroup.children[0]);
    }
    
    let furniture;
    
    switch(currentFurnitureType) {
        case 'cabinet':
            furniture = createCabinet();
            break;
        case 'door':
            furniture = createDoor();
            break;
        case 'chair':
            furniture = createChair();
            break;
        case 'bench':
            furniture = createBench();
            break;
        case 'table':
            furniture = createTable();
            break;
        default:
            furniture = createCabinet();
    }
    
    furnitureGroup.add(furniture);
    updateDimensions();
    updateDoorStylesVisibility();
}

// Update dimensions
function updateDimensions() {
    const width = parseFloat(document.getElementById('width').value) / 100;
    const depth = parseFloat(document.getElementById('depth').value) / 100;
    const height = parseFloat(document.getElementById('height').value) / 100;
    
    furnitureGroup.scale.set(width, depth, height);
}

// Calculate price
function calculatePrice() {
    const width = parseFloat(document.getElementById('width').value);
    const depth = parseFloat(document.getElementById('depth').value);
    const height = parseFloat(document.getElementById('height').value);
    
    const totalCm = width + depth + height;
    const sizeAddition = Math.ceil(totalCm / 10) * 5;
    const basePrice = basePrices[currentFurnitureType] || 900;
    const total = basePrice + sizeAddition;
    
    document.getElementById('size-addition').textContent = '+₱' + sizeAddition.toFixed(2);
    document.getElementById('total-price').textContent = '₱' + total.toFixed(2);
    document.getElementById('total-price-input').value = total.toFixed(2);
}

// AI Recommendation
async function getAIRecommendation() {
    const recommendBtn = document.getElementById('getRecommendation');
    const loadingEl = document.getElementById('recommendation-loading');
    const resultsEl = document.getElementById('recommendation-results');
    
    recommendBtn.disabled = true;
    loadingEl.style.display = 'block';
    resultsEl.style.display = 'none';
    
    const preferences = {
        style: document.getElementById('pref-style').value,
        room_type: document.getElementById('pref-room').value,
        color_preference: document.getElementById('pref-color').value,
        material_preference: document.getElementById('pref-material').value
    };
    
    try {
        const response = await fetch('customize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_ai_recommendation=1&preferences=' + encodeURIComponent(JSON.stringify(preferences))
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayRecommendations(result.recommendations);
        }
    } catch (error) {
        console.error('AI Recommendation error:', error);
        alert('Failed to get recommendations. Please try again.');
    } finally {
        recommendBtn.disabled = false;
        loadingEl.style.display = 'none';
        resultsEl.style.display = 'block';
    }
}

// Display recommendations
function displayRecommendations(recs) {
    // Furniture type suggestions with preview images
    const furnitureEl = document.getElementById('furniture-suggestions');
    furnitureEl.innerHTML = '';
    
    if (recs.suggested_furniture) {
        recs.suggested_furniture.forEach(item => {
            const card = document.createElement('div');
            card.className = 'furniture-preview-card';
            
            const img = document.createElement('img');
            img.src = item.preview_image;
            img.alt = item.name;
            
            const title = document.createElement('h4');
            title.textContent = item.name;
            
            const price = document.createElement('p');
            price.textContent = '₱' + item.base_price;
            
            card.appendChild(img);
            card.appendChild(title);
            card.appendChild(price);
            
            card.onclick = function() {
                currentFurnitureType = item.type;
                document.querySelectorAll('.type-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.type === item.type);
                });
                document.getElementById('furniture-type-input').value = item.type;
                document.getElementById('base-price').textContent = '₱' + item.base_price.toFixed(2);
                redrawFurniture();
                calculatePrice();
            };
            
            furnitureEl.appendChild(card);
        });
    }
    
    // Color palette
    const paletteEl = document.getElementById('color-palette');
    paletteEl.innerHTML = '';
    recs.main_palette.forEach(color => {
        const chip = document.createElement('div');
        chip.className = 'color-chip';
        chip.style.background = color;
        chip.onclick = function() {
            currentColor = color;
            document.getElementById('custom-color').value = color;
            document.getElementById('main-color-input').value = color;
            redrawFurniture();
        };
        paletteEl.appendChild(chip);
    });
    
    // Material suggestions
    const materialEl = document.getElementById('material-suggestions');
    materialEl.innerHTML = '';
    recs.suggested_materials.forEach(material => {
        const chip = document.createElement('div');
        chip.className = 'material-chip';
        chip.textContent = material.charAt(0).toUpperCase() + material.slice(1);
        chip.onclick = function() {
            currentMaterial = material;
            document.querySelectorAll('.material-card').forEach(c => {
                c.classList.toggle('active', c.dataset.material === material);
            });
            document.getElementById('material-input').value = material;
            redrawFurniture();
        };
        materialEl.appendChild(chip);
    });
    
    // Pattern suggestions
    const patternEl = document.getElementById('pattern-suggestions');
    patternEl.innerHTML = '';
    recs.suggested_patterns.forEach(pattern => {
        const chip = document.createElement('div');
        chip.className = 'pattern-chip';
        chip.textContent = pattern.charAt(0).toUpperCase() + pattern.slice(1);
        chip.onclick = function() {
            currentPattern = pattern;
            document.querySelectorAll('.pattern-option').forEach(p => {
                p.classList.toggle('active', p.dataset.pattern === pattern);
            });
            document.getElementById('pattern-input').value = pattern;
            redrawFurniture();
        };
        patternEl.appendChild(chip);
    });
    
    // Confidence
    document.getElementById('confidence-fill').style.width = recs.style_confidence + '%';
    document.getElementById('confidence-value').textContent = recs.style_confidence + '%';
    
    // Design tips
    const tipsEl = document.getElementById('design-tips');
    tipsEl.innerHTML = '';
    recs.design_tips.forEach(tip => {
        const li = document.createElement('li');
        li.innerHTML = `<i class="fas fa-lightbulb"></i> ${tip}`;
        tipsEl.appendChild(li);
    });
    
    // Apply recommended finish
    if (recs.recommended_finish) {
        currentFinish = recs.recommended_finish;
        document.querySelectorAll('.finish-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.finish === currentFinish);
        });
        document.getElementById('finish-input').value = currentFinish;
    }
    
    redrawFurniture();
}

// Load saved customization
function loadCustomization(data) {
    currentFurnitureType = data.furniture_type || 'cabinet';
    currentColor = data.color || '#8B4513';
    currentAccentColor = data.accent_color || '#d4a373';
    currentMaterial = data.material || 'wood';
    currentFinish = data.finish || 'matte';
    currentPattern = data.pattern || 'none';
    currentFabricType = data.fabric_type || 'standard';
    currentCushionFill = data.cushion_fill || 'foam';
    currentLegStyle = data.leg_style || 'standard';
    currentBackrestStyle = data.backrest_style || 'standard';
    currentDoorStyle = data.door_style || 'standard';
    
    if (data.dimensions) {
        const dims = data.dimensions.split('x');
        if (dims.length === 3) {
            document.getElementById('width').value = dims[0];
            document.getElementById('depth').value = dims[1];
            document.getElementById('height').value = dims[2];
            document.getElementById('width-value').textContent = dims[0] + 'cm';
            document.getElementById('depth-value').textContent = dims[1] + 'cm';
            document.getElementById('height-value').textContent = dims[2] + 'cm';
            document.getElementById('dimensions-hidden').value = data.dimensions;
        }
    }
    
    // Update UI to reflect loaded values
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.type === currentFurnitureType);
    });
    document.getElementById('furniture-type-input').value = currentFurnitureType;
    document.getElementById('base-price').textContent = '₱' + (basePrices[currentFurnitureType] || 900).toFixed(2);
    
    document.querySelectorAll('.material-card').forEach(card => {
        card.classList.toggle('active', card.dataset.material === currentMaterial);
    });
    document.getElementById('material-input').value = currentMaterial;
    
    document.querySelectorAll('.finish-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.finish === currentFinish);
    });
    document.getElementById('finish-input').value = currentFinish;
    
    document.getElementById('custom-color').value = currentColor;
    document.getElementById('main-color-input').value = currentColor;
    document.getElementById('accent-color').value = currentAccentColor;
    document.getElementById('accent-color-input').value = currentAccentColor;
    
    document.querySelectorAll('.pattern-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.pattern === currentPattern);
    });
    document.getElementById('pattern-input').value = currentPattern;
    
    document.querySelectorAll('.door-style-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.door === currentDoorStyle);
    });
    document.getElementById('door-style-input').value = currentDoorStyle;
    
    redrawFurniture();
    calculatePrice();
}

// Make functions globally accessible
window.manualCaptureWithPreview = manualCaptureWithPreview;
window.captureDesignImage = captureDesignImage;
window.loadCustomization = loadCustomization;
</script>
</body>
</html>