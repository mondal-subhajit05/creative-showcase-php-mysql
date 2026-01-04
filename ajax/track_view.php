<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_POST['image_id'])) {
    echo json_encode(['success' => false, 'message' => 'Image ID required']);
    exit;
}

$image_id = mysqli_real_escape_string($conn, $_POST['image_id']);

// Update view count
$update_query = "UPDATE images SET views = views + 1 WHERE id = $image_id";
if (mysqli_query($conn, $update_query)) {
    // Get updated view count
    $count_query = "SELECT views FROM images WHERE id = $image_id";
    $count_result = mysqli_query($conn, $count_query);
    $view_count = mysqli_fetch_assoc($count_result)['views'];
    
    echo json_encode(['success' => true, 'view_count' => $view_count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error tracking view']);
}
?>