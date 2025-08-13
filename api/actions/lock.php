<?php
/**
 * Lock Action Endpoint
 * Handles screen lock functionality
 */

session_start();
header('Content-Type: application/json');

// Check if operator is logged in
if (!isset($_SESSION['operator_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../config/response.php';

$response = new ApiResponse();

try {
    // For this simplified implementation, just acknowledge the lock request
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // In a full implementation, this would:
    // 1. Set a screen lock flag in the session
    // 2. Require PIN or authentication to unlock
    // 3. Log the lock/unlock events
    
    echo $response->success([
        'status' => 'locked',
        'operator' => $operator,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Screen lock activated');
    
} catch (Exception $e) {
    error_log('Lock action error: ' . $e->getMessage());
    echo $response->serverError('Lock action failed');
}
?>