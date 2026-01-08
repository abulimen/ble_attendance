import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'auth_service.dart'; // Assuming AuthService is in the same directory

import 'package:student_app/config/debug_config.dart';

class ApiService {
  // Base URL should match the one in AuthService and your PHP backend
  static const String _baseUrl = 'https://ble.xpansieve.com.ng/api/student';
  final AuthService _authService = AuthService();

  // Fetches active attendance sessions for the logged-in student
  Future<Map<String, dynamic>> getActiveSessions() async {
    try {
      final headers = await _authService.getAuthHeaders();
      if (!headers.containsKey('Authorization')) {
        return {
          'success': false,
          'message': 'User not authenticated. Please login.'
        };
      }

      final response = await http.get(
        Uri.parse('$_baseUrl/active_sessions.php'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body);
        if (responseData['status'] == 'success') {
          return {'success': true, 'data': responseData['data']};
        } else {
          return {
            'success': false,
            'message': responseData['message'] ??
                'Failed to fetch active sessions: Unexpected response'
          };
        }
      } else if (response.statusCode == 401) {
        // Token might be expired or invalid, attempt logout
        await _authService.logout();
        return {
          'success': false,
          'message':
              'Authentication failed or token expired. Please login again.',
          'logout_required': true
        };
      } else {
        final responseData = jsonDecode(response.body);
        return {
          'success': false,
          'message': responseData['message'] ??
              'Failed to fetch active sessions with status: ${response.statusCode}'
        };
      }
    } catch (e) {
      print('Get active sessions error: $e');
      return {
        'success': false,
        'message': 'An error occurred while fetching active sessions: $e'
      };
    }
  }

  // Marks attendance for the student with photo
  Future<Map<String, dynamic>> markAttendance(String sessionId, int courseId,
      int groupId, String scannedBleId, File studentPhoto) async {
    try {
      DebugConfig.log(
          'Marking attendance for session: $sessionId, BLE: $scannedBleId');

      final headers = await _authService.getAuthHeaders();
      if (!headers.containsKey('Authorization')) {
        return {
          'success': false,
          'message': 'User not authenticated. Please login.'
        };
      }

      // Read photo file and convert to base64
      final photoBytes = await studentPhoto.readAsBytes();
      final photoBase64 = base64Encode(photoBytes);

      // Determine image type from file extension
      String imageType = 'jpeg';
      if (studentPhoto.path.toLowerCase().endsWith('.png')) {
        imageType = 'png';
      } else if (studentPhoto.path.toLowerCase().endsWith('.jpg') ||
          studentPhoto.path.toLowerCase().endsWith('.jpeg')) {
        imageType = 'jpeg';
      }

      // Create base64 data URI
      final photoDataUri = 'data:image/$imageType;base64,$photoBase64';

      // Create JSON payload (as PHP expects)
      final payload = {
        'session_id': sessionId,
        'course_id': courseId,
        'group_id': groupId,
        'scanned_ble_id': scannedBleId,
        'photo': photoDataUri,
      };

      DebugConfig.log(
          'Attendance payload prepared. Image size: ${photoBase64.length} chars');

      // Set headers for JSON
      final jsonHeaders = Map<String, String>.from(headers);
      jsonHeaders['Content-Type'] = 'application/json';

      // Send POST request with JSON body
      final response = await http.post(
        Uri.parse('$_baseUrl/mark_attendance.php'),
        headers: jsonHeaders,
        body: jsonEncode(payload),
      );

      DebugConfig.log(
          'Mark attendance response status: ${response.statusCode}');
      DebugConfig.log('Mark attendance response body: ${response.body}');

      // Check if response body is empty
      if (response.body.isEmpty) {
        return {
          'success': false,
          'message':
              'Server returned empty response. Status code: ${response.statusCode}. This usually means a server error or configuration issue.'
        };
      }

      if (response.statusCode == 200) {
        try {
          final responseData = jsonDecode(response.body);
          if (responseData['status'] == 'success') {
            return {'success': true, 'data': responseData['data']};
          } else {
            return {
              'success': false,
              'message': responseData['message'] ??
                  'Failed to mark attendance: Unexpected response'
            };
          }
        } catch (e) {
          DebugConfig.log('JSON decode error: $e');
          return {
            'success': false,
            'message':
                'Invalid response from server: $e. Raw response: ${response.body}'
          };
        }
      } else if (response.statusCode == 401) {
        await _authService.logout();
        return {
          'success': false,
          'message':
              'Authentication failed or token expired. Please login again.',
          'logout_required': true
        };
      } else {
        try {
          final responseData = jsonDecode(response.body);
          // Handle specific error messages from backend like BLE ID mismatch, already marked, etc.
          return {
            'success': false,
            'message': responseData['message'] ??
                'Failed to mark attendance with status: ${response.statusCode}'
          };
        } catch (e) {
          return {
            'success': false,
            'message':
                'Server error (${response.statusCode}). Response: ${response.body}'
          };
        }
      }
    } catch (e) {
      DebugConfig.log('Mark attendance error: $e');
      return {
        'success': false,
        'message': 'An error occurred while marking attendance: $e'
      };
    }
  }
}
