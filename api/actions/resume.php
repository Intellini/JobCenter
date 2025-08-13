<?php
/**
 * Resume Action Endpoint
 * Handles job resume after pause
 */

function handle_resume($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_status, op_holdflg, op_pause_time FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Check if job is actually paused
        if ($operation['op_status'] != 6) {
            $status_names = [3 => 'Setup', 4 => 'FPQC', 5 => 'In Process', 10 => 'Complete'];
            $current_status = $status_names[$operation['op_status']] ?? 'Unknown';
            return $response->statusConflict($current_status, 'Paused');
        }
        
        // Check if pause time exists
        if (empty($operation['op_pause_time'])) {
            return $response->error('No pause time recorded', 'INVALID_STATE');
        }
        
        // Get form data
        $dtr = $input['dtr'] ?? date('Y-m-d H:i:s');
        $rmks = $input['rmks'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($rmks)) {
            return $response->missingFields('rmks (remarks)');
        }
        
        // Limit remarks length
        if (strlen($rmks) > 150) {
            return $response->validationError(['rmks' => 'Remarks must be 150 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Calculate pause duration and restore status
            $result = $db->execute(
                "UPDATE operations SET 
                    op_tot_pause = op_tot_pause + TIMESTAMPDIFF(MINUTE, op_pause_time, ?),
                    op_pause_time = NULL,
                    op_status = op_holdflg 
                WHERE op_id = ?",
                [$dtr, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Close downtime record
            $db->execute(
                "UPDATE downtime SET dt_endt = ? WHERE dt_type = 2 AND dt_opid = ? AND dt_endt IS NULL",
                [$dtr, $operation['op_id']]
            );
            
            // Create notification
            $notification_text = "Job resumed: " . $rmks;
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Resume')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'resume', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode(['remarks' => $rmks])]
            );
            
            // Calculate pause duration for response
            $pause_start = new DateTime($operation['op_pause_time']);
            $pause_end = new DateTime($dtr);
            $pause_duration = $pause_start->diff($pause_end);
            $pause_minutes = ($pause_duration->days * 24 * 60) + ($pause_duration->h * 60) + $pause_duration->i;
            
            $db->commit();
            
            // Get restored status name
            $status_names = [3 => 'Setup', 4 => 'FPQC', 5 => 'In Process'];
            $restored_status = $status_names[$operation['op_holdflg']] ?? 'Active';
            
            return $response->jobActionSuccess($planning_id, 'resume', [
                'operation_id' => $operation['op_id'],
                'status' => $restored_status,
                'resume_time' => $dtr,
                'pause_duration_minutes' => $pause_minutes,
                'remarks' => $rmks
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Resume action error: ' . $e->getMessage());
        return $response->serverError('Resume action failed');
    }
}