import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:student_app/screens/auth_wrapper.dart';
import 'package:student_app/theme/app_theme.dart';

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
      Navigator.pushReplacement(context,
          MaterialPageRoute(builder: (context) => const AuthWrapper()));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
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
                    width: 120,
                    height: 120,
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
                      color: TrueSignTheme.primaryBlue,
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
                    'Smart Attendance Verification',
                    style: TextStyle(
                      fontSize: 16,
                      color: TrueSignTheme.textSecondary.withValues(alpha: 0.7),
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
                width: 160, // Wider for linear bar
                child: LinearProgressIndicator(
                  minHeight: 4,
                  backgroundColor:
                      TrueSignTheme.primaryBlue.withValues(alpha: 0.1),
                  valueColor: const AlwaysStoppedAnimation<Color>(
                    TrueSignTheme.primaryBlue,
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
