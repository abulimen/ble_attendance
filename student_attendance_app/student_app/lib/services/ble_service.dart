import 'dart:async';
import 'dart:io' show Platform;
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'package:permission_handler/permission_handler.dart';

class BleService {
  StreamSubscription<List<ScanResult>>? _scanSubscription;
  final StreamController<Map<String, dynamic>> _bleScanResultController =
      StreamController.broadcast();
  Stream<Map<String, dynamic>> get bleScanResultStream =>
      _bleScanResultController.stream;

  bool _isScanning = false;
  bool get isScanning => _isScanning;

  Future<bool> _requestPermissions() async {
    Map<Permission, PermissionStatus> statuses = {};
    if (Platform.isAndroid) {
      statuses = await [
        Permission.location,
        Permission.bluetoothScan,
        Permission.bluetoothConnect,
        Permission.bluetoothAdvertise,
      ].request();
    } else if (Platform.isIOS) {
      statuses = await [
        Permission.bluetooth,
      ].request();
    }

    bool allGranted = true;
    statuses.forEach((permission, status) {
      if (!status.isGranted) {
        print('${permission.toString()} not granted: $status');
        allGranted = false;
      }
    });
    return allGranted;
  }

  Future<bool> _checkBluetoothEnabled() async {
    if (await FlutterBluePlus.isSupported == false) {
      return false;
    }

    final adapterState = await FlutterBluePlus.adapterState.first;
    return adapterState == BluetoothAdapterState.on;
  }

  Future<void> startScan(List<String> targetBleIds) async {
    if (_isScanning) {
      print('Scan already in progress.');
      return;
    }

    bool permissionsGranted = await _requestPermissions();
    if (!permissionsGranted) {
      _bleScanResultController.add({
        'type': 'error',
        'message': 'Bluetooth/Location permissions not granted.'
      });
      return;
    }

    if (await FlutterBluePlus.isSupported == false) {
      print("Bluetooth not supported by this device");
      _bleScanResultController.add({
        'type': 'error',
        'message': 'Bluetooth not supported by this device.'
      });
      return;
    }

    // Check if Bluetooth is enabled
    bool bluetoothEnabled = await _checkBluetoothEnabled();
    if (!bluetoothEnabled) {
      _bleScanResultController.add({
        'type': 'error',
        'message': 'Please turn on Bluetooth to scan for devices.'
      });
      _isScanning = false;
      return;
    }

    _isScanning = true;
    _bleScanResultController
        .add({'type': 'status', 'message': 'Scanning started...'});

    // Clear previous results if any specific handling is needed or rely on onScanResults behavior
    // FlutterBluePlus.scanResults is a BehaviorSubject, it holds the last emitted value.
    // FlutterBluePlus.onScanResults is a stream of *delta* scan results since the last emit.

    _scanSubscription = FlutterBluePlus.onScanResults.listen((results) {
      if (results.isNotEmpty) {
        for (ScanResult r in results) {
          // The BLE ID "XA-BLE_*********************" needs to be found in advertisement data.
          // It could be in: r.advertisementData.advName, r.advertisementData.serviceData, or r.advertisementData.manufacturerData
          // For this example, let's assume it's in serviceData under a specific UUID, or in the advertised name.

          String advertisedName = r.advertisementData.advName;
          // Guid specificServiceUuid = Guid("YOUR_SERVICE_UUID_HERE"); // Replace with actual UUID if used

          String? foundBleId;

          // Attempt 1: Check advertised name
          if (advertisedName.startsWith("XA-BLE_")) {
            foundBleId = advertisedName;
          }

          // Attempt 2: Check service data (more robust if you control the peripheral)
          // This requires knowing the service UUID under which the ID is advertised.
          // For now, we'll keep it simple. If you have a specific service UUID, uncomment and adapt.
          /*
            if (serviceData.containsKey(specificServiceUuid)) {
              List<int>? dataBytes = serviceData[specificServiceUuid];
              if (dataBytes != null) {
                try {
                  String potentialId = String.fromCharCodes(dataBytes);
                  if (potentialId.startsWith("XA-BLE_")) {
                    foundBleId = potentialId;
                  }
                } catch (e) {
                  print('Error decoding service data: $e');
                }
              }
            }
            */

          if (foundBleId != null && targetBleIds.contains(foundBleId)) {
            print(
                'Target BLE ID found: $foundBleId, Device: ${r.device.remoteId}');
            _bleScanResultController.add({
              'type': 'found',
              'ble_id': foundBleId,
              'device_name': advertisedName,
              'device_id': r.device.remoteId.toString(),
              // You might want to pass the full ScanResult or specific session details if matched here
            });
            // Optionally stop scan once a target is found, or continue for others
            // stopScan();
          } else if (foundBleId != null) {
            //  print('Non-target BLE ID found: $foundBleId');
          }
        }
      }
    }, onError: (e) {
      print('Scan error: $e');
      _bleScanResultController
          .add({'type': 'error', 'message': 'Scan error: $e'});
      _isScanning = false;
    }, onDone: () {
      print('Scan stream closed.');
      _isScanning = false; // Should be managed by stopScan or timeout
    });

    FlutterBluePlus.cancelWhenScanComplete(_scanSubscription!);

    // Start scanning with 20 second timeout
    await FlutterBluePlus.startScan(timeout: const Duration(seconds: 20));

    // Monitor scanning state and handle timeout
    FlutterBluePlus.isScanning.listen((isScanningStatus) {
      _isScanning = isScanningStatus;
      if (!isScanningStatus) {
        _bleScanResultController.add({
          'type': 'status',
          'message': 'Scanning stopped.',
          'timeout': true
        });
        print("Scanning stopped by timeout or manually.");
      }
    });
  }

  Future<void> stopScan() async {
    if (!_isScanning) return;
    if (FlutterBluePlus.isScanningNow) {
      await FlutterBluePlus.stopScan();
      print('Scan stopped manually.');
    }
    await _scanSubscription?.cancel();
    _scanSubscription = null;
    _isScanning = false;
    if (!_bleScanResultController.isClosed) {
      _bleScanResultController
          .add({'type': 'status', 'message': 'Scanning stopped manually.'});
    }
  }

  void dispose() {
    stopScan(); // Ensure scan is stopped
    if (!_bleScanResultController.isClosed) {
      _bleScanResultController.close();
    }
  }
}
