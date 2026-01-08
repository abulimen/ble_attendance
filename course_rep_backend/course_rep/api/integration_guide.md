# Course Rep API Integration Guide

## Overview

This document provides a comprehensive guide for integrating the Course Rep API with the Flutter mobile app. The API allows course representatives to authenticate, manage courses, and handle attendance sessions.

## API Structure

The API is located in the `/api` directory of the course rep panel and includes the following endpoints:

1. **Authentication**
   - `login.php` - Authenticates users and returns a JWT token
   - `verify_token.php` - Verifies JWT tokens and returns user information

2. **Course Management**
   - `get_groups.php` - Retrieves groups managed by the course rep
   - `get_courses.php` - Retrieves courses for a specific group

3. **Attendance Sessions**
   - `start_attendance_session.php` - Starts a new attendance session with BLE ID
   - `get_active_session.php` - Retrieves active attendance sessions
   - `end_attendance_session.php` - Ends an active attendance session

## Security Implementation

All API endpoints (except login and verify_token) automatically require JWT authentication. The implementation:

1. Uses JWT tokens for secure authentication
2. Validates tokens on every request
3. Checks user permissions for each operation
4. Requires BLE ID for starting attendance sessions

## Installation Instructions

1. Extract the `course_rep_complete.zip` file to your web server
2. Configure your database connection in `/includes/db_connect.php`
3. Update the JWT secret key in `/api/config.php`
4. Ensure the web server has write permissions for the attendance session records

## Integration with Flutter App

The Flutter app is already configured to work with these API endpoints. To connect it to your server:

1. Update the `baseUrl` constant in `lib/services/api_service.dart` to point to your server
2. Test the connection using the login functionality
3. Verify that attendance sessions can be started and stopped

## Testing the API

You can use the included `test_api.php` script to verify all API endpoints are working correctly:

1. Update the test credentials in the script
2. Access the script through your web browser or command line
3. Check that all endpoints return the expected responses

## Troubleshooting

Common issues and solutions:

1. **Authentication Errors**
   - Verify JWT secret key is consistent
   - Check token expiration settings
   - Ensure Authorization header is formatted correctly

2. **Database Connection Issues**
   - Confirm database credentials are correct
   - Check database server is accessible
   - Verify table structure matches expected schema

3. **Permission Errors**
   - Ensure course rep users have the correct role in the database
   - Verify group assignments for course reps

## API Updates

Recent updates to the API include:

1. Automatic JWT validation for all endpoints
2. Required BLE ID parameter for attendance sessions
3. Enhanced error handling and response messages
4. Comprehensive documentation with cURL examples

For detailed API documentation, refer to the `documentation.md` file in the `/api` directory.
