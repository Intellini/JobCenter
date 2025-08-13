# Job Center API Endpoints

This document provides a complete reference for all available API endpoints in the Job Center application.

## Base URL
All API requests should be made to: `/jc/api/`

## Request Format
- **Method**: POST (recommended) or GET
- **Content-Type**: `application/json`
- **Parameters**: Can be sent as JSON body or URL parameters

## Standard Response Format
```json
{
  "success": true|false,
  "message": "Description of result",
  "data": { ... },
  "error": "Error message (if failed)",
  "code": "ERROR_CODE (if failed)"
}
```

## Authentication
- All endpoints require a valid `planning_id` parameter (except health and status)
- Some endpoints require an `operator` parameter for audit logging

---

## System Endpoints

### Health Check
**Endpoint**: `?action=health`  
**Purpose**: Check if API is responding  
**Parameters**: None  
**Returns**: System status and timestamp

### Job Status
**Endpoint**: `?action=status&planning_id={id}`  
**Purpose**: Get current job status  
**Parameters**: 
- `planning_id` (required)

---

## Job Action Endpoints

### 1. Setup
**Endpoint**: `?action=setup`  
**Purpose**: Start job setup  
**Required Parameters**:
- `planning_id`
- `msg` (max 20 chars)

**Optional Parameters**:
- `dtsp` (setup date, default: today)
- `dtsp_hora` (setup time, default: now)
- `rmks` (remarks, max 100 chars)
- `operator`

### 2. First Piece QC (FPQC)
**Endpoint**: `?action=fpqc`  
**Purpose**: Request first piece quality check  
**Required Parameters**:
- `planning_id`
- `opq_act_prdqty` (actual production quantity)
- `opq_qc_qty` (QC quantity)

**Optional Parameters**:
- `opq_rjk_qty` (reject quantity)
- `opq_nc_qty` (non-conformance quantity)
- `opq_qc_rmks` (QC remarks, max 255 chars)
- `opq_reason` (reason if rejected)
- `operator`

### 3. QC Check
**Endpoint**: `?action=qc_check`  
**Purpose**: Request quality check during production  
**Required Parameters**:
- `planning_id`
- `msg` (message, max 100 chars)

**Optional Parameters**:
- `rmks` (remarks, max 200 chars)
- `dtq` (QC datetime, default: now)
- `operator`

### 4. Pause
**Endpoint**: `?action=pause`  
**Purpose**: Pause active job  
**Required Parameters**:
- `planning_id`
- `rsn` (reason code)

**Optional Parameters**:
- `dtp` (pause datetime, default: now)
- `rmks` (remarks, max 20 chars)
- `operator`

### 5. Resume
**Endpoint**: `?action=resume`  
**Purpose**: Resume paused job  
**Required Parameters**:
- `planning_id`
- `rmks` (remarks, max 150 chars)

**Optional Parameters**:
- `dtr` (resume datetime, default: now)
- `operator`

### 6. Breakdown
**Endpoint**: `?action=breakdown`  
**Purpose**: Report machine breakdown  
**Required Parameters**:
- `planning_id`
- `rmk` (breakdown remarks, max 200 chars)

**Optional Parameters**:
- `dtbd` (breakdown datetime, default: now)
- `operator`

### 7. Complete
**Endpoint**: `?action=complete`  
**Purpose**: Mark job as complete  
**Required Parameters**:
- `planning_id`
- `final_qty` (final quantity produced)

**Optional Parameters**:
- `reject_qty` (rejected quantity)
- `rmks` (completion remarks)
- `operator`

### 8. Contact Supervisor
**Endpoint**: `?action=contact`  
**Purpose**: Alert supervisor for assistance  
**Required Parameters**:
- `planning_id`
- `issue_type` (type of help needed)

**Optional Parameters**:
- `message` (brief description)
- `operator`

### 9. Alert/Issue
**Endpoint**: `?action=alert`  
**Purpose**: Report production issue  
**Required Parameters**:
- `planning_id`
- `issue_type` (quality|material|tooling|process|safety|equipment|measurement|documentation|other)
- `severity` (low|medium|high|critical)
- `description` (issue details, max 500 chars)

**Optional Parameters**:
- `operator`

### 10. Testing
**Endpoint**: `?action=testing`  
**Purpose**: Record quality test results  
**Required Parameters**:
- `planning_id`
- `test_type` (dimensional|surface_finish|hardness|visual|functional|torque|pressure|temperature|other)
- `test_value` (measurement value)
- `test_unit` (mm|in|um|mil|kg|lb|N|Nm|psi|bar|C|F|HRC|HRB|Ra|count)
- `pass_fail` (pass|fail)

**Optional Parameters**:
- `operator`

---

## Information Endpoints

### Drawing
**Endpoint**: `?action=drawing`  
**Purpose**: Get technical drawing information  
**Required Parameters**:
- `planning_id`

**Optional Parameters**:
- `operator` (for audit logging)

**Returns**: Available drawing files and metadata

### Control Chart
**Endpoint**: `?action=control_chart`  
**Purpose**: Get quality control chart data  
**Required Parameters**:
- `planning_id`

**Optional Parameters**:
- `operator` (for audit logging)

**Returns**: Quality test data, statistics, and control limits

### Get Job Data
**Endpoint**: `?action=get_job_data`  
**Purpose**: Get complete job information  
**Required Parameters**:
- `planning_id`

**Returns**: Comprehensive job details

---

## Screen Management

### Lock/Unlock
**Endpoint**: `?action=lock`  
**Purpose**: Lock or unlock the operator screen  
**Required Parameters**:
- `planning_id`
- `lock_action` (lock|unlock)

**For Lock**:
- `operator` (operator ID)

**For Unlock**:
- `unlock_operator` (operator ID for unlock)
- `unlock_pin` (operator PIN)

---

## Error Codes

- `INVALID_PLANNING_ID` - Planning ID is invalid or not found
- `JOB_NOT_FOUND` - Job not found for given planning ID
- `STATUS_CONFLICT` - Job status doesn't allow this action
- `MISSING_FIELDS` - Required fields are missing
- `VALIDATION_ERROR` - Input validation failed
- `DATABASE_ERROR` - Database operation failed
- `SERVER_ERROR` - Internal server error
- `INVALID_ACTION` - Requested action is not allowed
- `HANDLER_NOT_FOUND` - Action handler file not found
- `FUNCTION_NOT_FOUND` - Action function not found

---

## Example Usage

### Setup Job
```bash
curl -X POST /jc/api/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "setup",
    "planning_id": 12345,
    "msg": "Starting setup",
    "operator": "OP001"
  }'
```

### Record Test Result
```bash
curl -X POST /jc/api/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "testing",
    "planning_id": 12345,
    "test_type": "dimensional",
    "test_value": 25.4,
    "test_unit": "mm",
    "pass_fail": "pass",
    "operator": "OP001"
  }'
```

### Get Control Chart Data
```bash
curl -X GET "/jc/api/?action=control_chart&planning_id=12345"
```