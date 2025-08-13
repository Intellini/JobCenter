# Job Center Database Requirements

## Overview
This document outlines the database tables and data requirements for the Job Center application to function properly.

## Core Tables Required

### 1. **operations** (Main job tracking table)
Primary table that stores all job/operation records.

**Key Columns Used:**
- `op_id` (INT) - Primary key, auto-increment
- `op_lot` (VARCHAR) - Lot number (unique identifier for the job)
- `op_mach` (INT) - Machine ID (foreign key to machine.mm_id)
- `op_obid` (INT) - Order ID (foreign key to orders_head.ob_id)
- `op_prod` (INT) - Product/Item ID (foreign key to wip_items.im_id)
- `op_pln_prdqty` (INT) - Planned production quantity
- `op_act_prdqty` (INT) - Actual production quantity completed
- `op_status` (INT) - Job status (1-13, see status definitions below)
- `op_start` (DATETIME) - Planned start time
- `op_end` (DATETIME) - Planned end time
- `op_stp_time` (INT) - Setup time in minutes
- `op_tot_pause` (INT) - Total pause time in minutes
- `op_holdflg` (INT) - Hold flag (1 if on hold)
- `op_seq` (INT) - Sequence number for ordering
- `op_shift` (CHAR) - Shift code ('A', 'B', or 'C')
- `op_createdt` (DATETIME) - Record creation timestamp

**Status Values:**
- 1 = New/Pending
- 2 = Assigned
- 3 = Setup
- 4 = FPQC (First Piece QC)
- 5 = In Process/Production
- 6 = Paused
- 7 = Breakdown
- 8 = On Hold
- 9 = LPQC (Last Piece QC)
- 10 = Complete
- 12 = QC Hold
- 13 = QC Check

### 2. **machine** (Machine master data)
Stores information about all machines.

**Key Columns Used:**
- `mm_id` (INT) - Primary key
- `mm_name` (VARCHAR) - Full machine name/description
- `mm_code` (VARCHAR(5)) - 5-character unique code (e.g., 'HYD45')
- `mm_active` (INT) - 1 if active, 0 if inactive

**Sample Data:**
```
mm_id | mm_code | mm_name
37    | HYD45   | PT/HP/200/045/ISGEC
```

### 3. **wip_items** (Work-in-progress items/products)
Stores product/item information.

**Key Columns Used:**
- `im_id` (INT) - Primary key
- `im_name` (VARCHAR) - Item code/name (this is used as both code and name)
- `im_active` (INT) - 1 if active
- `im_group` (VARCHAR) - Item group/category

**Note:** There is no `im_code` column - the item code is stored in `im_name`

### 4. **orders_head** (Order header information)
Stores order/job header information.

**Key Columns Used:**
- `ob_id` (INT) - Primary key
- `ob_ref` (VARCHAR) - Order reference number
- `ob_porefno` (VARCHAR) - Purchase order reference
- `ob_date` (DATE) - Order date

### 5. **manpower** (Operator information)
Stores operator/employee information.

**Key Columns Used:**
- `mp_id` (INT) - Primary key
- `mp_name` (VARCHAR) - Operator name
- `mp_active` (INT) - 1 if active

### 6. **mach_planning** (Production planning - Optional)
Used for job sequencing when available.

**Key Columns Used:**
- `mp_op_id` (BIGINT) - Planning ID
- `mp_op_lot` (VARCHAR) - Lot number
- `mp_op_mach` (INT) - Machine ID
- `mp_op_date` (DATE) - Planning date
- `mp_op_shift` (INT) - Shift number (1=A, 2=B, 3=C)
- `mp_op_seq` (INT) - Sequence number
- `mp_op_start` (DATETIME) - Planned start
- `mp_op_end` (DATETIME) - Planned end
- `mp_op_pln_prdqty` (INT) - Planned quantity
- `mp_op_act_prdqty` (INT) - Actual quantity

**Note:** Shift is stored as number (1,2,3) not letter (A,B,C)

### 7. **jwork** (Job action history - Future use)
Will store all operator actions for audit trail.

**Planned Columns:**
- `jw_id` (INT) - Primary key
- `jw_planning_id` (VARCHAR) - References op_lot
- `jw_action` (VARCHAR) - Action type
- `jw_mach` (INT) - Machine ID
- `jw_date` (DATE) - Action date
- `jw_time` (TIME) - Action time
- `jw_operator` (INT) - Operator ID
- `jw_qty` (INT) - Quantity at time of action
- `jw_shift` (CHAR) - Shift

### 8. **notifications** (System notifications - Future use)
For system-wide notifications.

**Planned Columns:**
- `nm_id` (INT) - Primary key
- `nm_text` (TEXT) - Notification message
- `nm_target` (INT) - Target user/group
- `nm_createdt` (DATETIME) - Creation timestamp
- `nm_obj` (VARCHAR) - Object type (e.g., 'operations')
- `nm_objpk` (INT) - Object primary key
- `nm_action` (VARCHAR) - Action that triggered notification

## Data Requirements for Testing

### Minimum Data Needed:
1. **At least one active machine** with `mm_code` populated (5 chars)
2. **Active operators** in manpower table
3. **Operations records** with:
   - Valid machine ID
   - Status between 1-10 (not completed)
   - Start/end times (can be NULL but better with values)
   - Some quantity values for progress display

### Sample Test Data Structure:
```sql
-- Machine (already exists)
mm_id: 37, mm_code: 'HYD45', mm_name: 'PT/HP/200/045/ISGEC'

-- Operations (needs to be created)
op_lot: 'LOT-001'
op_mach: 37
op_obid: NULL or valid order ID
op_prod: NULL or valid item ID
op_pln_prdqty: 100
op_act_prdqty: 50
op_status: 5 (In Process)
op_start: '2025-07-31 14:00:00'
op_end: '2025-07-31 16:00:00'
```

## Key Relationships
1. `operations.op_mach` → `machine.mm_id`
2. `operations.op_prod` → `wip_items.im_id`
3. `operations.op_obid` → `orders_head.ob_id`
4. `operations.op_lot` = `mach_planning.mp_op_lot` (when using planning)

## Important Notes
1. **Shift Format**: Operations table uses 'A'/'B'/'C', but mach_planning uses 1/2/3
2. **Item Code**: No separate code column in wip_items - use im_name
3. **NULL Handling**: Start/end times can be NULL - app handles gracefully
4. **Status Filtering**: Only show jobs with status < 10 (not completed)

## Validation Rules
1. Machine code must be exactly 5 characters
2. Shift must be A, B, or C in operations table
3. Status must be between 1-13
4. Quantities should be non-negative integers