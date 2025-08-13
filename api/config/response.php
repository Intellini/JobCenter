<?php
/**
 * API Response Handler
 * Provides standardized JSON response formatting
 */

class ApiResponse {
    
    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $http_code HTTP status code
     * @return string JSON response
     */
    public function success($data = null, $message = 'Success', $http_code = 200) {
        http_response_code($http_code);
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param string $code Error code
     * @param int $http_code HTTP status code
     * @param mixed $details Additional error details
     * @return string JSON response
     */
    public function error($message = 'Error occurred', $code = 'ERROR', $http_code = 400, $details = null) {
        http_response_code($http_code);
        
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Send validation error response
     * 
     * @param array $errors Array of validation errors
     * @param string $message General error message
     * @return string JSON response
     */
    public function validationError($errors, $message = 'Validation failed') {
        return $this->error($message, 'VALIDATION_ERROR', 422, $errors);
    }
    
    /**
     * Send not found response
     * 
     * @param string $resource Resource name that was not found
     * @return string JSON response
     */
    public function notFound($resource = 'Resource') {
        return $this->error($resource . ' not found', 'NOT_FOUND', 404);
    }
    
    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     * @return string JSON response
     */
    public function unauthorized($message = 'Unauthorized access') {
        return $this->error($message, 'UNAUTHORIZED', 401);
    }
    
    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     * @return string JSON response
     */
    public function forbidden($message = 'Access forbidden') {
        return $this->error($message, 'FORBIDDEN', 403);
    }
    
    /**
     * Send server error response
     * 
     * @param string $message Error message
     * @param mixed $details Error details
     * @return string JSON response
     */
    public function serverError($message = 'Internal server error', $details = null) {
        return $this->error($message, 'SERVER_ERROR', 500, $details);
    }
    
    /**
     * Send database error response
     * 
     * @param string $operation Database operation that failed
     * @param string $details Error details (optional)
     * @return string JSON response
     */
    public function databaseError($operation = 'Database operation', $details = null) {
        return $this->error($operation . ' failed', 'DATABASE_ERROR', 500, $details);
    }
    
    /**
     * Send job-specific responses
     */
    
    /**
     * Job status updated successfully
     * 
     * @param int $job_id Job ID
     * @param string $action Action performed
     * @param mixed $data Additional data
     * @return string JSON response
     */
    public function jobActionSuccess($job_id, $action, $data = null) {
        $response_data = [
            'job_id' => $job_id,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response_data = array_merge($response_data, $data);
        }
        
        return $this->success($response_data, ucfirst($action) . ' completed successfully');
    }
    
    /**
     * Invalid job ID response
     * 
     * @param int $job_id Job ID that was invalid
     * @return string JSON response
     */
    public function invalidJobId($job_id) {
        return $this->error('Invalid job ID: ' . $job_id, 'INVALID_JOB_ID', 404);
    }
    
    /**
     * Job not found response
     * 
     * @param int $job_id Job ID
     * @return string JSON response
     */
    public function jobNotFound($job_id) {
        return $this->error('Job not found for ID: ' . $job_id, 'JOB_NOT_FOUND', 404);
    }
    
    /**
     * Job status conflict response
     * 
     * @param string $current_status Current job status
     * @param string $required_status Required status for action
     * @return string JSON response
     */
    public function statusConflict($current_status, $required_status) {
        return $this->error(
            'Cannot perform action. Current status: ' . $current_status . ', Required: ' . $required_status,
            'STATUS_CONFLICT',
            409
        );
    }
    
    /**
     * Required field missing response
     * 
     * @param string|array $fields Missing field(s)
     * @return string JSON response
     */
    public function missingFields($fields) {
        $field_list = is_array($fields) ? implode(', ', $fields) : $fields;
        return $this->error('Required field(s) missing: ' . $field_list, 'MISSING_FIELDS', 422);
    }
    
    /**
     * Format response for debugging
     * 
     * @param mixed $data Debug data
     * @return string JSON response
     */
    public function debug($data) {
        return $this->success($data, 'Debug information');
    }
}

/**
 * Helper function to create response instance
 * 
 * @return ApiResponse
 */
function response() {
    return new ApiResponse();
}