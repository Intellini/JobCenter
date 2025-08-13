# Architecture Decision Records (ADRs) - Job Center

## Overview
This document captures all significant architectural decisions made during the Job Center application development.

## ADR-001: No Framework Approach
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Target users are shop floor operators using tablets
- Zero training requirement
- Need for maximum reliability and minimal dependencies
- Progressive Web App capabilities required

### Decision
Use vanilla JavaScript, CSS, and PHP without any frontend frameworks.

### Consequences
- **Positive**: 
  - No build step required
  - Easier debugging
  - Faster initial load
  - No framework updates to manage
- **Negative**: 
  - More manual DOM manipulation
  - Need to implement common patterns ourselves

---

## ADR-002: ADODB Database Layer
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Existing codebase uses ADODB throughout
- Need consistency with other applications
- Production dashboard already established patterns

### Decision
Reuse ADODB with mysqli driver and create a Database singleton wrapper.

### Implementation
```php
// Singleton pattern for database access
$db = Database::getInstance();
$result = $db->getAll($sql, $params);
```

### Consequences
- Consistent with existing codebase
- Familiar to maintenance team
- Some deprecated warnings (non-critical)

---

## ADR-003: API Architecture
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Need RESTful API for tablet interface
- Multiple action endpoints required
- Standardized responses needed

### Decision
Single entry point router (`/api/index.php`) routing to individual action files.

### Implementation
```
/api/
  ├── index.php          # Main router
  ├── config/
  │   └── response.php   # Standardized responses
  └── actions/           # Individual endpoints
      ├── setup.php
      ├── fpqc.php
      └── ...
```

### Consequences
- Clean URL structure
- Easy to add new endpoints
- Centralized error handling

---

## ADR-004: Offline-First Architecture
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Shop floor may have intermittent connectivity
- Actions must not be lost
- Progressive Web App requirement

### Decision
Implement offline queue using localStorage with auto-sync.

### Implementation
```javascript
// Queue actions when offline
if (!navigator.onLine) {
    this.queueOfflineAction(action, data);
    return;
}

// Auto-sync when back online
window.addEventListener('online', () => {
    this.syncOfflineQueue();
});
```

### Consequences
- No data loss during disconnections
- Complexity in sync logic
- Need to handle conflicts

---

## ADR-005: Modal-Based Forms
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Limited screen space on tablets
- Need focused interaction
- Touch-friendly requirement

### Decision
All forms presented as modal overlays rather than separate pages.

### Consequences
- Better use of screen space
- Maintains context
- Easier to implement loading states

---

## ADR-006: Status-Based UI Updates
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Job status determines available actions
- QC Hold blocks most operations
- Real-time updates needed

### Decision
- Auto-refresh every 60 seconds
- QC Hold check every 30 seconds
- Disable buttons based on status

### Implementation
```javascript
// Auto-refresh for QC hold
setInterval(() => {
    this.checkQCHoldStatus();
}, 30000);
```

---

## ADR-007: Machine Identification
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Each tablet tied to one machine
- Need unique 5-character codes
- URL parameter override required

### Decision
Created `mm_code` column with format: `TYPNN` (e.g., HYD45)

### Implementation
```
?m=HYD45  # URL parameter
or
hostname  # Fallback
```

---

## ADR-008: Notification System
**Date**: 2025-07-31  
**Status**: Revised

### Context
- Need user feedback for actions
- Consistency with dashboards project
- Non-intrusive notifications

### Initial Decision
Custom toast notification system.

### Revised Decision
Use Bootstrap-style alerts to match dashboards project.

### Implementation
```javascript
// Show notification
this.showNotification('Action completed', 'success');

// Renders as Bootstrap alert
<div class="alert alert-success alert-dismissible">
    Action completed
</div>
```

---

## ADR-009: Session Management
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Operator context needed throughout
- Shift-based operations
- Simple authentication

### Decision
PHP session-based with operator selection, no complex auth.

### Implementation
```php
$_SESSION['operator_id']
$_SESSION['operator_name']
$_SESSION['shift']
$_SESSION['work_date']
$_SESSION['machine_code']
```

---

## ADR-010: Bilingual Support
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Hindi-speaking operators
- Need for clarity
- Reduce training

### Decision
Hard-coded Hindi labels alongside English, no i18n system.

### Implementation
```html
<label>Quantity / मात्रा</label>
<button>Start Work / कार्य शुरू करें</button>
```

---

## ADR-011: Touch-First Design
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Tablet primary interface
- Gloved hands usage
- Reduce mis-taps

### Decision
- Minimum 44px touch targets
- 8px spacing between buttons
- Visual feedback on touch

### Implementation
```css
.btn {
    min-height: 44px;
    margin: 8px;
}

@media (pointer: coarse) {
    .btn {
        min-height: 48px;
    }
}
```

---

## ADR-012: Job Sequencing
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Jobs must be completed in sequence
- Planning data may not exist
- Fallback required

### Decision
Use `mach_planning` when available, fallback to `operations` start time.

### Implementation
```php
// Try planning first
$planning_jobs = $db->getAll("SELECT ... FROM mach_planning ...");

// Apply sequence or use start time
if (!empty($planning_jobs)) {
    // Use planned sequence
} else {
    // Sort by op_start
}
```

---

## ADR-013: Error Handling Strategy
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Shop floor environment
- Non-technical users
- Need clear feedback

### Decision
- User-friendly error messages
- Technical details in logs only
- Always show next action

### Implementation
```php
if ($error) {
    Response::error('Unable to complete action. Please try again.', 'ACTION_FAILED');
    error_log('Technical details: ' . $e->getMessage());
}
```

---

## ADR-014: File Organization
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- No build process
- Direct file serving
- Easy maintenance

### Decision
Traditional PHP structure with clear separation.

### Structure
```
/jc/
├── index.php          # Entry point
├── api/              # API endpoints
├── assets/           # CSS, JS, images
├── config/           # Configuration
├── views/            # PHP views
└── docs/             # Documentation
```

---

## ADR-015: State Management
**Date**: 2025-07-31  
**Status**: Accepted

### Context
- Single page interactions
- Modal-based workflow
- Offline capability

### Decision
JavaScript object pattern with localStorage for persistence.

### Implementation
```javascript
const JobCenter = {
    currentJob: null,
    offlineQueue: [],
    
    init() {
        this.loadState();
        this.bindEvents();
    }
};
```

---

## Summary of Key Decisions

1. **Simplicity First**: No frameworks, direct implementation
2. **Offline Capable**: Queue and sync pattern
3. **Touch Optimized**: Large targets, visual feedback
4. **Status Driven**: UI reacts to job status
5. **Consistent Patterns**: Match existing dashboards where possible
6. **Zero Training**: Self-evident interface
7. **Reliability**: Minimal dependencies, proven technologies