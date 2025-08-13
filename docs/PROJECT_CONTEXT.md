# Project Context - Job Center (Simplified)

## Overview
Mobile-first tablet interface for discrete manufacturing shop floor operators. Designed for zero-training operation with single-screen access to all job functions. Built with simplicity and reliability as core principles.

## Business Context
### Manufacturing Process
1. **Job Assignment**: Production planning assigns jobs to machines via `mach_planning` table
2. **Sequential Processing**: Operators must complete jobs in order, no skipping allowed
3. **Setup Phase**: Operator sets up machine for specific job
4. **First Piece**: Operator produces first piece for QC approval (FPQC)
5. **Production**: After approval, operator completes remaining quantity
6. **Completion**: Job marked complete with actual quantity, next job auto-loads

### Operator Workflow
- Single machine interface showing job queue
- Current job details prominently displayed
- Complete current job before accessing next
- Manual quantity entry (future: sensor-based)
- QC hold blocks all actions until cleared

### Design Principles
- **Zero Training**: Interface so simple that operators need no training
- **Single Screen**: Everything accessible without navigation
- **Touch First**: Large buttons designed for gloved hands
- **Visual Feedback**: Clear status indicators and progress
- **Mobile Patterns**: Familiar smartphone-like interactions

## Architecture
```
[Tablet] → [/jc/index.php] → [Simple REST API] → [Database]
           ↓
    [Progressive Web App]
           ↓
    [Vanilla JS + CSS]
```

## Technical Stack
- **Backend**: PHP 7.4+ with clean REST API
- **Frontend**: Progressive Web App (PWA)
- **Styling**: Modern CSS with CSS Grid/Flexbox
- **Database**: MySQL/MariaDB (ptpl schema)
- **No Framework**: Vanilla JS for simplicity and speed
- **Server**: http://192.168.100.102/jc

## Implementation Status (2025-07-31 - COMPLETED)
- **Login System**: ✅ Complete with operator selection
- **Timeline View**: ✅ Visual job timeline for shift
- **Job Detail View**: ✅ All buttons with responsive layout  
- **Machine Detection**: ✅ URL parameter with 5-char codes
- **Database Integration**: ✅ Connected with ADODB wrapper
- **Header Design**: ✅ Light theme with Pushkar logo
- **Error Handling**: ✅ Comprehensive error handling
- **API Infrastructure**: ✅ Complete REST API with router
- **All 12 Endpoints**: ✅ Setup, FPQC, Pause, Resume, Complete, etc.
- **Modal Forms**: ✅ All 12 forms with validation
- **JavaScript App**: ✅ Complete with offline support
- **Button Actions**: ✅ All wired and functional
- **Offline Queue**: ✅ LocalStorage with auto-sync
- **Auto-refresh**: ✅ QC hold and status checks
- **Notifications**: ✅ Custom notification system
- **Test Data**: ⏳ Awaiting operations records

## Database Schema (ptpl)
### Key Tables
- **mach_planning**: Production planning data
  - `mp_op_od`: Planning ID (primary key)
  - `mp_op_mach`: Machine ID
  - `mp_op_order`: Order ID
  - `mp_op_item`: Item code
  - `mp_op_qty`: Quantity to produce
  - `mp_op_qty_done`: Quantity completed

- **machine**: Machine master
  - `mm_id`: Machine ID
  - `mm_shortname`: Display name

- **job_actions**: Log all operator actions
  - `ja_id`: Action ID
  - `ja_planning_id`: Planning ID
  - `ja_action`: Action type (setup, pause, resume, etc.)
  - `ja_timestamp`: When action occurred
  - `ja_operator`: Who performed action

## User Interface Design

### Layout (Single Screen)
```
┌─────────────────────────────────────────┐
│ Machine Info | Job Info | Operator/Time │
├─────────────────────────────────────────┤
│                                         │
│        Job Status Indicators            │
│        (Setup/Production/Complete)      │
│                                         │
│        Progress Bar & Numbers           │
│                                         │
├─────────────────────────────────────────┤
│        Primary Action Buttons           │
│        (Large, color-coded)             │
├─────────────────────────────────────────┤
│        Secondary Actions                │
│        (Smaller icon buttons)           │
└─────────────────────────────────────────┘
```

### Action Types (Operator-Only)
1. **Primary Actions** (Large buttons):
   - Setup - Start machine setup
   - FPQC - Request first piece QC
   - Pause/Resume - Toggle work status
   - Complete - Mark job done with quantity
   - Breakdown - Report machine issue

2. **Secondary Actions** (Icon buttons):
   - Drawing - View technical drawing
   - Control Chart - View quality chart
   - Contact - Call supervisor
   - QC Check - Request random QC
   - Testing - Record test results
   - Alert - Report production issue
   - Lock - Secure screen

**Excluded (Supervisor-Only)**:
- Assign, Split, Change Machine, QC Hold/Release

## API Endpoints
- `GET /api/job/{planning_id}` - Get job details
- `POST /api/action` - Log operator action
- `GET /api/status/{planning_id}` - Get current status
- `POST /api/quantity` - Update completed quantity
- `GET /api/operators` - List available operators

## Key Features
1. **Offline First**: Works without constant connection
2. **Auto-sync**: Syncs actions when online
3. **Real-time Clock**: Always shows current time
4. **Progress Tracking**: Visual and numeric progress
5. **Action History**: All actions logged with timestamp
6. **Bilingual**: Hindi/English labels

## Security & Reliability
- Planning ID validation
- Action logging for audit trail
- Offline queue for actions
- No complex authentication (operator selection only)
- Input validation on all forms

## Architecture Highlights
- **Single Page Application**: Modal-based interactions
- **Offline-First**: Queue and sync pattern
- **Status-Driven UI**: Buttons enable/disable based on job status
- **Auto-Refresh**: Real-time updates without page reload
- **Touch-Optimized**: 44px minimum targets, visual feedback
- **Zero Dependencies**: No external libraries required

## Development Guidelines
- Keep it simple - no complex frameworks
- Mobile-first responsive design
- Test on actual tablets
- Consider shop floor conditions
- Ensure buttons work with gloves
- High contrast for visibility
- Follow existing patterns from dashboards project

## URL Structure
- Main: `http://[server]/jc/?p=[planning_id]`
- API: `http://[server]/jc/api/[endpoint]`

## Success Metrics
- Zero operator training required
- All actions completable in 2 taps or less
- Works offline for at least 8 hours
- Page load under 2 seconds
- 100% touch accessible