<?php
/**
 * Breakdown Action Endpoint
 * Handles machine breakdown reports
 */

function handle_breakdown($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_status, op_mch_id, op_lot, op_itm FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Check if job can be marked as breakdown (not already in breakdown or complete)
        if (in_array($operation['op_status'], [7, 10])) {
            $status_names = [7 => 'Breakdown', 10 => 'Complete'];
            $current_status = $status_names[$operation['op_status']];
            return $response->statusConflict($current_status, 'Active status');
        }
        
        // Get form data
        $dtbd = $input['dtbd'] ?? date('Y-m-d H:i:s');
        $rmk = $input['rmk'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($rmk)) {
            return $response->missingFields('rmk (breakdown remarks)');
        }
        
        // Limit remarks length
        if (strlen($rmk) > 200) {
            return $response->validationError(['rmk' => 'Breakdown remarks must be 200 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Create maintenance task first to get task ID
            $task_title = "Machine Breakdown - Planning ID: " . $planning_id;
            $task_result = $db->execute(
                "INSERT INTO maintenance.tasks (title, project_id, created_at, created_by, description) 
                VALUES (?, ?, NOW(), 3, ?)",
                [$task_title, $operation['op_mch_id'], $rmk]
            );
            
            if (!$task_result) {
                throw new Exception('Failed to create maintenance task');
            }
            
            $task_id = $db->lastInsertId();
            
            // Set operation to breakdown status (7)
            $result = $db->execute(
                "UPDATE operations SET 
                    op_holdflg = op_status,
                    op_status = 7,
                    op_bkdown_task = ?
                WHERE op_id = ?",
                [$task_id, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Log downtime
            $downtime_result = $db->execute(
                "INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr, dt_remarks) 
                VALUES (1, 0, ?, ?, ?, ?, ?, NULL, NOW(), NOW(), ?, ?)",
                [
                    $operation['op_lot'],
                    $operation['op_itm'],
                    $operation['op_id'],
                    $operation['op_mch_id'],
                    $dtbd,
                    $operator,
                    $rmk
                ]
            );
            
            if (!$downtime_result) {
                throw new Exception('Failed to log downtime');
            }
            
            // Alert maintenance team
            $notification_text = "Machine Breakdown - " . $rmk;
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Breakdown')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $action_details = [
                'breakdown_time' => $dtbd,
                'remarks' => $rmk,
                'maintenance_task_id' => $task_id
            ];
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'breakdown', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'breakdown', [
                'operation_id' => $operation['op_id'],
                'status' => 'Breakdown',
                'breakdown_time' => $dtbd,
                'maintenance_task_id' => $task_id,
                'remarks' => $rmk
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Breakdown action error: ' . $e->getMessage());
        return $response->serverError('Breakdown action failed');
    }
}