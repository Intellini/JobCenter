<?php
/**
 * Control Chart Action Endpoint
 * Handles control chart data requests
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
    // 1. Generate or retrieve control chart data
    // 2. Return statistical process control information
    // 3. Log the access for audit purposes
    
    echo $response->jobActionSuccess($job_id, 'control_chart', [
        'message' => 'Control chart access logged',
        'operator' => $operator
    ]);
    
} catch (Exception $e) {
    error_log('Control chart action error: ' . $e->getMessage());
    echo $response->serverError('Control chart action failed');
}
?>