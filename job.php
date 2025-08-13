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

    <!-- Setup Modal -->
    <div id="setupModal" class="modal">
        <div class="modal-header">
            <h2>Setup Job / सेटअप कार्य</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="setupForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="setup_msg">Message / संदेश</label>
                    <input type="text" id="setup_msg" name="msg" maxlength="20" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="setup_date">Setup Date / सेटअप दिनांक</label>
                    <input type="date" id="setup_date" name="dtsp" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="setup_time">Setup Time / सेटअप समय</label>
                    <input type="time" id="setup_time" name="dtsp_hora" value="<?php echo date('H:i'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="setup_remarks">Remarks / टिप्पणी</label>
                    <textarea id="setup_remarks" name="rmks" rows="3" maxlength="20" placeholder="Setup remarks..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-primary">Start Setup / सेटअप शुरू करें</button>
            </div>
        </form>
    </div>

    <!-- FPQC Modal -->
    <div id="fpqcModal" class="modal">
        <div class="modal-header">
            <h2>First Piece QC / पहला टुकड़ा गुणवत्ता जांच</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="fpqcForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="fpqc_actual_qty">Actual Production Qty / वास्तविक उत्पादन मात्रा</label>
                    <input type="number" id="fpqc_actual_qty" name="opq_act_prdqty" min="1" required>
                </div>
                <div class="form-group">
                    <label for="fpqc_qc_qty">QC Quantity / गुणवत्ता जांच मात्रा</label>
                    <input type="number" id="fpqc_qc_qty" name="opq_qc_qty" min="1" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="fpqc_reject_qty">Reject Quantity / अस्वीकृत मात्रा</label>
                    <input type="number" id="fpqc_reject_qty" name="opq_rjk_qty" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="fpqc_nc_qty">Non-Conformance Qty / गैर-अनुरूप मात्रा</label>
                    <input type="number" id="fpqc_nc_qty" name="opq_nc_qty" min="0" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="fpqc_remarks">QC Remarks / गुणवत्ता टिप्पणी</label>
                    <textarea id="fpqc_remarks" name="opq_qc_rmks" rows="3" placeholder="Quality check remarks..."></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="fpqc_reason">Reason (if rejected) / कारण (यदि अस्वीकृत)</label>
                    <textarea id="fpqc_reason" name="opq_reason" rows="2" placeholder="Reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-warning">Submit for QC / गुणवत्ता जांच हेतु भेजें</button>
            </div>
        </form>
    </div>

    <!-- Pause Modal -->
    <div id="pauseModal" class="modal">
        <div class="modal-header">
            <h2>Pause Job / कार्य रोकें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="pauseForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="pause_datetime">Pause Date/Time / रोकने की तारीख/समय</label>
                    <input type="datetime-local" id="pause_datetime" name="dtp" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="pause_reason">Reason / कारण *</label>
                    <select id="pause_reason" name="rsn" required>
                        <option value="">Select reason / कारण चुनें</option>
                        <option value="material_wait">Material Wait / सामग्री प्रतीक्षा</option>
                        <option value="tool_change">Tool Change / औजार बदलना</option>
                        <option value="quality_issue">Quality Issue / गुणवत्ता समस्या</option>
                        <option value="machine_adjustment">Machine Adjustment / मशीन समायोजन</option>
                        <option value="break_time">Break Time / विश्राम समय</option>
                        <option value="other">Other / अन्य</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="pause_remarks">Remarks / टिप्पणी</label>
                    <textarea id="pause_remarks" name="rmks" rows="3" maxlength="20" placeholder="Additional remarks..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-warning">Pause Job / कार्य रोकें</button>
            </div>
        </form>
    </div>

    <!-- Resume Modal -->
    <div id="resumeModal" class="modal">
        <div class="modal-header">
            <h2>Resume Job / कार्य पुनः शुरू करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="resumeForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="resume_datetime">Resume Date/Time / पुनः शुरू तारीख/समय</label>
                    <input type="datetime-local" id="resume_datetime" name="dtr" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="resume_remarks">Remarks / टिप्पणी *</label>
                    <textarea id="resume_remarks" name="rmks" rows="3" maxlength="150" placeholder="Resumption remarks..." required></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-success">Resume Job / कार्य शुरू करें</button>
            </div>
        </form>
    </div>

    <!-- Complete Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-header">
            <h2>Complete Job / कार्य पूर्ण करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="completeForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="final_qty">Final Quantity Produced / अंतिम उत्पादित मात्रा *</label>
                    <input type="number" id="final_qty" name="final_qty" min="1" max="<?php echo $job['op_pln_prdqty']; ?>" value="<?php echo $job['op_act_prdqty']; ?>" required>
                    <small>Planned: <?php echo $job['op_pln_prdqty']; ?> pcs</small>
                </div>
                <div class="form-group">
                    <label for="reject_qty">Rejected Quantity / अस्वीकृत मात्रा</label>
                    <input type="number" id="reject_qty" name="reject_qty" min="0" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="completion_remarks">Completion Remarks / पूर्णता टिप्पणी</label>
                    <textarea id="completion_remarks" name="rmks" rows="3" placeholder="Job completion notes..."></textarea>
                </div>
            </div>
            <div class="completion-summary">
                <h3>Summary / सारांश</h3>
                <div class="summary-row">
                    <span>Planned Quantity:</span>
                    <span><?php echo $job['op_pln_prdqty']; ?> pcs</span>
                </div>
                <div class="summary-row">
                    <span>Final Quantity:</span>
                    <span id="final_qty_display"><?php echo $job['op_act_prdqty']; ?> pcs</span>
                </div>
                <div class="summary-row">
                    <span>Efficiency:</span>
                    <span id="efficiency_display">--%</span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-success">Complete Job / कार्य पूर्ण करें</button>
            </div>
        </form>
    </div>

    <!-- Breakdown Modal -->
    <div id="breakdownModal" class="modal">
        <div class="modal-header">
            <h2>Report Breakdown / खराबी की रिपोर्ट करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="breakdownForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="breakdown_datetime">Breakdown Date/Time / खराबी तारीख/समय</label>
                    <input type="datetime-local" id="breakdown_datetime" name="dtbd" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="breakdown_remarks">Breakdown Details / खराबी विवरण *</label>
                    <textarea id="breakdown_remarks" name="rmk" rows="5" placeholder="Describe the breakdown in detail..." required></textarea>
                    <small>Please provide as much detail as possible to help maintenance team</small>
                </div>
            </div>
            <div class="breakdown-warning">
                <p><strong>⚠️ Important:</strong></p>
                <p>This will stop the job and alert the maintenance team immediately.</p>
                <p><strong>महत्वपूर्ण:</strong> यह कार्य रोक देगा और तुरंत रखरखाव टीम को सूचित करेगा।</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-danger">Report Breakdown / खराबी रिपोर्ट करें</button>
            </div>
        </form>
    </div>

    <!-- QC Check Modal -->
    <div id="qccheckModal" class="modal">
        <div class="modal-header">
            <h2>Request QC Check / गुणवत्ता जांच का अनुरोध</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="qccheckForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="qc_datetime">QC Request Date/Time / गुणवत्ता जांच तारीख/समय</label>
                    <input type="datetime-local" id="qc_datetime" name="dtq" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="qc_message">Message / संदेश</label>
                    <input type="text" id="qc_message" name="msg" placeholder="Brief message for QC team...">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="qc_remarks">Remarks / टिप्पणी</label>
                    <textarea id="qc_remarks" name="rmks" rows="3" placeholder="Detailed remarks for QC inspection..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-primary">Request QC Check / गुणवत्ता जांच का अनुरोध करें</button>
            </div>
        </form>
    </div>

    <!-- Testing Modal -->
    <div id="testModal" class="modal">
        <div class="modal-header">
            <h2>Record Test Results / परीक्षण परिणाम दर्ज करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="testForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="test_type">Test Type / परीक्षण प्रकार</label>
                    <select id="test_type" name="test_type" required>
                        <option value="">Select test type / परीक्षण प्रकार चुनें</option>
                        <option value="dimension">Dimension / आयाम</option>
                        <option value="weight">Weight / वजन</option>
                        <option value="strength">Strength / मजबूती</option>
                        <option value="surface">Surface Finish / सतह फिनिश</option>
                        <option value="hardness">Hardness / कठोरता</option>
                        <option value="other">Other / अन्य</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="test_value">Test Value / परीक्षण मान</label>
                    <input type="number" id="test_value" name="test_value" step="0.01" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="test_unit">Unit / इकाई</label>
                    <select id="test_unit" name="test_unit" required>
                        <option value="">Select unit / इकाई चुनें</option>
                        <option value="mm">mm</option>
                        <option value="cm">cm</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="n">N (Newton)</option>
                        <option value="hrc">HRC</option>
                        <option value="percent">%</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Result / परिणाम</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="pass_fail" value="pass" required>
                            <span class="radio-custom"></span>
                            Pass / उत्तीर्ण
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="pass_fail" value="fail" required>
                            <span class="radio-custom"></span>
                            Fail / असफल
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-primary">Record Test / परीक्षण दर्ज करें</button>
            </div>
        </form>
    </div>

    <!-- Alert/Issue Modal -->
    <div id="alertModal" class="modal">
        <div class="modal-header">
            <h2>Report Issue / समस्या की रिपोर्ट करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="alertForm" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="issue_type">Issue Type / समस्या प्रकार</label>
                    <select id="issue_type" name="issue_type" required>
                        <option value="">Select issue type / समस्या प्रकार चुनें</option>
                        <option value="quality">Quality Issue / गुणवत्ता समस्या</option>
                        <option value="material">Material Issue / सामग्री समस्या</option>
                        <option value="tool">Tool Issue / औजार समस्या</option>
                        <option value="process">Process Issue / प्रक्रिया समस्या</option>
                        <option value="safety">Safety Concern / सुरक्षा चिंता</option>
                        <option value="other">Other / अन्य</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="severity">Severity / गंभीरता</label>
                    <select id="severity" name="severity" required>
                        <option value="">Select severity / गंभीरता चुनें</option>
                        <option value="low">Low / कम</option>
                        <option value="medium">Medium / मध्यम</option>
                        <option value="high">High / उच्च</option>
                        <option value="critical">Critical / गंभीर</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="issue_description">Issue Description / समस्या विवरण</label>
                    <textarea id="issue_description" name="description" rows="4" placeholder="Describe the issue in detail..." required></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-warning">Report Issue / समस्या रिपोर्ट करें</button>
            </div>
        </form>
    </div>

    <!-- Contact Modal -->
    <div id="contactModal" class="modal">
        <div class="modal-header">
            <h2>Contact Supervisor / पर्यवेक्षक से संपर्क करें</h2>
            <span class="job-info">Job: <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?></span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="contactForm" class="modal-form">
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="help_type">Type of Help Needed / आवश्यक सहायता का प्रकार</label>
                    <select id="help_type" name="issue_type" required>
                        <option value="">Select help type / सहायता प्रकार चुनें</option>
                        <option value="technical">Technical Help / तकनीकी सहायता</option>
                        <option value="quality">Quality Guidance / गुणवत्ता मार्गदर्शन</option>
                        <option value="material">Material Issue / सामग्री समस्या</option>
                        <option value="instruction">Job Instructions / कार्य निर्देश</option>
                        <option value="emergency">Emergency / आपातकाल</option>
                        <option value="other">Other / अन्य</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="help_message">Message / संदेश</label>
                    <textarea id="help_message" name="message" rows="4" placeholder="Brief description of help needed..." required></textarea>
                </div>
            </div>
            <div class="contact-info">
                <p><strong>Emergency Contact:</strong> Ext. 911</p>
                <p><strong>आपातकालीन संपर्क:</strong> एक्सटेंशन 911</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel / रद्द करें</button>
                <button type="submit" class="btn btn-primary">Call Supervisor / पर्यवेक्षक को बुलाएं</button>
            </div>
        </form>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    <script>
        // Update clock
        setInterval(function() {
            document.getElementById('clock').textContent = new Date().toTimeString().split(' ')[0];
        }, 1000);
        
        // Modal functionality
        function showModal(action) {
            const overlay = document.getElementById('modalOverlay');
            const modal = document.getElementById(action + 'Modal');
            
            if (!modal) {
                console.error('Modal not found for action:', action);
                return;
            }
            
            // Special handling for pause/resume
            if (action === 'pause' && <?php echo $show_resume ? 'true' : 'false'; ?>) {
                action = 'resume';
                const resumeModal = document.getElementById('resumeModal');
                if (resumeModal) {
                    overlay.classList.add('active');
                    resumeModal.classList.add('active');
                    return;
                }
            }
            
            overlay.classList.add('active');
            modal.classList.add('active');
            
            // Focus first input for accessibility
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
        
        function closeModal() {
            const overlay = document.getElementById('modalOverlay');
            const modals = document.querySelectorAll('.modal');
            
            overlay.classList.remove('active');
            modals.forEach(modal => modal.classList.remove('active'));
        }
        
        // Handle form submissions
        document.addEventListener('DOMContentLoaded', function() {
            // Setup form handler
            const setupForm = document.getElementById('setupForm');
            if (setupForm) {
                setupForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('setup', new FormData(this));
                });
            }
            
            // FPQC form handler
            const fpqcForm = document.getElementById('fpqcForm');
            if (fpqcForm) {
                fpqcForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('fpqc', new FormData(this));
                });
            }
            
            // Pause form handler
            const pauseForm = document.getElementById('pauseForm');
            if (pauseForm) {
                pauseForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('pause', new FormData(this));
                });
            }
            
            // Resume form handler
            const resumeForm = document.getElementById('resumeForm');
            if (resumeForm) {
                resumeForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('resume', new FormData(this));
                });
            }
            
            // Complete form handler
            const completeForm = document.getElementById('completeForm');
            if (completeForm) {
                completeForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('complete', new FormData(this));
                });
                
                // Update efficiency calculation
                const finalQtyInput = document.getElementById('final_qty');
                if (finalQtyInput) {
                    finalQtyInput.addEventListener('input', function() {
                        updateCompletionSummary();
                    });
                }
            }
            
            // Breakdown form handler
            const breakdownForm = document.getElementById('breakdownForm');
            if (breakdownForm) {
                breakdownForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('breakdown', new FormData(this));
                });
            }
            
            // QC Check form handler
            const qccheckForm = document.getElementById('qccheckForm');
            if (qccheckForm) {
                qccheckForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('qccheck', new FormData(this));
                });
            }
            
            // Testing form handler
            const testForm = document.getElementById('testForm');
            if (testForm) {
                testForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('test', new FormData(this));
                });
            }
            
            // Alert form handler
            const alertForm = document.getElementById('alertForm');
            if (alertForm) {
                alertForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('alert', new FormData(this));
                });
            }
            
            // Contact form handler
            const contactForm = document.getElementById('contactForm');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleFormSubmit('contact', new FormData(this));
                });
            }
        });
        
        // Handle form submission
        function handleFormSubmit(action, formData) {
            const form = document.getElementById(action + 'Form');
            if (!form) return;
            
            // Add job ID to form data
            formData.append('job_id', <?php echo $job_id; ?>);
            formData.append('action', action);
            
            // Show loading state
            form.classList.add('loading');
            
            // Submit to API
            fetch('api/actions/' + action + '.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                form.classList.remove('loading');
                
                if (data.success) {
                    closeModal();
                    // Show success message
                    showNotification('Success: ' + data.message, 'success');
                    // Refresh page after short delay
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                form.classList.remove('loading');
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            });
        }
        
        // Update completion summary
        function updateCompletionSummary() {
            const finalQty = document.getElementById('final_qty').value;
            const plannedQty = <?php echo $job['op_pln_prdqty']; ?>;
            
            document.getElementById('final_qty_display').textContent = finalQty + ' pcs';
            
            if (finalQty && plannedQty > 0) {
                const efficiency = Math.round((finalQty / plannedQty) * 100);
                document.getElementById('efficiency_display').textContent = efficiency + '%';
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            // Add styles
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 2000;
                transform: translateX(400px);
                transition: transform 0.3s;
            `;
            
            // Set background color based on type
            switch (type) {
                case 'success':
                    notification.style.backgroundColor = 'var(--success-color)';
                    break;
                case 'error':
                    notification.style.backgroundColor = 'var(--danger-color)';
                    break;
                default:
                    notification.style.backgroundColor = 'var(--primary-color)';
            }
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Lock screen
        function lockScreen() {
            // TODO: Implement screen lock
            alert('Screen lock - To be implemented');
        }
        
        // Auto refresh for QC hold check
        <?php if ($is_qc_hold): ?>
        setInterval(function() {
            location.reload();
        }, 30000); // 30 seconds
        <?php endif; ?>
    </script>
</body>
</html>