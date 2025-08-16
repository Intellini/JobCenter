<?php
/**
 * Session Configuration
 * Manages session security and lifetime settings
 */

// Session configuration
ini_set('session.use_strict_mode', 1);          // Prevent session fixation
ini_set('session.use_cookies', 1);              // Use cookies for sessions
ini_set('session.use_only_cookies', 1);         // Only use cookies (no URL parameters)
ini_set('session.cookie_httponly', 1);          // Prevent JavaScript access to session cookie
ini_set('session.cookie_samesite', 'Lax');      // CSRF protection
ini_set('session.cookie_lifetime', 0);          // Session cookie expires when browser closes
ini_set('session.gc_maxlifetime', 1800);        // Server-side session lifetime: 30 minutes
ini_set('session.gc_probability', 1);           // Garbage collection probability
ini_set('session.gc_divisor', 100);             // 1% chance of GC on each request

// Set session name for this application
session_name('JobCenterSession');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Maximum idle time before automatic logout (30 minutes)
define('MAX_IDLE_TIME', 1800);

/**
 * Initialize session with security settings
 */
function initializeSession() {
    // Check if session is already started
    if (session_status() === PHP_SESSION_NONE) {
        // Start session with secure settings
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Regenerate every 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if session is valid and not expired
 */
function isSessionValid() {
    // Check if required session variables exist
    if (!isset($_SESSION['operator_id']) || 
        !isset($_SESSION['shift']) || 
        !isset($_SESSION['work_date']) || 
        !isset($_SESSION['machine_code'])) {
        return false;
    }
    
    // Check for session timeout
    if (isset($_SESSION['last_activity'])) {
        $idle_time = time() - $_SESSION['last_activity'];
        if ($idle_time > MAX_IDLE_TIME) {
            return false;
        }
    }
    
    // Check for absolute session expiry
    if (isset($_SESSION['session_start_time'])) {
        $session_age = time() - $_SESSION['session_start_time'];
        if ($session_age > SESSION_TIMEOUT * 2) { // Max 1 hour absolute
            return false;
        }
    }
    
    return true;
}

/**
 * Destroy session completely
 */
function destroySession() {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Update session activity timestamp
 */
function updateSessionActivity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Set login session variables
 */
function setLoginSession($operator_id, $operator_name, $shift, $work_date, $machine_code, $is_supervisor = false) {
    $_SESSION['operator_id'] = $operator_id;
    $_SESSION['operator_name'] = $operator_name;
    $_SESSION['shift'] = $shift;
    $_SESSION['work_date'] = $work_date;
    $_SESSION['machine_code'] = $machine_code;
    $_SESSION['is_supervisor'] = $is_supervisor;
    $_SESSION['session_start_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Regenerate session ID on login for security
    session_regenerate_id(true);
}
?>