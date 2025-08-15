<?php
/**
 * Job Center - Machine Interface
 * Entry point that handles operator login and job timeline display
 */

session_start();
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Start output buffering to prevent any output before DOCTYPE
ob_start();

// Handle logout first - before any other processing
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Output JavaScript to clear localStorage before redirect
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Logging out...</title>
    </head>
    <body>
        <script>
            // Clear all localStorage data
            localStorage.clear();
            
            // Redirect to login page
            window.location.href = 'index.php';
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Handle force login - clear session and show login form
if (isset($_GET['login']) && $_GET['login'] == '1') {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a new session for the login
    session_start();
    
    // Continue to show login form (don't redirect)
    $_SESSION = array();
    $_SESSION['force_login'] = true;
}

// Check for session timeout (30 minutes = 1800 seconds)
if (isset($_SESSION['last_activity']) && !isset($_GET['login'])) {
    $inactive = time() - $_SESSION['last_activity'];
    if ($inactive > 1800) { // 30 minutes
        // Session has timed out
        $_SESSION = array();
        session_destroy();
        
        // Get machine code if available
        $m_param = isset($_GET['m']) ? '?m=' . $_GET['m'] : '';
        header('Location: index.php' . $m_param . '&timeout=1');
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Include configuration and database
require_once 'config/database.php';
require_once 'config/app.php';

// Get machine code from URL parameter first, then hostname
if (isset($_GET['m']) && !empty($_GET['m'])) {
    $machine_code = strtoupper(substr($_GET['m'], 0, 5));
    $_SESSION['machine_source'] = 'url';
} elseif (isset($_SESSION['machine_code']) && !empty($_SESSION['machine_code'])) {
    // Use machine code from session if already set
    $machine_code = $_SESSION['machine_code'];
    $_SESSION['machine_source'] = 'session';
} else {
    // Fallback to hostname (which likely won't match)
    $machine_code = gethostname();
    if (strlen($machine_code) > 5) {
        $machine_code = substr($machine_code, 0, 5);
    }
    $machine_code = strtoupper($machine_code);
    $_SESSION['machine_source'] = 'hostname';
}

// Get database instance
$db = Database::getInstance();
$conn = $db->getConnection();

// Ensure debug is off
$conn->debug = false;

// Check if operator is logged in - enhanced session validation
// Force login if requested
$logged_in = isset($_SESSION['operator_id']) && 
             isset($_SESSION['shift']) && 
             isset($_SESSION['work_date']) && 
             isset($_SESSION['machine_code']) && 
             !empty($_SESSION['operator_id']) && 
             !empty($_SESSION['shift']) &&
             !isset($_SESSION['force_login']);

// Clear force_login flag if it was set
if (isset($_SESSION['force_login'])) {
    unset($_SESSION['force_login']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $operator_name = trim($_POST['operator_name']);
    
    // Convert DD/MM/YYYY to YYYY-MM-DD for database
    $work_date = $_POST['work_date'];
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $work_date, $matches)) {
        $work_date = $matches[3] . '-' . $matches[2] . '-' . $matches[1]; // Convert to YYYY-MM-DD
    }
    
    // Check if this is a supervisor login
    if (strtoupper($operator_name) === 'SUPERVISOR') {
        // Handle supervisor authentication
        if (isset($_POST['supervisor_pin'])) {
            // Get machine details to verify PIN
            $machine_data = $db->getRow("SELECT mm_supervisor_pin FROM machine WHERE mm_code = ?", [$machine_code]);
            
            if ($machine_data && $machine_data['mm_supervisor_pin'] === $_POST['supervisor_pin']) {
                // PIN is correct, set supervisor session
                $_SESSION['operator_id'] = 'SUPERVISOR';
                $_SESSION['operator_name'] = 'SUPERVISOR';
                $_SESSION['shift'] = $_POST['shift'];
                $_SESSION['work_date'] = $work_date;
                $_SESSION['machine_code'] = $machine_code;
                $_SESSION['is_supervisor'] = true;
                
                // Redirect to planning interface
                header('Location: planning.php');
                exit;
            } else {
                $login_error = 'Invalid supervisor PIN';
            }
        } else {
            $login_error = 'PIN required for supervisor access';
        }
    } else {
        // Regular operator login
        $_SESSION['operator_id'] = $operator_name; // Use name as ID for traceability
        $_SESSION['operator_name'] = $operator_name;
        $_SESSION['shift'] = $_POST['shift'];
        $_SESSION['work_date'] = $work_date;
        $_SESSION['machine_code'] = $machine_code;
        $_SESSION['is_supervisor'] = false;
        
        header('Location: index.php');
        exit;
    }
}


// Auto-detect shift based on current time
function getCurrentShift() {
    $hour = date('H');
    if ($hour >= 6 && $hour < 14) return 'A';
    if ($hour >= 14 && $hour < 22) return 'B';
    return 'C'; // Night shift
}

// If not logged in, show login form
if (!$logged_in) {
    // Get machine details for display
    $machine_display = $db->getRow("SELECT mm_name FROM machine WHERE mm_code = ?", [$machine_code]);
    $machine_full_name = $machine_display ? $machine_display['mm_name'] : $machine_code;
    
    // Initialize login error message
    $login_error = isset($login_error) ? $login_error : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Center - <?php echo $machine_code; ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <img src="assets/images/pushkarlogo.png" alt="Pushkar Logo" class="login-logo">
            <h1>Job Center</h1>
            <h2><?php echo htmlspecialchars($machine_full_name); ?></h2>
        </div>
        
        <form method="POST" class="login-form">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="operator_name">Operator / ऑपरेटर</label>
                <input type="text" name="operator_name" id="operator_name" placeholder="Enter your name" required>
            </div>
            
            <?php if ($login_error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="shift">Shift / शिफ्ट</label>
                <select name="shift" id="shift" required>
                    <option value="A" <?php echo getCurrentShift() === 'A' ? 'selected' : ''; ?>>A (6:00 - 14:00)</option>
                    <option value="B" <?php echo getCurrentShift() === 'B' ? 'selected' : ''; ?>>B (14:00 - 22:00)</option>
                    <option value="C" <?php echo getCurrentShift() === 'C' ? 'selected' : ''; ?>>C (22:00 - 6:00)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="work_date">Date / दिनांक</label>
                <input type="text" name="work_date" id="work_date" 
                       placeholder="DD/MM/YYYY" 
                       pattern="^(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/\d{4}$"
                       title="Please enter date in DD/MM/YYYY format"
                       value="<?php echo date('d/m/Y'); ?>" 
                       required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-large">
                Start Work / कार्य शुरू करें
            </button>
        </form>
    </div>
    
    <!-- PIN Dialog Modal -->
    <div id="pinModal" class="modal">
        <div class="modal-content">
            <h3>Supervisor Authentication</h3>
            <p>Please enter supervisor PIN:</p>
            <input type="password" id="supervisorPin" placeholder="Enter PIN">
            <div class="modal-buttons">
                <button type="button" id="confirmPin" class="btn btn-primary">Confirm</button>
                <button type="button" id="cancelPin" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let pendingFormData = null;
            
            // Handle form submission
            document.querySelector('.login-form').addEventListener('submit', function(e) {
                const operatorName = document.getElementById('operator_name').value.trim();
                
                if (operatorName.toUpperCase() === 'SUPERVISOR') {
                    e.preventDefault();
                    
                    // Store form data
                    pendingFormData = new FormData(this);
                    
                    // Show PIN dialog
                    document.getElementById('pinModal').classList.add('show');
                    document.getElementById('supervisorPin').focus();
                }
                // For regular operators, let form submit normally
            });
            
            // Handle PIN confirmation
            document.getElementById('confirmPin').addEventListener('click', function() {
                const pin = document.getElementById('supervisorPin').value;
                
                if (pin) {
                // Add PIN to form data and submit
                pendingFormData.append('supervisor_pin', pin);
                
                // Create a new form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                for (let [key, value] of pendingFormData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Please enter a PIN');
            }
        });
        
            // Handle PIN dialog cancel
            document.getElementById('cancelPin').addEventListener('click', function() {
            document.getElementById('pinModal').classList.remove('show');
            document.getElementById('supervisorPin').value = '';
            pendingFormData = null;
        });
        
            // Handle Enter key in PIN input
            document.getElementById('supervisorPin').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('confirmPin').click();
            }
        });
        
            // Close modal when clicking outside
            document.getElementById('pinModal').addEventListener('click', function(e) {
            if (e.target === this) {
                document.getElementById('cancelPin').click();
            }
        });
        
            // Date input formatting and validation
            const dateInput = document.getElementById('work_date');
            if (dateInput) {
            // Format input as user types
            dateInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2);
                }
                if (value.length >= 5) {
                    value = value.substring(0, 5) + '/' + value.substring(5, 9);
                }
                
                e.target.value = value;
            });
            
            // Validate on blur
            dateInput.addEventListener('blur', function(e) {
                const value = e.target.value;
                const pattern = /^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/\d{4}$/;
                
                if (value && !pattern.test(value)) {
                    e.target.setCustomValidity('Please enter a valid date in DD/MM/YYYY format');
                    e.target.classList.add('error');
                } else {
                    e.target.setCustomValidity('');
                    e.target.classList.remove('error');
                }
            });
        }
        }); // End of DOMContentLoaded
    </script>
