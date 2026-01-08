import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:student_app/services/connectivity_service.dart';

enum WebViewState {
  loading,
  loaded,
  error,
  networkError,
  timeout,
}

class PortalWebViewService {
  static const String portalUrl = 'https://ble.xpansieve.com.ng/student';
  static const Duration _timeoutDuration = Duration(seconds: 30);

  late WebViewController _controller;

  // Stream controllers for state management
  final StreamController<WebViewState> _stateController =
      StreamController<WebViewState>.broadcast();
  final StreamController<double> _progressController =
      StreamController<double>.broadcast();
  final StreamController<String> _urlController =
      StreamController<String>.broadcast();

  Timer? _timeoutTimer;
  WebViewState _latestState = WebViewState.loading;
  double _latestProgress = 0.0;
  String? _latestUrl;

  // Getters for streams
  Stream<WebViewState> get stateStream => _stateController.stream;
  Stream<double> get progressStream => _progressController.stream;
  Stream<String> get urlStream => _urlController.stream;

  WebViewState get latestState => _latestState;
  double get latestProgress => _latestProgress;
  String? get latestUrl => _latestUrl;

  WebViewController createController() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..enableZoom(false)
      ..setBackgroundColor(const Color(0xFFFFFFFF))
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: _onPageStarted,
          onPageFinished: _onPageFinished,
          onProgress: _onProgress,
          onWebResourceError: _onWebResourceError,
          onNavigationRequest: _onNavigationRequest,
          onHttpError: _onHttpError,
        ),
      );

    return _controller;
  }

  Future<void> loadPortal() async {
    // Check connectivity first
    final isConnected = await ConnectivityService.hasInternetConnection();
    if (kDebugMode) {
      print(
          '[WebView] loadPortal: connectivity = $isConnected, url = $portalUrl');
    }
    if (!isConnected) {
      _latestState = WebViewState.networkError;
      _stateController.add(WebViewState.networkError);
      if (kDebugMode) {
        print('[WebView] loadPortal: networkError');
      }
      return;
    }

    _startTimeoutTimer();
    _latestState = WebViewState.loading;
    _stateController.add(WebViewState.loading);

    try {
      await _controller.loadRequest(Uri.parse(portalUrl));
    } catch (e) {
      if (kDebugMode) {
        print('[WebView] Error loading portal: $e');
      }
      _latestState = WebViewState.error;
      _stateController.add(WebViewState.error);
    }
  }

  Future<void> reload() async {
    final isConnected = await ConnectivityService.hasInternetConnection();
    if (kDebugMode) {
      print('[WebView] reload: connectivity = $isConnected');
    }
    if (!isConnected) {
      _latestState = WebViewState.networkError;
      _stateController.add(WebViewState.networkError);
      return;
    }

    _startTimeoutTimer();
    _latestState = WebViewState.loading;
    _stateController.add(WebViewState.loading);

    try {
      await _controller.reload();
    } catch (e) {
      if (kDebugMode) {
        print('[WebView] Error reloading portal: $e');
      }
      _stateController.add(WebViewState.error);
    }
  }

  Future<bool> canGoBack() async {
    return await _controller.canGoBack();
  }

  Future<bool> canGoForward() async {
    return await _controller.canGoForward();
  }

  Future<void> goBack() async {
    if (await canGoBack()) {
      await _controller.goBack();
    }
  }

  Future<void> goForward() async {
    if (await canGoForward()) {
      await _controller.goForward();
    }
  }

  Future<String?> getCurrentUrl() async {
    return await _controller.currentUrl();
  }

  void _onPageStarted(String url) {
    _startTimeoutTimer();
    _emitState(WebViewState.loading);
    _emitUrl(url);
    _emitProgress(0.0);
    if (kDebugMode) {
      print('[WebView] onPageStarted: $url');
    }
  }

  void _onPageFinished(String url) {
    _cancelTimeoutTimer();
    _emitState(WebViewState.loaded);
    _emitUrl(url);
    _emitProgress(1.0);
    if (kDebugMode) {
      print('[WebView] onPageFinished: $url');
    }
  }

  void _onProgress(int progress) {
    _emitProgress(progress / 100.0);
    if (kDebugMode) {
      print('[WebView] onProgress: $progress%');
    }
  }

  void _onWebResourceError(WebResourceError error) {
    _cancelTimeoutTimer();

    if (kDebugMode) {
      print(
          '[WebView] Resource Error: code=${error.errorCode}, description=${error.description}');
    }

    // Determine error type based on error code
    if (error.errorCode == -2 || // NAME_NOT_RESOLVED
        error.errorCode == -6 || // CONNECTION_REFUSED
        error.errorCode == -7) {
      // CONNECTION_TIMED_OUT
      _emitState(WebViewState.networkError);
    } else {
      _emitState(WebViewState.error);
    }
  }

  void _onHttpError(HttpResponseError error) {
    _cancelTimeoutTimer();
    // Many sites emit subresource 4xx/5xx while the main page loads.
    // Do not switch to an error state here; rely on onPageFinished/onWebResourceError.
    if (kDebugMode) {
      // Avoid accessing fields that may differ across versions; log the object.
      print('[WebView] HTTP Error: $error');
    }
  }

  NavigationDecision _onNavigationRequest(NavigationRequest request) {
    // Allow navigation to the portal domain and subdomains
    final uri = Uri.parse(request.url);
    if (uri.host.contains('xpansieve.com.ng') ||
        uri.host.contains('localhost') ||
        uri.scheme == 'https') {
      return NavigationDecision.navigate;
    }

    // Block navigation to external domains for security
    return NavigationDecision.prevent;
  }

  void _startTimeoutTimer() {
    _cancelTimeoutTimer();
    _timeoutTimer = Timer(_timeoutDuration, () {
      _emitState(WebViewState.timeout);
      if (kDebugMode) {
        print('[WebView] Timeout after ${_timeoutDuration.inSeconds}s');
      }
    });
  }

  void _emitState(WebViewState state) {
    _latestState = state;
    if (!_stateController.isClosed) {
      _stateController.add(state);
    }
  }

  void _emitProgress(double progress) {
    _latestProgress = progress;
    if (!_progressController.isClosed) {
      _progressController.add(progress);
    }
  }

  void _emitUrl(String url) {
    _latestUrl = url;
    if (!_urlController.isClosed) {
      _urlController.add(url);
    }
  }

  void _cancelTimeoutTimer() {
    _timeoutTimer?.cancel();
    _timeoutTimer = null;
  }

  void dispose() {
    _cancelTimeoutTimer();
    _stateController.close();
    _progressController.close();
    _urlController.close();
  }
}
