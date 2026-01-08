import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:ble_attendance_app/theme/app_theme.dart';
import 'package:shimmer/shimmer.dart';

class SlideAction extends StatefulWidget {
  final String text;
  final Future<void> Function() onSubmit;
  final Color outerColor;
  final Color innerColor;
  final double height;
  final double sliderButtonIconSize;
  final double borderRadius;
  final TextStyle? textStyle;
  final bool enabled;

  const SlideAction({
    super.key,
    required this.text,
    required this.onSubmit,
    this.outerColor = Colors.white,
    this.innerColor = TrueSignTheme.primaryBlue,
    this.height = 64,
    this.sliderButtonIconSize = 24,
    this.borderRadius = 50,
    this.textStyle,
    this.enabled = true,
  });

  @override
  State<SlideAction> createState() => _SlideActionState();
}

class _SlideActionState extends State<SlideAction>
    with SingleTickerProviderStateMixin {
  double _position = 0;
  bool _submitted = false;
  late AnimationController _shimmerController;

  @override
  void initState() {
    super.initState();
    _shimmerController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat();
  }

  @override
  void dispose() {
    _shimmerController.dispose();
    super.dispose();
  }

  void _onDragUpdate(DragUpdateDetails details, double maxWidth) {
    if (!widget.enabled || _submitted) return;

    setState(() {
      _position =
          (_position + details.delta.dx).clamp(0.0, maxWidth - widget.height);
    });

    // Haptic feedback during drag
    if (_position % 10 < 2) {
      HapticFeedback.selectionClick();
      if (_position % 40 < 5) {
        HapticFeedback.vibrate();
      }
    }
  }

  void _onDragEnd(DragEndDetails details, double maxWidth) {
    if (!widget.enabled || _submitted) return;

    if (_position > (maxWidth - widget.height) * 0.8) {
      // Threshold reached
      setState(() {
        _position = maxWidth - widget.height;
        _submitted = true;
      });
      HapticFeedback.heavyImpact();
      widget.onSubmit().then((_) {
        if (mounted) {
          setState(() {
            _submitted = false;
            _position = 0;
          });
        }
      });
    } else {
      // Reset
      setState(() {
        _position = 0;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return Container(
          height: widget.height,
          decoration: BoxDecoration(
            color: widget.outerColor,
            borderRadius: BorderRadius.circular(widget.borderRadius),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Stack(
            children: [
              // Shimmer Text
              Center(
                child: Shimmer.fromColors(
                  baseColor: widget.innerColor.withValues(alpha: 0.5),
                  highlightColor: widget.innerColor,
                  child: Text(
                    widget.text,
                    style: widget.textStyle ??
                        TextStyle(
                          color: widget.innerColor,
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 1,
                        ),
                  ),
                ),
              ),

              // Slider Button
              Positioned(
                left: _position,
                child: GestureDetector(
                  onHorizontalDragUpdate: (details) =>
                      _onDragUpdate(details, constraints.maxWidth),
                  onHorizontalDragEnd: (details) =>
                      _onDragEnd(details, constraints.maxWidth),
                  child: Container(
                    height: widget.height,
                    width: widget.height,
                    decoration: BoxDecoration(
                      color: widget.innerColor,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: widget.innerColor.withValues(alpha: 0.3),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: _submitted
                        ? const Center(
                            child: SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            ),
                          )
                        : Icon(
                            Icons.arrow_forward_rounded,
                            color: Colors.white,
                            size: widget.sliderButtonIconSize,
                          ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
