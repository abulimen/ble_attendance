import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../models/data_models.dart';
import '../services/auth_service.dart';
import '../services/profile_image_service.dart';

class AuthProvider with ChangeNotifier {
  final AuthService _authService = AuthService();
  final ProfileImageService _profileImageService = ProfileImageService();
  Student? _currentUser;
  bool _isAuthenticated = false;
  bool _isLoading = false;
  String? _errorMessage;

  Student? get currentUser => _currentUser;
  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  AuthProvider() {
    _tryAutoLogin();
  }

  Future<void> _tryAutoLogin() async {
    _isLoading = true;
    notifyListeners();
    final loggedIn = await _authService.isLoggedIn();
    if (loggedIn) {
      // Try to restore user details
      final userDetails = await _authService.getUserDetails();
      if (userDetails != null) {
        _currentUser = Student.fromJson(userDetails);
        await _cacheProfileImageForStudent(_currentUser!);
        _isAuthenticated = true;
      } else {
        // Token exists but no user details?
        // For now, we'll consider this an invalid session to be safe,
        // or we could try to fetch profile from API if an endpoint existed.
        // Let's force logout to ensure clean state.
        await _authService.logout();
        _isAuthenticated = false;
      }
    } else {
      _isAuthenticated = false;
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<bool> login(String username, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final result = await _authService.login(username, password);

    if (result != null &&
        result['success'] == true &&
        result['student_details'] != null) {
      _currentUser = Student.fromJson(result['student_details']);
      await _cacheProfileImageForStudent(_currentUser!);
      _isAuthenticated = true;
      _isLoading = false;
      notifyListeners();
      return true;
    } else {
      _errorMessage = result?['message'] ?? 'Login failed.';
      _isAuthenticated = false;
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    _isLoading = true;
    notifyListeners();

    // Clear WebView cookies and local storage
    try {
      final WebViewCookieManager cookieManager = WebViewCookieManager();
      await cookieManager.clearCookies();

      // Clear local storage by running JavaScript
      // This will be handled when WebView is reinitialized
    } catch (e) {
      debugPrint('Error clearing WebView cookies: $e');
    }

    await _authService.logout();
    await _profileImageService.clearCachedProfileImage();
    _currentUser = null;
    _isAuthenticated = false;
    _isLoading = false;
    notifyListeners();
  }

  Future<void> _cacheProfileImageForStudent(Student student) async {
    final String? imageUrl = student.profileImageUrl;
    if (imageUrl == null || imageUrl.isEmpty) return;
    try {
      await _profileImageService.downloadAndCacheProfileImage(imageUrl);
    } catch (e) {
      debugPrint('AuthProvider: Failed to cache profile image -> $e');
    }
  }
}
