# Data Persistence Architecture

## Overview
Job Center uses a dual-persistence approach combining MySQL database storage with browser localStorage for reliability and offline capability.

## Database Schema

### mach_planning Table
Primary storage for supervisor-planned job sequences with calculated timing.

```sql
CREATE TABLE mach_planning (
    mp_id INT PRIMARY KEY AUTO_INCREMENT,
    mp_machine INT,              -- Machine ID from machine table
    mp_work_date DATE,           -- Production date
    mp_shift CHAR(1),           -- Shift (A/B/C)
    mp_op_id INT,               -- Job ID from operations table
    mp_sequence INT,            -- Sequence order (1,2,3...)
    mp_planned_start DATETIME,  -- Calculated start time
    mp_planned_end DATETIME,    -- Calculated end time
    mp_setup_time INT,          -- Setup minutes
    mp_prod_time INT,           -- Production minutes
    mp_changeover INT,          -- Changeover minutes to next job
    mp_planned_by VARCHAR(50),  -- Supervisor/operator name
    mp_planned_at TIMESTAMP,    -- When plan was created
    mp_status INT DEFAULT 0,    -- 0=planned, 1=active, 2=completed
    UNIQUE KEY (mp_machine, mp_work_date, mp_shift, mp_op_id)
);
```

### operations Table Fields Used
- `op_seq`: Sequence number for ordering
- `op_pln_stdt`: Planned start datetime
- `op_pln_endt`: Planned end datetime
- `op_stp_time`: Setup time in minutes
- `op_tot_pause`: Production time in minutes

## localStorage Structure

### Key Format
`planning_sequence_${MACHINE_ID}_${WORK_DATE}_${SHIFT}`

### Value Structure
```javascript
{
    "timestamp": "2024-01-15T10:30:00",
    "jobs": [
        {
            "op_id": 123,
            "sequence": 1,
            "start_time": "2024-01-15 06:00:00",
            "end_time": "2024-01-15 08:30:00",
            "setup_time": 30,
            "prod_time": 120,
            "changeover": 10,
            "op_lot": "LOT001",
            "item_code": "ITEM123",
            "qty": 1000
        }
    ]
}
```

## Persistence Flow

### Save Priority
1. **Database First**: Always attempt to save to mach_planning
2. **localStorage Backup**: Save to localStorage before database write
3. **Confirmation**: Only clear localStorage after successful database write

### Load Priority
1. **Database Primary**: Check mach_planning for existing plan
2. **localStorage Fallback**: Use if database is empty or unavailable
3. **Merge Strategy**: Database takes precedence, localStorage for drafts

## Offline Capability

### Offline Mode Detection
```javascript
if (!navigator.onLine || databaseError) {
    // Work in offline mode with localStorage
    saveToLocalStorage(planData);
    showOfflineIndicator();
}
```

### Sync on Reconnect
```javascript
window.addEventListener('online', () => {
    syncLocalStorageToDatabase();
});
```

## Data Integrity

### Validation Rules
1. No overlapping job times
2. Jobs must fit within shift boundaries
3. Sequence numbers must be unique and sequential
4. Start time must be before end time

### Conflict Resolution
- **Same User**: Newer timestamp wins
- **Different Users**: Supervisor role takes precedence
- **Database vs localStorage**: Database is authoritative

## Cleanup Policy

### localStorage
- Clear after successful database save
- Clear on logout
- Expire after 24 hours

### Database
- Keep planning history for 30 days
- Archive completed shifts after 7 days
- Purge archived data after 90 days