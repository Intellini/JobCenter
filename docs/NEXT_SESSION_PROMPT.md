# Next Session Prompt for Job Center

## Quick Start Prompt

Copy this to start the next session:

```
I'm continuing work on the Job Center PWA for manufacturing shop floor. 

Current status:
- Planning interface with filters: COMPLETED
- Timeline view: COMPLETED  
- Helper functions (getPreviousWorkingDate, burn rate, buffer): COMPLETED
- CSS for time loss cards and visualizations: COMPLETED
- System documentation: COMPLETED

Need to:
1. Fix MCP MySQL if needed (was showing "connection closed" error)
2. Run database cleanup queries
3. Implement carryover modal for incomplete jobs
4. Create operator interface for real-time event capture
5. Test the system using TEST_PLAN.md

Key context:
- Operator interface captures real-time events (replacing post-facto supervisor entry)
- Uses EXISTING tables: operations, mach_planning, downtime, notifications
- mach_planning contains ONLY active/future work (no history, no paused/completed)
- Previous working date must skip Sundays and holidays
- 15% buffer time management with overtime projections

See /var/www/html/jc/docs/SYSTEM_CONTEXT.md for full details.
```

## SQL Statements to Run

### 1. Database Cleanup (Run First)
```sql
-- Clean orphaned records from mach_planning
DELETE FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE 
   OR mp_op_status IN (6, 10);

-- Verify cleanup
SELECT COUNT(*) as should_be_zero 
FROM mach_planning 
WHERE mp_op_proddate < CURRENT_DATE 
   OR mp_op_status IN (6, 10);
```

### 2. Test Data Setup (Optional)
```sql
-- Add test jobs for today if needed
-- First check what's available
SELECT op_id, op_lot, op_status, op_prod 
FROM operations 
WHERE op_mach = 13  -- HYD85
  AND op_status < 10
LIMIT 10;

-- If you need to reset a job for testing
UPDATE operations 
SET op_status = 2  -- Assigned
WHERE op_id = 627;  -- Replace with actual ID
```

### 3. Check Holiday Table
```sql
-- Ensure holiday table has data for getPreviousWorkingDate
SELECT * FROM holiday 
WHERE holiday_date BETWEEN '2025-08-01' AND '2025-08-31';

-- Add test holiday if needed
-- INSERT INTO holiday (holiday_date, holiday_name) 
-- VALUES ('2025-08-15', 'Independence Day');
```

## File Structure Reference

```
/var/www/html/jc/
├── index.php                 # Main entry/login
├── planning.php              # Supervisor planning interface
├── config/
│   ├── database.php         # DB connection
│   └── session.php          # Session management
├── helpers/
│   ├── date_helper.php      # Date formatting
│   └── planning_helper.php  # NEW: Planning functions
├── views/
│   ├── timeline.php         # Operator timeline view
│   └── operator.php         # TODO: Operator interface
├── assets/
│   ├── css/
│   │   └── app.css         # Includes time loss card styles
│   └── js/
│       ├── planning.js     # Planning drag-drop
│       └── components/
├── api/                     # TODO: API endpoints
├── docs/
│   ├── SYSTEM_CONTEXT.md   # Complete system overview
│   ├── TEST_PLAN.md        # Testing checklist
│   ├── ARCHITECTURE.md     # Planning rules
│   └── *.md                # Other documentation
```

## Implementation Status

### ✅ Completed
- Planning interface with Quick Find filters
- Timeline view with proper datetime formatting
- Helper functions:
  - `getPreviousWorkingDate()` - Holiday-aware
  - `calculateBurnRate()` - Performance metrics
  - `calculateBufferStatus()` - 15% buffer tracking
- CSS for time loss cards and burn rate visualization
- System documentation

### 🚧 In Progress
- Carryover modal implementation
- Operator interface development

### ⏳ TODO
1. **Carryover Modal** (planning.php)
   - Blocking modal for incomplete jobs
   - Categorize started vs not-started
   - Process carry/reject decisions

2. **Operator Interface** (views/operator.php)
   - Simple one-page design
   - Event buttons: Setup, FPQC, Pause, Resume, Breakdown, Complete
   - Real-time data capture to existing tables

3. **API Endpoints** (/api/)
   - `/api/production-event` - Record operator events
   - `/api/burn-rate/{planning_id}` - Get metrics
   - `/api/time-losses/{planning_id}` - Get delays
   - `/api/buffer-status/{machine}/{shift}` - Buffer info

4. **Visual Components Integration**
   - Time loss cards between jobs
   - Burn rate display
   - Buffer indicator

## Key Architecture Decisions

1. **mach_planning = Active Plans Only**
   - No historical data
   - Remove paused (status=6) and completed (status=10)
   - Daily cleanup required

2. **Event-Driven Tracking**
   - Every operator button creates event
   - Automatic time loss detection
   - No manual delay entry

3. **Real-Time vs Post-Facto**
   - OLD: Supervisors entered data after the fact
   - NEW: Operators capture events as they happen
   - BENEFIT: Live visibility for dynamic planning

## Testing Priorities

1. **First**: Test planning filters and timeline display
2. **Second**: Verify getPreviousWorkingDate with holidays
3. **Third**: Check burn rate calculations
4. **Fourth**: Implement and test carryover modal
5. **Fifth**: Create operator interface

## Common Commands

```bash
# Test URLs
http://localhost/jc/                      # Login
http://localhost/jc/planning.php?m=HYD85  # Planning
http://localhost/jc/?m=HYD85              # Timeline

# Test helper functions
php -r "require '/var/www/html/jc/helpers/planning_helper.php'; echo getPreviousWorkingDate('2025-08-18');"

# Git status
cd /var/www/html/jc && git status

# Check logs
tail -f /var/log/apache2/error.log
```

## MCP Configuration

If MCP MySQL shows "connection closed", restart with:
```bash
# Check status
claude mcp list

# The MySQL server config (for reference)
node /home/ptpl/mcp-database-server/dist/src/index.js \
  --mysql \
  --host localhost \
  --database ptpl \
  --user ptpl \
  --password pU5HK@r@2021
```

## Critical Reminders

1. **Date Format**: Always DD/MM/YYYY for display
2. **Session**: Cookie lifetime = 0 (closes with browser)
3. **Cleanup**: Run cleanup queries before testing
4. **Tables**: Use EXISTING tables, don't create new ones
5. **Testing**: Follow TEST_PLAN.md systematically

## Next Immediate Steps

1. Fix MCP MySQL connection if needed
2. Run database cleanup queries above
3. Test login → planning → timeline flow
4. Report any issues found
5. Implement carryover modal
6. Create operator interface

---
Ready to continue! Just paste the quick start prompt above to begin.