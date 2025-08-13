<?php
/**
<<<<<<< HEAD
 * Health Check Action Endpoint
 * Simple health check for API status
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
    echo $response->success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'operator' => $_SESSION['operator_name'] ?? 'unknown',
        'session_active' => true
    ], 'Health check successful');
    
} catch (Exception $e) {
    error_log('Health check error: ' . $e->getMessage());
    echo $response->serverError('Health check failed');
}
?>
=======
 * Health Check Endpoint
 * Simple endpoint to verify API is working
 */

function handle_health($input, $response) {
    try {
        // Test database connection
        $db = db();
        $db->getOne("SELECT 1");
        
        $health_data = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => 'connected',
            'version' => '1.0.0'
        ];
        
        return $response->success($health_data, 'API is healthy');
        
    } catch (Exception $e) {
        return $response->serverError('Health check failed', $e->getMessage());
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
