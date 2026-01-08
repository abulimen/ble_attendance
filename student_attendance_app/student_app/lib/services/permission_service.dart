import 'package:flutter/material.dart';
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:student_app/widgets/permission_dialog.dart';

class PermissionService {
  static Future<bool> requestCameraPermission(BuildContext context) async {
    final status = await Permission.camera.request();
    if (status.isDenied || status.isPermanentlyDenied) {
      _showPermissionDialog(
        context,
        'Camera',
        'Camera access is required to take a selfie for attendance verification. Please enable it in settings.',
      );
    }
    return status.isGranted;
  }

  static Future<bool> requestBluetoothPermissions(BuildContext context) async {
    final permissions = [
      Permission.bluetooth,
      Permission.bluetoothScan,
      Permission.bluetoothConnect,
      Permission.location,
    ];

    final statuses = await permissions.request();
    final anyDenied = statuses.values
        .any((status) => status.isDenied || status.isPermanentlyDenied);

    if (anyDenied) {
      _showPermissionDialog(
        context,
        'Bluetooth & Location',
        'Bluetooth and Location access are required to detect nearby class sessions. Please enable them in settings.',
      );
    }
    return statuses.values.every((status) => status.isGranted);
  }

  static Future<bool> checkBluetoothAndLocationEnabled(
      BuildContext context) async {
    // Check Bluetooth
    if (await FlutterBluePlus.isSupported == false) {
      _showPermissionDialog(
          context, 'Bluetooth', 'Bluetooth is not supported on this device.');
      return false;
    }

    final bluetoothState = await FlutterBluePlus.adapterState.first;
    if (bluetoothState != BluetoothAdapterState.on) {
      _showPermissionDialog(
        context,
        'Bluetooth',
        'Bluetooth is turned off. Please enable it to scan for class sessions.',
      );
      return false;
    }

    // Check Location Service
    final locationStatus = await Permission.location.serviceStatus;
    if (!locationStatus.isEnabled) {
      _showPermissionDialog(
        context,
        'Location',
        'Location services are disabled. Please enable them to scan for class sessions.',
      );
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
