<?php
// API Start Attendance Session Endpoint
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
$group_id = isset($input['group_id']) ? (int)$input['group_id'] : 0;
$course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;
$location = isset($input['location']) ? trim($input['location']) : '';
$ble_id = isset($input['ble_id']) ? trim($input['ble_id']) : null;

// Basic Input Validation
if ($group_id <= 0) {
    sendError('Invalid group specified');
}
if ($course_id <= 0) {
    sendError('Invalid course specified');
}
if (empty($location)) {
    sendError('Venue/Location is required');
}
if (empty($ble_id)) {
    sendError('BLE ID is required');
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

// Check for existing active session
$sql_check = "SELECT session_id FROM attendancesessions WHERE course_id = ? AND group_id = ? AND session_end_time IS NULL LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
$existing_session_id = null;

if (!$stmt_check) {
    error_log("Error preparing session check statement: " . $conn->error);
    sendError('Database error', 500);
}

$stmt_check->bind_param("ii", $course_id, $group_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($row_check = $result_check->fetch_assoc()) {
    $existing_session_id = $row_check['session_id'];
    $stmt_check->close();
    sendError("An active session ($existing_session_id) already exists for this course and group. Please end the previous session first.", 409);
}

$stmt_check->close();

// Generate Session ID
try {
    $session_id = 'sess_' . bin2hex(random_bytes(8)); // More robust unique ID
} catch (Exception $e) {
    error_log("Random generation failed: " . $e->getMessage());
    sendError('Failed to generate secure session data', 500);
}

// Insert into attendancesessions table
$sql_insert = "INSERT INTO attendancesessions
                (session_id, course_id, group_id, initiated_by_user_id, location, ble_id, session_start_time)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    error_log("Error preparing session insert statement: " . $conn->error);
    sendError('Database error', 500);
}

$stmt_insert->bind_param("siiiss", $session_id, $course_id, $group_id, $user_id, $location, $ble_id);

if (!$stmt_insert->execute()) {
    error_log("Error executing session insert: " . $stmt_insert->error);
    $stmt_insert->close();
    sendError('Failed to start attendance session', 500);
}

$stmt_insert->close();

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
$session_data = [
    'session_id' => $session_id,
    'course_id' => $course_id,
    'course_code' => $course_details ? $course_details['course_code'] : null,
    'course_name' => $course_details ? $course_details['course_name'] : null,
    'group_id' => $group_id,
    'group_name' => $group_details ? $group_details['group_name'] : null,
    'location' => $location,
    'ble_id' => $ble_id,
    'start_time' => date('Y-m-d H:i:s')
];

sendSuccess('Attendance session started successfully', $session_data);
?>