</body>
</html>
    <?php
    exit;
}

// Get machine ID from database
$machine = $db->getRow("SELECT mm_id, mm_name FROM machine WHERE mm_code = ?", [$machine_code]);
if (!$machine && $_SESSION['machine_source'] !== 'url') {
    // If machine not found and not from URL, show machine selector
    $active_machines = $db->getAll("SELECT mm_id, mm_code, mm_name FROM machine WHERE mm_active = 1 AND mm_code IS NOT NULL ORDER BY mm_code");
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Center - Select Machine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Job Center</h1>
            <h2>Select Machine</h2>
        </div>
        
        <div class="machine-list">
            <p>Please select a machine:</p>
            <?php foreach ($active_machines as $m): ?>
                <a href="?m=<?php echo $m['mm_code']; ?>" class="machine-link">
                    <strong><?php echo $m['mm_code']; ?></strong> - <?php echo htmlspecialchars($m['mm_name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
} elseif (!$machine) {
    die("Machine $machine_code not found in database");
}

// Get jobs for this machine - first try mach_planning, then fallback to operations
// Convert shift letter to number for mach_planning query
$shift_num = $_SESSION['shift'];
if (is_string($shift_num)) {
    $shift_map = ['A' => 1, 'B' => 2, 'C' => 3];
    $shift_num = $shift_map[$shift_num] ?? 1;
}

// First try to get planned jobs from mach_planning
// Get ALL jobs for the business date (all shifts)
$planning_query = "
    SELECT DISTINCT
        mp.mp_op_id as op_id,
        mp.mp_op_lot as op_lot,
        mp.mp_op_seq as op_seq,
        mp.mp_op_start as op_pln_stdt,
        mp.mp_op_end as op_pln_endt,
        mp.mp_op_esttime as op_setup_time,
        mp.mp_op_calctime as op_prd_time,
        mp.mp_op_pln_prdqty as op_pln_prdqty,
        mp.mp_op_act_prdqty as op_act_prdqty,
        mp.mp_op_status as op_status,
        mp.mp_op_proditm as item_code,
        mp.mp_op_proditm as item_name,
        mp.mp_op_jcrd as po_ref,
        mp.mp_op_shift as shift,
        o.op_obid as op_order,
        o.op_holdflg,
        oh.ob_eddt as due_date
    FROM mach_planning mp
    LEFT JOIN operations o ON mp.mp_op_id = o.op_id
    LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
    WHERE mp.mp_op_mach = ?
    AND mp.mp_op_proddate = ?
    ORDER BY mp.mp_op_start ASC, mp.mp_op_seq ASC
";

$planning_jobs = $db->getAll($planning_query, [$machine['mm_id'], $_SESSION['work_date']]);

// Log the query for debugging
error_log("JobCenter: Checking mach_planning with machine_id=" . $machine['mm_id'] . ", date=" . $_SESSION['work_date'] . " (all shifts)");

if ($planning_jobs === false) {
    error_log("JobCenter: mach_planning query failed - using operations table fallback");
    $planning_jobs = [];
}

if (!empty($planning_jobs)) {
    // Use planned jobs from mach_planning
    $jobs = $planning_jobs;
    error_log("JobCenter: Using " . count($jobs) . " jobs from mach_planning table");
} else {
    // Fallback to operations table - get all active jobs for the machine
    $jobs_query = "
        SELECT 
            o.op_id,
            o.op_lot,
            o.op_obid as op_order,
            o.op_prod as op_prd,
            o.op_pln_prdqty,
            o.op_act_prdqty,
            o.op_status,
            o.op_start as op_pln_stdt,
            o.op_end as op_pln_endt,
            o.op_stp_time as op_setup_time,
            o.op_tot_pause as op_prd_time,
            o.op_holdflg,
            o.op_seq,
            wi.im_name as item_code,
            wi.im_name as item_name,
            oh.ob_porefno as po_ref,
            oh.ob_eddt as due_date
        FROM operations o
        LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
        LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
        WHERE o.op_mach = ?
        AND o.op_status NOT IN (10, 11)
        ORDER BY COALESCE(o.op_seq, 999), o.op_start ASC
    ";

    $jobs = $db->getAll($jobs_query, [$machine['mm_id']]);
    error_log("JobCenter: Using " . count($jobs ?? []) . " jobs from operations table");
}

// Check if query failed
if ($jobs === false) {
    $jobs = array(); // Set to empty array if query failed
    $debug_message = "Query failed! Machine ID = " . $machine['mm_id'] . " - Error: " . $db->ErrorMsg();
    error_log("JobCenter ERROR: Query failed for machine ID " . $machine['mm_id'] . " - " . $db->ErrorMsg());
} else {
    // Store debug info (but don't display it)
    $debug_message = "Machine Code = $machine_code, Machine ID = " . $machine['mm_id'] . ", Jobs found = " . count($jobs);
    
    // Log to error log for debugging
    error_log("JobCenter Debug: " . $debug_message);
    error_log("JobCenter Debug: First job = " . json_encode($jobs[0] ?? 'no jobs'));
    error_log("JobCenter Debug: Session operator = " . $_SESSION['operator_name']);
}

// Sort jobs by sequence (op_seq field)
if (!empty($jobs)) {
    usort($jobs, function($a, $b) {
        // Jobs with sequence (op_seq > 0) come first
        if ($a['op_seq'] > 0 && $b['op_seq'] == 0) return -1;
        if ($a['op_seq'] == 0 && $b['op_seq'] > 0) return 1;
        
        // If both have sequences, sort by sequence number
        if ($a['op_seq'] > 0 && $b['op_seq'] > 0) {
            return $a['op_seq'] <=> $b['op_seq'];
        }
        
        // If neither has sequence, sort by status then start time
        // Active jobs (status 1-9) come before unassigned (status 0)
        if ($a['op_status'] > 0 && $b['op_status'] == 0) return -1;
        if ($a['op_status'] == 0 && $b['op_status'] > 0) return 1;
        
        // Then sort by planned start time
        return strcmp($a['op_pln_stdt'], $b['op_pln_stdt']);
    });
}

// Clean any accidental output before showing the view
ob_clean();

// Debug: Ensure variables are available
error_log("JobCenter: Before view include - jobs count = " . count($jobs ?? []));
error_log("JobCenter: Machine info = " . json_encode($machine ?? 'no machine'));

// Debug: Check if we have what we need
if (!isset($machine) || !isset($jobs)) {
    error_log("JobCenter CRITICAL: Missing required variables for timeline view");
    error_log("Machine: " . (isset($machine) ? 'SET' : 'NOT SET'));
    error_log("Jobs: " . (isset($jobs) ? 'SET with ' . count($jobs) . ' items' : 'NOT SET'));
    
    // Initialize if missing
    if (!isset($jobs)) {
        $jobs = [];
    }
    if (!isset($machine)) {
        $machine = ['mm_id' => 0, 'mm_name' => 'Unknown'];
    }
}

// Include the main view
require_once 'views/timeline.php';
?>