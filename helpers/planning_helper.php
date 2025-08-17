<?php
/**
 * Planning Helper Functions
 * Core functions for planning interface operations
 */

require_once __DIR__ . '/../config/database.php';

// Handle AJAX requests for burn rate data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_burn_rate') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['job_id'])) {
        echo json_encode(['success' => false, 'message' => 'Job ID required']);
        exit;
    }
    
    $job_id = intval($_POST['job_id']);
    $burn_rate_data = calculateBurnRate($job_id);
    
    echo json_encode([
        'success' => true,
        'burn_rate_data' => $burn_rate_data
    ]);
    exit;
}

// Handle AJAX requests for buffer status data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_buffer_status') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['machine_code'], $_POST['date'], $_POST['shift'])) {
        echo json_encode(['success' => false, 'message' => 'Machine code, date, and shift required']);
        exit;
    }
    
    $machine_code = $_POST['machine_code'];
    $date = $_POST['date'];
    $shift = $_POST['shift'];
    
    // Convert shift letter to number if needed (A=1, B=2, C=3)
    $shift_num = $shift;
    if (is_string($shift_num)) {
        $shift_map = ['A' => 1, 'B' => 2, 'C' => 3];
        $shift_num = $shift_map[$shift_num] ?? 1;
    }
    
    $buffer_status = calculateBufferStatus($machine_code, $date, $shift_num);
    
    echo json_encode([
        'success' => true,
        'buffer_status' => $buffer_status
    ]);
    exit;
}

/**
 * Get the previous working date excluding Sundays and holidays
 * 
 * @param string $current_date Current date in Y-m-d format
 * @param object $db Database connection
 * @return string|null Previous working date or null if not found within 30 days
 */
function getPreviousWorkingDate($current_date, $db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    $date = new DateTime($current_date);
    $date->modify('-1 day');
    $attempts = 0;
    $max_attempts = 30; // Safety limit - don't go back more than 30 days
    
    while ($attempts < $max_attempts) {
        $check_date = $date->format('Y-m-d');
        
        // Check if Sunday (0 = Sunday in PHP)
        if ($date->format('w') == 0) {
            $date->modify('-1 day');
            $attempts++;
            continue;
        }
        
        // Check holiday table
        $holiday_check = $db->GetOne(
            "SELECT COUNT(*) FROM holiday WHERE holiday_date = ?",
            array($check_date)
        );
        
        if (!$holiday_check) {
            // Found a working date
            return $check_date;
        }
        
        $date->modify('-1 day');
        $attempts++;
    }
    
    // No working date found within limit
    return null;
}

/**
 * Get incomplete jobs from a specific date and machine
 * 
 * @param string $date Date to check
 * @param string $machine Machine code
 * @param object $db Database connection
 * @return array Array of incomplete jobs
 */
function getIncompleteJobs($date, $machine, $db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    $sql = "
        SELECT 
            mp.*,
            o.op_lot,
            o.op_status,
            o.op_act_prdqty,
            o.op_pln_prdqty,
            wi.im_name as item_name,
            CASE 
                WHEN o.op_status BETWEEN 3 AND 9 THEN 'started'
                WHEN o.op_status IN (0,1,2) THEN 'not_started'
                ELSE 'other'
            END as job_category
        FROM mach_planning mp
        JOIN operations o ON mp.mp_op_id = o.op_id
        LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
        WHERE mp.mp_op_mach = ?
          AND mp.mp_op_proddate = ?
          AND o.op_status < 10
        ORDER BY mp.mp_op_seq
    ";
    
    return $db->GetAll($sql, array($machine, $date));
}

/**
 * Process carryover decisions for incomplete jobs
 * 
 * @param array $selected_jobs Array of job IDs to carry over
 * @param array $all_jobs Array of all incomplete job IDs
 * @param string $current_date Current date to carry jobs to
 * @param object $db Database connection
 * @return bool Success status
 */
