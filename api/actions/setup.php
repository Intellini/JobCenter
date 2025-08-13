<?php
/**
 * Setup Action Endpoint
 * Handles job setup initiation
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
        "SELECT op_id, op_status FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Check if job can be started (status should be New or Assigned)
    if (!in_array($job['op_status'], [1, 2])) {
        $status_names = [3 => 'Setup', 4 => 'FPQC', 5 => 'In Process', 6 => 'Paused', 7 => 'Breakdown', 10 => 'Complete'];
        $current_status = $status_names[$job['op_status']] ?? 'Unknown';
        echo $response->statusConflict($current_status, 'New or Assigned');
        exit;
    }
    
    // Get form data
    $msg = $input['msg'] ?? '';
    $dtsp = $input['dtsp'] ?? date('Y-m-d');
    $dtsp_hora = $input['dtsp_hora'] ?? date('H:i:s');
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($msg)) {
        echo $response->missingFields('msg');
        exit;
    }
    
    // Limit message length
    if (strlen($msg) > 20) {
        echo $response->validationError(['msg' => 'Message must be 20 characters or less']);
        exit;
    }
    
    // Limit remarks length
    if (strlen($rmks) > 100) {
        echo $response->validationError(['rmks' => 'Remarks must be 100 characters or less']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update operation status to Setup (3)
        $result = $db->execute(
            "UPDATE operations SET 
                op_status = 3,
                op_start_time = CONCAT(?, ' ', ?),
                op_msg = ?,
                op_remarks = ?
            WHERE op_id = ?",
            [$dtsp, $dtsp_hora, $msg, $rmks, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification
        $notification_text = "Setup started: " . $msg;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Setup')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'setup', [
            'operation_id' => $job['op_id'],
            'status' => 'Setup',
            'setup_time' => $dtsp . ' ' . $dtsp_hora,
            'message' => $msg
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Setup action error: ' . $e->getMessage());
    echo $response->serverError('Setup action failed');
}
?>