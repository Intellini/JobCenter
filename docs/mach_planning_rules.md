# mach_planning Table Rules

## Table Purpose
Temporary staging table for actively planned manufacturing jobs. This table is NOT for historical records.

## Fundamental Principle
**mach_planning = Current/Future Active Plans ONLY**

## Strict Rules

### 1. NO Historical Data
- Only current date or future dates allowed
- Past date records must be removed or updated
- No "archived" or "historical" planning records

### 2. NO Paused Jobs (Status = 6)
- Paused jobs must be immediately removed
- They remain in operations table for re-planning
- Can be added back when re-planned for future date

### 3. NO Completed Jobs (Status = 10)
- Completed jobs auto-remove from mach_planning
- Completion triggers immediate deletion
- Historical record stays in operations table only

### 4. NO Orphans
- Every job must be actively managed
- Cleanup runs on every date change
- Supervisor must handle all previous jobs

## Data Lifecycle

```
Step 1: Job Creation
operations table (permanent home)
        |
        v
Step 2: Planning
Supervisor selects job → INSERT into mach_planning
        |
        v
Step 3: Execution
Shop floor works on job
        |
        v
Step 4: Completion/Pause/Rejection
DELETE from mach_planning → Status updated in operations
        |
        v
Step 5: Available for Re-planning
Job can be selected again for any future date
```

## Table Structure

| Column | Purpose | Rules |
|--------|---------|-------|
| mp_op_id | Job ID reference | Must exist in operations |
| mp_op_mach | Machine assignment | Must be valid machine |
| mp_op_proddate | Production date | >= CURRENT_DATE |
| mp_op_shift | Shift (1=A, 2=B, 3=C) | Valid shift number |
| mp_op_seq | Sequence in plan | Unique per machine/date/shift |
| mp_op_start | Planned start time | Valid datetime |
| mp_op_end | Planned end time | Valid datetime |
| mp_op_status | Current status | NOT IN (6, 10) |

## Cleanup Triggers

### Automatic Cleanup:
1. **On Status Change**:
   ```sql
   -- Trigger on status update
   IF NEW.mp_op_status IN (6, 10) THEN
       DELETE FROM mach_planning WHERE mp_op_id = NEW.mp_op_id;
   END IF;
   ```

2. **On Date Change**:
   ```sql
   -- Run at start of each day
   DELETE FROM mach_planning 
   WHERE mp_op_proddate < CURRENT_DATE;
   ```

3. **On Supervisor Login**:
   ```php
   // Check and clean previous working date
   $previous_date = getPreviousWorkingDate($current_date);
   $orphan_jobs = getIncompleteJobs($previous_date);
   if ($orphan_jobs) {
       forceCarryoverDecision($orphan_jobs);
   }
   ```

### Manual Cleanup:
- Admin function to force cleanup
- Removes all orphans and invalid records
- Resets planning state

## Re-planning Scenarios

### Scenario 1: Job Paused Mid-Production
1. Status changes to 6 (paused)
2. Automatically removed from mach_planning
3. Shows in available jobs for future planning
4. Can be re-planned for any date

### Scenario 2: Job Not Started
1. Date passes without starting job
2. Supervisor prompted on next login
3. If rejected: status → 6, removed from mach_planning
4. If carried: date updated to current date

### Scenario 3: Multi-Day Holiday
1. Jobs planned for Friday
2. Factory closed Sat-Mon (holiday)
3. Tuesday login checks Friday's jobs
4. Must carry over or reject each job

## Query Examples

### Check for Orphans:
```sql
SELECT * FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE
OR mp_op_status IN (6, 10);
```

### Clean Orphans:
```sql
-- Remove past dates
DELETE FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE;

-- Remove paused
DELETE FROM mach_planning 
WHERE mp_op_status = 6;

-- Remove completed
DELETE FROM mach_planning 
WHERE mp_op_status = 10;
```

### Get Previous Working Date Jobs:
```sql
SELECT mp.*, o.op_lot, o.op_status 
FROM mach_planning mp
JOIN operations o ON mp.mp_op_id = o.op_id
WHERE mp.mp_op_mach = ?
AND mp.mp_op_proddate = ?
AND o.op_status < 10;
```

## Best Practices

1. **Always use transactions** when modifying mach_planning
2. **Check constraints** before insert
3. **Log all changes** for audit
4. **Validate dates** against holiday calendar
5. **Clean immediately** when status changes

## Common Mistakes to Avoid

❌ Leaving completed jobs in mach_planning
❌ Keeping paused jobs "for reference"
❌ Allowing past date records to persist
❌ Skipping carryover decisions
❌ Using mach_planning for historical reporting

✅ Clean table daily
✅ Remove immediately on pause/complete
✅ Force carryover decisions
✅ Use operations table for history
✅ Keep mach_planning lean and current

## Monitoring

Regular checks to ensure compliance:
```sql
-- Count of invalid records (should be 0)
SELECT COUNT(*) as invalid_records
FROM mach_planning
WHERE mp_op_proddate < CURRENT_DATE
   OR mp_op_status IN (6, 10);
```

This ensures mach_planning remains a clean, efficient planning tool rather than a cluttered historical record.