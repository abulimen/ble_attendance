import 'package:flutter/material.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:student_app/theme/app_theme.dart';

class FaceDetectionOverlay extends CustomPainter {
  final List<Face> faces;
  final Size imageSize;

  FaceDetectionOverlay({required this.faces, required this.imageSize});

  @override
  void paint(Canvas canvas, Size size) {
    // Modern TrueSign blue with glow effect
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.0
      ..color = TrueSignTheme.primaryBlue;

    // Glow effect
    final glowPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6.0
      ..color = TrueSignTheme.primaryBlue.withValues(alpha: 0.3)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4);

    for (final face in faces) {
      final rect = _scaleRect(
        rect: face.boundingBox,
        imageSize: imageSize,
        widgetSize: size,
      );

      // Draw glow first
      canvas.drawRRect(
        RRect.fromRectAndRadius(rect, const Radius.circular(16)),
        glowPaint,
      );

      // Draw main border
      canvas.drawRRect(
        RRect.fromRectAndRadius(rect, const Radius.circular(16)),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(FaceDetectionOverlay oldDelegate) {
    return oldDelegate.faces != faces || oldDelegate.imageSize != imageSize;
  }

  Rect _scaleRect({
    required Rect rect,
    required Size imageSize,
    required Size widgetSize,
  }) {
    final double scaleX = widgetSize.width / imageSize.width;
    final double scaleY = widgetSize.height / imageSize.height;

    return Rect.fromLTRB(
      rect.left * scaleX,
      rect.top * scaleY,
      rect.right * scaleX,
      rect.bottom * scaleY,
    );
  }
}
