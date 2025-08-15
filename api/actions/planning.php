<?php
/**
 * Planning API Endpoint
 * Handles supervisor planning actions: add, remove, and reorder jobs
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in as supervisor
if (!isset($_SESSION['is_supervisor']) || !$_SESSION['is_supervisor']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';

// Get database instance
$db = Database::getInstance();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    switch ($input['action']) {
        case 'add_job':
            if (!isset($input['job_id'], $input['machine_id'], $input['date'], $input['shift'])) {
                throw new Exception('Missing required parameters for add_job');
            }
            
            // Get the next sequence number
            $next_seq = $db->getValue("
                SELECT COALESCE(MAX(mp_op_seq), 0) + 1 
                FROM mach_planning 
                WHERE mp_op_mach = ? AND mp_op_date = ? AND mp_op_shift = ?
            ", [$input['machine_id'], $input['date'], $input['shift']]);
            
            // Insert into mach_planning
            $result = $db->query("
                INSERT INTO mach_planning (mp_op_id, mp_op_mach, mp_op_date, mp_op_shift, mp_op_seq, mp_op_lot)
                SELECT ?, ?, ?, ?, ?, op_lot
                FROM operations 
                WHERE op_id = ?
            ", [
                $input['job_id'], 
                $input['machine_id'], 
                $input['date'], 
                $input['shift'], 
                $next_seq, 
                $input['job_id']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Job added to planning sequence']);
            } else {
                throw new Exception('Failed to add job to planning');
            }
            break;
            
        case 'remove_job':
            if (!isset($input['planning_id'])) {
                throw new Exception('Missing planning_id for remove_job');
            }
            
            // Get the sequence number of the job being removed
            $removed_seq = $db->getValue("
                SELECT mp_op_seq 
                FROM mach_planning 
                WHERE mp_op_id = ?
            ", [$input['planning_id']]);
            
            if ($removed_seq === null) {
                throw new Exception('Planning job not found');
            }
            
            // Get machine, date, shift for updating other sequences
            $planning_info = $db->getRow("
                SELECT mp_op_mach, mp_op_date, mp_op_shift 
                FROM mach_planning 
                WHERE mp_op_id = ?
            ", [$input['planning_id']]);
            
            // Remove the job
            $result = $db->query("DELETE FROM mach_planning WHERE mp_op_id = ?", [$input['planning_id']]);
            
            if ($result) {
                // Update sequence numbers of remaining jobs
                $db->query("
                    UPDATE mach_planning 
                    SET mp_op_seq = mp_op_seq - 1 
                    WHERE mp_op_mach = ? AND mp_op_date = ? AND mp_op_shift = ? AND mp_op_seq > ?
                ", [
                    $planning_info['mp_op_mach'], 
                    $planning_info['mp_op_date'], 
                    $planning_info['mp_op_shift'], 
                    $removed_seq
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Job removed from planning sequence']);
            } else {
                throw new Exception('Failed to remove job from planning');
            }
            break;
            
        case 'update_sequence':
            if (!isset($input['machine_id'], $input['date'], $input['shift'], $input['sequence'])) {
                throw new Exception('Missing required parameters for update_sequence');
            }
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // First, reset all jobs for this machine back to unassigned
                $db->query("
                    UPDATE operations 
                    SET op_seq = 0 
                    WHERE (op_mach = ? OR op_mach IS NULL) AND op_seq IS NOT NULL
                ", [$input['machine_id']]);
                
                // Update sequence numbers and assign machine for sequenced jobs
                foreach ($input['sequence'] as $item) {
                    $db->query("
                        UPDATE operations 
                        SET op_seq = ?, op_mach = ? 
                        WHERE op_id = ?
                    ", [$item['sequenceOrder'], $input['machine_id'], $item['jobId']]);
                }
                
                // Commit transaction
                $db->commit();
                
                echo json_encode(['success' => true, 'message' => 'Sequence updated successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'save_to_mach_planning':
            // Save planning sequence with calculated times to mach_planning table
            if (!isset($input['machine_id'], $input['work_date'], $input['shift'], $input['jobs'])) {
                throw new Exception('Missing parameters for save_to_mach_planning');
            }
            
            $db->beginTransaction();
            
            try {
                // First, clear existing planning for this machine/date/shift
                $db->query("
                    DELETE FROM mach_planning 
                    WHERE mp_op_mach = ? AND mp_op_proddate = ? AND mp_op_shift = ?
                ", [$input['machine_id'], $input['work_date'], $input['shift']]);
                
                // Convert shift letter to number if needed (A=1, B=2, C=3)
                $shift_num = $input['shift'];
                if (is_string($shift_num)) {
                    $shift_map = ['A' => 1, 'B' => 2, 'C' => 3];
                    $shift_num = $shift_map[$shift_num] ?? 1;
                }
                
                // Insert each job with calculated times
                foreach ($input['jobs'] as $job) {
                    // Get job details from operations table
                    $op_data = $db->getRow("
                        SELECT o.*, wi.im_name as item_code, oh.ob_porefno as po_ref
                        FROM operations o
                        LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
                        LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
                        WHERE o.op_id = ?
                    ", [$job['op_id']]);
                    
                    if ($op_data) {
                        $db->query("
                            INSERT INTO mach_planning (
                                mp_op_id, mp_op_mach, mp_op_proddate, mp_op_shift, mp_op_seq,
                                mp_op_start, mp_op_end, mp_op_lot, mp_op_proditm,
                                mp_op_pln_prdqty, mp_op_esttime, mp_op_calctime,
                                mp_op_jcrd, mp_op_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ", [
                            $job['op_id'],
                            $input['machine_id'],
                            $input['work_date'],
                            $shift_num,
                            $job['sequence'],
                            $job['start_time'],
                            $job['end_time'],
                            $op_data['op_lot'],
                            $op_data['item_code'],
                            $op_data['op_pln_prdqty'],
                            $job['setup_time'] ?? 0,
                            $job['prod_time'] ?? 0,
                            $op_data['po_ref'] ?? $op_data['op_lot'],
                            0 // status: 0=planned
                        ]);
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Planning saved to database']);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'move_job':
            if (!isset($input['planning_id'], $input['direction'])) {
                throw new Exception('Missing parameters for move_job');
            }
            
            // Get current job info
            $current_job = $db->getRow("
                SELECT mp_op_seq, mp_op_mach, mp_op_date, mp_op_shift 
                FROM mach_planning 
                WHERE mp_op_id = ?
            ", [$input['planning_id']]);
            
            if (!$current_job) {
                throw new Exception('Planning job not found');
            }
            
            $current_seq = $current_job['mp_op_seq'];
            $new_seq = $input['direction'] === 'up' ? $current_seq - 1 : $current_seq + 1;
            
            // Check if the new position is valid
            $min_seq = $db->getValue("
                SELECT MIN(mp_op_seq) 
                FROM mach_planning 
                WHERE mp_op_mach = ? AND mp_op_date = ? AND mp_op_shift = ?
            ", [$current_job['mp_op_mach'], $current_job['mp_op_date'], $current_job['mp_op_shift']]);
            
            $max_seq = $db->getValue("
                SELECT MAX(mp_op_seq) 
                FROM mach_planning 
                WHERE mp_op_mach = ? AND mp_op_date = ? AND mp_op_shift = ?
            ", [$current_job['mp_op_mach'], $current_job['mp_op_date'], $current_job['mp_op_shift']]);
            
            if ($new_seq < $min_seq || $new_seq > $max_seq) {
                throw new Exception('Cannot move job beyond sequence bounds');
            }
            
            // Find the job at the target position
            $target_job = $db->getRow("
                SELECT mp_op_id 
                FROM mach_planning 
                WHERE mp_op_mach = ? AND mp_op_date = ? AND mp_op_shift = ? AND mp_op_seq = ?
            ", [$current_job['mp_op_mach'], $current_job['mp_op_date'], $current_job['mp_op_shift'], $new_seq]);
            
            if ($target_job) {
                // Swap the sequence numbers
                $db->query("UPDATE mach_planning SET mp_op_seq = ? WHERE mp_op_id = ?", [$current_seq, $target_job['mp_op_id']]);
                $db->query("UPDATE mach_planning SET mp_op_seq = ? WHERE mp_op_id = ?", [$new_seq, $input['planning_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Job position updated']);
            } else {
                throw new Exception('Target position not found');
            }
            break;
            
        case 'clear_job_sequence':
            if (!isset($input['job_id'])) {
                throw new Exception('Missing job_id for clear_job_sequence');
            }
            
            $result = $db->query("
                UPDATE operations 
                SET op_seq = 0 
                WHERE op_id = ?
            ", [$input['job_id']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Job sequence cleared']);
            } else {
                throw new Exception('Failed to clear job sequence');
            }
            break;
            
        default:
            throw new Exception('Unknown action: ' . $input['action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>