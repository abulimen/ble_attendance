import 'dart:io';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:flutter/services.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import 'package:student_app/config/debug_config.dart';

class FaceVerificationResult {
  final bool isMatch;
  final double? similarity;
  final String message;

  const FaceVerificationResult({
    required this.isMatch,
    required this.message,
    this.similarity,
  });
}

class FaceVerificationService {
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      enableLandmarks: true,
      enableClassification: true,
      enableTracking: true,
      performanceMode: FaceDetectorMode.accurate,
    ),
  );

  // Threshold for face matching (0.0 to 1.0, where 1.0 is identical)
  // Adjusted threshold for the new algorithm
  static const double _similarityThreshold = 0.7;

  /// Verifies if the live image matches the reference image
  /// Returns a FaceVerificationResult with match status and similarity score
  Future<FaceVerificationResult> verify({
    required File liveImage,
    required File referenceImage,
  }) async {
    try {
      DebugConfig.log('Starting face verification...');

      // Detect faces in both images
      // Use retry logic for live image to handle potential orientation issues
      final List<Face> liveFaces = await _detectFacesWithRetry(liveImage);

      final InputImage inputRefImage = InputImage.fromFile(referenceImage);
      final List<Face> refFaces =
          await _faceDetector.processImage(inputRefImage);

      DebugConfig.log(
          'Faces detected - Live: ${liveFaces.length}, Reference: ${refFaces.length}');

      // Validate that both images contain faces
      if (liveFaces.isEmpty) {
        DebugConfig.log('Face verification failed: No face in selfie');
        return const FaceVerificationResult(
          isMatch: false,
          message: 'No face detected in the selfie.',
        );
      }

      if (liveFaces.length > 1) {
        DebugConfig.log('Face verification failed: Multiple faces in selfie');
        return const FaceVerificationResult(
          isMatch: false,
          message:
              'Multiple faces detected. Please ensure only you are in the photo.',
        );
      }

      if (refFaces.isEmpty) {
        DebugConfig.log('Face verification failed: No face in profile photo');
        return const FaceVerificationResult(
          isMatch: false,
          message: 'No face detected in your profile photo.',
        );
      }

      // If multiple faces in reference, pick the largest one (most prominent)
      Face referenceFace = refFaces.first;
      if (refFaces.length > 1) {
        DebugConfig.log(
            'Multiple faces in profile photo (${refFaces.length}). Using the largest one.');
        // Sort by bounding box area (descending)
        refFaces.sort((a, b) {
          final areaA = a.boundingBox.width * a.boundingBox.height;
          final areaB = b.boundingBox.width * b.boundingBox.height;
          return areaB.compareTo(areaA);
        });
        referenceFace = refFaces.first;
      }

      // Compare the faces using feature-based similarity
      final double similarity = await _compareFaces(
        liveImage,
        referenceImage,
        liveFaces.first,
        referenceFace,
      );

      DebugConfig.log(
          'Face similarity score: $similarity (Threshold: $_similarityThreshold)');

      final bool isMatch = similarity >= _similarityThreshold;

      DebugConfig.log('Verification result: ${isMatch ? "MATCH" : "NO MATCH"}');

      return FaceVerificationResult(
        isMatch: isMatch,
        similarity: similarity,
        message: isMatch
            ? 'Face verified successfully.'
            : 'Face verification failed. Similarity: ${(similarity * 100).toStringAsFixed(1)}%',
      );
    } on PlatformException catch (e) {
      DebugConfig.log('Face verification platform error: $e');
      return FaceVerificationResult(
        isMatch: false,
        message: e.message ?? 'Face verification failed. Please try again.',
      );
    } catch (e) {
      DebugConfig.log('Face verification error: $e');
      return FaceVerificationResult(
        isMatch: false,
        message: 'Face verification error: ${e.toString()}',
      );
    }
  }

  /// Compares two faces using geometric features and image similarity
  Future<double> _compareFaces(
    File liveImageFile,
    File referenceImageFile,
    Face liveFace,
    Face referenceFace,
  ) async {
    // Calculate geometric similarity based on face landmarks
    double geometricSimilarity =
        _calculateGeometricSimilarity(liveFace, referenceFace);

    // Calculate image-based similarity by comparing face regions
    double imageSimilarity = await _calculateImageSimilarity(
      liveImageFile,
      referenceImageFile,
      liveFace.boundingBox,
      referenceFace.boundingBox,
    );

    // Combine both metrics (weighted average)
    // Geometric features are more reliable, so give them more weight
    return (geometricSimilarity * 0.6) + (imageSimilarity * 0.4);
  }

  /// Calculates similarity based on facial landmarks and proportions
  double _calculateGeometricSimilarity(Face face1, Face face2) {
    double totalSimilarity = 0.0;
    int comparisonCount = 0;

    // Compare face angles (head pose)
    if (face1.headEulerAngleX != null && face2.headEulerAngleX != null) {
      final angleDiff = (face1.headEulerAngleX! - face2.headEulerAngleX!).abs();
      totalSimilarity += 1.0 - (angleDiff / 90.0).clamp(0.0, 1.0);
      comparisonCount++;
    }

    if (face1.headEulerAngleY != null && face2.headEulerAngleY != null) {
      final angleDiff = (face1.headEulerAngleY! - face2.headEulerAngleY!).abs();
      totalSimilarity += 1.0 - (angleDiff / 90.0).clamp(0.0, 1.0);
      comparisonCount++;
    }

    // Compare smile probability
    if (face1.smilingProbability != null && face2.smilingProbability != null) {
      final smileDiff =
          (face1.smilingProbability! - face2.smilingProbability!).abs();
      totalSimilarity += 1.0 - smileDiff;
      comparisonCount++;
    }

    // Compare bounding box aspect ratio (face shape)
    final ratio1 = face1.boundingBox.width / face1.boundingBox.height;
    final ratio2 = face2.boundingBox.width / face2.boundingBox.height;
    final ratioDiff = (ratio1 - ratio2).abs();
    totalSimilarity += 1.0 - (ratioDiff / 1.0).clamp(0.0, 1.0);
    comparisonCount++;

    return comparisonCount > 0 ? totalSimilarity / comparisonCount : 0.0;
  }

  /// Calculates similarity by comparing the actual face image regions
  Future<double> _calculateImageSimilarity(
    File liveImageFile,
    File referenceImageFile,
    Rect liveBoundingBox,
    Rect referenceBoundingBox,
  ) async {
    try {
      // Load and decode images
      final liveBytes = await liveImageFile.readAsBytes();
      final referenceBytes = await referenceImageFile.readAsBytes();

      final liveImage = img.decodeImage(liveBytes);
      final referenceImage = img.decodeImage(referenceBytes);

      if (liveImage == null || referenceImage == null) {
        return 0.0;
      }

      // Crop face regions
      final liveFaceCrop = img.copyCrop(
        liveImage,
        x: liveBoundingBox.left.toInt().clamp(0, liveImage.width - 1),
        y: liveBoundingBox.top.toInt().clamp(0, liveImage.height - 1),
        width: liveBoundingBox.width.toInt().clamp(1, liveImage.width),
        height: liveBoundingBox.height.toInt().clamp(1, liveImage.height),
      );

      final referenceFaceCrop = img.copyCrop(
        referenceImage,
        x: referenceBoundingBox.left.toInt().clamp(0, referenceImage.width - 1),
        y: referenceBoundingBox.top.toInt().clamp(0, referenceImage.height - 1),
        width:
            referenceBoundingBox.width.toInt().clamp(1, referenceImage.width),
        height:
            referenceBoundingBox.height.toInt().clamp(1, referenceImage.height),
      );

      // Resize both to same size for comparison (64x64 is sufficient)
      const size = 64;
      final liveResized =
          img.copyResize(liveFaceCrop, width: size, height: size);
      final referenceResized =
          img.copyResize(referenceFaceCrop, width: size, height: size);

      // Calculate histogram similarity
      return _calculateHistogramSimilarity(liveResized, referenceResized);
    } catch (e) {
      // If image comparison fails, return neutral score
      return 0.5;
    }
  }

  /// Calculates similarity using histogram comparison
  double _calculateHistogramSimilarity(img.Image img1, img.Image img2) {
    // Create histograms for each color channel
    final hist1R = List<int>.filled(256, 0);
    final hist1G = List<int>.filled(256, 0);
    final hist1B = List<int>.filled(256, 0);
    final hist2R = List<int>.filled(256, 0);
    final hist2G = List<int>.filled(256, 0);
    final hist2B = List<int>.filled(256, 0);

    // Build histograms
    for (int y = 0; y < img1.height; y++) {
      for (int x = 0; x < img1.width; x++) {
        final pixel1 = img1.getPixel(x, y);
        final pixel2 = img2.getPixel(x, y);

        hist1R[pixel1.r.toInt()]++;
        hist1G[pixel1.g.toInt()]++;
        hist1B[pixel1.b.toInt()]++;
        hist2R[pixel2.r.toInt()]++;
        hist2G[pixel2.g.toInt()]++;
        hist2B[pixel2.b.toInt()]++;
      }
    }

    // Normalize histograms
    final totalPixels = img1.width * img1.height;
    final normHist1R = hist1R.map((v) => v / totalPixels).toList();
    final normHist1G = hist1G.map((v) => v / totalPixels).toList();
    final normHist1B = hist1B.map((v) => v / totalPixels).toList();
    final normHist2R = hist2R.map((v) => v / totalPixels).toList();
    final normHist2G = hist2G.map((v) => v / totalPixels).toList();
    final normHist2B = hist2B.map((v) => v / totalPixels).toList();

    // Calculate correlation for each channel
    final corrR = _calculateCorrelation(normHist1R, normHist2R);
    final corrG = _calculateCorrelation(normHist1G, normHist2G);
    final corrB = _calculateCorrelation(normHist1B, normHist2B);

    // Return average correlation
    return (corrR + corrG + corrB) / 3.0;
  }

  /// Calculates correlation coefficient between two histograms
  double _calculateCorrelation(List<double> hist1, List<double> hist2) {
    double sum1 = 0.0, sum2 = 0.0, sum1Sq = 0.0, sum2Sq = 0.0, pSum = 0.0;
    final n = hist1.length;

    for (int i = 0; i < n; i++) {
      sum1 += hist1[i];
      sum2 += hist2[i];
      sum1Sq += hist1[i] * hist1[i];
      sum2Sq += hist2[i] * hist2[i];
      pSum += hist1[i] * hist2[i];
    }

    final num = pSum - (sum1 * sum2 / n);
    final den = ((sum1Sq - sum1 * sum1 / n) * (sum2Sq - sum2 * sum2 / n)).abs();

    if (den == 0) return 0.0;
    return (num / den.abs()).clamp(0.0, 1.0);
  }

  Future<List<Face>> _detectFacesWithRetry(File imageFile) async {
    // 1. Try original
    final inputImage = InputImage.fromFile(imageFile);
    var faces = await _faceDetector.processImage(inputImage);
    if (faces.isNotEmpty) return faces;

    DebugConfig.log('No faces found in original. Trying rotations...');

    try {
      // 2. Try rotating the bytes
      final bytes = await imageFile.readAsBytes();
      final originalImg = img.decodeImage(bytes);
      if (originalImg == null) return [];

      // Try 90, 180, 270
      for (var angle in [90, 180, 270]) {
        DebugConfig.log('Trying rotation: $angle');
        final rotatedImg = img.copyRotate(originalImg, angle: angle);
        final rotatedBytes = img.encodeJpg(rotatedImg);

        final tempDir = await getTemporaryDirectory();
        final tempFile = File(
            '${tempDir.path}/temp_rotate_${angle}_${DateTime.now().millisecondsSinceEpoch}.jpg');
        await tempFile.writeAsBytes(rotatedBytes);

        final rotatedInput = InputImage.fromFile(tempFile);
        faces = await _faceDetector.processImage(rotatedInput);

        // Clean up temp file
        try {
          await tempFile.delete();
        } catch (_) {}

        if (faces.isNotEmpty) {
          DebugConfig.log('Found face with rotation $angle!');
          return faces;
        }
      }
    } catch (e) {
      DebugConfig.log('Error during rotation retry: $e');
    }

    return [];
  }

  void dispose() {
    _faceDetector.close();
  }
}
