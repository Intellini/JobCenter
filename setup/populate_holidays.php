<?php
/**
 * Populate Holiday Table
 * Adds Sundays and major Indian holidays for 2025
 */

require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getInstance();

// Start transaction
$db->beginTransaction();

try {
    // First, create holiday table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS holiday (
            holiday_id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE UNIQUE,
            holiday_name VARCHAR(100),
            holiday_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Clear existing holidays for 2025 to avoid duplicates
    $db->query("DELETE FROM holiday WHERE YEAR(holiday_date) = 2025");
    
    // Add all Sundays for 2025
    $start_date = new DateTime('2025-01-05'); // First Sunday of 2025
    $end_date = new DateTime('2025-12-31');
    
    while ($start_date <= $end_date) {
        if ($start_date->format('w') == 0) { // Sunday
            $db->query("
                INSERT INTO holiday (holiday_date, holiday_name, holiday_type) 
                VALUES (?, 'Sunday', 'weekly')
            ", [$start_date->format('Y-m-d')]);
        }
        $start_date->modify('+1 day');
    }
    
    // Add major Indian holidays for 2025
    $indian_holidays = [
        ['2025-01-01', 'New Year\'s Day', 'national'],
        ['2025-01-14', 'Makar Sankranti', 'festival'],
        ['2025-01-26', 'Republic Day', 'national'],
        ['2025-03-01', 'Maha Shivaratri', 'festival'],
        ['2025-03-14', 'Holi', 'festival'],
        ['2025-03-31', 'Ugadi / Gudi Padwa', 'festival'],
        ['2025-04-06', 'Ram Navami', 'festival'],
        ['2025-04-10', 'Mahavir Jayanti', 'festival'],
        ['2025-04-14', 'Baisakhi / Tamil New Year', 'festival'],
        ['2025-04-18', 'Good Friday', 'festival'],
        ['2025-05-01', 'May Day / Labour Day', 'national'],
        ['2025-05-12', 'Buddha Purnima', 'festival'],
        ['2025-06-27', 'Rath Yatra', 'festival'],
        ['2025-07-06', 'Guru Purnima', 'festival'],
        ['2025-08-09', 'Muharram', 'festival'],
        ['2025-08-15', 'Independence Day', 'national'],
        ['2025-08-16', 'Janmashtami', 'festival'],
        ['2025-08-27', 'Ganesh Chaturthi', 'festival'],
        ['2025-10-02', 'Gandhi Jayanti', 'national'],
        ['2025-10-02', 'Dussehra', 'festival'],
        ['2025-10-20', 'Karva Chauth', 'festival'],
        ['2025-10-21', 'Diwali', 'festival'],
        ['2025-10-22', 'Govardhan Puja', 'festival'],
        ['2025-10-23', 'Bhai Dooj', 'festival'],
        ['2025-11-05', 'Guru Nanak Jayanti', 'festival'],
        ['2025-12-25', 'Christmas', 'national']
    ];
    
    foreach ($indian_holidays as $holiday) {
        // Skip if it's already a Sunday
        $dayOfWeek = date('w', strtotime($holiday[0]));
        if ($dayOfWeek != 0) {
            $db->query("
                INSERT INTO holiday (holiday_date, holiday_name, holiday_type) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                holiday_name = VALUES(holiday_name),
                holiday_type = VALUES(holiday_type)
            ", $holiday);
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Get count of holidays
    $count = $db->getValue("SELECT COUNT(*) FROM holiday WHERE YEAR(holiday_date) = 2025");
    
    echo "Successfully populated holiday table with $count holidays for 2025\n";
    echo "This includes all Sundays and major Indian holidays.\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
?>