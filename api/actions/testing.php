<?php
/**
 * Testing Action Endpoint
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