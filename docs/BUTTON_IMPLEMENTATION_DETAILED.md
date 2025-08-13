# Job Center Button Implementation - Detailed Analysis

## Overview
This document provides a comprehensive analysis of all button implementations from the Scriptcase supervisor interface, detailing the SQL operations, form fields, and business logic for each action button.

## Button Implementations

### 1. **Breakdown (BKW)** - `/factorynet/bkw/`
**Purpose**: Reports machine breakdown and creates maintenance tasks

**Form Fields**:
- `dtbd` - Breakdown date/time (datetime)
- `dtbd_hora` - Time component (time)
- `rmk` - Remarks (textarea, 5x40)

**SQL Operations**:
```sql
-- Update operations status
UPDATE operations SET op_holdflg=op_status WHERE op_id=?
UPDATE operations SET op_status=7, op_bkdown_task=? WHERE op_id=?

-- Update quality status
UPDATE operations_quality SET opq_holdflg=opq_status WHERE opq_opid=?
UPDATE operations_quality SET opq_status=7 WHERE opq_opid=?

-- Create maintenance task
INSERT INTO maintenance.tasks (title, parent_id, project_id, project_list_id, `order`, created_at, created_by)
VALUES ('Investigate Breakdown', NULL, ?, ?, 0, ?, 3)

-- Log downtime
INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr)
VALUES (1, 0, ?, ?, ?, ?, ?, null, now(), now(), ?)

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Breakdown')
```

**Tables Affected**:
- operations, operations_quality, maintenance.tasks, maintenance.task_user, downtime, notifications

---

### 2. **FPQC (First Piece QC)** - `/factorynet/fqc/`
**Purpose**: Records first piece quality check results

**Form Fields**:
- `opq_act_prdqty` - Actual production quantity (numeric, max 5 digits)
- `opq_qc_qty` - QC quantity (numeric, max 5 digits)
- `opq_rjk_qty` - Reject quantity (numeric, max 5 digits)
- `opq_nc_qty` - Non-conformance quantity (numeric, max 5 digits)
- `opq_qc_rmks` - QC remarks (text)
- `opq_reason` - Reason (lookup)
- `opq_dierwk` - Die rework flag (integer)
- `opq_qchold` - QC hold flag (integer)

**SQL Operations**:
```sql
-- Update operations status
UPDATE operations SET op_act_prdqty=?, op_status=12 WHERE op_id IN (SELECT opq_opid FROM operations_quality WHERE opq_id=?)

-- Update quality status
UPDATE operations_quality SET opq_status=12 WHERE opq_id=?

-- Update WIP stock
UPDATE wip_items SET im_stkdt=now(), im_stkqty=im_stkqty+? WHERE im_id=?

-- Create new quality record for rejects
INSERT INTO operations_quality (opq_opid,opq_imbo,opq_obid,opq_lot,opq_prod,opq_pln_conqty,opq_pln_prdqty,opq_inst,opq_qc,opq_qc_by,opq_qc_qty,opq_status,opq_mflg,opq_qc_rev,opq_qchold)
SELECT opq_opid,opq_imbo,opq_obid,concat(??,'-',?),opq_prod,opq_pln_conqty,opq_pln_prdqty,opq_inst,opq_qc,opq_qc_by,opq_qc_qty,4,0,opq_qc_rev+1,1 FROM operations_quality WHERE opq_id=?

-- Log stock operation
INSERT INTO stock_oprs (so_imname,so_imid,so_date,so_type,so_in,so_out,so_sih,so_qty,so_docref,so_mach,so_usr,so_timestamp)
VALUES(?,?,?,?,?,?,?,?,?,?,?,?)

-- Create QC check record
INSERT INTO qc_check (qc_title,qc_opqid,qc_opid,opq_obid,qc_opqc,qc_hold,qc_rjkqty,qc_ncqty,qc_reason,qc_dierework,qc_remarks,qc_timestmp)
SELECT 'QC - Repeat',?,opq_opid,opq_obid,opq_qc,opq_qchold,0,0,0,0,'',NOW() FROM operations_quality WHERE opq_id=?
```

**Tables Affected**:
- operations, operations_quality, wip_items, stock_oprs, qc_check, notifications

---

### 3. **Pause** - `/factorynet/bps/`
**Purpose**: Pauses operation with reason tracking

