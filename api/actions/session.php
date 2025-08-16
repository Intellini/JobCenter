<?php
/**
 * Session Management API
 * Handles browser close detection and activity tracking
 */

// Include session configuration
require_once '../../config/session.php';

// Initialize session
initializeSession();

// Set JSON response header
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$response = ['success' => false];

switch ($input['action']) {
    case 'browser_close':
        // Browser is closing, mark session for cleanup
        if (isset($_SESSION['operator_id'])) {
            // Log the browser close event
            error_log("JobCenter: Browser close detected for operator " . $_SESSION['operator_name'] . " at " . date('Y-m-d H:i:s'));
            
            // Mark session as abandoned
            $_SESSION['browser_closed'] = time();
            
            // Don't destroy session immediately (user might be refreshing)
            // It will be cleaned up on next access if truly abandoned
            
            $response['success'] = true;
        }
        break;
        
    case 'activity_ping':
        // Update session activity timestamp
        if (isset($_SESSION['operator_id'])) {
            updateSessionActivity();
            $response['success'] = true;
            $response['session_valid'] = isSessionValid();
        } else {
            $response['success'] = false;
            $response['session_valid'] = false;
        }
        break;
        
    case 'check_session':
        // Check if session is still valid
        $response['success'] = true;
        $response['session_valid'] = isSessionValid();
        $response['logged_in'] = isset($_SESSION['operator_id']);
        
        if (!$response['session_valid'] && $response['logged_in']) {
            // Session expired, destroy it
            destroySession();
            $response['logged_in'] = false;
        }
        break;
        
    default:
        http_response_code(400);
        $response['error'] = 'Unknown action';
}

echo json_encode($response);
?>