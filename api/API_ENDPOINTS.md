# Job Center API Endpoints

This document describes the available API endpoints in the Job Center application.

## Base URL

```
/jc/api/
```

## Authentication

All API endpoints require an active session. Users must be logged in through the web interface.

## Response Format

All responses are in JSON format with the following structure:

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "error": "Error message",
    "code": "ERROR_CODE",
    "details": { ... }
}
```

## Endpoints

### 1. Health Check

**GET** `/health`

Checks if the API is running.

**Response:**
```json
{
    "success": true,
    "message": "API is running",
    "data": {
        "status": "healthy",
        "timestamp": "2025-01-15T10:30:00Z"
    }
}
```

### 2. Job Data

**GET** `/job/{id}`

Retrieves detailed information about a specific job.

**Parameters:**
- `id` (path): Job ID

**Response:**
```json
{
    "success": true,
    "message": "Job data retrieved",
    "data": {
        "op_id": 123,
        "op_lot": "LOT001",
        "item_code": "ITEM123",
        "planned_quantity": 100,
        "actual_quantity": 75,
        "op_status": 5,
        "progress_percent": 75.0,
        "is_qc_hold": false
    }
}
```

### 3. Job Status

**GET** `/status/{id}`

Retrieves the current status of a specific job.

**Parameters:**
- `id` (path): Job ID

**Response:**
```json
{
    "success": true,
    "message": "Job status retrieved",
    "data": {
        "status": 5
    }
}
```

## Action Endpoints

All action endpoints are accessed via `/actions/{action}` and require POST requests.

### 4. Setup Action

**POST** `/actions/setup`

Initiates job setup.

**Required Fields:**
- `job_id`: Job ID
- `msg`: Setup message (max 20 characters)
- `dtsp`: Setup date (YYYY-MM-DD)
- `dtsp_hora`: Setup time (HH:MM:SS)

**Optional Fields:**
- `rmks`: Remarks (max 100 characters)

**Response:**
```json
{
    "success": true,
    "message": "Setup completed successfully",
    "data": {
        "job_id": 123,
        "action": "setup",
        "timestamp": "2025-01-15 10:30:00",
        "operation_id": 456,
        "status": "Setup"
    }
}
```

### 5. FPQC Action

**POST** `/actions/fpqc`

Submits First Piece Quality Check.

**Required Fields:**
- `job_id`: Job ID
- `opq_act_prdqty`: Actual production quantity
- `opq_qc_qty`: QC quantity

**Optional Fields:**
- `opq_rjk_qty`: Reject quantity
- `opq_nc_qty`: Non-conformance quantity
- `opq_qc_rmks`: QC remarks
- `opq_reason`: Rejection reason

### 6. Pause Action

**POST** `/actions/pause`

Pauses the current job.

**Required Fields:**
- `job_id`: Job ID
- `dtp`: Pause datetime
- `rsn`: Pause reason

**Optional Fields:**
- `rmks`: Additional remarks

### 7. Resume Action

**POST** `/actions/resume`

Resumes a paused job.

**Required Fields:**
- `job_id`: Job ID
- `dtr`: Resume datetime
- `rmks`: Resume remarks

### 8. Complete Action

**POST** `/actions/complete`

Marks a job as complete.

**Required Fields:**
- `job_id`: Job ID
- `final_qty`: Final quantity produced

**Optional Fields:**
- `reject_qty`: Rejected quantity
- `rmks`: Completion remarks

### 9. Breakdown Action

**POST** `/actions/breakdown`

Reports a machine breakdown.

**Required Fields:**
- `job_id`: Job ID
- `dtbd`: Breakdown datetime
- `rmk`: Breakdown details

### 10. QC Check Action

**POST** `/actions/qc_check`

Requests a quality check.

**Required Fields:**
- `job_id`: Job ID
- `dtq`: QC request datetime

**Optional Fields:**
- `msg`: Message for QC team
- `rmks`: Detailed remarks

### 11. Testing Action

**POST** `/actions/testing`

Records test results.

**Required Fields:**
- `job_id`: Job ID
- `test_type`: Type of test
- `test_value`: Test value
- `test_unit`: Unit of measurement
- `pass_fail`: Test result (pass/fail)

### 12. Alert Action

**POST** `/actions/alert`

Reports an issue or concern.

**Required Fields:**
- `job_id`: Job ID
- `issue_type`: Type of issue
- `severity`: Issue severity
- `description`: Issue description

### 13. Contact Action

**POST** `/actions/contact`

Contacts supervisor for help.

**Required Fields:**
- `job_id`: Job ID
- `issue_type`: Type of help needed
- `message`: Message for supervisor

## Planning Endpoints (Supervisor Only)

### 14. Planning Actions

**POST** `/actions/planning`

Handles supervisor planning operations.

**Actions:**
- `update_sequence`: Update job sequence
- `clear_job_sequence`: Clear job from sequence
- `add_job`: Add job to planning
- `remove_job`: Remove job from planning

## Status Codes

- `200`: Success
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `409`: Conflict (e.g., status conflict)
- `422`: Validation Error
- `500`: Server Error

## Error Codes

- `MISSING_ACTION`: Action name not provided
- `MISSING_JOB_ID`: Job ID not provided
- `INVALID_JOB_ID`: Invalid job ID format
- `JOB_NOT_FOUND`: Job does not exist
- `STATUS_CONFLICT`: Job status prevents action
- `MISSING_FIELDS`: Required fields missing
- `VALIDATION_ERROR`: Field validation failed
- `UNAUTHORIZED`: Session not authenticated
- `SERVER_ERROR`: Internal server error
- `DATABASE_ERROR`: Database operation failed

## Rate Limiting

No rate limiting is currently implemented, but actions are logged for audit purposes.

## Logging

All actions are logged with:
- Job ID
- Action type
- Operator name
- Timestamp
- Action details (JSON)

## Notes

1. All datetime fields should be in `YYYY-MM-DD HH:MM:SS` format
2. Text fields have character limits as specified
3. Numeric fields are validated for appropriate ranges
4. Some actions have status prerequisites that must be met
5. Supervisor actions require `is_supervisor` session flag
