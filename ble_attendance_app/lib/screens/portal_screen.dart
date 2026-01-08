import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'dart:ui';
import 'package:ble_attendance_app/theme/app_theme.dart';
import '../widgets/custom_error_widget.dart';

class PortalScreen extends StatefulWidget {
  const PortalScreen({super.key});

  @override
  State<PortalScreen> createState() => _PortalScreenState();
}

class _PortalScreenState extends State<PortalScreen>
    with AutomaticKeepAliveClientMixin {
  late final WebViewController _controller;
  bool _isLoading = true;
  bool _hasError = false;
  String _errorMessage = '';
  double _loadingProgress = 0.0;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _initWebView();
  }

  void _initWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
              _hasError = false;
              _loadingProgress = 0.2;
            });
          },
          onProgress: (int progress) {
            setState(() {
              _loadingProgress = progress / 100;
            });
          },
          onPageFinished: (String url) {
            HapticFeedback.lightImpact();
            setState(() {
              _isLoading = false;
              _loadingProgress = 1.0;
            });
          },
          onWebResourceError: (WebResourceError error) {
            setState(() {
              _isLoading = false;
              _hasError = true;
              _errorMessage = 'Error: ${error.description}';
            });
          },
        ),
      )
      ..loadRequest(Uri.parse('https://ble.xpansieve.com.ng/course_rep'));
  }

  void _refreshWebView() {
    HapticFeedback.lightImpact();
    setState(() {
      _isLoading = true;
      _hasError = false;
      _loadingProgress = 0.0;
    });
    _controller.reload();
  }

  void _goBack() async {
    HapticFeedback.lightImpact();
    if (await _controller.canGoBack()) {
      _controller.goBack();
    }
  }

  void _goForward() async {
    HapticFeedback.lightImpact();
    if (await _controller.canGoForward()) {
      _controller.goForward();
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      backgroundColor: TrueSignTheme.background,
      body: Stack(
        children: [
          // WebView Content
          if (!_hasError)
            Padding(
              // Add padding to avoid bottom navbar overlap (approx 80-100px)
              padding: const EdgeInsets.only(top: 100, bottom: 100),
              child: WebViewWidget(controller: _controller),
            ),

          if (_hasError)
            Positioned.fill(
              child: CustomErrorWidget(
                errorMessage: _errorMessage,
                onRetry: _refreshWebView,
              ),
            ),

          // Glass Header
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: ClipRRect(
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
                child: Container(
                  padding: EdgeInsets.only(
                    top: MediaQuery.of(context).padding.top + 12,
                    bottom: 12,
                    left: 16,
                    right: 16,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.8),
                    border: Border(
                      bottom: BorderSide(
                        color: Colors.black.withValues(alpha: 0.05),
                      ),
                    ),
                  ),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Align(
                              alignment: Alignment.centerLeft,
                              child: Image.asset(
                                'images/logo-dark.png',
                                height: 32,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ),
                          // Navigation Controls
                          _buildNavButton(
                            icon: Icons.arrow_back_ios_rounded,
                            onTap: _goBack,
                            tooltip: 'Back',
                          ),
                          const SizedBox(width: 8),
                          _buildNavButton(
                            icon: Icons.arrow_forward_ios_rounded,
                            onTap: _goForward,
                            tooltip: 'Forward',
                          ),
                          const SizedBox(width: 8),
                          _buildNavButton(
                            icon: Icons.refresh_rounded,
                            onTap: _refreshWebView,
                            tooltip: 'Refresh',
                            isPrimary: true,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // Progress Indicator
          if (_isLoading && !_hasError)
            Positioned(
              top: MediaQuery.of(context).padding.top + 60,
              left: 0,
              right: 0,
              child: LinearProgressIndicator(
                value: _loadingProgress,
                backgroundColor: Colors.transparent,
                valueColor: const AlwaysStoppedAnimation<Color>(
                  TrueSignTheme.primaryBlue,
                ),
                minHeight: 2,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildNavButton({
    required IconData icon,
    required VoidCallback onTap,
    required String tooltip,
    bool isPrimary = false,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isPrimary
                ? TrueSignTheme.primaryBlue.withValues(alpha: 0.1)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(
            icon,
            size: 20,
            color: isPrimary
                ? TrueSignTheme.primaryBlue
                : TrueSignTheme.textSecondary,
          ),
        ),
      ),
    );
  }
}
