<?php
/**
 * Contact Supervisor Endpoint
 * Sends alert to supervisor that operator needs help
 */

function handle_contact($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists
        $operation = $db->getRow(
            "SELECT op_id FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Get form data
        $issue_type = $input['issue_type'] ?? '';
        $message = $input['message'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($issue_type)) {
            return $response->missingFields('issue_type');
        }
        
        if (empty($message)) {
            return $response->missingFields('message');
        }
        
        // Define issue types
        $issue_types = [
            'technical' => 'Technical Issue',
            'material' => 'Material Problem',
            'quality' => 'Quality Question',
            'safety' => 'Safety Concern',
            'other' => 'Other'
        ];
        
        if (!array_key_exists($issue_type, $issue_types)) {
            return $response->validationError(['issue_type' => 'Invalid issue type']);
        }
        
        // Limit message length
        if (strlen($message) > 200) {
            return $response->validationError(['message' => 'Message must be 200 characters or less']);
        }
        
        try {
            // Create supervisor alert notification
            $notification_text = "Operator needs help: " . $issue_types[$issue_type] . " - " . $message;
            
            $db->execute(
                "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action, nm_priority) 
                VALUES (?, 0, NOW(), 'operations', ?, 'Contact', 1)",
                [$notification_text, $operation['op_id']]
            );
            
            // Log contact action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'contact', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode([
                    'issue_type' => $issue_type,
                    'message' => $message
                ])]
            );
            
            // Record in supervisor_alerts table if it exists
            $table_exists = $db->getOne(
                "SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'supervisor_alerts'"
            );
            
            if ($table_exists) {
                $db->execute(
                    "INSERT INTO supervisor_alerts (sa_opid, sa_type, sa_message, sa_operator, sa_timestamp, sa_status) 
                    VALUES (?, ?, ?, ?, NOW(), 'open')",
                    [$operation['op_id'], $issue_type, $message, $operator]
                );
            }
            
            return $response->jobActionSuccess($planning_id, 'contact', [
                'operation_id' => $operation['op_id'],
                'issue_type' => $issue_types[$issue_type],
                'message' => $message,
                'alert_sent' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Contact supervisor error: ' . $e->getMessage());
        return $response->serverError('Failed to contact supervisor');
    }
}