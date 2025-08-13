<?php
/**
 * Pause Action Endpoint
 * Handles job pause with reason tracking
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
    
    // Check if job can be paused
    if (in_array($job['op_status'], [6, 10])) {
        $status_names = [6 => 'Paused', 10 => 'Complete'];
        $current_status = $status_names[$job['op_status']];
        echo $response->statusConflict($current_status, 'Active');
        exit;
    }
    
    // Get form data
    $dtp = $input['dtp'] ?? date('Y-m-d H:i:s');
    $rsn = $input['rsn'] ?? '';
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($rsn)) {
        echo $response->missingFields('rsn');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Save current status and set to paused (6)
        $result = $db->execute(
            "UPDATE operations SET 
                op_holdflg = op_status,
                op_status = 6,
                op_pause_time = ?
            WHERE op_id = ?",
            [$dtp, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification
        $notification_text = "Job paused: " . $rsn;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Pause')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'pause', [
            'operation_id' => $job['op_id'],
            'status' => 'Paused',
            'pause_time' => $dtp,
            'reason' => $rsn
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Pause action error: ' . $e->getMessage());
    echo $response->serverError('Pause action failed');
}
?>