**Form Fields**:
- `msg` - Message (text, max 20 chars)
- `dtp` - Pause date/time (datetime)
- `dtp_hora` - Time component (time)
- `rsn` - Reason (select, **REQUIRED**)
- `rmks` - Remarks (textarea, max 20 chars)

**SQL Operations**:
```sql
-- Save current status
UPDATE operations SET op_holdflg=op_status WHERE op_id IN (?)

-- Set paused status
UPDATE operations SET op_status=6, op_pause_time=? WHERE op_id IN (?)

-- Log downtime
INSERT INTO downtime (dt_type, dt_rsn, dt_lot, dt_itm, dt_opid, dt_mchid, dt_stdt, dt_endt, dt_createdt, dt_update, dt_usr)
VALUES (2, ?, ?, ?, ?, ?, ?, null, now(), now(), ?)

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Pause')
```

**Tables Affected**:
- operations, downtime, notifications, jobpause_rsn (lookup)

---

### 4. **Resume** - `/factorynet/rsm/`
**Purpose**: Resumes paused or breakdown operations

**Form Fields**:
- `msg` - Message (hidden)
- `dtr` - Resume date/time (datetime)
- `dtr_hora` - Time component (time)
- `rmks` - Remarks (**REQUIRED**, max 150 chars)

**SQL Operations**:
```sql
-- For paused operations (status=6)
UPDATE operations SET 
    op_tot_pause = (IFNULL(op_tot_pause,0) + (TIMESTAMPDIFF(MINUTE, IFNULL(op_pause_time, ?), ?))),
    op_pause_time = NULL 
WHERE op_id = ?

-- For breakdown operations (status=7)
UPDATE operations SET 
    op_tot_bkdn = (IFNULL(op_tot_bkdn,0) + (TIMESTAMPDIFF(MINUTE, IFNULL(op_bkdn_time, ?), ?))),
    op_bkdn_time = NULL 
WHERE op_id = ?

-- Close downtime record
UPDATE downtime SET dt_endt = ? WHERE dt_type = ? AND dt_itm = ? AND dt_mchid = ? AND dt_endt IS NULL

-- Restore previous status
UPDATE operations SET op_status = op_holdflg WHERE op_id = ?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, ?, 'operations', ?, 'Resume')
```

**Tables Affected**:
- operations, downtime, notifications, maintenance.projects, maintenance.tasks, maintenance.favorite_projects

---

### 5. **Complete** - `/factorynet/tskcmp/`
**Purpose**: Marks task/action as complete

**Form Fields**:
- `msg` - Message (text)
- `aid` - Action ID (numeric)

**SQL Operations**:
```sql
-- Update action item status
UPDATE actionitems SET status=5 WHERE action_id=?

-- Log the action
INSERT INTO sc_log (inserted_date, username, application, creator, ip_user, action, description)
VALUES (?, ?, 'tskcmp', ?, ?, ?, ?)
```

**Tables Affected**:
- actionitems, sc_log

---

### 6. **Assign** - `/factorynet/jasgn/`
**Purpose**: Manages job assignment reasons/categories

**Form Fields**:
- `jr_name` - Name (text, max 50)
- `jr_shortname` - Short name (text, max 50)
- `jr_groupname` - Group name (text, max 50)
- `jr_active` - Active flag (checkbox)
- `jr_createdate` - Create date (datetime)

**SQL Operations**:
```sql
-- CRUD operations on job reasons table
INSERT INTO [job_reasons] (jr_name, jr_shortname, jr_groupname, jr_active, jr_createdate)
VALUES (?, ?, ?, ?, ?)

UPDATE [job_reasons] SET jr_name=?, jr_shortname=?, jr_groupname=?, jr_active=? WHERE jr_id=?

DELETE FROM [job_reasons] WHERE jr_id=?
```

**Tables Affected**:
- Job reasons/assignments table

---

### 7. **Setup** - `/factorynet/stp/`
**Purpose**: Records operation setup start

**Form Fields**:
- `msg` - Message (text, max 20)
- `dtsp` - Setup date (date)
- `dtsp_hora` - Setup time (time)
- `rmks` - Remarks (textarea, 2x50, max 20)

**SQL Operations**:
```sql
-- Create setup notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Setup')
```

**Tables Affected**:
- notifications, operations (status update implied)

---

### 8. **Change Machine (CHMC)** - `/factorynet/chmc/`
**Purpose**: Reassigns operation to different machine/resource

