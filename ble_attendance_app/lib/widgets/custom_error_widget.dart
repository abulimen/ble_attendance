import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:ble_attendance_app/theme/app_theme.dart';

class CustomErrorWidget extends StatefulWidget {
  final String errorMessage;
  final VoidCallback onRetry;

  const CustomErrorWidget({
    super.key,
    required this.errorMessage,
    required this.onRetry,
  });

  @override
  State<CustomErrorWidget> createState() => _CustomErrorWidgetState();
}

class _CustomErrorWidgetState extends State<CustomErrorWidget>
    with TickerProviderStateMixin {
  late AnimationController _bounceController;
  late AnimationController _scaleController;
  late Animation<double> _bounceAnimation;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();
    _bounceController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    _scaleController = AnimationController(
      duration: const Duration(milliseconds: 150),
      vsync: this,
    );

    _bounceAnimation = Tween<double>(
      begin: 0.0,
      end: 1.0,
    ).animate(CurvedAnimation(
      parent: _bounceController,
      curve: Curves.elasticOut,
    ));

    _scaleAnimation = Tween<double>(
      begin: 1.0,
      end: 0.95,
    ).animate(CurvedAnimation(
      parent: _scaleController,
      curve: Curves.easeInOut,
    ));

    _bounceController.forward();
  }

  @override
  void dispose() {
    _bounceController.dispose();
    _scaleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: TrueSignTheme.background,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32.0),
          child: AnimatedBuilder(
            animation: _bounceAnimation,
            builder: (context, child) {
              return Transform.scale(
                scale: _bounceAnimation.value,
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 320),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(TrueSignTheme.radiusXL),
                    boxShadow: TrueSignTheme.shadowMD,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(32.0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Error Icon with gradient background
                        Container(
                          width: 80,
                          height: 80,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                TrueSignTheme.error,
                                TrueSignTheme.error.withValues(alpha: 0.8),
                              ],
                            ),
                            borderRadius: BorderRadius.circular(40),
                            boxShadow: [
                              BoxShadow(
                                color:
                                    TrueSignTheme.error.withValues(alpha: 0.3),
                                blurRadius: 16,
                                spreadRadius: 0,
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.wifi_off_rounded,
                            size: 40,
                            color: Colors.white,
                          ),
                        ),

                        const SizedBox(height: 24),

                        Text(
                          'Connection Failed',
                          style:
                              Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.bold,
                                    color: TrueSignTheme.textPrimary,
                                  ),
                          textAlign: TextAlign.center,
                        ),

                        const SizedBox(height: 12),

                        Text(
                          'Unable to load the page. Please check your internet connection and try again.',
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: TrueSignTheme.textSecondary,
                                    height: 1.4,
                                  ),
                          textAlign: TextAlign.center,
                        ),

                        const SizedBox(height: 32),

                        // Retry Button with animation
                        GestureDetector(
                          onTapDown: (_) {
                            HapticFeedback.lightImpact();
                            _scaleController.forward();
                          },
                          onTapUp: (_) {
                            _scaleController.reverse();
                          },
                          onTapCancel: () {
                            _scaleController.reverse();
                          },
                          child: AnimatedBuilder(
                            animation: _scaleAnimation,
                            builder: (context, child) {
                              return Transform.scale(
                                scale: _scaleAnimation.value,
                                child: Container(
                                  width: double.infinity,
                                  height: 56,
                                  decoration: BoxDecoration(
                                    gradient: const LinearGradient(
                                      begin: Alignment.topLeft,
                                      end: Alignment.bottomRight,
                                      colors: [
                                        TrueSignTheme.primaryBlue,
                                        TrueSignTheme.primaryBlueDark,
                                      ],
                                    ),
                                    borderRadius: BorderRadius.circular(
                                        TrueSignTheme.radiusMD),
                                    boxShadow: [
                                      BoxShadow(
                                        color: TrueSignTheme.primaryBlue
                                            .withValues(alpha: 0.3),
                                        blurRadius: 12,
                                        offset: const Offset(0, 6),
                                        spreadRadius: 0,
                                      ),
                                    ],
                                  ),
                                  child: Material(
                                    color: Colors.transparent,
                                    child: InkWell(
                                      onTap: () {
                                        HapticFeedback.mediumImpact();
                                        widget.onRetry();
                                      },
                                      borderRadius: BorderRadius.circular(
                                          TrueSignTheme.radiusMD),
                                      child: const Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        children: [
                                          Icon(
                                            Icons.refresh_rounded,
                                            color: Colors.white,
                                            size: 24,
                                          ),
                                          SizedBox(width: 12),
                                          Text(
                                            'Try Again',
                                            style: TextStyle(
                                              color: Colors.white,
                                              fontSize: 16,
                                              fontWeight: FontWeight.w600,
                                              letterSpacing: 0.5,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
