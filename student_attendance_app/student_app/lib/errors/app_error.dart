abstract class AppError {
  final String message;
  final String? code;
  final dynamic originalError;

  const AppError(this.message, {this.code, this.originalError});
}

class NetworkError extends AppError {
  const NetworkError(super.message);
}

class PermissionError extends AppError {
  const PermissionError(super.message);
}

class FaceDetectionError extends AppError {
  const FaceDetectionError(super.message);
}
