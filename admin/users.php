<?php
require_once '../config.php';

if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
        redirect('users.php');
    } else {
        // Get user name for log
        $user = $conn->query("SELECT full_name FROM users WHERE user_id = $user_id")->fetch_assoc();
        
        // Start transaction to ensure all deletions succeed or none
        $conn->begin_transaction();
        
        try {
            // First, delete related records from admin_permissions
            $delete_permissions = "DELETE FROM admin_permissions WHERE admin_id = ?";
            $stmt_permissions = $conn->prepare($delete_permissions);
            $stmt_permissions->bind_param("i", $user_id);
            $stmt_permissions->execute();
            $stmt_permissions->close();
            
            // Delete from notifications
            $delete_notifications = "DELETE FROM notifications WHERE user_id = ?";
            $stmt_notifications = $conn->prepare($delete_notifications);
            $stmt_notifications->bind_param("i", $user_id);
            $stmt_notifications->execute();
            $stmt_notifications->close();
            
            // Delete from customizations
            $delete_customizations = "DELETE FROM customizations WHERE user_id = ?";
            $stmt_customizations = $conn->prepare($delete_customizations);
            $stmt_customizations->bind_param("i", $user_id);
            $stmt_customizations->execute();
            $stmt_customizations->close();
            
            // Delete from orders
            $delete_orders = "DELETE FROM orders WHERE user_id = ?";
            $stmt_orders = $conn->prepare($delete_orders);
            $stmt_orders->bind_param("i", $user_id);
            $stmt_orders->execute();
            $stmt_orders->close();
            
            // Finally delete the user
            $query = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Check if user was deleted
            if ($stmt->affected_rows > 0) {
                // Log action
                $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'delete_user', ?)";
                $log_stmt = $conn->prepare($log_query);
                $details = "Deleted user: " . $user['full_name'] . " (ID: " . $user_id . ")";
                $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
                $log_stmt->execute();
                $log_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "User deleted successfully!";
            } else {
                throw new Exception("User not found or already deleted");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
        }
        redirect('users.php');
    }
}

// Fetch all users with additional info
$users = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count,
           (SELECT COUNT(*) FROM customizations WHERE user_id = u.user_id) as customization_count
    FROM users u 
    ORDER BY u.created_at DESC
");

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0,
    'admins' => $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'")->fetch_assoc()['count'] ?? 0,
    'customers' => $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'")->fetch_assoc()['count'] ?? 0,
    'active' => $conn->query("SELECT COUNT(*) as count FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'] ?? 0
];

// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Furniverse Admin</title>
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

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-primary {
            padding: 0.8rem 2rem;
            background: transparent;
            border: 1.5px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
            text-align: center;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #cfb087, #8b6f4c);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #6b5b85;
        }

        .stat-card h3 {
            color: #b2a6ca;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #f0e6d2;
        }

        .stat-card i {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            color: #cfb087;
            opacity: 0.2;
        }

        /* Warning Message */
        .warning-message {
            background: #2d2a1a;
            border: 1px solid #b88a4a;
            color: #ffd966;
            padding: 1.2rem 2rem;
            border-radius: 60px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .warning-message i {
            font-size: 1.5rem;
            color: #ffd966;
        }

        /* Table Styles */
        .table-responsive {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 1.5rem;
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            color: #b2a6ca;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #332d44;
        }

        .admin-table td {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #332d44;
            color: #d6cee8;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background: #161224;
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            background: #161224;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #332d44;
        }

        .user-avatar i {
            font-size: 1.5rem;
            color: #cfb087;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #f0e6d2;
            margin-bottom: 0.3rem;
        }

        .user-stats {
            display: flex;
            gap: 0.8rem;
            font-size: 0.8rem;
            color: #b2a6ca;
        }

        .user-stats span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #1e192c;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            border: 1px solid #332d44;
        }

        .user-stats i {
            color: #cfb087;
            font-size: 0.7rem;
        }

        /* Contact Info */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .contact-info div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #d6cee8;
        }

        .contact-info i {
            color: #cfb087;
            width: 16px;
        }

        /* Badge */
        .badge {
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge.primary {
            background: #1a2d3a;
            color: #8bb9ff;
            border: 1px solid #3a6ea5;
        }

        .badge.secondary {
            background: #2d2640;
            color: #b3a4cb;
            border: 1px solid #5a4b7a;
        }

        .badge.success {
            background: #1a2d24;
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }

        .badge.warning {
            background: #2d2a1a;
            color: #ffd966;
            border: 1px solid #b88a4a;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            background: transparent;
            border: 1px solid #332d44;
            border-radius: 30px;
            color: #b2a6ca;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-icon:hover {
            border-color: #cfb087;
            color: #cfb087;
            transform: scale(1.1);
        }

        .btn-icon.delete:hover {
            border-color: #b84a6e;
            color: #ffb3b3;
        }

        /* Last Login */
        .last-login {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .last-login i {
            color: #cfb087;
        }

        .text-muted {
            color: #b2a6ca;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 60px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #b2a6ca;
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
                <h1><i class="fas fa-users" style="margin-right: 10px; color: #cfb087;"></i>Manage Users</h1>
                <div class="header-actions">
                    <a href="add-user.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <h3>Total Users</h3>
                        <div class="value"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-shield"></i>
                        <h3>Admins</h3>
                        <div class="value"><?php echo $stats['admins']; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user"></i>
                        <h3>Customers</h3>
                        <div class="value"><?php echo $stats['customers']; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-circle-check"></i>
                        <h3>Active (30d)</h3>
                        <div class="value"><?php echo $stats['active']; ?></div>
                    </div>
                </div>

                <!-- Warning about deletion -->
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Deleting a user will permanently remove all associated data including orders, customizations, and permissions. This action cannot be undone.</span>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Activity</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                <div class="user-stats">
                                                    <span><i class="fas fa-shopping-cart"></i> <?php echo $user['order_count']; ?></span>
                                                    <span><i class="fas fa-paint-brush"></i> <?php echo $user['customization_count']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['user_type'] == 'admin' ? 'primary' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($user['last_login']): ?>
                                            <div class="last-login" title="Last login: <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?>">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo timeAgo($user['last_login']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge secondary">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit-user.php?id=<?php echo $user['user_id']; ?>" class="btn-icon" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?php echo $user['user_id']; ?>" 
                                                   class="btn-icon delete" 
                                                   onclick="return confirm('⚠️ WARNING: This will permanently delete this user and ALL their associated data including:\n\n• Orders\n• Customizations\n• Messages\n• Permissions\n\nThis action cannot be undone. Are you absolutely sure?')"
                                                   title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="user-details.php?id=<?php echo $user['user_id']; ?>" class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <h3>No Users Found</h3>
                                            <p>There are no users in the system yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>