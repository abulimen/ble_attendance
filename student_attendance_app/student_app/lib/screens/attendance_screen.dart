import 'package:flutter/services.dart';
import 'package:student_app/theme/app_theme.dart';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import 'package:student_app/models/data_models.dart';
import 'package:student_app/providers/attendance_provider.dart';
import 'package:student_app/providers/auth_provider.dart';
import 'package:student_app/screens/selfie_capture_screen.dart';
import 'package:student_app/theme/app_animations.dart';

import 'package:student_app/widgets/celebration_animation.dart';
import 'package:student_app/widgets/fade_page_route.dart';
import 'package:student_app/widgets/scanning_indicator.dart';
import 'package:student_app/services/permission_service.dart';
import 'package:student_app/utils/snackbars.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  bool _showSuccessOverlay = false;
  String? _successMessage;

  void _triggerSuccessOverlay(String? message) {
    setState(() {
      _successMessage = message ?? 'Attendance marked successfully!';
      _showSuccessOverlay = true;
    });

    Future.delayed(const Duration(milliseconds: 3200), () {
      if (mounted) {
        setState(() {
          _showSuccessOverlay = false;
          _successMessage = null;
        });
      }
    });
  }

  @override
  void initState() {
    super.initState();
    // Fetch active sessions when the screen is first loaded, if authenticated
    // Ensure provider is listened to false in initState if you call methods that notifyListeners
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      if (authProvider.isAuthenticated) {
        Provider.of<AttendanceProvider>(context, listen: false)
            .fetchActiveSessions();
      }
    });
  }

  void _showAttendanceErrorDialog(String message) {
    String title = "Attendance Failed";
    String content = message;
    IconData icon = Icons.error_outline_rounded;
    Color color = TrueSignTheme.error;
    String buttonText = "Try Again";

    if (message.contains("No face detected in your profile photo")) {
      title = "Invalid Profile Photo";
      content =
          "We couldn't detect a face in your profile photo. Please contact support or update your profile picture.";
      icon = Icons.account_box_rounded;
      color = TrueSignTheme.error;
    } else if (message.contains("No face detected")) {
      title = "Face Not Detected";
      content =
          "We couldn't see your face. Please ensure you are in a well-lit area and nothing is covering your face.";
      icon = Icons.face_retouching_off_rounded;
      color = TrueSignTheme.warning;
    } else if (message.contains("Multiple faces")) {
      title = "Multiple Faces Detected";
      content = "Please ensure only you are in the frame.";
      icon = Icons.people_outline_rounded;
      color = TrueSignTheme.warning;
    } else if (message.contains("Face verification failed") ||
        message.contains("Similarity")) {
      title = "Verification Failed";
      content =
          "Your face didn't match your profile photo. Please try again or contact support if this persists.";
      icon = Icons.no_accounts_rounded;
      color = TrueSignTheme.error;
    } else if (message.toLowerCase().contains("network") ||
        message.toLowerCase().contains("connection") ||
        message.toLowerCase().contains("socketexception") ||
        message.toLowerCase().contains("host lookup") ||
        message.toLowerCase().contains("handshake") ||
        message.toLowerCase().contains("clientexception")) {
      title = "Connection Error";
      content =
          "We couldn't connect to the server. Please check your internet connection and try again.";
      icon = Icons.wifi_off_rounded;
      color = TrueSignTheme.error;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
        content: Text(
          content,
          style: const TextStyle(fontSize: 15, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(
              buttonText,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: TrueSignTheme.primaryBlue,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSessionCard(
      AttendanceSession session, AttendanceProvider attendanceProvider) {
    bool isMatched =
        attendanceProvider.matchedSession?.sessionId == session.sessionId;

    return AppAnimations.fadeSlide(
      child: GestureDetector(
        onTap: isMatched
            ? () async {
                HapticFeedback.mediumImpact();
                final bool? success = await Navigator.of(context).push(
                  FadePageRoute(
                    page: SelfieCapturePage(
                      sessionName: session.courseName,
                      onPhotoTaken: (File photo) async {
                        // The loading overlay is handled inside SelfieCapturePage
                        final result = await attendanceProvider
                            .markAttendanceForMatchedSession(photo);
                        if (result) {
                          HapticFeedback.heavyImpact();
                          _triggerSuccessOverlay(
                            attendanceProvider.statusMessage,
                          );
                        }
                        return result;
                      },
                    ),
                  ),
                );

                if (success == true) {
                  setState(() {});
                } else if (attendanceProvider.statusMessage != null) {
                  // Show specific error dialog if attendance failed
                  _showAttendanceErrorDialog(attendanceProvider.statusMessage!);
                }
              }
            : null,
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: isMatched
                ? Border.all(color: TrueSignTheme.accentGold, width: 2)
                : Border.all(color: Colors.grey.shade100),
            boxShadow: [
              BoxShadow(
                color: isMatched
                    ? TrueSignTheme.accentGold.withValues(alpha: 0.2)
                    : Colors.black.withValues(alpha: 0.03),
                blurRadius: isMatched ? 16 : 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isMatched
                          ? TrueSignTheme.accentGold.withValues(alpha: 0.15)
                          : TrueSignTheme.primaryBlue.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.school_rounded,
                      color: isMatched
                          ? TrueSignTheme.warning
                          : TrueSignTheme.primaryBlue,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          session.courseName,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: TrueSignTheme.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          session.courseCode,
                          style: const TextStyle(
                            fontSize: 13,
                            color: TrueSignTheme.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (isMatched)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: TrueSignTheme.accentGold,
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: [
                          BoxShadow(
                            color:
                                TrueSignTheme.accentGold.withValues(alpha: 0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.check_circle,
                              color: Colors.white, size: 14),
                          SizedBox(width: 4),
                          Text(
                            "Ready",
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: TrueSignTheme.surfaceVariant,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  children: [
                    _buildSessionDetail(
                        Icons.people_outline, "Group", session.groupName),
                    const SizedBox(height: 12),
                    _buildSessionDetail(
                        Icons.access_time, "Time", session.sessionStartTime),
                    const SizedBox(height: 12),
                    _buildSessionDetail(Icons.person_outline, "Lecturer",
                        session.lecturerName ?? 'N/A'),
                    const SizedBox(height: 12),
                    _buildSessionDetail(Icons.location_on_outlined, "Location",
                        session.location ?? 'N/A'),
                  ],
                ),
              ),
              if (isMatched) ...[
                const SizedBox(height: 16),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: BoxDecoration(
                    color: TrueSignTheme.accentGold.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: TrueSignTheme.accentGold.withValues(alpha: 0.3)),
                  ),
                  child: const Text(
                    "Tap card to mark attendance",
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: TrueSignTheme.warning,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  void _showLogoutConfirmation(
      BuildContext context, AuthProvider authProvider) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return Dialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          elevation: 0,
          backgroundColor: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.1),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.orange.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.logout_rounded,
                    size: 48,
                    color: Colors.orange.shade700,
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  'Confirm Logout',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).colorScheme.onSurface,
                      ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Are you sure you want to logout?',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
                const SizedBox(height: 32),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: () {
                          HapticFeedback.lightImpact();
                          Navigator.of(context).pop();
                        },
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: Text(
                          'Cancel',
                          style: TextStyle(
                            fontSize: 16,
                            color:
                                Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          HapticFeedback.mediumImpact();
                          Navigator.of(context).pop();
                          await authProvider.logout();
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red.shade600,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
                        ),
                        child: const Text(
                          'Logout',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSessionDetail(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: TrueSignTheme.primaryBlue),
        const SizedBox(width: 10),
        Text(
          "$label:",
          style: const TextStyle(
            fontWeight: FontWeight.w500,
            color: TrueSignTheme.textSecondary,
            fontSize: 13,
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: TrueSignTheme.textPrimary,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }

  List<AttendanceSession> _getSortedSessions(AttendanceProvider provider) {
    final sessions = List<AttendanceSession>.from(provider.activeSessions);

    // Sort: matched session first, then by start time
    sessions.sort((a, b) {
      final aIsMatched = provider.matchedSession?.sessionId == a.sessionId;
      final bIsMatched = provider.matchedSession?.sessionId == b.sessionId;

      if (aIsMatched && !bIsMatched) return -1;
      if (!aIsMatched && bIsMatched) return 1;

      return a.sessionStartTime.compareTo(b.sessionStartTime);
    });

    return sessions;
  }

  @override
  Widget build(BuildContext context) {
    final attendanceProvider = Provider.of<AttendanceProvider>(context);
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final sortedSessions = _getSortedSessions(attendanceProvider);

    // Show toast if no devices found
    if (attendanceProvider.showNoDevicesFoundToast) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Snackbars.showInfo(context, 'No class signals found nearby');
        attendanceProvider.clearNoDevicesFoundToast();
      });
    }

    return Scaffold(
      backgroundColor: TrueSignTheme.background,
      appBar: AppBar(
        elevation: 0,
        backgroundColor: Colors.white,
        title: Image.asset(
          'images/logo-dark.png',
          height: 32,
          fit: BoxFit.contain,
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded,
                color: TrueSignTheme.textPrimary),
            tooltip: "Logout",
            onPressed: () {
              HapticFeedback.lightImpact();
              _showLogoutConfirmation(context, authProvider);
            },
          ),
        ],
      ),
      body: Stack(
        children: [
          RefreshIndicator(
            color: TrueSignTheme.primaryBlue,
            onRefresh: () async {
              HapticFeedback.lightImpact();
              await attendanceProvider.fetchActiveSessions();
            },
            child: CustomScrollView(
              slivers: [
                // Header section with scan card
                SliverToBoxAdapter(
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    child: _buildScanCard(attendanceProvider),
                  ),
                ),

                // Sessions section header
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          "Active Sessions",
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w600,
                            color: TrueSignTheme.textPrimary,
                          ),
                        ),
                        if (!attendanceProvider.isLoadingSessions)
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 6),
                            decoration: BoxDecoration(
                              color: TrueSignTheme.primaryBlue
                                  .withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              "${sortedSessions.length}",
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: TrueSignTheme.primaryBlue,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),

                // Sessions list
                attendanceProvider.isLoadingSessions
                    ? SliverToBoxAdapter(child: _buildSkeletonList())
                    : sortedSessions.isEmpty
                        ? SliverFillRemaining(
                            child: _buildEmptyState(attendanceProvider),
                          )
                        : SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                            sliver: SliverList(
                              delegate: SliverChildBuilderDelegate(
                                (ctx, index) => Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: _buildSessionCard(
                                    sortedSessions[index],
                                    attendanceProvider,
                                  ),
                                ),
                                childCount: sortedSessions.length,
                              ),
                            ),
                          ),
              ],
            ),
          ),
          if (_showSuccessOverlay)
            Positioned.fill(
              child: Container(
                color: Colors.white.withValues(alpha: 0.9),
                child: CelebrationAnimation(
                  message: _successMessage,
                  type: CelebrationType.attendance,
                  duration: const Duration(milliseconds: 2600),
                  onComplete: () {
                    if (mounted) {
                      setState(() {
                        _showSuccessOverlay = false;
                        _successMessage = null;
                      });
                    }
                  },
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildScanCard(AttendanceProvider attendanceProvider) {
    return AppAnimations.fadeSlide(
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: TrueSignTheme.primaryBlue,
          borderRadius: BorderRadius.circular(24),
          boxShadow: TrueSignTheme.shadowMD,
          image: DecorationImage(
            image: const AssetImage(
                'assets/images/pattern_bg.png'), // Optional: subtle pattern if available, else remove
            fit: BoxFit.cover,
            colorFilter: ColorFilter.mode(
              TrueSignTheme.primaryBlue.withValues(alpha: 0.2),
              BlendMode.dstATop,
            ),
            onError: (_, __) {}, // Fail gracefully if asset missing
          ),
        ),
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.sensors_rounded,
                    size: 32,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Flexible(
                            child: Text(
                              "Discover Class Signals",
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                                letterSpacing: 0.5,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 8),
                          if (attendanceProvider.isScanningLocation)
                            SizedBox(
                              width: 12,
                              height: 12,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                    TrueSignTheme.accentGold),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        "Tap to detect nearby class signals",
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.white.withValues(alpha: 0.8),
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            if (attendanceProvider.isScanningLocation)
              ScanningIndicator(
                message: "Searching for class signals...",
                onStop: () async {
                  HapticFeedback.mediumImpact();
                  await attendanceProvider.stopLocationScan();
                },
              )
            else
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    HapticFeedback.mediumImpact();
                    final hasPermissions =
                        await PermissionService.requestBluetoothPermissions(
                            context);
                    if (hasPermissions) {
                      final servicesEnabled = await PermissionService
                          .checkBluetoothAndLocationEnabled(context);
                      if (servicesEnabled) {
                        await attendanceProvider.startLocationScan();
                      }
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: TrueSignTheme.primaryBlue,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  child: const Text(
                    "Start Scanning",
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ).animate(onPlay: (controller) => controller.repeat()).shimmer(
                    duration: 3000.ms,
                    delay: 2000.ms,
                    color: TrueSignTheme.primaryBlue.withValues(alpha: 0.1)),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState(AttendanceProvider provider) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 48),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: TrueSignTheme.surfaceVariant,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.event_busy_rounded,
                    size: 64,
                    color: TrueSignTheme.textSecondary.withValues(alpha: 0.5),
                  )
                      .animate()
                      .scaleXY(begin: 0.8, end: 1, duration: 600.ms)
                      .fadeIn(duration: 500.ms),
                ),
                const SizedBox(height: 24),
                Text(
                  provider.statusMessage ?? "No Active Sessions",
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: TrueSignTheme.textPrimary,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                const Text(
                  "Check back when your class starts or scan for nearby sessions",
                  style: TextStyle(
                    fontSize: 15,
                    color: TrueSignTheme.textSecondary,
                    height: 1.5,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSkeletonList() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: List.generate(
          3,
          (index) => Shimmer.fromColors(
            baseColor: Colors.grey[300]!,
            highlightColor: Colors.grey[100]!,
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: double.infinity,
                              height: 16,
                              color: Colors.white,
                            ),
                            const SizedBox(height: 8),
                            Container(
                              width: 100,
                              height: 12,
                              color: Colors.white,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Container(
                    width: double.infinity,
                    height: 80,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
