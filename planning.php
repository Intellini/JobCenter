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
        
        .job-list {
            min-height: 100px;
        }
        
        #available-jobs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        #todays-sequence {
            display: flex;
            flex-direction: column;
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
            margin-bottom: 0.75rem;
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
            top: -8px;
            left: -8px;
            background: var(--primary-color);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid white;
        }
        
        .sequenced-job {
            position: relative;
            background: #f0fdf4;
            border-left: 4px solid var(--success-color);
        }
        
        .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #ef4444;
            color: white;
            border: 2px solid white;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .remove-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }
        
        .remove-btn:active {
            transform: scale(0.95);
        }
        
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
        }
        
        @media (max-width: 480px) {
            #available-jobs {
                grid-template-columns: 1fr;
                gap: 0.5rem;
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
            <div class="planning-title">
                <h1>Supervisor Planning Interface</h1>
                <div class="planning-info">
                    Machine: <strong><?php echo htmlspecialchars($machine['mm_name']); ?></strong> |
                    Date: <strong><?php echo $work_date; ?></strong> |
                    Shift: <strong><?php echo $shift; ?></strong> |
                    Supervisor: <strong><?php echo $_SESSION['operator_name']; ?></strong>
                </div>
                <div class="shift-info">
                    <span>Shift Start: <strong id="shift-start-time">Loading...</strong></span>
                    <span>Shift End: <strong id="shift-end-time">Loading...</strong></span>
                    <span>Current Time: <strong id="current-time">Loading...</strong></span>
                </div>
            </div>
            <div class="header-actions">
                <a href="index.php?machine=<?php echo urlencode($machine_code); ?>&date=<?php echo urlencode($work_date); ?>&shift=<?php echo urlencode($shift); ?>" class="btn-operator-view">Exit to Operator View</a>
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

    <script>
        // Configuration
        const MACHINE_ID = <?php echo $machine['mm_id']; ?>;
        const WORK_DATE = '<?php echo $work_date; ?>';
        const SHIFT = '<?php echo $shift; ?>';
        const STORAGE_KEY = `planning_sequence_${MACHINE_ID}_${WORK_DATE}_${SHIFT}`;
        
        // Shift times (can be configured)
        const SHIFT_TIMES = {
            'A': { start: '06:00', end: '14:00' },
            'B': { start: '14:00', end: '22:00' },
            'C': { start: '22:00', end: '06:00' }
        };
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
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
                    put: false
                },
                sort: false,
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
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
                },
                onUpdate: function(evt) {
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    updateOperationsTable();
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
                }
            });
        }
        
        // Update sequence numbers
        function updateSequenceNumbers() {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            sequencedJobs.forEach((job, index) => {
                // Remove existing sequence number and remove button
                const existingNumber = job.querySelector('.sequence-number');
                if (existingNumber) {
                    existingNumber.remove();
                }
                const existingRemove = job.querySelector('.remove-btn');
                if (existingRemove) {
                    existingRemove.remove();
                }
                
                // Add new sequence number
                const sequenceNumber = document.createElement('div');
                sequenceNumber.className = 'sequence-number';
                sequenceNumber.textContent = index + 1;
                job.appendChild(sequenceNumber);
                
                // Add remove button
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-btn';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'Remove from sequence';
                removeBtn.onclick = function(e) {
                    e.stopPropagation();
                    removeFromSequence(job);
                };
                job.appendChild(removeBtn);
            });
        }
        
        // Remove job from sequence
        function removeFromSequence(jobCard) {
            const jobId = jobCard.dataset.jobId;
            
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
                    const removeBtn = jobCard.querySelector('.remove-btn');
                    if (removeBtn) removeBtn.remove();
                    
                    // Move the card
                    availableJobs.appendChild(jobCard);
                    
                    // Update everything
                    updateSequenceNumbers();
                    saveSequenceToStorage();
                    updateTimeEstimation();
                    checkEmptyState();
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
        
        // Update time estimation
        function updateTimeEstimation() {
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
            
            sequencedJobs.forEach((job, index) => {
                const calcTime = parseInt(job.dataset.calcTime) || 50;
                const startTime = formatTime(currentTime);
                currentTime += calcTime;
                const endTime = formatTime(currentTime);
                totalTime += calcTime;
                
                timeline += `<div>${index + 1}. ${job.dataset.lot}: ${startTime} - ${endTime} (${calcTime}min)</div>`;
            });
            
            const shiftEndTime = parseTime(shiftInfo.end);
            if (SHIFT === 'C' && shiftEndTime < currentTime) {
                shiftEndTime += 24 * 60; // Add 24 hours for night shift
            }
            
            const overrun = currentTime > shiftEndTime;
            const overrunTime = overrun ? currentTime - shiftEndTime : 0;
            
            timelineDetails.innerHTML = timeline + 
                `<div style="margin-top: 0.5rem; font-weight: 600;">
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