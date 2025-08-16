# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Philosophy
This is a **simplified rewrite** of the Job Center interface. Key principles:
- Zero training required - if it needs explanation, redesign it
- Single screen - no navigation, no modals, no popups
- Touch first - designed for tablets and gloved hands
- Offline capable - Progressive Web App approach
- No frameworks - vanilla JS/CSS for simplicity

## Common Development Commands

### Running the Application
- No build step required - just serve PHP files
- Access via: `http://[server]/jc/?p=[planning_id]`
- Test offline mode by disabling network

### Database Operations
- Connection uses singleton pattern in `/config/database.php`
- Credentials from `/dbcon/conn.php` (j_srv, j_usr, j_pwd, j_db)
- Primary database: `ptpl`
- Use prepared statements for all queries

### Testing
- Always test on actual tablets
- Test with network throttling (3G)
- Test offline scenarios
- Verify touch targets are 44px minimum

## High-Level Architecture

### Directory Structure
```
/jc/
├── index.php          # Main entry, routes to appropriate view
├── config/           
│   ├── app.php       # App settings
│   └── database.php  # DB connection singleton
├── api/              
│   ├── index.php     # API router
│   └── *.php         # Individual endpoints
├── assets/           
│   ├── css/app.css   # All styles in one file
│   ├── js/app.js     # Main application logic
│   └── images/       # Icons and assets
└── views/            
    └── machine.php   # Main operator interface
```

### Request Flow
1. Tablet loads `/jc/?p=123` (planning ID)
2. `index.php` validates planning ID
3. Renders `views/machine.php` with job data
4. JavaScript handles all interactions
5. API calls update database
6. Offline actions queued in LocalStorage

### API Design
All endpoints return JSON:
```json
{
  "success": true,
  "data": {},
  "message": "Action completed"
}
```

Error responses:
```json
{
  "success": false,
  "error": "Invalid planning ID",
  "code": "INVALID_ID"
}
```

### Frontend Patterns
- **No jQuery** - Use modern vanilla JS
- **No Build Tools** - Direct ES6 modules
- **CSS Variables** - For theming
- **CSS Grid/Flexbox** - For layout
- **LocalStorage** - For offline queue
- **Service Worker** - For PWA caching

## Key Implementation Details

### Status Management
Jobs have three states:
1. **Setup** - Machine being prepared
2. **Production** - Making parts
3. **Complete** - Job finished

### Action Logging
Every operator action creates a record:
```sql
INSERT INTO job_actions (ja_planning_id, ja_action, ja_operator, ja_timestamp)
VALUES (?, ?, ?, NOW())
```

### Offline Queue
Actions stored in LocalStorage:
```javascript
const action = {
  planning_id: 123,
  action: 'pause',
  timestamp: Date.now(),
  synced: false
};
localStorage.setItem('offline_queue', JSON.stringify(queue));
```

### Touch Handling
- Buttons minimum 44x44px
- 8px spacing between targets
- Visual feedback on touch
- No hover states (touch only)

## Development Guidelines

### CSS Organization
```css
/* Layout */
.container { }

/* Components */
.btn { }
.btn--primary { }

/* States */
.is-active { }
.is-loading { }

/* Utilities */
.u-text-center { }
```

### JavaScript Patterns
```javascript
// Module pattern
const JobCenter = {
  init() {},
  handleAction(action) {},
  syncOffline() {}
};

// Always use const/let, never var
// Use async/await over callbacks
// Handle errors gracefully
```

### PHP Standards
- Use type hints where possible
- Return early to reduce nesting
- Always validate input
- Use prepared statements

## Common Tasks

### Adding a New Action Button
1. Add button HTML in `views/machine.php`
2. Add click handler in `assets/js/app.js`
3. Create API endpoint in `api/`
4. Add action type to database enum

### Testing Offline Mode
1. Load the page normally
2. Open DevTools > Network > Offline
3. Perform actions (should queue)
4. Go back online (should sync)

### Debugging API Calls
Check browser console for:
- Request/response data
- Network errors
- Offline queue status

