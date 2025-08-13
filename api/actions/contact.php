<?php
/**
 * Contact Action Endpoint
 * Handles supervisor contact requests
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
$db = Database::getInstance();

try {
    // Get input data
    $input = $_POST;
    $job_id = intval($input['job_id'] ?? 0);
    
    if ($job_id <= 0) {
        echo $response->invalidJobId($job_id);
        exit;
    }
    
    // Validate job exists
    $job = $db->getRow(
        "SELECT op_id, op_status FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Get form data
    $issue_type = $input['issue_type'] ?? '';
    $message = $input['message'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($issue_type)) {
        echo $response->missingFields('issue_type');
        exit;
    }
    
    if (empty($message)) {
        echo $response->missingFields('message');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert contact request
        $contact_data = [
            'cr_op_id' => $job['op_id'],
            'cr_type' => $issue_type,
            'cr_message' => $message,
            'cr_timestamp' => date('Y-m-d H:i:s'),
            'cr_operator' => $operator,
            'cr_status' => 'pending'
        ];
        
        $result = $db->insert('contact_requests', $contact_data);
        
        if (!$result) {
            throw new Exception('Failed to create contact request');
        }
        
        // Determine priority based on issue type
        $priority = 0;
        if ($issue_type === 'emergency') $priority = 1;
        elseif ($issue_type === 'technical') $priority = 2;
        
        // Create notification for supervisors
        $notification_text = "Supervisor Help Requested: " . $issue_type . " - " . $message;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action, nm_priority) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Contact', ?)",
            [$notification_text, $job['op_id'], $priority]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'contact', [
            'operation_id' => $job['op_id'],
            'issue_type' => $issue_type,
            'message' => $message,
            'status' => 'Supervisor notified'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Contact action error: ' . $e->getMessage());
    echo $response->serverError('Contact action failed');
}
?>