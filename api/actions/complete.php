<?php
/**
 * Complete Action Endpoint
 * Handles job completion with final quantity
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
        "SELECT op_id, op_status, op_pln_prdqty FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Check if job can be completed
    if ($job['op_status'] == 10) {
        echo $response->statusConflict('Complete', 'Active');
        exit;
    }
    
    // Get form data
    $final_qty = floatval($input['final_qty'] ?? 0);
    $reject_qty = floatval($input['reject_qty'] ?? 0);
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if ($final_qty <= 0) {
        echo $response->missingFields('final_qty');
        exit;
    }
    
    // Validate quantities
    if ($reject_qty < 0) {
        echo $response->validationError(['reject_qty' => 'Reject quantity cannot be negative']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update operation status to Complete (10)
        $result = $db->execute(
            "UPDATE operations SET 
                op_status = 10,
                op_act_prdqty = ?,
                op_end_time = NOW(),
                op_remarks = ?
            WHERE op_id = ?",
            [$final_qty, $rmks, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification
        $notification_text = "Job Completed: " . $final_qty . " pcs";
        if ($reject_qty > 0) {
            $notification_text .= " (" . $reject_qty . " rejected)";
        }
        
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Complete')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        // Calculate completion metrics
        $completion_percent = round(($final_qty / $job['op_pln_prdqty']) * 100, 1);
        $net_qty = $final_qty - $reject_qty;
        
        echo $response->jobActionSuccess($job_id, 'complete', [
            'operation_id' => $job['op_id'],
            'status' => 'Complete',
            'final_quantity' => $final_qty,
            'reject_quantity' => $reject_qty,
            'net_quantity' => $net_qty,
            'completion_percent' => $completion_percent
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Complete action error: ' . $e->getMessage());
    echo $response->serverError('Complete action failed');
}
?>