## Performance Targets
- Initial load: < 2 seconds
- API responses: < 500ms
- Touch response: < 100ms
- Offline capable for 8+ hours

## Security Notes
- Validate planning IDs
- Sanitize all inputs
- No sensitive data in LocalStorage
- Use HTTPS in production
- Log all actions for audit trail

## Testing Session - August 8, 2025

### Current Test Plan
**Objective**: Validate Job Center timeline view and operator interface functionality

**Test Environment**:
- Server: Local development
- Database: ptpl (MySQL)
- Test Date: August 8, 2025
- Browsers: Chrome/Edge on Windows

### Test URLs
1. **Primary Test Machine - HYD85**
   - URL: `/jc/index.php?m=HYD85`
   - Machine ID: 13
   - Status: 4 jobs with overlapping times

2. **Secondary Test Machine - PNU83**
   - URL: `/jc/index.php?m=PNU83`
   - Machine ID: 9
   - Status: 4 jobs with mixed statuses (Assigned, Paused)

3. **Alternative Machine - PNU65**
   - URL: `/jc/index.php?m=PNU65`
   - Machine ID: 3
   - Status: 4 jobs in Assigned status

### Issues Tracker

#### ✅ RESOLVED Issues
1. **Issue #1: Machine Parameter Format** [RESOLVED]
   - Problem: URL parameter `?m=9` didn't work (expected machine code, not ID)
   - Solution: Use machine CODE (e.g., `?m=HYD85`) instead of ID
   - Files Modified: None (documentation update only)

2. **Issue #2: Overlapping Job Cards** [RESOLVED]
   - Problem: Jobs with overlapping times rendered on same row causing visual overlap
   - Root Cause: No vertical positioning logic for overlapping time slots
   - Solution: Added row detection algorithm to assign different rows to overlapping jobs
   - Files Modified: `/views/timeline.php` (lines 56-89)

3. **Issue #3: Visual Overlap Despite Row Separation** [RESOLVED]
   - Problem: Job cards still overlapping even on different rows
   - Root Cause: CSS margin (0.5rem) not accounted for in row height calculation
   - Solution: Removed vertical margins, increased row spacing from 140px to 145px
   - Files Modified: `/assets/css/app.css` (line 261), `/views/timeline.php` (lines 91, 133)

4. **Issue #4: Job Identification Display** [RESOLVED]
   - Problem: Showing `op_lot` instead of more useful PO reference
   - Solution: Display `ob_porefno` from orders_head table, fallback to op_lot if empty
   - Files Modified: `/views/timeline.php` (line 137)

5. **Issue #5: Next Job Logic Bug** [RESOLVED]
   - Problem: `is_next` logic always returned false
   - Solution: Fixed array index comparison logic
   - Files Modified: `/views/timeline.php` (lines 93-109)

#### ⏳ PENDING Issues
- None currently identified

### Key Design Decisions

1. **Separation of Concerns (August 16, 2025)**
   - **Timeline (index.php)**: Simple read-only view, queries mach_planning for date/machine only
   - **Planning (planning.php)**: Handles all complexity including carryover from previous dates
   - **Rationale**: Clean architecture, operators see simple view, supervisors handle complexity
   - **Implementation**: All business logic in planning, timeline just displays saved data

2. **Carryover Workflow**
   - **Detection**: Planning page checks for incomplete jobs from previous date
   - **Categories**: Started (3-9) vs Not Started (0-2) jobs
   - **Modal Interface**: Supervisor selects which jobs to carry over
   - **Processing**: Selected jobs update to current date, unselected release to pool

3. **Overlap Handling Strategy**
   - Decision: Display overlapping jobs on separate rows rather than side-by-side
   - Rationale: Clearer visual separation, accommodates planning errors gracefully
   - Implementation: Row-based layout with 145px vertical spacing

4. **Job Identification**
   - Decision: Show PO Reference (ob_porefno) instead of lot number
   - Rationale: More meaningful for shop floor operators
   - Implementation: LEFT JOIN with orders_head table, fallback to lot if no PO ref

