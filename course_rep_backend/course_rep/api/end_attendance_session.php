<?php
// API End Attendance Session Endpoint
require_once __DIR__ . '/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Authentication is now handled automatically in config.php
// $payload and $user_id are already available

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON data');
}

// Extract parameters
$session_id = isset($input['session_id']) ? trim($input['session_id']) : '';
$group_id = isset($input['group_id']) ? (int)$input['group_id'] : 0;
$course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;

// Basic Input Validation
if (empty($session_id)) {
    sendError('Invalid session specified');
}
if ($group_id <= 0) {
    sendError('Invalid group specified');
}
if ($course_id <= 0) {
    sendError('Invalid course specified');
}

// Verify this rep manages this group
$sql_verify = "SELECT 1 FROM courserepgroup WHERE course_rep_id = ? AND group_id = ?";
$stmt_verify = $conn->prepare($sql_verify);

if (!$stmt_verify) {
    error_log("Error preparing group verification statement: " . $conn->error);
    sendError('Database error', 500);
}

$stmt_verify->bind_param("ii", $user_id, $group_id);
$stmt_verify->execute();
$stmt_verify->store_result();

if ($stmt_verify->num_rows === 0) {
    $stmt_verify->close();
    sendError('Permission Denied: You do not manage this group', 403);
}

$stmt_verify->close();

// Check if the session exists and is active
$sql_check = "SELECT session_id, session_start_time, location, ble_id FROM attendancesessions 
              WHERE session_id = ? AND course_id = ? AND group_id = ? AND session_end_time IS NULL";
$stmt_check = $conn->prepare($sql_check);

if (!$stmt_check) {
    error_log("Error preparing session check statement: " . $conn->error);
    sendError('Database error', 500);
}

$stmt_check->bind_param("sii", $session_id, $course_id, $group_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $stmt_check->close();
    
    // Check if the session exists but is already ended
    $sql_ended = "SELECT session_id FROM attendancesessions 
                  WHERE session_id = ? AND course_id = ? AND group_id = ? AND session_end_time IS NOT NULL";
    $stmt_ended = $conn->prepare($sql_ended);
    
    if (!$stmt_ended) {
        error_log("Error preparing ended session check: " . $conn->error);
        sendError('Database error', 500);
    }
    
    $stmt_ended->bind_param("sii", $session_id, $course_id, $group_id);
    $stmt_ended->execute();
    $stmt_ended->store_result();
    
    if ($stmt_ended->num_rows > 0) {
        $stmt_ended->close();
        sendError("Session ($session_id) was already ended", 409);
    } else {
        $stmt_ended->close();
        sendError("Session not found or you don't have permission to end it", 404);
    }
}

$session_data = $result_check->fetch_assoc();
$stmt_check->close();

// End the session
$sql_update = "UPDATE attendancesessions
               SET session_end_time = NOW()
               WHERE session_id = ?
               AND course_id = ?
               AND group_id = ?
               AND session_end_time IS NULL";

$stmt_update = $conn->prepare($sql_update);

if (!$stmt_update) {
    error_log("Error preparing session update statement: " . $conn->error);
    sendError('Database error', 500);
}

$stmt_update->bind_param("sii", $session_id, $course_id, $group_id);

if (!$stmt_update->execute()) {
    error_log("Error executing session update: " . $stmt_update->error);
    $stmt_update->close();
    sendError('Failed to end attendance session', 500);
}

$affected_rows = $stmt_update->affected_rows;
$stmt_update->close();

if ($affected_rows === 0) {
    sendError('Failed to end session. It might have been ended by another request', 409);
}

// Get course details for response
$sql_course = "SELECT course_code, course_name FROM courses WHERE course_id = ?";
$stmt_course = $conn->prepare($sql_course);
$course_details = null;

if ($stmt_course) {
    $stmt_course->bind_param("i", $course_id);
    $stmt_course->execute();
    $result_course = $stmt_course->get_result();
    $course_details = $result_course->fetch_assoc();
    $stmt_course->close();
}

// Get group details for response
$sql_group = "SELECT group_name FROM departmentgroups WHERE group_id = ?";
$stmt_group = $conn->prepare($sql_group);
$group_details = null;

if ($stmt_group) {
    $stmt_group->bind_param("i", $group_id);
    $stmt_group->execute();
    $result_group = $stmt_group->get_result();
    $group_details = $result_group->fetch_assoc();
    $stmt_group->close();
}

// Return success response with session details
$response_data = [
    'session_id' => $session_id,
    'course_id' => $course_id,
    'course_code' => $course_details ? $course_details['course_code'] : null,
    'course_name' => $course_details ? $course_details['course_name'] : null,
    'group_id' => $group_id,
    'group_name' => $group_details ? $group_details['group_name'] : null,
    'location' => $session_data['location'],
    'ble_id' => $session_data['ble_id'],
    'start_time' => $session_data['session_start_time'],
    'end_time' => date('Y-m-d H:i:s')
];

sendSuccess('Attendance session ended successfully', $response_data);
?>
