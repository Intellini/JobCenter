<?php
/**
 * Drawing Action Endpoint
<<<<<<< HEAD
 * Handles technical drawing requests
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

try {
    // For this simplified implementation, just log the request
    $job_id = intval($_POST['job_id'] ?? 0);
    $operator = $_SESSION['operator_name'] ?? 'system';
    
    if ($job_id <= 0) {
        echo $response->invalidJobId($job_id);
        exit;
    }
    
    // In a full implementation, this would:
    // 1. Retrieve technical drawings from document management system
    // 2. Log the access for audit purposes
    // 3. Return drawing URLs or file paths
    
    echo $response->jobActionSuccess($job_id, 'drawing', [
        'message' => 'Drawing access logged',
        'operator' => $operator
    ]);
    
} catch (Exception $e) {
    error_log('Drawing action error: ' . $e->getMessage());
    echo $response->serverError('Drawing action failed');
}
?>
=======
 * Handles technical drawing/document retrieval
 */

function handle_drawing($input, $response) {
    try {
        $planning_id = intval($input['planning_id']);
        
        if ($planning_id <= 0) {
            return $response->invalidPlanningId($planning_id);
        }
        
        $db = db();
        
        // Validate job exists and get operation details
        $operation = $db->getRow(
            "SELECT op_id, op_itm, op_rev FROM operations WHERE op_planning_id = ?",
            [$planning_id]
        );
        
        if (!$operation) {
            return $response->jobNotFound($planning_id);
        }
        
        // Get item details for drawing lookup
        $item_details = $db->getRow(
            "SELECT im_drw, im_desc, im_rev FROM items WHERE im_id = ?",
            [$operation['op_itm']]
        );
        
        if (!$item_details) {
            return $response->error('Item details not found', 'ITEM_NOT_FOUND', 404);
        }
        
        // Look for drawing files in various formats and locations
        $drawing_paths = [];
        $base_drawing_path = '/drawings/';
        $drawing_number = $item_details['im_drw'];
        $revision = $operation['op_rev'] ?? $item_details['im_rev'] ?? 'A';
        
        // Possible file extensions for drawings
        $extensions = ['pdf', 'dwg', 'dxf', 'step', 'stp', 'iges', 'igs'];
        
        // Build possible file paths
        foreach ($extensions as $ext) {
            // Format: DRAWING-REV.ext
            $drawing_paths[] = $base_drawing_path . $drawing_number . '-' . $revision . '.' . $ext;
            // Format: DRAWING.ext (no revision)
            $drawing_paths[] = $base_drawing_path . $drawing_number . '.' . $ext;
        }
        
        // Check which files actually exist
        $available_files = [];
        foreach ($drawing_paths as $path) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
            if (file_exists($full_path)) {
                $file_info = [
                    'path' => $path,
                    'full_path' => $full_path,
                    'filename' => basename($path),
                    'extension' => pathinfo($path, PATHINFO_EXTENSION),
                    'size' => filesize($full_path),
                    'modified' => date('Y-m-d H:i:s', filemtime($full_path))
                ];
                $available_files[] = $file_info;
            }
        }
        
        // If no files found, check database for drawing links
        if (empty($available_files)) {
            $drawing_links = $db->getAll(
                "SELECT dl_type, dl_path, dl_desc FROM drawing_links WHERE dl_item = ? ORDER BY dl_priority",
                [$operation['op_itm']]
            );
            
            foreach ($drawing_links as $link) {
                $available_files[] = [
                    'path' => $link['dl_path'],
                    'type' => $link['dl_type'],
                    'description' => $link['dl_desc'],
                    'is_link' => true
                ];
            }
        }
        
        // Log access for audit trail
        $operator = $input['operator'] ?? 'system';
        $db->execute(
            "INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp, ja_details) 
            VALUES (?, 'drawing_view', ?, NOW(), ?)",
            [$planning_id, $operator, json_encode(['drawing_number' => $drawing_number, 'revision' => $revision])]
        );
        
        // Prepare response data
        $response_data = [
            'operation_id' => $operation['op_id'],
            'item_id' => $operation['op_itm'],
            'drawing_number' => $drawing_number,
            'revision' => $revision,
            'item_description' => $item_details['im_desc'],
            'files' => $available_files,
            'file_count' => count($available_files)
        ];
        
        if (empty($available_files)) {
            return $response->success($response_data, 'No drawing files found for this item');
        }
        
        // Add primary file (usually PDF if available)
        $primary_file = null;
        foreach ($available_files as $file) {
            if (isset($file['extension']) && strtolower($file['extension']) === 'pdf') {
                $primary_file = $file;
                break;
            }
        }
        
        if (!$primary_file && !empty($available_files)) {
            $primary_file = $available_files[0];
        }
        
        if ($primary_file) {
            $response_data['primary_file'] = $primary_file;
        }
        
        return $response->success($response_data, 'Drawing information retrieved successfully');
        
    } catch (Exception $e) {
        error_log('Drawing action error: ' . $e->getMessage());
        return $response->serverError('Drawing retrieval failed');
    }
}
>>>>>>> Initial commit: Job Center simplified tablet interface
