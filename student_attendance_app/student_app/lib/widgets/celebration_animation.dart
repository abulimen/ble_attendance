import 'package:student_app/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:student_app/theme/app_colors.dart';
import 'package:student_app/utils/haptic_service.dart';

class CelebrationAnimation extends StatefulWidget {
  final String? message;
  final VoidCallback? onComplete;
  final Duration duration;
  final CelebrationType type;
  final bool playHaptic;

  const CelebrationAnimation({
    super.key,
    this.message,
    this.onComplete,
    this.duration = const Duration(milliseconds: 3000),
    this.type = CelebrationType.success,
    this.playHaptic = true,
  });

  @override
  State<CelebrationAnimation> createState() => _CelebrationAnimationState();
}

enum CelebrationType {
  success,
  achievement,
  attendance,
  milestone,
}

class _CelebrationAnimationState extends State<CelebrationAnimation>
    with TickerProviderStateMixin {
  late AnimationController _mainController;
  late AnimationController _confettiController;
  late AnimationController _pulseController;

  @override
  void initState() {
    super.initState();

    _mainController = AnimationController(
      duration: widget.duration,
      vsync: this,
    );

    _confettiController = AnimationController(
      duration: const Duration(milliseconds: 2000),
      vsync: this,
    );

    _pulseController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );

    _startAnimation();
  }

  void _startAnimation() async {
    if (widget.playHaptic) {
      HapticService.successImpact();
    }

    _mainController.forward();
    _confettiController.forward();
    _pulseController.repeat(reverse: true);

    await Future.delayed(widget.duration);
    widget.onComplete?.call();
  }

  @override
  void dispose() {
    _mainController.dispose();
    _confettiController.dispose();
    _pulseController.dispose();
    super.dispose();
  }

  Color get _primaryColor {
    switch (widget.type) {
      case CelebrationType.success:
        return TrueSignTheme.success;
      case CelebrationType.achievement:
        return TrueSignTheme.warning;
      case CelebrationType.attendance:
        return TrueSignTheme.secondaryCyan;
      case CelebrationType.milestone:
        return TrueSignTheme.warning;
    }
  }

  IconData get _iconData {
    switch (widget.type) {
      case CelebrationType.success:
        return Icons.check_circle_rounded;
      case CelebrationType.achievement:
        return Icons.emoji_events_rounded;
      case CelebrationType.attendance:
        return Icons.how_to_reg_rounded;
      case CelebrationType.milestone:
        return Icons.celebration_rounded;
    }
  }

  String get _defaultMessage {
    switch (widget.type) {
      case CelebrationType.success:
        return 'Success!';
      case CelebrationType.achievement:
        return 'Achievement Unlocked!';
      case CelebrationType.attendance:
        return 'Attendance Marked!';
      case CelebrationType.milestone:
        return 'Milestone Reached!';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Confetti particles
          ...List.generate(12, (index) {
            final angle = (index * 30.0) * (3.14159 / 180);
            final distance = 80.0 + (index % 3) * 20.0;

            return AnimatedBuilder(
              animation: _confettiController,
              builder: (context, child) {
                final progress = _confettiController.value;
                final x = distance * progress * (index.isEven ? 1 : -1) * 0.5;
                final y = -distance * progress + (progress * progress * 50);

                return Transform.translate(
                  offset: Offset(
                    x * (index.isEven ? 1 : -1),
                    y,
                  ),
                  child: Transform.rotate(
                    angle: angle * progress,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: [
                          TrueSignTheme.primaryBlue,
                          AppColors.gold,
                          Colors.white,
                          TrueSignTheme.secondaryCyan,
                        ][index % 4],
                      ),
                    )
                        .animate()
                        .fadeIn(duration: 200.ms)
                        .then(delay: 1500.ms)
                        .fadeOut(duration: 300.ms),
                  ),
                );
              },
            );
          }),

          // Main celebration icon
          AnimatedBuilder(
            animation: _pulseController,
            builder: (context, child) {
              return Transform.scale(
                scale: 1.0 + (_pulseController.value * 0.2),
                child: Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        _primaryColor.withValues(alpha: 0.9),
                        _primaryColor.withValues(alpha: 0.5),
                        _primaryColor.withValues(alpha: 0.1),
                      ],
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: _primaryColor.withValues(alpha: 0.4),
                        blurRadius: 24,
                        spreadRadius: 6,
                      ),
                    ],
                  ),
                  child: Icon(
                    _iconData,
                    size: 60,
                    color: Colors.white,
                  ),
                ),
              );
            },
          )
              .animate()
              .scale(duration: 600.ms, curve: Curves.elasticOut)
              .fadeIn(duration: 400.ms),

          const SizedBox(height: 24),

          // Celebration message
          Text(
            widget.message ?? _defaultMessage,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: _primaryColor,
                  fontWeight: FontWeight.bold,
                ),
            textAlign: TextAlign.center,
          )
              .animate()
              .slideY(
                  begin: 30, end: 0, duration: 800.ms, curve: Curves.easeOut)
              .fadeIn(duration: 600.ms, delay: 300.ms),

          const SizedBox(height: 16),

          // Animated sparkle dots
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              return Container(
                margin: const EdgeInsets.symmetric(horizontal: 4),
                child: Icon(
                  Icons.auto_awesome_rounded,
                  size: 16,
                  color: _primaryColor,
                ),
              )
                  .animate(
                      onPlay: (controller) => controller.repeat(reverse: true))
                  .scale(
                    begin: const Offset(0.5, 0.5),
                    end: const Offset(1.0, 1.0),
                    delay: Duration(milliseconds: index * 100),
                    duration: 800.ms,
                    curve: Curves.easeInOut,
                  )
                  .fadeIn(
                    delay: Duration(milliseconds: index * 100 + 400),
                    duration: 400.ms,
                  );
            }),
          ),
        ],
      ),
    );
  }
}
