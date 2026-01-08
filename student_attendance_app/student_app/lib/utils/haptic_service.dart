import 'package:flutter/services.dart';

class HapticService {
  static Future<void> lightImpact() async {
    await HapticFeedback.lightImpact();
  }

  static Future<void> mediumImpact() async {
    await HapticFeedback.mediumImpact();
  }

  static Future<void> heavyImpact() async {
    await HapticFeedback.heavyImpact();
  }

  static Future<void> selectionClick() async {
    await HapticFeedback.selectionClick();
  }
  
  // Semantic haptic feedback methods
  static Future<void> successImpact() async {
    await HapticFeedback.lightImpact();
    // Add a slight delay and another light impact for success feel
    await Future.delayed(const Duration(milliseconds: 50));
    await HapticFeedback.lightImpact();
  }
  
  static Future<void> errorImpact() async {
    await HapticFeedback.heavyImpact();
  }
  
  static Future<void> warningImpact() async {
    await HapticFeedback.mediumImpact();
  }
}
