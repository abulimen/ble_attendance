import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:student_app/theme/app_theme.dart';

class PermissionDialog extends StatelessWidget {
  final String permissionName;
  final String? content;

  const PermissionDialog({
    super.key,
    required this.permissionName,
    this.content,
  });

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: Row(
        children: [
          const Icon(Icons.settings_rounded, color: TrueSignTheme.primaryBlue),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              '$permissionName Required',
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: TrueSignTheme.textPrimary,
              ),
            ),
          ),
        ],
      ),
      content: Text(
        content ??
            'This app needs the $permissionName permission to function properly. Please grant the permission in the app settings.',
        style: const TextStyle(
          fontSize: 15,
          color: TrueSignTheme.textSecondary,
          height: 1.5,
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text(
            'Cancel',
            style: TextStyle(color: TrueSignTheme.textSecondary),
          ),
        ),
        ElevatedButton(
          onPressed: () {
            openAppSettings();
            Navigator.of(context).pop();
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: TrueSignTheme.primaryBlue,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          child: const Text('Open Settings'),
        ),
      ],
    );
  }
}
