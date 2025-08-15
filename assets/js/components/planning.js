/**
 * Planning Component - Handles changeover time configuration and timeline recalculation
 * Implements automatic timeline updates when changeover time is modified
 */

class PlanningComponent {
    constructor(config = {}) {
        this.config = {
            changeoverTime: config.changeoverTime || 15,
            defaultJobTime: config.defaultJobTime || 50,
            ...config
        };
        
        this.state = {
            isRecalculating: false,
            lastUpdate: null,
            isInitialLoad: true
        };
        
        this.init();
        
        // After initialization, set flag to false
        setTimeout(() => {
            this.state.isInitialLoad = false;
        }, 1000);
    }

    init() {
        console.log('PlanningComponent: Initializing with config:', this.config);
        this.setupEventListeners();
        this.createConfigInterface();
        this.updateChangeoverDisplays();
    }

    /**
     * Setup event listeners for changeover time changes
     */
    setupEventListeners() {
        // Listen for changeover time input changes
        document.addEventListener('input', (e) => {
            if (e.target.id === 'changeover-time-input') {
                this.handleChangeoverTimeChange(e.target.value);
            }
        });

        // Listen for job sequence updates to recalculate timeline
        document.addEventListener('jobSequenceUpdated', () => {
            this.recalculateTimeline();
        });

        // Listen for manual recalculation triggers
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('recalculate-timeline')) {
                this.recalculateTimeline();
            }
        });
    }

    /**
     * Create configuration interface for changeover time
     */
    createConfigInterface() {
        const configPanel = document.createElement('div');
        configPanel.id = 'changeover-config-panel';
        configPanel.className = 'config-panel';
        configPanel.innerHTML = `
            <div class="config-header">
                <h3>Planning Configuration</h3>
                <button class="config-toggle" onclick="this.parentElement.parentElement.classList.toggle('collapsed')">
                    <span>⚙️</span>
                </button>
            </div>
            <div class="config-content">
                <div class="config-item">
                    <label for="changeover-time-input">Changeover Time (minutes):</label>
                    <input type="number" 
                           id="changeover-time-input" 
                           value="${this.config.changeoverTime}" 
                           min="1" 
                           max="120" 
                           step="1">
                    <small>Time between job changes</small>
                </div>
                <div class="config-actions">
                    <button class="recalculate-timeline btn-secondary">Update Timeline</button>
                    <button class="reset-config btn-secondary" onclick="planningComponent.resetToDefault()">Reset</button>
                </div>
                <div class="config-status" id="config-status"></div>
            </div>
        `;

        // Add CSS for the config panel
        this.addConfigPanelCSS();

        // Insert the panel into the header actions area, before the logout button
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const logoutBtn = headerActions.querySelector('.btn-logout');
            if (logoutBtn) {
                headerActions.insertBefore(configPanel, logoutBtn);
            } else {
                headerActions.appendChild(configPanel);
            }
        } else {
            // Fallback: add to planning header
            const planningHeader = document.querySelector('.planning-header');
            if (planningHeader) {
                planningHeader.appendChild(configPanel);
            }
        }
    }

    /**
     * Add CSS styles for the config panel
     */
    addConfigPanelCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .config-panel {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                min-width: 200px;
                transition: all 0.3s ease;
                margin-right: 0.5rem;
            }
            
            .config-panel.collapsed .config-content {
                display: none;
            }
            
            .config-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 1rem;
                border-bottom: 1px solid #e5e7eb;
                background: #f9fafb;
                border-radius: 8px 8px 0 0;
            }
            
            .config-header h3 {
                margin: 0;
                font-size: 0.9rem;
                color: #374151;
            }
            
            .config-toggle {
                background: none;
                border: none;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                transition: background 0.2s;
            }
            
            .config-toggle:hover {
                background: #e5e7eb;
            }
            
            .config-content {
                padding: 1rem;
            }
            
            .config-item {
                margin-bottom: 1rem;
            }
            
            .config-item label {
                display: block;
                margin-bottom: 0.25rem;
                font-weight: 500;
                font-size: 0.875rem;
                color: #374151;
            }
            
            .config-item input {
                width: 100%;
                padding: 0.5rem;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 0.875rem;
            }
            
            .config-item small {
                display: block;
                margin-top: 0.25rem;
                color: #6b7280;
                font-size: 0.75rem;
            }
            
            .config-actions {
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
            }
            
            .config-actions button {
                flex: 1;
                padding: 0.5rem 1rem;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #f3f4f6;
                color: #374151;
                cursor: pointer;
                font-size: 0.8rem;
                font-weight: 500;
                transition: all 0.2s;
            }
            
            .config-actions button:hover {
                background: #e5e7eb;
                border-color: #9ca3af;
                transform: translateY(-1px);
            }
            
            .config-actions button.btn-secondary:first-child {
                background: #2563eb;
                color: white;
                border-color: #2563eb;
            }
            
            .config-actions button.btn-secondary:first-child:hover {
                background: #1d4ed8;
                border-color: #1d4ed8;
            }
            
            .config-actions button.btn-secondary:last-child {
                background: #6b7280;
                color: white;
                border-color: #6b7280;
            }
            
            .config-actions button.btn-secondary:last-child:hover {
                background: #4b5563;
                border-color: #4b5563;
            }
            
            .config-status {
                margin-top: 0.5rem;
                padding: 0.5rem;
                border-radius: 4px;
                font-size: 0.8rem;
                min-height: 1.5rem;
            }
            
            .config-status.success {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }
            
            .config-status.error {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #fca5a5;
            }
            
            .config-status.info {
                background: #dbeafe;
                color: #1e40af;
                border: 1px solid #93c5fd;
            }

            @media (max-width: 768px) {
                .config-panel {
                    min-width: 250px;
                    margin-left: 0;
                    margin-top: 0.5rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Handle changeover time change
     */
    handleChangeoverTimeChange(newValue) {
        const value = parseInt(newValue);
        if (isNaN(value) || value < 1 || value > 120) {
            this.showConfigStatus('Invalid changeover time. Must be between 1-120 minutes.', 'error');
            return;
        }

        this.config.changeoverTime = value;
        this.showConfigStatus('Changeover time updated. Timeline will be recalculated.', 'info');
        
        // Debounce the recalculation to avoid excessive updates
        clearTimeout(this.recalculateTimer);
        this.recalculateTimer = setTimeout(() => {
            this.recalculateTimeline();
            this.updateChangeoverDisplays();
        }, 500);
    }

    /**
     * Recalculate the entire timeline with new changeover time
     */
    recalculateTimeline() {
        if (this.state.isRecalculating) return;

        this.state.isRecalculating = true;
        this.showConfigStatus('Recalculating timeline...', 'info');

        try {
            const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
            
            if (sequencedJobs.length === 0) {
                this.showConfigStatus('No jobs in sequence to calculate.', 'info');
                this.state.isRecalculating = false;
                return;
            }

            // Recalculate job timings
            const timeline = this.calculateJobTimeline(sequencedJobs);
            
            // Update displays
            this.updateTimeEstimation(timeline);
            this.updateJobStartEndTimes(sequencedJobs, timeline);
            
            this.state.lastUpdate = new Date();
            
            // Only show notification if not initial load
            if (!this.state.isInitialLoad) {
                this.showConfigStatus(`Timeline updated successfully (${timeline.totalJobs} jobs, ${timeline.totalTime} min)`, 'success');
            }
            
            // Trigger custom event for other components
            document.dispatchEvent(new CustomEvent('timelineRecalculated', { 
                detail: { timeline, changeoverTime: this.config.changeoverTime }
            }));

        } catch (error) {
            console.error('Error recalculating timeline:', error);
            this.showConfigStatus('Error recalculating timeline: ' + error.message, 'error');
        } finally {
            this.state.isRecalculating = false;
        }
    }

    /**
     * Calculate job timeline with changeover times
     */
    calculateJobTimeline(sequencedJobs) {
        const shiftInfo = window.SHIFT_TIMES ? window.SHIFT_TIMES[window.SHIFT] : { start: '06:00', end: '14:00' };
        let currentTime = this.parseTime(shiftInfo.start);
        let totalTime = 0;
        let totalChangeoverTime = 0;
        const jobs = [];
        
        // Get stored individual changeover times
        const storedTimes = JSON.parse(localStorage.getItem('changeover_times') || '{}');

        sequencedJobs.forEach((job, index) => {
            const calcTime = parseInt(job.dataset.calcTime) || this.config.defaultJobTime;
            const startTime = currentTime;
            const endTime = currentTime + calcTime;
            
            jobs.push({
                index: index + 1,
                jobId: job.dataset.jobId,
                lot: job.dataset.lot,
                calcTime: calcTime,
                startTime: startTime,
                endTime: endTime,
                startTimeFormatted: this.formatTime(startTime),
                endTimeFormatted: this.formatTime(endTime)
            });

            currentTime = endTime;
            totalTime += calcTime;

            // Add changeover time if not the last job
            if (index < sequencedJobs.length - 1) {
                // Use individual changeover time if stored, otherwise use default
                const individualChangeoverTime = storedTimes[`${index}`] || this.config.changeoverTime;
                currentTime += individualChangeoverTime;
                totalChangeoverTime += individualChangeoverTime;
            }
        });

        const shiftEndTime = this.parseTime(shiftInfo.end);
        const adjustedShiftEndTime = window.SHIFT === 'C' && shiftEndTime < currentTime ? 
                                   shiftEndTime + 24 * 60 : shiftEndTime;
        
        const isOverrun = currentTime > adjustedShiftEndTime;
        const overrunTime = isOverrun ? currentTime - adjustedShiftEndTime : 0;
        const finishTime = this.formatTime(currentTime);

        return {
            jobs: jobs,
            totalJobs: jobs.length,
            totalTime: totalTime,
            totalChangeoverTime: totalChangeoverTime,
            totalDuration: totalTime + totalChangeoverTime,
            finishTime: finishTime,
            isOverrun: isOverrun,
            overrunTime: overrunTime,
            shiftEndTime: shiftInfo.end
        };
    }

    /**
     * Update the time estimation display
     */
    updateTimeEstimation(timeline) {
        const estimationDiv = document.getElementById('time-estimation');
        const timelineDetails = document.getElementById('timeline-details');
        
        if (!estimationDiv || !timelineDetails) return;

        // Get stored individual changeover times for display
        const storedTimes = JSON.parse(localStorage.getItem('changeover_times') || '{}');
        
        let timelineHTML = '';
        timeline.jobs.forEach((job, index) => {
            timelineHTML += `<div class="timeline-job">
                ${job.index}. ${job.lot}: ${job.startTimeFormatted} - ${job.endTimeFormatted} (${job.calcTime}min)
            </div>`;
            
            // Add changeover time display if not the last job
            if (index < timeline.jobs.length - 1) {
                const changeoverTime = storedTimes[`${index}`] || this.config.changeoverTime;
                timelineHTML += `<div style="margin-left: 1rem; color: #f97316; font-size: 0.85rem;">
                    ↓ ${changeoverTime} min changeover
                </div>`;
            }
        });

        const summaryHTML = `
            <div class="timeline-summary">
                <div class="summary-row">
                    <span>Total Production Time:</span>
                    <span><strong>${timeline.totalTime} minutes (${Math.round(timeline.totalTime/60*10)/10} hours)</strong></span>
                </div>
                <div class="summary-row">
                    <span>Total Changeover Time:</span>
                    <span><strong>${timeline.totalChangeoverTime} minutes (${timeline.totalJobs - 1} changes)</strong></span>
                </div>
                <div class="summary-row">
                    <span>Total Duration:</span>
                    <span><strong>${timeline.totalDuration} minutes</strong></span>
                </div>
                <div class="summary-row">
                    <span>Estimated Finish:</span>
                    <span><strong>${timeline.finishTime}</strong></span>
                </div>
                ${timeline.isOverrun ? 
                    `<div class="summary-row overrun">
                        <span>Shift Overrun:</span>
                        <span><strong>${timeline.overrunTime} minutes past ${timeline.shiftEndTime}</strong></span>
                    </div>` : ''
                }
            </div>
        `;

        timelineDetails.innerHTML = timelineHTML + summaryHTML;
        estimationDiv.style.display = 'block';

        // Add CSS for better timeline display
        this.addTimelineCSS();
    }

    /**
     * Add CSS for timeline display
     */
    addTimelineCSS() {
        if (document.getElementById('timeline-css')) return;

        const style = document.createElement('style');
        style.id = 'timeline-css';
        style.textContent = `
            .timeline-job {
                padding: 0.25rem 0;
                border-bottom: 1px solid #e5e7eb;
                font-family: monospace;
                font-size: 0.85rem;
            }
            
            .timeline-summary {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 2px solid #d1d5db;
            }
            
            .summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.25rem 0;
            }
            
            .summary-row.overrun {
                color: #dc2626;
                font-weight: 600;
                background: #fee2e2;
                padding: 0.5rem;
                border-radius: 4px;
                margin-top: 0.5rem;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Update changeover time displays in the UI
     */
    updateChangeoverDisplays() {
        // This function intentionally left empty
        // Changeover times are now displayed between cards, not on cards
        console.log('Changeover time set to:', this.config.changeoverTime);
    }

    /**
     * Update individual job start/end times (if needed for future features)
     */
    updateJobStartEndTimes(jobs, timeline) {
        // This could be used to add start/end time tooltips or displays to job cards
        jobs.forEach((jobElement, index) => {
            const jobData = timeline.jobs[index];
            if (jobData) {
                // Add data attributes for potential tooltips or displays
                jobElement.setAttribute('data-start-time', jobData.startTimeFormatted);
                jobElement.setAttribute('data-end-time', jobData.endTimeFormatted);
                
                // Add tooltip if not exists
                if (!jobElement.querySelector('.job-timing-tooltip')) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'job-timing-tooltip';
                    tooltip.innerHTML = `${jobData.startTimeFormatted} - ${jobData.endTimeFormatted}`;
                    tooltip.style.display = 'none';
                    jobElement.appendChild(tooltip);
                }
            }
        });
    }

    /**
     * Show status message in config panel
     */
    showConfigStatus(message, type = 'info') {
        // Only show in global notification, not in config panel
        this.showNotification(message, type);
    }
    
    /**
     * Show notification at top of page
     */
    showNotification(message, type = 'info') {
        // Remove existing notification if any
        const existing = document.querySelector('.planning-notification');
        if (existing) {
            existing.remove();
        }
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `planning-notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            font-size: 14px;
            font-weight: 500;
            animation: slideInRight 0.3s ease;
            max-width: 400px;
        `;
        notification.textContent = message;
        
        // Add animation style if not exists
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(notification);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    /**
     * Reset configuration to default values
     */
    resetToDefault() {
        this.config.changeoverTime = 15;
        
        const input = document.getElementById('changeover-time-input');
        if (input) input.value = 15;
        
        this.recalculateTimeline();
        this.updateChangeoverDisplays();
        this.showConfigStatus('Configuration reset to default values.', 'success');
    }

    /**
     * Get current changeover time
     */
    getChangeoverTime() {
        return this.config.changeoverTime;
    }
    
    /**
     * Get timeline data with calculated times for saving
     */
    getTimelineData() {
        const sequencedJobs = document.querySelectorAll('#todays-sequence .job-card');
        if (sequencedJobs.length === 0) {
            return null;
        }
        
        // Get shift and work date from global variables (set by PHP)
        const shift = typeof SHIFT !== 'undefined' ? SHIFT : 'A';
        const workDate = typeof WORK_DATE !== 'undefined' ? WORK_DATE : new Date().toISOString().split('T')[0];
        const shiftTimes = {
            'A': { start: '06:00', end: '14:00' },
            'B': { start: '14:00', end: '22:00' },
            'C': { start: '22:00', end: '06:00' }
        };
        const shiftInfo = shiftTimes[shift];
        let currentTime = this.parseTime(shiftInfo.start);
        
        const jobs = [];
        
        sequencedJobs.forEach((job, index) => {
            const setupTime = 30; // Default setup time
            const prodTime = parseInt(job.dataset.calcTime) || 50;
            const changeoverTime = this.config.changeoverTime;
            
            const startTime = new Date(workDate + ' ' + this.formatTime(currentTime));
            currentTime += setupTime + prodTime;
            const endTime = new Date(workDate + ' ' + this.formatTime(currentTime));
            
            jobs.push({
                jobId: job.dataset.jobId,
                lot: job.dataset.lot,
                item: job.dataset.item,
                quantity: job.dataset.quantity,
                setupTime: setupTime,
                prodTime: prodTime,
                calcTime: prodTime,
                changeoverTime: changeoverTime,
                startTime: startTime.toISOString().slice(0, 19).replace('T', ' '),
                endTime: endTime.toISOString().slice(0, 19).replace('T', ' '),
                startTimeFormatted: this.formatTime(currentTime - setupTime - prodTime),
                endTimeFormatted: this.formatTime(currentTime)
            });
            
            // Add changeover time for next job
            if (index < sequencedJobs.length - 1) {
                currentTime += changeoverTime;
            }
        });
        
        return {
            jobs: jobs,
            totalTime: currentTime - this.parseTime(shiftInfo.start),
            shiftStart: shiftInfo.start,
            shiftEnd: shiftInfo.end
        };
    }

    /**
     * Set changeover time programmatically
     */
    setChangeoverTime(minutes) {
        if (minutes < 1 || minutes > 120) {
            throw new Error('Changeover time must be between 1-120 minutes');
        }
        
        this.config.changeoverTime = minutes;
        
        const input = document.getElementById('changeover-time-input');
        if (input) input.value = minutes;
        
        this.recalculateTimeline();
        this.updateChangeoverDisplays();
    }

    /**
     * Utility function to parse time string (HH:MM) to minutes
     */
    parseTime(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    /**
     * Utility function to format minutes to time string (HH:MM)
     */
    formatTime(minutes) {
        const hours = Math.floor(minutes / 60) % 24;
        const mins = minutes % 60;
        return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
    }
}

// Make it globally available
window.PlanningComponent = PlanningComponent;