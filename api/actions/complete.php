<?php
/**
 * Complete Action Endpoint
 * Handles job completion with final quantity
 */

function handle_complete($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_status, op_pln_prdqty FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Check if job can be completed (not already complete)
        if ($operation['op_status'] == 10) {
            return $response->statusConflict('Complete', 'Active');
        }
        
        // Get form data
        $final_qty = floatval($input['final_qty'] ?? 0);
        $reject_qty = floatval($input['reject_qty'] ?? 0);
        $rmks = $input['rmks'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if ($final_qty <= 0) {
            return $response->missingFields('final_qty');
        }
        
        // Validate quantities
        if ($reject_qty < 0) {
            return $response->validationError(['reject_qty' => 'Reject quantity cannot be negative']);
        }
        
        if ($final_qty > ($operation['op_pln_prdqty'] * 1.1)) {
            return $response->validationError(['final_qty' => 'Final quantity significantly exceeds planned quantity']);
        }
        
        // Get additional job details for inventory update
        $job_details = $db->getRow(
            "SELECT p.pl_lot, p.pl_itm, w.im_id 
            FROM planning p 
            LEFT JOIN wip_items w ON p.pl_itm = w.im_itm AND p.pl_lot = w.im_lot
            WHERE p.pl_id = ?",
            [$planning_id]
        );
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update operation status to Complete (10)
            $result = $db->execute(
                "UPDATE operations SET 
                    op_status = 10,
                    op_act_prdqty = ?,
                    op_end_time = NOW(),
                    op_remarks = ?
                WHERE op_id = ?",
                [$final_qty, $rmks, $operation['op_id']]
            );
            
            if (!$result) {
                throw new Exception('Failed to update operation status');
            }
            
            // Update inventory if WIP item exists
            if ($job_details['im_id']) {
                $net_qty = $final_qty - $reject_qty;
                $db->execute(
                    "UPDATE wip_items SET im_stkqty = im_stkqty + ? WHERE im_id = ?",
                    [$net_qty, $job_details['im_id']]
                );
            }
            
            // Record reject quantity if any
            if ($reject_qty > 0) {
                $db->execute(
                    "INSERT INTO quality_rejects (qr_opid, qr_qty, qr_reason, qr_timestamp, qr_usr) 
                    VALUES (?, ?, 'Completion reject', NOW(), ?)",
                    [$operation['op_id'], $reject_qty, $operator]
                );
            }
            
            // Create notification
            $notification_text = "Job Completed: " . $final_qty . " pcs";
            if ($reject_qty > 0) {
                $notification_text .= " (" . $reject_qty . " rejected)";
            }
            
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Complete')",
                [$notification_text, $operation['op_id']]
            );
            
            // Log action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'complete', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode([
                    'final_qty' => $final_qty,
                    'reject_qty' => $reject_qty,
                    'remarks' => $rmks
                ])]
            );
            
            $db->commit();
            
            // Calculate completion metrics
            $completion_percent = round(($final_qty / $operation['op_pln_prdqty']) * 100, 1);
            $net_qty = $final_qty - $reject_qty;
            
            return $response->jobActionSuccess($planning_id, 'complete', [
                'operation_id' => $operation['op_id'],
                'status' => 'Complete',
                'final_quantity' => $final_qty,
                'reject_quantity' => $reject_qty,
                'net_quantity' => $net_qty,
                'planned_quantity' => $operation['op_pln_prdqty'],
                'completion_percent' => $completion_percent,
                'remarks' => $rmks,
                'completion_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Complete action error: ' . $e->getMessage());
        return $response->serverError('Complete action failed');
    }
}