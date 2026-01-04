<?php
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user info
$query = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Get user's images with likes count
$query = "SELECT i.*, 
          (SELECT COUNT(*) FROM likes WHERE image_id = i.id) as like_count
          FROM images i 
          WHERE i.user_id = $user_id 
          ORDER BY i.uploaded_at DESC";
$result = mysqli_query($conn, $query);
$images = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
}

// Calculate statistics
$total_views = 0;
$total_likes = 0;
foreach ($images as $image) {
    $total_views += $image['views'];
    $total_likes += $image['like_count'];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($conn, $_POST['username']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    // $current_password = $_POST['current_password'];
    // $new_password = $_POST['new_password'];
    // $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    $success = '';
    
    // Check if username is being changed
    if ($new_username !== $user['username']) {
        // Check if new username already exists
        $check_username = "SELECT id FROM users WHERE username = '$new_username' AND id != $user_id";
        $result = mysqli_query($conn, $check_username);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'Username already taken';
        }
    }
    
    // Handle profile image upload
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed)) {
            if ($_FILES['profile_image']['size'] <= 2097152) { // 2MB
                // Generate unique filename
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../assets/uploads/' . $new_filename;
                
                // Delete old profile image if exists
                if ($profile_image && file_exists('../assets/uploads/' . $profile_image) && $profile_image != 'default.png') {
                    unlink('../assets/uploads/' . $profile_image);
                }
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    $profile_image = $new_filename;
                } else {
                    $errors[] = 'Failed to upload profile image';
                }
            } else {
                $errors[] = 'Profile image must be less than 2MB';
            }
        } else {
            $errors[] = 'Only JPG, PNG, and GIF files are allowed';
        }
    }
    
    // Handle password change
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        // Build update query
        $update_fields = [
            "username = '$new_username'",
            "full_name = '$full_name'",
            "bio = '$bio'",
            "profile_image = '$profile_image'"
        ];
        
        if (isset($hashed_password)) {
            $update_fields[] = "password = '$hashed_password'";
        }
        
        $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = $user_id";
        
        if (mysqli_query($conn, $update_query)) {
            // Update session
            $_SESSION['username'] = $new_username;
            
            // Refresh user data
            $query = "SELECT * FROM users WHERE id = $user_id";
            $result = mysqli_query($conn, $query);
            $user = mysqli_fetch_assoc($result);
            
            $success = 'Profile updated successfully!';
        } else {
            $errors[] = 'Failed to update profile';
        }
    }
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['artwork_image'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // File upload
    $file = $_FILES['artwork_image'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($file_ext, $allowed)) {
        if ($file['error'] === 0) {
            if ($file['size'] <= 5242880) { // 5MB
                $new_filename = uniqid('', true) . '.' . $file_ext;
                $upload_path = '../assets/uploads/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $query = "INSERT INTO images (user_id, title, description, image_path, category) 
                              VALUES ('$user_id', '$title', '$description', '$new_filename', '$category')";
                    
                    if (mysqli_query($conn, $query)) {
                        header('Location: profile.php');
                        exit;
                    }
                }
            }
        }
    }
}

// Handle image edit (AJAX or form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_image'])) {
    $image_id = intval($_POST['image_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // Check if user owns this image
    $check_query = "SELECT id FROM images WHERE id = $image_id AND user_id = $user_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $update_query = "UPDATE images SET title = '$title', description = '$description', category = '$category' 
                         WHERE id = $image_id";
        mysqli_query($conn, $update_query);
        header('Location: profile.php');
        exit;
    }
}

