# Job Center Testing Plan

## Test Environment Setup
- **URL Base**: `http://localhost/jc/`
- **Test Machines**: HYD85, PNU83, PNU65
- **Test Date**: 18/08/2025
- **Browser**: Chrome/Firefox (check both)

## Phase 1: Basic System Check

### Test 1.1 - Login Page
**URL**: `http://localhost/jc/`

**Checklist**:
- [ ] Login form displays correctly
- [ ] Date picker shows DD/MM/YYYY format
- [ ] Machine dropdown populated with codes
- [ ] Shift selection (A/B/C) works
- [ ] Operator dropdown populated
- [ ] Login button functional

### Test 1.2 - Session Management
**Steps**:
1. Login successfully
2. Close browser completely (all tabs)
3. Reopen browser and navigate to `http://localhost/jc/`

**Expected**: Should show login page (no auto-login)

## Phase 2: Planning Interface

### Test 2.1 - Supervisor Planning View
**URL**: `http://localhost/jc/planning.php?m=HYD85`

**Checklist**:
- [ ] Two panels display (Available Jobs | Sequenced Jobs)
- [ ] Filter bar shows "Quick Find" with two input fields
- [ ] Item name filter works
- [ ] Lot number filter works
- [ ] Jobs can be dragged from left to right
- [ ] Changeover blocks appear between jobs
- [ ] Save button saves to mach_planning
- [ ] Clear sequence button works

### Test 2.2 - Carryover Workflow
**Steps**:
1. Plan jobs for today
2. Change date to tomorrow
3. Reload planning page

**Expected**:
- [ ] Blocking modal appears for incomplete jobs
- [ ] Shows categorized jobs (started/not started)
- [ ] Can select jobs to carry over
- [ ] Cannot close modal without decision
- [ ] Selected jobs update to new date
- [ ] Rejected jobs removed from planning

## Phase 3: Timeline View

### Test 3.1 - Operator Timeline Display
**URL**: `http://localhost/jc/?m=HYD85`

**Checklist**:
- [ ] Timeline loads without duplicates
- [ ] Time format shows as HH:MM (not raw datetime)
- [ ] Current job highlighted in blue
- [ ] Next job has red dashed border
- [ ] Jobs positioned correctly on timeline
- [ ] No overlapping job cards
- [ ] Shift indicators visible
- [ ] Status badges show correct colors

### Test 3.2 - No Jobs Scenario
**URL**: `http://localhost/jc/?m=PNU65` (or machine with no jobs)

**Expected**:
- [ ] "No Jobs Scheduled" splash screen appears
- [ ] Bilingual message (Hindi/English)
- [ ] Contact Supervisor button visible
- [ ] Logout button available
- [ ] No JavaScript errors in console

## Phase 4: Helper Functions

### Test 4.1 - Date Calculation
**Test Script**: Create `/tmp/test_helpers.php`
```php
<?php
require_once '/var/www/html/jc/helpers/planning_helper.php';

// Test 1: Previous working date (skip Sunday)
echo "Test 1 - Previous working date from Monday 2025-08-18: ";
echo getPreviousWorkingDate('2025-08-18') . "\n"; // Should be Friday 2025-08-15

// Test 2: Previous working date (skip holiday if exists)
echo "Test 2 - Check holiday skipping\n";

// Test 3: Buffer calculation
$buffer = calculateBufferStatus('HYD85', '2025-08-18', 1);
echo "Test 3 - Buffer status:\n";
print_r($buffer);
```

Run: `php /tmp/test_helpers.php`

### Test 4.2 - Visual Components CSS
**Browser Console Tests**:
```javascript
// Check CSS variables loaded
console.log('Setup color:', getComputedStyle(document.documentElement).getPropertyValue('--setup-delay-color'));
console.log('Breakdown color:', getComputedStyle(document.documentElement).getPropertyValue('--breakdown-color'));

// Check if time loss card styles exist
console.log('Time loss CSS:', document.styleSheets[0].cssRules);
```

