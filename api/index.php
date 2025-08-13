<?php
/**
 * Job Center API Router
 * Main entry point for all API requests
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include dependencies
require_once '../config/database.php';
require_once 'config/response.php';

// Create response handler
$response = new ApiResponse();

try {
    // Parse request URI
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path = str_replace('/jc/api/', '', $path);
    $path = trim($path, '/');
    
    // Split path into segments
    $segments = explode('/', $path);
    $action = $segments[0] ?? '';
    
    // Route to appropriate handler
    switch ($action) {
        case 'health':
            echo $response->success(['status' => 'healthy', 'timestamp' => date('c')], 'API is running');
            break;
            
        case 'actions':
            // Handle action endpoints
            $action_name = $segments[1] ?? '';
            if (empty($action_name)) {
                echo $response->error('Action name required', 'MISSING_ACTION');
                break;
            }
            
            $action_file = __DIR__ . '/actions/' . $action_name . '.php';
            if (!file_exists($action_file)) {
                echo $response->notFound('Action: ' . $action_name);
                break;
            }
            
            // Include and execute action
            require_once $action_file;
            break;
            
        case 'job':
            // Handle job-specific endpoints
            $job_id = $segments[1] ?? '';
            if (empty($job_id)) {
                echo $response->error('Job ID required', 'MISSING_JOB_ID');
                break;
            }
            
            // Get job data
            $db = Database::getInstance();
            $job = $db->getRow(
                "SELECT * FROM operations WHERE op_id = ?",
                [intval($job_id)]
            );
            
            if (!$job) {
                echo $response->notFound('Job');
                break;
            }
            
            echo $response->success($job, 'Job data retrieved');
            break;
            
        case 'status':
            // Handle status endpoints
            $job_id = $segments[1] ?? '';
            if (empty($job_id)) {
                echo $response->error('Job ID required', 'MISSING_JOB_ID');
                break;
            }
            
            // Get job status
            $db = Database::getInstance();
            $status = $db->getValue(
                "SELECT op_status FROM operations WHERE op_id = ?",
                [intval($job_id)]
            );
            
            if ($status === null) {
                echo $response->notFound('Job');
                break;
            }
            
            echo $response->success(['status' => $status], 'Job status retrieved');
            break;
            
        case '':
            // API root - show available endpoints
            $endpoints = [
                'GET /health' => 'API health check',
                'POST /actions/{action}' => 'Execute operator action',
                'GET /job/{id}' => 'Get job details',
                'GET /status/{id}' => 'Get job status',
            ];
            
            echo $response->success($endpoints, 'Job Center API v2.0');
            break;
            
        default:
            echo $response->notFound('Endpoint');
    }
    
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    echo $response->serverError('An error occurred while processing your request');
}
?>