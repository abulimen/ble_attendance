# BLE Attendance App - Developer Documentation

## Overview
This document provides technical information about the BLE Attendance App implementation, focusing on the Bluetooth Low Energy (BLE) functionality used for the attendance feature.

## BLE Implementation

### Plugin Used
The app uses the `bluetooth_low_energy` plugin which supports both Peripheral (broadcasting) and Central (scanning) modes on iOS and Android. This plugin was chosen because:

1. It supports both iOS and Android platforms
2. It provides Peripheral mode functionality needed for the attendance feature
3. It has comprehensive API for BLE operations

### BleService Class
The `BleService` class in `lib/services/ble_service.dart` handles all BLE-related functionality:

```dart
class BleService extends ChangeNotifier {
  // Properties and getters
  bool _isAdvertising = false;
  DateTime? _startTime;
  Timer? _timer;
  String _duration = '00:00:00';
  String _statusMessage = 'Ready to broadcast';
  final PeripheralManager _peripheralManager = PeripheralManager();
  
  bool get isAdvertising => _isAdvertising;
  String get duration => _duration;
  String get statusMessage => _statusMessage;
  
  // Methods
  Future<void> startAdvertising() async { ... }
  Future<void> stopAdvertising() async { ... }
  // ...
}
```

### Key Components

1. **PeripheralManager**: Initializes as `PeripheralManager()` (not with `.instance`)
   ```dart
   final PeripheralManager _peripheralManager = PeripheralManager();
   ```

2. **State Change Listener**: Uses the correct event args type
   ```dart
   _peripheralManager.stateChanged.listen((BluetoothLowEnergyStateChangedEventArgs args) {
     if (args.state != BluetoothLowEnergyState.poweredOn) {
       // Handle state change
     }
   });
   ```

3. **Service and Characteristic Creation**: Uses the correct class names and formats
   ```dart
   final service = GATTService(
     uuid: serviceUuid,
     characteristics: [
       GATTCharacteristic(
         uuid: characteristicUuid,
         properties: GATTCharacteristicProperties(
           read: true,
           write: true,
           notify: true,
         ),
         permissions: GATTCharacteristicPermissions(
           read: true,
           write: true,
         ),
         value: Uint8List.fromList('ATTENDANCE'.codeUnits),
       ),
     ],
   );
   ```

4. **Advertising**: Uses the `Advertisement` class (not `AdvertiseData`)
   ```dart
   await _peripheralManager.startAdvertising(
     Advertisement(
       serviceUuids: [serviceUuid],
       localName: 'BLE Attendance',
     ),
   );
   ```

## Required Imports
```dart
import 'package:flutter/material.dart';
import 'package:bluetooth_low_energy/bluetooth_low_energy.dart';
import 'dart:async';
import 'dart:typed_data';  // Required for Uint8List
```

## Platform-Specific Considerations

### Android
- Minimum SDK version must be 21 or higher
- Requires location permissions for BLE scanning

### iOS
- Requires NSBluetoothAlwaysUsageDescription in Info.plist
- Limited advertising capabilities compared to Android

## Troubleshooting

### Common Issues
1. **Bluetooth not turning on**: Ensure the device has Bluetooth capabilities and it's enabled
2. **Advertising not starting**: Check if Bluetooth is in powered-on state
3. **Permissions issues**: Verify that all required permissions are granted

### Debugging Tips
- Use the `statusMessage` property to track the current state of BLE operations
- Monitor the `isAdvertising` property to confirm if broadcasting is active
- Check the timer functionality with the `duration` property
