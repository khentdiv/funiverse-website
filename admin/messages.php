<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Determine the correct date column for messages
$message_date_column = 'created_at';
$check = $conn->query("SHOW COLUMNS FROM messages LIKE 'created_at'");
if (!$check || $check->num_rows == 0) {
    $check = $conn->query("SHOW COLUMNS FROM messages LIKE 'created_date'");
    if ($check && $check->num_rows > 0) {
        $message_date_column = 'created_date';
    } else {
        $check = $conn->query("SHOW COLUMNS FROM messages LIKE 'date_sent'");
        if ($check && $check->num_rows > 0) {
            $message_date_column = 'date_sent';
        }
    }
}

// Handle Mark as Read
if (isset($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    $conn->query("UPDATE messages SET status = 'read' WHERE message_id = $message_id");
    $_SESSION['success'] = "Message marked as read";
    redirect('messages.php');
}

// Handle Mark as Unread
if (isset($_GET['mark_unread'])) {
    $message_id = intval($_GET['mark_unread']);
    $conn->query("UPDATE messages SET status = 'unread' WHERE message_id = $message_id");
    $_SESSION['success'] = "Message marked as unread";
    redirect('messages.php');
}

// Handle Delete Message
if (isset($_GET['delete'])) {
    $message_id = intval($_GET['delete']);
    $conn->query("DELETE FROM messages WHERE message_id = $message_id");
    $_SESSION['success'] = "Message deleted successfully";
    redirect('messages.php');
}

// Handle Reply to Message
if (isset($_POST['send_reply'])) {
    $message_id = intval($_POST['message_id']);
    $reply_subject = $conn->real_escape_string($_POST['reply_subject']);
    $reply_message = $conn->real_escape_string($_POST['reply_message']);
    $recipient_email = $conn->real_escape_string($_POST['recipient_email']);
    $recipient_name = $conn->real_escape_string($_POST['recipient_name']);
    
    // In a real application, you would send an email here
    // For now, we'll just log it and mark as replied
    
    $conn->query("UPDATE messages SET status = 'replied', replied_at = NOW(), replied_by = $admin_id WHERE message_id = $message_id");
    
    // Save reply in database
    $conn->query("INSERT INTO message_replies (message_id, admin_id, subject, message, sent_at) 
                  VALUES ($message_id, $admin_id, '$reply_subject', '$reply_message', NOW())");
    
    $_SESSION['success'] = "Reply sent successfully";
    redirect('messages.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build query
$query = "SELECT m.*, u.full_name as user_name, u.email as user_email,
          (SELECT COUNT(*) FROM message_replies WHERE message_id = m.message_id) as reply_count
          FROM messages m
          LEFT JOIN users u ON m.user_id = u.user_id
          WHERE 1=1";

if ($status_filter != 'all') {
    $query .= " AND m.status = '$status_filter'";
}

if (!empty($search)) {
    $query .= " AND (m.name LIKE '%$search%' OR m.email LIKE '%$search%' OR m.subject LIKE '%$search%' OR m.message LIKE '%$search%')";
}

$query .= " ORDER BY m.{$message_date_column} DESC";

$messages = $conn->query($query);

// Get message statistics
$stats = [
    'all' => $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'],
    'unread' => $conn->query("SELECT COUNT(*) as count FROM messages WHERE status = 'unread'")->fetch_assoc()['count'],
    'read' => $conn->query("SELECT COUNT(*) as count FROM messages WHERE status = 'read'")->fetch_assoc()['count'],
    'replied' => $conn->query("SELECT COUNT(*) as count FROM messages WHERE status = 'replied'")->fetch_assoc()['count']
];

// Get message for viewing if ID is provided
$view_message = null;
$replies = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $view_message = $conn->query("
        SELECT m.*, u.full_name as user_name, u.email as user_email,
               u.user_id, u.phone, u.address
        FROM messages m
        LEFT JOIN users u ON m.user_id = u.user_id
        WHERE m.message_id = $view_id
    ")->fetch_assoc();
    
    if ($view_message) {
        // Mark as read when viewed
        if ($view_message['status'] == 'unread') {
            $conn->query("UPDATE messages SET status = 'read' WHERE message_id = $view_id");
        }
        
        // Get replies
        $replies = $conn->query("
            SELECT r.*, u.full_name as admin_name
            FROM message_replies r
            LEFT JOIN users u ON r.admin_id = u.user_id
            WHERE r.message_id = $view_id
            ORDER BY r.sent_at ASC
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Furniverse Admin</title>
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

        .messages-container {
            width: 100%;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            text-decoration: none;
            display: block;
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
        }

        .stat-card[data-color="all"]::before { background: #6b5b85; }
        .stat-card[data-color="unread"]::before { background: #b84a6e; }
        .stat-card[data-color="read"]::before { background: #4a9b6e; }
        .stat-card[data-color="replied"]::before { background: #3a8ca8; }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #6b5b85;
        }

        .stat-card.active {
            border-color: #cfb087;
            box-shadow: 0 0 20px rgba(207, 176, 135, 0.2);
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

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-badge {
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #1e192c;
            border: 1px solid #332d44;
            color: #f0e6d2;
        }

        .filter-badge.all { border-color: #6b5b85; color: #b2a6ca; }
        .filter-badge.unread { border-color: #b84a6e; color: #ffb3b3; }
        .filter-badge.read { border-color: #4a9b6e; color: #b3ffb3; }
        .filter-badge.replied { border-color: #3a8ca8; color: #8bb9ff; }

        .search-box {
            display: flex;
            gap: 0.5rem;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            flex: 1;
            padding: 0.8rem 1.5rem;
            background: #161224;
            border: 1px solid #332d44;
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
        }

        .search-box input:focus {
            border-color: #cfb087;
        }

        .search-box button {
            padding: 0.8rem 1.5rem;
            background: transparent;
            border: 1px solid #cfb087;
            border-radius: 40px;
            color: #f0e6d2;
            cursor: pointer;
            transition: 0.3s;
        }

        .search-box button:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        /* Messages Layout */
        .messages-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            height: calc(100vh - 300px);
        }

        /* Messages List */
        .messages-list {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cfb087 #161224;
        }

        .messages-list::-webkit-scrollbar {
            width: 8px;
        }

        .messages-list::-webkit-scrollbar-track {
            background: #161224;
            border-radius: 10px;
        }

        .messages-list::-webkit-scrollbar-thumb {
            background: #cfb087;
            border-radius: 10px;
        }

        .message-item {
            padding: 1.5rem;
            border-bottom: 1px solid #332d44;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
        }

        .message-item:hover {
            background: #161224;
        }

        .message-item.unread {
            background: #2d2a1a;
            border-left: 4px solid #ffd966;
        }

        .message-item.active {
            background: #1a2d3a;
            border-left: 4px solid #cfb087;
        }

        .message-sender {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .message-sender h4 {
            margin: 0;
            font-size: 1rem;
            color: #f0e6d2;
        }

        .message-date {
            font-size: 0.8rem;
            color: #b2a6ca;
        }

        .message-subject {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #cfb087;
        }

        .message-preview {
            font-size: 0.85rem;
            color: #b2a6ca;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.8rem;
        }

        .message-status {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .message-status.unread { 
            background: #2d1a24; 
            color: #ffb3b3;
            border: 1px solid #b84a6e;
        }
        
        .message-status.read { 
            background: #1a2d24; 
            color: #b3ffb3;
            border: 1px solid #4a9b6e;
        }
        
        .message-status.replied { 
            background: #1a2d3a; 
            color: #8bb9ff;
            border: 1px solid #3a8ca8;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
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

        /* Message Detail */
        .message-detail {
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 42px;
            padding: 2rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cfb087 #161224;
        }

        .message-detail::-webkit-scrollbar {
            width: 8px;
        }

        .message-detail::-webkit-scrollbar-track {
            background: #161224;
            border-radius: 10px;
        }

        .message-detail::-webkit-scrollbar-thumb {
            background: #cfb087;
            border-radius: 10px;
        }

        .message-header {
            margin-bottom: 2rem;
        }

        .message-header h2 {
            font-size: 1.8rem;
            color: #f0e6d2;
            margin-bottom: 1rem;
        }

        .message-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #332d44;
        }

        .message-meta span {
            color: #b2a6ca;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-meta i {
            color: #cfb087;
        }

        .message-body {
            background: #161224;
            padding: 2rem;
            border-radius: 32px;
            color: #d6cee8;
            line-height: 1.8;
            margin-bottom: 2rem;
            border: 1px solid #332d44;
        }

        /* Reply Section */
        .reply-section {
            margin-bottom: 2rem;
        }

        .reply-section h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reply-section h3 i {
            color: #cfb087;
        }

        .reply-item {
            background: #161224;
            padding: 1.5rem;
            border-radius: 32px;
            margin-bottom: 1rem;
            border: 1px solid #332d44;
        }

        .reply-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            color: #b2a6ca;
            font-size: 0.85rem;
        }

        .reply-meta strong {
            color: #cfb087;
        }

        .reply-content {
            color: #d6cee8;
        }

        .reply-content strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #f0e6d2;
        }

        /* Reply Form */
        .reply-form {
            background: #161224;
            padding: 2rem;
            border-radius: 42px;
            border: 1px solid #332d44;
        }

        .reply-form h3 {
            font-size: 1.3rem;
            color: #f0e6d2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

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
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            background: #1e192c;
            border: 1px solid #332d44;
            border-radius: 40px;
            color: #f0e6d2;
            font-family: 'Inter', sans-serif;
            outline: none;
        }

        .form-group textarea {
            border-radius: 24px;
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #cfb087;
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
        }

        .btn-primary:hover {
            background: #cfb087;
            color: #0f0b17;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            color: #b2a6ca;
        }

        .empty-state i {
            font-size: 4rem;
            color: #4a3f60;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #f0e6d2;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #b2a6ca;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 0;
                display: none;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .messages-layout {
                grid-template-columns: 1fr;
            }
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
                <h1><i class="fas fa-envelope" style="margin-right: 10px; color: #cfb087;"></i>Messages</h1>
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

                <div class="messages-container">
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <a href="?status=all" class="stat-card <?php echo $status_filter == 'all' ? 'active' : ''; ?>" data-color="all">
                            <h3>All Messages</h3>
                            <div class="value"><?php echo $stats['all']; ?></div>
                        </a>
                        <a href="?status=unread" class="stat-card <?php echo $status_filter == 'unread' ? 'active' : ''; ?>" data-color="unread">
                            <h3>Unread</h3>
                            <div class="value"><?php echo $stats['unread']; ?></div>
                        </a>
                        <a href="?status=read" class="stat-card <?php echo $status_filter == 'read' ? 'active' : ''; ?>" data-color="read">
                            <h3>Read</h3>
                            <div class="value"><?php echo $stats['read']; ?></div>
                        </a>
                        <a href="?status=replied" class="stat-card <?php echo $status_filter == 'replied' ? 'active' : ''; ?>" data-color="replied">
                            <h3>Replied</h3>
                            <div class="value"><?php echo $stats['replied']; ?></div>
                        </a>
                    </div>

                    <!-- Search and Filter -->
                    <div class="action-bar">
                        <div class="filter-info">
                            <span class="filter-badge <?php echo $status_filter; ?>">
                                <i class="fas fa-filter"></i> <?php echo ucfirst($status_filter); ?> Messages
                            </span>
                        </div>
                        <form method="GET" class="search-box">
                            <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </form>
                    </div>

                    <!-- Messages Layout -->
                    <div class="messages-layout">
                        <!-- Messages List -->
                        <div class="messages-list">
                            <?php if ($messages && $messages->num_rows > 0): ?>
                                <?php while($msg = $messages->fetch_assoc()): 
                                    $date_col = $message_date_column;
                                ?>
                                    <div class="message-item <?php echo $msg['status']; ?> <?php echo (isset($_GET['view']) && $_GET['view'] == $msg['message_id']) ? 'active' : ''; ?>" onclick="window.location.href='?view=<?php echo $msg['message_id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>'">
                                        <div class="message-sender">
                                            <h4><?php echo htmlspecialchars($msg['name'] ?: $msg['user_name']); ?></h4>
                                            <span class="message-date"><?php echo date('M d, Y', strtotime($msg[$date_col])); ?></span>
                                        </div>
                                        <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                        <div class="message-preview">
                                            <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?>
                                        </div>
                                        <div class="message-footer">
                                            <span class="message-status <?php echo $msg['status']; ?>">
                                                <?php echo ucfirst($msg['status']); ?>
                                                <?php if ($msg['reply_count'] > 0): ?>
                                                    (<?php echo $msg['reply_count']; ?>)
                                                <?php endif; ?>
                                            </span>
                                            <div class="message-actions" onclick="event.stopPropagation()">
                                                <?php if ($msg['status'] != 'read' && $msg['status'] != 'replied'): ?>
                                                    <a href="?mark_read=<?php echo $msg['message_id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon" title="Mark as Read"><i class="fas fa-check"></i></a>
                                                <?php endif; ?>
                                                <?php if ($msg['status'] == 'read'): ?>
                                                    <a href="?mark_unread=<?php echo $msg['message_id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon" title="Mark as Unread"><i class="fas fa-envelope"></i></a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $msg['message_id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this message?')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-envelope-open"></i>
                                    <h3>No Messages Found</h3>
                                    <p>There are no messages matching your criteria.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Detail View -->
                        <div class="message-detail">
                            <?php if ($view_message): ?>
                                <div class="message-header">
                                    <h2><?php echo htmlspecialchars($view_message['subject']); ?></h2>
                                    <div class="message-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($view_message['name'] ?: $view_message['user_name']); ?></span>
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($view_message['email']); ?></span>
                                        <?php if ($view_message['phone']): ?>
                                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($view_message['phone']); ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y h:i A', strtotime($view_message[$message_date_column])); ?></span>
                                    </div>
                                </div>

                                <div class="message-body">
                                    <?php echo nl2br(htmlspecialchars($view_message['message'])); ?>
                                </div>

                                <!-- Replies -->
                                <?php if ($replies && $replies->num_rows > 0): ?>
                                    <div class="reply-section">
                                        <h3><i class="fas fa-reply-all"></i> Replies</h3>
                                        <?php while($reply = $replies->fetch_assoc()): ?>
                                            <div class="reply-item">
                                                <div class="reply-meta">
                                                    <span><strong><?php echo htmlspecialchars($reply['admin_name'] ?: 'Admin'); ?></strong> replied</span>
                                                    <span><?php echo date('M d, Y h:i A', strtotime($reply['sent_at'])); ?></span>
                                                </div>
                                                <div class="reply-content">
                                                    <strong><?php echo htmlspecialchars($reply['subject']); ?></strong>
                                                    <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Reply Form -->
                                <div class="reply-form">
                                    <h3><i class="fas fa-paper-plane"></i> Send Reply</h3>
                                    <form method="POST">
                                        <input type="hidden" name="message_id" value="<?php echo $view_message['message_id']; ?>">
                                        <input type="hidden" name="recipient_email" value="<?php echo $view_message['email']; ?>">
                                        <input type="hidden" name="recipient_name" value="<?php echo htmlspecialchars($view_message['name'] ?: $view_message['user_name']); ?>">
                                        
                                        <div class="form-group">
                                            <label for="reply_subject">Subject</label>
                                            <input type="text" id="reply_subject" name="reply_subject" required value="Re: <?php echo htmlspecialchars($view_message['subject']); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="reply_message">Message</label>
                                            <textarea id="reply_message" name="reply_message" required placeholder="Type your reply here..."></textarea>
                                        </div>
                                        
                                        <button type="submit" name="send_reply" class="btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Reply
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-envelope"></i>
                                    <h3>Select a Message</h3>
                                    <p>Choose a message from the list to view its contents and reply</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>