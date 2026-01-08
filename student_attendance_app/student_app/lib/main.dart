import "package:flutter/material.dart";
import "package:provider/provider.dart";
import "package:student_app/theme/app_theme.dart";
import "providers/auth_provider.dart";
import "providers/attendance_provider.dart";
import 'providers/portal_webview_provider.dart';
import "screens/splash_screen.dart";

void main() {
  WidgetsFlutterBinding
      .ensureInitialized(); // Important for plugins like flutter_secure_storage
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        // AttendanceProvider depends on AuthProvider, so it should be created after or receive AuthProvider
        ChangeNotifierProxyProvider<AuthProvider, AttendanceProvider>(
          create: (context) => AttendanceProvider(
              Provider.of<AuthProvider>(context, listen: false)),
          update: (context, authProvider, previousAttendanceProvider) =>
              AttendanceProvider(
                  authProvider), // Or manage updates more granularly if needed
        ),
        ChangeNotifierProvider(create: (_) => PortalWebViewProvider()),
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: "TrueSign (Student)",
        theme: TrueSignTheme.lightTheme,
        home: const SplashScreen(),
      ),
    );
  }
}
