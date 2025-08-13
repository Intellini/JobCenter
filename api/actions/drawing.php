<?php
/**
 * Drawing Action Endpoint
 * Handles technical drawing requests
 */

session_start();
header('Content-Type: application/json');

// Check if operator is logged in
if (!isset($_SESSION['operator_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';
require_once '../config/response.php';

$response = new ApiResponse();

try {
    // For this simplified implementation, just log the request
    $job_id = intval($_POST['job_id'] ?? 0);
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    if ($job_id <= 0) {
        echo $response->invalidJobId($job_id);
        exit;
    }
    
    // In a full implementation, this would:
    // 1. Retrieve technical drawings from document management system
    // 2. Log the access for audit purposes
    // 3. Return drawing URLs or file paths
    
    echo $response->jobActionSuccess($job_id, 'drawing', [
        'message' => 'Drawing access logged',
        'operator' => $operator
    ]);
    
} catch (Exception $e) {
    error_log('Drawing action error: ' . $e->getMessage());
    echo $response->serverError('Drawing action failed');
}
?>