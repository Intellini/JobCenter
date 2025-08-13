<?php
/**
 * Get Job Data Endpoint
 * Retrieves comprehensive job information
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
    // Get job ID from query parameter
    $job_id = intval($_GET['job_id'] ?? 0);
    
    if ($job_id <= 0) {
        echo $response->invalidJobId($job_id);
        exit;
    }
    
    // Get comprehensive job data
    $job_query = "
        SELECT 
            o.op_id,
            o.op_lot,
            o.op_obid as order_id,
            o.op_prod as product_id,
            o.op_pln_prdqty as planned_quantity,
            o.op_act_prdqty as actual_quantity,
            o.op_status,
            o.op_start as planned_start,
            o.op_end as planned_end,
            o.op_stp_time as setup_time,
            o.op_tot_pause as total_pause_time,
            o.op_holdflg as hold_flag,
            o.op_seq as sequence,
            o.op_msg as message,
            o.op_remarks,
            wi.im_name as item_code,
            wi.im_name as item_name,
            oh.ob_porefno as po_reference,
            oh.ob_duedate as due_date,
            m.mm_code as machine_code,
            m.mm_name as machine_name
        FROM operations o
        LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
        LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
        LEFT JOIN machine m ON o.op_mach = m.mm_id
        WHERE o.op_id = ?
    ";
    
    $job = $db->getRow($job_query, [$job_id]);
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Calculate progress percentage
    $progress_percent = 0;
    if ($job['planned_quantity'] > 0) {
        $progress_percent = min(100, ($job['actual_quantity'] / $job['planned_quantity']) * 100);
    }
    
    // Add calculated fields
    $job['progress_percent'] = round($progress_percent, 2);
    $job['is_qc_hold'] = $job['op_status'] == 12;
    $job['is_paused'] = $job['op_status'] == 6;
    $job['is_completed'] = $job['op_status'] == 10;
    
    // Get recent actions for this job
    $actions = $db->getAll(
        "SELECT ja_action, ja_operator, ja_timestamp, ja_details 
        FROM job_actions 
        WHERE ja_job_id = ? 
        ORDER BY ja_timestamp DESC 
        LIMIT 10",
        [$job_id]
    );
    
    $job['recent_actions'] = $actions;
    
    echo $response->success($job, 'Job data retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get job data error: ' . $e->getMessage());
    echo $response->serverError('Failed to retrieve job data');
}
?>