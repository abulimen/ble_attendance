# Course Rep API Documentation

## Overview

This API provides endpoints for the Course Rep section of the University Attendance Management System. It allows course representatives to authenticate, manage courses, and handle attendance sessions through a Flutter mobile application.

## Base URL

All API endpoints are relative to the base URL:

```
https://ble.xpansieve.com.ng/api/
```

## Authentication

The API uses JWT (JSON Web Token) for authentication. All endpoints except login and verify_token require authentication.

To access protected endpoints, you must include the token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

### Authentication Endpoints

#### Login

Authenticates a course rep and returns a JWT token.

- **URL**: `/login.php`
- **Method**: `POST`
- **Auth Required**: No
- **Content-Type**: `application/json`

**Request Body**:

```json
{
  "username": "course_rep_username",
  "password": "password123"
}
```

**cURL Example**:

```bash
curl -X POST \
  https://ble.xpansieve.com.ng/api/login.php \
  -H 'Content-Type: application/json' \
  -d '{
    "username": "course_rep_username",
    "password": "password123"
}'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "user_id": 123,
    "username": "course_rep_username",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "role": "course_rep",
    "managed_groups": [
      {
        "group_id": 1,
        "group_name": "Computer Science 300L"
      }
    ],
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

**Error Response**:

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

#### Verify Token

Verifies a JWT token and returns user information.

- **URL**: `/verify_token.php`
- **Method**: `POST`
- **Auth Required**: No
- **Content-Type**: `application/json`

**Request Body**:

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**cURL Example**:

```bash
curl -X POST \
  https://ble.xpansieve.com.ng/api/verify_token.php \
  -H 'Content-Type: application/json' \
  -d '{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Token is valid",
  "data": {
    "user_id": 123,
    "username": "course_rep_username",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "role": "course_rep",
    "managed_groups": [
      {
        "group_id": 1,
        "group_name": "Computer Science 300L"
      }
    ]
  }
}
```

**Error Response**:

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Invalid or expired token"
}
```

## Group and Course Management

### Get Groups

Retrieves all groups managed by the authenticated course rep.

- **URL**: `/get_groups.php`
- **Method**: `GET`
- **Auth Required**: Yes (JWT token in Authorization header)

**cURL Example**:

```bash
curl -X GET \
  https://ble.xpansieve.com.ng/api/get_groups.php \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Groups retrieved successfully",
  "data": {
    "groups": [
      {
        "group_id": 1,
        "group_name": "Computer Science 300L"
      },
      {
        "group_id": 2,
        "group_name": "Computer Science 200L"
      }
    ]
  }
}
```

**Error Response**:

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Authorization header missing"
}
```

- **Code**: 403
- **Content**:

```json
{
  "status": "error",
  "message": "User is not authorized as a course rep"
}
```

### Get Courses

Retrieves all courses for a specific group.

- **URL**: `/get_courses.php?group_id=1`
- **Method**: `GET`
- **Auth Required**: Yes (JWT token in Authorization header)
- **URL Parameters**: `group_id=[integer]`

**cURL Example**:

```bash
curl -X GET \
  'https://ble.xpansieve.com.ng/api/get_courses.php?group_id=1' \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Courses retrieved successfully",
  "data": {
    "courses": [
      {
        "course_id": 101,
        "course_name": "Introduction to Programming",
        "course_code": "CSC101"
      },
      {
        "course_id": 102,
        "course_name": "Data Structures",
        "course_code": "CSC102"
      }
    ]
  }
}
```

**Error Response**:

- **Code**: 400
- **Content**:

```json
{
  "status": "error",
  "message": "Invalid group ID"
}
```

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Authorization header missing"
}
```

- **Code**: 403
- **Content**:

```json
{
  "status": "error",
  "message": "You do not manage this group"
}
```

## Attendance Session Management

### Start Attendance Session

Starts a new attendance session for a specific course and group.

- **URL**: `/start_attendance_session.php`
- **Method**: `POST`
- **Auth Required**: Yes (JWT token in Authorization header)
- **Content-Type**: `application/json`

**Request Body**:

```json
{
  "group_id": 1,
  "course_id": 101,
  "location": "Lecture Hall A",
  "ble_id": "ble_device_123"
}
```

**cURL Example**:

```bash
curl -X POST \
  https://ble.xpansieve.com.ng/api/start_attendance_session.php \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...' \
  -H 'Content-Type: application/json' \
  -d '{
    "group_id": 1,
    "course_id": 101,
    "location": "Lecture Hall A",
    "ble_id": "ble_device_123"
}'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Attendance session started successfully",
  "data": {
    "session_id": "sess_1a2b3c4d5e6f7g8h",
    "course_id": 101,
    "course_code": "CSC101",
    "course_name": "Introduction to Programming",
    "group_id": 1,
    "group_name": "Computer Science 300L",
    "location": "Lecture Hall A",
    "ble_id": "ble_device_123",
    "start_time": "2025-04-24 10:30:00"
  }
}
```

**Error Response**:

- **Code**: 400
- **Content**:

```json
{
  "status": "error",
  "message": "BLE ID is required"
}
```

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Authorization header missing"
}
```

- **Code**: 409
- **Content**:

```json
{
  "status": "error",
  "message": "An active session (sess_abcdef123456) already exists for this course and group. Please end the previous session first."
}
```

### Get Active Session

Retrieves the active attendance session for a specific course and group.

- **URL**: `/get_active_session.php?group_id=1&course_id=101`
- **Method**: `GET`
- **Auth Required**: Yes (JWT token in Authorization header)
- **URL Parameters**: `group_id=[integer]&course_id=[integer]`

**cURL Example**:

```bash
curl -X GET \
  'https://ble.xpansieve.com.ng/api/get_active_session.php?group_id=1&course_id=101' \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
