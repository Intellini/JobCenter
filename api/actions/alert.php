<?php
/**
 * Alert Action Endpoint
 * Handles production issue/alert reports
 */

function handle_alert($input, $response) {
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
        
        // Check if job is in active status (not complete)
        if ($operation['op_status'] == 10) {
            return $response->statusConflict('Complete', 'Active status');
        }
        
        // Get form data
        $issue_type = $input['issue_type'] ?? '';
        $severity = $input['severity'] ?? '';
        $description = $input['description'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($issue_type)) {
            return $response->missingFields('issue_type');
        }
        
        if (empty($severity)) {
            return $response->missingFields('severity');
        }
        
        if (empty($description)) {
            return $response->missingFields('description');
        }
        
        // Validate issue type
        $valid_issue_types = [
            'quality', 'material', 'tooling', 'process', 'safety', 
            'equipment', 'measurement', 'documentation', 'other'
        ];
        
        if (!in_array($issue_type, $valid_issue_types)) {
            return $response->validationError(['issue_type' => 'Invalid issue type']);
        }
        
        // Validate severity
        $valid_severities = ['low', 'medium', 'high', 'critical'];
        
        if (!in_array($severity, $valid_severities)) {
            return $response->validationError(['severity' => 'Invalid severity level']);
        }
        
        // Limit description length
        if (strlen($description) > 500) {
            return $response->validationError(['description' => 'Description must be 500 characters or less']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Log production issue
            $issue_data = [
                'pi_opid' => $operation['op_id'],
                'pi_type' => $issue_type,
                'pi_severity' => $severity,
                'pi_desc' => $description,
                'pi_timestamp' => date('Y-m-d H:i:s'),
                'pi_usr' => $operator,
                'pi_status' => 'open'
            ];
            
            $issue_result = $db->insert('production_issues', $issue_data);
            if (!$issue_result) {
                throw new Exception('Failed to log production issue');
            }
            
            $issue_id = $db->lastInsertId();
            
            // Create notification based on severity
            $severity_text = strtoupper($severity);
            $notification_text = "Production Issue (" . $severity_text . "): " . $issue_type . " - " . substr($description, 0, 50);
            if (strlen($description) > 50) {
                $notification_text .= "...";
            }
            
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Issue')",
                [$notification_text, $operation['op_id']]
            );
            
            // For high/critical issues, also create urgent notification
            if (in_array($severity, ['high', 'critical'])) {
                $urgent_text = "URGENT: " . $notification_text;
                $db->execute(
                    "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action, nm_priority) 
                    VALUES (?, 0, NOW(), 'operations', ?, 'Urgent Issue', 1)",
                    [$urgent_text, $operation['op_id']]
                );
            }
            
            // Log action
            $action_details = [
                'issue_type' => $issue_type,
                'severity' => $severity,
                'description' => $description,
                'issue_id' => $issue_id
            ];
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'alert', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'alert', [
                'operation_id' => $operation['op_id'],
                'issue_id' => $issue_id,
                'issue_type' => $issue_type,
                'severity' => $severity,
                'description' => $description,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Alert action error: ' . $e->getMessage());
        return $response->serverError('Alert action failed');
    }
}