5. **Current/Next Job Highlighting**
   - Current Job: Blue background (#eff6ff)
   - Next Job: Red dashed border
   - Logic: First incomplete job is "current", immediate next is "next"

6. **Row Height Calculation**
   - Job block height: 120px (CSS variable)
   - Row spacing: 145px (includes 25px gap)
   - Container min-height: Dynamic based on row count

### Database Findings

**Test Data Available**:
- 63 active machines with valid 5-char codes
- 3,259 incomplete jobs (status < 10)
- 1,328 active WIP items
- 79 active operators
- 660 order records

**Data Quality Issues Found**:
- Multiple jobs with overlapping time slots (planning errors)
- Empty mach_planning table (not critical - app works without it)
- Minimal jwork audit trail (17 records)

### Updated Testing URLs (August 13, 2025)
1. **Primary Test Machine - HYD85**
   - Operator View: `/jc/index.php?m=HYD85`
   - Supervisor Planning: `/jc/planning.php?m=HYD85`
   - Machine ID: 13
   - Status: 4 jobs with overlapping times

2. **Secondary Test Machine - PNU83**
   - Operator View: `/jc/index.php?m=PNU83`
   - Supervisor Planning: `/jc/planning.php?m=PNU83`
   - Machine ID: 9
   - Status: 4 jobs with mixed statuses

3. **Alternative Machine - PNU65**
   - Operator View: `/jc/index.php?m=PNU65`
   - Supervisor Planning: `/jc/planning.php?m=PNU65`
   - Machine ID: 3
   - Status: 4 jobs in Assigned status

### Troubleshooting Section

#### Common Issues and Solutions

**Issue: Session Not Persisting**
- Check browser localStorage for operator session data
- Verify session timeout settings (default: 8 hours)
- Clear localStorage and re-login if corrupted

**Issue: Date Format Not DD/MM/YYYY**
- Check JavaScript date formatting functions
- Verify locale settings in browser
- Clear cache and reload page

**Issue: Timeline Layout Broken**
- Verify CSS Grid support in browser
- Check for CSS conflicts in browser DevTools
- Ensure viewport meta tag is present

**Issue: Touch Targets Too Small**
- Verify all buttons meet 44px minimum requirement
- Check CSS for proper touch target sizing
- Test with actual finger/stylus input

**Issue: Offline Mode Not Working**
- Check localStorage quota availability
- Verify Service Worker registration
- Check network connectivity detection

**Issue: Planning Cards Not Draggable**
- Verify drag-and-drop event listeners
- Check for JavaScript errors in console
- Ensure touch events are properly handled

**Issue: API Calls Failing**
- Check network connectivity
- Verify API endpoint URLs
- Check server PHP error logs
- Validate request payload format

#### Development Commands

**Clear Application Data**:
```javascript
// In browser console
localStorage.clear();
sessionStorage.clear();
location.reload();
```

**Debug Session State**:
```javascript
// Check current session
console.log(JSON.parse(localStorage.getItem('jc_session') || '{}'));
```

**Test Offline Mode**:
1. Load application normally
2. Open DevTools > Network > Throttling > Offline
3. Perform actions (should queue in localStorage)
4. Return online (should auto-sync)

**Monitor API Performance**:
```javascript
// Log API response times
console.time('api-call');
fetch('/jc/api/status').then(() => console.timeEnd('api-call'));
```

### Next Testing Steps
1. ✅ Test operator actions (Setup, FPQC, Start, Pause, Complete) - COMPLETED
2. ✅ Verify offline mode functionality - COMPLETED
3. ✅ Test on actual tablet devices - COMPLETED
4. ✅ Validate touch targets (44px minimum) - COMPLETED
5. ✅ Test API response times (< 500ms target) - COMPLETED
6. Test supervisor planning workflow end-to-end
7. Validate session persistence across browser restarts
8. Test contact supervisor functionality with logout
9. Verify responsive layout on different tablet orientations
10. Performance testing under load conditions