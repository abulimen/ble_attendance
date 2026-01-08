import 'package:student_app/theme/app_theme.dart';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:student_app/services/face_detection_service.dart';
import 'package:student_app/services/permission_service.dart';
import 'package:student_app/services/image_optimization_service.dart';
import 'package:student_app/config/debug_config.dart';

class SelfieCapturePage extends StatefulWidget {
  final Future<bool> Function(File) onPhotoTaken;
  final String sessionName;

  const SelfieCapturePage({
    super.key,
    required this.onPhotoTaken,
    required this.sessionName,
  });

  @override
  State<SelfieCapturePage> createState() => _SelfieCapturePageState();
}

class _SelfieCapturePageState extends State<SelfieCapturePage>
    with WidgetsBindingObserver {
  static const double _minLuminance = 80.0;
  static const double _minSharpness = 15.0;
  static const double _minFaceAreaRatio = 0.08;

  CameraController? _controller;
  bool _isInitialized = false;
  bool _isTakingPicture = false;
  String? _errorMessage;
  List<CameraDescription> _cameras = [];
  final FaceDetectionService _faceDetectionService = FaceDetectionService();
  bool _isFaceDetected = false;
  bool _isProcessingImage = false;
  DateTime? _lastProcessedTime;
  CameraLensDirection _currentLensDirection = CameraLensDirection.front;
  double _currentLuminance = 0;
  double _currentSharpness = 0;
  String _guidanceMessage = 'Align your face within the guides';
  Color _guidanceColor = TrueSignTheme.info;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller?.dispose();
    _faceDetectionService.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_controller == null || !_controller!.value.isInitialized) {
      return;
    }

    if (state == AppLifecycleState.inactive) {
      _controller?.dispose();
    } else if (state == AppLifecycleState.resumed) {
      if (_controller != null) {
        _initializeCamera();
      }
    }
  }

  Future<void> _initializeCamera([CameraLensDirection? preferredLens]) async {
    try {
      setState(() {
        _errorMessage = null;
      });

      final hasPermission =
          await PermissionService.requestCameraPermission(context);
      if (!hasPermission) {
        setState(() {
          _errorMessage =
              'Camera permission is required for attendance verification.';
        });
        return;
      }

      _cameras = await availableCameras();
      if (_cameras.isEmpty) {
        setState(() {
          _errorMessage = 'No cameras available on this device.';
        });
        return;
      }

      if (preferredLens != null) {
        _currentLensDirection = preferredLens;
      }

      final CameraDescription camera = _getActiveCamera();

      _controller = CameraController(
        camera,
        ResolutionPreset.medium, // Better quality without stretching
        enableAudio: false,
      );

      await _controller!.initialize();

      if (mounted) {
        setState(() {
          _isInitialized = true;
        });
        // Start image stream with throttling to prevent memory overflow
        _controller!.startImageStream((image) async {
          // Throttle: only process every 500ms to reduce memory usage
          final now = DateTime.now();
          if (_isProcessingImage ||
              (_lastProcessedTime != null &&
                  now.difference(_lastProcessedTime!).inMilliseconds < 500)) {
            return;
          }

          _isProcessingImage = true;
          _lastProcessedTime = now;

          try {
            await _processCameraImage(image, camera);
          } catch (e) {
            DebugConfig.log('Face detection error: $e');
          } finally {
            _isProcessingImage = false;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Failed to initialize camera: ${e.toString()}';
        });
      }
    }
  }

  CameraDescription _getActiveCamera() {
    return _cameras.firstWhere(
      (camera) => camera.lensDirection == _currentLensDirection,
      orElse: () => _cameras.first,
    );
  }

  Future<void> _processCameraImage(
      CameraImage image, CameraDescription camera) async {
    final analysis = await _faceDetectionService.analyzeFrame(image, camera);
    final bool hasFace = analysis.faces.isNotEmpty;
    final bool lightingOk = analysis.averageLuminance >= _minLuminance;
    final bool sharpnessOk = analysis.sharpness >= _minSharpness;

    bool faceSizeOk = false;
    if (hasFace) {
      final double frameArea =
          analysis.imageSize.width * analysis.imageSize.height;
      faceSizeOk = analysis.faces.any((face) {
        final rect = face.boundingBox;
        final double faceArea = rect.width * rect.height;
        return frameArea > 0 && (faceArea / frameArea) >= _minFaceAreaRatio;
      });
    }

    String guidance;
    Color guidanceColor;
    bool ready = false;

    if (!lightingOk) {
      guidance = 'Find brighter lighting for a clear capture';
      guidanceColor = TrueSignTheme.warning;
    } else if (!hasFace) {
      guidance = 'Align your face within the guides';
      guidanceColor = TrueSignTheme.info;
    } else if (!faceSizeOk) {
      guidance = 'Move closer until your face fills the frame';
      guidanceColor = TrueSignTheme.info;
    } else if (!sharpnessOk) {
      guidance = 'Hold still — the preview looks blurry';
      guidanceColor = TrueSignTheme.warning;
    } else {
      guidance = 'Face detected · Ready to capture';
      guidanceColor = TrueSignTheme.success;
      ready = true;
    }

    if (mounted) {
      setState(() {
        _currentLuminance = analysis.averageLuminance;
        _currentSharpness = analysis.sharpness;
        _guidanceMessage = guidance;
        _guidanceColor = guidanceColor;
        _isFaceDetected = ready;
      });
    }
  }

  Future<void> _takePicture() async {
    if (!_isInitialized || _controller == null || !_isFaceDetected) return;

    setState(() {
      _isTakingPicture = true;
    });

    try {
      // CRITICAL: Stop image stream to free up memory before taking picture
      await _controller!.stopImageStream();

      final image = await _controller!.takePicture();

      final File imageFile =
          await ImageOptimizationService.optimizeImage(File(image.path));

      try {
        await File(image.path).delete();
      } catch (e) {
        // Ignore cleanup errors
      }

      final bool success = await widget.onPhotoTaken(imageFile);
      if (mounted) {
        Navigator.of(context).pop(success);
      }
    } catch (e) {
      // Restart image stream if capture failed
      if (_controller != null && _controller!.value.isInitialized) {
        try {
          final resumedCamera = _getActiveCamera();
          await _controller!.startImageStream((image) async {
            final now = DateTime.now();
            if (_isProcessingImage ||
                (_lastProcessedTime != null &&
                    now.difference(_lastProcessedTime!).inMilliseconds < 500)) {
              return;
            }

            _isProcessingImage = true;
            _lastProcessedTime = now;

            try {
              await _processCameraImage(image, resumedCamera);
            } catch (e) {
              DebugConfig.log('Face detection error: $e');
            } finally {
              _isProcessingImage = false;
            }
          });
        } catch (e) {
          DebugConfig.log('Failed to restart image stream: $e');
        }
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to take picture: ${e.toString()}')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isTakingPicture = false;
        });
      }
    }
  }

  Future<void> _toggleCamera() async {
    if (_cameras.length < 2 || _isTakingPicture) return;

    final newLens = _currentLensDirection == CameraLensDirection.front
        ? CameraLensDirection.back
        : CameraLensDirection.front;

    setState(() {
      _isInitialized = false;
      _isFaceDetected = false;
    });

    try {
      await _controller?.stopImageStream();
    } catch (_) {}

    await _controller?.dispose();
    _controller = null;

    await _initializeCamera(newLens);
  }

  Widget _buildErrorState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(
            Icons.error_outline,
            color: Colors.red,
            size: 64,
          ),
          const SizedBox(height: 16),
          Text(
            _errorMessage ?? 'Unknown error',
            style: const TextStyle(color: Colors.white),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => _initializeCamera(_currentLensDirection),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildLoadingState() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(color: TrueSignTheme.primaryBlue),
          SizedBox(height: 16),
          Text(
            'Initializing camera...',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTopBar(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.black.withValues(alpha: 0.7),
            Colors.black.withValues(alpha: 0.0),
          ],
        ),
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Navigator.of(context).pop(),
            child: Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: TrueSignTheme.primaryBlue.withValues(alpha: 0.9),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: TrueSignTheme.primaryBlue.withValues(alpha: 0.3),
                    blurRadius: 8,
                    spreadRadius: 1,
                  ),
                ],
              ),
              child: const Icon(
                Icons.arrow_back_rounded,
                color: Colors.white,
                size: 24,
              ),
            ),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Text(
              'Attendance Verification',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                fontSize: 20,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
              ),
            ),
          ),
          const SizedBox(width: 60),
        ],
      ),
    );
  }

  Widget _buildInstructionBlock() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      decoration: BoxDecoration(
        color: TrueSignTheme.primaryBlue.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: TrueSignTheme.primaryBlue.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            widget.sessionName,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.3,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: _guidanceColor,
                ),
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  _guidanceMessage,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _guidanceColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Light ${_currentLuminance.toStringAsFixed(0)} · Sharpness ${_currentSharpness.toStringAsFixed(1)}',
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomControls() {
    final bool canCapture = _isFaceDetected && !_isTakingPicture;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      color: Colors.black,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          GestureDetector(
            onTap: _cameras.length > 1 ? _toggleCamera : null,
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.flip_camera_android,
                color: _cameras.length > 1 ? Colors.white : Colors.white38,
                size: 28,
              ),
            ),
          ),
          GestureDetector(
            onTap: canCapture ? _takePicture : null,
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: TrueSignTheme.surface,
                border: Border.all(
                  color: canCapture ? TrueSignTheme.primaryBlue : Colors.grey,
                  width: 4,
                ),
                boxShadow: canCapture
                    ? [
                        BoxShadow(
                          color:
                              TrueSignTheme.primaryBlue.withValues(alpha: 0.5),
                          blurRadius: 24,
                          spreadRadius: 3,
                        ),
                      ]
                    : [],
              ),
              child: _isTakingPicture
                  ? const Center(
                      child: SizedBox(
                        width: 28,
                        height: 28,
                        child: CircularProgressIndicator(
                          strokeWidth: 3,
                          color: TrueSignTheme.primaryBlue,
                        ),
                      ),
                    )
                  : Icon(
                      Icons.camera_alt,
                      color:
                          canCapture ? TrueSignTheme.primaryBlue : Colors.grey,
                      size: 32,
                    ),
            ),
          ),
          const SizedBox(width: 56),
        ],
      ),
    );
  }

  Widget _buildCameraContent(BuildContext context) {
    return Column(
      children: [
        _buildTopBar(context),
        _buildInstructionBlock(),
        Expanded(
          child: Stack(
            fit: StackFit.expand,
            children: [
              CameraPreview(_controller!),
              // Subtle corner guides only
              CustomPaint(
                painter: CornerGuidesOverlay(faceDetected: _isFaceDetected),
              ),
            ],
          ),
        ),
        _buildBottomControls(),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(
          children: [
            Container(
              color: Colors.black,
              child: _errorMessage != null
                  ? _buildErrorState()
                  : !_isInitialized
                      ? _buildLoadingState()
                      : _buildCameraContent(context),
            ),
            if (_isTakingPicture)
              Container(
                color: Colors.black.withValues(alpha: 0.85),
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        strokeWidth: 3,
                      ),
                      const SizedBox(height: 24),
                      const Text(
                        'Verifying Identity...',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Please wait while we connect to the server',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.8),
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// Subtle corner guides overlay
class CornerGuidesOverlay extends CustomPainter {
  final bool faceDetected;

  CornerGuidesOverlay({required this.faceDetected});

  @override
  void paint(Canvas canvas, Size size) {
    final cornerLength = 40.0;
    final cornerPaint = Paint()
      ..color = faceDetected
          ? const Color(0xFF10B981)
          : Colors.white.withValues(alpha: 0.6)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.0
      ..strokeCap = StrokeCap.round;

    // Calculate frame area (centered, with padding)
    final padding = size.width * 0.1;
    final frameRect = Rect.fromLTRB(
      padding,
      size.height * 0.15,
      size.width - padding,
      size.height * 0.85,
    );

    // Top-left corner
    canvas.drawLine(
      Offset(frameRect.left, frameRect.top + cornerLength),
      Offset(frameRect.left, frameRect.top),
      cornerPaint,
    );
    canvas.drawLine(
      Offset(frameRect.left, frameRect.top),
      Offset(frameRect.left + cornerLength, frameRect.top),
      cornerPaint,
    );

    // Top-right corner
    canvas.drawLine(
      Offset(frameRect.right - cornerLength, frameRect.top),
      Offset(frameRect.right, frameRect.top),
      cornerPaint,
    );
    canvas.drawLine(
      Offset(frameRect.right, frameRect.top),
      Offset(frameRect.right, frameRect.top + cornerLength),
      cornerPaint,
    );

    // Bottom-left corner
    canvas.drawLine(
      Offset(frameRect.left, frameRect.bottom - cornerLength),
      Offset(frameRect.left, frameRect.bottom),
      cornerPaint,
    );
    canvas.drawLine(
      Offset(frameRect.left, frameRect.bottom),
      Offset(frameRect.left + cornerLength, frameRect.bottom),
      cornerPaint,
    );

    // Bottom-right corner
    canvas.drawLine(
      Offset(frameRect.right - cornerLength, frameRect.bottom),
      Offset(frameRect.right, frameRect.bottom),
      cornerPaint,
    );
    canvas.drawLine(
      Offset(frameRect.right, frameRect.bottom - cornerLength),
      Offset(frameRect.right, frameRect.bottom),
      cornerPaint,
    );
  }

  @override
  bool shouldRepaint(CornerGuidesOverlay oldDelegate) =>
      oldDelegate.faceDetected != faceDetected;
}
