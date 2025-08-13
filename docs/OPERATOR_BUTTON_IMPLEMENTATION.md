# Job Center Operator Button Implementation

## Overview
This document details only the operator-level button implementations for the tablet interface. Supervisor-only functions (Assign, Split, Change Machine, QC Hold) are excluded from this project scope.

## Project Scope
- **Single Machine Interface**: Shows jobs for one machine only
- **Sequential Job Processing**: Operators must complete jobs in sequence
- **No Job Selection**: Cannot skip to next job until current is marked complete
- **Completion Tracking**: Manual quantity entry on completion (future: sensor-based)

## Operator Button Implementations

### 1. **Setup** - Start Job Setup
**Purpose**: Operator indicates they are starting machine setup for the job

**Form Fields**:
- `msg` - Message (text, max 20)
- `dtsp` - Setup date (date)
- `dtsp_hora` - Setup time (time)
- `rmks` - Remarks (textarea, 2x50, max 20)

**SQL Operations**:
```sql
-- Update operation status to Setup (3)
UPDATE operations SET op_status=3 WHERE op_id=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Setup')
```

---

### 2. **FPQC (First Piece QC)** - Request Quality Check
**Purpose**: Operator requests QC inspection after producing first piece

**Form Fields**:
- `opq_act_prdqty` - Actual production quantity
- `opq_qc_qty` - QC quantity
- `opq_rjk_qty` - Reject quantity
- `opq_nc_qty` - Non-conformance quantity
- `opq_qc_rmks` - QC remarks
- `opq_reason` - Reason (if rejected)

**SQL Operations**:
```sql
-- Update operation status to FPQC (4)
UPDATE operations SET op_status=4 WHERE op_id=?

-- Create QC record
INSERT INTO operations_quality (...) VALUES (...)

-- Create notification for QC team
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('FPQC Required', 0, NOW(), 'operations', ?, 'FPQC')
```

---

### 3. **QC Check** - Request Random Quality Check
**Purpose**: Operator can request QC inspection during production

**Form Fields**:
- `msg` - Message (text)
- `rmks` - Remarks (text)
- `dtq` - QC date (datetime)

**SQL Operations**:
```sql
-- Create QC request record
INSERT INTO operations_quality (opq_opid, opq_status, opq_qc_rev, ...)
SELECT ?, 13, MAX(opq_qc_rev)+1, ... FROM operations_quality WHERE opq_opid=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('QC Check Requested', 0, NOW(), 'operations', ?, 'QC Check')
```

---

### 4. **Pause/Resume** - Toggle Production Status
**Purpose**: Operator pauses work with reason or resumes after pause

#### Pause Operation
**Form Fields**:
- `dtp` - Pause date/time
- `rsn` - Reason (**REQUIRED**, dropdown)
- `rmks` - Remarks (max 20 chars)

**SQL Operations**:
```sql
-- Save current status and set to paused (6)
UPDATE operations SET op_holdflg=op_status, op_status=6, op_pause_time=? WHERE op_id=?

-- Log downtime
INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr)
VALUES (2, ?, ?, ?, ?, ?, ?, null, now(), now(), ?)
```

#### Resume Operation
**Form Fields**:
- `dtr` - Resume date/time
- `rmks` - Remarks (**REQUIRED**, max 150 chars)

**SQL Operations**:
```sql
-- Calculate pause duration and restore status
UPDATE operations SET 
    op_tot_pause = op_tot_pause + TIMESTAMPDIFF(MINUTE, op_pause_time, ?),
    op_pause_time = NULL,
    op_status = op_holdflg 
WHERE op_id = ?

-- Close downtime record
UPDATE downtime SET dt_endt = ? WHERE dt_type = 2 AND dt_opid = ? AND dt_endt IS NULL
```

---

### 5. **Breakdown** - Report Machine Breakdown
**Purpose**: Operator reports machine breakdown requiring maintenance

**Form Fields**:
- `dtbd` - Breakdown date/time
- `rmk` - Remarks (textarea, 5x40)

**SQL Operations**:
```sql
-- Set operation to breakdown status (7)
UPDATE operations SET op_holdflg=op_status, op_status=7, op_bkdown_task=? WHERE op_id=?

-- Create maintenance task
INSERT INTO maintenance.tasks (title, project_id, created_at, created_by)
VALUES ('Investigate Breakdown', ?, ?, 3)

-- Log downtime
INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr)
VALUES (1, 0, ?, ?, ?, ?, ?, null, now(), now(), ?)

-- Alert maintenance team
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('Machine Breakdown', 0, NOW(), 'operations', ?, 'Breakdown')
```

---

