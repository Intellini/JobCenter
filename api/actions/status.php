<?php
/**
 * Status Action Endpoint
 * Handles job status requests
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
    // Get job ID from query parameter or POST data
    $job_id = intval($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
    
    if ($job_id <= 0) {
        echo $response->invalidJobId($job_id);
        exit;
    }
    
    // Get current job status
    $status_data = $db->getRow(
        "SELECT op_id, op_status, op_holdflg, op_act_prdqty, op_pln_prdqty FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$status_data) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Calculate progress
    $progress = 0;
    if ($status_data['op_pln_prdqty'] > 0) {
        $progress = round(($status_data['op_act_prdqty'] / $status_data['op_pln_prdqty']) * 100, 1);
    }
    
    // Status names
    $status_names = [
        1 => 'New',
        2 => 'Assigned', 
        3 => 'Setup',
        4 => 'FPQC',
        5 => 'In Process',
        6 => 'Paused',
        7 => 'Breakdown',
        8 => 'On Hold',
        9 => 'LPQC',
        10 => 'Complete',
        12 => 'QC Hold',
        13 => 'QC Check'
    ];
    
    echo $response->success([
        'job_id' => $job_id,
        'status_code' => $status_data['op_status'],
        'status_name' => $status_names[$status_data['op_status']] ?? 'Unknown',
        'hold_flag' => $status_data['op_holdflg'],
        'progress_percent' => $progress,
        'actual_quantity' => $status_data['op_act_prdqty'],
        'planned_quantity' => $status_data['op_pln_prdqty'],
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Status retrieved successfully');
    
} catch (Exception $e) {
    error_log('Status action error: ' . $e->getMessage());
    echo $response->serverError('Status retrieval failed');
}
?>