```

**Success Response (Active Session Found)**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Active session found",
  "data": {
    "session_id": "sess_1a2b3c4d5e6f7g8h",
    "course_id": 101,
    "course_code": "CSC101",
    "course_name": "Introduction to Programming",
    "group_id": 1,
    "group_name": "Computer Science 300L",
    "location": "Lecture Hall A",
    "ble_id": "ble_device_123",
    "start_time": "2025-04-24 10:30:00",
    "initiated_by_user_id": 123
  }
}
```

**Success Response (No Active Session)**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "No active session found",
  "data": {
    "active_session": false
  }
}
```

**Error Response**:

- **Code**: 400
- **Content**:

```json
{
  "status": "error",
  "message": "Invalid group ID"
}
```

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Authorization header missing"
}
```

### End Attendance Session

Ends an active attendance session.

- **URL**: `/end_attendance_session.php`
- **Method**: `POST`
- **Auth Required**: Yes (JWT token in Authorization header)
- **Content-Type**: `application/json`

**Request Body**:

```json
{
  "session_id": "sess_1a2b3c4d5e6f7g8h",
  "group_id": 1,
  "course_id": 101
}
```

**cURL Example**:

```bash
curl -X POST \
  https://ble.xpansieve.com.ng/api/end_attendance_session.php \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...' \
  -H 'Content-Type: application/json' \
  -d '{
    "session_id": "sess_1a2b3c4d5e6f7g8h",
    "group_id": 1,
    "course_id": 101
}'
```

**Success Response**:

- **Code**: 200
- **Content**:

```json
{
  "status": "success",
  "message": "Attendance session ended successfully",
  "data": {
    "session_id": "sess_1a2b3c4d5e6f7g8h",
    "course_id": 101,
    "course_code": "CSC101",
    "course_name": "Introduction to Programming",
    "group_id": 1,
    "group_name": "Computer Science 300L",
    "location": "Lecture Hall A",
    "ble_id": "ble_device_123",
    "start_time": "2025-04-24 10:30:00",
    "end_time": "2025-04-24 11:45:00"
  }
}
```

**Error Response**:

- **Code**: 401
- **Content**:

```json
{
  "status": "error",
  "message": "Authorization header missing"
}
```

- **Code**: 404
- **Content**:

```json
{
  "status": "error",
  "message": "Session not found or you don't have permission to end it"
}
```

- **Code**: 409
- **Content**:

```json
{
  "status": "error",
  "message": "Session (sess_1a2b3c4d5e6f7g8h) was already ended"
}
```

## Error Codes

The API uses standard HTTP status codes to indicate the success or failure of a request:

- `200 OK`: The request was successful
- `400 Bad Request`: The request was invalid or missing required parameters
- `401 Unauthorized`: Authentication failed or token is invalid/missing
- `403 Forbidden`: The authenticated user does not have permission to access the resource
- `404 Not Found`: The requested resource was not found
- `409 Conflict`: The request could not be completed due to a conflict with the current state of the resource
- `500 Internal Server Error`: An error occurred on the server

## Integration with Flutter Mobile App

### Authentication Flow

1. User enters credentials in the Flutter app
2. App sends a POST request to `/login.php`
3. App stores the returned JWT token securely (using secure storage)
4. App includes the token in the Authorization header for all subsequent requests

### Starting an Attendance Session

1. User selects a group and course in the app
2. App sends a POST request to `/start_attendance_session.php` with the required parameters including `ble_id`
3. App displays the session details returned by the API

### Ending an Attendance Session

1. User selects an active session in the app
2. App sends a POST request to `/end_attendance_session.php` with the session details
3. App displays the confirmation message returned by the API

## Security Considerations

1. Always use HTTPS in production to encrypt data in transit
2. Store JWT tokens securely in the Flutter app using flutter_secure_storage
3. Implement token refresh mechanism for long-lived sessions
4. Validate all input data on both client and server sides
5. Implement rate limiting to prevent abuse

## Troubleshooting

### Common Issues

1. **Authentication Failures**: 
   - Ensure the token is valid and not expired
   - Check that the Authorization header is formatted correctly: `Bearer <token>`
   - Verify that the token is being sent with every request to protected endpoints

2. **Permission Errors**: 
   - Verify that the user is a course rep and has permission to manage the specified group
   - Check that the user has the correct role in the database

3. **Session Conflicts**: 
   - Check if there's already an active session for the course and group before starting a new one
   - Use the get_active_session endpoint to verify session status

4. **Missing or Invalid Parameters**:
   - Ensure all required parameters are included in requests
   - Verify that parameter types match what the API expects (integers for IDs, strings for text)
   - BLE ID is now required for starting attendance sessions

### Debugging

For debugging purposes, you can use the `/test_api.php` script to test all API endpoints. This script simulates API calls and displays the responses.

## API Changes Log

### Latest Update (April 24, 2025)
- All endpoints now automatically require JWT authentication except login and verify_token
- BLE ID is now a required parameter for starting attendance sessions
- Added detailed cURL examples for all endpoints
- Improved error handling and response messages
