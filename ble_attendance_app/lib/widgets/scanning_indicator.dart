import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:ble_attendance_app/theme/app_theme.dart';

class ScanningIndicator extends StatefulWidget {
  final String? message;
  final VoidCallback? onStop;
  final bool showStopButton;

  const ScanningIndicator({
    super.key,
    this.message,
    this.onStop,
    this.showStopButton = true,
  });

  @override
  State<ScanningIndicator> createState() => _ScanningIndicatorState();
}

class _ScanningIndicatorState extends State<ScanningIndicator> {
  Timer? _hapticTimer;

  @override
  void initState() {
    super.initState();
    // Periodic haptic feedback to simulate "scanning"
    _hapticTimer = Timer.periodic(const Duration(milliseconds: 1200), (timer) {
      HapticFeedback.selectionClick();
    });
  }

  @override
  void dispose() {
    _hapticTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          height: 160,
          width: 160,
          child: Stack(
            alignment: Alignment.center,
            children: [
              // Ripple 1
              Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: TrueSignTheme.accentGold.withValues(alpha: 0.3),
                    width: 2,
                  ),
                ),
              )
                  .animate(onPlay: (controller) => controller.repeat())
                  .scale(
                      duration: 2000.ms,
                      begin: const Offset(0.5, 0.5),
                      end: const Offset(1.2, 1.2))
                  .fadeOut(duration: 2000.ms, curve: Curves.easeOut),

              // Ripple 2 (Delayed)
              Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: TrueSignTheme.primaryBlue.withValues(alpha: 0.3),
                    width: 2,
                  ),
                ),
              )
                  .animate(onPlay: (controller) => controller.repeat())
                  .scale(
                    delay: 600.ms,
                    duration: 2000.ms,
                    begin: const Offset(0.5, 0.5),
                    end: const Offset(1.2, 1.2),
                  )
                  .fadeOut(
                      delay: 600.ms, duration: 2000.ms, curve: Curves.easeOut),

              // Central Pulse
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: TrueSignTheme.primaryBlue.withValues(alpha: 0.2),
                      blurRadius: 20,
                      spreadRadius: 5,
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.sensors_rounded,
                  color: TrueSignTheme.primaryBlue,
                  size: 40,
                ),
              )
                  .animate(
                      onPlay: (controller) => controller.repeat(reverse: true))
                  .scale(
                    duration: 1000.ms,
                    begin: const Offset(1.0, 1.0),
                    end: const Offset(1.1, 1.1),
                    curve: Curves.easeInOut,
                  ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        Text(
          widget.message ?? 'Searching for class signals...',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w500,
            letterSpacing: 0.5,
          ),
          textAlign: TextAlign.center,
        ).animate(onPlay: (controller) => controller.repeat()).shimmer(
            duration: 2000.ms, color: Colors.white.withValues(alpha: 0.5)),
        if (widget.showStopButton && widget.onStop != null) ...[
          const SizedBox(height: 24),
          OutlinedButton(
            onPressed: () {
              HapticFeedback.mediumImpact();
              widget.onStop?.call();
            },
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              side: BorderSide(color: Colors.white.withValues(alpha: 0.5)),
              padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(30),
              ),
            ),
            child: const Text("Stop Search"),
          ),
        ],
      ],
    );
  }
}
