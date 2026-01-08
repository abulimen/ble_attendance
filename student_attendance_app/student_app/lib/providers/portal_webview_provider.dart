import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:student_app/services/portal_webview_service.dart';

class PortalWebViewProvider with ChangeNotifier {
  final PortalWebViewService _service = PortalWebViewService();
  late final WebViewController _controller;

  StreamSubscription<WebViewState>? _stateSub;
  StreamSubscription<double>? _progressSub;

  bool _isInitialized = false;
  bool _isLoading = true;
  bool _hasError = false;
  bool _showProgressOverlay = true;
  double _progress = 0.0;

  PortalWebViewProvider() {
    _controller = _service.createController();
    _stateSub =
        _service.stateStream.listen(_handleState, onError: _handleError);
    _progressSub = _service.progressStream.listen((p) {
      _progress = p;
      notifyListeners();
    });
    _isInitialized = true;
    notifyListeners();
    _kickoffPreload();
  }

  WebViewController get controller => _controller;
  bool get isInitialized => _isInitialized;
  bool get isLoading => _isLoading;
  bool get hasError => _hasError;
  bool get showProgressOverlay => _showProgressOverlay;
  double get progress => _progress;
  WebViewState get currentState => _service.latestState;

  void _kickoffPreload() {
    scheduleMicrotask(() async {
      try {
        await _service.loadPortal();
      } catch (error) {
        _handleError(error);
      }
    });
  }

  Future<void> refresh() async {
    await _service.reload();
  }

  Future<void> preload() async {
    await _service.loadPortal();
  }

  void _handleState(WebViewState state) {
    switch (state) {
      case WebViewState.loading:
        _isLoading = true;
        _hasError = false;
        _showProgressOverlay = true;
        break;
      case WebViewState.loaded:
        _isLoading = false;
        _hasError = false;
        _showProgressOverlay = false;
        _progress = 1.0;
        break;
      case WebViewState.error:
      case WebViewState.networkError:
      case WebViewState.timeout:
        _isLoading = false;
        _hasError = true;
        _showProgressOverlay = false;
        break;
    }
    notifyListeners();
  }

  void _handleError(Object error) {
    if (kDebugMode) {
      print('[PortalWebViewProvider] Stream error: $error');
    }
    _isLoading = false;
    _hasError = true;
    _showProgressOverlay = false;
    notifyListeners();
  }

  @override
  void dispose() {
    _stateSub?.cancel();
    _progressSub?.cancel();
    _service.dispose();
    super.dispose();
  }
}
