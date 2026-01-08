import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:ble_attendance_app/widgets/permission_dialog.dart';

class PermissionService {
  static Future<bool> requestCameraPermission(BuildContext context) async {
    final status = await Permission.camera.request();
    if (status.isDenied || status.isPermanentlyDenied) {
      if (context.mounted) {
        _showPermissionDialog(
          context,
          'Camera',
          'Camera access is required to take a selfie for attendance verification. Please enable it in settings.',
        );
      }
    }
    return status.isGranted;
  }

  static Future<bool> requestBluetoothPermissions(BuildContext context) async {
    final permissions = [
      Permission.bluetooth,
      Permission.bluetoothScan,
      Permission
          .bluetoothAdvertise, // Added advertise permission for broadcaster
      Permission.bluetoothConnect,
      Permission.location,
    ];

    final statuses = await permissions.request();
    final anyDenied = statuses.values
        .any((status) => status.isDenied || status.isPermanentlyDenied);

    if (anyDenied) {
      if (context.mounted) {
        _showPermissionDialog(
          context,
          'Bluetooth & Location',
          'Bluetooth and Location access are required to broadcast class sessions. Please enable them in settings.',
        );
      }
    }
    return statuses.values.every((status) => status.isGranted);
  }

  static Future<bool> checkBluetoothAndLocationEnabled(
      BuildContext context) async {
    // Note: Bluetooth adapter state is checked by BleService

    // Check Location Service
    final locationStatus = await Permission.location.serviceStatus;
    if (!locationStatus.isEnabled) {
      if (context.mounted) {
        _showPermissionDialog(
          context,
          'Location',
          'Location services are disabled. Please enable them to broadcast class sessions.',
        );
      }
      return false;
    }

    return true;
  }

  static void _showPermissionDialog(
      BuildContext context, String permissionName, String content) {
    showDialog(
      context: context,
      builder: (context) => PermissionDialog(
        permissionName: permissionName,
        content: content,
      ),
    );
  }
}
