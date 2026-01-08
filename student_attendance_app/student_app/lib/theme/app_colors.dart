import 'package:flutter/material.dart';

class AppColors {
  // Primary Branding – Blue family
  static const Color primary = Color(0xFF003366); // --blue
  static const Color primaryLight = Color(0xFF0047AB); // --blue-light
  static const Color primaryDark = Color(0xFF001F3F); // --blue-dark
  static const Color primaryHover = Color(0xFF004080); // --blue-hover
  static const Color primaryContainer =
      Color(0xFFE5E7EB); // soft neutral backing

  // Accent – Gold reserved for high-value UI
  static const Color gold = Color(0xFFB98D3B);
  static const Color goldLight = Color(0xFFB98D3B);
  static const Color goldDark = Color(0xFFB8860B);

  // Neutral palette
  static const Color white = Color(0xFFFFFFFF);
  static const Color gray50 = Color(0xFFF9FAFB);
  static const Color gray100 = Color(0xFFF3F4F6);
  static const Color gray200 = Color(0xFFE5E7EB);
  static const Color gray300 = Color(0xFFD1D5DB);
  static const Color gray400 = Color(0xFF9CA3AF);
  static const Color gray500 = Color(0xFF6B7280);
  static const Color gray600 = Color(0xFF4B5563);
  static const Color gray700 = Color(0xFF374151);
  static const Color gray800 = Color(0xFF1F2937);
  static const Color gray900 = Color(0xFF111827);

  // Surface + background assignments derived from neutrals
  static const Color surface = white;
  static const Color surfaceVariant = gray100;
  static const Color surfaceContainer = gray50;
  static const Color background = gray50;

  // Text colors
  static const Color onPrimary = white;
  static const Color onSurface = gray900;
  static const Color onSurfaceVariant = gray500;
  static const Color onBackground = gray900;

  // Semantic colors
  static const Color success = Color(0xFF10B981);
  static const Color successLight = Color(0xFFD1FAE5);
  static const Color warning = Color(0xFFF59E0B);
  static const Color warningLight = Color(0xFFFEF3C7);
  static const Color error = Color(0xFFEF4444);
  static const Color errorLight = Color(0xFFFEE2E2);
  static const Color info = Color(0xFF3B82F6);
  static const Color infoLight = Color(0xFFDBEAFE);

  // Misc / interactive tokens
  static const Color outline = gray300;
  static const Color outlineVariant = gray200;
  static const Color shadow = Color(0x1A000000);
  static const Color overlay = Color(0x80000000);

  // Legacy semantic aliases for backwards compatibility
  static const Color successContainer = successLight;
  static const Color warningContainer = warningLight;
  static const Color errorContainer = errorLight;
  static const Color infoContainer = infoLight;

  // Bluetooth status colors mapped to standard palette
  static const Color bluetoothConnected = success;
  static const Color bluetoothScanning = info;
  static const Color bluetoothDisconnected = gray500;
  static const Color bluetoothError = error;
}
