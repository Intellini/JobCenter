# Planning Cleanup Workflow

## Purpose
Maintain data integrity by preventing orphan records in mach_planning table. All planned jobs must be actively managed, never abandoned.

## Core Principle
**Every job in mach_planning must be explicitly handled - no exceptions**

## Cleanup Flow Diagram

```
Supervisor Login
       |
       v
Check Previous Working Date
       |
       v
Found Incomplete Jobs?
       |
    Yes|                    No|
       v                       v
Show BLOCKING Modal      Continue to Planning
       |
       v
For Each Job:
  - Carry Over → Update Date
  - Reject → Pause + Delete
       |
       v
No Orphans Remain
```

## Cleanup Triggers

### 1. Supervisor Login (Primary Trigger)
When supervisor logs in with a new date:
```php
// On planning.php load
$current_date = $_SESSION['work_date'];
$previous_working_date = getPreviousWorkingDate($current_date, $db);

if ($previous_working_date) {
    $incomplete_jobs = checkIncompleteJobs($previous_working_date);
    if ($incomplete_jobs) {
        // FORCE carryover decision
        showBlockingCarryoverModal($incomplete_jobs);
    }
}
```

### 2. Status Change Trigger
When job status changes to paused or complete:
```php
// In status update handler
if ($new_status == 6 || $new_status == 10) {
    // Remove from mach_planning immediately
    $db->query("DELETE FROM mach_planning WHERE mp_op_id = ?", [$job_id]);
}
```

### 3. Shift Change Trigger
At shift boundaries, check for incomplete jobs:
```php
// At shift change
cleanupIncompleteShiftJobs($machine_id, $previous_shift);
```

### 4. Manual Admin Cleanup
Administrative function for forced cleanup:
```php
// Admin cleanup function
function forceCleanupOrphans($db) {
    // Remove all past incomplete
    $db->query("DELETE FROM mach_planning WHERE mp_op_proddate < CURRENT_DATE");
    
    // Remove all paused
    $db->query("DELETE FROM mach_planning WHERE mp_op_status = 6");
    
    // Remove all completed
    $db->query("DELETE FROM mach_planning WHERE mp_op_status = 10");
}
```

## Cleanup Process Details

### Step 1: Identify Orphan Jobs
```sql
SELECT mp.*, o.op_lot, o.op_status,
       CASE 
           WHEN o.op_status BETWEEN 3 AND 9 THEN 'started'
           WHEN o.op_status IN (0,1,2) THEN 'not_started'
           ELSE 'other'
       END as job_category
FROM mach_planning mp
JOIN operations o ON mp.mp_op_id = o.op_id
WHERE mp.mp_op_mach = ?
  AND mp.mp_op_proddate = ?
  AND o.op_status < 10
ORDER BY mp.mp_op_seq;
```

### Step 2: Present to Supervisor
```javascript
// Blocking modal - cannot be dismissed
function showCarryoverModal(jobs) {
    const modal = createBlockingModal();
    
    // Categorize jobs
    const started = jobs.filter(j => j.category === 'started');
    const notStarted = jobs.filter(j => j.category === 'not_started');
    
    // Show with clear categories
    modal.showStartedJobs(started);     // Likely to carry over
    modal.showNotStartedJobs(notStarted); // Likely to reject
    
    // No close button - must decide
    modal.onDecision = handleCarryoverDecision;
}
```

### Step 3: Process Decisions
```php
function handleCarryoverDecision($selected_jobs, $all_jobs) {
    $db->beginTransaction();
    
    try {
        // Get rejected jobs
        $rejected = array_diff($all_jobs, $selected_jobs);
        
        // Process carried over jobs
        foreach ($selected_jobs as $job_id) {
            // Update to current date
            $db->query("
                UPDATE mach_planning 
                SET mp_op_proddate = ? 
                WHERE mp_op_id = ?
            ", [$current_date, $job_id]);
        }
        
        // Process rejected jobs
        foreach ($rejected as $job_id) {
            // Set to paused in operations
            $db->query("
                UPDATE operations 
                SET op_status = 6 
                WHERE op_id = ?
            ", [$job_id]);
            
            // Remove from planning
            $db->query("
                DELETE FROM mach_planning 
                WHERE mp_op_id = ?
            ", [$job_id]);
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
```

