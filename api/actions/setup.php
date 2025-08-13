<?php
/**
 * Setup Action Endpoint
 * Handles job setup initiation
 */

function handle_setup($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_status FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Check if job can be started (status should be New or Assigned)
        if (!in_array($operation['op_status'], [1, 2])) {
            $status_names = [3 => 'Setup', 4 => 'FPQC', 5 => 'In Process', 6 => 'Paused', 7 => 'Breakdown', 10 => 'Complete'];
            $current_status = $status_names[$operation['op_status']] ?? 'Unknown';
            return $response->statusConflict($current_status, 'New or Assigned');
        }
        
        // Get form data
        $msg = $input['msg'] ?? '';
        $dtsp = $input['dtsp'] ?? date('Y-m-d');
        $dtsp_hora = $input['dtsp_hora'] ?? date('H:i:s');
        $rmks = $input['rmks'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($msg)) {
            return $response->missingFields('msg');
        }
        
        // Limit message length
        if (strlen($msg) > 20) {
            return $response->validationError(['msg' => 'Message must be 20 characters or less']);
        }
        
        // Limit remarks length
        if (strlen($rmks) > 100) {
            return $response->validationError(['rmks' => 'Remarks must be 100 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update operation status to Setup (3)
            $result = $db->execute(
                "UPDATE operations SET 
                    op_status = 3,
                    op_start_time = CONCAT(?, ' ', ?),
                    op_msg = ?,
                    op_remarks = ?
                WHERE op_id = ?",
                [$dtsp, $dtsp_hora, $msg, $rmks, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Create notification
            $notification_text = "Setup started: " . $msg;
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Setup')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'setup', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode(['msg' => $msg, 'remarks' => $rmks])]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'setup', [
                'operation_id' => $operation['op_id'],
                'status' => 'Setup',
                'setup_time' => $dtsp . ' ' . $dtsp_hora,
                'message' => $msg
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Setup action error: ' . $e->getMessage());
        return $response->serverError('Setup action failed');
    }
}