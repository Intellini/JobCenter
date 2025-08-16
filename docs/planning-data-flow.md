# Planning Data Flow Documentation

## Overview
The Job Center planning interface allows supervisors to sequence jobs and calculate timing, which must be persisted to the `mach_planning` table for operators to view.

## Current Architecture

### Tables Involved
1. **operations** - Stores job details with `op_seq` field for sequencing
2. **mach_planning** - Stores planned sequence with calculated start/end times
3. **localStorage** - Browser storage for offline capability and draft saves

## Data Flow Workflow

### 1. Planning Interface Load (planning.php)
- Load existing plan from `mach_planning` table if exists for machine/date/shift
- Check for incomplete jobs from previous date:
  - Started but not complete (status 3-9)
  - Planned but not started (status 0-2)
- Show modal for supervisor to select jobs to carry over
- If no database plan exists, load unsequenced jobs from `operations` table
- Apply any localStorage draft if newer than database version

### 2. Supervisor Makes Changes
- Drag-and-drop jobs to sequence them
- System calculates start/end times based on:
  - Shift start time (A: 06:00, B: 14:00, C: 22:00)
  - Setup time per job
  - Production time per job
  - Changeover time between jobs
- Changes saved to localStorage immediately for draft protection

### 3. Save Sequence Action
- When supervisor clicks "Save Sequence" or exits to operator view:
  1. Update `operations.op_seq` with sequence numbers
  2. Insert/Update records in `mach_planning` table with:
     - Machine ID
     - Work date
     - Shift
     - Job ID (op_id)
     - Sequence number
     - Calculated start time
     - Calculated end time
     - Operator who planned
     - Timestamp
  3. Clear localStorage draft

### 4. Operator View Load (index.php → timeline.php)
- Simple query: Load from `mach_planning` table for machine/date ONLY
- No complex logic or carryover handling
- Display exactly what supervisor saved
- Jobs are already consolidated including any carryovers

## API Endpoints

### planning.php Actions
- **load_planning**: Get existing plan from mach_planning
- **update_sequence**: Save sequence to operations.op_seq
- **save_to_mach_planning**: Save calculated times to mach_planning table
- **get_previous_jobs**: Check for incomplete jobs from previous date
- **handle_previous_jobs**: Carry over selected jobs and release others

## Time Calculation Logic
```
shift_start = shift_start_time (06:00, 14:00, or 22:00)
current_time = shift_start

for each job in sequence:
    job.start_time = current_time
    job.end_time = current_time + setup_time + production_time
    current_time = job.end_time + changeover_time
```

## localStorage Keys
- Planning draft: `planning_sequence_${MACHINE_ID}_${WORK_DATE}_${SHIFT}`
- Contains: Array of job objects with calculated times

## Carryover Workflow

### Previous Day Job Handling
1. **Detection**: On planning page load, check for incomplete jobs from previous date
2. **Categorization**:
   - Started jobs (status 3-9): Auto-selected for carryover
   - Not started jobs (status 0-2): Not selected by default
3. **Supervisor Decision**:
   - Select jobs to carry over to current date
   - Unselected jobs are released back to pool
4. **Processing**:
   - Selected jobs: Update date, recalculate sequence, maintain status
   - Unselected jobs: Delete from mach_planning, clear op_seq
5. **Result**: Consolidated plan saved to mach_planning for current date

## Error Handling
- Database write failures: Keep localStorage as backup
- Network issues: Work offline with localStorage
- Conflicting edits: Last write wins with timestamp comparison
- Carryover conflicts: Supervisor resolves through modal interface