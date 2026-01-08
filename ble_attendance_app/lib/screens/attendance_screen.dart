import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'dart:ui';

import 'package:ble_attendance_app/services/ble_service.dart';
import 'package:ble_attendance_app/services/permission_service.dart';
import 'package:ble_attendance_app/theme/app_theme.dart';
import 'package:ble_attendance_app/utils/snackbars.dart';
import 'package:ble_attendance_app/widgets/slide_action.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen>
    with TickerProviderStateMixin {
  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;
  late AnimationController _fadeController;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      duration: const Duration(seconds: 3),
      vsync: this,
    );

    _pulseAnimation = Tween<double>(
      begin: 1.0,
      end: 1.5,
    ).animate(CurvedAnimation(
      parent: _pulseController,
      curve: Curves.easeOut,
    ));

    _fadeController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    )..forward();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  void _startPulseAnimation() {
    _pulseController.repeat();
  }

  void _stopPulseAnimation() {
    _pulseController.stop();
    _pulseController.reset();
  }

  Future<void> _handleBroadcastStart(BleService bleService) async {
    // Check permissions first
    final hasPermissions =
        await PermissionService.requestBluetoothPermissions(context);
    if (!hasPermissions) return;

    // Check services
    final servicesEnabled =
        await PermissionService.checkBluetoothAndLocationEnabled(context);
    if (!servicesEnabled) return;

    await bleService.startAdvertising();

    if (bleService.isAdvertising) {
      HapticFeedback.heavyImpact();
      Snackbars.showSuccess(context, 'Session Started');
    } else {
      HapticFeedback.vibrate();
      Snackbars.showError(context, bleService.statusMessage);
    }
  }

  Future<void> _handleBroadcastStop(BleService bleService) async {
    await bleService.stopAdvertising();
    HapticFeedback.heavyImpact();
    Snackbars.showInfo(context, 'Session Ended');
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<BleService>(
      builder: (context, bleService, child) {
        // Manage pulse animation based on broadcasting state
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (bleService.isAdvertising) {
            if (!_pulseController.isAnimating) _startPulseAnimation();
          } else {
            if (_pulseController.isAnimating) _stopPulseAnimation();
          }
        });

        final isAdvertising = bleService.isAdvertising;
        // User requested white background always
        const backgroundColor = TrueSignTheme.background;
        const textColor = TrueSignTheme.textPrimary;

        return Scaffold(
          backgroundColor: backgroundColor,
          body: Stack(
            children: [
              // Background Pulse Effect (Active State) - Updated for white background
              if (isAdvertising)
                Positioned.fill(
                  child: AnimatedBuilder(
                    animation: _pulseAnimation,
                    builder: (context, child) {
                      return Center(
                        child: Container(
                          width: 300 * _pulseAnimation.value,
                          height: 300 * _pulseAnimation.value,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: TrueSignTheme.primaryBlue.withValues(
                                alpha: 0.1 * (1.5 - _pulseAnimation.value)),
                          ),
                        ),
                      );
                    },
                  ),
                ),

              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24.0),
                  child: Column(
                    children: [
                      // Header
                      Padding(
                        padding: const EdgeInsets.only(top: 16.0, bottom: 8.0),
                        child: FadeTransition(
                          opacity: _fadeController,
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Image.asset(
                                'images/logo-dark.png',
                                height: 32,
                                fit: BoxFit.contain,
                              ),
                              _buildStatusChip(isAdvertising),
                            ],
                          ),
                        ),
                      ),

                      // Main Content
                      Expanded(
                        child: FadeTransition(
                          opacity: _fadeController,
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              // Icon / Timer
                              Container(
                                width: 150,
                                height: 150,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: Colors.white,
                                  boxShadow: TrueSignTheme.shadowMD,
                                  border: isAdvertising
                                      ? Border.all(
                                          color: TrueSignTheme.primaryBlue
                                              .withValues(alpha: 0.3),
                                          width: 2,
                                        )
                                      : null,
                                ),
                                child: Center(
                                  child: isAdvertising
                                      ? Column(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            const Icon(
                                              Icons.timer_outlined,
                                              size: 36,
                                              color: TrueSignTheme.primaryBlue,
                                            ),
                                            const SizedBox(height: 8),
                                            Text(
                                              bleService.duration,
                                              style: const TextStyle(
                                                fontSize: 22,
                                                fontWeight: FontWeight.bold,
                                                color:
                                                    TrueSignTheme.primaryBlue,
                                                fontFeatures: [
                                                  FontFeature.tabularFigures()
                                                ],
                                              ),
                                            ),
                                          ],
                                        )
                                      : const Icon(
                                          Icons.wifi_tethering_rounded,
                                          size: 70,
                                          color: TrueSignTheme.primaryBlue,
                                        ),
                                ),
                              ),
                              const SizedBox(height: 32),

                              // Status Text
                              Text(
                                isAdvertising
                                    ? 'Broadcasting Live'
                                    : 'Ready to Broadcast',
                                style: const TextStyle(
                                  fontSize: 26,
                                  fontWeight: FontWeight.bold,
                                  color: textColor,
                                ),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 10),
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 20.0),
                                child: Text(
                                  isAdvertising
                                      ? 'Students can now mark their attendance.\nKeep this screen open.'
                                      : 'Slide below to start a new attendance session for your class.',
                                  style: const TextStyle(
                                    fontSize: 14,
                                    color: TrueSignTheme.textSecondary,
                                    height: 1.5,
                                  ),
                                  textAlign: TextAlign.center,
                                  maxLines: 2,
                                ),
                              ),

                              if (isAdvertising) ...[
                                const SizedBox(height: 28),
                                // Code Display
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 28, vertical: 14),
                                  decoration: BoxDecoration(
                                    color: TrueSignTheme.primaryBlue
                                        .withValues(alpha: 0.05),
                                    borderRadius: BorderRadius.circular(18),
                                    border: Border.all(
                                      color: TrueSignTheme.primaryBlue
                                          .withValues(alpha: 0.1),
                                    ),
                                  ),
                                  child: Column(
                                    children: [
                                      const Text(
                                        'CODE',
                                        style: TextStyle(
                                          color: TrueSignTheme.textSecondary,
                                          fontSize: 11,
                                          fontWeight: FontWeight.bold,
                                          letterSpacing: 2,
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      GestureDetector(
                                        onTap: () {
                                          HapticFeedback.selectionClick();
                                          Clipboard.setData(ClipboardData(
                                              text: bleService.currentBleId));
                                          Snackbars.showSuccess(
                                              context, 'Copied to clipboard');
                                        },
                                        child: Text(
                                          bleService.currentBleId,
                                          style: const TextStyle(
                                            color: TrueSignTheme.primaryBlue,
                                            fontSize: 32,
                                            fontWeight: FontWeight.bold,
                                            letterSpacing: 3,
                                            fontFamily: 'monospace',
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),

                      // Slide Action
                      Padding(
                        padding: EdgeInsets.only(
                          bottom: MediaQuery.of(context).padding.bottom + 80,
                          top: 16.0,
                        ),
                        child: FadeTransition(
                          opacity: _fadeController,
                          child: SlideAction(
                            text: isAdvertising
                                ? 'Slide to Stop'
                                : 'Slide to Broadcast',
                            onSubmit: () => isAdvertising
                                ? _handleBroadcastStop(bleService)
                                : _handleBroadcastStart(bleService),
                            outerColor: Colors.white,
                            innerColor: isAdvertising
                                ? TrueSignTheme.error
                                : TrueSignTheme.primaryBlue,
                            textStyle: TextStyle(
                              color: isAdvertising
                                  ? TrueSignTheme.error
                                  : TrueSignTheme.primaryBlue,
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildStatusChip(bool isAdvertising) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: isAdvertising
            ? TrueSignTheme.success.withValues(alpha: 0.1)
            : TrueSignTheme.surfaceVariant,
        borderRadius: BorderRadius.circular(20),
        border: isAdvertising
            ? Border.all(color: TrueSignTheme.success.withValues(alpha: 0.2))
            : null,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: isAdvertising
                  ? TrueSignTheme.success
                  : TrueSignTheme.textSecondary,
              shape: BoxShape.circle,
              boxShadow: isAdvertising
                  ? [
                      BoxShadow(
                        color: TrueSignTheme.success.withValues(alpha: 0.5),
                        blurRadius: 6,
                        spreadRadius: 1,
                      )
                    ]
                  : null,
            ),
          ),
          const SizedBox(width: 8),
          Text(
            isAdvertising ? 'LIVE' : 'OFFLINE',
            style: TextStyle(
              color: isAdvertising
                  ? TrueSignTheme.success
                  : TrueSignTheme.textSecondary,
              fontSize: 12,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
            ),
          ),
        ],
      ),
    );
  }
}
