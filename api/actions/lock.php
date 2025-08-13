<?php
/**
 * Lock Action Endpoint
<<<<<<< HEAD
 * Handles screen lock functionality
 */

session_start();
header('Content-Type: application/json');

// Check if operator is logged in
if (!isset($_SESSION['operator_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../config/response.php';

$response = new ApiResponse();

try {
    // For this simplified implementation, just acknowledge the lock request
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // In a full implementation, this would:
    // 1. Set a screen lock flag in the session
    // 2. Require PIN or authentication to unlock
    // 3. Log the lock/unlock events
    
    echo $response->success([
        'status' => 'locked',
        'operator' => $operator,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Screen lock activated');
    
} catch (Exception $e) {
    error_log('Lock action error: ' . $e->getMessage());
    echo $response->serverError('Lock action failed');
}
?>
=======
 * Handles screen lock/unlock functionality
 */

function handle_lock($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        $action = $input['lock_action'] ?? ''; // 'lock' or 'unlock'
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        if (!in_array($action, ['lock', 'unlock'])) {
            return $response->validationError(['lock_action' => 'Must be either "lock" or "unlock"']);
        }
        
        $db = db();
        session_start();
        
        // Validate job exists
        $operation = $db->getRow(
            "SELECT op_id, op_status FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        $operator = $input['operator'] ?? 'system';
        
        if ($action === 'lock') {
            // Lock the screen
            $_SESSION['screen_locked'] = true;
            $_SESSION['lock_time'] = date('Y-m-d H:i:s');
            $_SESSION['lock_operator'] = $operator;
            $_SESSION['lock_planning_id'] = $planning_id;
            
            // Pause any active timers (this would be handled client-side as well)
            $_SESSION['timers_paused'] = true;
            
            // Log lock action
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'screen_lock', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode(['lock_time' => $_SESSION['lock_time']])]
            );
            
            return $response->success([
                'screen_locked' => true,
                'lock_time' => $_SESSION['lock_time'],
                'operator' => $operator
            ], 'Screen locked successfully');
            
        } else if ($action === 'unlock') {
            // Validate unlock credentials if provided
            $unlock_operator = $input['unlock_operator'] ?? '';
            $unlock_pin = $input['unlock_pin'] ?? '';
            
            // Check if unlock credentials are required and valid
            if (!empty($unlock_operator) && !empty($unlock_pin)) {
                // Verify operator PIN (this would typically check against a operators table)
                $operator_valid = $db->getOne(
                    "SELECT COUNT(*) FROM operators WHERE op_id = ? AND op_pin = ? AND op_active = 1",
                    [$unlock_operator, $unlock_pin]
                );
                
                if (!$operator_valid) {
                    return $response->error('Invalid operator credentials', 'INVALID_CREDENTIALS', 401);
                }
                
                $operator = $unlock_operator; // Use the unlocking operator's ID
            }
            
            // Calculate lock duration if was previously locked
            $lock_duration = null;
            if (isset($_SESSION['lock_time'])) {
                $lock_start = new DateTime($_SESSION['lock_time']);
                $lock_end = new DateTime();
                $duration = $lock_start->diff($lock_end);
                $lock_duration = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            }
            
            // Unlock the screen
            unset($_SESSION['screen_locked']);
            unset($_SESSION['timers_paused']);
            $unlock_time = date('Y-m-d H:i:s');
            
            // Log unlock action
            $action_details = [
                'unlock_time' => $unlock_time,
                'lock_duration_minutes' => $lock_duration,
                'unlock_operator' => $operator
            ];
            
            if (isset($_SESSION['lock_time'])) {
                $action_details['lock_time'] = $_SESSION['lock_time'];
            }
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'screen_unlock', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            // Clean up session variables
            unset($_SESSION['lock_time']);
            unset($_SESSION['lock_operator']);
            unset($_SESSION['lock_planning_id']);
            
            return $response->success([
                'screen_locked' => false,
                'unlock_time' => $unlock_time,
                'lock_duration_minutes' => $lock_duration,
                'operator' => $operator
            ], 'Screen unlocked successfully');
        }
        
    } catch (Exception $e) {
        error_log('Lock action error: ' . $e->getMessage());
        return $response->serverError('Lock action failed');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
