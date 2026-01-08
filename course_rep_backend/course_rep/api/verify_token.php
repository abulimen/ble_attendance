<?php
// API Verify Token Endpoint
require_once __DIR__ . '/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON data');
}

$token = isset($input['token']) ? trim($input['token']) : '';

// Basic validation
if (empty($token)) {
    sendError('Token is required');
}

// Validate the token
$payload = validateJWT($token);

if (!$payload) {
    sendError('Invalid or expired token', 401);
}

// Verify that the user still exists and is a course rep
if (!verifyCourseRep($payload['user_id'], $conn)) {
    sendError('User is not authorized as a course rep', 403);
}

// Token is valid, return user information
$user_id = $payload['user_id'];
$sql = "SELECT user_id, username, role, first_name, last_name, email
        FROM users
        WHERE user_id = ? AND role = 'course_rep'";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Get the groups managed by this course rep
        $managed_groups = [];
        $group_sql = "SELECT g.group_id, g.group_name 
                     FROM courserepgroup crg
                     JOIN departmentgroups g ON crg.group_id = g.group_id
                     WHERE crg.course_rep_id = ?";
        
        if ($group_stmt = $conn->prepare($group_sql)) {
            $group_stmt->bind_param("i", $user_id);
            $group_stmt->execute();
            $group_result = $group_stmt->get_result();
            
            while ($group = $group_result->fetch_assoc()) {
                $managed_groups[] = $group;
            }
            
            $group_stmt->close();
        }
        
        // Return user data
        $user_data = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'managed_groups' => $managed_groups
        ];
        
        sendSuccess('Token is valid', $user_data);
    } else {
        sendError('User not found', 404);
    }
    $stmt->close();
} else {
    error_log("Error preparing user verification statement: " . $conn->error);
    sendError('Verification error. Please try again later', 500);
}

$conn->close();
?>
