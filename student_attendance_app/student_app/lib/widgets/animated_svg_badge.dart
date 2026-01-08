import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_svg/flutter_svg.dart';

class AnimatedSvgBadge extends StatelessWidget {
  final double size;
  final String assetPath;

  const AnimatedSvgBadge({
    super.key,
    this.size = 104,
    this.assetPath = 'assets/icons/attendance_badge.svg',
  });

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      assetPath,
      width: size,
      height: size,
    )
        .animate(onPlay: (controller) => controller.repeat())
        .scaleXY(
          begin: 0.96,
          end: 1.02,
          duration: 1200.ms,
          curve: Curves.easeInOut,
        )
        .then()
        .shake(
          duration: 1600.ms,
          hz: 1,
          offset: const Offset(1.2, 1.2),
        );
  }
}
