<?php
/**
 * FPQC Action Endpoint
 * Handles First Piece QC requests
 */

function handle_fpqc($input, $response) {
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
        
        // Check if job is in Setup or In Process status
        if (!in_array($operation['op_status'], [3, 5])) {
            $status_names = [
                1 => 'New', 2 => 'Assigned', 4 => 'FPQC', 6 => 'Paused', 
                7 => 'Breakdown', 10 => 'Complete', 12 => 'QC Hold'
            ];
            $current_status = $status_names[$operation['op_status']] ?? 'Unknown';
            return $response->statusConflict($current_status, 'Setup or In Process');
        }
        
        // Get form data
        $opq_act_prdqty = intval($input['opq_act_prdqty'] ?? 0);
        $opq_qc_qty = intval($input['opq_qc_qty'] ?? 0);
        $opq_rjk_qty = intval($input['opq_rjk_qty'] ?? 0);
        $opq_nc_qty = intval($input['opq_nc_qty'] ?? 0);
        $opq_qc_rmks = $input['opq_qc_rmks'] ?? '';
        $opq_reason = $input['opq_reason'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if ($opq_act_prdqty <= 0) {
            return $response->missingFields('opq_act_prdqty (actual production quantity)');
        }
        
        if ($opq_qc_qty <= 0) {
            return $response->missingFields('opq_qc_qty (QC quantity)');
        }
        
        // Validate quantities make sense
        if ($opq_qc_qty > $opq_act_prdqty) {
            return $response->validationError(['opq_qc_qty' => 'QC quantity cannot exceed actual production quantity']);
        }
        
        if (($opq_rjk_qty + $opq_nc_qty) > $opq_qc_qty) {
            return $response->validationError(['quantities' => 'Reject and non-conformance quantities cannot exceed QC quantity']);
        }
        
        // Limit remarks length
        if (strlen($opq_qc_rmks) > 255) {
            return $response->validationError(['opq_qc_rmks' => 'QC remarks must be 255 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update operation status to FPQC (4)
            $result = $db->execute(
                "UPDATE operations SET 
                    op_status = 4,
                    op_act_prdqty = ?
                WHERE op_id = ?",
                [$opq_act_prdqty, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Get next revision number for QC record
            $next_rev = $db->getOne(
                "SELECT COALESCE(MAX(opq_qc_rev), 0) + 1 FROM operations_quality WHERE opq_opid = ?",
                [$operation['op_id']]
            );
            
            // Create QC record
            $qc_data = [
                'opq_opid' => $operation['op_id'],
                'opq_status' => 4, // FPQC status
                'opq_qc_rev' => $next_rev,
                'opq_act_prdqty' => $opq_act_prdqty,
                'opq_qc_qty' => $opq_qc_qty,
                'opq_rjk_qty' => $opq_rjk_qty,
                'opq_nc_qty' => $opq_nc_qty,
                'opq_qc_rmks' => $opq_qc_rmks,
                'opq_reason' => $opq_reason,
                'opq_createdt' => date('Y-m-d H:i:s'),
                'opq_usr' => $operator
            ];
            
            $qc_result = $db->insert('operations_quality', $qc_data);
            if (!$qc_result) {
                throw new Exception('Failed to create QC record');
            }
            
            // Create notification for QC team
            $notification_text = "FPQC Required - " . $opq_act_prdqty . " pieces produced";
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'FPQC')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $action_details = [
                'act_prdqty' => $opq_act_prdqty,
                'qc_qty' => $opq_qc_qty,
                'reject_qty' => $opq_rjk_qty,
                'nc_qty' => $opq_nc_qty,
                'remarks' => $opq_qc_rmks,
                'reason' => $opq_reason
            ];
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'fpqc', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'fpqc', [
                'operation_id' => $operation['op_id'],
                'status' => 'FPQC',
                'qc_revision' => $next_rev,
                'actual_quantity' => $opq_act_prdqty,
                'qc_quantity' => $opq_qc_qty,
                'reject_quantity' => $opq_rjk_qty,
                'nc_quantity' => $opq_nc_qty,
                'remarks' => $opq_qc_rmks
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('FPQC action error: ' . $e->getMessage());
        return $response->serverError('FPQC action failed');
    }
}