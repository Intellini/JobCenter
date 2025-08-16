<?php
/**
 * Holiday API Endpoint
 * Checks if a given date is a holiday
 */

// Include database configuration
require_once '../../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Get database instance
$db = Database::getInstance();

// Get date parameter
$date = isset($_GET['date']) ? $_GET['date'] : null;

if (!$date) {
    echo json_encode(['error' => 'Date parameter required']);
    exit;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    // Check if date is in holiday table
    $holiday = $db->getRow("
        SELECT holiday_name, holiday_type 
        FROM holiday 
        WHERE holiday_date = ?
    ", [$date]);
    
    if ($holiday) {
        echo json_encode([
            'is_holiday' => true,
            'holiday_name' => $holiday['holiday_name'],
            'holiday_type' => $holiday['holiday_type']
        ]);
    } else {
        // Check if it's a Sunday
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0) {
            echo json_encode([
                'is_holiday' => true,
                'holiday_name' => 'Sunday - Weekly Holiday',
                'holiday_type' => 'weekly'
            ]);
        } else {
            echo json_encode([
                'is_holiday' => false
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>