function processCarryoverDecisions($selected_jobs, $all_jobs, $current_date, $db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    $db->StartTrans();
    
    try {
        // Get rejected jobs (not selected for carryover)
        $rejected_jobs = array_diff($all_jobs, $selected_jobs);
        
        // Process carried over jobs - update to current date
        foreach ($selected_jobs as $job_id) {
            $db->Execute(
                "UPDATE mach_planning 
                 SET mp_op_proddate = ? 
                 WHERE mp_op_id = ?",
                array($current_date, $job_id)
            );
        }
        
        // Process rejected jobs
        foreach ($rejected_jobs as $job_id) {
            // Set to paused status in operations
            $db->Execute(
                "UPDATE operations 
                 SET op_status = 6 
                 WHERE op_id = ?",
                array($job_id)
            );
            
            // Remove from mach_planning
            $db->Execute(
                "DELETE FROM mach_planning 
                 WHERE mp_op_id = ?",
                array($job_id)
            );
        }
        
        $db->CompleteTrans();
        return true;
        
    } catch (Exception $e) {
        $db->FailTrans();
        error_log("Carryover processing failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up orphaned jobs in mach_planning
 * 
 * @param object $db Database connection
 * @return int Number of records cleaned
 */
function cleanupOrphanedJobs($db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    $count = 0;
    
    // Remove past date jobs
    $count += $db->Execute(
        "DELETE FROM mach_planning 
         WHERE mp_op_proddate < CURRENT_DATE"
    );
    
    // Remove paused jobs
    $count += $db->Execute(
        "DELETE FROM mach_planning 
         WHERE mp_op_status = 6"
    );
    
    // Remove completed jobs
    $count += $db->Execute(
        "DELETE FROM mach_planning 
         WHERE mp_op_status = 10"
    );
    
    return $count;
}

/**
 * Calculate buffer status for a shift
 * 
 * @param string $machine Machine code
 * @param string $date Date
 * @param int $shift Shift number (1=A, 2=B, 3=C)
 * @param int $shift_minutes Total shift duration in minutes (default 480 for 8 hours)
 * @return array Buffer status information
 */
function calculateBufferStatus($machine, $date, $shift, $shift_minutes = 480) {
    $buffer_percentage = 0.15; // 15% buffer time
    $total_buffer = $shift_minutes * $buffer_percentage;
    
    // Get time losses for this shift
    $db = Database::getInstance()->getConnection();
    $sql = "
        SELECT SUM(TIMESTAMPDIFF(MINUTE, dt_stdt, dt_endt)) as total_loss
        FROM downtime
        WHERE dt_mchid = (SELECT m_id FROM machine WHERE m_code = ?)
          AND DATE(dt_stdt) = ?
          AND dt_endt IS NOT NULL
    ";
    
    $time_lost = $db->GetOne($sql, array($machine, $date)) ?: 0;
    $buffer_used = min($time_lost, $total_buffer);
    $buffer_remaining = $total_buffer - $buffer_used;
    $buffer_percent_remaining = ($buffer_remaining / $total_buffer) * 100;
    
    // Calculate if overtime needed
    $overtime_needed = max(0, $time_lost - $total_buffer);
    
    return array(
        'total_buffer' => $total_buffer,
        'buffer_used' => $buffer_used,
        'buffer_remaining' => $buffer_remaining,
        'buffer_percentage' => $buffer_percent_remaining,
        'overtime_projected' => $overtime_needed,
        'status' => $buffer_percent_remaining > 25 ? 'healthy' : 
                   ($buffer_percent_remaining > 10 ? 'warning' : 'critical')
    );
}

/**
 * Calculate burn rate for a job
 * 
 * @param int $planning_id Planning ID
 * @return array Burn rate metrics
 */
function calculateBurnRate($job_id) {
    $db = Database::getInstance()->getConnection();
    
    // Get job details from operations table, optionally join with mach_planning
    $sql = "
        SELECT 
            o.op_id,
            o.op_act_prdqty,
            o.op_pln_prdqty,
            o.op_status,
            mp.mp_op_start,
            mp.mp_op_end,
            COALESCE(mp.mp_op_start, NOW()) as start_time,
            COALESCE(mp.mp_op_end, DATE_ADD(NOW(), INTERVAL 2 HOUR)) as end_time,
            NOW() as current_time
        FROM operations o
        LEFT JOIN mach_planning mp ON o.op_id = mp.mp_op_id
        WHERE o.op_id = ?
    ";
    
    $job = $db->GetRow($sql, array($job_id));
    
    if (!$job || $job['op_status'] < 3) {
        return array('burn_rate' => 0, 'status' => 'not_started', 'time_progress' => 0, 'qty_progress' => 0);
    }
    
    // Calculate time progress
    $start_time = new DateTime($job['start_time']);
    $end_time = new DateTime($job['end_time']);
    $current_time = new DateTime();
    
    // Handle case where job hasn't started yet
    if ($current_time < $start_time) {
        return array('burn_rate' => 0, 'status' => 'not_started', 'time_progress' => 0, 'qty_progress' => 0);
    }
    
    $total_interval = $start_time->diff($end_time);
    $elapsed_interval = $start_time->diff($current_time);
    
    $total_minutes = ($total_interval->h * 60) + $total_interval->i + ($total_interval->days * 24 * 60);
    $elapsed_minutes = ($elapsed_interval->h * 60) + $elapsed_interval->i + ($elapsed_interval->days * 24 * 60);
    
    $time_progress = $total_minutes > 0 ? ($elapsed_minutes / $total_minutes) : 0;
    
    // Calculate quantity progress
    $qty_progress = $job['op_pln_prdqty'] > 0 ? 
                    ($job['op_act_prdqty'] / $job['op_pln_prdqty']) : 0;
    
    // Calculate burn rate
    $burn_rate = $time_progress > 0 ? ($qty_progress / $time_progress) : 0;
    
    // Determine status
    if ($burn_rate >= 0.95) {
        $status = 'on_track';
    } elseif ($burn_rate >= 0.80) {
        $status = 'slightly_behind';
    } elseif ($burn_rate >= 0.60) {
        $status = 'behind';
    } else {
        $status = 'critical';
    }
    
    // Project completion time
    $projected_minutes = $qty_progress > 0 ? 
                        ($elapsed_minutes / $qty_progress) : 
                        ($total_minutes * 2); // Double time if no progress
    
    $overtime_minutes = max(0, $projected_minutes - $total_minutes);
    
    return array(
        'burn_rate' => round($burn_rate, 2),
        'time_progress' => round($time_progress * 100, 1),
        'qty_progress' => round($qty_progress * 100, 1),
        'status' => $status,
        'elapsed_minutes' => $elapsed_minutes,
        'total_minutes' => $total_minutes,
        'overtime_projected' => $overtime_minutes
    );
}