import 'package:student_app/theme/app_theme.dart';
import 'package:flutter/material.dart';

class CustomCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;

  const CustomCard({
    super.key,
    required this.child,
    this.padding,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: padding ?? const EdgeInsets.all(TrueSignTheme.spaceMD),
        decoration: BoxDecoration(
          color: TrueSignTheme.surface,
          borderRadius: BorderRadius.circular(TrueSignTheme.radiusLG),
          boxShadow: TrueSignTheme.shadowSM,
        ),
        child: child,
      ),
    );
  }
}
