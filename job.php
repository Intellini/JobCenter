<?php
/**
 * Job Center - Individual Job View
 * Shows job details and action buttons
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if operator is logged in
if (!isset($_SESSION['operator_id'])) {
    header('Location: index.php');
    exit;
}

// Include configuration and database
require_once 'config/database.php';
require_once 'config/app.php';

$config = include 'config/app.php';

// Get job ID and current flag
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_current = isset($_GET['current']) && $_GET['current'] == '1';

if (!$job_id) {
    header('Location: index.php');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get job details
$job_query = "
    SELECT 
        o.*,
        wi.im_name as item_code,
        wi.im_name as item_name,
        oh.ob_porefno as po_ref,
        m.mm_code as machine_code,
        m.mm_name as machine_name
    FROM operations o
    LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
    LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
    LEFT JOIN machine m ON o.op_mach = m.mm_id
    WHERE o.op_id = ?
";

$job = $db->getRow($job_query, [$job_id]);

if (!$job) {
    header('Location: index.php');
    exit;
}

// Calculate progress
$progress_percent = 0;
if ($job['op_pln_prdqty'] > 0) {
    $progress_percent = min(100, ($job['op_act_prdqty'] / $job['op_pln_prdqty']) * 100);
}

// Check if job is on QC hold
$is_qc_hold = $job['op_status'] == 12;

// Determine which buttons to show based on status
$show_pause = in_array($job['op_status'], [3, 4, 5]);
$show_resume = $job['op_status'] == 6;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job <?php echo $job['op_lot']; ?> - Job Center</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/job.css">
</head>
<body class="job-page <?php echo $is_qc_hold ? 'qc-hold' : ''; ?>">
    <!-- QC Hold Overlay -->
    <?php if ($is_qc_hold): ?>
    <div class="qc-hold-overlay">
        <div class="qc-hold-message">
            <h2>Waiting for QC Clearance</h2>
            <p>QC निकासी की प्रतीक्षा</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="job-header-bar">
        <img src="/common/assets/images/pushkarlogo.png" alt="Pushkar Logo" class="header-logo">
        <a href="index.php" class="back-button">← Back</a>
        <div class="job-title">
            <span><?php echo $job['op_lot']; ?></span>
            <span class="status-badge status-<?php echo $job['op_status']; ?>">
                <?php echo $config['status'][$job['op_status']]['name']; ?>
            </span>
        </div>
        <div class="header-clock" id="clock"><?php echo date('H:i:s'); ?></div>
    </header>

    <!-- Job Details -->
    <main class="job-main">
        <!-- Job Info Card -->
        <div class="job-info-card">
            <div class="info-row">
                <div class="info-item">
                    <label>Item / वस्तु</label>
                    <div class="info-value"><?php echo $job['item_code']; ?></div>
                    <div class="info-subvalue"><?php echo $job['item_name']; ?></div>
                </div>
                <div class="info-item">
                    <label>PO Reference</label>
                    <div class="info-value"><?php echo $job['po_ref'] ?: 'N/A'; ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <label>Quantity / मात्रा</label>
                    <div class="info-value large">
                        <?php echo $job['op_act_prdqty']; ?> / <?php echo $job['op_pln_prdqty']; ?>
                        <span class="unit">pcs</span>
                    </div>
                </div>
                <div class="info-item">
                    <label>Progress / प्रगति</label>
                    <div class="progress-container">
                        <div class="progress-bar-large">
                            <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                        </div>
                        <div class="progress-percent"><?php echo round($progress_percent); ?>%</div>
                    </div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <label>Planned Time / नियोजित समय</label>
                    <div class="info-value">
                        <?php echo date('H:i', strtotime($job['op_pln_stdt'])); ?> - 
                        <?php echo date('H:i', strtotime($job['op_pln_endt'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Setup Time / सेटअप समय</label>
                    <div class="info-value"><?php echo $job['op_setup_time']; ?> min</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons <?php echo !$is_current ? 'read-only' : ''; ?>">
            <!-- Primary Actions -->
            <div class="button-group primary-actions">
                <?php if ($job['op_status'] == 2): // Assigned ?>
                    <button class="action-btn btn-setup" onclick="showModal('setup')" <?php echo !$is_current ? 'disabled' : ''; ?>>
                        <span class="btn-icon">🔧</span>
                        <span class="btn-label">Setup<br>सेटअप</span>
                    </button>
                <?php endif; ?>
                
                <?php if ($job['op_status'] == 3): // Setup ?>
                    <button class="action-btn btn-fpqc" onclick="showModal('fpqc')" <?php echo !$is_current ? 'disabled' : ''; ?>>
                        <span class="btn-icon">🔍</span>
                        <span class="btn-label">FPQC<br>पहला टुकड़ा</span>
                    </button>
                <?php endif; ?>
                
                <?php if ($show_pause): ?>
                    <button class="action-btn btn-pause" onclick="showModal('pause')" <?php echo !$is_current ? 'disabled' : ''; ?>>
                        <span class="btn-icon">⏸️</span>
                        <span class="btn-label">Pause<br>रोकें</span>
                    </button>
                <?php elseif ($show_resume): ?>
                    <button class="action-btn btn-resume" onclick="showModal('resume')" <?php echo !$is_current ? 'disabled' : ''; ?>>
                        <span class="btn-icon">▶️</span>
                        <span class="btn-label">Resume<br>शुरू करें</span>
                    </button>
                <?php endif; ?>
                
                <?php if (in_array($job['op_status'], [5, 9])): // In Process or LPQC ?>
                    <button class="action-btn btn-complete" onclick="showModal('complete')" <?php echo !$is_current ? 'disabled' : ''; ?>>
                        <span class="btn-icon">✓</span>
                        <span class="btn-label">Complete<br>पूर्ण</span>
                    </button>
                <?php endif; ?>
                
                <button class="action-btn btn-breakdown" onclick="showModal('breakdown')" <?php echo !$is_current || $is_qc_hold ? 'disabled' : ''; ?>>
                    <span class="btn-icon">🚫</span>
                    <span class="btn-label">Breakdown<br>खराबी</span>
                </button>
            </div>
            
            <!-- Secondary Actions -->
            <div class="button-group secondary-actions">
                <button class="action-btn-small" onclick="showModal('drawing')">
                    <span class="btn-icon">📐</span>
                    <span class="btn-label">Drawing</span>
                </button>
                
                <button class="action-btn-small" onclick="showModal('chart')">
                    <span class="btn-icon">📊</span>
                    <span class="btn-label">Chart</span>
                </button>
                
                <button class="action-btn-small" onclick="showModal('contact')" <?php echo !$is_current || $is_qc_hold ? 'disabled' : ''; ?>>
                    <span class="btn-icon">📞</span>
                    <span class="btn-label">Contact</span>
                </button>
                
                <button class="action-btn-small" onclick="showModal('qccheck')" <?php echo !$is_current || $is_qc_hold ? 'disabled' : ''; ?>>
                    <span class="btn-icon">🔍</span>
                    <span class="btn-label">QC Check</span>
                </button>
                
                <button class="action-btn-small" onclick="showModal('test')" <?php echo !$is_current || $is_qc_hold ? 'disabled' : ''; ?>>
                    <span class="btn-icon">🧪</span>
                    <span class="btn-label">Test</span>
                </button>
                
                <button class="action-btn-small" onclick="showModal('alert')" <?php echo !$is_current || $is_qc_hold ? 'disabled' : ''; ?>>
                    <span class="btn-icon">⚠️</span>
                    <span class="btn-label">Alert</span>
                </button>
                
                <button class="action-btn-small" onclick="lockScreen()">
                    <span class="btn-icon">🔒</span>
                    <span class="btn-label">Lock</span>
                </button>
            </div>
        </div>
        
        <?php if (!$is_current): ?>
        <div class="read-only-message">
            <p>This job is not active. You can only view details.</p>
            <p>यह कार्य सक्रिय नहीं है। आप केवल विवरण देख सकते हैं।</p>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal Container -->
    <div id="modalContainer"></div>

    <!-- Modal Overlay -->
    <div id="modalOverlay" class="modal-overlay" onclick="closeModal()"></div>

    <!-- Setup Modal and other modals truncated for brevity -->
    
    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    <script>
        // Update clock
        setInterval(function() {
            document.getElementById('clock').textContent = new Date().toTimeString().split(' ')[0];
        }, 1000);
        
        // Modal functionality and form handlers are handled by app.js
        
        // Auto refresh for QC hold check
        <?php if ($is_qc_hold): ?>
        setInterval(function() {
            location.reload();
        }, 30000); // 30 seconds
        <?php endif; ?>
    </script>
</body>
</html>