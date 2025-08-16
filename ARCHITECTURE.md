# Job Center Planning Architecture

## Fundamental Rule
**mach_planning table contains ONLY actively planned future work**
- No historical records
- No paused jobs  
- No orphans
- Clean slate for re-planning

## Core Principles

### 1. Planning Workflow
The planning system follows a strict supervisor-controlled workflow where jobs move from the operations table (permanent record) to mach_planning table (temporary planning) for execution.

### 2. mach_planning Table Purpose
- **Temporary staging** for actively planned manufacturing jobs
- **Future-only** - contains only current date or future date jobs
- **Active plans only** - no paused, completed, or abandoned jobs
- **Clean daily** - automatic cleanup of past incomplete jobs

## Cleanup Logic

### When Jobs Are Removed from mach_planning:
1. Job completed (status 10)
2. Job paused (status 6)
3. Job rejected during carryover
4. Past date with incomplete status
5. Any manual removal by supervisor

### What Happens to Removed Jobs:
- Job remains in operations table with current status
- Can be re-planned on ANY future date
- Paused status (6) indicates "was planned but not executed"
- Available for selection in planning interface again

## Previous Working Date Logic

The system must account for holidays and non-working days when checking for previous jobs:

```php
function getPreviousWorkingDate($current_date, $db) {
    $date = new DateTime($current_date);
    $date->modify('-1 day');
    
    while (true) {
        $check_date = $date->format('Y-m-d');
        
        // Check if it's Sunday
        if ($date->format('w') == 0) {
            $date->modify('-1 day');
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
        
        // Safety check - don't go back more than 30 days
        if ($date < new DateTime($current_date . ' -30 days')) {
            return null;
        }
    }
}
```

## Carryover Workflow

### Supervisor Login Process:
1. System checks previous working date for incomplete jobs
2. If found, shows BLOCKING modal (cannot dismiss without decision)
3. Supervisor must decide for each job:
   - **Carry Over**: 
     - Update `mp_op_proddate` to current date in mach_planning
     - Job continues in current day's plan
   - **Reject**:
     - Set `op_status = 6` (paused) in operations table
     - DELETE from mach_planning table completely
     - Job becomes available for future planning

### No Orphans Policy:
- System enforces zero orphans in mach_planning
- Every incomplete past job must be explicitly handled
- No jobs can be "forgotten" in the planning table

## Planning Interface Rules

### Available Jobs Panel (Left Side):
- Shows ALL pending jobs (status < 10) from operations table
- Includes jobs with status 6 (paused) - they can be re-planned
- Excludes jobs already in current day's sequence
- Filters available:
  - Item name (from wip_items table)
  - Lot number search
- Jobs disappear when dragged to sequence (right panel)

### Sequenced Jobs Panel (Right Side):
- Shows jobs planned for current date/machine/shift
- Calculates timeline with changeover times
- Updates mach_planning when saved

## Data Flow

```
operations table          mach_planning table         execution
(permanent record) -----> (temporary planning) -----> (shop floor)
     ^                            |
     |                            v
     +----------------------- cleanup
                           (paused/complete/rejected)
```

## Status Codes and Meanings

| Status | Meaning | In mach_planning? |
|--------|---------|-------------------|
| 0 | Planned | Yes (if future) |
| 1 | New | Yes (if planned) |
| 2 | Assigned | Yes (if planned) |
| 3 | Setup | Yes (if planned) |
| 4 | FPQC | Yes (if planned) |
| 5 | In Process | Yes (if planned) |
| 6 | Paused | NO - Always removed |
| 7 | Breakdown | Yes (if planned) |
| 8 | On Hold | Yes (if planned) |
| 9 | LPQC | Yes (if planned) |
| 10 | Complete | NO - Always removed |

## Re-planning Rules

- **No restrictions** on re-planning previously planned jobs
- Paused jobs (status 6) are eligible for planning
- Each planning session starts with a clean slate
- Previous planning attempts don't block future planning

## Cleanup Triggers

1. **Supervisor login** with different date
2. **Shift change** 
3. **Job status change** to 6 (paused) or 10 (complete)
4. **Manual cleanup** command
5. **Carryover rejection** by supervisor

## Database Integrity

### Constraints:
- mach_planning.mp_op_proddate >= CURRENT_DATE (no past dates except current)
- mach_planning.mp_op_status NOT IN (6, 10) (no paused or complete)
- Every job in mach_planning must exist in operations table
- Machine assignment must be valid

### Cleanup Queries:
```sql
-- Daily cleanup of past incomplete jobs
DELETE FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE
AND mp_op_status < 10;

-- Remove paused jobs immediately
DELETE FROM mach_planning 
WHERE mp_op_status = 6;

-- Remove completed jobs immediately
DELETE FROM mach_planning 
WHERE mp_op_status = 10;
```

## Implementation Notes

1. **Always use transactions** when updating both operations and mach_planning
2. **Check for orphans** on every supervisor login
3. **Force decision** on previous jobs - no skip option
4. **Log all planning changes** for audit trail
5. **Validate dates** to ensure no past planning

This architecture ensures clean, manageable planning data with no historical orphans cluttering the system.