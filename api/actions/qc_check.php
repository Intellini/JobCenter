<?php
/**
 * QC Check Action Endpoint
 * Handles quality check requests
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
    $dtq = $input['dtq'] ?? date('Y-m-d H:i:s');
    $msg = $input['msg'] ?? '';
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update operation status to QC Check (13)
        $result = $db->execute(
            "UPDATE operations SET 
                op_holdflg = op_status,
                op_status = 13,
                op_qc_request_time = ?,
                op_msg = ?,
                op_remarks = ?
            WHERE op_id = ?",
            [$dtq, $msg, $rmks, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification for QC team
        $notification_text = "QC Check Requested: " . ($msg ?: 'Quality check needed');
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'QC_Check')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'qc_check', [
            'operation_id' => $job['op_id'],
            'status' => 'QC Check',
            'request_time' => $dtq,
            'message' => $msg
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('QC Check action error: ' . $e->getMessage());
    echo $response->serverError('QC Check action failed');
}
?>