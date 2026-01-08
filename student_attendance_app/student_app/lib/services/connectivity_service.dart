import 'package:student_app/theme/app_theme.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';

class ConnectivityService {
  static Stream<ConnectivityResult> get connectivityStream =>
      Connectivity().onConnectivityChanged;

  static Future<bool> hasInternetConnection() async {
    final result = await Connectivity().checkConnectivity();
    return result != ConnectivityResult.none;
  }

  static void showOfflineSnackBar(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Row(
          children: [
            Icon(Icons.wifi_off, color: Colors.white),
            SizedBox(width: 8),
            Text('No internet connection'),
          ],
        ),
        backgroundColor: TrueSignTheme.warning,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}
