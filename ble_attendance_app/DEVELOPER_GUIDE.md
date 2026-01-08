# Flutter BLE App - Developer Guide

This document provides a comprehensive guide for developers working with the BLE Attendance App, including implementation details, architecture decisions, and technical specifications.

## Architecture Overview

The application follows a clean architecture approach with separation of concerns:

- **UI Layer**: Screens and widgets that handle user interaction
- **Service Layer**: Business logic and state management
- **Platform Layer**: Native platform interactions via plugins

### State Management

The app uses Provider for state management, which offers several advantages:
- Efficient rebuilds with granular control
- Simple dependency injection
- Easy testing capabilities

## BLE Implementation Details

### Peripheral Mode Implementation

The BLE broadcasting functionality is implemented using the `bluetooth_low_energy` plugin, which supports both peripheral and central roles across iOS and Android platforms.

```dart
// Creating a BLE service
final serviceUuid = Guid.fromString('00001234-0000-1000-8000-00805F9B34FB');
final characteristicUuid = Guid.fromString('00001235-0000-1000-8000-00805F9B34FB');

// Adding the service
await _peripheralManager.addService(
  GattService(
    uuid: serviceUuid,
    characteristics: [
      GattCharacteristic(
        uuid: characteristicUuid,
        properties: GattCharacteristicProperties(
          read: true,
          write: true,
          notify: true,
        ),
        permissions: GattCharacteristicPermissions(
          read: true,
          write: true,
        ),
        value: Uint8List.fromList('ATTENDANCE'.codeUnits),
      ),
    ],
  ),
);

// Start advertising
await _peripheralManager.startAdvertising(
  AdvertiseData(
    serviceUuids: [serviceUuid],
    localName: 'BLE Attendance',
  ),
);
```

### Platform-Specific Considerations

#### Android
- Requires API level 21+ (Android 5.0 Lollipop)
- Needs location permissions for BLE scanning
- Requires explicit runtime permissions for Android 12+

#### iOS
- Requires NSBluetoothAlwaysUsageDescription in Info.plist
- Limited advertising data capabilities compared to Android
- Background mode entitlements needed for background operation

## Webview Implementation

The Portal screen uses `webview_flutter` to display the xmarket.infy.uk website:

```dart
WebViewController controller = WebViewController()
  ..setJavaScriptMode(JavaScriptMode.unrestricted)
  ..setBackgroundColor(const Color(0x00000000))
  ..setNavigationDelegate(
    NavigationDelegate(
      onPageStarted: (String url) {
        // Handle page load start
      },
      onPageFinished: (String url) {
        // Handle page load complete
      },
      onWebResourceError: (WebResourceError error) {
        // Handle loading errors
      },
    ),
  )
  ..loadRequest(Uri.parse('https://xmarket.infy.uk'));
```

### Error Handling

Custom error handling is implemented to provide a better user experience when network issues occur:

1. Detecting errors via `onWebResourceError` callback
2. Displaying a custom error widget with retry functionality
3. Implementing proper loading states

## Performance Optimizations

### Memory Management

- Proper disposal of resources in `dispose()` methods
- Cancellation of timers and streams when not needed
- Use of `AutomaticKeepAliveClientMixin` to preserve state

### UI Performance

- Minimal widget rebuilds using `Consumer` pattern
- Efficient state updates with Provider
- Hardware acceleration for webview rendering

### Battery Considerations

BLE broadcasting can impact battery life. The implementation includes:

- Clear user controls to start/stop broadcasting
- Visual indicators of active broadcasting
- Automatic cleanup of BLE resources when navigating away

## Testing Strategy

For comprehensive testing of this application:

1. **Unit Tests**: Test individual components like BleService
2. **Widget Tests**: Test UI components in isolation
3. **Integration Tests**: Test the interaction between components
4. **Platform Tests**: Test on real iOS and Android devices

## Future Enhancements

Potential areas for future development:

1. **Central Mode**: Implement BLE scanning functionality
2. **Background Operation**: Enable BLE broadcasting in background
3. **Secure Storage**: Add encrypted storage for attendance data
4. **Analytics**: Implement usage tracking and performance monitoring
5. **Offline Support**: Add caching for the webview content

## Development Environment Setup

For optimal development experience:

1. Use Flutter 3.0.0 or higher
2. Test on physical devices for BLE functionality
3. Use Flutter DevTools for performance profiling
4. Enable strict analysis options for code quality

## Conclusion

This application demonstrates effective integration of BLE functionality with webview capabilities in a Flutter application. The architecture provides a solid foundation for future enhancements while maintaining good performance and user experience.