### 6. **Complete** - Mark Job Complete
**Purpose**: Operator marks job as complete and enters final quantity

**Form Fields**:
- `final_qty` - Final quantity produced (numeric, **REQUIRED**)
- `reject_qty` - Rejected quantity (numeric)
- `rmks` - Completion remarks (text)

**SQL Operations**:
```sql
-- Update operation status to Complete (10)
UPDATE operations SET 
    op_status=10, 
    op_act_prdqty=?,
    op_end_time=NOW()
WHERE op_id=?

-- Update inventory
UPDATE wip_items SET im_stkqty=im_stkqty+? WHERE im_id=?

-- Log completion
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('Job Completed: ? pcs', 0, NOW(), 'operations', ?, 'Complete')
```

**Note**: After completion, the interface automatically loads the next job in sequence for the machine.

---

### 7. **Drawing** - View Technical Drawing
**Purpose**: Opens technical drawing for current job
- No form submission
- Opens drawing viewer/PDF in modal
- Read-only operation

---

### 8. **Control Chart** - View Quality Control Chart
**Purpose**: Opens control chart for current job
- No form submission
- Opens chart viewer in modal
- Read-only operation

---

### 9. **Contact Supervisor** - Call for Help
**Purpose**: Alerts supervisor that operator needs assistance

**Form Fields**:
- `issue_type` - Type of help needed (dropdown)
- `message` - Brief description (text)

**SQL Operations**:
```sql
-- Create supervisor alert
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('Operator needs help: ?', 0, NOW(), 'operations', ?, 'Contact')
```

---

### 10. **Alert/Issue** - Report Production Issue
**Purpose**: Report non-breakdown issues affecting production

**Form Fields**:
- `issue_type` - Issue category (dropdown)
- `severity` - Severity level (dropdown)
- `description` - Issue details (textarea)

**SQL Operations**:
```sql
-- Log issue
INSERT INTO production_issues (pi_opid, pi_type, pi_severity, pi_desc, pi_timestamp, pi_usr)
VALUES (?, ?, ?, ?, NOW(), ?)

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES ('Production Issue: ?', 0, NOW(), 'operations', ?, 'Issue')
```

---

### 11. **Testing** - Record Test Results
**Purpose**: Record quality test measurements during production

**Form Fields**:
- `test_type` - Test category (dropdown)
- `test_value` - Measurement value (numeric)
- `test_unit` - Unit of measurement (dropdown)
- `pass_fail` - Pass/Fail status (radio)

**SQL Operations**:
```sql
-- Record test result
INSERT INTO quality_tests (qt_opid, qt_type, qt_value, qt_unit, qt_result, qt_timestamp, qt_usr)
VALUES (?, ?, ?, ?, ?, NOW(), ?)
```

---

### 12. **Lock Screen** - Secure Interface
**Purpose**: Lock screen when operator steps away
- Requires operator ID/PIN to unlock
- Pauses any active timers
- No database operations

---

## Interface Behavior Rules

### Job Sequence Management
1. **Job List Display**: Shows all jobs assigned to machine in sequence
2. **Current Job Highlight**: Active job prominently displayed
3. **Sequential Processing**: 
   - Next job button disabled until current is complete
   - Cannot skip jobs in queue
   - Auto-loads next job after completion

### Button States
1. **Always Visible**: All buttons remain visible regardless of status
2. **Pause/Resume Toggle**: Single button that changes label/icon
3. **Disabled During QC Hold**: All action buttons disabled when `op_status=12`

### QC Hold Interface
When job is on QC hold:
- Screen overlay with 50% opacity
- Large message: "Waiting for QC Clearance"
- Only Drawing and Control Chart buttons remain active
- Auto-refresh every 30 seconds to check hold status

### Auto-Refresh Behavior
1. **Split Detection**: Check every 60 seconds if job was split by supervisor
2. **Quantity Alert**: If `op_act_prdqty >= op_pln_prdqty`, show completion prompt
3. **Status Updates**: Refresh job status every 30 seconds

### Future Enhancements
1. **Sensor Integration**: 
   - Auto-populate quantity fields from stroke sensors
   - Real-time progress bar updates
   - Burn rate calculation (time elapsed vs qty completed)

2. **Performance Metrics**:
   - Show % of planned time elapsed
   - Show % of job completed
   - Visual indicators when falling behind schedule

## Database Status Codes
- 1: New
- 2: Assigned
- 3: Setup
- 4: FPQC
- 5: In Process
- 6: Paused
- 7: Breakdown
- 8: On Hold
- 9: LPQC
- 10: Complete
- 12: QC Hold
- 13: QC Check