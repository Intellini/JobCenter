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