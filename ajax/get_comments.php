<?php
// get_comments.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['image_id'])) {
    echo json_encode(['success' => false, 'message' => 'Image ID required']);
    exit;
}

$image_id = intval($_GET['image_id']);

// Get comments for this image
$comments_query = "SELECT c.*, u.username, u.profile_image 
                   FROM comments c 
                   JOIN users u ON c.user_id = u.id 
                   WHERE c.image_id = $image_id 
                   ORDER BY c.created_at DESC 
                   LIMIT 10";
$comments_result = mysqli_query($conn, $comments_query);

$comments = [];
if ($comments_result && mysqli_num_rows($comments_result) > 0) {
    while ($row = mysqli_fetch_assoc($comments_result)) {
        $comments[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'profile_image' => $row['profile_image'] ?: 'default.png',
            'comment' => htmlspecialchars($row['comment']),
            'created_at' => date('M d, Y g:i A', strtotime($row['created_at'])),
            'formatted_time' => time_elapsed_string($row['created_at'])
        ];
    }
}

echo json_encode([
    'success' => true,
    'comments' => $comments
]);

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
?>