import 'dart:io';
import 'package:flutter/material.dart';
import 'package:bluetooth_low_energy/bluetooth_low_energy.dart';

/// iOS-specific extensions for the BleService class
extension IOSBleExtensions on PeripheralManager {
  /// Handles iOS-specific Bluetooth state restoration
  Future<bool> handleIOSStateRestoration() async {
    if (!Platform.isIOS) return true;
    
    try {
      // On iOS, sometimes the Bluetooth state needs to be refreshed
      // This is a workaround for the "unauthorized" state that persists
      // even when Bluetooth permissions are granted
      final currentState = state;
      
      if (currentState == BluetoothLowEnergyState.unauthorized) {
        // Try to show settings to prompt the user to enable permissions
        await showAppSettings();
        return false;
      } else if (currentState == BluetoothLowEnergyState.poweredOff) {
        // On iOS, we can't programmatically turn on Bluetooth
        // We need to guide the user to do it manually
        return false;
      }
      
      return true;
    } catch (e) {
      debugPrint('Error in iOS state restoration: $e');
      return false;
    }
  }
  
  /// Optimizes advertising parameters specifically for iOS
  Advertisement getIOSOptimizedAdvertisement(UUID serviceUUID, String name) {
    // On iOS, the advertising parameters need to be handled differently
    // The name parameter works differently on iOS vs Android
    if (Platform.isIOS) {
      return Advertisement(
        serviceUUIDs: [serviceUUID],
        // On iOS, the local name might not be displayed in all scanning apps
        // so we ensure it's set correctly
        name: name,
        // iOS doesn't support manufacturer data in the same way as Android
        // so we don't include it for iOS
      );
    } else {
      // Return regular advertisement for other platforms
      return Advertisement(
        serviceUUIDs: [serviceUUID],
        name: name,
      );
    }
  }
  
  /// Checks if the current iOS version supports all required Bluetooth features
  bool isIOSVersionSupported() {
    if (!Platform.isIOS) return true;
    
    // Parse iOS version string
    // This is a simplified check - in a real app you might want to use
    // a package like device_info_plus to get more detailed information
    final versionString = Platform.operatingSystemVersion;
    final versionRegex = RegExp(r'(\d+)\.(\d+)');
    final match = versionRegex.firstMatch(versionString);
    
    if (match != null) {
      final majorVersion = int.parse(match.group(1)!);
      final minorVersion = int.parse(match.group(2)!);
      
      // iOS 13.0+ has full support for Bluetooth LE peripheral mode
      if (majorVersion >= 13) {
        return true;
      } else if (majorVersion == 12) {
        // iOS 12.x has some limitations
        return minorVersion >= 0;
      }
      return false;
    }
    
    // If we can't determine the version, assume it's supported
    // to avoid blocking functionality unnecessarily
    return true;
  }
}