## Phase 5: Database Validation

### Test 5.1 - Data Integrity Checks
```sql
-- Check for orphaned records in mach_planning
SELECT COUNT(*) as orphan_count 
FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE 
   OR mp_op_status IN (6, 10);

-- Check jobs distribution
SELECT 
    mp_op_mach as machine,
    mp_op_proddate as date,
    COUNT(*) as job_count
FROM mach_planning
GROUP BY mp_op_mach, mp_op_proddate;

-- Check for incomplete jobs
SELECT 
    o.op_lot,
    o.op_status,
    mp.mp_op_proddate
FROM operations o
LEFT JOIN mach_planning mp ON o.op_id = mp.mp_op_id
WHERE o.op_status < 10
  AND o.op_mach = 13  -- HYD85
LIMIT 10;
```

### Test 5.2 - Event Tables Check
```sql
-- Recent downtime events
SELECT dt_type, dt_rsn, dt_stdt, dt_endt,
       TIMESTAMPDIFF(MINUTE, dt_stdt, dt_endt) as duration_min
FROM downtime 
WHERE dt_endt IS NOT NULL
ORDER BY dt_stdt DESC 
LIMIT 5;

-- Recent notifications
SELECT nm_action, nm_text, nm_createdt 
FROM notifications 
ORDER BY nm_createdt DESC 
LIMIT 10;
```

## Phase 6: Performance Tests

### Test 6.1 - Load Times
| Page | Target | Actual | Pass/Fail |
|------|--------|--------|-----------|
| Login | < 1s | | |
| Planning | < 2s | | |
| Timeline | < 2s | | |
| Auto-refresh | < 500ms | | |

### Test 6.2 - Touch/Mobile
- [ ] Test on tablet (iPad/Android)
- [ ] Touch targets >= 44px
- [ ] Drag-drop works on touch
- [ ] No horizontal scroll
- [ ] Buttons respond to touch

## Quick Test Commands

### URLs for Testing
```bash
# Login page
http://localhost/jc/

# Planning interface (supervisor)
http://localhost/jc/planning.php?m=HYD85
http://localhost/jc/planning.php?m=PNU83

# Timeline view (operator)
http://localhost/jc/?m=HYD85
http://localhost/jc/?m=PNU83
http://localhost/jc/?m=PNU65
```

### Browser Console Commands
```javascript
// Clear all local storage
localStorage.clear();

// Check planning sequence
console.log(localStorage.getItem('planning_sequence_13_2025-08-18_1'));

// Force reload without cache
location.reload(true);

// Check session data
console.log(document.cookie);
```

### Database Quick Checks
```sql
-- Clean orphans
DELETE FROM mach_planning WHERE mp_op_proddate < CURRENT_DATE;

-- Reset test job
UPDATE operations SET op_status = 2 WHERE op_id = 627;

-- Check machine codes
SELECT m_id, m_code, mm_name FROM machine WHERE m_active = 1;
```

## Test Results Summary

| Component | Status | Issues Found | Fixed |
|-----------|--------|--------------|-------|
| Login | | | |
| Session | | | |
| Planning UI | | | |
| Filters | | | |
| Carryover | | | |
| Timeline | | | |
| Date Format | | | |
| Helper Functions | | | |
| CSS/Visual | | | |
| Database | | | |

## Known Issues to Track

1. **MCP MySQL Connection** - May need restart
2. **Carryover Modal** - Not yet implemented
3. **Operator Interface** - Not yet created
4. **Button Handlers** - Not yet implemented
5. **API Endpoints** - Not yet created

## Next Testing Phase

After fixing above issues:
1. Test operator button clicks
2. Test real-time event capture
3. Test burn rate calculation
4. Test time loss card generation
5. Test buffer visualization

## Notes Section

_Record any unexpected behavior or suggestions here:_

---

Last Updated: 2025-08-17