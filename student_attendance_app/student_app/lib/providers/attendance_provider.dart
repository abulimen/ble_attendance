import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import '../models/data_models.dart';
import '../services/api_service.dart';
import '../services/ble_service.dart';
import '../services/face_verification_service.dart';
import '../services/profile_image_service.dart';
import 'auth_provider.dart';

import 'package:student_app/config/debug_config.dart';

class AttendanceProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  final BleService _bleService = BleService();
  final AuthProvider _authProvider;
  final FaceVerificationService _faceVerificationService =
      FaceVerificationService();
  final ProfileImageService _profileImageService = ProfileImageService();
  List<AttendanceSession> _activeSessions = [];
  AttendanceSession? _matchedSession;
  String? _statusMessage;
  bool _isLoadingSessions = false;
  final bool _isScanningNearby = false;
  bool _isScanningLocation = false;
  bool _isMarkingAttendance = false;
  String? _locationScanStatusMessage;
  String? _foundLocationIdForSession;
  bool _showNoDevicesFoundToast = false;

  List<AttendanceSession> get activeSessions => _activeSessions;
  AttendanceSession? get matchedSession => _matchedSession;
  String? get statusMessage => _statusMessage;
  bool get isLoadingSessions => _isLoadingSessions;
  bool get isScanningNearby => _isScanningNearby;
  bool get isScanningLocation => _isScanningLocation;
  bool get isMarkingAttendance => _isMarkingAttendance;
  String? get locationScanStatusMessage => _locationScanStatusMessage;
  String? get foundLocationIdForSession =>
      _foundLocationIdForSession; // The location ID that was successfully scanned and matched
  bool get showNoDevicesFoundToast => _showNoDevicesFoundToast;

  StreamSubscription? _locationScanSubscription;
  bool _disposed = false;

  AttendanceProvider(this._authProvider) {
    _locationScanSubscription =
        _bleService.bleScanResultStream.listen(_handleLocationScanResult);
    // If auth status changes (e.g., logout), clear sessions
    _authProvider.addListener(_onAuthChanged);
  }

  void _onAuthChanged() {
    if (!_authProvider.isAuthenticated && !_disposed) {
      clearAttendanceData();
    }
  }

  void _handleLocationScanResult(Map<String, dynamic> scanResult) {
    if (_disposed) return;

    final type = scanResult['type'];
    if (type == 'status') {
      _locationScanStatusMessage = scanResult['message'];
      if (scanResult['message'] == 'Scanning stopped.' ||
          scanResult['message'] == 'Scanning stopped manually.') {
        _isScanningLocation = false;

        // Show no devices found message on timeout
        if (scanResult['timeout'] == true && _matchedSession == null) {
          _locationScanStatusMessage = 'No devices found nearby.';
          _showNoDevicesFoundToast = true;
        }
      }
      notifyListeners();
    } else if (type == 'error') {
      _locationScanStatusMessage = scanResult['message'];
      _isScanningLocation = false;
      notifyListeners();
    } else if (type == 'found') {
      final String foundBleId = scanResult['ble_id'];
      // Check if this foundBleId matches any of our active sessions
      for (var session in _activeSessions) {
        if (session.bleId == foundBleId) {
          _matchedSession = session;
          _foundLocationIdForSession =
              foundBleId; // Store the successfully scanned ID
          _locationScanStatusMessage =
              'Matched session: ${session.courseName} with device ID: $foundBleId';
          _isScanningLocation =
              false; // Stop internal scanning state, actual scan might stop via timeout or manually
          _bleService.stopScan(); // Explicitly stop scanning
          notifyListeners();
          return; // Exit after first match
        }
      }
    }
  }

  Future<void> fetchActiveSessions() async {
    if (_disposed) return;

    if (!_authProvider.isAuthenticated) {
      _statusMessage = "Please login to fetch sessions.";
      notifyListeners();
      return;
    }
    _isLoadingSessions = true;
    _statusMessage = null;
    _activeSessions = [];
    _matchedSession = null;
    _foundLocationIdForSession = null;
    notifyListeners();

    final result = await _apiService.getActiveSessions();

    if (_disposed) return;

    if (result['success'] == true &&
        result['data'] != null &&
        result['data']['active_sessions'] != null) {
      final List<dynamic> sessionsData = result['data']['active_sessions'];
      _activeSessions =
          sessionsData.map((data) => AttendanceSession.fromJson(data)).toList();
      if (_activeSessions.isEmpty) {
        _statusMessage =
            "No active attendance sessions found for you at this time.";
      }
    } else {
      // User-friendly error messages
      final errorMessage =
          result['message'] ?? "Failed to fetch active sessions.";

      if (errorMessage.toLowerCase().contains('network') ||
          errorMessage.toLowerCase().contains('connection') ||
          errorMessage.toLowerCase().contains('internet')) {
        _statusMessage =
            "Unable to connect. Please check your internet connection and try again.";
      } else if (errorMessage.toLowerCase().contains('timeout')) {
        _statusMessage = "Request timed out. Please try again.";
      } else if (errorMessage.toLowerCase().contains('server')) {
        _statusMessage =
            "Server is temporarily unavailable. Please try again later.";
      } else if (errorMessage.toLowerCase().contains('unauthorized') ||
          errorMessage.toLowerCase().contains('token')) {
        _statusMessage = "Session expired. Please log in again.";
      } else {
        _statusMessage = "Unable to load sessions. Please try again.";
      }

      if (result['logout_required'] == true) {
        await _authProvider.logout(); // Force logout if token is invalid
      }
    }
    _isLoadingSessions = false;
    if (!_disposed) {
      notifyListeners();
    }
  }

  Future<void> startLocationScan() async {
    if (_disposed) return;

    if (_activeSessions.isEmpty) {
      _locationScanStatusMessage =
          "No active sessions to scan for. Fetch sessions first.";
      notifyListeners();
      return;
    }
    if (_isScanningLocation) {
      _locationScanStatusMessage = "Scan already in progress.";
      notifyListeners();
      return;
    }

    _isScanningLocation = true;
    _matchedSession = null; // Reset previous match
    _foundLocationIdForSession = null;
    _locationScanStatusMessage = "Starting location scan...";
    notifyListeners();

    List<String> targetDeviceIds = _activeSessions.map((s) => s.bleId).toList();
    await _bleService.startScan(targetDeviceIds);
    // isScanningLocation will be updated by _handleLocationScanResult or timeout from BleService
  }

  Future<void> stopLocationScan() async {
    if (_disposed) return;

    await _bleService.stopScan();
    _isScanningLocation = false;
    _locationScanStatusMessage = "Location scan stopped manually.";
    if (!_disposed) {
      notifyListeners();
    }
  }

  Future<bool> markAttendanceForMatchedSession(File studentPhoto) async {
    DebugConfig.log('Starting attendance marking process...');

    if (_matchedSession == null || _foundLocationIdForSession == null) {
      DebugConfig.log(
          'Mark attendance failed: No matched session or device ID');
      _statusMessage = "No matched session or device ID to mark attendance.";
      notifyListeners();
      return false;
    }
    if (!_authProvider.isAuthenticated) {
      DebugConfig.log('Mark attendance failed: User not authenticated');
      _statusMessage = "Please login to mark attendance.";
      notifyListeners();
      return false;
    }

    _isMarkingAttendance = true;
    _statusMessage = null;
    notifyListeners();

    final student = _authProvider.currentUser;
    if (student == null) {
      DebugConfig.log('Mark attendance failed: Current user is null');
      _statusMessage = "Unable to verify student information.";
      _isMarkingAttendance = false;
      notifyListeners();
      return false;
    }

    final File? referenceImage =
        await _profileImageService.ensureProfileImage(student.profileImageUrl);
    if (referenceImage == null) {
      DebugConfig.log(
          'Mark attendance failed: Could not retrieve profile image');
      _statusMessage =
          "Your profile photo is missing. Please re-login or contact support.";
      _isMarkingAttendance = false;
      notifyListeners();
      return false;
    }

    DebugConfig.log('Verifying face...');
    final verificationResult = await _faceVerificationService.verify(
      liveImage: studentPhoto,
      referenceImage: referenceImage,
    );

    if (!verificationResult.isMatch) {
      DebugConfig.log(
          'Face verification failed: ${verificationResult.message}');
      _statusMessage = verificationResult.message;
      _isMarkingAttendance = false;
      notifyListeners();
      return false;
    }

    DebugConfig.log('Face verified. Sending attendance request to API...');
    final result = await _apiService.markAttendance(
      _matchedSession!.sessionId,
      _matchedSession!.courseId,
      _matchedSession!.groupId,
      _foundLocationIdForSession!, // Use the actual scanned location/device ID
      studentPhoto, // Include the captured photo
    );

    if (result['success'] == true) {
      DebugConfig.log('Attendance marked successfully!');
      _statusMessage =
          result['data']?['message'] ?? "Attendance marked successfully!";
      // Refresh active sessions as this one should now be gone
      await fetchActiveSessions();
      _matchedSession = null; // Clear matched session after marking
      _foundLocationIdForSession = null;
      _isMarkingAttendance = false;
      notifyListeners();
      return true;
    } else {
      DebugConfig.log('API failed to mark attendance: ${result['message']}');
      _statusMessage = result['message'] ?? "Failed to mark attendance.";
      if (result['logout_required'] == true) {
        await _authProvider.logout();
      }
      _isMarkingAttendance = false;
      notifyListeners();
      return false;
    }
  }

  void clearAttendanceData() {
    _activeSessions = [];
    _matchedSession = null;
    _statusMessage = null;
    _foundLocationIdForSession = null;
    _locationScanStatusMessage = null;
    _showNoDevicesFoundToast = false;
    if (_isScanningLocation) {
      stopLocationScan(); // Stop scanning if active
    }
    if (!_disposed) {
      notifyListeners();
    }
  }

  void clearNoDevicesFoundToast() {
    _showNoDevicesFoundToast = false;
    notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _locationScanSubscription?.cancel();
    _bleService.dispose(); // Dispose BleService resources
    _authProvider.removeListener(_onAuthChanged); // Clean up listener
    _faceVerificationService.dispose();
    super.dispose();
  }
}
