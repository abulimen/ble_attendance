import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AppAnimations {
  // Duration constants
  static const Duration fast = Duration(milliseconds: 150);
  static const Duration medium = Duration(milliseconds: 300);
  static const Duration slow = Duration(milliseconds: 500);

  // Curves
  static const Curve defaultCurve = Curves.easeOutCubic;
  static const Curve bounceCurve = Curves.bounceOut;
  static const Curve elasticCurve = Curves.elasticOut;

  // Scale animation for buttons
  static Widget scaleOnTap({
    required Widget child,
    required VoidCallback onTap,
    double scale = 0.95,
  }) {
    return _ScaleOnTap(
      onTap: onTap,
      scale: scale,
      child: child,
    );
  }

  // Fade and slide animation
  static Widget fadeSlide({
    required Widget child,
    Duration duration = medium,
    Offset beginOffset = const Offset(0, 0.2),
    Curve curve = defaultCurve,
  }) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: duration,
      curve: curve,
      builder: (context, value, child) {
        return Opacity(
          opacity: value,
          child: Transform.translate(
            offset: Offset(0, beginOffset.dy * (1 - value) * 20),
            child: child,
          ),
        );
      },
      child: child,
    );
  }

  // Pulse animation for scanning states
  static Widget pulse({
    required Widget child,
    bool isActive = false,
    Duration duration = const Duration(seconds: 2),
  }) {
    if (!isActive) return child;

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 1.0, end: 1.1),
      duration: duration,
      curve: Curves.easeInOut,
      builder: (context, value, child) {
        return Transform.scale(
          scale: value,
          child: child,
        );
      },
      child: child,
    );
  }

  // Staggered list animation
  static List<Widget> staggeredList(List<Widget> children,
      {Duration delay = medium}) {
    return List.generate(children.length, (index) {
      return TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: 1),
        duration: medium,
        curve: defaultCurve,
        child: children[index],
        builder: (context, value, child) {
          return Opacity(
            opacity: value,
            child: Transform.translate(
              offset: Offset(0, (1 - value) * 20 * (index + 1) * 0.1),
              child: child,
            ),
          );
        },
      );
    });
  }
}

class _ScaleOnTap extends StatefulWidget {
  final Widget child;
  final VoidCallback onTap;
  final double scale;

  const _ScaleOnTap({
    required this.child,
    required this.onTap,
    required this.scale,
  });

  @override
  State<_ScaleOnTap> createState() => _ScaleOnTapState();
}

class _ScaleOnTapState extends State<_ScaleOnTap>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: AppAnimations.fast,
      vsync: this,
    );
    _scaleAnimation = Tween<double>(begin: 1.0, end: widget.scale)
        .animate(CurvedAnimation(parent: _controller, curve: Curves.easeOut));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => _controller.forward(),
      onTapUp: (_) {
        _controller.reverse();
        widget.onTap();
      },
      onTapCancel: () => _controller.reverse(),
      child: ScaleTransition(
        scale: _scaleAnimation,
        child: widget.child,
      ),
    );
  }
}

// Haptic feedback helper
class HapticHelper {
  static void light() {
    HapticFeedback.lightImpact();
  }

  static void medium() {
    HapticFeedback.mediumImpact();
  }

  static void heavy() {
    HapticFeedback.heavyImpact();
  }

  static void success() {
    HapticFeedback.vibrate();
  }
}