**Form Fields**:
- `msg` - Message (text)
- `rmks` - Remarks (text)
- `mch` - Machine ID (select from machine table)
- `res` - Resource ID (select from manpower table)
- `rsn` - Reason ID (select from jobrasgn_rsn table)
- `dtc` - Date changed (datetime)
- `dtc_hora` - Time component (time)

**SQL Operations**:
```sql
-- Update machine and resource
UPDATE operations SET op_mach=?, op_res=? WHERE op_id=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Reassign')
```

**Tables Affected**:
- operations, notifications, machine (lookup), manpower (lookup), jobrasgn_rsn (lookup)

---

### 9. **QC Check** - `/factorynet/qcchk/`
**Purpose**: Creates quality control check records

**Form Fields**:
- `msg` - Message (text)
- `rmks` - Remarks (text)
- `dtq` - QC date (datetime)
- `dtq_hora` - Time component (time)

**SQL Operations**:
```sql
-- Create QC record with revision tracking
INSERT INTO operations_quality (opq_opid, opq_lot, opq_status, opq_qc_rev, ...)
SELECT ?, ?, ?, MAX(opq_qc_rev)+1, ... FROM operations_quality WHERE opq_opid=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'QC Check')
```

**Tables Affected**:
- operations, operations_quality, notifications

---

### 10. **QC Hold/Release** - `/factorynet/qcrel/`
**Purpose**: Releases operations from QC hold status

**Form Fields**:
- `msg` - Message (text)
- `rmks` - Remarks (text)
- `dtqr` - Release date (datetime)
- `dtqr_hora` - Time component (time)

**SQL Operations**:
```sql
-- Release from hold
UPDATE operations SET op_status=op_holdflg WHERE op_id=?

-- Update maintenance system
UPDATE maintenance.projects SET meta='{"color":"#84CC16"}' WHERE id=?
DELETE FROM maintenance.favorite_projects WHERE project_id=?
UPDATE maintenance.tasks SET completed_at=? WHERE id=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Resume')
```

**Tables Affected**:
- operations, notifications, maintenance.projects, maintenance.favorite_projects, maintenance.tasks

---

### 11. **Split** - `/factorynet/splt/`
**Purpose**: Splits operation into two separate operations

**Form Fields**:
- `msg` - Message (text)
- `prdqty` - Production quantity (numeric)
- `rjkqty` - Reject/split quantity (numeric)
- `rmks` - Remarks (text)
- `q1dt` - Queue 1 date (datetime)
- `q2dt` - Queue 2 date (datetime)
- `rsn` - Split reason (select from jobsplit_rsn table)

**SQL Operations**:
```sql
-- Update original operation with -S1 suffix
UPDATE operations SET op_lot=CONCAT(op_lot,'-S1'), op_pln_prdqty=? WHERE op_id=?

-- Create new split operation with -S2 suffix
INSERT INTO operations (op_lot, op_pln_prdqty, ...) 
SELECT CONCAT(op_lot,'-S2'), ?, ... FROM operations WHERE op_id=?

-- Duplicate QC records for both splits
INSERT INTO operations_quality (...) SELECT ... FROM operations_quality WHERE opq_opid=?

-- Create notification
INSERT INTO notifications (nm_text, nm_target, nm_createdt, nm_obj, nm_objpk, nm_action)
VALUES (?, 0, NOW(), 'operations', ?, 'Split')
```

**Tables Affected**:
- operations, operations_quality, notifications, wip_items, machine_part, jobsplit_rsn (lookup)

---

## Common Patterns

### Parameters Received
All applications typically receive:
- Operation ID(s) - via session or GET/POST
- User ID - from session
- CSRF token - for security
- Form-specific fields

### Validation
- Date/time format validation
- Numeric field validation
- Required field checks
- Character length limits
- CSRF token validation

### Notifications
All actions create notification records with:
- Action description
- Timestamp
- Operation reference
- Action type

### Status Management
Operations use status codes:
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
- 11: (Reserved)
- 12: QC Hold
- 13: QC Check

### Missing Implementations
Based on the original button list, these implementations were not found as separate applications:
- **Drawing (dwg)** - Likely opens drawing viewer/document
- **Control Chart (chrt)** - Likely opens chart viewer/report

These may be implemented as direct links or integrated into other applications rather than separate control forms.