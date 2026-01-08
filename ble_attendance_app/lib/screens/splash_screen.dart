import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:ble_attendance_app/screens/home_screen.dart';
import 'package:ble_attendance_app/theme/app_theme.dart';

class AppColors extends TrueSignTheme {}

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _navigateToHome();
  }

  _navigateToHome() async {
    await Future.delayed(const Duration(milliseconds: 3500), () {});
    if (mounted) {
      Navigator.pushReplacement(
          context, MaterialPageRoute(builder: (context) => const HomeScreen()));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor:
          const Color(0xFF091C3E), // Dark navy background matching favicon
      body: Column(
        children: [
          Expanded(
            child: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Favicon logo with animation
                  Image.asset(
                    'images/favicon.png',
                    width: 140,
                    height: 140,
                  ).animate().fadeIn(duration: 600.ms).scale(
                        begin: const Offset(0.5, 0.5),
                        end: const Offset(1.0, 1.0),
                        duration: 600.ms,
                        curve: Curves.easeOutBack,
                      ),
                  const SizedBox(height: 32),
                  // App name
                  const Text(
                    'TrueSign',
                    style: TextStyle(
                      fontSize: 42,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                      letterSpacing: -1.0,
                    ),
                  ).animate().fadeIn(delay: 300.ms, duration: 600.ms).slideY(
                        begin: 0.3,
                        end: 0,
                        duration: 600.ms,
                        curve: Curves.easeOut,
                      ),
                  const SizedBox(height: 8),
                  // Tagline
                  Text(
                    'Class Rep Dashboard',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.white.withValues(alpha: 0.7),
                      fontWeight: FontWeight.w400,
                      letterSpacing: 0.5,
                    ),
                  ).animate().fadeIn(delay: 600.ms, duration: 600.ms).slideY(
                        begin: 0.3,
                        end: 0,
                        duration: 600.ms,
                        curve: Curves.easeOut,
                      ),
                ],
              ),
            ),
          ),
          // Loading indicator at bottom
          SizedBox(
            height: 120,
            child: Center(
              child: SizedBox(
                width: 160,
                child: LinearProgressIndicator(
                  minHeight: 4,
                  backgroundColor: Colors.white.withValues(alpha: 0.1),
                  valueColor: AlwaysStoppedAnimation<Color>(
                    TrueSignTheme.accentGold,
                  ),
                  borderRadius: BorderRadius.circular(2),
                ),
              )
                  .animate(onPlay: (controller) => controller.repeat())
                  .fadeIn(delay: 900.ms, duration: 400.ms),
            ),
          ),
        ],
      ),
    );
  }
}
