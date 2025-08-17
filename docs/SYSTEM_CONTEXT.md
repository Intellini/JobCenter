# Job Center System Context

## Project Overview
Complete rewrite of Job Center for manufacturing shop floor management with real-time production tracking.

## System Components

### 1. Supervisor Planning Interface (`planning.php`)
- **Purpose**: Supervisors plan and sequence jobs for machines/shifts
- **Features**:
  - Drag-and-drop job sequencing
  - Automatic changeover time calculation
  - Carryover workflow for incomplete jobs
  - Real-time adjustments based on shop floor events
  - Filter by item name and lot number
  - Visual feedback on delays and buffer consumption

### 2. Timeline View (`timeline.php`, `index.php`)
- **Purpose**: Read-only view of current shop floor status
- **Architecture Decision**: "Timeline page needs simple query - just show what's in mach_planning"
- **Display**: Jobs for selected machine with visual status indicators
- **Issues Fixed**: Duplicate jobs, overlapping times, date format

### 3. Operator Interface (NEW - In Development)
- **Purpose**: Real-time event capture as work happens
- **Workflow**: Login → Select first job → Click buttons as events occur
- **Events**: Setup, FPQC, Start, Pause/Resume, Breakdown, Complete
- **Design**: Simple one-page interface, no complex navigation
- **Data**: Feeds existing tables in real-time (previously entered post-facto by supervisors)

## Database Architecture

### Core Tables (Existing)
- `operations` - Permanent job records
- `mach_planning` - Temporary staging for active plans (current/future only)
- `downtime` - Tracks pauses and breakdowns with durations
- `notifications` - Event log for all operator actions
- `operations_quality` - QC check records
- `wip_items` - Work in progress items
- `machine` - Machine definitions
- `holiday` - Non-working days

### Key Rules
1. **mach_planning = Active Plans Only**
   - No historical data
   - No paused jobs (status=6)
   - No completed jobs (status=10)
   - Automatic cleanup on date change

2. **Carryover Workflow**
   - Check previous working date (excluding Sundays/holidays)
   - Force supervisor decision on incomplete jobs
   - Carry forward or pause/reject each job

3. **Status Management**
   - 0-2: Planning stages
   - 3: Setup
   - 4: FPQC
   - 5: In Process
   - 6: Paused (removed from mach_planning)
   - 7: Breakdown
   - 10: Complete (removed from mach_planning)

## Data Flow

### Planning Flow
```
Supervisor selects jobs → Sequences in planning UI → Saves to mach_planning
                      ↓
              Timeline displays plans
                      ↓
         Operator executes on shop floor
```

### Real-time Event Flow
```
Operator clicks button → Event logged to tables → Metrics calculated
                      ↓                         ↓
              Supervisor sees update    Time loss detected
                      ↓                         ↓
              Can adjust plans          Visual cards shown
```

## Time Tracking & Visualization

### Automatic Time Loss Detection
- **Setup Delays**: Actual setup time > standard
- **Breakdowns**: Duration from breakdown to resume
- **Changeover Excess**: Actual changeover > standard
- **Production Rate Loss**: Actual rate < planned rate

### Visual Indicators
- **Time Loss Cards**: Small blocks between jobs showing delays
  - Orange: Setup delay
  - Red: Breakdown
  - Yellow: Changeover excess
  - Purple: Production rate loss
- **Burn Rate**: (Actual Qty ÷ Planned Qty) / (Time Elapsed ÷ Planned Time)
- **Buffer Management**: 15% shift buffer tracking

## Implementation Status

### Completed
- Planning interface with filters
- Timeline view with job display
- Architecture documentation
- Cleanup workflow design

### In Progress
- Operator interface development
- Real-time event capture
- Burn rate calculation
- Time loss visualization

### Pending
- getPreviousWorkingDate function
- Carryover modal implementation
- Sensor/counter integration
- WebSocket real-time updates

## Key Design Decisions

1. **Separation of Concerns**
   - Timeline: Simple read from mach_planning
   - Planning: Complex logic and carryover
   - Operator: Real-time event capture

2. **Event-Driven Architecture**
   - Every operator action creates trackable event
   - Automatic time loss detection from event sequences
   - No manual data entry for delays

3. **Visual Feedback Priority**
   - Supervisors need immediate visual cues
   - Color coding for status (green/yellow/red)
   - Progressive enhancement (works without real-time)

## Session Management
- Cookie lifetime = 0 (session ends on browser close)
- Previous working date logic for carryover
- Operator remains logged in during shift

## Date Format
- Standard: DD/MM/YYYY throughout project
- Database: YYYY-MM-DD
- Display: DD/MM/YYYY

## Testing Environment
- Machine codes: HYD85, PNU83, PNU65
- Test with overlapping jobs
- Verify touch targets (44px minimum)
- Test offline capability

## Future Enhancements
1. Sensor integration for automatic quantity updates
2. WebSocket for real-time supervisor updates
3. Predictive analytics for delays
4. Machine learning for pattern detection

## Critical Context for Next Session
1. **Operator interface is NEW** - captures real-time events that were previously entered post-facto
2. **Same tables** - uses existing downtime, notifications, operations tables
3. **Simple workflow** - operator just clicks buttons as work happens
4. **Supervisor benefit** - sees live progress and can adjust plans dynamically
5. **Architecture rule** - mach_planning contains ONLY active future work

This system transforms manual post-facto data entry into real-time event capture, enabling dynamic production management.