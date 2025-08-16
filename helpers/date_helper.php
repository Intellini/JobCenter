<?php
/**
 * Date Helper Functions
 * Standard date format for the application: DD/MM/YYYY
 */

/**
 * Convert date from YYYY-MM-DD to DD/MM/YYYY
 */
function formatDateDisplay($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    return date('d/m/Y', $timestamp);
}

/**
 * Convert date from DD/MM/YYYY to YYYY-MM-DD for database
 */
function formatDateForDB($date) {
    if (empty($date)) return '';
    
    // Check if already in YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    
    // Convert from DD/MM/YYYY to YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    return $date;
}

/**
 * Get current date in DD/MM/YYYY format
 */
function getCurrentDateDisplay() {
    return date('d/m/Y');
}

/**
 * Get current date in YYYY-MM-DD format for database
 */
function getCurrentDateDB() {
    return date('Y-m-d');
}

/**
 * Format date with month name (e.g., "16 Aug 2025")
 */
function formatDateWithMonth($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    return date('j M Y', $timestamp);
}

/**
 * Format date shortened (e.g., "16/08")
 */
function formatDateShort($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    return date('d/m', $timestamp);
}

/**
 * Validate DD/MM/YYYY format
 */
function isValidDateFormat($date) {
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        return false;
    }
    
    $day = $matches[1];
    $month = $matches[2];
    $year = $matches[3];
    
    return checkdate($month, $day, $year);
}

/**
 * Get day of week from date
 */
function getDayOfWeek($date) {
    $timestamp = strtotime(formatDateForDB($date));
    if ($timestamp === false) return '';
    return date('l', $timestamp);
}

/**
 * Check if date is Sunday
 */
function isSunday($date) {
    $timestamp = strtotime(formatDateForDB($date));
    if ($timestamp === false) return false;
    return date('w', $timestamp) == 0;
}
?>