<?php
/**
 * QC Check Action Endpoint
 * Handles QC check requests during production
 */

function handle_qc_check($input, $response) {
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
        
        // Check if job is in production status (5 - In Process)
        if ($operation['op_status'] != 5) {
            $status_names = [
                1 => 'New', 2 => 'Assigned', 3 => 'Setup', 4 => 'FPQC', 
                6 => 'Paused', 7 => 'Breakdown', 10 => 'Complete', 12 => 'QC Hold'
            ];
            $current_status = $status_names[$operation['op_status']] ?? 'Unknown';
            return $response->statusConflict($current_status, 'In Process');
        }
        
        // Get form data
        $msg = $input['msg'] ?? '';
        $rmks = $input['rmks'] ?? '';
        $dtq = $input['dtq'] ?? date('Y-m-d H:i:s');
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($msg)) {
            return $response->missingFields('msg (message)');
        }
        
        // Limit message and remarks length
        if (strlen($msg) > 100) {
            return $response->validationError(['msg' => 'Message must be 100 characters or less']);
        }
        
        if (strlen($rmks) > 200) {
            return $response->validationError(['rmks' => 'Remarks must be 200 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Get next revision number for QC record
            $next_rev = $db->getOne(
                "SELECT COALESCE(MAX(opq_qc_rev), 0) + 1 FROM operations_quality WHERE opq_opid = ?",
                [$operation['op_id']]
            );
            
            // Create QC request record
            $qc_data = [
                'opq_opid' => $operation['op_id'],
                'opq_status' => 13, // QC Check status
                'opq_qc_rev' => $next_rev,
                'opq_qc_rmks' => $rmks,
                'opq_msg' => $msg,
                'opq_createdt' => $dtq,
                'opq_usr' => $operator
            ];
            
            $qc_result = $db->insert('operations_quality', $qc_data);
            if (!$qc_result) {
                throw new Exception('Failed to create QC check request');
            }
            
            // Create notification for QC team
            $notification_text = "QC Check Requested: " . $msg;
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'QC Check')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $action_details = [
                'message' => $msg,
                'remarks' => $rmks,
                'qc_datetime' => $dtq,
                'qc_revision' => $next_rev
            ];
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'qc_check', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'qc_check', [
                'operation_id' => $operation['op_id'],
                'status' => 'In Process',
                'qc_revision' => $next_rev,
                'qc_datetime' => $dtq,
                'message' => $msg,
                'remarks' => $rmks
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('QC Check action error: ' . $e->getMessage());
        return $response->serverError('QC Check action failed');
    }
}