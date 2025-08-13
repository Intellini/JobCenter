<?php
/**
 * Breakdown Action Endpoint
 * Handles machine breakdown reporting
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
    
    // Get form data
    $dtbd = $input['dtbd'] ?? date('Y-m-d H:i:s');
    $rmk = $input['rmk'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($rmk)) {
        echo $response->missingFields('rmk');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update operation status to Breakdown (7)
        $result = $db->execute(
            "UPDATE operations SET 
                op_holdflg = op_status,
                op_status = 7,
                op_breakdown_time = ?,
                op_remarks = ?
            WHERE op_id = ?",
            [$dtbd, $rmk, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create urgent notification for maintenance
        $notification_text = "URGENT: Machine Breakdown - " . $rmk;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action, nm_priority) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Breakdown', 1)",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'breakdown', [
            'operation_id' => $job['op_id'],
            'status' => 'Breakdown',
            'breakdown_time' => $dtbd,
            'details' => $rmk
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Breakdown action error: ' . $e->getMessage());
    echo $response->serverError('Breakdown action failed');
}
?>