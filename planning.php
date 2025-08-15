<?php
/**
 * Job Center - Planning Interface
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
$config = require_once 'config/app.php';

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
        oh.ob_porefno as po_ref,
        js.jc_name as status_name,
        js.jc_color as status_color
    FROM operations o
    LEFT JOIN wip_items wi ON o.op_prod = wi.im_id
    LEFT JOIN orders_head oh ON o.op_obid = oh.ob_id
    LEFT JOIN jc_status js ON o.op_status = js.jc_id
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
    <style>
        .planning-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .planning-header {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .planning-title h1 {
            color: var(--primary-color);
            margin-bottom: 0.25rem;
            font-size: 1.5rem;
        }
        
        .planning-info {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .planning-main {
            flex: 1;
            display: grid;
            grid-template-columns: 70% 30%;
            gap: 1rem;
            min-height: 0;
        }
        
        .planning-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .section-header {
            padding: 1rem;
            border-bottom: 2px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }
        
        .section-subtitle {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }
        
        .section-content {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            min-height: 0;
        }
        
        /* Today's Sequence specific scrolling */
        .planning-section:nth-child(2) .section-content {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }
        
        .job-list {
            min-height: 100px;
        }
        
        #available-jobs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            min-height: 100px;
            position: relative;
            transition: background-color 0.3s ease;
        }
        
        #available-jobs.sortable-drag-over {
            background-color: #f0f9ff;
            border: 2px dashed #3b82f6;
        }
        
        #available-jobs:empty::after {
            content: 'Drag jobs here to remove from sequence';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #9ca3af;
            font-style: italic;
        }
        
        #todays-sequence {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 350px);
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0.5rem;
        }
        
        /* Custom scrollbar for Today's Sequence */
        #todays-sequence::-webkit-scrollbar {
            width: 8px;
        }
        
        #todays-sequence::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        #todays-sequence::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        #todays-sequence::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .job-card {
            background: var(--light-color);
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 1rem;
            cursor: grab;
            transition: all 0.2s ease;
            user-select: none;
        }
        
        #available-jobs .job-card {
            margin-bottom: 0;
        }
        
        #todays-sequence .job-card {
            margin-bottom: 2rem;
            position: relative;
            padding-right: 3rem;
        }
        
        /* Visual connector between job cards */
        #todays-sequence .job-card:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: -2rem;
            left: 50%;
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-top: 16px solid var(--primary-color);
            transform: translateX(-50%);
            z-index: 10;
        }
        
        /* Changeover time card - small horizontal card between jobs */
        .changeover-time {
            margin: -0.5rem auto 1rem auto;
            width: calc(100% - 1rem);
            height: 28px;
            background: linear-gradient(90deg, #ff6b35, #f97316);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(249, 115, 22, 0.25);
            z-index: 25;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .changeover-time:hover {
            transform: scale(1.02);
            box-shadow: 0 3px 10px rgba(249, 115, 22, 0.35);
        }
        
        .changeover-time input {
            width: 55px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 3px;
            color: #f97316;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 2px 4px;
            margin-right: 3px;
            outline: none;
        }
        
        .changeover-time input:focus {
            background: white;
            box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.3);
        }
        
        .changeover-time span {
            color: white;
            font-size: 0.8rem;
        }
        
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .job-card:active {
            cursor: grabbing;
        }
        
        .job-card.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        
        .job-card.drag-over {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .job-card.overdue {
            border-left: 4px solid var(--danger-color);
        }
        
        .job-card.due-soon {
            border-left: 4px solid var(--warning-color);
        }
        
        .job-card.future {
            border-left: 4px solid var(--success-color);
        }
        
        .job-status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .job-lot {
            font-weight: 600;
            color: var(--text-color);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .job-details {
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.4;
        }
        
        .job-item-name {
            color: var(--text-color);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .job-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .job-quantity {
            background: var(--primary-color);
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .job-due-date {
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .job-due-date.overdue {
            background: var(--danger-color);
            color: white;
        }
        
        .job-due-date.due-soon {
            background: var(--warning-color);
            color: white;
        }
        
        .job-due-date.future {
            background: var(--success-color);
            color: white;
        }
        
        .sequence-number {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--primary-color);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            border: 2px solid white;
            z-index: 15;
        }
        
        .sequenced-job {
            position: relative;
            background: #f0fdf4;
            border-left: 4px solid var(--success-color);
        }
        
        /* Remove button styles deleted - using drag back to left instead */
        
        .empty-state {
            text-align: center;
            color: var(--text-light);
            padding: 2rem;
            font-style: italic;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .drag-placeholder {
            height: 120px;
            border: 2px dashed var(--primary-color);
            border-radius: 8px;
            background: rgba(37, 99, 235, 0.05);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-style: italic;
        }
        
        .time-estimation {
            background: var(--light-color);
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .shift-info {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .btn-operator-view {
            background: var(--success-color);
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn-operator-view:hover {
            background: #059669;
            color: white;
            text-decoration: none;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        @media (max-width: 1024px) {
            .planning-main {
                grid-template-columns: 1fr;
            }
            
            .planning-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                align-self: stretch;
            }
            
            #available-jobs {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .planning-container {
                padding: 0.5rem;
            }
            
            .job-meta {
                flex-direction: column;
                align-items: flex-start;
            }
            
            #available-jobs {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 0.75rem;
            }
            
            /* Responsive adjustments for changeover cards */
            .changeover-time {
                height: 24px;
                font-size: 0.75rem;
            }
            
            .changeover-time input {
                width: 48px;
                font-size: 0.75rem;
            }
            
            #todays-sequence .job-card:not(:last-child)::after {
                border-left-width: 10px;
                border-right-width: 10px;
                border-top-width: 14px;
                bottom: -1.7rem;
            }
        }
        
        @media (max-width: 480px) {
            #available-jobs {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            /* Further mobile adjustments for changeover cards */
            .changeover-time {
                height: 22px;
                font-size: 0.7rem;
            }
            
            .changeover-time input {
                width: 45px;
                font-size: 0.7rem;
            }
            
            #todays-sequence .job-card:not(:last-child)::after {
                border-left-width: 8px;
                border-right-width: 8px;
                border-top-width: 12px;
                bottom: -1.6rem;
            }
        }

        /* Sortable.js Styles */
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .sortable-chosen {
            transform: rotate(2deg);
        }
        
        .sortable-fallback {
            display: none;
        }
    </style>
</head>
<body>
    <div class="planning-container">
        <div class="planning-header">
            <img src="/common/assets/images/pushkarlogo.png" alt="Pushkar Logo" style="height: 40px; margin-right: 20px;">
            <div class="planning-title">
                <h1>Planning Interface</h1>
                <div class="planning-info">
                    Machine: <strong><?php echo htmlspecialchars($machine['mm_name']); ?></strong> |
                    Date: <strong><?php echo date('d/m/Y', strtotime($work_date)); ?></strong> |
                    Shift: <strong><?php echo $shift; ?></strong>
                </div>
                <div class="shift-info">
                    <span>Shift Start: <strong id="shift-start-time">Loading...</strong></span>
                    <span>Shift End: <strong id="shift-end-time">Loading...</strong></span>
                    <span>Current Time: <strong id="current-time">Loading...</strong></span>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="saveAndExitToOperator()" class="btn-operator-view">Exit to Operator View</button>
                <a href="?logout=1" class="btn btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="planning-main">
            <!-- Available Jobs Panel (70%) -->
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
                                     data-calc-time="<?php echo $job['op_calctime'] ?? 50; ?>"
                                     data-status="<?php echo $job['op_status']; ?>"
                                     data-status-name="<?php echo htmlspecialchars($job['status_name']); ?>"
                                     data-status-color="<?php echo htmlspecialchars($job['status_color']); ?>">
                                    <?php if ($job['status_name'] && $job['status_color']): ?>
                                        <div class="job-status-badge" style="background-color: <?php echo htmlspecialchars($job['status_color']); ?>">
                                            <?php echo htmlspecialchars($job['status_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="job-lot">Job: <?php echo htmlspecialchars($job['op_lot']); ?></div>
                                    <div class="job-details">
                                        <?php if ($job['item_name']): ?>
                                            <div class="job-item-name"><?php echo htmlspecialchars($job['item_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($job['po_ref']): ?>
                                            <div>Lot: <?php echo htmlspecialchars($job['po_ref']); ?></div>
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
            
            <!-- Today's Sequence Panel (30%) -->
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

    <!-- Include Planning Component -->
    <script src="assets/js/components/planning.js"></script>
    
    <script>
        // Configuration
        const MACHINE_ID = <?php echo $machine['mm_id']; ?>;
        const WORK_DATE = '<?php echo $work_date; ?>';
        const SHIFT = '<?php echo $shift; ?>';
        const STORAGE_KEY = `planning_sequence_${MACHINE_ID}_${WORK_DATE}_${SHIFT}`;
        
        // Planning configuration from server
        const PLANNING_CONFIG = {
            changeoverTime: <?php echo $config['planning']['changeover_time_minutes']; ?>,
            defaultJobTime: <?php echo $config['planning']['default_job_time_minutes']; ?>
        };
        
        // Shift times (can be configured)
        const SHIFT_TIMES = {
            'A': { start: '06:00', end: '14:00' },
            'B': { start: '14:00', end: '22:00' },
            'C': { start: '22:00', end: '06:00' }
        };
        
        // Global planning component instance
        let planningComponent;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize planning component
            planningComponent = new PlanningComponent(PLANNING_CONFIG);
            
            // Load saved default changeover time from localStorage
            const savedChangeoverTime = localStorage.getItem('changeover_time');
            if (savedChangeoverTime) {
                const time = parseInt(savedChangeoverTime);
                PLANNING_CONFIG.changeoverTime = time;
            }
            
            // Remove button deleted - using drag back to left instead
            
            initializeDragAndDrop();
            loadSequenceFromStorage();
            updateDueDateColors();
            updateTimeDisplay();
            setInterval(updateTimeDisplay, 60000); // Update every minute
        });
        
        
        // Initialize drag and drop
        function initializeDragAndDrop() {
            // Available jobs sortable
            const availableJobs = document.getElementById('available-jobs');
            new Sortable(availableJobs, {
                group: {
                    name: 'jobs',
                    pull: 'clone',
                    put: true  // Allow dropping cards back here
                },
                sort: false,
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onMove: function(evt) {
                    // Add visual feedback when dragging over available jobs
                    if (evt.to.id === 'available-jobs') {
                        evt.to.classList.add('sortable-drag-over');
                    }
                },
                onAdd: function(evt) {
                    // Card was dropped back to available jobs from sequence
                    const item = evt.item;
                    
                    // Remove sequence-specific elements
                    const seqNum = item.querySelector('.sequence-number');
                    if (seqNum) seqNum.remove();
                    // No remove button to clean up
                    
                    // Clear sequence in database
                    fetch('api/actions/planning.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'clear_job_sequence',
                            job_id: item.dataset.jobId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateSequenceNumbers();
                            saveSequenceToStorage();
                            updateTimeEstimation();
                            checkEmptyState();
                            
                            // Trigger planning component recalculation
                            if (planningComponent) {
                                planningComponent.recalculateTimeline();
                            }
                        }
                    });
                },
                onEnd: function(evt) {
                    // Remove visual feedback
                    availableJobs.classList.remove('sortable-drag-over');
                    
                    if (evt.to !== evt.from && evt.to.id === 'todays-sequence') {
                        // Remove the original card from available jobs
                        const originalCard = evt.from.querySelector(`[data-job-id="${evt.item.dataset.jobId}"]`);
                        if (originalCard && originalCard !== evt.item) {
                            originalCard.remove();
                        }
                    }
                }
            });
            
            // Today's sequence sortable - allow dragging back to available
            const todaysSequence = document.getElementById('todays-sequence');
            new Sortable(todaysSequence, {
                group: {
                    name: 'jobs',
                    pull: true,  // Allow dragging out
                    put: true    // Allow dragging in
                },
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onAdd: function(evt) {
                    const item = evt.item;
                    item.classList.add('sequenced-job');
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    updateOperationsTable();
                    hideEmptyState();
                    
                    // Trigger planning component recalculation
                    if (planningComponent) {
                        planningComponent.recalculateTimeline();
                    }
                },
                onUpdate: function(evt) {
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    updateOperationsTable();
                    
                    // Trigger planning component recalculation
                    if (planningComponent) {
                        planningComponent.recalculateTimeline();
                    }
                },
                onRemove: function(evt) {
                    // Clear the job's sequence assignment in the database
                    const jobId = evt.item.dataset.jobId;
                    
                    fetch('api/actions/planning.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'clear_job_sequence',
                            job_id: jobId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to clear job sequence:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error clearing job sequence:', error);
                    });
                    
                    // Restore the card to available jobs if removed from sequence
                    const availableJobsContainer = document.getElementById('available-jobs');
                    const jobCard = evt.item.cloneNode(true);
                    jobCard.classList.remove('sequenced-job');
                    const sequenceNumber = jobCard.querySelector('.sequence-number');
                    if (sequenceNumber) {
                        sequenceNumber.remove();
                    }
                    availableJobsContainer.appendChild(jobCard);
                    
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    updateOperationsTable();
                    checkEmptyState();
                    
                    // Trigger planning component recalculation
                    if (planningComponent) {
                        planningComponent.recalculateTimeline();
                    }
                }
            });
        }
        
        // Update sequence numbers and add changeover cards
        function updateSequenceNumbers() {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            const todaysSequence = document.getElementById('todays-sequence');
            
            // First, remove all existing changeover time elements
            document.querySelectorAll('.changeover-time').forEach(el => el.remove());
            
            // Get stored individual changeover times
            const storedTimes = JSON.parse(localStorage.getItem('changeover_times') || '{}');
            
            sequencedJobs.forEach((job, index) => {
                // Remove existing sequence number
                const existingNumber = job.querySelector('.sequence-number');
                if (existingNumber) {
                    existingNumber.remove();
                }
                // Add new sequence number (on the right)
                const sequenceNumber = document.createElement('div');
                sequenceNumber.className = 'sequence-number';
                sequenceNumber.textContent = index + 1;
                job.appendChild(sequenceNumber);
                
                // Add changeover time card after each job (except the last)
                if (index < sequencedJobs.length - 1) {
                    const changeoverCard = document.createElement('div');
                    changeoverCard.className = 'changeover-time';
                    changeoverCard.dataset.index = index;
                    
                    // Get stored time or use default
                    const changeoverTime = storedTimes[`${index}`] || PLANNING_CONFIG.changeoverTime;
                    
                    changeoverCard.innerHTML = `
                        <input type="number" value="${changeoverTime}" min="0" max="999" />
                        <span>min changeover</span>
                    `;
                    
                    // Insert after the current job card
                    job.insertAdjacentElement('afterend', changeoverCard);
                    
                    // Add event listener for editing
                    const input = changeoverCard.querySelector('input');
                    input.addEventListener('click', function(e) {
                        e.stopPropagation();
                        this.select();
                    });
                    
                    input.addEventListener('change', function(e) {
                        const newValue = parseInt(this.value) || 0;
                        const index = changeoverCard.dataset.index;
                        
                        // Store individual changeover time
                        const times = JSON.parse(localStorage.getItem('changeover_times') || '{}');
                        times[index] = newValue;
                        localStorage.setItem('changeover_times', JSON.stringify(times));
                        
                        // Recalculate timeline
                        updateTimeEstimation();
                        
                        // Trigger planning component recalculation
                        if (planningComponent) {
                            planningComponent.recalculateTimeline();
                        }
                    });
                    
                    input.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            this.blur();
                        }
                    });
                }
            });
        }
        
        // Remove job from sequence
        function removeFromSequence(jobCard) {
            console.log('removeFromSequence called');
            const jobId = jobCard.dataset.jobId;
            console.log('Removing job ID:', jobId);
            
            // Clear sequence in database
            fetch('api/actions/planning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'clear_job_sequence',
                    job_id: jobId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Move card back to available jobs
                    const availableJobs = document.getElementById('available-jobs');
                    
                    // Remove sequence-specific elements
                    const seqNum = jobCard.querySelector('.sequence-number');
                    if (seqNum) seqNum.remove();
                    // No remove button to clean up
                    
                    // Move the card
                    availableJobs.appendChild(jobCard);
                    
                    // Update everything
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    checkEmptyState();
                    
                    // Trigger planning component recalculation
                    if (planningComponent) {
                        planningComponent.recalculateTimeline();
                    }
                } else {
                    console.error('Failed to clear job sequence:', data.message);
                }
            })
            .catch(error => {
                console.error('Error clearing job sequence:', error);
            });
        }
        
        // Save sequence to localStorage
        function saveSequenceToStorage() {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            const sequence = Array.from(sequencedJobs).map((job, index) => ({
                jobId: job.dataset.jobId,
                lot: job.dataset.lot,
                item: job.dataset.item,
                quantity: job.dataset.quantity,
                dueDate: job.dataset.dueDate,
                poRef: job.dataset.poRef,
                calcTime: job.dataset.calcTime || 50,
                sequenceOrder: index + 1
            }));
            
            localStorage.setItem(STORAGE_KEY, JSON.stringify(sequence));
        }
        
        // Save to mach_planning and exit to operator view
        function saveAndExitToOperator() {
            // Get the timeline data from planning component
            if (!planningComponent) {
                alert('Planning component not initialized');
                return;
            }
            
            const timeline = planningComponent.getTimelineData();
            if (!timeline || !timeline.jobs || timeline.jobs.length === 0) {
                // No jobs sequenced, just redirect
                window.location.href = 'index.php?m=<?php echo urlencode($machine_code); ?>';
                return;
            }
            
            // Prepare data for API
            const jobs = timeline.jobs.map((job, index) => ({
                op_id: job.jobId,
                sequence: index + 1,
                start_time: job.startTime,  // Already formatted as YYYY-MM-DD HH:MM:SS
                end_time: job.endTime,      // Already formatted as YYYY-MM-DD HH:MM:SS
                setup_time: job.setupTime || 30,
                prod_time: job.prodTime || job.calcTime || 50,
                changeover: job.changeoverTime || 15
            }));
            
            // Save to mach_planning table
            fetch('api/actions/planning.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_to_mach_planning',
                    machine_id: <?php echo $machine['mm_id']; ?>,
                    work_date: '<?php echo $work_date; ?>',
                    shift: '<?php echo $shift; ?>',
                    jobs: jobs
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Planning saved to database');
                    // Also save sequence numbers to operations table
                    return fetch('api/actions/planning.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update_sequence',
                            machine_id: <?php echo $machine['mm_id']; ?>,
                            jobs: jobs.map(j => ({ job_id: j.op_id, sequence: j.sequence }))
                        })
                    });
                } else {
                    throw new Error(data.message || 'Failed to save planning');
                }
            })
            .then(response => response.json())
            .then(data => {
                // Redirect to operator view regardless of sequence update result
                window.location.href = 'index.php?m=<?php echo urlencode($machine_code); ?>';
            })
            .catch(error => {
                console.error('Error saving planning:', error);
                // Show error but still allow redirect
                if (confirm('Error saving planning: ' + error.message + '\n\nContinue to operator view anyway?')) {
                    window.location.href = 'index.php?m=<?php echo urlencode($machine_code); ?>';
                }
            });
        }
        
        // Load sequence from localStorage
        function loadSequenceFromStorage() {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return;
            
            try {
                const sequence = JSON.parse(stored);
                const todaysSequence = document.getElementById('todays-sequence');
                const availableJobs = document.getElementById('available-jobs');
                
                sequence.forEach(job => {
                    // Find the original job card in available jobs
                    const originalCard = availableJobs.querySelector(`[data-job-id="${job.jobId}"]`);
                    if (originalCard) {
                        // Clone the card for sequence and remove from available jobs
                        const sequencedCard = originalCard.cloneNode(true);
                        sequencedCard.classList.add('sequenced-job');
                        todaysSequence.appendChild(sequencedCard);
                        originalCard.remove();
                    }
                });
                
                updateSequenceNumbers();
                updateTimeEstimation();
                hideEmptyState();
                
                // Trigger planning component recalculation after loading
                setTimeout(() => {
                    if (planningComponent) {
                        planningComponent.recalculateTimeline();
                    }
                }, 100);
            } catch (e) {
                console.error('Error loading sequence from storage:', e);
            }
        }
        
        // Update due date colors
        function updateDueDateColors() {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            document.querySelectorAll('.job-card').forEach(card => {
                const dueDateStr = card.dataset.dueDate;
                const dueDate = new Date(dueDateStr);
                const dueDateSpan = card.querySelector('.job-due-date');
                
                if (dueDate < today) {
                    card.classList.add('overdue');
                    dueDateSpan.classList.add('overdue');
                } else if (dueDate <= tomorrow) {
                    card.classList.add('due-soon');
                    dueDateSpan.classList.add('due-soon');
                } else {
                    card.classList.add('future');
                    dueDateSpan.classList.add('future');
                }
            });
        }
        
        // Update time estimation (legacy function - now delegates to planning component)
        function updateTimeEstimation() {
            // If planning component is available, let it handle the calculation
            if (planningComponent) {
                planningComponent.recalculateTimeline();
                return;
            }
            
            // Fallback to original calculation for backward compatibility
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            const estimationDiv = document.getElementById('time-estimation');
            const timelineDetails = document.getElementById('timeline-details');
            
            if (sequencedJobs.length === 0) {
                estimationDiv.style.display = 'none';
                return;
            }
            
            const shiftInfo = SHIFT_TIMES[SHIFT];
            let currentTime = parseTime(shiftInfo.start);
            let totalTime = 0;
            let timeline = '';
            const changeoverTime = PLANNING_CONFIG.changeoverTime;
            
            // Get stored individual changeover times
            const storedTimes = JSON.parse(localStorage.getItem('changeover_times') || '{}');
            
            sequencedJobs.forEach((job, index) => {
                const calcTime = parseInt(job.dataset.calcTime) || PLANNING_CONFIG.defaultJobTime;
                const startTime = formatTime(currentTime);
                currentTime += calcTime;
                const endTime = formatTime(currentTime);
                totalTime += calcTime;
                
                timeline += `<div>${index + 1}. ${job.dataset.lot}: ${startTime} - ${endTime} (${calcTime}min)</div>`;
                
                // Add changeover time if not the last job
                if (index < sequencedJobs.length - 1) {
                    // Use individual changeover time if stored, otherwise use default
                    const individualChangeoverTime = storedTimes[`${index}`] || changeoverTime;
                    currentTime += individualChangeoverTime;
                    totalTime += individualChangeoverTime;
                    timeline += `<div style="margin-left: 1rem; color: #f97316; font-size: 0.85rem;">↓ ${individualChangeoverTime} min changeover</div>`;
                }
            });
            
            const shiftEndTime = parseTime(shiftInfo.end);
            if (SHIFT === 'C' && shiftEndTime < currentTime) {
                shiftEndTime += 24 * 60; // Add 24 hours for night shift
            }
            
            const overrun = currentTime > shiftEndTime;
            const overrunTime = overrun ? currentTime - shiftEndTime : 0;
            
            // Calculate total changeover time from individual times
            let totalChangeoverTime = 0;
            for (let i = 0; i < sequencedJobs.length - 1; i++) {
                totalChangeoverTime += storedTimes[`${i}`] || changeoverTime;
            }
            const totalProductionTime = totalTime - totalChangeoverTime;
            
            timelineDetails.innerHTML = timeline + 
                `<div style="margin-top: 0.5rem; font-weight: 600;">
                    Production Time: ${totalProductionTime} minutes<br>
                    Changeover Time: ${totalChangeoverTime} minutes<br>
                    Total Time: ${totalTime} minutes (${Math.round(totalTime/60*10)/10} hours)
                    ${overrun ? `<br><span style="color: var(--danger-color);">Overrun: ${overrunTime} minutes</span>` : ''}
                </div>`;
            
            estimationDiv.style.display = 'block';
        }
        
        // Update time display
        function updateTimeDisplay() {
            const now = new Date();
            const currentTimeStr = now.toLocaleTimeString('en-US', { 
                hour12: false, 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            document.getElementById('current-time').textContent = currentTimeStr;
            
            const shiftInfo = SHIFT_TIMES[SHIFT];
            document.getElementById('shift-start-time').textContent = shiftInfo.start;
            document.getElementById('shift-end-time').textContent = shiftInfo.end;
        }
        
        // Update operations table with sequence
        function updateOperationsTable() {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            const updates = Array.from(sequencedJobs).map((job, index) => ({
                jobId: job.dataset.jobId,
                sequenceOrder: index + 1
            }));
            
            if (updates.length === 0) return;
            
            fetch('api/actions/planning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_sequence',
                    machine_id: MACHINE_ID,
                    date: WORK_DATE,
                    shift: SHIFT,
                    sequence: updates
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to update sequence:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating sequence:', error);
            });
        }
        
        // Utility functions
        function parseTime(timeStr) {
            const [hours, minutes] = timeStr.split(':').map(Number);
            return hours * 60 + minutes;
        }
        
        function formatTime(minutes) {
            const hours = Math.floor(minutes / 60) % 24;
            const mins = minutes % 60;
            return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
        }
        
        function hideEmptyState() {
            document.getElementById('sequence-empty').style.display = 'none';
        }
        
        function checkEmptyState() {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            const emptyState = document.getElementById('sequence-empty');
            
            if (sequencedJobs.length === 0) {
                emptyState.style.display = 'block';
                document.getElementById('time-estimation').style.display = 'none';
            }
        }
        
        // Initial setup
        hideEmptyState();
        if (document.querySelectorAll('#todays-sequence .job-card').length === 0) {
            checkEmptyState();
        }
    </script>
</body>
</html>