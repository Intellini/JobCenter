<?php
/**
 * Testing Action Endpoint
<<<<<<< HEAD
 * Handles test result recording
 */

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
    
    // Validate job exists
    $job = $db->getRow(
        "SELECT op_id, op_status FROM operations WHERE op_id = ?",
        [$job_id]
    );
    
    if (!$job) {
        echo $response->jobNotFound($job_id);
        exit;
    }
    
    // Get form data
    $test_type = $input['test_type'] ?? '';
    $test_value = floatval($input['test_value'] ?? 0);
    $test_unit = $input['test_unit'] ?? '';
    $pass_fail = $input['pass_fail'] ?? '';
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    // Validate required fields
    if (empty($test_type)) {
        echo $response->missingFields('test_type');
        exit;
    }
    
    if (empty($test_unit)) {
        echo $response->missingFields('test_unit');
        exit;
    }
    
    if (!in_array($pass_fail, ['pass', 'fail'])) {
        echo $response->missingFields('pass_fail');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert test record
        $test_data = [
            'tr_op_id' => $job['op_id'],
            'tr_type' => $test_type,
            'tr_value' => $test_value,
            'tr_unit' => $test_unit,
            'tr_result' => $pass_fail,
            'tr_timestamp' => date('Y-m-d H:i:s'),
            'tr_operator' => $operator
        ];
        
        $result = $db->insert('test_results', $test_data);
        
        if (!$result) {
            throw new Exception('Failed to record test result');
        }
        
        // Create notification
        $notification_text = "Test Recorded: " . $test_type . " - " . strtoupper($pass_fail);
        $db->execute(
            "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
            VALUES (?, 0, NOW(), 'operations', ?, 'Test')",
            [$notification_text, $job['op_id']]
        );
        
        $db->commit();
        
        echo $response->jobActionSuccess($job_id, 'testing', [
            'operation_id' => $job['op_id'],
            'test_type' => $test_type,
            'test_value' => $test_value,
            'test_unit' => $test_unit,
            'result' => $pass_fail
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Testing action error: ' . $e->getMessage());
    echo $response->serverError('Testing action failed');
}
?>
=======
 * Handles quality test result recording
 */

function handle_testing($input, $response) {
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
        
        // Check if job is in active status (Setup, FPQC, or In Process)
        if (!in_array($operation['op_status'], [3, 4, 5])) {
            $status_names = [
                1 => 'New', 2 => 'Assigned', 6 => 'Paused', 
                7 => 'Breakdown', 10 => 'Complete', 12 => 'QC Hold'
            ];
            $current_status = $status_names[$operation['op_status']] ?? 'Unknown';
            return $response->statusConflict($current_status, 'Setup, FPQC, or In Process');
        }
        
        // Get form data
        $test_type = $input['test_type'] ?? '';
        $test_value = floatval($input['test_value'] ?? 0);
        $test_unit = $input['test_unit'] ?? '';
        $pass_fail = $input['pass_fail'] ?? '';
        $operator = $input['operator'] ?? 'system';
        
        // Validate required fields
        if (empty($test_type)) {
            return $response->missingFields('test_type');
        }
        
        if ($test_value === 0.0 && $input['test_value'] !== '0') {
            return $response->missingFields('test_value');
        }
        
        if (empty($test_unit)) {
            return $response->missingFields('test_unit');
        }
        
        if (empty($pass_fail) || !in_array(strtolower($pass_fail), ['pass', 'fail'])) {
            return $response->validationError(['pass_fail' => 'Must be either "pass" or "fail"']);
        }
        
        // Validate test type (should be from predefined list)
        $valid_test_types = [
            'dimensional', 'surface_finish', 'hardness', 'visual', 
            'functional', 'torque', 'pressure', 'temperature', 'other'
        ];
        
        if (!in_array($test_type, $valid_test_types)) {
            return $response->validationError(['test_type' => 'Invalid test type']);
        }
        
        // Validate test unit
        $valid_units = [
            'mm', 'in', 'um', 'mil', 'kg', 'lb', 'N', 'Nm', 
            'psi', 'bar', 'C', 'F', 'HRC', 'HRB', 'Ra', 'count'
        ];
        
        if (!in_array($test_unit, $valid_units)) {
            return $response->validationError(['test_unit' => 'Invalid measurement unit']);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Record test result
            $test_data = [
                'qt_opid' => $operation['op_id'],
                'qt_type' => $test_type,
                'qt_value' => $test_value,
                'qt_unit' => $test_unit,
                'qt_result' => strtolower($pass_fail),
                'qt_timestamp' => date('Y-m-d H:i:s'),
                'qt_usr' => $operator
            ];
            
            $test_result = $db->insert('quality_tests', $test_data);
            if (!$test_result) {
                throw new Exception('Failed to record test result');
            }
            
            $test_id = $db->lastInsertId();
            
            // Create notification if test failed
            if (strtolower($pass_fail) === 'fail') {
                $notification_text = "Quality Test Failed: " . $test_type . " = " . $test_value . " " . $test_unit;
                $db->execute(
                    "INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action) 
                    VALUES (?, 0, NOW(), 'operations', ?, 'Test Failed')",
                    [$notification_text, $operation['op_id']]
                );
            }
            
            // Log action
            $action_details = [
                'test_type' => $test_type,
                'test_value' => $test_value,
                'test_unit' => $test_unit,
                'result' => strtolower($pass_fail),
                'test_id' => $test_id
            ];
            
            $db->execute(
                "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
                VALUES (?, 'testing', ?, NOW(), ?)",
                [$planning_id, $operator, json_encode($action_details)]
            );
            
            $db->commit();
            
            return $response->jobActionSuccess($planning_id, 'testing', [
                'operation_id' => $operation['op_id'],
                'test_id' => $test_id,
                'test_type' => $test_type,
                'test_value' => $test_value,
                'test_unit' => $test_unit,
                'result' => strtolower($pass_fail),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Testing action error: ' . $e->getMessage());
        return $response->serverError('Testing action failed');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
