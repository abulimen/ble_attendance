# BLE Attendance App

A Flutter application that combines a webview portal with Bluetooth Low Energy (BLE) broadcasting functionality for attendance tracking. The app is designed to be cross-platform, working on both iOS and Android devices.

## Features

- **Bottom Navigation Bar**: Navigate between Portal and Attendance screens
- **Portal Screen**: Webview displaying xmarket.infy.uk with custom error handling and refresh capability
- **Attendance Screen**: BLE broadcasting functionality with real-time status monitoring
- **Modern UI**: Professional and clean interface with Material 3 design
- **Cross-Platform**: Compatible with both iOS and Android devices

## Technical Overview

This application uses the following key technologies:

- **Flutter**: Cross-platform UI framework
- **bluetooth_low_energy**: Plugin for BLE functionality (both peripheral and central modes)
- **webview_flutter**: For displaying web content within the app
- **Provider**: For state management
- **flutter_native_splash**: For implementing a custom splash screen

## Project Structure

```
lib/
├── main.dart                  # Application entry point
├── screens/
│   ├── home_screen.dart       # Main screen with bottom navigation
│   ├── portal_screen.dart     # Webview implementation
│   └── attendance_screen.dart # BLE broadcasting functionality
├── services/
│   └── ble_service.dart       # BLE functionality management
├── widgets/
│   └── custom_error_widget.dart # Error display for webview
└── utils/                     # Utility functions and constants
```

## Setup Instructions

### Prerequisites

- Flutter SDK (version 3.0.0 or higher)
- Android Studio / Xcode for platform-specific development
- Physical devices for testing BLE functionality (emulators do not support BLE)

### Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/ble_attendance_app.git
   ```

2. Navigate to the project directory:
   ```
   cd ble_attendance_app
   ```

3. Install dependencies:
   ```
   flutter pub get
   ```

4. Run the app:
   ```
   flutter run
   ```

### Platform-Specific Setup

#### Android

1. Ensure your `android/app/build.gradle` file has `minSdkVersion 21` or higher
2. Add the following permissions to your `AndroidManifest.xml`:
   ```xml
   <uses-permission android:name="android.permission.INTERNET" />
   <uses-permission android:name="android.permission.BLUETOOTH" />
   <uses-permission android:name="android.permission.BLUETOOTH_ADMIN" />
   <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
   <uses-permission android:name="android.permission.BLUETOOTH_ADVERTISE" />
   <uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />
   <uses-permission android:name="android.permission.BLUETOOTH_SCAN" />
   ```

#### iOS

1. Add the following to your `Info.plist`:
   ```xml
   <key>NSBluetoothAlwaysUsageDescription</key>
   <string>This app uses Bluetooth to broadcast attendance data</string>
   <key>NSBluetoothPeripheralUsageDescription</key>
   <string>This app uses Bluetooth to broadcast attendance data</string>
   <key>NSLocationWhenInUseUsageDescription</key>
   <string>This app needs location access for BLE functionality</string>
   ```

## Usage Guide

### Portal Screen

The Portal screen displays the xmarket.infy.uk website within the app. Features include:

- **Refresh Button**: Located in the top-right corner to reload the page
- **Error Handling**: Custom error display with retry option if the website cannot be loaded
- **Loading Indicator**: Shows while the page is loading

### Attendance Screen

The Attendance screen provides BLE broadcasting functionality for attendance tracking:

1. **Start Broadcasting**: Press the "Start Broadcasting" button to begin advertising BLE signals
2. **Status Monitoring**: View real-time status of the BLE broadcast, including:
   - Current status (Ready, Broadcasting, Error)
   - Duration of active broadcast
3. **Stop Broadcasting**: Press the "Stop Broadcasting" button to end the BLE advertisement

## BLE Technical Details

The app uses the `bluetooth_low_energy` plugin to implement BLE peripheral mode functionality:

- **Service UUID**: 00001234-0000-1000-8000-00805F9B34FB
- **Characteristic UUID**: 00001235-0000-1000-8000-00805F9B34FB
- **Broadcast Name**: "BLE Attendance"
- **Broadcast Data**: "ATTENDANCE" (as bytes)

## Troubleshooting

### Common Issues

1. **Bluetooth Not Available**:
   - Ensure Bluetooth is enabled on your device
   - Check that your device supports Bluetooth Low Energy
   - Verify that all required permissions are granted

2. **Webview Not Loading**:
   - Check your internet connection
   - Verify that the URL is accessible
   - Use the refresh button to reload the page

3. **BLE Broadcasting Not Working**:
   - Ensure you're using a physical device (not an emulator)
   - Check that all Bluetooth permissions are granted
   - Restart the app if BLE functionality becomes unresponsive

### Debugging

For advanced debugging:

1. Enable verbose logging in the BLE service
2. Check platform-specific Bluetooth settings
3. Use BLE scanner apps to verify broadcast signals

## Performance Optimization

The app has been optimized for performance in several ways:

1. **State Management**: Using Provider for efficient state updates
2. **Memory Management**: Proper disposal of resources when not needed
3. **UI Optimization**: Minimal rebuilds with Consumer pattern
4. **Webview State Preservation**: Maintaining webview state during navigation

## License

This project is licensed under the MIT License - see the LICENSE file for details.
