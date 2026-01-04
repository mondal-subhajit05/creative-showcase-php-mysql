<?php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
require_once '../config/db.php';

// Get username from URL
$username = isset($_GET['username']) ? mysqli_real_escape_string($conn, $_GET['username']) : '';

if (empty($username)) {
    header('Location: ../index.php');
    exit;
}

// Get user info using prepared statement
$query = "SELECT * FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header('Location: ../index.php');
    exit;
}

$user_id = $user['id'];
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get user's images with like counts
$query = "SELECT i.*, 
          (SELECT COUNT(*) FROM likes WHERE image_id = i.id) as like_count,
          (SELECT COUNT(*) FROM comments WHERE image_id = i.id) as comment_count
          FROM images i 
          WHERE i.user_id = ? 
          ORDER BY uploaded_at DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$images = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
}

// Calculate followers and following counts
$followers_query = "SELECT COUNT(*) as count FROM follows WHERE following_id = ?";
$following_query = "SELECT COUNT(*) as count FROM follows WHERE follower_id = ?";

// Get followers count
$stmt = mysqli_prepare($conn, $followers_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$followers_result = mysqli_stmt_get_result($stmt);
$followers_count = mysqli_fetch_assoc($followers_result)['count'];

// Get following count
$stmt = mysqli_prepare($conn, $following_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$following_result = mysqli_stmt_get_result($stmt);
$following_count = mysqli_fetch_assoc($following_result)['count'];

// Get notifications for current user (if logged in and viewing own profile)
$notifications = [];
$unread_notifications_count = 0;

if ($current_user_id && $current_user_id == $user_id) {
    // Get notifications
    $notifications_query = "SELECT n.*, u.username, u.profile_image 
                           FROM notifications n 
                           JOIN users u ON n.source_id = u.id 
                           WHERE n.user_id = ? 
                           ORDER BY n.created_at DESC 
                           LIMIT 20";
    $stmt = mysqli_prepare($conn, $notifications_query);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
    mysqli_stmt_execute($stmt);
    $notifications_result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = $row;
    }
    
    // Get unread notifications count
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $unread_query);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
    mysqli_stmt_execute($stmt);
    $unread_result = mysqli_stmt_get_result($stmt);
    $unread_notifications_count = mysqli_fetch_assoc($unread_result)['count'];
}

// Check if current user is logged in and if they follow this user
$isFollowing = false;

if ($current_user_id && $current_user_id != $user_id) {
    $check_follow_query = "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?";
    $stmt = mysqli_prepare($conn, $check_follow_query);
    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $isFollowing = mysqli_stmt_num_rows($stmt) > 0;
}

