<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
// CORRECT PATH: Go up one level from api/ to config/
require_once '../config/db.php';

header('Content-Type: application/json');

// Debug: Log session info
error_log("API called - Session ID: " . session_id());
error_log("API called - User ID in session: " . ($_SESSION['user_id'] ?? 'not set'));

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    error_log("API: User not authenticated");
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please login again.']);
    exit;
}

// Validate user_id parameter
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

$user_id = intval($_GET['user_id']);
error_log("API: Fetching following for user ID: $user_id");

try {
    // Get following
    $query = "SELECT u.id, u.username, u.full_name, u.profile_image 
              FROM follows f 
              JOIN users u ON f.following_id = u.id 
              WHERE f.follower_id = ? 
              ORDER BY f.created_at DESC";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database query error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $following = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $following[] = $row;
    }

    error_log("API: Found " . count($following) . " following");
    echo json_encode([
        'success' => true, 
        'following' => $following,
        'count' => count($following)
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching following: ' . $e->getMessage()
    ]);
}

// Close connection
mysqli_close($conn);
?>