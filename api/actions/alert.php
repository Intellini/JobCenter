<?php
/**
 * Alert Action Endpoint
 * Handles issue reporting and alerts
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
    $severity = $input['severity'] ?? '';
    $description = $input['description'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($issue_type)) {
        echo $response->missingFields('issue_type');
        exit;
    }
    
    if (empty($severity)) {
        echo $response->missingFields('severity');
        exit;
    }
    
    if (empty($description)) {
        echo $response->missingFields('description');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert alert record
        $alert_data = [
            'al_op_id' => $job['op_id'],
            'al_type' => $issue_type,
            'al_severity' => $severity,
            'al_description' => $description,
            'al_timestamp' => date('Y-m-d H:i:s'),
            'al_operator' => $operator,
            'al_status' => 'open'
        ];
        
        $result = $db->insert('alerts', $alert_data);
        
        if (!$result) {
            throw new Exception('Failed to create alert');
        }
        
        // Determine notification priority based on severity
        $priority = 0;
        if ($severity === 'critical') $priority = 1;
        elseif ($severity === 'high') $priority = 2;
        
        // Create notification
        $notification_text = strtoupper($severity) . " Alert: " . $issue_type . " - " . $description;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action, nm_priority) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Alert', ?)",
            [$notification_text, $job['op_id'], $priority]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'alert', [
            'operation_id' => $job['op_id'],
            'issue_type' => $issue_type,
            'severity' => $severity,
            'description' => $description
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Alert action error: ' . $e->getMessage());
    echo $response->serverError('Alert action failed');
}
?>