// Handle follow/unfollow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_action'])) {
    if (!$current_user_id) {
        header('Location: ../login.php');
        exit;
    }
    
    // Prevent self-follow
    if ($current_user_id == $user_id) {
        die("You cannot follow yourself.");
    }
    
    // Validate action
    $allowed_actions = ['follow', 'unfollow'];
    $action = $_POST['action'];
    
    if (!in_array($action, $allowed_actions)) {
        die("Invalid action.");
    }
    
    if ($action === 'follow') {
        // Add to follows table
        $follow_query = "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $follow_query);
        mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $isFollowing = true;
            $followers_count++;
            
            // Send notification
            $notification_query = "INSERT INTO notifications (user_id, type, source_id, message) 
                                  VALUES (?, 'follow', ?, ?)";
            $notification_message = "started following you";
            $stmt_notif = mysqli_prepare($conn, $notification_query);
            mysqli_stmt_bind_param($stmt_notif, "iis", $user_id, $current_user_id, $notification_message);
            mysqli_stmt_execute($stmt_notif);
        }
    } else {
        // Remove from follows table
        $unfollow_query = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
        $stmt = mysqli_prepare($conn, $unfollow_query);
        mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $isFollowing = false;
            $followers_count--;
        }
    }
    
    // Redirect to refresh page and avoid form resubmission
    header("Location: user.php?username=" . urlencode($username));
    exit;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!$current_user_id) {
        header('Location: ../login.php');
        exit;
    }
    
    $image_id = intval($_POST['image_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    $comment_query = "INSERT INTO comments (user_id, image_id, comment) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $comment_query);
    mysqli_stmt_bind_param($stmt, "iis", $current_user_id, $image_id, $comment);
    mysqli_stmt_execute($stmt);
    
    // Get image owner for notification
    $owner_query = "SELECT user_id FROM images WHERE id = ?";
    $stmt = mysqli_prepare($conn, $owner_query);
    mysqli_stmt_bind_param($stmt, "i", $image_id);
    mysqli_stmt_execute($stmt);
    $owner_result = mysqli_stmt_get_result($stmt);
    $image_owner = mysqli_fetch_assoc($owner_result);
    
    // Send notification if comment is not by the image owner
    if ($image_owner && $image_owner['user_id'] != $current_user_id) {
        $notification_query = "INSERT INTO notifications (user_id, type, source_id, message) 
                              VALUES (?, 'comment', ?, ?)";
        $notification_message = "commented on your artwork";
        $stmt_notif = mysqli_prepare($conn, $notification_query);
        mysqli_stmt_bind_param($stmt_notif, "iis", $image_owner['user_id'], $current_user_id, $notification_message);
        mysqli_stmt_execute($stmt_notif);
    }
    
    // Redirect to refresh page
    header("Location: user.php?username=" . urlencode($username));
    exit;
}

// Handle mark notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if ($current_user_id && $current_user_id == $user_id) {
        $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        mysqli_stmt_execute($stmt);
        
        $unread_notifications_count = 0;
        
        header("Location: user.php?username=" . urlencode($username));
        exit;
    }
}

