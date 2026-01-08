# Student API Documentation

## Overview

This API provides endpoints for students to authenticate, manage their face profiles, view active attendance sessions, and mark their attendance.

## Base URL

All API endpoints are relative to the base URL:

```
https://ble.xpansieve.com.ng/api/student/
```

#### NOTE: ALL REQUEST BODIES ARE NOT JSON, BUT FORM DATA! YOU ARE NOT TO SEND JSON TO THE SERVER! ONLY FORM INPUT DATA AND FILES LIKE AN HTML FORM BCOS THE SERVER EXPECTS FORMDATA. NOT JSON! IT'LL ONLY RETURN JSON. NOT ACCEPT!

## Authentication

The API uses JWT (JSON Web Token) for authentication. All endpoints except login require authentication.

To access protected endpoints, you must include the token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

Student JWTs are valid for 7 days.

### Authentication Endpoints

#### Login

Authenticates a student and returns a JWT token along with student details and face registration status.

- **URL**: `/login.php`
- **Method**: `POST`
- **Auth Required**: No
- **Content-Type**: `application/json`

**Request Body**:

```json
{
  "username": "student_username_or_matric_number_or_application_id",
  "password": "student_password"
}
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "requires_face_registration": true/false,
    "student_details": {
      "user_id": 123,
      "application_id": "APP123",
      "matric_number": "MATRIC123",
      "first_name": "Jane",
      "last_name": "Doe",
      "email": "jane.doe@example.com",
      "group_id": 1,
      "group_name": "Computer Science 100L",
      "department_id": 1,
      "face_profile_status": "pending/approved/rejected/incomplete",
      "profile_completion_status": "complete/incomplete"
    }
  }
}
```

**Error Responses**:

- **Code**: 400
    - **Content**: `{"status": "error", "message": "Username and password are required."}`
- **Code**: 401
    - **Content**: `{"status": "error", "message": "Invalid username or password."}`
- **Code**: 405
    - **Content**: `{"status": "error", "message": "Invalid request method. Only POST is allowed."}`
- **Code**: 500
    - **Content**: `{"status": "error", "message": "Database connection failed."}` or `{"status": "error", "message": "Failed to prepare statement: [error_details]"}`


### Attendance Management

#### Get Active Sessions

Retrieves a list of active attendance sessions relevant to the student (based on their group or carry-over enrollments).

- **URL**: `/active_sessions.php`
- **Method**: `GET`
- **Auth Required**: Yes (JWT token in Authorization header)

**Request Parameters**: None (all information derived from JWT and database)

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "data": {
    "active_sessions": [
      {
        "session_id": "sess_...",
        "course_id": 101,
        "course_name": "Introduction to Programming",
        "course_code": "CSC101",
        "group_id": 1,
        "group_name": "Computer Science 100L",
        "session_start_time": "YYYY-MM-DD HH:MM:SS",
        "location": "Lecture Hall A",
        "ble_id": "ble_device_123",
        "lecturer_name": "John Doe"
      }
      // ... more active sessions
    ]
  }
}
```

*   If no active sessions are found, `active_sessions` array will be empty.

**Error Responses**:

- **Code**: 401
    - **Content**: `{"status": "error", "message": "Access denied. No token provided."}`
    - **Content**: `{"status": "error", "message": "Access denied. Invalid or expired token."}`
- **Code**: 500
    - **Content**: `{"status": "error", "message": "Failed to prepare statement: [error_details]"}`

#### Mark Attendance

Marks a student's attendance for a specific session after successful BLE ID verification. Requires an approved face profile.

- **URL**: `/mark_attendance.php`
- **Method**: `POST`
- **Auth Required**: Yes (JWT token in Authorization header)
- **Content-Type**: `application/json`

**Pre-conditions**: Student must have an approved face profile.

**Request Body**:

```json
{
  "session_id": "string_session_id",
  "course_id": 123,
  "group_id": 456,
  "scanned_ble_id": "string_ble_id",
  "student_photo": IMAGE_FILE_DATA
}
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "data": {
    "message": "Attendance marked successfully.",
    "attendance_record": {
      "attendance_id": 789,
      "session_id": "string_session_id",
      "student_id": 123,
      "course_id": 123,
      "group_id": 456,
      "status": "Present",
      "attendance_time": "YYYY-MM-DD HH:MM:SS"
    }
  }
}
```

**Error Responses**:

- **Code**: 400
    - **Content**: `{"status": "error", "message": "Missing required parameters: session_id, course_id, group_id, scanned_ble_id."}`
    - **Content**: `{"status": "error", "message": "BLE ID mismatch. Cannot mark attendance."}`
- **Code**: 401
    - **Content**: `{"status": "error", "message": "Access denied. No token provided."}`
    - **Content**: `{"status": "error", "message": "Access denied. Invalid or expired token."}`
- **Code**: 404
    - **Content**: `{"status": "error", "message": "Session not found, not active, or course/group mismatch."}`
- **Code**: 405
    - **Content**: `{"status": "error", "message": "Invalid request method. Only POST is allowed."}`
- **Code**: 409
    - **Content**: `{"status": "error", "message": "Attendance already marked for this session."}`
- **Code**: 500
    - **Content**: `{"status": "error", "message": "Failed to prepare session statement: [error_details]"}`
    - **Content**: `{"status": "error", "message": "Failed to prepare check statement: [error_details]"}`
    - **Content**: `{"status": "error", "message": "Failed to prepare insert statement: [error_details]"}`
    - **Content**: `{"status": "error", "message": "Failed to mark attendance: [error_details]"}`

