import 'package:student_app/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:student_app/utils/haptic_service.dart';

enum WebViewErrorType {
  network,
  server,
  timeout,
  notFound,
  general,
}

class WebViewErrorWidget extends StatefulWidget {
  final WebViewErrorType errorType;
  final String? customMessage;
  final VoidCallback onRetry;
  final VoidCallback? onGoBack;
  final bool showRetryButton;
  final bool isRetrying;

  const WebViewErrorWidget({
    super.key,
    this.errorType = WebViewErrorType.general,
    this.customMessage,
    required this.onRetry,
    this.onGoBack,
    this.showRetryButton = true,
    this.isRetrying = false,
  });

  // Legacy constructor for backward compatibility
  const WebViewErrorWidget.legacy({
    Key? key,
    required String message,
    required VoidCallback onRetry,
  }) : this(
          key: key,
          errorType: WebViewErrorType.general,
          customMessage: message,
          onRetry: onRetry,
        );

  @override
  State<WebViewErrorWidget> createState() => _WebViewErrorWidgetState();
}

class _WebViewErrorWidgetState extends State<WebViewErrorWidget>
    with TickerProviderStateMixin {
  late AnimationController _shakeController;
  late AnimationController _pulseController;

  @override
  void initState() {
    super.initState();
    _shakeController = AnimationController(
      duration: const Duration(milliseconds: 500),
      vsync: this,
    );
    _pulseController = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    );
    _pulseController.repeat(reverse: true);
  }

  @override
  void dispose() {
    _shakeController.dispose();
    _pulseController.dispose();
    super.dispose();
  }

  void _handleRetry() {
    HapticService.lightImpact();
    _shakeController.forward().then((_) {
      _shakeController.reset();
      widget.onRetry();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      color: TrueSignTheme.background,
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildErrorIcon(),
              const SizedBox(height: 32),
              _buildErrorTitle(),
              const SizedBox(height: 16),
              _buildErrorMessage(),
              const SizedBox(height: 32),
              _buildActionButtons(),
            ],
          ).animate().fadeIn(duration: 600.ms).slideY(
                begin: 0.3,
                end: 0,
                duration: 600.ms,
                curve: Curves.easeOutCubic,
              ),
        ),
      ),
    );
  }

  Widget _buildErrorIcon() {
    IconData iconData;
    Color iconColor;

    switch (widget.errorType) {
      case WebViewErrorType.network:
        iconData = Icons.wifi_off_rounded;
        iconColor = TrueSignTheme.warning;
        break;
      case WebViewErrorType.server:
        iconData = Icons.dns_rounded;
        iconColor = TrueSignTheme.error;
        break;
      case WebViewErrorType.timeout:
        iconData = Icons.access_time_rounded;
        iconColor = TrueSignTheme.warning;
        break;
      case WebViewErrorType.notFound:
        iconData = Icons.search_off_rounded;
        iconColor = TrueSignTheme.error;
        break;
      case WebViewErrorType.general:
        iconData = Icons.error_outline_rounded;
        iconColor = TrueSignTheme.error;
        break;
    }

    return AnimatedBuilder(
      animation: _shakeController,
      builder: (context, child) {
        final offset = _shakeController.value * 10;
        return Transform.translate(
          offset: Offset(offset * (1 - _shakeController.value), 0),
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: iconColor.withValues(alpha: 0.15),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Icon(
              iconData,
              size: 64,
              color: iconColor,
            ),
          ),
        );
      },
    );
  }

  Widget _buildErrorTitle() {
    String title;

    switch (widget.errorType) {
      case WebViewErrorType.network:
        title = 'No Internet Connection';
        break;
      case WebViewErrorType.server:
        title = 'Server Error';
        break;
      case WebViewErrorType.timeout:
        title = 'Connection Timeout';
        break;
      case WebViewErrorType.notFound:
        title = 'Page Not Found';
        break;
      case WebViewErrorType.general:
        title = 'Something Went Wrong';
        break;
    }

    return Text(
      title,
      style: Theme.of(context).textTheme.headlineMedium!.copyWith(
            color: TrueSignTheme.textPrimary,
            fontWeight: FontWeight.bold,
          ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildErrorMessage() {
    String message;

    if (widget.customMessage != null) {
      message = widget.customMessage!;
    } else {
      switch (widget.errorType) {
        case WebViewErrorType.network:
          message = 'Please check your internet connection and try again.';
          break;
        case WebViewErrorType.server:
          message =
              'The server is experiencing issues. Please try again later.';
          break;
        case WebViewErrorType.timeout:
          message = 'The request took too long to complete. Please try again.';
          break;
        case WebViewErrorType.notFound:
          message = 'The requested page could not be found.';
          break;
        case WebViewErrorType.general:
          message = 'An unexpected error occurred. Please try again.';
          break;
      }
    }

    return Text(
      message,
      style: Theme.of(context).textTheme.bodySmall!.copyWith(
            color: TrueSignTheme.textSecondary.withValues(alpha: 0.7),
          ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildActionButtons() {
    return Column(
      children: [
        if (widget.showRetryButton)
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: widget.isRetrying ? null : _handleRetry,
              icon: widget.isRetrying
                  ? SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(
                          Colors.white.withValues(alpha: 0.7),
                        ),
                      ),
                    )
                  : const Icon(Icons.refresh_rounded),
              label: Text(widget.isRetrying ? 'Retrying...' : 'Try Again'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 32,
                  vertical: 16,
                ),
              ),
            ),
          ),
        if (widget.onGoBack != null) ...<Widget>[
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: widget.onGoBack,
              icon: const Icon(Icons.arrow_back_rounded),
              label: const Text('Go Back'),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 32,
                  vertical: 16,
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}
