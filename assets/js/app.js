/**
 * Job Center - Main Application JavaScript
 * Handles all operator interface interactions, API communication, and offline functionality
 */

// Application configuration
const JobCenter = {
    config: {
        apiUrl: '/jc/api/',
        refreshInterval: 30000, // 30 seconds for QC hold check
        offlineQueueKey: 'jc_offline_queue',
        maxRetries: 3,
        retryDelay: 1000
    },
    
    // Application state
    state: {
        isOnline: navigator.onLine,
        currentJobId: null,
        offlineQueue: [],
        activeModal: null,
        refreshTimer: null,
        qcHoldCheck: null
    },

    /**
     * Initialize the application
     */
    init() {
        console.log('JobCenter: Initializing application...');
        
        // Load offline queue from localStorage
        this.loadOfflineQueue();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Setup network monitoring
        this.setupNetworkMonitoring();
        
        // Setup auto-refresh for QC hold status
        this.setupAutoRefresh();
        
        // Process any queued offline actions
        this.processOfflineQueue();
        
        console.log('JobCenter: Application initialized');
    },

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Modal functionality
        this.setupModalHandlers();
        
        // Form submissions
        this.setupFormHandlers();
        
        // Button click handlers
        this.setupButtonHandlers();
        
        // Keyboard shortcuts
        this.setupKeyboardHandlers();
        
        // Touch/click feedback
        this.setupTouchFeedback();
    },

    /**
     * Setup modal management system
     */
    setupModalHandlers() {
        // Close modal overlay click
        const overlay = document.getElementById('modalOverlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closeModal();
                }
            });
        }

        // Close modal buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-close')) {
                this.closeModal();
            }
        });
    },

    /**
     * Setup form submission handlers
     */
    setupFormHandlers() {
        const forms = [
            'setupForm', 'fpqcForm', 'pauseForm', 'resumeForm', 
            'completeForm', 'breakdownForm', 'qccheckForm', 
            'testForm', 'alertForm', 'contactForm'
        ];

        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const action = formId.replace('Form', '');
                    this.handleFormSubmit(action, new FormData(form));
                });
            }
        });

        // Special handlers for dynamic forms
        this.setupCompletionFormHandlers();
    },

    /**
     * Setup button click handlers for all 12 operator buttons
     */
    setupButtonHandlers() {
        // Primary action buttons
        const buttonActions = {
            'btn-setup': 'setup',
            'btn-fpqc': 'fpqc', 
            'btn-pause': 'pause',
            'btn-resume': 'resume',
            'btn-complete': 'complete',
            'btn-breakdown': 'breakdown'
        };

        Object.entries(buttonActions).forEach(([className, action]) => {
            const buttons = document.querySelectorAll(`.${className}`);
            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    if (!button.disabled) {
                        this.showModal(action);
                    }
                });
            });
        });

        // Secondary action buttons
        const secondaryActions = {
            'drawing': this.showDrawing,
            'chart': this.showControlChart,
            'contact': () => this.showModal('contact'),
            'qccheck': () => this.showModal('qccheck'),
            'test': () => this.showModal('test'),
            'alert': () => this.showModal('alert'),
            'lock': this.lockScreen
        };

        Object.entries(secondaryActions).forEach(([action, handler]) => {
            const buttons = document.querySelectorAll(`[onclick*="${action}"]`);
            buttons.forEach(button => {
                // Remove inline onclick and add proper event listener
                button.removeAttribute('onclick');
                button.addEventListener('click', () => {
                    if (!button.disabled) {
                        if (typeof handler === 'string') {
                            this.showModal(handler);
                        } else {
                            handler.call(this);
                        }
                    }
                });
            });
        });
    },

    /**
     * Setup keyboard shortcuts and accessibility
     */
    setupKeyboardHandlers() {
        document.addEventListener('keydown', (e) => {
            // Close modal on Escape
            if (e.key === 'Escape' && this.state.activeModal) {
                this.closeModal();
            }
            
            // Quick actions with Alt + number
            if (e.altKey && !isNaN(e.key)) {
                e.preventDefault();
                this.handleQuickAction(parseInt(e.key));
            }
        });
    },

    /**
     * Setup touch feedback for better UX
     */
    setupTouchFeedback() {
        // Add touch feedback to all buttons
        const buttons = document.querySelectorAll('button, .action-btn, .action-btn-small');
        buttons.forEach(button => {
            button.addEventListener('touchstart', () => {
                button.classList.add('touch-active');
            });
            
            button.addEventListener('touchend', () => {
                setTimeout(() => {
                    button.classList.remove('touch-active');
                }, 150);
            });
        });
    },

    /**
     * Setup network monitoring for offline functionality
     */
    setupNetworkMonitoring() {
        window.addEventListener('online', () => {
            console.log('JobCenter: Network online');
            this.state.isOnline = true;
            this.showNotification('Connection restored', 'success');
            this.processOfflineQueue();
        });

        window.addEventListener('offline', () => {
            console.log('JobCenter: Network offline');
            this.state.isOnline = false;
            this.showNotification('Working offline - actions will be queued', 'warning');
        });
    },

    /**
     * Setup auto-refresh functionality for QC hold status
     */
    setupAutoRefresh() {
        // Check if we're on QC hold
        const isQcHold = document.body.classList.contains('qc-hold');
        
        if (isQcHold) {
            // Auto-refresh every 30 seconds when on QC hold
            this.state.qcHoldCheck = setInterval(() => {
                this.checkQcHoldStatus();
            }, this.config.refreshInterval);
        }

        // General status refresh every 60 seconds
        this.state.refreshTimer = setInterval(() => {
            this.refreshJobStatus();
        }, 60000);
    },

    /**
     * Setup specific handlers for completion form
     */
    setupCompletionFormHandlers() {
        const finalQtyInput = document.getElementById('final_qty');
        if (finalQtyInput) {
            finalQtyInput.addEventListener('input', () => {
                this.updateCompletionSummary();
            });
        }
    },

    /**
     * Show modal with proper management
     */
    showModal(action) {
        console.log(`JobCenter: Opening modal for action: ${action}`);
        
        const overlay = document.getElementById('modalOverlay');
        const modal = document.getElementById(action + 'Modal');
        
        if (!modal) {
            console.error('Modal not found for action:', action);
            this.showNotification('Modal not found', 'error');
            return;
        }

        // Close any existing modal first
        this.closeModal();

        // Show new modal
        overlay.classList.add('active');
        modal.classList.add('active');
        this.state.activeModal = action;

        // Focus first input for accessibility
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }

        // Initialize modal-specific functionality
        this.initializeModalFeatures(action, modal);
    },

    /**
     * Close active modal
     */
    closeModal() {
        const overlay = document.getElementById('modalOverlay');
        const modals = document.querySelectorAll('.modal');
        
        overlay.classList.remove('active');
        modals.forEach(modal => modal.classList.remove('active'));
        this.state.activeModal = null;
    },

    /**
     * Initialize modal-specific features
     */
    initializeModalFeatures(action, modal) {
        switch (action) {
            case 'complete':
                this.updateCompletionSummary();
                break;
            case 'drawing':
                this.loadDrawing();
                break;
            case 'chart':
                this.loadControlChart();
                break;
        }
    },

    /**
     * Handle form submission with offline support
     */
    async handleFormSubmit(action, formData) {
        console.log(`JobCenter: Handling form submission for action: ${action}`);
        
        const form = document.getElementById(action + 'Form');
        if (!form) {
            console.error('Form not found for action:', action);
            return;
        }

        // Add job context to form data
        const jobId = this.getCurrentJobId();
        if (jobId) {
            formData.append('job_id', jobId);
        }
        formData.append('action', action);
        formData.append('timestamp', Date.now());

        // Show loading state
        this.setFormLoading(form, true);

        try {
            if (this.state.isOnline) {
                // Online: Submit directly
                await this.submitAction(action, formData);
            } else {
                // Offline: Queue action
                this.queueOfflineAction(action, formData);
                this.showNotification('Action queued - will sync when online', 'info');
                this.closeModal();
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showNotification('Submission failed: ' + error.message, 'error');
        } finally {
            this.setFormLoading(form, false);
        }
    },

    /**
     * Submit action to API
     */
    async submitAction(action, formData) {
        const response = await fetch(`${this.config.apiUrl}actions/${action}.php`, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        if (data.success) {
            this.closeModal();
            this.showNotification(data.message || 'Action completed successfully', 'success');
            
            // Refresh page after brief delay for user feedback
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
    },

    /**
     * Queue action for offline processing
     */
    queueOfflineAction(action, formData) {
        const queueItem = {
            id: Date.now() + Math.random(),
            action: action,
            data: this.formDataToObject(formData),
            timestamp: Date.now(),
            retries: 0
        };

        this.state.offlineQueue.push(queueItem);
        this.saveOfflineQueue();
        
        console.log('JobCenter: Action queued for offline processing', queueItem);
    },

    /**
     * Process offline queue when connection is restored
     */
    async processOfflineQueue() {
        if (!this.state.isOnline || this.state.offlineQueue.length === 0) {
            return;
        }

        console.log(`JobCenter: Processing ${this.state.offlineQueue.length} offline actions`);
        
        const itemsToProcess = [...this.state.offlineQueue];
        
        for (const item of itemsToProcess) {
            try {
                const formData = this.objectToFormData(item.data);
                await this.submitAction(item.action, formData);
                
                // Remove successful item from queue
                this.state.offlineQueue = this.state.offlineQueue.filter(q => q.id !== item.id);
                
            } catch (error) {
                console.error('Failed to sync offline action:', error);
                
                // Increment retry count
                item.retries++;
                
                // Remove if max retries reached
                if (item.retries >= this.config.maxRetries) {
                    console.warn('Max retries reached for offline action, removing:', item);
                    this.state.offlineQueue = this.state.offlineQueue.filter(q => q.id !== item.id);
                    this.showNotification(`Failed to sync ${item.action} action`, 'error');
                }
            }
        }

        this.saveOfflineQueue();
        
        if (this.state.offlineQueue.length === 0) {
            this.showNotification('All offline actions synced successfully', 'success');
        }
    },

    /**
     * Load and save offline queue from localStorage
     */
    loadOfflineQueue() {
        try {
            const stored = localStorage.getItem(this.config.offlineQueueKey);
            this.state.offlineQueue = stored ? JSON.parse(stored) : [];
        } catch (error) {
            console.error('Failed to load offline queue:', error);
            this.state.offlineQueue = [];
        }
    },

    saveOfflineQueue() {
        try {
            localStorage.setItem(this.config.offlineQueueKey, JSON.stringify(this.state.offlineQueue));
        } catch (error) {
            console.error('Failed to save offline queue:', error);
        }
    },

    /**
     * Check QC hold status via API
     */
    async checkQcHoldStatus() {
        if (!this.state.isOnline) return;

        try {
            const jobId = this.getCurrentJobId();
            if (!jobId) return;

            const response = await fetch(`${this.config.apiUrl}actions/status.php?job_id=${jobId}`);
            const data = await response.json();

            if (data.success && data.status !== 12) { // Not on QC hold anymore
                console.log('JobCenter: QC hold cleared, refreshing page');
                location.reload();
            }
        } catch (error) {
            console.error('Failed to check QC hold status:', error);
        }
    },

    /**
     * Refresh job status
     */
    async refreshJobStatus() {
        if (!this.state.isOnline) return;

        try {
            const jobId = this.getCurrentJobId();
            if (!jobId) return;

            const response = await fetch(`${this.config.apiUrl}actions/get_job_data.php?job_id=${jobId}`);
            const data = await response.json();

            if (data.success) {
                // Check if job was split or status changed significantly
                this.handleStatusChange(data.job);
            }
        } catch (error) {
            console.error('Failed to refresh job status:', error);
        }
    },

    /**
     * Handle status changes
     */
    handleStatusChange(jobData) {
        // Check if quantity is at or above planned (completion alert)
        if (jobData.op_act_prdqty >= jobData.op_pln_prdqty) {
            this.showCompletionAlert();
        }

        // Check if job was split (different job ID or significant changes)
        // This would require more complex logic based on your split detection needs
    },

    /**
     * Show completion alert when quantity reached
     */
    showCompletionAlert() {
        if (!document.querySelector('.completion-alert')) {
            const alert = document.createElement('div');
            alert.className = 'completion-alert';
            alert.innerHTML = `
                <div class="alert-content">
                    <h3>Production Target Reached!</h3>
                    <p>उत्पादन लक्ष्य पहुंच गया!</p>
                    <button onclick="JobCenter.showModal('complete')" class="btn btn-success">
                        Mark Complete / पूर्ण करें
                    </button>
                    <button onclick="this.parentElement.parentElement.remove()" class="btn btn-secondary">
                        Continue / जारी रखें
                    </button>
                </div>
            `;
            document.body.appendChild(alert);
        }
    },

    /**
     * Update completion summary calculations
     */
    updateCompletionSummary() {
        const finalQtyInput = document.getElementById('final_qty');
        const plannedQtyElement = document.querySelector('[data-planned-qty]');
        
        if (!finalQtyInput || !plannedQtyElement) return;

        const finalQty = parseInt(finalQtyInput.value) || 0;
        const plannedQty = parseInt(plannedQtyElement.dataset.plannedQty) || 
                          parseInt(finalQtyInput.getAttribute('max')) || 0;

        // Update display elements
        const finalQtyDisplay = document.getElementById('final_qty_display');
        const efficiencyDisplay = document.getElementById('efficiency_display');

        if (finalQtyDisplay) {
            finalQtyDisplay.textContent = `${finalQty} pcs`;
        }

        if (efficiencyDisplay && plannedQty > 0) {
            const efficiency = Math.round((finalQty / plannedQty) * 100);
            efficiencyDisplay.textContent = `${efficiency}%`;
            
            // Color code efficiency
            efficiencyDisplay.className = efficiency >= 100 ? 'efficiency-good' : 
                                         efficiency >= 80 ? 'efficiency-fair' : 'efficiency-poor';
        }
    },

    /**
     * Show technical drawing
     */
    showDrawing() {
        console.log('JobCenter: Loading technical drawing');
        
        // Create drawing modal if it doesn't exist
        let drawingModal = document.getElementById('drawingModal');
        if (!drawingModal) {
            drawingModal = this.createDrawingModal();
        }
        
        this.showModal('drawing');
    },

    /**
     * Show control chart
     */
    showControlChart() {
        console.log('JobCenter: Loading control chart');
        
        // Create chart modal if it doesn't exist
        let chartModal = document.getElementById('chartModal');
        if (!chartModal) {
            chartModal = this.createChartModal();
        }
        
        this.showModal('chart');
    },

    /**
     * Create drawing modal dynamically
     */
    createDrawingModal() {
        const modal = document.createElement('div');
        modal.id = 'drawingModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-header">
                <h2>Technical Drawing / तकनीकी चित्र</h2>
                <button class="modal-close" onclick="JobCenter.closeModal()">&times;</button>
            </div>
            <div class="modal-body drawing-viewer">
                <div class="drawing-placeholder">
                    <p>Loading drawing...</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    },

    /**
     * Create chart modal dynamically
     */
    createChartModal() {
        const modal = document.createElement('div');
        modal.id = 'chartModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-header">
                <h2>Control Chart / नियंत्रण चार्ट</h2>
                <button class="modal-close" onclick="JobCenter.closeModal()">&times;</button>
            </div>
            <div class="modal-body chart-viewer">
                <div class="chart-placeholder">
                    <p>Loading control chart...</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    },

    /**
     * Load drawing content
     */
    async loadDrawing() {
        const viewer = document.querySelector('.drawing-viewer .drawing-placeholder');
        if (!viewer) return;

        try {
            const jobId = this.getCurrentJobId();
            // In a real implementation, this would load the actual drawing
            viewer.innerHTML = `
                <div class="drawing-content">
                    <p>Drawing viewer would be implemented here</p>
                    <p>Job ID: ${jobId}</p>
                </div>
            `;
        } catch (error) {
            viewer.innerHTML = '<p>Failed to load drawing</p>';
        }
    },

    /**
     * Load control chart content
     */
    async loadControlChart() {
        const viewer = document.querySelector('.chart-viewer .chart-placeholder');
        if (!viewer) return;

        try {
            const jobId = this.getCurrentJobId();
            // In a real implementation, this would load the actual chart
            viewer.innerHTML = `
                <div class="chart-content">
                    <p>Control chart would be implemented here</p>
                    <p>Job ID: ${jobId}</p>
                </div>
            `;
        } catch (error) {
            viewer.innerHTML = '<p>Failed to load control chart</p>';
        }
    },

    /**
     * Lock screen functionality
     */
    lockScreen() {
        console.log('JobCenter: Locking screen');
        
        // Create lock screen overlay
        const lockOverlay = document.createElement('div');
        lockOverlay.id = 'lockOverlay';
        lockOverlay.className = 'lock-overlay';
        lockOverlay.innerHTML = `
            <div class="lock-screen">
                <div class="lock-icon">🔒</div>
                <h2>Screen Locked</h2>
                <p>स्क्रीन लॉक</p>
                <form id="unlockForm">
                    <input type="password" placeholder="Enter PIN / पिन दर्ज करें" id="unlockPin" required>
                    <button type="submit">Unlock / अनलॉक करें</button>
                </form>
            </div>
        `;
        
        document.body.appendChild(lockOverlay);
        
        // Setup unlock handler
        const unlockForm = document.getElementById('unlockForm');
        unlockForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const pin = document.getElementById('unlockPin').value;
            
            // In a real implementation, verify PIN against operator
            if (pin === '1234' || pin.length >= 4) { // Placeholder logic
                document.body.removeChild(lockOverlay);
                this.showNotification('Screen unlocked', 'success');
            } else {
                this.showNotification('Invalid PIN', 'error');
                document.getElementById('unlockPin').value = '';
            }
        });
        
        // Focus PIN input
        setTimeout(() => {
            document.getElementById('unlockPin').focus();
        }, 100);
    },

    /**
     * Handle quick actions via keyboard shortcuts
     */
    handleQuickAction(keyNumber) {
        const quickActions = {
            1: 'setup',
            2: 'fpqc', 
            3: 'pause',
            4: 'complete',
            5: 'breakdown',
            6: 'contact',
            7: 'qccheck',
            8: 'test',
            9: 'alert'
        };

        const action = quickActions[keyNumber];
        if (action) {
            console.log(`JobCenter: Quick action ${keyNumber} -> ${action}`);
            this.showModal(action);
        }
    },

    /**
     * Set form loading state
     */
    setFormLoading(form, isLoading) {
        if (isLoading) {
            form.classList.add('loading');
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
            }
        } else {
            form.classList.remove('loading');
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                // Restore original text (would need to store it)
            }
        }
    },

    /**
     * Show notification to user
     */
    showNotification(message, type = 'info', duration = 3000) {
        console.log(`JobCenter: Notification [${type}]: ${message}`);
        
        // Remove existing notifications of same type
        document.querySelectorAll(`.notification-${type}`).forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Apply styles
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '600',
            zIndex: '2000',
            transform: 'translateX(400px)',
            transition: 'transform 0.3s ease',
            maxWidth: '300px',
            wordWrap: 'break-word'
        });
        
        // Set background color based on type
        const colors = {
            success: '#28a745',
            error: '#dc3545', 
            warning: '#ffc107',
            info: '#007bff'
        };
        notification.style.backgroundColor = colors[type] || colors.info;
        
        document.body.appendChild(notification);
        
        // Animate in
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });
        
        // Auto remove
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    },

    /**
     * Utility functions
     */
    getCurrentJobId() {
        // Extract job ID from URL or page context
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || this.state.currentJobId;
    },

    formDataToObject(formData) {
        const obj = {};
        for (let [key, value] of formData.entries()) {
            obj[key] = value;
        }
        return obj;
    },

    objectToFormData(obj) {
        const formData = new FormData();
        for (let [key, value] of Object.entries(obj)) {
            formData.append(key, value);
        }
        return formData;
    },

    /**
     * Error handling and logging
     */
    handleError(error, context = '') {
        console.error(`JobCenter Error ${context}:`, error);
        this.showNotification(`Error: ${error.message}`, 'error');
    },

    /**
     * Cleanup on page unload
     */
    cleanup() {
        if (this.state.refreshTimer) {
            clearInterval(this.state.refreshTimer);
        }
        if (this.state.qcHoldCheck) {
            clearInterval(this.state.qcHoldCheck);
        }
    }
};

// Initialize application when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => JobCenter.init());
} else {
    JobCenter.init();
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => JobCenter.cleanup());

// Make JobCenter globally available for inline event handlers
window.JobCenter = JobCenter;

// Service Worker registration for PWA functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/jc/sw.js');
            console.log('JobCenter: Service Worker registered', registration);
        } catch (error) {
            console.log('JobCenter: Service Worker registration failed', error);
        }
    });
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = JobCenter;
}