// Get comments for each image
foreach ($images as &$image) {
    $image_id = $image['id'];
    $comments_query = "SELECT c.*, u.username, u.profile_image FROM comments c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.image_id = ? 
                      ORDER BY c.created_at DESC";
    $stmt = mysqli_prepare($conn, $comments_query);
    mysqli_stmt_bind_param($stmt, "i", $image_id);
    mysqli_stmt_execute($stmt);
    $comments_result = mysqli_stmt_get_result($stmt);
    $image_comments = [];
    if ($comments_result) {
        while ($row = mysqli_fetch_assoc($comments_result)) {
            $image_comments[] = $row;
        }
    }
    $image['comments'] = $image_comments;
}
unset($image);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - Creative Showcase</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Navbar mobile spacing */
        .navbar-toggler {
            border: none;
        }
        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* Mobile nav links */
        @media (max-width: 991px) {
            .nav-links {
                display: flex;
                flex-direction: column;
                gap: 15px;
                padding: 20px 0;
                text-align: center;
            }
        }

        /* Artwork grid responsiveness */
        @media (max-width: 992px) {
            .col-md-4 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        @media (max-width: 576px) {
            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* Header text spacing */
        @media (max-width: 576px) {
            .user-details h1 {
                font-size: 1.6rem;
            }

            .user-details .bio {
                font-size: 0.95rem;
            }

            .follow-btn {
                width: 100%;
                text-align: center;
            }
        }

        /* Modal scrolling on small devices */
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-body {
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-body img {
                max-height: 250px;
            }
        }

        /* Comment box mobile fix */
        @media (max-width: 576px) {
            .comment-item {
                font-size: 0.85rem;
            }

            .comment-author {
                font-size: 0.85rem;
            }
        }

        .user-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 100px 0 60px;
            margin-bottom: 3rem;
            border-radius: 0 0 50px 50px;
        }
        
        .user-info-container {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            overflow: hidden;
            background: white;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details h1 {
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .user-details .username {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }
        
        .user-details .bio {
            max-width: 600px;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .user-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat {
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .stat:hover {
            transform: translateY(-3px);
        }
        
        .stat .number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat .label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .follow-btn {
            background: white;
            color: var(--primary);
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .follow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .follow-btn.following {
            background: var(--success);
            color: white;
        }
        
        .notification-btn {
            position: relative;
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .notification-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Image card styles */
        .image-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            background: white;
        }
        
        .image-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .image-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .image-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .image-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Comments section */
        .comments-section {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .comment-item {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .comment-author {
            font-weight: bold;
            color: var(--primary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .comment-author img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
        }
        
        .comment-text {
            font-size: 0.9rem;
            margin: 0;
        }
        
        .comment-time {
            font-size: 0.8rem;
            color: #999;
            margin-top: 5px;
        }
        
        .no-comments {
            text-align: center;
            color: #999;
            padding: 20px;
            font-style: italic;
        }
        
        .comment-form textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .category-badge {
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        /* Modal styles */
        .modal-body img {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 10px;
        }
        
        /* Followers/Following modal styles */
        .follow-modal .user-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .follow-modal .user-item:hover {
            background: #f8f9fa;
        }
        
        .follow-modal .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .follow-modal .user-info {
            flex: 1;
        }
        
        .follow-modal .user-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .follow-modal .user-username {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Notifications modal styles */
        .notification-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f7ff;
            border-left: 3px solid var(--primary);
        }
        
        .notification-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-message {
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .notification-message a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        
        .notification-message a:hover {
            text-decoration: underline;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #888;
        }
        
        .notification-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
            text-transform: uppercase;
        }
        
        .notification-type.follow {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-type.comment {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .notification-type.like {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .notification-actions button {
            font-size: 0.8rem;
            padding: 3px 10px;
        }
        
        @media (max-width: 768px) {
            .user-header {
                padding: 80px 0 40px;
            }
            
            .user-info-container {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            
            .user-stats {
                justify-content: center;
                flex-wrap: wrap;
                gap: 1.5rem;
            }
            
            .user-actions {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }
            
            .user-actions button {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .user-header {
                padding: 60px 0 30px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .user-stats {
                gap: 1rem;
            }
            
            .stat .number {
                font-size: 1.3rem;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loading-spinner.active {
            display: block;
        }
        
        .user-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar navbar-expand-lg">
                <a href="../index.php" class="logo navbar-brand">
                    <i class="fas fa-palette"></i>
                    <span>CreativeShowcase</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNav">
                    <div class="nav-links ms-auto">
                        <a href="../index.php">Home</a>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="../dashboard/profile.php">Dashboard</a>
                            <a href="../auth/logout.php" class="btn btn-outline">Logout</a>
                        <?php else: ?>
                            <a href="../login.php" class="btn btn-outline">Login</a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <section class="user-header">
        <div class="container">
            <div class="user-info-container">
                <div class="profile-avatar">
                    <img src="../assets/uploads/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" 
                         alt="<?php echo htmlspecialchars($user['username']); ?>"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=150&background=6c5ce7&color=fff'">
                </div>
                <div class="user-details">
                    <h1><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h1>
                    <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>
                    
                    <?php if(!empty($user['bio'])): ?>
                        <div class="bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
                    <?php endif; ?>
                    
                    <!-- User Stats -->
                    <div class="user-stats">
                        <div class="stat">
                            <span class="number"><?php echo count($images); ?></span>
                            <span class="label">Artworks</span>
                        </div>
                        <div class="stat" data-bs-toggle="modal" data-bs-target="#followersModal">
                            <span class="number"><?php echo $followers_count; ?></span>
                            <span class="label">Followers</span>
                        </div>
                        <div class="stat" data-bs-toggle="modal" data-bs-target="#followingModal">
                            <span class="number"><?php echo $following_count; ?></span>
                            <span class="label">Following</span>
                        </div>
                    </div>
                    
                    <!-- User Actions -->
                    <div class="user-actions">
                        <?php if($current_user_id && $current_user_id != $user_id): ?>
                            <form method="POST" action="" class="follow-form" data-user-id="<?php echo $user_id; ?>">
                                <input type="hidden" name="follow_action" value="1">
                                <input type="hidden" name="action" value="<?php echo $isFollowing ? 'unfollow' : 'follow'; ?>">
                                <button type="submit" class="follow-btn <?php echo $isFollowing ? 'following' : ''; ?>" id="followButton">
                                    <i class="fas <?php echo $isFollowing ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
                                    <?php echo $isFollowing ? ' Following' : ' Follow'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if($current_user_id && $current_user_id == $user_id): ?>
                            <button class="notification-btn" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if($unread_notifications_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="container">
        <h2 style="margin-bottom: 2rem; color: var(--primary);">
            <?php echo count($images); ?> Artwork<?php echo count($images) != 1 ? 's' : ''; ?>
        </h2>
        
        <?php if(empty($images)): ?>
            <div class="empty-state">
                <i class="fas fa-paint-brush"></i>
                <h3>No artworks yet</h3>
                <p>This artist hasn't uploaded any artworks yet.</p>
            </div>
        <?php else: ?>
            <!-- Bootstrap Grid for Images -->
            <div class="row">
                <?php foreach($images as $image): ?>
                <div class="col-md-4 mb-4">
                    <div class="image-card">
                        <!-- Image -->
                        <img src="../assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($image['title']); ?>"
                             data-bs-toggle="modal" 
                             data-bs-target="#imageModal<?php echo $image['id']; ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1579546929662-711aa81148cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'">
                        
                        <!-- Image Info -->
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 0.5rem; font-size: 1.2rem;"><?php echo htmlspecialchars($image['title']); ?></h3>
                            
                            <?php if(!empty($image['description'])): ?>
                                <p style="margin: 0.5rem 0; font-size: 0.9rem; opacity: 0.9;">
                                    <?php echo htmlspecialchars(substr($image['description'], 0, 100)); ?>
                                    <?php if(strlen($image['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span class="category-badge"><?php echo htmlspecialchars($image['category']); ?></span>
                                <span style="font-size: 0.8rem; color: #666;">
                                    <?php echo date('M d, Y', strtotime($image['uploaded_at'])); ?>
                                </span>
                            </div>
                            
                            <!-- Image Stats -->
                            <div class="image-stats">
                                <span class="image-stat">
                                    <i class="fas fa-eye"></i> <?php echo $image['views']; ?>
                                </span>
                                <span class="image-stat">
                                    <i class="fas fa-heart" style="color: #e74c3c;"></i> <?php echo $image['like_count']; ?>
                                </span>
                                <span class="image-stat">
                                    <i class="fas fa-comment" style="color: #3498db;"></i> <?php echo $image['comment_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Image Modal with Comments -->
                <div class="modal fade" id="imageModal<?php echo $image['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?php echo htmlspecialchars($image['title']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Image -->
                                <img src="../assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['title']); ?>"
                                     class="img-fluid mb-4"
                                     onerror="this.src='https://images.unsplash.com/phone-1579546929662-711aa81148cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=60'">
                                
                                <!-- Image Details -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Description</h6>
                                        <p><?php echo nl2br(htmlspecialchars($image['description'] ?: 'No description')); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Details</h6>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                            <span><i class="fas fa-eye"></i> <?php echo $image['views']; ?> views</span>
                                            <span><i class="fas fa-heart" style="color: #e74c3c;"></i> <?php echo $image['like_count']; ?> likes</span>
                                            <span><i class="fas fa-comment" style="color: #3498db;"></i> <?php echo $image['comment_count']; ?> comments</span>
                                        </div>
                                        <div style="margin-bottom: 0.5rem;">
                                            <span class="category-badge"><?php echo htmlspecialchars($image['category']); ?></span>
                                        </div>
                                        <div>
                                            <small style="color: #666;">
                                                Uploaded on <?php echo date('F d, Y', strtotime($image['uploaded_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Comments Section -->
                                <h6>Comments (<?php echo $image['comment_count']; ?>)</h6>
                                <div class="comments-section">
                                    <?php if(empty($image['comments'])): ?>
                                        <div class="no-comments">
                                            <p>No comments yet. Be the first to comment!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($image['comments'] as $comment): ?>
                                            <div class="comment-item">
                                                <div class="comment-author">
                                                    <img src="../assets/uploads/<?php echo !empty($comment['profile_image']) ? htmlspecialchars($comment['profile_image']) : 'default.png'; ?>" 
                                                         alt="<?php echo htmlspecialchars($comment['username']); ?>"
                                                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($comment['username']); ?>&size=30&background=6c5ce7&color=fff'">
                                                    @<?php echo htmlspecialchars($comment['username']); ?>
                                                </div>
                                                <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                                <div class="comment-time">
                                                    <?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Comment Form -->
                                <?php if($current_user_id): ?>
                                    <form method="POST" action="" class="mt-4">
                                        <input type="hidden" name="add_comment" value="1">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <div class="mb-3">
                                            <textarea name="comment" class="form-control" 
                                                      placeholder="Add a comment..." 
                                                      rows="3" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> Post Comment
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-info mt-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Please <a href="../login.php">login</a> to leave a comment.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Followers Modal -->
    <div class="modal fade follow-modal" id="followersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Followers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="followersList">
                        <!-- Followers will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Following Modal -->
    <div class="modal fade follow-modal" id="followingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Following</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="followingList">
                        <!-- Following will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal fade follow-modal" id="notificationsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4>No notifications yet</h4>
                            <p>When you get notifications, they'll appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="notification-actions mb-3">
                            <form method="POST" action="">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-check-double"></i> Mark all as read
                                </button>
                            </form>
                        </div>
                        
                        <?php foreach($notifications as $notification): ?>
                            <div class="notification-item <?php echo isset($notification['is_read']) && $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                                <img src="../assets/uploads/<?php echo !empty($notification['profile_image']) ? htmlspecialchars($notification['profile_image']) : 'default.png'; ?>" 
                                     alt="<?php echo htmlspecialchars($notification['username']); ?>"
                                     class="notification-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($notification['username']); ?>&size=45&background=6c5ce7&color=fff'">
                                
                                <div class="notification-content">
                                    <div class="notification-message">
                                        <a href="user.php?username=<?php echo urlencode($notification['username']); ?>">
                                            @<?php echo htmlspecialchars($notification['username']); ?>
                                        </a>
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                        <span class="notification-type <?php echo $notification['type']; ?>">
                                            <?php echo $notification['type']; ?>
                                        </span>
                                    </div>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
   <script>
    // Function to load followers/following via AJAX
    function loadFollowData(type, userId) {
        console.log(`Loading ${type} for user ${userId}`);
        
        const modalId = type + 'Modal';
        const listId = type + 'List';
        const loadingSpinner = document.querySelector(`#${modalId} .loading-spinner`);
        const listElement = document.getElementById(listId);
        
        if (!listElement) {
            console.error(`Element #${listId} not found`);
            return;
        }
        
        loadingSpinner.classList.add('active');
        listElement.innerHTML = '';
        
        // CORRECT PATH: Since user.php is in public/ and API is in api/ at same level
        const apiPath = `../api/get_${type}.php?user_id=${userId}`;
        console.log(`Fetching from: ${apiPath}`);
        
        fetch(apiPath)
            .then(response => {
                console.log('Response status:', response.status, response.statusText);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                console.log('Content-Type:', contentType);
                
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.log('Non-JSON response (first 500 chars):', text.substring(0, 500));
                        throw new Error('Response is not JSON. Server returned HTML or an error page.');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                loadingSpinner.classList.remove('active');
                
                if (data.success) {
                    const items = data[type] || [];
                    if (items.length > 0) {
                        let html = '';
                        items.forEach(user => {
                            // Handle profile image path correctly
                            let profileImage;
                            if (user.profile_image && user.profile_image !== 'default.png' && user.profile_image !== '') {
                                // Images are stored in assets/uploads/
                                profileImage = `../assets/uploads/${user.profile_image}`;
                            } else {
                                profileImage = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.username || user.full_name || 'User')}&size=50&background=6c5ce7&color=fff`;
                            }
                            
                            const userName = user.full_name || user.username || 'User';
                            const userUsername = user.username || 'user';
                            
                            html += `
                                <div class="user-item">
                                    <img src="${profileImage}" 
                                         alt="${userName}"
                                         class="user-avatar"
                                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&size=50&background=6c5ce7&color=fff'">
                                    <div class="user-info">
                                        <div class="user-name">${userName}</div>
                                        <div class="user-username">@${userUsername}</div>
                                    </div>
                                    <a href="user.php?username=${userUsername}" class="btn btn-outline-primary btn-sm">View Profile</a>
                                </div>
                            `;
                        });
                        listElement.innerHTML = html;
                    } else {
                        listElement.innerHTML = `<div class="text-center py-4 text-muted">
                            <i class="fas fa-users me-2"></i>
                            No ${type} found
                        </div>`;
                    }
                } else {
                    listElement.innerHTML = `<div class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || `Failed to load ${type}`}
                    </div>`;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                loadingSpinner.classList.remove('active');
                listElement.innerHTML = `<div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading ${type}. Please check console for details.
                    <br><small class="text-muted">${error.message}</small>
                    <br><small class="text-muted">Tried path: ${apiPath}</small>
                </div>`;
            });
    }

    // Load followers/following when modal opens
    document.addEventListener('DOMContentLoaded', function() {
        const userId = <?php echo $user_id; ?>;
        console.log('User ID:', userId);
        console.log('Current URL:', window.location.href);
        console.log('Current path:', window.location.pathname);
        
        // Test the API endpoint
        console.log('Testing API endpoints...');
        console.log('Trying path: ../api/get_followers.php?user_id=' + userId);
        
        // Followers modal
        const followersModal = document.getElementById('followersModal');
        if (followersModal) {
            followersModal.addEventListener('show.bs.modal', function() {
                console.log('Followers modal opening');
                loadFollowData('followers', userId);
            });
        }
        
        // Following modal
        const followingModal = document.getElementById('followingModal');
        if (followingModal) {
            followingModal.addEventListener('show.bs.modal', function() {
                console.log('Following modal opening');
                loadFollowData('following', userId);
            });
        }
        
        // Notifications modal
        const notificationsModal = document.getElementById('notificationsModal');
        if (notificationsModal) {
            notificationsModal.addEventListener('show.bs.modal', function() {
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.style.display = 'none';
                }
            });
        }
        
        // Clean up modals when closed
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                const followersList = document.getElementById('followersList');
                const followingList = document.getElementById('followingList');
                if (followersList) followersList.innerHTML = '';
                if (followingList) followingList.innerHTML = '';
            });
        });
        
        // Debug: Test the API endpoint directly
        async function testApi() {
            try {
                const response = await fetch(`../api/get_followers.php?user_id=${userId}`);
                console.log('Direct API test - Status:', response.status);
                console.log('Direct API test - Headers:', response.headers);
                const text = await response.text();
                console.log('Direct API test - Response (first 500 chars):', text.substring(0, 500));
            } catch (error) {
                console.error('Direct API test failed:', error);
            }
        }
        
        // Uncomment to test API on page load
        // testApi();
    });
    
    // AJAX follow/unfollow
    document.addEventListener('DOMContentLoaded', function() {
        const followForms = document.querySelectorAll('.follow-form');
        
        followForms.forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const followBtn = this.querySelector('.follow-btn');
                const followersCountElement = document.querySelector('.user-stats .stat:nth-child(2) .number');
                
                // Show loading state
                const originalText = followBtn.innerHTML;
                followBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                followBtn.disabled = true;
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    
                    // If not redirected, reload the page
                    window.location.reload();
                    
                } catch (error) {
                    console.error('Error:', error);
                    followBtn.innerHTML = originalText;
                    followBtn.disabled = false;
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
</script>
</body>
</html>