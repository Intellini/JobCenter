<?php
/**
 * Resume Action Endpoint
 * Handles job resume after pause
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
    
    // Validate job exists and get operation details
    $job = $db->getRow(
        "SELECT op_id, op_status, op_holdflg FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Check if job is paused
    if ($job['op_status'] != 6) {
        $status_names = [1 => 'New', 2 => 'Assigned', 3 => 'Setup', 5 => 'In Process', 10 => 'Complete'];
        $current_status = $status_names[$job['op_status']] ?? 'Unknown';
        echo $response->statusConflict($current_status, 'Paused');
        exit;
    }
    
    // Get form data
    $dtr = $input['dtr'] ?? date('Y-m-d H:i:s');
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($rmks)) {
        echo $response->missingFields('rmks');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Restore previous status from hold flag
        $previous_status = $job['op_holdflg'] ?: 5; // Default to In Process
        
        $result = $db->execute(
            "UPDATE operations SET 
                op_status = ?,
                op_holdflg = 0,
                op_resume_time = ?,
                op_remarks = ?
            WHERE op_id = ?",
            [$previous_status, $dtr, $rmks, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification
        $notification_text = "Job resumed: " . $rmks;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Resume')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        $status_names = [3 => 'Setup', 4 => 'FPQC', 5 => 'In Process'];
        $status_name = $status_names[$previous_status] ?? 'Active';
        
        echo $response->jobActionSuccess($job_id, 'resume', [
            'operation_id' => $job['op_id'],
            'status' => $status_name,
            'resume_time' => $dtr,
            'remarks' => $rmks
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Resume action error: ' . $e->getMessage());
    echo $response->serverError('Resume action failed');
}
?>