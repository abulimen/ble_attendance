# Student Attendance App - Project Brief

## 📱 Overview

The **Student Attendance App** is a Flutter-based mobile application that revolutionizes traditional attendance marking in educational institutions by combining **Bluetooth Low Energy (BLE) proximity detection** with **mandatory facial verification** through live selfie capture.

This system ensures accurate attendance tracking by requiring students to be physically present within BLE range of the lecturer's device AND provide visual verification through guided selfie capture.

---

## 🎯 Problem Statement

Traditional attendance systems face several challenges:
- **Manual roll calls** are time-consuming and disruptive
- **Proxy attendance** where friends mark attendance for absent students
- **Inaccurate location verification** - students can mark attendance from anywhere
- **Paper-based systems** are inefficient and prone to errors
- **Basic digital systems** lack proper verification mechanisms

---

## ✨ Key Features

### 🔐 **Secure Authentication**
- JWT token-based authentication (7-day validity)
- Support for multiple login credentials (username, matric number, application ID)
- Automatic token refresh and logout handling

### 📡 **BLE Proximity Detection**
- Scans for lecturer's BLE device within classroom range
- Matches BLE signals with active attendance sessions
- Prevents remote attendance marking
- Real-time session matching and validation

### 📸 **Mandatory Selfie Verification**
- **Live camera capture only** - no file uploads allowed
- Face positioning guides with oval overlay
- Front camera prioritization for selfies
- Real-time preview with alignment instructions
- Prevents photo spoofing and proxy attendance

### 📚 **Session Management**
- Fetches active attendance sessions based on student's enrolled courses
- Displays session details (course, lecturer, location, timing)
- Real-time session status updates
- Group and carry-over enrollment support

### 🔄 **Robust Error Handling**
- Network connectivity management
- Permission handling (Camera, Bluetooth, Location)
- Graceful fallbacks and user-friendly error messages
- Automatic retry mechanisms

---

## 🏗️ Technical Architecture

### **Frontend (Mobile App)**
- **Framework**: Flutter (Dart)
- **State Management**: Provider pattern
- **Camera**: Flutter Camera plugin with custom face guides
- **BLE Communication**: Flutter Blue Plus
- **Storage**: Flutter Secure Storage for tokens
- **HTTP Client**: Dart HTTP with multipart form data support

### **Backend Integration**
- **API Base**: `https://ble.xpansieve.com.ng/api/student/`
- **Authentication**: JWT tokens with Bearer authentication
- **Data Format**: Form data (not JSON) as per API requirements
- **File Upload**: Multipart form data for photo submission

### **Key Dependencies**
```yaml
dependencies:
  flutter_blue_plus: ^1.35.5     # BLE functionality
  camera: ^0.10.5+9               # Camera capture
  permission_handler: ^11.3.1     # System permissions
  provider: ^6.1.5                # State management
  flutter_secure_storage: ^9.2.4  # Secure token storage
  http: ^1.2.2                    # API communication
```

---

## 🔄 Attendance Workflow

### **1. Authentication Phase**
```
Login Screen → Enter Credentials → JWT Token → Main Dashboard
```

### **2. Session Discovery**
```
App Launch → Fetch Active Sessions → Display Available Classes
```

### **3. BLE Detection Phase**
```
Tap "Scan for BLE" → Scan for Lecturer's Device → Match BLE ID → Session Matched
```

### **4. Photo Verification Phase**
```
"Take Photo" Button → Camera with Face Guide → Capture Selfie → Photo Validation
```

### **5. Attendance Submission**
```
Submit Form Data → Server Verification → Attendance Marked → Success Feedback
```

---

## 📊 API Integration

### **Endpoints Used**
- `POST /login.php` - Student authentication
- `GET /active_sessions.php` - Fetch available attendance sessions  
- `POST /mark_attendance.php` - Submit attendance with photo

### **Data Flow**
```
Mobile App → HTTPS Request → PHP Backend → MySQL Database
     ↓              ↓              ↓            ↓
Form Data → JWT Validation → Business Logic → Data Storage
```

### **Security Features**
- JWT token authentication
- HTTPS encryption
- Form data validation
- Photo file verification
- Session timeout handling

---

## 👥 User Personas

