<?php
/**
 * Health Check Endpoint
 * Simple endpoint to verify API is working
 */

function handle_health($input, $response) {
    try {
        // Test database connection
        $db = db();
        $db->getOne("SELECT 1");
        
        $health_data = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => 'connected',
            'version' => '1.0.0'
        ];
        
        return $response->success($health_data, 'API is healthy');
        
    } catch (Exception $e) {
        return $response->serverError('Health check failed', $e->getMessage());
    }
}