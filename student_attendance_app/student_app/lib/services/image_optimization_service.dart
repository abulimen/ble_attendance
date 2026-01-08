import 'dart:io';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';

class ImageOptimizationService {
  static Future<File> optimizeImage(File originalImage) async {
    final bytes = await originalImage.readAsBytes();
    final image = img.decodeImage(bytes);

    if (image == null) {
      throw Exception('Invalid image');
    }

    // Bake orientation to ensure image is upright (fixes rotation issues)
    final uprightImage = img.bakeOrientation(image);

    // Debug log for image dimensions
    print(
        'ImageOptimizationService: Original size: ${image.width}x${image.height}');
    print(
        'ImageOptimizationService: Upright size: ${uprightImage.width}x${uprightImage.height}');

    // Resize if too large
    // final resized = img.copyResize(uprightImage, width: 800);
    final resized = uprightImage; // Skip resizing for now to debug

    // Compress with quality optimization
    final compressed = img.encodeJpg(resized, quality: 85);

    // Save optimized image
    final tempDir = await getTemporaryDirectory();
    final optimizedFile = File(
        '${tempDir.path}/optimized_${DateTime.now().millisecondsSinceEpoch}.jpg');
    await optimizedFile.writeAsBytes(compressed);

    print(
        'ImageOptimizationService: Optimized image saved to ${optimizedFile.path}');

    return optimizedFile;
  }
}
