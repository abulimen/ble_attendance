import 'package:student_app/theme/app_theme.dart';
import 'package:flutter/material.dart';



class WebViewProgressOverlay extends StatelessWidget {
  final double progress;
  final String loadingText;

  const WebViewProgressOverlay({
    super.key,
    required this.progress,
    this.loadingText = '',
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black.withValues(alpha: 0.7),
      child: Center(
        child: Container(
          padding: const EdgeInsets.all(32),
          decoration: BoxDecoration(
            color: TrueSignTheme.surface,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.3),
                blurRadius: 20,
                spreadRadius: 5,
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Progress indicator
              SizedBox(
                width: 200,
                child: LinearProgressIndicator(
                  value: progress,
                  backgroundColor: TrueSignTheme.surfaceVariant,
                  valueColor:
                      const AlwaysStoppedAnimation<Color>(TrueSignTheme.primaryBlue),
                  borderRadius: BorderRadius.circular(8),
                  minHeight: 8,
                ),
              ),
              const SizedBox(height: 20),

              // Loading text
              Text(
                loadingText,
                style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                  color: TrueSignTheme.textPrimary,
                  fontWeight: FontWeight.w500,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),

              // Progress percentage
              Text(
                '${(progress * 100).toInt()}%',
                style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                  color: TrueSignTheme.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
