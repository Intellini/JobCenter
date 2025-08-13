<?php
/**
 * Job Center API Router
 * Main entry point for all API requests
<<<<<<< HEAD
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
=======
 * Handles CORS, JSON parsing, and routing to action endpoints
 */

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Handle CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once(__DIR__ . '/../config/database.php');
require_once(__DIR__ . '/config/response.php');

/**
 * Main API Router Class
 */
class ApiRouter {
    private $input;
    private $method;
    private $response;
    
    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->response = new ApiResponse();
        $this->parseInput();
    }
    
    /**
     * Parse incoming request data
     */
    private function parseInput() {
        $this->input = [];
        
        // Handle different request methods
        switch ($this->method) {
            case 'GET':
                $this->input = $_GET;
                break;
                
            case 'POST':
            case 'PUT':
            case 'DELETE':
                // Get raw input
                $raw_input = file_get_contents('php://input');
                
                // Try to decode JSON first
                if (!empty($raw_input)) {
                    $json_data = json_decode($raw_input, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->input = $json_data;
                    } else {
                        // Fallback to POST data
                        $this->input = $_POST;
                    }
                } else {
                    $this->input = $_POST;
                }
                break;
        }
        
        // Merge GET parameters for all methods (for compatibility)
        $this->input = array_merge($_GET, $this->input);
    }
    
    /**
     * Route request to appropriate handler
     */
    public function route() {
        try {
            // Validate request has action parameter
            if (!isset($this->input['action'])) {
                return $this->response->error('Missing action parameter', 'MISSING_ACTION');
            }
            
            $action = $this->input['action'];
            
            // Validate planning_id is provided for most actions
            if (!in_array($action, ['health', 'status']) && !isset($this->input['planning_id'])) {
                return $this->response->error('Missing planning_id parameter', 'MISSING_PLANNING_ID');
            }
            
            // Define allowed actions
            $allowed_actions = [
                'health',           // System health check
                'status',           // Get job status
                'setup',            // Start setup
                'fpqc',             // First piece QC
                'qc_check',         // Request QC check
                'pause',            // Pause job
                'resume',           // Resume job
                'breakdown',        // Report breakdown
                'complete',         // Complete job
                'contact',          // Contact supervisor
                'alert',            // Report issue
                'testing',          // Record test results
                'drawing',          // View technical drawing
                'control_chart',    // View control chart
                'lock',             // Lock/unlock screen
                'get_job_data'      // Get job information
            ];
            
            // Validate action is allowed
            if (!in_array($action, $allowed_actions)) {
                return $this->response->error('Invalid action: ' . $action, 'INVALID_ACTION');
            }
            
            // Route to action handler
            $action_file = __DIR__ . '/actions/' . $action . '.php';
            
            if (!file_exists($action_file)) {
                return $this->response->error('Action handler not found: ' . $action, 'HANDLER_NOT_FOUND');
            }
            
            // Include and execute action handler
            require_once($action_file);
            
            // Call the action function
            $function_name = 'handle_' . $action;
            if (!function_exists($function_name)) {
                return $this->response->error('Action function not found: ' . $function_name, 'FUNCTION_NOT_FOUND');
            }
            
            // Execute action with input data
            return $function_name($this->input, $this->response);
            
        } catch (Exception $e) {
            // Log error for debugging
            error_log('API Error: ' . $e->getMessage());
            return $this->response->error('Internal server error', 'SERVER_ERROR');
        }
    }
    
    /**
     * Log API request for debugging
     */
    private function logRequest() {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $this->method,
            'action' => $this->input['action'] ?? 'unknown',
            'planning_id' => $this->input['planning_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        error_log('API Request: ' . json_encode($log_data));
    }
}

/**
 * Execute the API router
 */
try {
    $router = new ApiRouter();
    echo $router->route();
} catch (Exception $e) {
    $response = new ApiResponse();
    echo $response->error('Fatal error occurred', 'FATAL_ERROR');
}
>>>>>>> Initial commit: Job Center simplified tablet interface
