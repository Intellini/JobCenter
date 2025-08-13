<?php
/**
 * Get Job Data Endpoint
<<<<<<< HEAD
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
=======
 * Retrieves job information for a given planning ID
 */

function handle_get_job_data($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->error('Invalid planning ID', 'INVALID_PLANNING_ID');
        }
        
        $db = db();
        
        // Get job data with operation details
        $sql = "
            SELECT 
                p.pl_id,
                p.pl_lot,
                p.pl_itm,
                p.pl_desc,
                p.pl_prdqty,
                p.pl_mch,
                o.op_id,
                o.op_status,
                o.op_pln_prdqty,
                o.op_act_prdqty,
                o.op_start_time,
                o.op_end_time,
                o.op_tot_pause,
                o.op_pause_time,
                m.mc_desc as machine_name,
                i.im_partno,
                i.im_desc as item_description
            FROM planning p
            LEFT JOIN operations o ON p.pl_id = o.op_planning_id
            LEFT JOIN machines m ON p.pl_mch = m.mc_id
            LEFT JOIN items i ON p.pl_itm = i.im_id
            WHERE p.pl_id = ?
            ORDER BY o.op_id ASC
        ";
        
        $job_data = $db->getRow($sql, [$planning_id]);
        
        if (!$job_data) {
            return $response->jobNotFound($planning_id);
        }
        
        // Get status name
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
        
        $job_data['status_name'] = $status_names[$job_data['op_status']] ?? 'Unknown';
        
        // Calculate progress percentage
        if ($job_data['op_pln_prdqty'] > 0) {
            $job_data['progress_percent'] = round(($job_data['op_act_prdqty'] / $job_data['op_pln_prdqty']) * 100, 1);
        } else {
            $job_data['progress_percent'] = 0;
        }
        
        // Calculate time elapsed if job is started
        if ($job_data['op_start_time']) {
            $start_time = new DateTime($job_data['op_start_time']);
            $current_time = new DateTime();
            $elapsed = $start_time->diff($current_time);
            $job_data['time_elapsed_minutes'] = ($elapsed->days * 24 * 60) + ($elapsed->h * 60) + $elapsed->i;
        } else {
            $job_data['time_elapsed_minutes'] = 0;
        }
        
        // Check if job is currently paused
        $job_data['is_paused'] = !empty($job_data['op_pause_time']);
        
        return $response->success($job_data, 'Job data retrieved successfully');
        
    } catch (Exception $e) {
        error_log('Get job data error: ' . $e->getMessage());
        return $response->serverError('Failed to retrieve job data');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
