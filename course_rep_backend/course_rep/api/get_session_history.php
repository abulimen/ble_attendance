<?php
require_once 'config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get and validate input parameters with defaults
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Validate limit and offset ranges
if ($limit < 1 || $limit > 100) {
    $limit = 20; // Default to reasonable limit
}
if ($offset < 0) {
    $offset = 0;
}

try {
    // Authentication and course rep verification is automatically handled by config.php
    // $user_id is available from global authentication in config.php

    // Get session history for this course rep
    $history_sql = "SELECT s.session_id, s.course_id, s.group_id, s.location, 
                           s.session_start_time, s.session_end_time, s.ble_id,
                           c.course_name, c.course_code, 
                           d.group_name,
                           COUNT(ar.attendance_id) as total_attendance,
                           TIMESTAMPDIFF(MINUTE, s.session_start_time, s.session_end_time) as duration_minutes
                    FROM attendancesessions s
                    JOIN courses c ON s.course_id = c.course_id
                    JOIN departmentgroups d ON s.group_id = d.group_id
                    LEFT JOIN attendancerecords ar ON s.session_id = ar.session_id
                    WHERE s.initiated_by_user_id = ? AND s.session_end_time IS NOT NULL
                    GROUP BY s.session_id, s.course_id, s.group_id, s.location, 
                             s.session_start_time, s.session_end_time, s.ble_id,
                             c.course_name, c.course_code, d.group_name
                    ORDER BY s.session_start_time DESC
                    LIMIT ? OFFSET ?";
    
    $history_stmt = $conn->prepare($history_sql);
    if (!$history_stmt) {
        error_log("Session history prepare failed: " . $conn->error);
        sendError('Database error', 500);
    }
    
    $history_stmt->bind_param("iii", $user_id, $limit, $offset);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    $sessions = [];
    while ($row = $history_result->fetch_assoc()) {
        $sessions[] = [
            'session_id' => $row['session_id'],
            'course_id' => (int)$row['course_id'],
            'group_id' => (int)$row['group_id'],
            'course_name' => $row['course_name'],
            'course_code' => $row['course_code'],
            'group_name' => $row['group_name'],
            'location' => $row['location'],
            'session_start_time' => $row['session_start_time'],
            'session_end_time' => $row['session_end_time'],
            'duration_minutes' => (int)$row['duration_minutes'],
            'total_attendance' => (int)$row['total_attendance'],
            'ble_id' => $row['ble_id']
        ];
    }
    
    $history_stmt->close();

    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT s.session_id) as total_sessions
                  FROM attendancesessions s
                  WHERE s.initiated_by_user_id = ? AND s.session_end_time IS NOT NULL";
    
    $count_stmt = $conn->prepare($count_sql);
    if (!$count_stmt) {
        error_log("Count query prepare failed: " . $conn->error);
        sendError('Database error', 500);
    }
    
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total_sessions'];
    $count_stmt->close();

    // Prepare response data
    $response_data = [
        'sessions' => $sessions,
        'pagination' => [
            'total_sessions' => (int)$total_count,
            'current_page' => (int)floor($offset / $limit) + 1,
            'per_page' => $limit,
            'total_pages' => (int)ceil($total_count / $limit),
            'has_more' => ($offset + $limit) < $total_count
        ],
        'retrieved_at' => date('Y-m-d H:i:s')
    ];
    
    sendSuccess('Session history retrieved successfully', $response_data);

} catch (Exception $e) {
    error_log("Get session history error: " . $e->getMessage());
    sendError('Failed to retrieve session history', 500);
}
?>
