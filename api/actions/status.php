<?php
/**
<<<<<<< HEAD
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
=======
 * Status Endpoint
 * Get current status of a job without requiring planning_id
 * Can be used for general system status or specific job status
 */

function handle_status($input, $response) {
    try {
        $db = db();
        
        // If planning_id is provided, get specific job status
        if (isset($input['planning_id']) && $input['planning_id'] > 0) {
            $planning_id = intval($input['planning_id']);
            
            $job_status = $db->getRow(
                "SELECT 
                    p.pl_id,
                    p.pl_lot,
                    p.pl_itm,
                    p.pl_desc,
                    o.op_id,
                    o.op_status,
                    o.op_act_prdqty,
                    o.op_pln_prdqty,
                    o.op_pause_time,
                    m.mc_desc as machine_name
                FROM planning p
                LEFT JOIN operations o ON p.pl_id = o.op_planning_id
                LEFT JOIN machines m ON p.pl_mch = m.mc_id
                WHERE p.pl_id = ?",
                [$planning_id]
            );
            
            if (!$job_status) {
                return $response->jobNotFound($planning_id);
            }
            
            // Add status name
            $status_names = [
                1 => 'New', 2 => 'Assigned', 3 => 'Setup', 4 => 'FPQC',
                5 => 'In Process', 6 => 'Paused', 7 => 'Breakdown',
                8 => 'On Hold', 9 => 'LPQC', 10 => 'Complete',
                12 => 'QC Hold', 13 => 'QC Check'
            ];
            
            $job_status['status_name'] = $status_names[$job_status['op_status']] ?? 'Unknown';
            $job_status['is_paused'] = !empty($job_status['op_pause_time']);
            
            return $response->success($job_status, 'Job status retrieved');
        }
        
        // Otherwise return general system status
        $system_status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => 'connected',
            'api_version' => '1.0.0'
        ];
        
        // Get active jobs count
        $active_jobs = $db->getOne(
            "SELECT COUNT(*) FROM operations WHERE op_status IN (3,4,5,6,7)"
        );
        
        $system_status['active_jobs'] = intval($active_jobs);
        
        // Get paused jobs count
        $paused_jobs = $db->getOne(
            "SELECT COUNT(*) FROM operations WHERE op_status = 6"
        );
        
        $system_status['paused_jobs'] = intval($paused_jobs);
        
        return $response->success($system_status, 'System status retrieved');
        
    } catch (Exception $e) {
        error_log('Status check error: ' . $e->getMessage());
        return $response->serverError('Status check failed');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
