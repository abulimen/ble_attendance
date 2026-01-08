import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:student_app/config/debug_config.dart';

class AuthService {
  // TODO: Replace with your actual API base URL from the PHP backend
  static const String _baseUrl = 'https://ble.xpansieve.com.ng/api/student';
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();

  Future<String?> get token async {
    return await _secureStorage.read(key: 'jwt_token');
  }

  Future<Map<String, dynamic>?> login(String username, String password) async {
    try {
      final response = await http
          .post(
            Uri.parse('$_baseUrl/login.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'username': username, 'password': password}),
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body);
        DebugConfig.log('AuthService: Login response: $responseData');
        if (responseData['status'] == 'success' &&
            responseData['data'] != null &&
            responseData['data']['token'] != null) {
          final String token = responseData['data']['token'];
          await _secureStorage.write(key: 'jwt_token', value: token);

          // Store user details
          if (responseData['data']['student_details'] != null) {
            final userDetails =
                jsonEncode(responseData['data']['student_details']);
            DebugConfig.log('AuthService: Storing user details: $userDetails');
            await _secureStorage.write(key: 'user_details', value: userDetails);
          } else {
            DebugConfig.log(
                'AuthService: No student_details found in response');
          }

          return {
            'success': true,
            'student_details': responseData['data']['student_details']
          };
        } else {
          return {
            'success': false,
            'message': responseData['message'] ??
                'Invalid credentials. Please check your username and password.'
          };
        }
      } else if (response.statusCode == 401) {
        return {
          'success': false,
          'message':
              'Invalid credentials. Please check your username and password.'
        };
      } else if (response.statusCode == 500) {
        return {
          'success': false,
          'message': 'Server error. Please try again later.'
        };
      } else {
        try {
          final responseData = jsonDecode(response.body);
          return {
            'success': false,
            'message':
                responseData['message'] ?? 'Login failed. Please try again.'
          };
        } catch (_) {
          return {
            'success': false,
            'message': 'Login failed. Please try again.'
          };
        }
      }
    } on http.ClientException catch (e) {
      DebugConfig.log('AuthService: Network error: $e');
      return {
        'success': false,
        'message': 'Network error. Please check your internet connection.'
      };
    } on FormatException catch (e) {
      DebugConfig.log('AuthService: Response format error: $e');
      return {
        'success': false,
        'message': 'Server response error. Please try again.'
      };
    } catch (e) {
      DebugConfig.log('AuthService: Login error: $e');
      if (e.toString().contains('TimeoutException')) {
        return {
          'success': false,
          'message':
              'Connection timeout. Please check your internet connection.'
        };
      }
      return {
        'success': false,
        'message': 'An unexpected error occurred. Please try again.'
      };
    }
  }

  Future<void> logout() async {
    await _secureStorage.delete(key: 'jwt_token');
    await _secureStorage.delete(key: 'user_details');
  }

  Future<bool> isLoggedIn() async {
    final token = await _secureStorage.read(key: 'jwt_token');
    return token != null && token.isNotEmpty;
  }

  Future<Map<String, dynamic>?> getUserDetails() async {
    final userDetailsString = await _secureStorage.read(key: 'user_details');
    DebugConfig.log(
        'AuthService: Retrieved stored user details: $userDetailsString');
    if (userDetailsString != null) {
      try {
        return jsonDecode(userDetailsString);
      } catch (e) {
        DebugConfig.log('AuthService: Error decoding user details: $e');
        return null;
      }
    }
    return null;
  }

  Future<Map<String, String>> getAuthHeaders() async {
    String? authToken = await token;
    if (authToken != null) {
      return {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
      };
    } else {
      return {'Content-Type': 'application/json'};
    }
  }
}
