import 'package:student_app/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:student_app/providers/portal_webview_provider.dart';
import 'package:student_app/utils/haptic_service.dart';

class StudentPortalWebView extends StatefulWidget {
  const StudentPortalWebView({super.key});

  @override
  State<StudentPortalWebView> createState() => _StudentPortalWebViewState();
}

class _StudentPortalWebViewState extends State<StudentPortalWebView>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  Future<void> _pullToRefresh(PortalWebViewProvider provider) async {
    HapticService.lightImpact();
    await provider.refresh();
  }

  Widget _buildErrorState(PortalWebViewProvider provider) {
    return Container(
      color: TrueSignTheme.background,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: TrueSignTheme.error.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.wifi_off_rounded,
                  size: 48,
                  color: TrueSignTheme.error,
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Connection Error',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: TrueSignTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Unable to load the student portal.\nPlease check your internet connection.',
                style: TextStyle(
                  fontSize: 15,
                  color: TrueSignTheme.textSecondary,
                  height: 1.5,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              ElevatedButton.icon(
                onPressed: () {
                  HapticService.mediumImpact();
                  _pullToRefresh(provider);
                },
                icon: const Icon(Icons.refresh_rounded, size: 20),
                label: const Text('Try Again'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: TrueSignTheme.primaryBlue,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 2,
                  textStyle: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Consumer<PortalWebViewProvider>(
      builder: (context, portalProvider, _) {
        return Scaffold(
          backgroundColor: TrueSignTheme.background,
          appBar: AppBar(
            elevation: 0,
            backgroundColor: Colors.white,
            shadowColor: TrueSignTheme.primaryBlue.withValues(alpha: 0.1),
            surfaceTintColor: Colors.white,
            title: const Text(
              'Student Portal',
              style: TextStyle(
                color: TrueSignTheme.textPrimary,
                fontWeight: FontWeight.w600,
                fontSize: 20,
              ),
            ),
            bottom: portalProvider.isLoading && !portalProvider.hasError
                ? PreferredSize(
                    preferredSize: const Size.fromHeight(2),
                    child: LinearProgressIndicator(
                      value: portalProvider.progress > 0
                          ? portalProvider.progress
                          : null,
                      backgroundColor:
                          TrueSignTheme.primaryBlue.withValues(alpha: 0.1),
                      valueColor: const AlwaysStoppedAnimation<Color>(
                          TrueSignTheme.primaryBlue),
                      minHeight: 2,
                    ),
                  )
                : null,
            actions: [
              IconButton(
                icon: const Icon(Icons.refresh_rounded,
                    color: TrueSignTheme.textPrimary),
                onPressed: () => _pullToRefresh(portalProvider),
              ),
            ],
          ),
          body: Stack(
            children: [
              if (!portalProvider.hasError)
                RefreshIndicator(
                  onRefresh: () => _pullToRefresh(portalProvider),
                  color: TrueSignTheme.primaryBlue,
                  child: WebViewWidget(controller: portalProvider.controller),
                ),
              if (portalProvider.hasError) _buildErrorState(portalProvider),
            ],
          ),
        );
      },
    );
  }
}
