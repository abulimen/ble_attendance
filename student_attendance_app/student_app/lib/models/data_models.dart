enum FaceProfileStatus {
  pending,
  approved,
  rejected,
  incomplete,
  unknown,
}

class Student {
  final int userId;
  final String applicationId;
  final String? matricNumber;
  final String firstName;
  final String lastName;
  final String? email;
  final int? groupId;
  final String? groupName;
  final int? departmentId;
  final String? profileImageUrl;
  final FaceProfileStatus faceProfileStatus;

  Student({
    required this.userId,
    required this.applicationId,
    this.matricNumber,
    required this.firstName,
    required this.lastName,
    this.email,
    this.groupId,
    this.groupName,
    this.departmentId,
    this.profileImageUrl,
    this.faceProfileStatus = FaceProfileStatus.unknown,
  });

  String get fullName => "$firstName $lastName";

  factory Student.fromJson(Map<String, dynamic> json) {
    return Student(
      userId: json["user_id"],
      applicationId: json["application_id"],
      matricNumber: json["matric_number"],
      firstName: json["first_name"],
      lastName: json["last_name"],
      email: json["email"],
      groupId: json["group_id"],
      groupName: json["group_name"],
      departmentId: json["department_id"],
      profileImageUrl: json["profile_image_url"] ??
          json["profile_photo_url"] ??
          json["pfp_url"],
      faceProfileStatus:
          _mapFaceProfileStatus(json["face_profile_status"] as String?),
    );
  }
}

FaceProfileStatus _mapFaceProfileStatus(String? status) {
  switch ((status ?? '').toLowerCase()) {
    case 'pending':
      return FaceProfileStatus.pending;
    case 'approved':
      return FaceProfileStatus.approved;
    case 'rejected':
      return FaceProfileStatus.rejected;
    case 'incomplete':
      return FaceProfileStatus.incomplete;
    default:
      return FaceProfileStatus.unknown;
  }
}

class AttendanceSession {
  final String sessionId;
  final int courseId;
  final String courseName;
  final String courseCode;
  final int groupId;
  final String groupName;
  final String sessionStartTime;
  final String? location;
  final String bleId;
  final String? lecturerName;

  AttendanceSession({
    required this.sessionId,
    required this.courseId,
    required this.courseName,
    required this.courseCode,
    required this.groupId,
    required this.groupName,
    required this.sessionStartTime,
    this.location,
    required this.bleId,
    this.lecturerName,
  });

  factory AttendanceSession.fromJson(Map<String, dynamic> json) {
    return AttendanceSession(
      sessionId: json["session_id"],
      courseId: json["course_id"],
      courseName: json["course_name"],
      courseCode: json["course_code"],
      groupId: json["group_id"],
      groupName: json["group_name"],
      sessionStartTime: json["session_start_time"],
      location: json["location"],
      bleId: json["ble_id"],
      lecturerName: json["lecturer_name"],
    );
  }
}

class AttendanceRecord {
  final int attendanceId;
  final String sessionId;
  final int studentId;
  final int courseId;
  final int groupId;
  final String status;
  final String attendanceTime;

  AttendanceRecord({
    required this.attendanceId,
    required this.sessionId,
    required this.studentId,
    required this.courseId,
    required this.groupId,
    required this.status,
    required this.attendanceTime,
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      attendanceId: json["attendance_id"],
      sessionId: json["session_id"],
      studentId: json["student_id"],
      courseId: json["course_id"],
      groupId: json["group_id"],
      status: json["status"],
      attendanceTime: json["attendance_time"],
    );
  }
}
