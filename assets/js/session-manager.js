/**
 * Session Manager
 * Handles browser close detection and session cleanup
 */

(function() {
    'use strict';
    
    // Track if the page is being unloaded due to navigation or browser close
    let isNavigating = false;
    
    // Mark navigation when clicking links or submitting forms
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' || e.target.closest('a')) {
            isNavigating = true;
        }
    });
    
    document.addEventListener('submit', function() {
        isNavigating = true;
    });
    
    // Handle page visibility change (tab switching, minimizing)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // Page is hidden, store timestamp
            localStorage.setItem('jc_last_active', Date.now());
        } else if (document.visibilityState === 'visible') {
            // Page is visible again, check if session should expire
            const lastActive = localStorage.getItem('jc_last_active');
            if (lastActive) {
                const inactiveTime = Date.now() - parseInt(lastActive);
                // If inactive for more than 30 minutes, force logout
                if (inactiveTime > 1800000) { // 30 minutes in milliseconds
                    window.location.href = 'index.php?logout=1';
                }
            }
        }
    });
    
    // Handle browser/tab close
    window.addEventListener('beforeunload', function(e) {
        // Only act if not navigating within the app
        if (!isNavigating) {
            // Mark session as potentially abandoned
            localStorage.setItem('jc_session_abandoned', Date.now());
            
            // Try to send a beacon to notify server (works even as page closes)
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/jc/api/actions/session.php', JSON.stringify({
                    action: 'browser_close',
                    timestamp: Date.now()
                }));
            }
        }
    });
    
    // Check on page load if previous session was abandoned
    window.addEventListener('load', function() {
        const abandoned = localStorage.getItem('jc_session_abandoned');
        if (abandoned) {
            const abandonedTime = Date.now() - parseInt(abandoned);
            // If session was abandoned more than 5 seconds ago, clear it
            if (abandonedTime > 5000) {
                localStorage.removeItem('jc_session_abandoned');
                localStorage.removeItem('jc_last_active');
                
                // Clear any planning data as well
                const keys = Object.keys(localStorage);
                keys.forEach(key => {
                    if (key.startsWith('planning_')) {
                        localStorage.removeItem(key);
                    }
                });
            }
        }
        
        // Reset navigation flag
        isNavigating = false;
    });
    
    // Periodic session activity ping (every 5 minutes)
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            // Send activity ping to server
            fetch('/jc/api/actions/session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'activity_ping',
                    timestamp: Date.now()
                })
            }).catch(function() {
                // Ignore errors for activity pings
            });
        }
    }, 300000); // 5 minutes
    
})();