### **Primary User: Students**
- **Goal**: Mark attendance quickly and accurately
- **Pain Points**: Long queues, forgotten credentials, technical issues
- **Needs**: Simple interface, clear instructions, reliable functionality

### **Secondary Users: Lecturers/Administrators**
- **Goal**: Accurate attendance tracking with minimal effort
- **Benefits**: Reduced proxy attendance, automated verification, digital records

---

## 🎨 User Experience Design

### **Design Principles**
- **Simplicity**: Minimal steps to mark attendance
- **Clarity**: Clear visual guides and instructions
- **Feedback**: Real-time status updates and error messages
- **Accessibility**: Large touch targets, readable fonts, intuitive navigation

### **Key UI Components**
- **Session Cards**: Display course information and BLE status
- **BLE Scanner**: Real-time scanning feedback with progress indicators
- **Camera Interface**: Oval face guide with corner markers
- **Status Indicators**: Color-coded feedback for different states

---

## 🔧 Development Setup

### **Prerequisites**
- Flutter SDK (latest stable)
- Android Studio / VS Code
- Android device/emulator with BLE and camera
- Network access to API endpoints

### **Installation**
```bash
# Clone and setup
cd student_attendance_app/student_app
flutter pub get
flutter run
```

### **Build Commands**
```bash
# Debug APK
flutter build apk --debug

# Release APK
flutter build apk --release
```

---

## 📱 Device Requirements

### **Minimum Requirements**
- **OS**: Android 6.0+ (API level 23)
- **RAM**: 2GB minimum
- **Storage**: 100MB available space
- **Camera**: Front-facing camera required
- **Bluetooth**: BLE support required
- **Network**: Internet connectivity required

### **Permissions Required**
- Camera access (mandatory)
- Bluetooth/Location (for BLE scanning)
- Internet access
- Storage access (for photo caching)

---

## 🚀 Deployment Strategy

### **Testing Phase**
1. **Unit Testing**: Core functionality testing
2. **Integration Testing**: API connectivity testing
3. **User Acceptance Testing**: Student feedback collection
4. **Load Testing**: Multiple concurrent users

### **Rollout Plan**
1. **Pilot Program**: Single department/course
2. **Limited Release**: Department-wide deployment
3. **Full Deployment**: Institution-wide rollout
4. **Monitoring**: Performance and usage analytics

---

## 🔮 Future Enhancements

### **Planned Features**
- **Offline Mode**: Cache sessions for poor connectivity
- **Analytics Dashboard**: Attendance patterns and insights
- **Multi-language Support**: Localization for different regions
- **Biometric Integration**: Fingerprint/Face ID authentication
- **Geofencing**: Additional location-based verification

### **Technical Improvements**
- **Performance Optimization**: Faster camera initialization
- **Battery Optimization**: Reduced BLE scanning power usage
- **Enhanced Security**: Additional photo verification algorithms
- **Cross-platform**: iOS version development

---

## 📊 Success Metrics

### **Technical KPIs**
- **App Performance**: < 3s session loading time
- **Camera Quality**: > 95% successful photo captures
- **BLE Detection**: > 90% successful session matching
- **API Reliability**: < 1% request failure rate

### **Business KPIs**  
- **Attendance Accuracy**: Reduced proxy attendance
- **Time Efficiency**: 50% reduction in attendance marking time
- **User Satisfaction**: > 80% positive feedback
- **System Adoption**: > 90% student enrollment

---

## 🛠️ Support & Maintenance

### **Technical Support**
- **Bug Reporting**: In-app feedback system
- **Documentation**: Comprehensive user guides
- **Updates**: Regular feature updates and security patches
- **Monitoring**: Real-time system health monitoring

### **User Support**
- **Training Materials**: Video tutorials and guides
- **Help Desk**: Technical support contact
- **FAQ Section**: Common issues and solutions
- **Feedback Channel**: Continuous improvement input

---

## 📄 License & Compliance

### **Data Privacy**
- Student photo data handled according to institutional privacy policies
- Secure photo transmission and storage
- GDPR/local privacy law compliance
- Data retention and deletion policies

### **Technical Compliance**
- Mobile app store guidelines compliance
- Educational technology standards
- Security best practices implementation
- Regular security audits and updates

---

*Built with ❤️ using Flutter for modern educational institutions*
