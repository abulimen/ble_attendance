class DebugConfig {
  static const bool isLoggingEnabled = false;

  static void log(String message) {
    if (isLoggingEnabled) {
      print('[DEBUG] $message');
    }
  }
}