## Previous Working Date Calculation

```php
function getPreviousWorkingDate($current_date, $db) {
    $date = new DateTime($current_date);
    $date->modify('-1 day');
    $attempts = 0;
    
    while ($attempts < 30) { // Safety limit
        $check_date = $date->format('Y-m-d');
        
        // Check if Sunday
        if ($date->format('w') == 0) {
            $date->modify('-1 day');
            $attempts++;
            continue;
        }
        
        // Check holiday table
        $is_holiday = $db->getValue(
            "SELECT COUNT(*) FROM holiday WHERE holiday_date = ?",
            [$check_date]
        );
        
        if (!$is_holiday) {
            return $check_date; // Found working date
        }
        
        $date->modify('-1 day');
        $attempts++;
    }
    
    return null; // No working date found in 30 days
}
```

## Database Impact

### Operations Table Updates:
```sql
-- Mark rejected jobs as paused
UPDATE operations 
SET op_status = 6,
    op_update_timestamp = NOW(),
    op_update_user = 'SUPERVISOR'
WHERE op_id IN (rejected_job_ids);
```

### mach_planning Table Updates:
```sql
-- Carry over selected jobs
UPDATE mach_planning 
SET mp_op_proddate = CURRENT_DATE
WHERE mp_op_id IN (selected_job_ids);

-- Delete rejected jobs
DELETE FROM mach_planning 
WHERE mp_op_id IN (rejected_job_ids);
```

## Special Scenarios

### Scenario 1: Multi-Day Holiday
```
Thursday: Jobs planned
Friday-Monday: Holidays
Tuesday: Login
- System checks Thursday (last working date)
- Shows all Thursday's incomplete jobs
- Supervisor must handle each
```

### Scenario 2: Shift Overlap
```
Shift A: Job started but not complete
Shift B: Supervisor logs in
- System shows incomplete job from Shift A
- Must decide: continue or pause
```

### Scenario 3: Emergency Shutdown
```
Unexpected closure for 3 days
On return:
- System shows ALL incomplete jobs from last working date
- Supervisor batch decisions allowed
- Bulk carry over or reject
```

## Error Handling

### Network Failure During Cleanup:
```php
try {
    performCleanup();
} catch (NetworkException $e) {
    // Store cleanup state locally
    localStorage.setItem('pending_cleanup', JSON.stringify($jobs));
    // Retry on next action
}
```

### Database Lock:
```php
// Use transaction with timeout
$db->query("SET innodb_lock_wait_timeout = 5");
$db->beginTransaction();
// ... cleanup operations
$db->commit();
```

## Monitoring and Alerts

### Daily Health Check:
```sql
-- Should return 0
SELECT COUNT(*) as orphan_count
FROM mach_planning
WHERE mp_op_proddate < CURRENT_DATE
   OR mp_op_status IN (6, 10);
```

### Alert Conditions:
1. Orphan count > 0
2. Jobs older than previous working date
3. Paused/completed jobs in planning
4. Cleanup failures

## Best Practices

1. **Never skip cleanup** - Make modal truly blocking
2. **Log all decisions** - Audit trail for carried/rejected
3. **Use transactions** - All or nothing updates
4. **Validate dates** - Check against holiday calendar
5. **Test edge cases** - Multi-day closures, shift boundaries

## Implementation Checklist

- [ ] getPreviousWorkingDate function
- [ ] Blocking carryover modal
- [ ] Cleanup on login
- [ ] Cleanup on status change
- [ ] Transaction handling
- [ ] Error recovery
- [ ] Audit logging
- [ ] Monitoring queries
- [ ] Admin cleanup tool

This workflow ensures zero orphans and complete accountability for all planned jobs.