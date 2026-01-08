import "package:flutter/material.dart";
import "package:provider/provider.dart";
import "../providers/auth_provider.dart";
import "login_screen.dart";
import "main_navigation_screen.dart";

class AuthWrapper extends StatefulWidget {
  const AuthWrapper({super.key});

  @override
  State<AuthWrapper> createState() => _AuthWrapperState();
}

class _AuthWrapperState extends State<AuthWrapper> {
  bool _isInitialLoad = true;

  @override
  void initState() {
    super.initState();
    // Listen for authentication state changes to update initial load state
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    
    // If auth check is already complete, mark as not initial load
    if (!authProvider.isLoading) {
      _isInitialLoad = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);

    // Show loading spinner during initial auto-login check
    if (authProvider.isLoading && _isInitialLoad) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    // Once initial load is complete, update the flag
    if (_isInitialLoad && !authProvider.isLoading) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          setState(() {
            _isInitialLoad = false;
          });
        }
      });
    }

    if (authProvider.isAuthenticated) {
      return const MainNavigationScreen();
    } else {
      return const LoginScreen();
    }
  }
}
