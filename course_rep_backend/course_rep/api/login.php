<?php
// API Login Endpoint
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

$username_or_matric = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// Basic validation
if (empty($username_or_matric) || empty($password)) {
    sendError('Username/Matric Number and Password are required');
}

// Prepare SQL statement to prevent SQL injection
$sql = "SELECT user_id, username, password, role, first_name, last_name, email
        FROM users
        WHERE (username = ? OR matric_number = ?) AND role = 'course_rep'";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $username_or_matric, $username_or_matric);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        // Note: In a production environment, you should use password_hash() and password_verify()
        if ($password == $user['password']) {
            // Password is correct, generate JWT token
            $token = generateJWT($user['user_id'], $user['username'], $user['role']);
            
            // Get the groups managed by this course rep
            $managed_groups = [];
            $group_sql = "SELECT g.group_id, g.group_name 
                         FROM courserepgroup crg
                         JOIN departmentgroups g ON crg.group_id = g.group_id
                         WHERE crg.course_rep_id = ?";
            
            if ($group_stmt = $conn->prepare($group_sql)) {
                $group_stmt->bind_param("i", $user['user_id']);
                $group_stmt->execute();
                $group_result = $group_stmt->get_result();
                
                while ($group = $group_result->fetch_assoc()) {
                    $managed_groups[] = $group;
                }
                
                $group_stmt->close();
            }
            
            // Return user data and token
            $user_data = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'managed_groups' => $managed_groups,
                'token' => $token
            ];
            
            // Remove password from response
            unset($user_data['password']);
            
            sendSuccess('Login successful', $user_data);
        } else {
            // Invalid password
            sendError('Invalid credentials', 401);
        }
    } else {
        // User not found or not a course rep
        sendError('Invalid credentials or user is not a course representative', 401);
    }
    $stmt->close();
} else {
    // SQL error
    error_log("Error preparing login statement: " . $conn->error);
    sendError('Login error. Please try again later', 500);
}

$conn->close();
?>
