<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Center - <?php echo $machine_code ?? 'Unknown'; ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo time(); ?>">
    <meta http-equiv="refresh" content="60"> <!-- Auto refresh every minute -->
</head>
<body class="timeline-page">
    <!-- Header Bar -->
    <header class="main-header">
        <div class="header-left">
            <img src="/common/assets/images/pushkarlogo.png" alt="Pushkar Logo" class="header-logo">
            <span class="machine-name"><?php echo isset($machine['mm_name']) ? $machine['mm_name'] : 'Machine Not Found'; ?></span>
        </div>
        <div class="header-center">
            <span class="operator-name">Op: <?php echo $_SESSION['operator_name']; ?></span>
            <span class="shift">Shift: <?php echo $_SESSION['shift']; ?></span>
            <span class="date"><?php echo date('d M Y', strtotime($_SESSION['work_date'])); ?></span>
        </div>
        <div class="header-right">
            <span class="clock" id="clock"><?php echo date('H:i:s'); ?></span>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <!-- Check for sequence data -->
    <div id="no-data-splash" class="splash-screen" style="display: none;">
        <div class="splash-content">
            <img src="/common/assets/images/pushkarlogo.png" alt="Pushkar Logo" class="splash-logo">
            <h2>No Jobs Scheduled</h2>
            <div class="splash-message">
                <p>कोई जॉब शेड्यूल नहीं है</p>
                <p>No jobs are currently scheduled for this machine on the selected shift and date.</p>
                <p><strong>Possible actions:</strong></p>
                <ul style="text-align: left; margin: 1rem 0;">
                    <li>Contact supervisor for job assignment</li>
                    <li>Check if the correct machine is selected</li>
                    <li>Verify shift and date selection</li>
                </ul>
            </div>
            <button type="button" class="btn btn-supervisor" onclick="contactSupervisor()">
                Contact Supervisor / सुपरवाइजर से संपर्क करें
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='?logout=1'" style="margin-top: 1rem;">
                Logout / लॉगआउट
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loading-screen" class="splash-screen" style="display: flex;">
        <div class="splash-content">
            <div class="loading-spinner"></div>
            <h2>Loading Jobs...</h2>
            <p>जॉब्स लोड हो रहे हैं...</p>
        </div>
    </div>

    <!-- Timeline Container -->
    <main class="timeline-container" id="timeline-container" style="display: none;">
        <div class="timeline-layout">
            <!-- Left Column: Jobs List -->
            <div class="jobs-list-column">
                <div class="jobs-list-header">
                    <h3>Jobs for Today</h3>
                    <span class="job-count" id="job-count"><?php echo count($jobs ?? []); ?> jobs</span>
                </div>
                <div class="jobs-list-content" id="jobs-list-content">
                    <!-- Jobs list will be populated here -->
                </div>
            </div>
            
            <!-- Right Column: Timeline -->
            <div class="timeline-column">
                <div class="timeline-header">
                    <div class="timeline-controls">
                        <button class="timeline-zoom-btn" data-hours="8" title="8 Hour View">8h</button>
                        <button class="timeline-zoom-btn active" data-hours="12" title="12 Hour View">12h</button>
                        <button class="timeline-zoom-btn" data-hours="24" title="24 Hour View">24h</button>
                        <div class="timeline-current-time" id="timeline-current-time"></div>
                    </div>
                    <div class="time-scale-container">
                        <div class="time-scale" id="time-scale">
                            <?php
                            // Generate 24-hour time scale (6am to 6am next day)
                            $business_day_start = 6; // 6 AM
                            for ($hour = 0; $hour < 24; $hour++) {
                                $display_hour = ($business_day_start + $hour) % 24;
                                $hour_label = sprintf('%02d:00', $display_hour);
                                $is_shift_start = false;
                                $shift_label = '';
                                
                                // Mark shift boundaries
                                if ($display_hour == 6) {
                                    $is_shift_start = true;
                                    $shift_label = 'A';
                                } elseif ($display_hour == 14) {
                                    $is_shift_start = true;
                                    $shift_label = 'B';
                                } elseif ($display_hour == 22) {
                                    $is_shift_start = true;
                                    $shift_label = 'C';
                                }
                                
                                echo '<div class="time-marker' . ($is_shift_start ? ' shift-start' : '') . '" data-hour="' . $hour . '">';
                                echo '<span class="time-label">' . $hour_label . '</span>';
                                if ($shift_label) {
                                    echo '<span class="shift-label">Shift ' . $shift_label . '</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <div class="current-time-indicator" id="current-time-indicator"></div>
                    </div>
                </div>

        <?php
        // Determine layout mode: timeline vs grid
        $use_timeline = false;
        $has_timing_data = false;
        
        // Check if we have timing data from mach_planning
        if (!empty($planning_jobs)) {
            foreach ($planning_jobs as $pj) {
                if (!empty($pj['planned_start']) && !empty($pj['planned_end'])) {
                    $has_timing_data = true;
                    break;
                }
            }
        }
        
        // Use timeline if we have timing data (localStorage will be checked in JS)
        $use_timeline = $has_timing_data;
        
        // Calculate job rows for timeline layout
        $job_rows = [];
        $max_rows = 1;
        
        if ($use_timeline && !empty($jobs) && is_array($jobs)) {
            foreach ($jobs as &$job) {
                $start = !empty($job['op_pln_stdt']) ? strtotime($job['op_pln_stdt']) : time();
                $end = !empty($job['op_pln_endt']) ? strtotime($job['op_pln_endt']) : $start + 7200;
                
                // Find available row for this job
                $row = 0;
                while (true) {
                    $can_use_row = true;
                    if (isset($job_rows[$row])) {
                        foreach ($job_rows[$row] as $existing) {
                            // Check if jobs overlap
                            if (!($end <= $existing['start'] || $start >= $existing['end'])) {
                                $can_use_row = false;
                                break;
                            }
                        }
                    }
                    if ($can_use_row) break;
                    $row++;
                }
                
                // Assign row to job and track it
                $job['row_index'] = $row;
                $job_rows[$row][] = ['start' => $start, 'end' => $end];
                $max_rows = max($max_rows, $row + 1);
            }
        }
        
        // Add EDD color coding function
        function getEddClass($due_date) {
            if (empty($due_date)) return 'edd-unknown';
            
            $due_timestamp = strtotime($due_date);
            $today = strtotime('today');
            $tomorrow = strtotime('tomorrow');
            
            if ($due_timestamp < $today) {
                return 'edd-overdue';
            } elseif ($due_timestamp <= $tomorrow) {
                return 'edd-urgent';
            } else {
                return 'edd-future';
            }
        }
        ?>
                
                <!-- Timeline View -->
                <div class="timeline-view" id="timeline-view">
                    <div class="timeline-scroll-container">
                        <div class="jobs-timeline" style="min-height: <?php echo max(400, ($max_rows * 145) + 40); ?>px; width: 2400px;"> <!-- 24 hours * 100px per hour -->
            <?php
            // Debug: Log job count
            error_log("Timeline view - Jobs array count: " . count($jobs ?? []));
            error_log("Timeline view - Jobs type: " . gettype($jobs));
            error_log("Timeline view - First job: " . json_encode($jobs[0] ?? 'empty'));
            
            // Force display a test message if we have jobs but they're not showing
            if (!empty($jobs) && is_array($jobs) && count($jobs) > 0) {
                echo "<!-- DEBUG: Found " . count($jobs) . " jobs to display -->";
            }
            
            $current_job_found = false;
            $current_job_index = -1;
            if (!empty($jobs) && is_array($jobs)):
                foreach ($jobs as $index => $job):
                $is_current = false;
                $is_next = false;
                $is_completed = $job['op_status'] == 10;
                $is_on_hold = $job['op_status'] == 12;
                
                // Determine if this is current or next job
                if (!$current_job_found && !$is_completed) {
                    $is_current = true;
                    $current_job_found = true;
                    $current_job_index = $index;
                } elseif ($current_job_found && $index === $current_job_index + 1 && !$is_completed) {
                    $is_next = true;
                }
                
                // Calculate timeline position and width for 24-hour view
                $start_time = !empty($job['op_pln_stdt']) ? strtotime($job['op_pln_stdt']) : time();
                $end_time = !empty($job['op_pln_endt']) ? strtotime($job['op_pln_endt']) : $start_time + 7200; // Default 2 hours
                
                // Business day starts at 6 AM
                $business_day_start = strtotime($_SESSION['work_date'] . ' 06:00:00');
                $business_day_duration = 24 * 3600; // 24 hours in seconds
                
                // Calculate positions as pixels (100px per hour)
                $start_offset_hours = ($start_time - $business_day_start) / 3600;
                $duration_hours = ($end_time - $start_time) / 3600;
                
                // Handle jobs that cross midnight
                if ($start_offset_hours < 0) {
                    $start_offset_hours += 24;
                }
                if ($start_offset_hours >= 24) {
                    $start_offset_hours -= 24;
                }
                
                $left_pixels = max(0, $start_offset_hours * 100);
                $width_pixels = max(50, $duration_hours * 100); // Minimum 50px width
                
                // Calculate progress
                $progress_percent = 0;
                if ($job['op_pln_prdqty'] > 0) {
                    $progress_percent = min(100, ($job['op_act_prdqty'] / $job['op_pln_prdqty']) * 100);
                }
                
                $job_class = 'job-block';
                if ($is_current) $job_class .= ' current';
                if ($is_next) $job_class .= ' next';
                if ($is_completed) $job_class .= ' completed';
                if ($is_on_hold) $job_class .= ' on-hold';
            ?>
            <div class="<?php echo $job_class; ?>" 
                 style="left: <?php echo $left_pixels; ?>px; width: <?php echo $width_pixels; ?>px; top: <?php echo ($job['row_index'] * 145); ?>px;"
                 onclick="viewJob(<?php echo $job['op_id']; ?>, <?php echo $is_current ? 'true' : 'false'; ?>)"
                 data-job-id="<?php echo $job['op_id']; ?>"
                 data-start-time="<?php echo date('H:i', $start_time); ?>"
                 data-end-time="<?php echo date('H:i', $end_time); ?>"
                 tabindex="0"
                 role="button"
                 aria-label="Job <?php echo $job['op_lot']; ?> - <?php echo $job['item_code']; ?> from <?php echo date('H:i', $start_time); ?> to <?php echo date('H:i', $end_time); ?>"
                 onkeydown="if(event.key==='Enter'||event.key===' ') viewJob(<?php echo $job['op_id']; ?>, <?php echo $is_current ? 'true' : 'false'; ?>)">
                
                <div class="job-header">
                    <span class="job-lot"><?php echo !empty($job['po_ref']) ? htmlspecialchars($job['po_ref']) : $job['op_lot']; ?></span>
                    <span class="job-status"><?php echo getStatusLabel($job['op_status']); ?></span>
                </div>
                
                <div class="job-content">
                    <div class="job-item"><?php echo $job['item_code']; ?></div>
                    <div class="job-time">
                        <?php echo date('H:i', $start_time); ?> - <?php echo date('H:i', $end_time); ?>
                    </div>
                    <div class="job-qty"><?php echo $job['op_act_prdqty']; ?> / <?php echo $job['op_pln_prdqty']; ?> pcs</div>
                    <?php if (!empty($job['due_date'])): ?>
                        <div class="job-edd <?php echo getEddClass($job['due_date']); ?>">
                            EDD: <?php echo date('d M', strtotime($job['due_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="job-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <span class="progress-text"><?php echo round($progress_percent); ?>%</span>
                </div>
                
                <div class="job-timing">
                    <?php if ($job['op_setup_time'] > 0): ?>
                        <span class="setup-time">Setup: <?php echo $job['op_setup_time']; ?>m</span>
                    <?php endif; ?>
                    <span class="prod-time">Prod: <?php echo round($job['op_prd_time'] / 60); ?>h</span>
                </div>
            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script>
        // Check localStorage for sequenced jobs
        const machineId = <?php echo isset($machine['mm_id']) ? $machine['mm_id'] : 'null'; ?>;
        const workDate = '<?php echo $_SESSION['work_date']; ?>';
        const shift = '<?php echo $_SESSION['shift']; ?>';
        const localStorageKey = `planning_sequence_${machineId}_${workDate}_${shift}`;
        const localSequence = localStorage.getItem(localStorageKey);
        
        // Parse localStorage data
        let sequenceData = null;
        if (localSequence) {
            try {
                sequenceData = JSON.parse(localSequence);
                // Planning saves array directly, wrap it for compatibility
                if (Array.isArray(sequenceData)) {
                    sequenceData = { jobs: sequenceData };
                }
            } catch (e) {
                console.error('Failed to parse localStorage data:', e);
            }
        }
        
        // Check if we have jobs to display
        const hasSequencedJobs = sequenceData && sequenceData.jobs && sequenceData.jobs.length > 0;
        const hasServerJobs = <?php echo (!empty($jobs) && is_array($jobs) && count($jobs) > 0) ? 'true' : 'false'; ?>;
        
        console.log('Jobs from server:', <?php echo json_encode(count($jobs ?? [])); ?>);
        console.log('Has sequenced jobs:', hasSequencedJobs);
        console.log('Has server jobs:', hasServerJobs);
        console.log('Jobs array:', <?php echo json_encode(array_slice($jobs ?? [], 0, 2)); ?>); // Show first 2 jobs for debugging
        
        // Update clock every second
        setInterval(function() {
            const now = new Date();
            const clockEl = document.getElementById('clock');
            if (clockEl) {
                clockEl.textContent = now.toTimeString().split(' ')[0];
            }
            
            // Update current time indicator if in timeline mode
            if (hasSequencedJobs || hasServerJobs) {
                updateTimeIndicator();
            }
        }, 1000);
        
        // View job details
        function viewJob(jobId, isCurrent) {
            window.location.href = 'job.php?id=' + jobId + '&current=' + (isCurrent ? '1' : '0');
        }
        
        // Contact supervisor function
        function contactSupervisor() {
            // Call groupmsg function
            groupmsg();
            
            // Redirect to logout
            window.location.href = 'index.php?logout=1';
        }
        
        // Placeholder groupmsg function
        function groupmsg() {
            const machineCode = '<?php echo $machine_code; ?>';
            const operatorName = '<?php echo $_SESSION['operator_name']; ?>';
            const timestamp = new Date().toLocaleString();
            
            console.log(`Supervisor requested by ${operatorName} on machine ${machineCode} at ${timestamp}`);
            
            // Future enhancement: This could integrate with chat system
            // For now, just log the request
        }
        
        // Update current time indicator position for 24-hour timeline
        function updateTimeIndicator() {
            const now = new Date();
            const currentHour = now.getHours() + (now.getMinutes() / 60);
            
            // Business day starts at 6 AM
            let timelineHour = currentHour - 6;
            if (timelineHour < 0) {
                timelineHour += 24;
            }
            
            // Position in pixels (100px per hour)
            const position = timelineHour * 100;
            
            const indicator = document.getElementById('current-time-indicator');
            const timeDisplay = document.getElementById('timeline-current-time');
            
            if (indicator) {
                indicator.style.left = position + 'px';
                indicator.style.display = 'block';
            }
            
            if (timeDisplay) {
                timeDisplay.textContent = now.toTimeString().split(' ')[0];
            }
        }
        
        // Initialize timeline view and zoom
        function initializeTimeline() {
            const timelineContainer = document.querySelector('.timeline-scroll-container');
            const zoomButtons = document.querySelectorAll('.timeline-zoom-btn');
            
            // Set default zoom to 8 hours (show current time centered)
            if (timelineContainer) {
                const now = new Date();
                const currentHour = now.getHours() - 6; // Relative to 6 AM start
                const scrollPosition = Math.max(0, (currentHour - 4) * 100); // Center current time
                timelineContainer.scrollLeft = scrollPosition;
            }
            
            // Setup zoom controls
            zoomButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const hours = parseInt(this.dataset.hours);
                    zoomButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    zoomToHours(hours);
                });
            });
        }
        
        // Zoom timeline to specific hour range
        function zoomToHours(hours) {
            const timelineContainer = document.querySelector('.timeline-scroll-container');
            const timeline = document.querySelector('.jobs-timeline');
            
            if (!timelineContainer || !timeline) return;
            
            const now = new Date();
            const currentHour = now.getHours() - 6; // Relative to 6 AM start
            
            if (hours === 8) {
                // Show 8 hours centered on current time
                const scrollPosition = Math.max(0, (currentHour - 4) * 100);
                timelineContainer.scrollLeft = scrollPosition;
            } else if (hours === 12) {
                // Show 12 hours from current shift start
                const shiftStarts = [0, 8, 16]; // 6AM, 2PM, 10PM relative to 6AM
                const currentShiftStart = shiftStarts.find(start => currentHour >= start && currentHour < start + 8) || 0;
                timelineContainer.scrollLeft = currentShiftStart * 100;
            } else if (hours === 24) {
                // Show full day, scroll to beginning
                timelineContainer.scrollLeft = 0;
            }
        }
        
        // Populate jobs list in left column
        function populateJobsList() {
            const jobsList = document.getElementById('jobs-list-content');
            const jobCount = document.getElementById('job-count');
            
            if (!jobsList) return;
            
            const jobs = Array.from(document.querySelectorAll('.job-block')).map(block => {
                return {
                    id: block.dataset.jobId,
                    element: block,
                    startTime: block.dataset.startTime,
                    endTime: block.dataset.endTime,
                    lot: block.querySelector('.job-lot')?.textContent || '',
                    item: block.querySelector('.job-item')?.textContent || '',
                    status: block.querySelector('.job-status')?.textContent || '',
                    edd: block.querySelector('.job-edd')?.textContent || '',
                    isCurrent: block.classList.contains('current'),
                    isNext: block.classList.contains('next'),
                    isCompleted: block.classList.contains('completed')
                };
            });
            
            // Sort jobs by start time
            jobs.sort((a, b) => {
                if (a.startTime < b.startTime) return -1;
                if (a.startTime > b.startTime) return 1;
                return 0;
            });
            
            // Generate list HTML
            let listHtml = '';
            jobs.forEach(job => {
                const statusClass = job.isCurrent ? 'current' : (job.isNext ? 'next' : (job.isCompleted ? 'completed' : ''));
                
                // Get EDD class from timeline job block
                const eddElement = job.element.querySelector('.job-edd');
                const eddClass = eddElement ? Array.from(eddElement.classList).find(cls => cls.startsWith('edd-')) || '' : '';
                
                listHtml += `
                    <div class="job-list-item ${statusClass}" data-job-id="${job.id}" onclick="scrollToJob('${job.id}')">
                        <div class="job-list-header">
                            <span class="job-list-lot">${job.lot}</span>
                            <span class="job-list-time">${job.startTime}-${job.endTime}</span>
                        </div>
                        <div class="job-list-content">
                            <div class="job-list-item-code">${job.item}</div>
                            <div class="job-list-status">${job.status}</div>
                        </div>
                        ${job.edd ? `<div class="job-list-edd ${eddClass}">${job.edd}</div>` : ''}
                    </div>
                `;
            });
            
            jobsList.innerHTML = listHtml;
            
            if (jobCount) {
                jobCount.textContent = `${jobs.length} job${jobs.length !== 1 ? 's' : ''}`;
            }
        }
        
        // Scroll timeline to specific job
        function scrollToJob(jobId) {
            const jobBlock = document.querySelector(`[data-job-id="${jobId}"]`);
            const timelineContainer = document.querySelector('.timeline-scroll-container');
            
            if (jobBlock && timelineContainer) {
                const jobLeft = parseInt(jobBlock.style.left);
                const containerWidth = timelineContainer.clientWidth;
                const scrollPosition = Math.max(0, jobLeft - (containerWidth / 2));
                
                timelineContainer.scrollTo({
                    left: scrollPosition,
                    behavior: 'smooth'
                });
                
                // Highlight the job briefly
                jobBlock.classList.add('highlighted');
                setTimeout(() => jobBlock.classList.remove('highlighted'), 2000);
            }
        }
        
        // Show splash screen or timeline on page load
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('loading-screen');
            const splashScreen = document.getElementById('no-data-splash');
            const timelineContainer = document.getElementById('timeline-container');
            
            // Show loading screen briefly for better UX
            setTimeout(() => {
                // Hide loading screen
                if (loadingScreen) loadingScreen.style.display = 'none';
                
                // Check for data availability with better error handling
                const hasJobs = hasSequencedJobs || hasServerJobs;
                
                if (!hasJobs) {
                    // No data available - show splash screen
                    if (splashScreen) splashScreen.style.display = 'flex';
                    if (timelineContainer) timelineContainer.style.display = 'none';
                } else {
                    // We have data - show timeline
                    if (splashScreen) splashScreen.style.display = 'none';
                    if (timelineContainer) timelineContainer.style.display = 'block';
                    
                    // Initialize timeline features
                    try {
                        initializeTimeline();
                        populateJobsList();
                        updateTimeIndicator();
                        
                        // If we have localStorage sequence, use it to enhance the display
                        if (hasSequencedJobs && sequenceData) {
                            console.log('Using localStorage sequence:', sequenceData);
                        }
                    } catch (error) {
                        console.error('Timeline initialization error:', error);
                        // Show error message
                        showErrorMessage('Failed to load timeline. Please refresh the page.');
                        // Fallback to basic display
                        updateTimeIndicator();
                    }
                }
            }, 800); // Show loading for 800ms
        });
        
        // Error message helper
        function showErrorMessage(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-banner';
            errorDiv.innerHTML = `
                <div class="error-content">
                    <span class="error-icon">⚠️</span>
                    <span class="error-text">${message}</span>
                    <button onclick="location.reload()" class="error-retry">Retry</button>
                </div>
            `;
            document.body.appendChild(errorDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }
    </script>
</body>
</html>

<?php
// Helper function to get status label
function getStatusLabel($status) {
    $labels = [
        1 => 'New',
        2 => 'Assigned',
        3 => 'Setup',
        4 => 'FPQC',
        5 => 'In Process',
        6 => 'Paused',
        7 => 'Breakdown',
        8 => 'On Hold',
        9 => 'LPQC',
        10 => 'Complete',
        12 => 'QC Hold',
        13 => 'QC Check'
    ];
    return $labels[$status] ?? 'Unknown';
}
?>