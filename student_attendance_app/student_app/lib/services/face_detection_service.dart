import 'dart:io';
import 'dart:typed_data';
import 'dart:ui';
import 'package:flutter/foundation.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

class FaceFrameAnalysis {
  final List<Face> faces;
  final double averageLuminance;
  final double sharpness;
  final Size imageSize;

  const FaceFrameAnalysis({
    required this.faces,
    required this.averageLuminance,
    required this.sharpness,
    required this.imageSize,
  });
}

class FaceDetectionService {
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      performanceMode: FaceDetectorMode.accurate,
      enableClassification: true,
      enableTracking: true,
    ),
  );

  Future<FaceFrameAnalysis> analyzeFrame(
      CameraImage image, CameraDescription camera) async {
    final Size inputSize = Size(
      image.width.toDouble(),
      image.height.toDouble(),
    );

    final double luminance = _calculateAverageLuminance(image);
    final double sharpness = _calculateSharpness(image);

    final inputImage = InputImage.fromBytes(
      bytes: _concatenatePlanes(image.planes),
      metadata: InputImageMetadata(
        size: inputSize,
        rotation: InputImageRotation.values.firstWhere(
          (element) => element.rawValue == camera.sensorOrientation,
          orElse: () => InputImageRotation.rotation0deg,
        ),
        format: InputImageFormat.nv21,
        bytesPerRow: image.planes.first.bytesPerRow,
      ),
    );
    final faces = await _faceDetector.processImage(inputImage);

    return FaceFrameAnalysis(
      faces: faces,
      averageLuminance: luminance,
      sharpness: sharpness,
      imageSize: inputSize,
    );
  }

  double _calculateAverageLuminance(CameraImage image) {
    final Plane plane = image.planes.first;
    final Uint8List bytes = plane.bytes;
    final int rowStride = plane.bytesPerRow;
    final int width = image.width;
    final int height = image.height;
    const int sampleStep = 8;

    int sum = 0;
    int count = 0;

    for (int y = 0; y < height; y += sampleStep) {
      final int rowStart = y * rowStride;
      for (int x = 0; x < width; x += sampleStep) {
        sum += bytes[rowStart + x];
        count++;
      }
    }

    if (count == 0) return 0;
    return sum / count;
  }

  double _calculateSharpness(CameraImage image) {
    final Plane plane = image.planes.first;
    final Uint8List bytes = plane.bytes;
    final int rowStride = plane.bytesPerRow;
    final int width = image.width;
    final int height = image.height;

    if (width < 3 || height < 3) {
      return 0;
    }

    const int sampleStep = 6;
    double mean = 0;
    double m2 = 0;
    int count = 0;

    for (int y = 1; y < height - 1; y += sampleStep) {
      final int row = y * rowStride;
      final int rowAbove = (y - 1) * rowStride;
      final int rowBelow = (y + 1) * rowStride;

      for (int x = 1; x < width - 1; x += sampleStep) {
        final double center = bytes[row + x].toDouble();
        final double left = bytes[row + x - 1].toDouble();
        final double right = bytes[row + x + 1].toDouble();
        final double top = bytes[rowAbove + x].toDouble();
        final double bottom = bytes[rowBelow + x].toDouble();

        final double laplacian = 4 * center - left - right - top - bottom;

        count++;
        final double delta = laplacian - mean;
        mean += delta / count;
        final double delta2 = laplacian - mean;
        m2 += delta * delta2;
      }
    }

    if (count < 2) {
      return 0;
    }

    return m2 / count;
  }

  Future<Face?> detectFaceFromFile(File imageFile) async {
    try {
      final inputImage = InputImage.fromFilePath(imageFile.path);
      final faces = await _faceDetector.processImage(inputImage);
      if (faces.isEmpty) {
        return null;
      }
      faces.sort((a, b) => b.boundingBox.size.longestSide
          .compareTo(a.boundingBox.size.longestSide));
      return faces.first;
    } catch (e) {
      debugPrint('FaceDetectionService: detectFaceFromFile error -> $e');
      return null;
    }
  }

  Uint8List _concatenatePlanes(List<Plane> planes) {
    final WriteBuffer allBytes = WriteBuffer();
    for (final Plane plane in planes) {
      allBytes.putUint8List(plane.bytes);
    }
    return allBytes.done().buffer.asUint8List();
  }

  void dispose() {
    _faceDetector.close();
  }
}
