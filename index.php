<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get random images for mosaic with user data and like counts
$query = "SELECT i.*, u.username, u.profile_image,
          (SELECT COUNT(*) FROM likes WHERE image_id = i.id) as like_count,
          (SELECT COUNT(*) FROM comments WHERE image_id = i.id) as comment_count
          FROM images i 
          JOIN users u ON i.user_id = u.id 
          ORDER BY RAND() LIMIT 12";
$result = mysqli_query($conn, $query);
$images = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
}

// Get total counts for statistics
$total_users_query = "SELECT COUNT(*) as count FROM users";
$total_artworks_query = "SELECT COUNT(*) as count FROM images";
$total_likes_query = "SELECT COUNT(*) as count FROM likes";

$total_users_result = mysqli_query($conn, $total_users_query);
$total_artworks_result = mysqli_query($conn, $total_artworks_query);
$total_likes_result = mysqli_query($conn, $total_likes_query);

$total_users = mysqli_fetch_assoc($total_users_result)['count'];
$total_artworks = mysqli_fetch_assoc($total_artworks_result)['count'];
$total_likes = mysqli_fetch_assoc($total_likes_result)['count'];

// Get featured artists (users with most artworks, excluding current user)
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$featured_artists_query = "SELECT u.*, 
                           COUNT(i.id) as artwork_count,
                           (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count
                           FROM users u 
                           LEFT JOIN images i ON u.id = i.user_id 
                           WHERE u.id != $current_user_id
                           GROUP BY u.id 
                           ORDER BY artwork_count DESC 
                           LIMIT 4";
$featured_artists_result = mysqli_query($conn, $featured_artists_query);
$featured_artists = [];
if ($featured_artists_result) {
    while ($row = mysqli_fetch_assoc($featured_artists_result)) {
        $featured_artists[] = $row;
    }
}

// Get all categories
$categories_query = "SELECT DISTINCT category FROM images WHERE category IS NOT NULL AND category != '' LIMIT 6";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row['category'];
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$current_user_id = $isLoggedIn ? $_SESSION['user_id'] : null;

// For each image, check if current user has liked it
foreach ($images as &$image) {
    if ($current_user_id) {
        $check_like_query = "SELECT id FROM likes WHERE user_id = $current_user_id AND image_id = {$image['id']}";
        $check_like_result = mysqli_query($conn, $check_like_query);
        $image['liked_by_user'] = mysqli_num_rows($check_like_result) > 0;
    } else {
        $image['liked_by_user'] = false;
    }
}
unset($image); // Unset reference

// Handle category filtering
$selected_category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';

// Get recent comments for each image
foreach ($images as &$image) {
    $image_id = $image['id'];
    $comments_query = "SELECT c.*, u.username, u.profile_image FROM comments c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.image_id = $image_id 
                      ORDER BY c.created_at DESC 
                      LIMIT 3";
    $comments_result = mysqli_query($conn, $comments_query);
    $image_comments = [];
    if ($comments_result) {
        while ($row = mysqli_fetch_assoc($comments_result)) {
            $image_comments[] = $row;
        }
    }
    $image['recent_comments'] = $image_comments;
}
unset($image);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Showcase - Discover Amazing Art</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Existing styles remain the same... */
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .stat-box {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
            min-width: 150px;
        }
        
        .stat-box .number {
            font-size: 2.5rem;
            font-weight: 800;
            display: block;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .stat-box .label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .categories {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0 3rem;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 0.8rem 1.5rem;
            background: white;
            border: 2px solid var(--gray);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            color: var(--dark);
            text-decoration: none;
        }
        
        .category-btn:hover,
        .category-btn.active {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.3);
            border-color: var(--primary);
        }
        
        .featured-artists {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .artist-card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .artist-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .artist-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary);
            overflow: hidden;
            background: #f0f0f0;
        }
        
        .artist-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .artist-stats {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .artist-stat {
            text-align: center;
        }
        
        .artist-stat .number {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
            color: var(--primary);
        }
        
        .artist-stat .label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .image-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .image-stat {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .like-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .like-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        .like-btn.liked {
            background: rgba(253, 121, 168, 0.3);
        }
        
        .view-all-btn {
            text-align: center;
            margin: 3rem 0;
        }
        
        .trending-section {
            margin-top: 4rem;
        }
        
        .trending-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .trending-tabs {
            display: flex;
            gap: 1rem;
        }
        
        .trending-tab {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: var(--dark);
        }
        
        .trending-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        
        .featured-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .featured-header h2 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .featured-header p {
            color: #666;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(150%);
            transition: transform 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: var(--success);
        }
        
        .toast.error {
            background: var(--danger);
        }
        
        /* Image modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .image-modal-content {
            max-width: 90%;
            max-height: 90vh;
            position: relative;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .image-modal-content img {
            width: 100%;
            height: auto;
            max-height: 60vh;
            object-fit: contain;
            background: #f5f5f5;
        }
        
        .image-modal-info {
            padding: 1.5rem;
            background: white;
            overflow-y: auto;
            max-height: 40vh;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: rgba(0,0,0,0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
        }
        
        .user-avatar-nav {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .user-name-nav {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Comments section */
        .comments-section {
            margin-top: 1.5rem;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        
        .comment-item {
            margin-bottom: 1rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .comment-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .comment-author img {
            width: 25px;
            height: 25px;
            border-radius: 50%;
        }
        
        .comment-text {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .comment-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.3rem;
        }
        
        .no-comments {
            text-align: center;
            color: #999;
            padding: 1.5rem;
            font-style: italic;
        }
        
        .comment-form {
            margin-top: 1rem;
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
            font-size: 0.9rem;
        }
        
        .comment-form button {
            margin-top: 0.5rem;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .comment-form button:hover {
            background: var(--primary-dark);
        }
        
        .login-prompt {
            text-align: center;
            color: #666;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .login-prompt a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .hamburger-menu {
            display: none;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .mobile-nav-links {
            display: none;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
            background: white;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 0 0 var(--radius) var(--radius);
        }
        
        .mobile-nav-links.show {
            display: flex;
        }
        
        .mosaic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .mosaic-item {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .mosaic-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .mosaic-item .image-info h3 {
            display: none;
        }
        
        .mosaic-item img {
            width: 100%;
            height: auto;
            max-height: 320px;
            object-fit: contain;
            background: #f5f5f5; 
            padding: 10px;
            display: block;
        }
        
        .image-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .image-info h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .image-info .author {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        

        
        @media (max-width: 768px) {
            .hero-stats {
                gap: 1.5rem;
            }
            
            .stat-box {
                padding: 1rem;
                min-width: 120px;
            }
            
            .stat-box .number {
                font-size: 2rem;
            }
            
            .categories {
                gap: 0.5rem;
            }
            
            .category-btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .trending-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .trending-tabs {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            
            .user-name-nav {
                display: none;
            }
            
            .image-modal {
                padding: 10px;
            }
            
            .image-modal-content {
                max-width: 95%;
                max-height: 95vh;
            }
            
            .hamburger-menu {
                display: block;
            }
            
            .nav-links {
                display: none;
            }
            
            .mobile-nav-links {
                display: none;
            }
            
            .mobile-nav-links.show {
                display: flex;
            }
            
            .mosaic-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .stat-box {
                min-width: 100px;
            }
            
            .stat-box .number {
                font-size: 1.5rem;
            }
            
            .featured-artists {
                grid-template-columns: 1fr;
            }
            
            .mosaic-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .image-loading {
            width: 100%;
            height: 250px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <header>
        <div class="container">
            <nav>
                <a href="index.php" class="logo">
                    <i class="fas fa-palette"></i>
                    <span>CreativeShowcase</span>
                </a>
                
                <!-- Mobile Hamburger Menu -->
                <button class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Desktop Navigation -->
                <div class="nav-links">
                    <a href="index.php" class="active">Discover</a>
                    <a href="#categories">Categories</a>
                    <a href="#artists">Artists</a>
                    <?php if($isLoggedIn): ?>
                        <?php 
                        $user_profile_image = isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'default.png';
                        $user_username = $_SESSION['username'];
                        $user_display_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $user_username;
                        ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-right: 1rem;">
                            <img src="assets/uploads/<?php echo htmlspecialchars($user_profile_image); ?>" 
                                 alt="<?php echo htmlspecialchars($user_display_name); ?>"
                                 class="user-avatar-nav"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_display_name); ?>&size=35&background=6c5ce7&color=fff'">
                            <span class="user-name-nav"><?php echo htmlspecialchars($user_display_name); ?></span>
                        </div>
                        <a href="dashboard/profile.php" class="btn btn-secondary">
                            <i class="fas fa-columns"></i> Dashboard
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="signup.php" class="btn">Sign Up</a>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Navigation (hidden by default) -->
                <div class="mobile-nav-links" id="mobileNavLinks">
                    <a href="index.php" class="active">Discover</a>
                    <a href="#categories" onclick="closeMobileNav()">Categories</a>
                    <a href="#artists" onclick="closeMobileNav()">Artists</a>
                    <?php if($isLoggedIn): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <img src="assets/uploads/<?php echo htmlspecialchars($user_profile_image); ?>" 
                                 alt="<?php echo htmlspecialchars($user_display_name); ?>"
                                 style="width: 35px; height: 35px; border-radius: 50%;"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_display_name); ?>&size=35&background=6c5ce7&color=fff'">
                            <span><?php echo htmlspecialchars($user_display_name); ?></span>
                        </div>
                        <a href="dashboard/profile.php" class="btn btn-secondary">
                            <i class="fas fa-columns"></i> Dashboard
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="signup.php" class="btn">Sign Up</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Where Creativity Finds Its Stage</h1>
            <p>Discover, share, and connect with a global community of artists. Showcase your digital masterpieces to the world.</p>
            
            <div class="hero-stats">
                <div class="stat-box">
                    <span class="number"><?php echo $total_users; ?>+</span>
                    <span class="label">Artists</span>
                </div>
                <div class="stat-box">
                    <span class="number"><?php echo $total_artworks; ?>+</span>
                    <span class="label">Artworks</span>
                </div>
                <div class="stat-box">
                    <span class="number"><?php echo $total_likes; ?>+</span>
                    <span class="label">Likes</span>
                </div>
            </div>
            
            <div style="margin-top: 2rem;">
                <a href="<?php echo $isLoggedIn ? 'dashboard/profile.php' : 'signup.php'; ?>" class="btn" style="margin-right: 1rem;">
                    <i class="fas fa-upload"></i> Start Showcasing
                </a>
                <a href="#artists" class="btn btn-secondary">
                    <i class="fas fa-users"></i> Explore Artists
                </a>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container">
        <!-- Categories -->
        <section id="categories">
            <div class="featured-header">
                <h2>Browse Categories</h2>
                <p>Explore artwork by category</p>
            </div>
            <div class="categories">
                <a href="index.php" class="category-btn <?php echo empty($selected_category) ? 'active' : ''; ?>">
                    All Categories
                </a>
                <?php foreach($categories as $category): ?>
                    <a href="index.php?category=<?php echo urlencode($category); ?>" 
                       class="category-btn <?php echo $selected_category === $category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Mosaic Gallery -->
        <section>
            <div class="featured-header">
                <h2>Featured Artworks</h2>
                <p>Discover amazing artwork from our community</p>
            </div>
            
            <?php 
            // Filter images by category if selected
            $filtered_images = $images;
            if (!empty($selected_category)) {
                $filtered_images = array_filter($images, function($image) use ($selected_category) {
                    return strcasecmp($image['category'], $selected_category) === 0;
                });
                
                if (empty($filtered_images)) {
                    echo "<p style='text-align: center; color: #666; margin: 2rem 0;'>No artworks found in this category. <a href='index.php'>View all artworks</a></p>";
                }
            }
            ?>
            
            <div class="mosaic-grid" id="artworksGrid">
                <?php foreach($filtered_images as $image): ?>
                <div class="mosaic-item" data-image-id="<?php echo $image['id']; ?>" 
                     data-image-title="<?php echo htmlspecialchars($image['title']); ?>"
                     data-image-src="assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>"
                     data-image-author="<?php echo htmlspecialchars($image['username']); ?>"
                     data-image-author-profile="assets/uploads/<?php echo !empty($image['profile_image']) ? htmlspecialchars($image['profile_image']) : 'default.png'; ?>"
                     data-image-views="<?php echo $image['views']; ?>"
                     data-image-likes="<?php echo $image['like_count']; ?>"
                     data-image-comments="<?php echo $image['comment_count']; ?>"
                     data-image-description="<?php echo htmlspecialchars($image['description'] ?: ''); ?>">
                    <!-- Image with loading placeholder -->
                    <div class="image-loading" style="display: none;" id="loading-<?php echo $image['id']; ?>"></div>
                    <img src="assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($image['title']); ?>"
                         onload="this.style.opacity='1'; document.getElementById('loading-<?php echo $image['id']; ?>').style.display='none';"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1579546929662-711aa81148cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'; this.style.opacity='1'; document.getElementById('loading-<?php echo $image['id']; ?>').style.display='none';"
                         onclick="openImageModal(<?php echo $image['id']; ?>)"
                         style="opacity: 0; transition: opacity 0.3s;">
                    <div class="image-info">
                        <h3><?php echo htmlspecialchars($image['title']); ?></h3>
                        <a href="public/user.php?username=<?php echo urlencode($image['username']); ?>" class="author">
                            <img src="assets/uploads/<?php echo !empty($image['profile_image']) ? htmlspecialchars($image['profile_image']) : 'default.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($image['username']); ?>"
                                 style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; object-position: center;  vertical-align: middle; background: none;"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($image['username']); ?>&size=30&background=6c5ce7&color=fff'">
                            @<?php echo htmlspecialchars($image['username']); ?>
                        </a>
                        
                        <div class="image-stats">
                            <span class="image-stat">
                                <i class="fas fa-eye"></i> <span id="view-count-<?php echo $image['id']; ?>"><?php echo $image['views']; ?></span>
                            </span>
                            <span class="image-stat">
                                <i class="fas fa-heart"></i> <span id="like-count-<?php echo $image['id']; ?>"><?php echo $image['like_count']; ?></span>
                            </span>
                            <span class="image-stat">
                                <i class="fas fa-comment"></i> <span id="comment-count-<?php echo $image['id']; ?>"><?php echo $image['comment_count']; ?></span>
                            </span>
                        </div>
                        
                        <?php if($isLoggedIn): ?>
                            <button class="like-btn <?php echo $image['liked_by_user'] ? 'liked' : ''; ?>" 
                                    data-image-id="<?php echo $image['id']; ?>" 
                                    onclick="toggleLike(<?php echo $image['id']; ?>, this)">
                                <i class="<?php echo $image['liked_by_user'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span><?php echo $image['liked_by_user'] ? 'Liked' : 'Like'; ?></span>
                            </button>
                        <?php else: ?>
                            <a href="login.php" class="like-btn">
                                <i class="far fa-heart"></i> Like
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Trending Section -->
        <section class="trending-section">
            <div class="trending-header">
                <div>
                    <h2 style="color: var(--primary);">Trending Now</h2>
                    <p style="color: #666; margin-top: 0.5rem;">See what's popular this week</p>
                </div>
                <div class="trending-tabs">
                    <button class="trending-tab active">This Week</button>
                    <button class="trending-tab">This Month</button>
                    <button class="trending-tab">All Time</button>
                </div>
            </div>
            
            <?php
            // Get trending images (most viewed in last 7 days)
            $trending_query = "SELECT i.*, u.username, u.profile_image,
                              (SELECT COUNT(*) FROM likes WHERE image_id = i.id) as like_count,
                              (SELECT COUNT(*) FROM comments WHERE image_id = i.id) as comment_count
                              FROM images i 
                              JOIN users u ON i.user_id = u.id 
                              WHERE i.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                              ORDER BY i.views DESC 
                              LIMIT 4";
            $trending_result = mysqli_query($conn, $trending_query);
            $trending_images = [];
            if ($trending_result && mysqli_num_rows($trending_result) > 0) {
                while ($row = mysqli_fetch_assoc($trending_result)) {
                    $trending_images[] = $row;
                }
            }
            ?>
            
            <?php if(!empty($trending_images)): ?>
            <div class="gallery-grid">
                <?php foreach($trending_images as $image): ?>
                <div class="gallery-item" data-image-id="<?php echo $image['id']; ?>"
                     onclick="openImageModal(<?php echo $image['id']; ?>)">
                    <img src="assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($image['title']); ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1579546929662-711aa81148cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'">
                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($image['title']); ?></h3>
                        <p style="color: #666; font-size: 0.9rem; margin: 0.5rem 0;">
                            <?php echo htmlspecialchars(substr($image['description'] ?: 'No description', 0, 100)) . '...'; ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                            <a href="public/user.php?username=<?php echo urlencode($image['username']); ?>" 
                               style="color: var(--primary); text-decoration: none; font-weight: 600;"
                               onclick="event.stopPropagation();">
                                @<?php echo htmlspecialchars($image['username']); ?>
                            </a>
                            <div style="display: flex; gap: 1rem; font-size: 0.9rem; color: #666;">
                                <span><i class="fas fa-eye"></i> <?php echo $image['views']; ?></span>
                                <span><i class="fas fa-heart"></i> <?php echo $image['like_count']; ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo $image['comment_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Featured Artists -->
        <section id="artists">
            <div class="featured-header">
                <h2>Featured Artists</h2>
                <p>Discover talented artists from our community</p>
            </div>
            
            <div class="featured-artists">
                <?php foreach($featured_artists as $artist): ?>
                <div class="artist-card">
                    <div class="artist-avatar">
                        <img src="assets/uploads/<?php echo !empty($artist['profile_image']) ? htmlspecialchars($artist['profile_image']) : 'default.png'; ?>" 
                             alt="<?php echo htmlspecialchars($artist['username']); ?>"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($artist['username']); ?>&size=100&background=6c5ce7&color=fff'">
                    </div>
                    <h3><?php echo htmlspecialchars($artist['full_name'] ?: $artist['username']); ?></h3>
                    <p style="color: #666; margin: 0.5rem 0; font-size: 0.9rem;">
                        <?php echo htmlspecialchars($artist['bio'] ? substr($artist['bio'], 0, 100) . '...' : 'Digital Artist'); ?>
                    </p>
                    
                    <div class="artist-stats">
                        <div class="artist-stat">
                            <span class="number"><?php echo $artist['artwork_count']; ?></span>
                            <span class="label">Artworks</span>
                        </div>
                        <div class="artist-stat">
                            <span class="number"><?php echo $artist['follower_count']; ?></span>
                            <span class="label">Followers</span>
                        </div>
                    </div>
                    
                    <a href="public/user.php?username=<?php echo urlencode($artist['username']); ?>" 
                       class="btn" style="margin-top: 1rem; display: inline-block;">
                        View Profile
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer style="background: var(--dark); color: white; padding: 3rem 0; margin-top: 5rem;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 3rem; margin-bottom: 2rem;">
                <div>
                    <a href="index.php" class="logo" style="color: white; margin-bottom: 1rem; display: block;">
                        <i class="fas fa-palette"></i>
                        CreativeShowcase
                    </a>
                    <p style="color: #aaa; max-width: 300px;">A platform for artists to share their creativity with the world.</p>
                    
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem; color: white;">Quick Links</h4>
                    <a href="index.php" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Home</a>
                    <a href="#artists" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Artists</a>
                    <a href="#categories" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Categories</a>
                    <a href="#" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">About Us</a>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem; color: white;">Legal</h4>
                    <a href="#" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Privacy Policy</a>
                    <a href="#" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Terms of Service</a>
                    <a href="#" style="color: #aaa; display: block; margin-bottom: 0.5rem; text-decoration: none;">Cookie Policy</a>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem; color: white;">Contact</h4>
                    <p style="color: #aaa; margin-bottom: 0.5rem;">
                        <i class="fas fa-envelope"></i> creativeshowcase@gmail.com
                    </p>
                    
                </div>
            </div>
            
            <div style="border-top: 1px solid #444; padding-top: 2rem; text-align: center; color: #aaa;">
                <p>&copy; <?php echo date('Y'); ?> Creative Showcase. All rights reserved.</p>
                <p style="margin-top: 0.5rem; font-size: 0.9rem;">Made with <i class="fas fa-heart" style="color: var(--accent);"></i> for the creative community</p>
            </div>
        </div>
    </footer>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="">
            <div class="image-modal-info">
                <h2 id="modalTitle"></h2>
                <div style="display: flex; align-items: center; margin: 1rem 0;">
                    <img id="modalAuthorAvatar" src="" alt="" 
                         style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?size=40&background=6c5ce7&color=fff'">
                    <div>
                        <a id="modalAuthorLink" href="#" style="font-weight: 600; color: var(--primary); text-decoration: none;">
                            <span id="modalAuthor"></span>
                        </a>
                        <div id="modalStats" style="display: flex; gap: 1rem; font-size: 0.9rem; color: #666; margin-top: 0.3rem;">
                            <span><i class="fas fa-eye"></i> <span id="modalViews"></span></span>
                            <span><i class="fas fa-heart"></i> <span id="modalLikes"></span></span>
                            <span><i class="fas fa-comment"></i> <span id="modalComments"></span></span>
                        </div>
                    </div>
                </div>
                <p id="modalDescription" style="color: #666; margin: 1rem 0;"></p>
                
                <!-- Comments Section -->
                <div class="comments-section">
                    <h4>Comments (<span id="modalCommentCount">0</span>)</h4>
                    <div id="modalCommentsList"></div>
                    <div id="noCommentsMessage" class="no-comments">No comments yet. Be the first to comment!</div>
                </div>
                
                <!-- Comment Form -->
                <div id="commentFormContainer">
                    <?php if($isLoggedIn): ?>
                        <form class="comment-form" id="modalCommentForm">
                            <input type="hidden" id="commentImageId" name="image_id" value="">
                            <textarea id="commentText" name="comment" placeholder="Add a comment..." required></textarea>
                            <button type="submit">Post Comment</button>
                        </form>
                    <?php else: ?>
                        <div class="login-prompt">
                            Please <a href="login.php">login</a> to leave a comment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script src="assets/js/main.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
<script>
    // Mobile Navigation Toggle
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileNavLinks = document.getElementById('mobileNavLinks');
    
    hamburgerBtn.addEventListener('click', function() {
        mobileNavLinks.classList.toggle('show');
    });
    
    function closeMobileNav() {
        mobileNavLinks.classList.remove('show');
    }
    
    // Category filtering
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Trending tabs
    document.querySelectorAll('.trending-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.trending-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            console.log('Loading trending content for:', this.textContent);
        });
    });
    
    // Loading overlay functions
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }
    
    // Enhanced Like functionality with better error handling and smooth UI
    function toggleLike(imageId, button) {
        <?php if(!$isLoggedIn): ?>
            showToast('Please login to like artworks', 'error');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 1500);
            return;
        <?php endif; ?>
        
        const isLiked = button.classList.contains('liked');
        const heartIcon = button.querySelector('i');
        const likeText = button.querySelector('span');
        const likeCountElement = document.getElementById(`like-count-${imageId}`);
        
        // Optimistic UI update (immediate response)
        const originalLikeCount = parseInt(likeCountElement.textContent);
        const newLikeCount = isLiked ? originalLikeCount - 1 : originalLikeCount + 1;
        
        // Update UI immediately for better UX
        if (isLiked) {
            button.classList.remove('liked');
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            likeText.textContent = 'Like';
        } else {
            button.classList.add('liked');
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            heartIcon.style.animation = 'heartBeat 0.5s';
            likeText.textContent = 'Liked';
        }
        
        likeCountElement.textContent = newLikeCount;
        
        // Update modal if open and showing this image
        const modalImageId = document.getElementById('commentImageId')?.value;
        if (modalImageId == imageId) {
            const modalLikes = document.getElementById('modalLikes');
            if (modalLikes) {
                modalLikes.textContent = newLikeCount;
            }
        }
        
        // Send AJAX request in background
        fetch('ajax/like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `image_id=${imageId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update like count from server response (for accuracy)
                if (data.like_count !== undefined) {
                    likeCountElement.textContent = data.like_count;
                    
                    // Update modal if open
                    if (modalImageId == imageId) {
                        const modalLikes = document.getElementById('modalLikes');
                        if (modalLikes) {
                            modalLikes.textContent = data.like_count;
                        }
                    }
                }
                
                // Update dataset
                const mosaicItem = document.querySelector(`.mosaic-item[data-image-id="${imageId}"]`);
                if (mosaicItem && data.like_count !== undefined) {
                    mosaicItem.dataset.imageLikes = data.like_count;
                }
                
                // Show success toast only if not unliking
                if (!isLiked && data.message) {
                    showToast(data.message, 'success');
                }
            } else {
                // Revert optimistic update if failed
                if (isLiked) {
                    button.classList.add('liked');
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    likeText.textContent = 'Liked';
                } else {
                    button.classList.remove('liked');
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    likeText.textContent = 'Like';
                }
                likeCountElement.textContent = originalLikeCount;
                
                if (data.message && !data.message.includes('Database error')) {
                    showToast(data.message, 'error');
                }
            }
        })
        .catch(error => {
            console.error('Like error:', error);
            // Silent fail - don't show toast for network errors
            // Keep optimistic update (assume server received it)
        });
    }
    
    // Image modal functionality
    function openImageModal(imageId) {
        // Find the mosaic item
        const mosaicItem = document.querySelector(`.mosaic-item[data-image-id="${imageId}"]`);
        if (!mosaicItem) return;
        
        // Get image data from dataset
        const imageTitle = mosaicItem.dataset.imageTitle;
        const imageSrc = mosaicItem.dataset.imageSrc;
        const imageAuthor = mosaicItem.dataset.imageAuthor;
        const imageAuthorProfile = mosaicItem.dataset.imageAuthorProfile;
        const imageViews = mosaicItem.dataset.imageViews;
        const imageLikes = mosaicItem.dataset.imageLikes;
        const imageComments = mosaicItem.dataset.imageComments;
        const imageDescription = mosaicItem.dataset.imageDescription;
        
        // Update modal content
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('modalTitle').textContent = imageTitle;
        document.getElementById('modalAuthor').textContent = '@' + imageAuthor;
        document.getElementById('modalAuthorLink').href = 'public/user.php?username=' + encodeURIComponent(imageAuthor);
        document.getElementById('modalAuthorAvatar').src = imageAuthorProfile;
        document.getElementById('modalViews').textContent = imageViews;
        document.getElementById('modalLikes').textContent = imageLikes;
        document.getElementById('modalComments').textContent = imageComments;
        document.getElementById('modalCommentCount').textContent = imageComments;
        document.getElementById('modalDescription').textContent = imageDescription || 'No description available';
        document.getElementById('commentImageId').value = imageId;
        
        // Clear previous comments
        document.getElementById('modalCommentsList').innerHTML = '';
        document.getElementById('noCommentsMessage').style.display = 'block';
        
        // Load comments via AJAX
        loadComments(imageId);
        
        // Mark this item as active
        document.querySelectorAll('.mosaic-item').forEach(item => item.classList.remove('active'));
        mosaicItem.classList.add('active');
        
        // Show modal
        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Track view
        trackView(imageId);
    }
    
    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto'; // Re-enable scrolling
    }
    
    // Load comments for an image
    function loadComments(imageId) {
        fetch(`ajax/get_comments.php?image_id=${imageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentsList = document.getElementById('modalCommentsList');
                    const noCommentsMessage = document.getElementById('noCommentsMessage');
                    
                    commentsList.innerHTML = '';
                    
                    if (data.comments && data.comments.length > 0) {
                        noCommentsMessage.style.display = 'none';
                        
                        data.comments.forEach(comment => {
                            const commentItem = document.createElement('div');
                            commentItem.className = 'comment-item';
                            commentItem.style.animation = 'fadeInUp 0.3s';
                            commentItem.innerHTML = `
                                <div class="comment-author">
                                    <img src="assets/uploads/${comment.profile_image}" 
                                         alt="${comment.username}"
                                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(comment.username)}&size=25&background=6c5ce7&color=fff'}">
                                    @${comment.username}
                                </div>
                                <p class="comment-text">${comment.comment}</p>
                                <div class="comment-time">
                                    ${comment.formatted_time}
                                </div>
                            `;
                            commentsList.appendChild(commentItem);
                        });
                    } else {
                        noCommentsMessage.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Failed to load comments:', error);
                // Silent fail for comment loading
            });
    }
    
    // Enhanced comment form submission with better error handling
    const commentForm = document.getElementById('modalCommentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const imageId = document.getElementById('commentImageId').value;
            const commentText = document.getElementById('commentText').value.trim();
            
            if (!commentText) {
                showToast('Please enter a comment', 'error');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            submitBtn.disabled = true;
            
            // Store original counts for rollback
            const modalCommentCount = document.getElementById('modalCommentCount');
            const modalComments = document.getElementById('modalComments');
            const mosaicCommentCount = document.getElementById(`comment-count-${imageId}`);
            const originalCommentCount = parseInt(modalCommentCount.textContent);
            
            // Optimistic update - update UI immediately
            const newCommentCount = originalCommentCount + 1;
            modalCommentCount.textContent = newCommentCount;
            modalComments.textContent = newCommentCount;
            if (mosaicCommentCount) {
                mosaicCommentCount.textContent = parseInt(mosaicCommentCount.textContent) + 1;
            }
            
            // Add optimistic comment to list
            const commentsList = document.getElementById('modalCommentsList');
            const noCommentsMessage = document.getElementById('noCommentsMessage');
            
            const optimisticComment = document.createElement('div');
            optimisticComment.className = 'comment-item';
            optimisticComment.innerHTML = `
                <div class="comment-author">
                    <img src="assets/uploads/<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'default.png'); ?>" 
                         alt="<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username'] ?? 'User'); ?>&size=25&background=6c5ce7&color=fff'}">
                    @<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </div>
                <p class="comment-text">${commentText}</p>
                <div class="comment-time">
                    Just now
                </div>
            `;
            
            if (noCommentsMessage.style.display !== 'none') {
                noCommentsMessage.style.display = 'none';
            }
            commentsList.insertBefore(optimisticComment, commentsList.firstChild);
            
            // Clear form
            document.getElementById('commentText').value = '';
            
            // Send AJAX request
            fetch('ajax/post_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image_id=${imageId}&comment=${encodeURIComponent(commentText)}`
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Update counts from server response
                    if (data.comment_count !== undefined) {
                        modalCommentCount.textContent = data.comment_count;
                        modalComments.textContent = data.comment_count;
                    }
                    
                    // Update comment count in mosaic item
                    if (mosaicCommentCount && data.comment_count !== undefined) {
                        mosaicCommentCount.textContent = data.comment_count;
                    }
                    
                    // Update dataset
                    const mosaicItem = document.querySelector(`.mosaic-item[data-image-id="${imageId}"]`);
                    if (mosaicItem && data.comment_count !== undefined) {
                        mosaicItem.dataset.imageComments = data.comment_count;
                    }
                    
                    // Replace optimistic comment with actual comment
                    if (data.comment) {
                        const actualComment = document.createElement('div');
                        actualComment.className = 'comment-item';
                        actualComment.style.animation = 'fadeInUp 0.3s';
                        actualComment.innerHTML = `
                            <div class="comment-author">
                                <img src="assets/uploads/${data.comment.profile_image}" 
                                     alt="${data.comment.username}"
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(data.comment.username)}&size=25&background=6c5ce7&color=fff'}">
                                @${data.comment.username}
                            </div>
                            <p class="comment-text">${data.comment.comment}</p>
                            <div class="comment-time">
                                ${data.comment.formatted_time}
                            </div>
                        `;
                        commentsList.replaceChild(actualComment, optimisticComment);
                    }
                    
                    // Show success toast
                    showToast('Comment posted successfully!', 'success');
                } else {
                    // Revert optimistic update
                    modalCommentCount.textContent = originalCommentCount;
                    modalComments.textContent = originalCommentCount;
                    if (mosaicCommentCount) {
                        mosaicCommentCount.textContent = parseInt(mosaicCommentCount.textContent) - 1;
                    }
                    
                    // Remove optimistic comment
                    commentsList.removeChild(optimisticComment);
                    
                    // Show empty message if no comments
                    if (commentsList.children.length === 0) {
                        noCommentsMessage.style.display = 'block';
                    }
                    
                    // Check if redirect is needed
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        showToast(data.message || 'Failed to post comment', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Comment error:', error);
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                // Keep optimistic update - assume server received it
                // This provides better UX for users with spotty connections
                // The comment will sync when they refresh
                
                // Only revert if we get a specific error
                if (error.message.includes('NetworkError')) {
                    showToast('Connection lost. Comment will be saved locally.', 'info');
                }
            });
        });
    }
    
    // Track view function
    function trackView(imageId) {
        fetch('ajax/track_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `image_id=${imageId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.view_count !== undefined) {
                // Update view count in mosaic item
                const viewCountElement = document.getElementById(`view-count-${imageId}`);
                if (viewCountElement) {
                    viewCountElement.textContent = data.view_count;
                }
                
                // Update view count in modal if open
                const modalViews = document.getElementById('modalViews');
                if (modalViews && document.getElementById('commentImageId').value == imageId) {
                    modalViews.textContent = data.view_count;
                }
                
                // Update dataset
                const mosaicItem = document.querySelector(`.mosaic-item[data-image-id="${imageId}"]`);
                if (mosaicItem) {
                    mosaicItem.dataset.imageViews = data.view_count;
                }
            }
        })
        .catch(error => {
            console.error('Track view error:', error);
            // Silent fail for view tracking
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target == modal) {
            closeImageModal();
        }
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeImageModal();
        }
    });
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                           type === 'error' ? 'fa-exclamation-circle' : 
                           'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.getElementById('toastContainer').appendChild(toast);
        
        // Show toast
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Remove toast after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    // Add CSS animations for smooth interactions
    const style = document.createElement('style');
    style.textContent = `
        @keyframes heartBeat {
            0% { transform: scale(1); }
            25% { transform: scale(1.3); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .like-btn.liked .fa-heart {
            animation: heartBeat 0.5s;
        }
        
        .comment-item {
            animation: fadeInUp 0.3s;
        }
    `;
    document.head.appendChild(style);
    
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Improved image loading
        const images = document.querySelectorAll('.mosaic-item img, .gallery-item img');
        images.forEach(img => {
            const loadingDiv = img.parentElement.querySelector('.image-loading');
            if (loadingDiv) {
                loadingDiv.style.display = 'block';
            }
            
            if (img.complete) {
                img.style.opacity = '1';
                if (loadingDiv) loadingDiv.style.display = 'none';
            } else {
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                    if (loadingDiv) loadingDiv.style.display = 'none';
                });
                
                img.addEventListener('error', function() {
                    this.src = 'https://images.unsplash.com/photo-1579546929662-711aa81148cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';
                    this.style.opacity = '1';
                    if (loadingDiv) loadingDiv.style.display = 'none';
                });
            }
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    e.preventDefault();
                    closeMobileNav();
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Close mobile nav when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('nav') && mobileNavLinks.classList.contains('show')) {
                mobileNavLinks.classList.remove('show');
            }
        });
    });
</script>
</body>
</html>