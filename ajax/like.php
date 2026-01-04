<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if (!isset($_POST['image_id'])) {
    echo json_encode(['success' => false, 'message' => 'Image ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$image_id = mysqli_real_escape_string($conn, $_POST['image_id']);

// Check if user already liked the image
$check_query = "SELECT id FROM likes WHERE user_id = $user_id AND image_id = $image_id";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    // Unlike
    $delete_query = "DELETE FROM likes WHERE user_id = $user_id AND image_id = $image_id";
    if (mysqli_query($conn, $delete_query)) {
        // Update image likes count
        $update_query = "UPDATE images SET likes = likes - 1 WHERE id = $image_id";
        mysqli_query($conn, $update_query);
        
        // Get updated like count
        $count_query = "SELECT COUNT(*) as like_count FROM likes WHERE image_id = $image_id";
        $count_result = mysqli_query($conn, $count_query);
        $like_count = mysqli_fetch_assoc($count_result)['like_count'];
        
        echo json_encode(['success' => true, 'liked' => false, 'like_count' => $like_count]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error unliking']);
    }
} else {
    // Like
    $insert_query = "INSERT INTO likes (user_id, image_id) VALUES ($user_id, $image_id)";
    if (mysqli_query($conn, $insert_query)) {
        // Update image likes count
        $update_query = "UPDATE images SET likes = likes + 1 WHERE id = $image_id";
        mysqli_query($conn, $update_query);
        
        // Get updated like count
        $count_query = "SELECT COUNT(*) as like_count FROM likes WHERE image_id = $image_id";
        $count_result = mysqli_query($conn, $count_query);
        $like_count = mysqli_fetch_assoc($count_result)['like_count'];
        
        echo json_encode(['success' => true, 'liked' => true, 'like_count' => $like_count]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error liking']);
    }
}
?>