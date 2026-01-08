<?php
// API Get Active Session Endpoint
require_once __DIR__ . '/config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Authentication is now handled automatically in config.php
// $payload and $user_id are already available

// Get the group ID and course ID from the query parameters
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Validate parameters
if ($group_id <= 0) {
    sendError('Invalid group ID');
}
if ($course_id <= 0) {
    sendError('Invalid course ID');
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
    sendError('You do not manage this group', 403);
}

$stmt_verify->close();

// Check for active session
$sql = "SELECT a.session_id, a.course_id, a.group_id, a.initiated_by_user_id, 
               a.location, a.ble_id, a.session_start_time, 
               c.course_code, c.course_name, 
               d.group_name
        FROM attendancesessions a
        JOIN courses c ON a.course_id = c.course_id
        JOIN departmentgroups d ON a.group_id = d.group_id
        WHERE a.course_id = ? AND a.group_id = ? AND a.session_end_time IS NULL
        ORDER BY a.session_start_time DESC
        LIMIT 1";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $course_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        
        $session_data = [
            'session_id' => $session['session_id'],
            'course_id' => $session['course_id'],
            'course_code' => $session['course_code'],
            'course_name' => $session['course_name'],
            'group_id' => $session['group_id'],
            'group_name' => $session['group_name'],
            'location' => $session['location'],
            'ble_id' => $session['ble_id'],
            'start_time' => $session['session_start_time'],
            'initiated_by_user_id' => $session['initiated_by_user_id']
        ];
        
        sendSuccess('Active session found', $session_data);
    } else {
        sendSuccess('No active session found', ['active_session' => false]);
    }
    
    $stmt->close();
} else {
    error_log("Error preparing active session query: " . $conn->error);
    sendError('Database error', 500);
}

$conn->close();
?>
