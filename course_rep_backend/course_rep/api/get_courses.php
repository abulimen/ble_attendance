<?php
// API Get Courses Endpoint
require_once __DIR__ . '/config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Authentication is now handled automatically in config.php
// $payload and $user_id are already available

// Get the group ID from the query parameters
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Validate group_id
if ($group_id <= 0) {
    sendError('Invalid group ID');
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

// Get courses for the specified group
$courses = [];
$sql = "SELECT DISTINCT c.course_id, c.course_name, c.course_code
        FROM courses c
        JOIN group_course_lecturer_assignments gcl ON c.course_id = gcl.course_id
        WHERE gcl.group_id = ?
        ORDER BY c.course_code";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($course = $result->fetch_assoc()) {
        $courses[] = $course;
    }
    
    $stmt->close();
    
    sendSuccess('Courses retrieved successfully', ['courses' => $courses]);
} else {
    error_log("Error preparing courses query: " . $conn->error);
    sendError('Database error', 500);
}

$conn->close();
?>
