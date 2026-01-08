<?php
require_once 'config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get and validate input parameters
$session_id = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';

if (empty($session_id)) {
    sendError('Session ID is required', 400);
}

try {
    // Authentication and course rep verification is automatically handled by config.php
    // $user_id is available from global authentication in config.php

    // First, verify the session exists and belongs to this course rep
    $session_check_sql = "SELECT s.*, c.course_name, c.course_code, d.group_name 
                          FROM attendancesessions s
                          JOIN courses c ON s.course_id = c.course_id
                          JOIN departmentgroups d ON s.group_id = d.group_id
                          WHERE s.session_id = ? AND s.initiated_by_user_id = ?";
    
    $session_stmt = $conn->prepare($session_check_sql);
    if (!$session_stmt) {
        error_log("Session check prepare failed: " . $conn->error);
        sendError('Database error', 500);
    }
    
    $session_stmt->bind_param("si", $session_id, $user_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    
    if ($session_result->num_rows === 0) {
        sendError('Session not found or access denied', 403);
    }
    
    $session_info = $session_result->fetch_assoc();
    $session_stmt->close();

    // Get live attendance records for this session
    $attendance_sql = "SELECT ar.*, u.first_name, u.last_name, u.matric_number, u.profile_picture
                       FROM attendancerecords ar
                       JOIN users u ON ar.student_id = u.user_id
                       WHERE ar.session_id = ? 
                       ORDER BY ar.attendance_time DESC";
    
    $attendance_stmt = $conn->prepare($attendance_sql);
    if (!$attendance_stmt) {
        error_log("Attendance query prepare failed: " . $conn->error);
        sendError('Database error', 500);
    }
    
    $attendance_stmt->bind_param("s", $session_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    
    $attendance_records = [];
    while ($row = $attendance_result->fetch_assoc()) {
        $attendance_records[] = [
            'student_id' => (int)$row['student_id'],
            'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'matric_number' => $row['matric_number'],
            'attendance_time' => $row['attendance_time'],
            'selfie_url' => $row['selfie_image_path'] ?? null,
            'profile_picture' => $row['profile_picture'] ?? null
        ];
    }
    
    $attendance_stmt->close();
    
    // Prepare response data
    $response_data = [
        'session_info' => [
            'session_id' => $session_info['session_id'],
            'course_name' => $session_info['course_name'],
            'course_code' => $session_info['course_code'],
            'group_name' => $session_info['group_name'],
            'location' => $session_info['location'],
            'session_start_time' => $session_info['session_start_time'],
            'session_end_time' => $session_info['session_end_time'],
            'is_active' => is_null($session_info['session_end_time'])
        ],
        'attendance_records' => $attendance_records,
        'total_present' => count($attendance_records),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    sendSuccess('Live attendance data retrieved successfully', $response_data);

} catch (Exception $e) {
    error_log("Get live attendance error: " . $e->getMessage());
    sendError('Failed to retrieve live attendance data', 500);
}
?>
