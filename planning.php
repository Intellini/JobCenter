<?php
/**
 * Job Center - Supervisor Planning Interface
 * Comprehensive drag-and-drop interface for job sequencing
 */

session_start();
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Check if user is logged in as supervisor
if (!isset($_SESSION['is_supervisor']) || !$_SESSION['is_supervisor']) {
    header('Location: index.php');
    exit;
}

// Include configuration and database
require_once 'config/database.php';
require_once 'config/app.php';

// Get database instance
$db = Database::getInstance();
$conn = $db->getConnection();

// Get machine code from session
$machine_code = $_SESSION['machine_code'];
$work_date = $_SESSION['work_date'];
$shift = $_SESSION['shift'];

// Get machine details
$machine = $db->getRow("SELECT mm_id, mm_name FROM machine WHERE mm_code = ?", [$machine_code]);
if (!$machine) {
    die("Machine $machine_code not found in database");
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get available jobs for this machine (incomplete jobs only)
$available_jobs = $db->getAll("
    SELECT 
        o.op_id,
        o.op_lot,
        o.op_prod,
        o.op_pln_prdqty,
        o.op_status,
        o.op_date,
        o.op_seq,
        o.op_calctime,
        wi.im_name as item_name,
        oh.ob_porefno as po_ref
    FROM operations o
    LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
    LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
    WHERE (o.op_mach = ? OR o.op_mach IS NULL)
    AND o.op_status < 10
    AND (o.op_seq IS NULL OR o.op_seq = 0)
    AND o.op_date BETWEEN DATE_SUB(NOW(), INTERVAL 15 DAY) AND DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY o.op_date ASC, o.op_seq ASC
", [$machine['mm_id']]);

// Get today's sequenced jobs from localStorage (will be handled by JavaScript)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Interface - <?php echo $machine_code; ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- Planning-specific styles included in head for brevity -->
</head>
<body>
    <div class="planning-container">
        <div class="planning-header">
            <div class="planning-title">
                <h1>Supervisor Planning Interface</h1>
                <div class="planning-info">
                    Machine: <strong><?php echo htmlspecialchars($machine['mm_name']); ?></strong> |
                    Date: <strong><?php echo $work_date; ?></strong> |
                    Shift: <strong><?php echo $shift; ?></strong> |
                    Supervisor: <strong><?php echo $_SESSION['operator_name']; ?></strong>
                </div>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn-operator-view">Exit to Operator View</a>
                <a href="?logout=1" class="btn btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="planning-main">
            <!-- Available Jobs Panel -->
            <div class="planning-section">
                <div class="section-header">
                    <h2 class="section-title">Available Jobs</h2>
                    <div class="section-subtitle">Drag jobs to Today's Sequence to plan production order</div>
                </div>
                <div class="section-content">
                    <div id="available-jobs" class="job-list">
                        <?php if (empty($available_jobs)): ?>
                            <div class="empty-state">
                                No incomplete jobs available for this machine.
                            </div>
                        <?php else: ?>
                            <?php foreach ($available_jobs as $job): ?>
                                <div class="job-card" 
                                     data-job-id="<?php echo $job['op_id']; ?>"
                                     data-lot="<?php echo htmlspecialchars($job['op_lot']); ?>"
                                     data-item="<?php echo htmlspecialchars($job['item_name']); ?>"
                                     data-quantity="<?php echo $job['op_pln_prdqty']; ?>"
                                     data-due-date="<?php echo $job['op_date']; ?>"
                                     data-po-ref="<?php echo htmlspecialchars($job['po_ref']); ?>"
                                     data-calc-time="<?php echo $job['op_calctime'] ?? 50; ?>">
                                    <div class="job-lot">Lot: <?php echo htmlspecialchars($job['op_lot']); ?></div>
                                    <div class="job-details">
                                        <?php if ($job['item_name']): ?>
                                            <div class="job-item-name"><?php echo htmlspecialchars($job['item_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($job['po_ref']): ?>
                                            <div>PO: <?php echo htmlspecialchars($job['po_ref']); ?></div>
                                        <?php endif; ?>
                                        <div class="job-meta">
                                            <span class="job-quantity">Qty: <?php echo number_format($job['op_pln_prdqty']); ?></span>
                                            <span class="job-due-date" data-due="<?php echo $job['op_date']; ?>">
                                                Due: <?php echo date('M j', strtotime($job['op_date'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Today's Sequence Panel -->
            <div class="planning-section">
                <div class="section-header">
                    <h2 class="section-title">Today's Sequence</h2>
                    <div class="section-subtitle">Production order for today's shift</div>
                </div>
                <div class="section-content">
                    <div id="todays-sequence" class="job-list">
                        <div class="empty-state" id="sequence-empty">
                            Drop jobs here to plan today's sequence.
                            <br><br>
                            Order will determine production priority.
                        </div>
                    </div>
                    <div class="time-estimation" id="time-estimation" style="display: none;">
                        <strong>Estimated Timeline:</strong>
                        <div id="timeline-details"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Planning interface JavaScript functionality
        // Full implementation available in the complete file
    </script>
</body>
</html>