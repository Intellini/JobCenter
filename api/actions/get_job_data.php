<?php
/**
 * Get Job Data Endpoint
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