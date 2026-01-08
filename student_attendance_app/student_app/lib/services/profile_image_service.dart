import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

import 'package:student_app/config/debug_config.dart';

class ProfileImageService {
  ProfileImageService._internal();
  static final ProfileImageService _instance = ProfileImageService._internal();
  factory ProfileImageService() => _instance;

  static const String _profileImagePathKey = 'cached_profile_image_path';
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();

  Future<File?> downloadAndCacheProfileImage(String url) async {
    try {
      DebugConfig.log('ProfileImageService: Downloading image from $url');
      if (url.isEmpty) return null;
      final response = await http.get(Uri.parse(url));
      if (response.statusCode != 200) {
        DebugConfig.log(
          'ProfileImageService: Failed to download profile image. Status: ${response.statusCode}',
        );
        return null;
      }

      final Directory directory = await getApplicationDocumentsDirectory();
      final File file = File('${directory.path}/profile_image.jpg');
      await file.writeAsBytes(response.bodyBytes, flush: true);
      await _secureStorage.write(key: _profileImagePathKey, value: file.path);
      DebugConfig.log('ProfileImageService: Image cached at ${file.path}');
      return file;
    } catch (e) {
      DebugConfig.log('ProfileImageService: Error caching profile image -> $e');
      return null;
    }
  }

  Future<File?> getCachedProfileImage() async {
    try {
      final String? path = await _secureStorage.read(key: _profileImagePathKey);
      DebugConfig.log('ProfileImageService: Checking cached path: $path');
      if (path == null) return null;
      final File file = File(path);
      if (await file.exists()) {
        DebugConfig.log('ProfileImageService: Cached file exists');
        return file;
      }
      DebugConfig.log('ProfileImageService: Cached file missing at path');
      await _secureStorage.delete(key: _profileImagePathKey);
      return null;
    } catch (e) {
      DebugConfig.log(
          'ProfileImageService: Unable to read cached profile image -> $e');
      return null;
    }
  }

  Future<void> clearCachedProfileImage() async {
    try {
      final String? path = await _secureStorage.read(key: _profileImagePathKey);
      if (path != null) {
        final File file = File(path);
        if (await file.exists()) {
          await file.delete();
        }
      }
    } catch (e) {
      DebugConfig.log('ProfileImageService: Error clearing cached image -> $e');
    } finally {
      await _secureStorage.delete(key: _profileImagePathKey);
    }
  }

  Future<File?> ensureProfileImage(String? remoteUrl) async {
    DebugConfig.log(
        'ProfileImageService: Ensuring profile image. Remote URL: $remoteUrl');
    final File? cached = await getCachedProfileImage();
    if (cached != null) {
      return cached;
    }
    if (remoteUrl == null || remoteUrl.isEmpty) {
      DebugConfig.log('ProfileImageService: No remote URL provided');
      return null;
    }
    return await downloadAndCacheProfileImage(remoteUrl);
  }
}
