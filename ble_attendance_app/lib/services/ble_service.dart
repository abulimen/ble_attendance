import 'dart:io';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:bluetooth_low_energy/bluetooth_low_energy.dart';
import '../utils/ios_ble_extensions.dart';
import 'dart:async';

class BleService extends ChangeNotifier {
  bool _isAdvertising = false;
  DateTime? _startTime;
  Timer? _timer;
  String _duration = '00:00:00';
  String _statusMessage = 'Ready to broadcast';
  String _currentBleId = '';
  final PeripheralManager _peripheralManager = PeripheralManager();
  bool _servicesAdded = false;
  bool _permissionsRequested = false;
  
  bool get isAdvertising => _isAdvertising;
  String get duration => _duration;
  String get statusMessage => _statusMessage;
  String get currentBleId => _currentBleId;
  
  BleService() {
    _initBle();
  }

  @override
  void dispose() {
    stopAdvertising();
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _initBle() async {
    try {
      // Listen to state changes
      _peripheralManager.stateChanged.listen((BluetoothLowEnergyStateChangedEventArgs args) {
        if (args.state != BluetoothLowEnergyState.poweredOn) {
          _statusMessage = 'Bluetooth is ${_getReadableState(args.state)}';
          stopAdvertising();
        } else {
          _statusMessage = 'Ready to broadcast';
        }
        notifyListeners();
      });
      
      // Request permissions on initialization
      await _requestPermissions();
      
      // Check iOS version compatibility
      if (Platform.isIOS && !_peripheralManager.isIOSVersionSupported()) {
        _statusMessage = 'Your iOS version may not fully support Bluetooth LE peripheral mode';
        notifyListeners();
      }
    } catch (e) {
      _statusMessage = 'Error initializing BLE: $e';
      notifyListeners();
    }
  }
  
  // Helper method to convert BluetoothLowEnergyState to readable string
  String _getReadableState(BluetoothLowEnergyState state) {
    switch (state) {
      case BluetoothLowEnergyState.unauthorized:
        return 'not authorized';
      case BluetoothLowEnergyState.poweredOff:
        return 'turned off';
      case BluetoothLowEnergyState.unsupported:
        return 'not supported';
      case BluetoothLowEnergyState.unknown:
        return 'in unknown state';
      case BluetoothLowEnergyState.poweredOn:
        return 'ready';
      default:
        return state.toString().split('.').last;
    }
  }
  
  // Request Bluetooth permissions
  Future<void> _requestPermissions() async {
    if (_permissionsRequested) return;
    
    try {
      // Request permissions using the authorize method
      final authorized = await _peripheralManager.authorize();
      
      if (!authorized) {
        _statusMessage = 'Bluetooth permissions denied';
        notifyListeners();
      } else {
        _permissionsRequested = true;
        
        // Check current state after permissions granted
        final state = _peripheralManager.state;
        if (state != BluetoothLowEnergyState.poweredOn) {
          _statusMessage = 'Wireless connection is ${_getReadableState(state)}';
        } else {
          _statusMessage = 'Ready to broadcast';
        }
        notifyListeners();
      }
      
      // For iOS, handle state restoration if needed
      if (Platform.isIOS) {
        await _peripheralManager.handleIOSStateRestoration();
      }
    } catch (e) {
      // Handle UnsupportedError on platforms where authorize is not available
      _permissionsRequested = true; // Mark as requested to avoid repeated attempts
      
      // Check current state
      final state = _peripheralManager.state;
      if (state != BluetoothLowEnergyState.poweredOn) {
        _statusMessage = 'Bluetooth is ${_getReadableState(state)}';
        notifyListeners();
      }
    }
  }
  
  // Show app settings to allow user to enable permissions
  Future<void> showSettings() async {
    try {
      await _peripheralManager.showAppSettings();
    } catch (e) {
      _statusMessage = 'Error opening settings: $e';
      notifyListeners();
    }
  }

  // Generate a unique BLE ID in the format expected by student app
  String _generateBleId() {
    final random = Random();
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final randomSuffix = List.generate(8, (index) => 
      random.nextInt(36).toRadixString(36).toUpperCase()).join();
    
    // Format: XA-BLE_ + timestamp + random characters (limited to 20 chars total for BLE name limits)
    final bleId = 'XA-BLE_${timestamp.toString().substring(7)}$randomSuffix';
    return bleId.length > 20 ? bleId.substring(0, 20) : bleId;
  }

  Future<void> startAdvertising() async {
    try {
      // Request permissions if not already done
      if (!_permissionsRequested) {
        await _requestPermissions();
      }
      
      // Check if Bluetooth is powered on
      final state = _peripheralManager.state;
      if (state != BluetoothLowEnergyState.poweredOn) {
        _statusMessage = 'Bluetooth is ${_getReadableState(state)}';
        
        // If unauthorized, prompt to open settings
        if (state == BluetoothLowEnergyState.unauthorized) {
          _statusMessage = 'Wireless permissions required. Please enable in settings.';
        }
        
        notifyListeners();
        return;
      }

      // Generate a new unique BLE ID for this session
      _currentBleId = _generateBleId();

      // Create UUIDs using the proper constructor
      final serviceUuid = UUID.fromString('F0001234-0451-4000-B000-000000000000');
      final characteristicUuid = UUID.fromString('F0001235-0451-4000-B000-000000000000');
      
      // Only remove services if they were previously added
      if (_servicesAdded) {
        try {
          await _peripheralManager.stopAdvertising();
        } catch (e) {
          print('Warning when stopping advertising: $e');
        }
        
        try {
          await _peripheralManager.removeAllServices();
        } catch (e) {
          print('Warning when removing services: $e');
        }
      }
      
      // Create a list of characteristic properties using the enum values
      final characteristicProperties = [
        GATTCharacteristicProperty.read,
        GATTCharacteristicProperty.write,
        GATTCharacteristicProperty.notify,
      ];
      
      // Create a list of characteristic permissions using the enum values
      final characteristicPermissions = [
        GATTCharacteristicPermission.read,
        GATTCharacteristicPermission.write,
      ];
      
      // Create the characteristic using the mutable factory constructor
      final characteristic = GATTCharacteristic.mutable(
        uuid: characteristicUuid,
        properties: characteristicProperties,
        permissions: characteristicPermissions,
        descriptors: [], // Empty list of descriptors
      );
      
      // Create the service with all required parameters
      final service = GATTService(
        uuid: serviceUuid,
        isPrimary: true, // Set as primary service
        includedServices: [], // Empty list of included services
        characteristics: [characteristic],
      );
      
      // Add the service
      await _peripheralManager.addService(service);
      _servicesAdded = true;

      // Create advertisement with the generated BLE ID as the advertised name
      final advertisement = Advertisement(
        serviceUUIDs: [serviceUuid],
        name: _currentBleId, // This is what the student app will detect
      );

      // Start advertising with the unique BLE ID
      await _peripheralManager.startAdvertising(advertisement);

      // Start timer
      _startTime = DateTime.now();
      _timer = Timer.periodic(const Duration(seconds: 1), _updateDuration);

      _isAdvertising = true;
      _statusMessage = 'Session active - sharing attendance code';
      notifyListeners();
    } catch (e) {
      // Handle specific exceptions
      if (e.toString().contains('IllegalStateException')) {
        _statusMessage = 'Error: Wireless service not ready. Please try turning wireless off and on again.';
      } else {
        _statusMessage = 'Error starting session: $e';
      }
      
      // Clean up any partial setup
      try {
        await _peripheralManager.stopAdvertising();
      } catch (_) {}
      
      try {
        if (_servicesAdded) {
          await _peripheralManager.removeAllServices();
          _servicesAdded = false;
        }
      } catch (_) {}
      
      _isAdvertising = false;
      _currentBleId = '';
      notifyListeners();
    }
  }

  Future<void> stopAdvertising() async {
    if (!_isAdvertising) {
      return; // Don't try to stop if not advertising
    }
    
    try {
      // Stop the timer first
      _timer?.cancel();
      _timer = null;
      
      // Stop advertising
      try {
        await _peripheralManager.stopAdvertising();
      } catch (e) {
        print('Warning when stopping advertising: $e');
        // Continue execution even if this fails
      }
      
      // Only try to remove services if they were added
      if (_servicesAdded) {
        try {
          await _peripheralManager.removeAllServices();
        } catch (e) {
          print('Warning when removing services: $e');
          // Continue execution even if this fails
        }
      }
      
      _isAdvertising = false;
      _servicesAdded = false;
      _statusMessage = 'Session ended';
      _currentBleId = '';
      notifyListeners();
    } catch (e) {
      _statusMessage = 'Error stopping session: $e';
      notifyListeners();
    }
  }

  void _updateDuration(Timer timer) {
    if (_startTime != null) {
      final now = DateTime.now();
      final difference = now.difference(_startTime!);
      
      final hours = difference.inHours.toString().padLeft(2, '0');
      final minutes = (difference.inMinutes % 60).toString().padLeft(2, '0');
      final seconds = (difference.inSeconds % 60).toString().padLeft(2, '0');
      
      _duration = '$hours:$minutes:$seconds';
      notifyListeners();
    }
  }
}
