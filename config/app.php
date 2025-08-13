<?php
/**
 * Application Configuration
 */

return [
    // Application settings
    'app' => [
        'name' => 'Job Center',
        'version' => '2.0.0',
        'timezone' => 'Asia/Kolkata',
        'debug' => true,
    ],
    
    // Shift definitions
    'shifts' => [
        'A' => ['start' => '06:00', 'end' => '14:00', 'name' => 'Morning'],
        'B' => ['start' => '14:00', 'end' => '22:00', 'name' => 'Afternoon'],
        'C' => ['start' => '22:00', 'end' => '06:00', 'name' => 'Night'],
    ],
    
    // Status definitions
    'status' => [
        1 => ['name' => 'New', 'color' => '#6b7280'],
        2 => ['name' => 'Assigned', 'color' => '#8b5cf6'],
        3 => ['name' => 'Setup', 'color' => '#3b82f6'],
        4 => ['name' => 'FPQC', 'color' => '#f59e0b'],
        5 => ['name' => 'In Process', 'color' => '#10b981'],
        6 => ['name' => 'Paused', 'color' => '#f97316'],
        7 => ['name' => 'Breakdown', 'color' => '#ef4444'],
        8 => ['name' => 'On Hold', 'color' => '#ef4444'],
        9 => ['name' => 'LPQC', 'color' => '#a855f7'],
        10 => ['name' => 'Complete', 'color' => '#16a34a'],
        12 => ['name' => 'QC Hold', 'color' => '#dc2626'],
        13 => ['name' => 'QC Check', 'color' => '#7c3aed'],
    ],
    
    // UI settings
    'ui' => [
        'refresh_interval' => 60000,      // 60 seconds for page refresh
        'split_check_interval' => 30000,  // 30 seconds for split detection
        'clock_update' => 1000,           // 1 second for clock
    ],
    
    // File paths
    'paths' => [
        'root' => dirname(__DIR__),
        'api' => dirname(__DIR__) . '/api',
        'assets' => dirname(__DIR__) . '/assets',
        'views' => dirname(__DIR__) . '/views',
    ],
    
    // Production planning settings
    'planning' => [
        'changeover_time_minutes' => 15,  // Default changeover time between jobs
        'default_job_time_minutes' => 50, // Default job time if not specified
    ],
    
    // Feature flags
    'features' => [
        'auto_refresh' => true,
        'sensor_integration' => false,  // Future feature
        'offline_mode' => false,        // Future PWA feature
    ],
];
?>