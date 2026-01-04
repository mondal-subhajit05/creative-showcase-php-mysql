<?php
// post_comment.php

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Set header for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please login to comment',
        'redirect' => 'login.php'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check required fields
if (!isset($_POST['image_id']) || !isset($_POST['comment']) || empty($_POST['comment'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Comment cannot be empty'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$image_id = intval($_POST['image_id']);
$comment = trim($_POST['comment']);

// Validate comment length
if (strlen($comment) > 500) {
    echo json_encode([
        'success' => false, 
        'message' => 'Comment is too long (max 500 characters)'
    ]);
    exit;
}

// Escape the comment text
$comment = mysqli_real_escape_string($conn, $comment);

// Check if image exists
$check_image_query = "SELECT id FROM images WHERE id = $image_id";
$check_image_result = mysqli_query($conn, $check_image_query);

if (!$check_image_result || mysqli_num_rows($check_image_result) == 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Image not found'
    ]);
    exit;
}

// Insert comment
$insert_query = "INSERT INTO comments (image_id, user_id, comment, created_at) 
                 VALUES ($image_id, $user_id, '$comment', NOW())";

if (mysqli_query($conn, $insert_query)) {
    $comment_id = mysqli_insert_id($conn);
    
    // Get the newly inserted comment with user info
    $comment_query = "SELECT c.*, u.username, u.profile_image, u.full_name
                     FROM comments c 
                     JOIN users u ON c.user_id = u.id 
                     WHERE c.id = $comment_id";
    
    $comment_result = mysqli_query($conn, $comment_query);
    $comment_data = mysqli_fetch_assoc($comment_result);
    
    if (!$comment_data) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to retrieve comment'
        ]);
        exit;
    }
    
    // Get updated comment count
    $count_query = "SELECT COUNT(*) as comment_count FROM comments WHERE image_id = $image_id";
    $count_result = mysqli_query($conn, $count_query);
    $count_data = mysqli_fetch_assoc($count_result);
    $comment_count = $count_data['comment_count'];
    
    // Helper function to format time
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
    
    // Format the comment data
    $formatted_comment = [
        'id' => $comment_data['id'],
        'user_id' => $comment_data['user_id'],
        'username' => $comment_data['username'],
        'full_name' => $comment_data['full_name'],
        'profile_image' => $comment_data['profile_image'] ?: 'default.png',
        'comment' => htmlspecialchars($comment_data['comment']),
        'created_at' => date('M d, Y g:i A', strtotime($comment_data['created_at'])),
        'formatted_time' => time_elapsed_string($comment_data['created_at'])
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully!',
        'comment_count' => $comment_count,
        'comment' => $formatted_comment
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
}
?>