// Handle image delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_id = intval($_POST['image_id']);
    
    // Check if user owns this image
    $check_query = "SELECT image_path FROM images WHERE id = $image_id AND user_id = $user_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $image_data = mysqli_fetch_assoc($check_result);
        $image_path = $image_data['image_path'];
        
        // Delete image file
        if (file_exists('../assets/uploads/' . $image_path)) {
            unlink('../assets/uploads/' . $image_path);
        }
        
        // Delete from database
        $delete_query = "DELETE FROM images WHERE id = $image_id AND user_id = $user_id";
        mysqli_query($conn, $delete_query);
        
        // Also delete any likes associated with this image
        $delete_likes = "DELETE FROM likes WHERE image_id = $image_id";
        mysqli_query($conn, $delete_likes);
        
        header('Location: profile.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Creative Showcase</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .sidebar {
            width: 250px;
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: fit-content;
        }
        
        .main-content {
            flex: 1;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-menu li {
            margin-bottom: 0.5rem;
        }
        
        .nav-menu a {
            display: block;
            padding: 0.8rem 1rem;
            text-decoration: none;
            color: var(--dark);
            border-radius: var(--radius);
            transition: all 0.3s;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary);
        }
        
        .nav-menu i {
            width: 20px;
            margin-right: 10px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-preview {
            text-align: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray);
            margin-bottom: 1rem;
        }
        
        .avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary);
        }
        
        .file-input-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: block;
            padding: 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            text-align: center;
            cursor: pointer;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        /* Gallery Item Styles */
        .gallery-item {
            position: relative;
            overflow: hidden;
        }
        
        .image-stats {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .image-stat {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .edit-btn {
            background: var(--primary);
            color: white;
        }
        
        .edit-btn:hover {
            background: var(--primary-dark);
        }
        
        .delete-btn {
            background: var(--danger);
            color: white;
        }
        
        .delete-btn:hover {
            background: #d45d40;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(108, 92, 231, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .stat-content h3 {
            font-size: 2rem;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-content p {
            margin: 0;
            color: #666;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        /* Image Preview in Modal */
        .image-preview {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            margin-bottom: 1rem;
            border-radius: var(--radius);
        }

/* Navbar */
.menu-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }

    .nav-links {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-top: 1rem;
    }

    .nav-links.show {
        display: flex;
    }
}

/* Dashboard layout */
@media (max-width: 992px) {
    .dashboard-container {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
    }
}

/* Sidebar menu */
@media (max-width: 768px) {
    .nav-menu {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .nav-menu li {
        flex: 1 1 48%;
    }
}

/* Gallery grid */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

@media (max-width: 576px) {
    .gallery-grid {
        grid-template-columns: 1fr;
    }
}

/* Gallery images */
.gallery-image {
    width: 100%;
    height: 220px;
    object-fit: cover;
    border-radius: var(--radius);
}

/* Modal responsiveness */
@media (max-width: 576px) {
    .modal-content {
        padding: 1.2rem;
        max-height: 90vh;
    }
}

/* Forms */
@media (max-width: 576px) {
    .form-group input,
    .form-group textarea,
    .form-group select {
        font-size: 0.95rem;
    }

    .btn {
        width: 100%;
        text-align: center;
    }
}

/* Stats cards */
@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
}

    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
    <a href="../index.php" class="logo">
        <i class="fas fa-palette"></i>
        <span>CreativeShowcase</span>
    </a>

    <button class="menu-toggle" onclick="document.querySelector('.nav-links').classList.toggle('show')">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links">
        <a href="../index.php">Home</a>
        <a href="profile.php" class="active">Dashboard</a>
        <a href="../public/user.php?username=<?php echo urlencode($username); ?>">My Public Profile</a>
        <a href="../auth/logout.php" class="btn btn-outline">Logout</a>
    </div>
</nav>

        </div>
    </header>

    <main class="container">
        <div class="dashboard-container">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="profile-preview">
                    <img src="../assets/uploads/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" 
                         alt="<?php echo htmlspecialchars($user['username']); ?>" 
                         class="avatar-large"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=100&background=6c5ce7&color=fff'">
                    <h3><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h3>
                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                
                <ul class="nav-menu">
                    <li><a href="#upload" class="nav-link active"><i class="fas fa-upload"></i> Upload Artwork</a></li>
                    <li><a href="#gallery" class="nav-link"><i class="fas fa-images"></i> My Gallery</a></li>
                    <li><a href="#profile" class="nav-link"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                    <li><a href="#stats" class="nav-link"><i class="fas fa-chart-bar"></i> Statistics</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Upload Tab -->
                <div id="upload-tab" class="tab-content active">
                    <h1>Upload New Artwork</h1>
                    <div class="upload-form">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Digital Art">Digital Art</option>
                                    <option value="Pencil Sketch">Pencil Sketch</option>
                                    <option value="Painting">Painting</option>
                                    <option value="Photography">Photography</option>
                                    <option value="Illustration">Illustration</option>
                                    <option value="3D Art">3D Art</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="artwork_image">Image</label>
                                <input type="file" id="artwork_image" name="artwork_image" accept="image/*" required>
                            </div>
                            
                            <button type="submit" class="btn">Upload Artwork</button>
                        </form>
                    </div>
                </div>

                <!-- Gallery Tab -->
                <div id="gallery-tab" class="tab-content">
                    <h1>My Gallery</h1>
                    <?php if(empty($images)): ?>
                        <div class="empty-state">
                            <i class="fas fa-images fa-3x"></i>
                            <h3>No artworks yet</h3>
                            <p>Upload your first artwork to get started!</p>
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid">
                            <?php foreach($images as $image): ?>
                            <div class="gallery-item">
                                <img src="../assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['title']); ?>"
                                     class="gallery-image">
                                <div class="item-info">
                                    <h3><?php echo htmlspecialchars($image['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($image['description']); ?></p>
                                    
                                    <div class="image-stats">
                                        <span class="image-stat">
                                            <i class="fas fa-eye"></i> <?php echo $image['views']; ?> views
                                        </span>
                                        <span class="image-stat">
                                            <i class="fas fa-heart"></i> <?php echo $image['like_count']; ?> likes
                                        </span>
                                    </div>
                                    
                                    <span class="category"><?php echo htmlspecialchars($image['category']); ?></span>
                                    <div class="item-actions">
                                        <button class="btn-small edit-btn" 
                                                onclick="openEditModal(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['title']); ?>', '<?php echo htmlspecialchars(addslashes($image['description'])); ?>', '<?php echo htmlspecialchars($image['category']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="delete_image" value="1">
                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                            <button type="submit" class="btn-small delete-btn" onclick="return confirm('Are you sure you want to delete this artwork?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Image Modal -->
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Edit Artwork</h2>
                            <button class="close-modal" onclick="closeEditModal()">&times;</button>
                        </div>
                        <form method="POST" action="" id="editForm">
                            <input type="hidden" name="edit_image" value="1">
                            <input type="hidden" name="image_id" id="editImageId">
                            
                            <div class="form-group">
                                <label for="editTitle">Title</label>
                                <input type="text" id="editTitle" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="editDescription">Description</label>
                                <textarea id="editDescription" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="editCategory">Category</label>
                                <select id="editCategory" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Digital Art">Digital Art</option>
                                    <option value="Pencil Sketch">Pencil Sketch</option>
                                    <option value="Painting">Painting</option>
                                    <option value="Photography">Photography</option>
                                    <option value="Illustration">Illustration</option>
                                    <option value="3D Art">3D Art</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn">Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Profile Edit Tab -->
                <div id="profile-tab" class="tab-content">
                    <h1>Edit Profile</h1>
                    
                    <?php if(isset($errors) && !empty($errors)): ?>
                        <div class="alert error">
                            <?php foreach($errors as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($success)): ?>
                        <div class="alert success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-form">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-group">
                                <label>Profile Picture</label>
                                <div class="file-input-wrapper">
                                    <img src="../assets/uploads/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" 
                                         alt="Current Profile Picture" 
                                         id="profileImagePreview"
                                         class="avatar-large"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=100&background=6c5ce7&color=fff'">
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                                    <label for="profile_image" class="file-input-label">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </label>
                                </div>
                                <small>Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?: ''); ?>"
                                       placeholder="Your full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>"
                                       required>
                                <small>This will change your profile URL</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" rows="4" 
                                          placeholder="Tell us about yourself"><?php echo htmlspecialchars($user['bio'] ?: ''); ?></textarea>
                                <small>Max 500 characters</small>
                            </div>
                            
                            <button type="submit" class="btn">Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Statistics Tab -->
                <div id="stats-tab" class="tab-content">
                    <h1>Statistics</h1>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-images"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo count($images); ?></h3>
                                <p>Total Artworks</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_views; ?></h3>
                                <p>Total Views</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_likes; ?></h3>
                                <p>Total Likes</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo date('M Y', strtotime($user['created_at'])); ?></h3>
                                <p>Member Since</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(!empty($images)): ?>
                    <div style="margin-top: 3rem;">
                        <h3>Most Viewed Artworks</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <?php 
                            // Sort images by views
                            usort($images, function($a, $b) {
                                return $b['views'] - $a['views'];
                            });
                            $top_images = array_slice($images, 0, 4);
                            ?>
                            <?php foreach($top_images as $image): ?>
                            <div style="background: white; border-radius: var(--radius); padding: 1rem; box-shadow: var(--shadow);">
                                <img src="../assets/uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['title']); ?>"
                                     style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius);">
                                <h4 style="margin: 0.5rem 0 0.2rem; font-size: 0.9rem;"><?php echo htmlspecialchars($image['title']); ?></h4>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #666;">
                                    <span><i class="fas fa-eye"></i> <?php echo $image['views']; ?></span>
                                    <span><i class="fas fa-heart"></i> <?php echo $image['like_count']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tab Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Show selected tab
                const tabId = this.getAttribute('href').substring(1) + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Profile Image Preview
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profileImagePreview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Password Toggle
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
        
        // Edit Modal Functions
        function openEditModal(id, title, description, category) {
            document.getElementById('editImageId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            document.getElementById('editCategory').value = category;
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Bio character counter
        const bioTextarea = document.getElementById('bio');
        if (bioTextarea) {
            const counter = document.createElement('div');
            counter.className = 'bio-counter';
            counter.textContent = `${bioTextarea.value.length}/500 characters`;
            bioTextarea.parentNode.insertBefore(counter, bioTextarea.nextSibling);
            
            bioTextarea.addEventListener('input', function() {
                counter.textContent = `${this.value.length}/500 characters`;
            });
        }
        
        // Delete confirmation
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this artwork?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Initialize gallery items with hover effects
        document.querySelectorAll('.gallery-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow)';
            });
        });
    </script>
</body>
</html>