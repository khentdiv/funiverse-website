<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Handle General Settings Update
if (isset($_POST['update_general'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $site_email = $conn->real_escape_string($_POST['site_email']);
    $site_phone = $conn->real_escape_string($_POST['site_phone']);
    $site_address = $conn->real_escape_string($_POST['site_address']);
    $currency = $conn->real_escape_string($_POST['currency']);
    $tax_rate = floatval($_POST['tax_rate']);
    
    // Update settings in database (assuming a settings table)
    $conn->query("UPDATE settings SET setting_value = '$site_name' WHERE setting_key = 'site_name'");
    $conn->query("UPDATE settings SET setting_value = '$site_email' WHERE setting_key = 'site_email'");
    $conn->query("UPDATE settings SET setting_value = '$site_phone' WHERE setting_key = 'site_phone'");
    $conn->query("UPDATE settings SET setting_value = '$site_address' WHERE setting_key = 'site_address'");
    $conn->query("UPDATE settings SET setting_value = '$currency' WHERE setting_key = 'currency'");
    $conn->query("UPDATE settings SET setting_value = '$tax_rate' WHERE setting_key = 'tax_rate'");
    
    $_SESSION['success'] = "General settings updated successfully";
    redirect('settings.php');
}

// Handle Shipping Settings
if (isset($_POST['update_shipping'])) {
    $free_shipping_threshold = floatval($_POST['free_shipping_threshold']);
    $standard_shipping_fee = floatval($_POST['standard_shipping_fee']);
    $express_shipping_fee = floatval($_POST['express_shipping_fee']);
    $shipping_zones = $conn->real_escape_string($_POST['shipping_zones']);
    
    $conn->query("UPDATE settings SET setting_value = '$free_shipping_threshold' WHERE setting_key = 'free_shipping_threshold'");
    $conn->query("UPDATE settings SET setting_value = '$standard_shipping_fee' WHERE setting_key = 'standard_shipping_fee'");
    $conn->query("UPDATE settings SET setting_value = '$express_shipping_fee' WHERE setting_key = 'express_shipping_fee'");
    $conn->query("UPDATE settings SET setting_value = '$shipping_zones' WHERE setting_key = 'shipping_zones'");
    
    $_SESSION['success'] = "Shipping settings updated successfully";
    redirect('settings.php');
}

// Handle Payment Settings
if (isset($_POST['update_payment'])) {
    $payment_methods = isset($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : '';
    $paypal_email = $conn->real_escape_string($_POST['paypal_email']);
    $stripe_key = $conn->real_escape_string($_POST['stripe_key']);
    $bank_details = $conn->real_escape_string($_POST['bank_details']);
    
    $conn->query("UPDATE settings SET setting_value = '$payment_methods' WHERE setting_key = 'payment_methods'");
    $conn->query("UPDATE settings SET setting_value = '$paypal_email' WHERE setting_key = 'paypal_email'");
    $conn->query("UPDATE settings SET setting_value = '$stripe_key' WHERE setting_key = 'stripe_key'");
    $conn->query("UPDATE settings SET setting_value = '$bank_details' WHERE setting_key = 'bank_details'");
    
    $_SESSION['success'] = "Payment settings updated successfully";
    redirect('settings.php');
}

// Handle Email Settings
if (isset($_POST['update_email'])) {
    $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_username = $conn->real_escape_string($_POST['smtp_username']);
    $smtp_password = $conn->real_escape_string($_POST['smtp_password']);
    $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption']);
    
    $conn->query("UPDATE settings SET setting_value = '$smtp_host' WHERE setting_key = 'smtp_host'");
    $conn->query("UPDATE settings SET setting_value = '$smtp_port' WHERE setting_key = 'smtp_port'");
    $conn->query("UPDATE settings SET setting_value = '$smtp_username' WHERE setting_key = 'smtp_username'");
    $conn->query("UPDATE settings SET setting_value = '$smtp_password' WHERE setting_key = 'smtp_password'");
    $conn->query("UPDATE settings SET setting_value = '$smtp_encryption' WHERE setting_key = 'smtp_encryption'");
    
    $_SESSION['success'] = "Email settings updated successfully";
    redirect('settings.php');
}

// Handle Backup
if (isset($_POST['create_backup'])) {
    // Create database backup
    $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = '../backups/' . $backup_file;
    
    // Ensure backup directory exists
    if (!file_exists('../backups')) {
        mkdir('../backups', 0777, true);
    }
    
    // Get all tables
    $tables = $conn->query("SHOW TABLES");
    $sql = "-- Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    while ($table = $tables->fetch_array()) {
        $table_name = $table[0];
        
        // Drop table if exists
        $sql .= "DROP TABLE IF EXISTS `$table_name`;\n";
        
        // Create table
        $create_table = $conn->query("SHOW CREATE TABLE $table_name")->fetch_assoc();
        $sql .= $create_table['Create Table'] . ";\n\n";
        
        // Get table data
        $rows = $conn->query("SELECT * FROM $table_name");
        while ($row = $rows->fetch_assoc()) {
            $columns = implode("`, `", array_keys($row));
            $values = implode("', '", array_map([$conn, 'real_escape_string'], array_values($row)));
            $sql .= "INSERT INTO `$table_name` (`$columns`) VALUES ('$values');\n";
        }
        $sql .= "\n\n";
    }
    
    // Save backup file
    file_put_contents($backup_path, $sql);
    
    $_SESSION['success'] = "Backup created successfully: $backup_file";
    redirect('settings.php');
}

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set default values if not set
$default_settings = [
    'site_name' => 'Furniverse',
    'site_email' => 'admin@furniverse.com',
    'site_phone' => '+63 XXX XXX XXXX',
    'site_address' => '123 Main Street, City',
    'currency' => 'PHP',
    'tax_rate' => '12',
    'free_shipping_threshold' => '5000',
    'standard_shipping_fee' => '150',
    'express_shipping_fee' => '300',
    'payment_methods' => 'cod,paypal,bank',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get backup files
$backups = [];
if (file_exists('../backups')) {
    $backups = array_diff(scandir('../backups'), ['.', '..']);
    rsort($backups);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Furniverse Admin</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
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
            line-height: 1.5;
        }

        h1, h2, h3, h4, .logo h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: rgba(18, 14, 29, 0.95);
            backdrop-filter: blur(16px);
            border-right: 1px solid rgba(207, 176, 135, 0.25);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(207, 176, 135, 0.2);
        }

        .sidebar-header h2 {
            font-size: 2rem;
            background: linear-gradient(135deg, #ece3f0, #cfb087);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            list-style: none;
            padding: 2rem 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1.5rem;
            color: #d6cee8;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a i {
            width: 20px;
            color: #cfb087;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(207, 176, 135, 0.1);
            border-left-color: #cfb087;
            color: #f0e6d2;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            background: #0f0b17;
        }

        .admin-header {
            background: rgba(18, 14, 29, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(207, 176, 135, 0.25);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-header h1 {
            font-size: 1.8rem;
            color: #f0e6d2;
        }

        .admin-user {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            border: 1px solid #3d3452;
            color: #cfb087;
        }

        .admin-content {
            padding: 2rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 500;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .alert-error {
            background: #2d1a24;
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        .settings-container {
            width: 100%;
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid #332d44;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .settings-tab {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            color: #b2a6ca;
            border-radius: 40px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid transparent;
        }

        .settings-tab:hover {
            background: #161224;
            color: #f0e6d2;
        }

        .settings-tab.active {
            background: #cfb087;
            color: #0f0b17;
        }

        .settings-tab i {
            font-size: 1rem;
        }

        .settings-content {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .settings-section h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .settings-section h2 i {
            color: #cfb087;
        }

        .settings-section h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #cfb087;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: 0.3s;
        }

        .form-group textarea {
            border-radius: 24px;
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #cfb087;
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #b2a6ca;
            font-size: 0.8rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            background: #161224;
            padding: 1.5rem;
            border-radius: 32px;
            border: 1px solid #332d44;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #cfb087;
        }

        .checkbox-item label {
            margin: 0;
            color: #d6cee8;
        }

        .btn-primary {
            padding: 1rem 2rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .btn-secondary {
            padding: 0.6rem 1.2rem;
            background: transparent;
            border: 1px solid #3d3452;
            border-radius: 30px;
            color: #b2a6ca;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            border-color: #cfb087;
            color: #f0e6d2;
        }

        /* Info Box */
        .info-box {
            background: #161224;
            border-left: 4px solid #cfb087;
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 2rem;
        }

        .info-box h4 {
            color: #cfb087;
            margin-bottom: 0.5rem;
        }

        .info-box p {
            color: #b2a6ca;
            line-height: 1.6;
        }

        /* Backup List */
        .backup-list {
            margin-top: 1.5rem;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem;
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 42px;
            margin-bottom: 1rem;
            transition: 0.3s;
        }

        .backup-item:hover {
            border-color: #cfb087;
        }

        .backup-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .backup-info i {
            font-size: 1.5rem;
            color: #cfb087;
        }

        .backup-details {
            line-height: 1.5;
        }

        .backup-name {
            font-weight: 600;
            color: #f0e6d2;
        }

        .backup-size {
            color: #b2a6ca;
            font-size: 0.8rem;
        }

        .backup-actions {
            display: flex;
            gap: 0.5rem;
        }

        .backup-actions a {
            width: 36px;
            height: 36px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-download {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .btn-download:hover {
            background: #4a9b6e;
            color: #0f0b17;
        }

        .btn-restore {
            background: #2d2a1a;
            color: #ffd966;
            border: 1px solid #b88a4a;
        }

        .btn-restore:hover {
            background: #b88a4a;
            color: #0f0b17;
        }

        .btn-delete {
            background: #2d1a24;
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }

        .btn-delete:hover {
            background: #b84a6e;
            color: #0f0b17;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: #161224;
            border-radius: 32px;
            overflow: hidden;
            border: 1px solid #332d44;
        }

        .data-table th,
        .data-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #332d44;
        }

        .data-table th {
            background: #1e192c;
            color: #cfb087;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table td {
            color: #d6cee8;
        }

        .data-table tr:hover td {
            background: #1e192c;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #b2a6ca;
        }

        .empty-state i {
            font-size: 3rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <h2>Furniverse</h2>
            </div>
            <ul class="sidebar-menu">
                     <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-chair"></i> Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="customizations.php"><i class="fas fa-paint-brush"></i> Customizations</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-cog" style="margin-right: 10px; color: #cfb087;"></i>System Settings</h1>
                <div class="admin-user">
                    <i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <div class="settings-container">
                    <!-- Settings Tabs -->
                    <div class="settings-tabs">
                        <div class="settings-tab active" onclick="showSettingsTab('general')">
                            <i class="fas fa-globe"></i> General
                        </div>
                        <div class="settings-tab" onclick="showSettingsTab('shipping')">
                            <i class="fas fa-truck"></i> Shipping
                        </div>
                        <div class="settings-tab" onclick="showSettingsTab('payment')">
                            <i class="fas fa-credit-card"></i> Payment
                        </div>
                        <div class="settings-tab" onclick="showSettingsTab('email')">
                            <i class="fas fa-envelope"></i> Email
                        </div>
                        <div class="settings-tab" onclick="showSettingsTab('backup')">
                            <i class="fas fa-database"></i> Backup
                        </div>
                        <div class="settings-tab" onclick="showSettingsTab('system')">
                            <i class="fas fa-info-circle"></i> System
                        </div>
                    </div>

                    <!-- Settings Content -->
                    <div class="settings-content">
                        <!-- General Settings -->
                        <div id="general-section" class="settings-section active">
                            <h2><i class="fas fa-globe"></i> General Settings</h2>
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="site_email">Site Email</label>
                                        <input type="email" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="site_phone">Site Phone</label>
                                        <input type="text" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="site_address">Site Address</label>
                                    <textarea id="site_address" name="site_address" required><?php echo htmlspecialchars($settings['site_address']); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select id="currency" name="currency">
                                            <option value="PHP" <?php echo $settings['currency'] == 'PHP' ? 'selected' : ''; ?>>PHP (₱)</option>
                                            <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                            <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="tax_rate">Tax Rate (%)</label>
                                        <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" value="<?php echo $settings['tax_rate']; ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="update_general" class="btn-primary">
                                        <i class="fas fa-save"></i> Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Shipping Settings -->
                        <div id="shipping-section" class="settings-section">
                            <h2><i class="fas fa-truck"></i> Shipping Settings</h2>
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="free_shipping_threshold">Free Shipping Threshold (₱)</label>
                                    <input type="number" id="free_shipping_threshold" name="free_shipping_threshold" step="0.01" min="0" value="<?php echo $settings['free_shipping_threshold']; ?>" required>
                                    <small>Orders above this amount get free shipping</small>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="standard_shipping_fee">Standard Shipping Fee (₱)</label>
                                        <input type="number" id="standard_shipping_fee" name="standard_shipping_fee" step="0.01" min="0" value="<?php echo $settings['standard_shipping_fee']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="express_shipping_fee">Express Shipping Fee (₱)</label>
                                        <input type="number" id="express_shipping_fee" name="express_shipping_fee" step="0.01" min="0" value="<?php echo $settings['express_shipping_fee']; ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="shipping_zones">Shipping Zones</label>
                                    <textarea id="shipping_zones" name="shipping_zones" placeholder="Enter shipping zones and rates (one per line)"><?php echo htmlspecialchars($settings['shipping_zones'] ?? ''); ?></textarea>
                                    <small>Format: Zone Name: Fee (one per line)</small>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="update_shipping" class="btn-primary">
                                        <i class="fas fa-save"></i> Save Shipping Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Payment Settings -->
                        <div id="payment-section" class="settings-section">
                            <h2><i class="fas fa-credit-card"></i> Payment Settings</h2>
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label>Payment Methods</label>
                                    <div class="checkbox-group">
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="payment_methods[]" value="cod" <?php echo strpos($settings['payment_methods'], 'cod') !== false ? 'checked' : ''; ?>>
                                            <label>Cash on Delivery (COD)</label>
                                        </div>
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="payment_methods[]" value="paypal" <?php echo strpos($settings['payment_methods'], 'paypal') !== false ? 'checked' : ''; ?>>
                                            <label>PayPal</label>
                                        </div>
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="payment_methods[]" value="stripe" <?php echo strpos($settings['payment_methods'], 'stripe') !== false ? 'checked' : ''; ?>>
                                            <label>Stripe</label>
                                        </div>
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="payment_methods[]" value="bank" <?php echo strpos($settings['payment_methods'], 'bank') !== false ? 'checked' : ''; ?>>
                                            <label>Bank Transfer</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="paypal_email">PayPal Email</label>
                                    <input type="email" id="paypal_email" name="paypal_email" value="<?php echo htmlspecialchars($settings['paypal_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="stripe_key">Stripe API Key</label>
                                    <input type="text" id="stripe_key" name="stripe_key" value="<?php echo htmlspecialchars($settings['stripe_key'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bank_details">Bank Account Details</label>
                                    <textarea id="bank_details" name="bank_details" placeholder="Enter bank account details for bank transfers"><?php echo htmlspecialchars($settings['bank_details'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="update_payment" class="btn-primary">
                                        <i class="fas fa-save"></i> Save Payment Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Email Settings -->
                        <div id="email-section" class="settings-section">
                            <h2><i class="fas fa-envelope"></i> Email Settings</h2>
                            <div class="info-box">
                                <h4>SMTP Configuration</h4>
                                <p>Configure your SMTP settings to enable email notifications. For Gmail, use smtp.gmail.com, port 587, and TLS encryption.</p>
                            </div>
                            <form method="POST" action="">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="smtp_host">SMTP Host</label>
                                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_port">SMTP Port</label>
                                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo $settings['smtp_port']; ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="smtp_username">SMTP Username</label>
                                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_password">SMTP Password</label>
                                        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="smtp_encryption">SMTP Encryption</label>
                                    <select id="smtp_encryption" name="smtp_encryption" required>
                                        <option value="tls" <?php echo $settings['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $settings['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $settings['smtp_encryption'] == 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="update_email" class="btn-primary">
                                        <i class="fas fa-save"></i> Save Email Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Backup Settings -->
                        <div id="backup-section" class="settings-section">
                            <h2><i class="fas fa-database"></i> Backup & Restore</h2>
                            
                            <div style="margin-bottom: 2rem;">
                                <form method="POST" action="">
                                    <button type="submit" name="create_backup" class="btn-primary">
                                        <i class="fas fa-database"></i> Create New Backup
                                    </button>
                                </form>
                            </div>

                            <h3><i class="fas fa-history"></i> Available Backups</h3>
                            <div class="backup-list">
                                <?php if (empty($backups)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-database"></i>
                                        <p>No backups available</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($backups as $backup): 
                                        $backup_path = '../backups/' . $backup;
                                        $file_size = filesize($backup_path);
                                        $size_formatted = $file_size < 1024 ? $file_size . ' B' : 
                                                          ($file_size < 1048576 ? round($file_size / 1024, 2) . ' KB' : 
                                                          round($file_size / 1048576, 2) . ' MB');
                                    ?>
                                        <div class="backup-item">
                                            <div class="backup-info">
                                                <i class="fas fa-file-archive"></i>
                                                <div class="backup-details">
                                                    <div class="backup-name"><?php echo $backup; ?></div>
                                                    <div class="backup-size">Size: <?php echo $size_formatted; ?></div>
                                                </div>
                                            </div>
                                            <div class="backup-actions">
                                                <a href="../backups/<?php echo $backup; ?>" download class="btn-download" title="Download"><i class="fas fa-download"></i></a>
                                                <a href="#" onclick="restoreBackup('<?php echo $backup; ?>')" class="btn-restore" title="Restore"><i class="fas fa-undo"></i></a>
                                                <a href="#" onclick="deleteBackup('<?php echo $backup; ?>')" class="btn-delete" title="Delete"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div id="system-section" class="settings-section">
                            <h2><i class="fas fa-info-circle"></i> System Information</h2>
                            
                            <table class="data-table">
                                <tr>
                                    <td><strong>PHP Version</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>MySQL Version</strong></td>
                                    <td><?php echo $conn->query("SELECT VERSION()")->fetch_array()[0]; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software</strong></td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Protocol</strong></td>
                                    <td><?php echo $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>HTTP Host</strong></td>
                                    <td><?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Document Root</strong></td>
                                    <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Upload Size</strong></td>
                                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Post Size</strong></td>
                                    <td><?php echo ini_get('post_max_size'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Memory Limit</strong></td>
                                    <td><?php echo ini_get('memory_limit'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Execution Time</strong></td>
                                    <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
                                </tr>
                            </table>

                            <h3><i class="fas fa-chart-pie"></i> Database Statistics</h3>
                            <?php
                            $table_stats = $conn->query("
                                SELECT 
                                    TABLE_NAME as table_name,
                                    TABLE_ROWS as rows,
                                    DATA_LENGTH + INDEX_LENGTH as size
                                FROM information_schema.TABLES 
                                WHERE TABLE_SCHEMA = DATABASE()
                            ");
                            ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Rows</th>
                                        <th>Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($table = $table_stats->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $table['table_name']; ?></td>
                                            <td><?php echo number_format($table['rows']); ?></td>
                                            <td><?php echo round($table['size'] / 1024, 2); ?> KB</td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSettingsTab(tab) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tabElement => {
                tabElement.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(tab + '-section').classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }

        function restoreBackup(filename) {
            if (confirm('⚠️ Are you sure you want to restore this backup? Current data will be overwritten. This action cannot be undone.')) {
                window.location.href = 'restore-backup.php?file=' + encodeURIComponent(filename);
            }
        }

        function deleteBackup(filename) {
            if (confirm('Are you sure you want to delete this backup?')) {
                window.location.href = 'delete-backup.php?file=' + encodeURIComponent(filename);
            }
        }
    </script>
</body>
</html>