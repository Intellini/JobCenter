<?php
/**
 * Control Chart Action Endpoint
 * Handles quality control chart data retrieval
 */

function handle_control_chart($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_itm, op_opn FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Get operation details for control chart context
        $operation_details = $db->getRow(
            "SELECT opn_desc, opn_setup FROM operations_master WHERE opn_id = ?",
            [$operation['op_opn']]
        );
        
        // Get item details
        $item_details = $db->getRow(
            "SELECT im_desc, im_drw FROM items WHERE im_id = ?",
            [$operation['op_itm']]
        );
        
        // Get recent quality test data for this operation/item combination (last 30 days)
        $quality_data = $db->getAll(
            "SELECT qt.*, op.op_planning_id, op.op_lot 
            FROM quality_tests qt
            JOIN operations op ON qt.qt_opid = op.op_id
            WHERE op.op_itm = ? AND op.op_opn = ? 
            AND qt.qt_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY qt.qt_timestamp DESC
            LIMIT 100",
            [$operation['op_itm'], $operation['op_opn']]
        );
        
        // Get control limits from quality specifications
        $control_limits = $db->getAll(
            "SELECT qs_characteristic, qs_target, qs_upper_limit, qs_lower_limit, qs_unit
            FROM quality_specs
            WHERE qs_item = ? AND qs_operation = ?",
            [$operation['op_itm'], $operation['op_opn']]
        );
        
        // Organize data by test type/characteristic
        $chart_data = [];
        $test_types = [];
        
        foreach ($quality_data as $test) {
            $type = $test['qt_type'];
            if (!in_array($type, $test_types)) {
                $test_types[] = $type;
            }
            
            if (!isset($chart_data[$type])) {
                $chart_data[$type] = [
                    'type' => $type,
                    'unit' => $test['qt_unit'],
                    'data_points' => [],
                    'control_limits' => null
                ];
            }
            
            $chart_data[$type]['data_points'][] = [
                'timestamp' => $test['qt_timestamp'],
                'value' => floatval($test['qt_value']),
                'result' => $test['qt_result'],
                'lot' => $test['op_lot'],
                'planning_id' => $test['op_planning_id']
            ];
        }
        
        // Add control limits to chart data
        foreach ($control_limits as $limit) {
            $characteristic = $limit['qs_characteristic'];
            if (isset($chart_data[$characteristic])) {
                $chart_data[$characteristic]['control_limits'] = [
                    'target' => floatval($limit['qs_target']),
                    'upper_limit' => floatval($limit['qs_upper_limit']),
                    'lower_limit' => floatval($limit['qs_lower_limit']),
                    'unit' => $limit['qs_unit']
                ];
            }
        }
        
        // Calculate statistics for each test type
        foreach ($chart_data as $type => &$data) {
            if (!empty($data['data_points'])) {
                $values = array_column($data['data_points'], 'value');
                $data['statistics'] = [
                    'count' => count($values),
                    'mean' => round(array_sum($values) / count($values), 4),
                    'min' => min($values),
                    'max' => max($values),
                    'range' => max($values) - min($values),
                    'std_dev' => $this->calculateStandardDeviation($values)
                ];
                
                // Calculate process capability if control limits exist
                if ($data['control_limits']) {
                    $ucl = $data['control_limits']['upper_limit'];
                    $lcl = $data['control_limits']['lower_limit'];
                    $mean = $data['statistics']['mean'];
                    $std_dev = $data['statistics']['std_dev'];
                    
                    if ($std_dev > 0) {
                        $cp = ($ucl - $lcl) / (6 * $std_dev);
                        $cpk = min(($ucl - $mean) / (3 * $std_dev), ($mean - $lcl) / (3 * $std_dev));
                        
                        $data['capability'] = [
                            'cp' => round($cp, 3),
                            'cpk' => round($cpk, 3)
                        ];
                    }
                }
            }
        }
        
        // Log access for audit trail
        $operator = $input['operator'] ?? 'system';
        $db->execute(
            "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
            VALUES (?, 'control_chart_view', ?, NOW(), ?)",
            [$planning_id, $operator, json_encode(['test_types' => $test_types])]
        );
        
        // Prepare response
        $response_data = [
            'operation_id' => $operation['op_id'],
            'item_id' => $operation['op_itm'],
            'operation_number' => $operation['op_opn'],
            'item_description' => $item_details['im_desc'] ?? '',
            'operation_description' => $operation_details['opn_desc'] ?? '',
            'chart_data' => array_values($chart_data),
            'data_period' => 'Last 30 days',
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        return $response->success($response_data, 'Control chart data retrieved successfully');
        
    } catch (Exception $e) {
        error_log('Control chart action error: ' . $e->getMessage());
        return $response->serverError('Control chart retrieval failed');
    }
}

/**
 * Calculate standard deviation
 */
function calculateStandardDeviation($values) {
    if (count($values) < 2) {
        return 0;
    }
    
    $mean = array_sum($values) / count($values);
    $variance = 0;
    
    foreach ($values as $value) {
        $variance += pow($value - $mean, 2);
    }
    
    $variance = $variance / (count($values) - 1); // Sample standard deviation
    return round(sqrt($variance), 4);
}