# Architecture Decisions

## Decision: Separation of Concerns - Planning vs Timeline

**Date:** August 16, 2025

### Context
The Job Center application needs to handle incomplete jobs from previous dates, including:
- Jobs that were started but not completed (status 3-9)
- Jobs that were planned but never started (status 0-2)

### Decision
We implement a clear separation of concerns between the Planning interface and Timeline view:

#### Timeline Page (`index.php`)
- **Purpose:** Simple, read-only view for operators
- **Query:** Direct query to `mach_planning` for specific date and machine
- **Complexity:** None - just display what's saved
- **Logic:** No business logic, no carryover handling

```sql
SELECT * FROM mach_planning 
WHERE mp_op_mach = ? AND mp_op_proddate = ?
```

#### Planning Page (`planning.php`)
- **Purpose:** Complete planning interface for supervisors
- **Complexity:** All business logic lives here
- **Features:**
  - Check for incomplete jobs from previous dates
  - Modal interface for job selection
  - Carry over selected jobs to current date
  - Release unselected jobs back to pool
  - Save consolidated plan to `mach_planning`

### Rationale

1. **Simplicity for Operators**
   - Timeline shows exactly what was planned
   - No confusion about job sources
   - Fast, simple queries

2. **Control for Supervisors**
   - All decisions made in planning interface
   - Full visibility of pending work
   - Explicit control over carryover

3. **Clean Data Flow**
   - Planning → mach_planning → Timeline
   - No complex queries in timeline
   - Single source of truth

4. **Maintainability**
   - Business logic in one place
   - Easy to understand and debug
   - Clear responsibilities

### Implementation Details

#### Carryover Workflow
1. Supervisor opens planning page
2. System checks for incomplete jobs from previous date
3. Modal shows:
   - Started jobs (auto-selected for carryover)
   - Not started jobs (not selected by default)
4. Supervisor selects jobs to carry over
5. Selected jobs:
   - Update to current date
   - Get new sequence numbers
   - Maintain status and progress
6. Unselected jobs:
   - Removed from mach_planning
   - Released back to operations pool

#### Data Integrity
- Carryover jobs maintain their operation status
- Times are adjusted to current date
- Sequence numbers are recalculated
- Original job IDs preserved for tracking

### Consequences

**Positive:**
- Clear separation of concerns
- Simple timeline queries
- Full supervisor control
- Clean audit trail
- Easy to understand

**Negative:**
- Supervisor must handle carryover daily
- No automatic carryover (by design)

### Status
**Accepted and Implemented**

### Related Documents
- [Planning Data Flow](planning-data-flow.md)
- [Database Schema](../CLAUDE.md)