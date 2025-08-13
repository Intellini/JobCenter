/**
 * JobCenter Application JavaScript
 * Core functionality for UI interactions and API communication
 */

// Application Configuration
const JobCenter = {
    config: {
        apiBase: '/jc/api/',
        refreshInterval: 60000, // 1 minute
        timeoutWarning: 25 * 60 * 1000, // 25 minutes
        sessionTimeout: 30 * 60 * 1000, // 30 minutes
    },
    
    // Initialize application
    init() {
        this.setupEventListeners();
        this.startClock();
        this.checkSession();
        this.setupOfflineDetection();
        console.log('JobCenter application initialized');
    },
    
    // Set up global event listeners
    setupEventListeners() {
        // Handle escape key for modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
        
        // Handle form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('.modal-form')) {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            }
        });
        
        // Handle modal overlay clicks
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-overlay')) {
                this.closeModal();
            }
        });
        
        // Handle visibility change for auto-refresh
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkForUpdates();
            }
        });
    },
    
    // Start real-time clock
    startClock() {
        const updateClock = () => {
            const now = new Date();
            const timeString = now.toTimeString().split(' ')[0];
            
            const clocks = document.querySelectorAll('#clock, .header-clock');
            clocks.forEach(clock => {
                if (clock) clock.textContent = timeString;
            });
        };
        
        updateClock();
        setInterval(updateClock, 1000);
    },
    
    // Check session status
    checkSession() {
        // Warn before session timeout
        setTimeout(() => {
            this.showSessionWarning();
        }, this.config.timeoutWarning);
        
        // Auto logout on session timeout
        setTimeout(() => {
            this.handleSessionTimeout();
        }, this.config.sessionTimeout);
    },
    
    // Show session timeout warning
    showSessionWarning() {
        const confirmed = confirm(
            'Your session will expire in 5 minutes. Do you want to continue working?\n\n' +
            'आपका सेशन 5 मिनट में समाप्त हो जाएगा। क्या आप काम जारी रखना चाहते हैं?'
        );
        
        if (confirmed) {
            // Extend session by making a keep-alive request
            this.keepSessionAlive();
        }
    },
    
    // Handle session timeout
    handleSessionTimeout() {
        alert(
            'Your session has expired. You will be redirected to login.\n\n' +
            'आपका सेशन समाप्त हो गया है। आपको लॉगिन पेज पर भेजा जाएगा।'
        );
        window.location.href = 'index.php?timeout=1';
    },
    
    // Keep session alive
    keepSessionAlive() {
        fetch(this.config.apiBase + 'health')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Session extended');
                    // Reset session timers
                    this.checkSession();
                }
            })
            .catch(error => {
                console.error('Failed to extend session:', error);
            });
    },
    
    // Setup offline detection
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.showNotification('Connection restored', 'success');
            this.syncOfflineActions();
        });
        
        window.addEventListener('offline', () => {
            this.showNotification('You are offline. Actions will be queued.', 'warning');
        });
    },
    
    // Check for updates (if on job or timeline page)
    checkForUpdates() {
        if (window.location.pathname.includes('job.php')) {
            // Refresh job page if QC status might have changed
            const urlParams = new URLSearchParams(window.location.search);
            const jobId = urlParams.get('id');
            
            if (jobId) {
                this.checkJobStatus(jobId);
            }
        }
    },
    
    // Check job status for updates
    checkJobStatus(jobId) {
        fetch(`${this.config.apiBase}status/${jobId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Check if status changed significantly
                    const currentStatus = data.data.status;
                    const pageStatus = this.getCurrentPageStatus();
                    
                    if (currentStatus !== pageStatus) {
                        this.showStatusChangeNotification(currentStatus);
                    }
                }
            })
            .catch(error => {
                console.error('Failed to check job status:', error);
            });
    },
    
    // Get current page status (implement based on page structure)
    getCurrentPageStatus() {
        const statusBadge = document.querySelector('.status-badge');
        return statusBadge ? statusBadge.textContent.trim() : null;
    },
    
    // Show status change notification
    showStatusChangeNotification(newStatus) {
        const shouldRefresh = confirm(
            `Job status has changed to: ${newStatus}. Would you like to refresh the page?\n\n` +
            `जॉब स्टेटस बदल गया है: ${newStatus}। क्या आप पेज रीफ्रेश करना चाहते हैं?`
        );
        
        if (shouldRefresh) {
            window.location.reload();
        }
    },
    
    // Modal Management
    showModal(modalId) {
        const modal = document.getElementById(modalId + 'Modal');
        const overlay = document.getElementById('modalOverlay');
        
        if (modal && overlay) {
            overlay.classList.add('active');
            modal.classList.add('active');
            
            // Focus first input
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }
    },
    
    closeModal() {
        const overlay = document.getElementById('modalOverlay');
        const modals = document.querySelectorAll('.modal');
        
        if (overlay) overlay.classList.remove('active');
        modals.forEach(modal => modal.classList.remove('active'));
        
        // Restore body scroll
        document.body.style.overflow = '';
    },
    
    // Form Handling
    handleFormSubmit(form) {
        const formData = new FormData(form);
        const action = formData.get('action') || form.id.replace('Form', '');
        
        // Add job ID if available
        const jobId = this.getJobId();
        if (jobId) {
            formData.append('job_id', jobId);
        }
        
        // Show loading state
        form.classList.add('loading');
        
        // Submit to appropriate API endpoint
        this.submitAction(action, formData)
            .then(response => response.json())
            .then(data => {
                form.classList.remove('loading');
                
                if (data.success) {
                    this.closeModal();
                    this.showNotification(data.message, 'success');
                    
                    // Refresh page after successful action
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                form.classList.remove('loading');
                console.error('Form submission error:', error);
                
                if (!navigator.onLine) {
                    this.queueOfflineAction(action, formData);
                    this.showNotification('Action queued for when online', 'info');
                } else {
                    this.showNotification('Network error occurred', 'error');
                }
            });
    },
    
    // Submit action to API
    submitAction(action, formData) {
        return fetch(`${this.config.apiBase}actions/${action}`, {
            method: 'POST',
            body: formData
        });
    },
    
    // Get job ID from current page
    getJobId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    },
    
    // Offline Action Management
    queueOfflineAction(action, formData) {
        const actions = this.getOfflineActions();
        const actionData = {
            action: action,
            data: Object.fromEntries(formData),
            timestamp: Date.now(),
            jobId: this.getJobId()
        };
        
        actions.push(actionData);
        localStorage.setItem('offline_actions', JSON.stringify(actions));
    },
    
    getOfflineActions() {
        const stored = localStorage.getItem('offline_actions');
        return stored ? JSON.parse(stored) : [];
    },
    
    syncOfflineActions() {
        const actions = this.getOfflineActions();
        
        if (actions.length === 0) return;
        
        console.log(`Syncing ${actions.length} offline actions`);
        
        actions.forEach((actionData, index) => {
            const formData = new FormData();
            Object.entries(actionData.data).forEach(([key, value]) => {
                formData.append(key, value);
            });
            
            this.submitAction(actionData.action, formData)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove synced action
                        const remainingActions = this.getOfflineActions();
                        remainingActions.splice(index, 1);
                        localStorage.setItem('offline_actions', JSON.stringify(remainingActions));
                        
                        console.log(`Synced action: ${actionData.action}`);
                    }
                })
                .catch(error => {
                    console.error(`Failed to sync action: ${actionData.action}`, error);
                });
        });
    },
    
    // Notification System
    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.notification');
        existing.forEach(n => n.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
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
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        notification.style.backgroundColor = colors[type] || colors.info;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, type === 'error' ? 5000 : 3000);
    },
    
    // Utility Functions
    formatTime(date) {
        return date.toTimeString().split(' ')[0];
    },
    
    formatDate(date) {
        return date.toLocaleDateString('en-GB'); // DD/MM/YYYY format
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Global functions for backward compatibility
window.showModal = (modalId) => JobCenter.showModal(modalId);
window.closeModal = () => JobCenter.closeModal();
window.showNotification = (message, type) => JobCenter.showNotification(message, type);

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => JobCenter.init());
} else {
    JobCenter.init();
}

// Export for use in other scripts
window.JobCenter = JobCenter;