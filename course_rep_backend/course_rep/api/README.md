# Course Rep API for Attendance Management System

This directory contains the API endpoints for the Course Rep section of the University Attendance Management System. These endpoints allow course representatives to authenticate, manage courses, and handle attendance sessions through a Flutter mobile application.

## API Endpoints

The following API endpoints have been implemented:

### Authentication
- `login.php` - Authenticates a course rep and returns a JWT token
- `verify_token.php` - Verifies a JWT token and returns user information

### Group and Course Management
- `get_groups.php` - Retrieves all groups managed by the authenticated course rep
- `get_courses.php` - Retrieves all courses for a specific group

### Attendance Session Management
- `start_attendance_session.php` - Starts a new attendance session with BLE ID support
- `get_active_session.php` - Retrieves the active attendance session for a course and group
- `end_attendance_session.php` - Ends an active attendance session

## Documentation

For detailed API documentation, please refer to the `documentation.md` file in this directory. It provides comprehensive information about:

- Request and response formats for each endpoint
- Authentication flow
- Error codes and handling
- Integration guidance for the Flutter mobile app

## Testing

The `test_api.php` script can be used to test all API endpoints. It simulates API calls and displays the responses.

## Security

The API uses JWT (JSON Web Token) for authentication. All protected endpoints require a valid token in the Authorization header.

## Integration with Flutter

The API is designed to be easily integrated with a Flutter mobile app. The documentation provides guidance on how to implement the authentication flow and attendance session management in your Flutter application.
