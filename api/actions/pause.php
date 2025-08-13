<?php
/**
 * Pause Action Endpoint
 * Handles job pause with reason tracking
 */

<<<<<<< HEAD
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
    
    // Check if job can be paused
    if (in_array($job['op_status'], [6, 10])) {
        $status_names = [6 => 'Paused', 10 => 'Complete'];
        $current_status = $status_names[$job['op_status']];
        echo $response->statusConflict($current_status, 'Active');
        exit;
    }
    
    // Get form data
    $dtp = $input['dtp'] ?? date('Y-m-d H:i:s');
    $rsn = $input['rsn'] ?? '';
    $rmks = $input['rmks'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($rsn)) {
        echo $response->missingFields('rsn');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Save current status and set to paused (6)
        $result = $db->execute(
            "UPDATE operations SET 
                op_holdflg = op_status,
                op_status = 6,
                op_pause_time = ?
            WHERE op_id = ?",
            [$dtp, $job['op_id']]
        );
        
        if (!$result) {
            throw new Exception('Failed to update operation status');
        }
        
        // Create notification
        $notification_text = "Job paused: " . $rsn;
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Pause')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'pause', [
            'operation_id' => $job['op_id'],
            'status' => 'Paused',
            'pause_time' => $dtp,
            'reason' => $rsn
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Pause action error: ' . $e->getMessage());
    echo $response->serverError('Pause action failed');
}
?>
=======
function handle_pause($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_status, op_planning_id FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Check if job can be paused (not already paused, not complete, etc.)
        if (in_array($operation['op_status'], [6, 10])) {
            $status_names = [6 => 'Paused', 10 => 'Complete'];
            $current_status = $status_names[$operation['op_status']];
            return $response->statusConflict($current_status, 'Active');
        }
        
        // Get form data
        $dtp = $input['dtp'] ?? date('Y-m-d H:i:s');
        $rsn = intval($input['rsn'] ?? 0);
        $rmks = $input['rmks'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if ($rsn <= 0) {
            return $response->missingFields('rsn (reason)');
        }
        
        // Limit remarks length
        if (strlen($rmks) > 20) {
            return $response->validationError(['rmks' => 'Remarks must be 20 characters or less']);
        }
        
        // Get additional job details for downtime logging
        $job_details = $db->getRow(
            "SELECT p.pl_lot, p.pl_itm, p.pl_mch 
            FROM planning p 
            WHERE p.pl_id = ?",
            [$planning_id]
        );
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Save current status and set to paused (6)
            $result = $db->execute(
                "UPDATE operations SET 
                    op_holdflg = op_status,
                    op_status = 6,
                    op_pause_time = ?
                WHERE op_id = ?",
                [$dtp, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Log downtime
            $db->execute(
                "INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr) 
                VALUES (2, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW(), ?)",
                [
                    $rsn,
                    $job_details['pl_lot'],
                    $job_details['pl_itm'],
                    $operation['op_id'],
                    $job_details['pl_mch'],
                    $dtp,
                    $operator
                ]
            );
            
            // Create notification
            $reason_names = [
                1 => 'Break',
                2 => 'Material shortage',
                3 => 'Tool change',
                4 => 'Quality issue',
                5 => 'Other'
            ];
            $reason_text = $reason_names[$rsn] ?? 'Unknown reason';
            $notification_text = "Job paused: " . $reason_text;
            
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Pause')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'pause', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode(['reason' => $rsn, 'remarks' => $rmks])]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'pause', [
                'operation_id' => $operation['op_id'],
                'status' => 'Paused',
                'pause_time' => $dtp,
                'reason' => $reason_text,
                'remarks' => $rmks
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Pause action error: ' . $e->getMessage());
        return $response->serverError('Pause action failed');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
