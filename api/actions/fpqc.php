<?php
/**
 * FPQC Action Endpoint
 * Handles First Piece QC requests
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
    
    // Check if job is in Setup status
    if ($job['op_status'] != 3) {
        $status_names = [1 => 'New', 2 => 'Assigned', 4 => 'FPQC', 5 => 'In Process', 10 => 'Complete'];
        $current_status = $status_names[$job['op_status']] ?? 'Unknown';
        echo $response->statusConflict($current_status, 'Setup');
        exit;
    }
    
    // Get form data
    $opq_act_prdqty = intval($input['opq_act_prdqty'] ?? 0);
    $opq_qc_qty = intval($input['opq_qc_qty'] ?? 0);
    $opq_rjk_qty = intval($input['opq_rjk_qty'] ?? 0);
    $opq_nc_qty = intval($input['opq_nc_qty'] ?? 0);
    $opq_qc_rmks = $input['opq_qc_rmks'] ?? '';
    $opq_reason = $input['opq_reason'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if ($opq_act_prdqty <= 0) {
        echo $response->missingFields('opq_act_prdqty');
        exit;
    }
    
    if ($opq_qc_qty <= 0) {
        echo $response->missingFields('opq_qc_qty');
        exit;
    }
    
    // Validate quantities
    if ($opq_qc_qty > $opq_act_prdqty) {
        echo $response->validationError(['opq_qc_qty' => 'QC quantity cannot exceed actual production quantity']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update operation status to FPQC (4)
        $result = $db->execute(
            "UPDATE operations SET 
                op_status = 4,
                op_act_prdqty = ?
            WHERE op_id = ?",
            [$opq_act_prdqty, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification for QC team
        $notification_text = "FPQC Required - " . $opq_act_prdqty . " pieces produced";
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'FPQC')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'fpqc', [
            'operation_id' => $job['op_id'],
            'status' => 'FPQC',
            'actual_quantity' => $opq_act_prdqty,
            'qc_quantity' => $opq_qc_qty
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('FPQC action error: ' . $e->getMessage());
    echo $response->serverError('FPQC action failed');
}
?>