<?php
// API Configuration File

// Enable CORS for API endpoints
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// JWT Configuration
define('JWT_SECRET', '$M12v5mmnmaD#y@eN5xU-tsu}g)eA)I^-GHFXSA8-fL([y5JM,~,OEa8uytflN)KPWE,NNb9khfVSS3~)Z9]@%zG$gbtPtpJ{on');
define('JWT_EXPIRY', 86400*3); 

// API Response Functions
function sendResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse('error', $message);
}

function sendSuccess($message, $data = null) {
    http_response_code(200);
    sendResponse('success', $message, $data);
}

// JWT Functions
function generateJWT($user_id, $username, $role) {
    $issuedAt = time();
    $expiryTime = $issuedAt + JWT_EXPIRY;
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expiryTime,
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role
    ];
    
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64_encode(json_encode($payload));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    return "$header.$payload.$signature";
}

function validateJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    $valid_signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if ($signature !== $valid_signature) {
        return false;
    }
    
    $payload_data = json_decode(base64_decode($payload), true);
    if ($payload_data === null) {
        return false;
    }
    
    // Check if token has expired
    if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

// Authentication Middleware - Now automatically applied to all API endpoints except login
function authenticateRequest() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        sendError('Authorization header missing', 401);
    }
    
    $authHeader = $headers['Authorization'];
    if (strpos($authHeader, 'Bearer ') !== 0) {
        sendError('Invalid authorization format. Use: Bearer {token}', 401);
    }
    
    $token = str_replace('Bearer ', '', $authHeader);
    
    $payload = validateJWT($token);
    if (!$payload) {
        sendError('Invalid or expired token', 401);
    }
    
    return $payload;
}

// Check if user is a course rep
function verifyCourseRep($user_id, $conn) {
    $sql = "SELECT role FROM users WHERE user_id = ? AND role = 'course_rep'";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $stmt->close();
        return true;
    }
    
    $stmt->close();
    return false;
}

// Apply authentication to all API endpoints except login and verify_token
$current_script = basename($_SERVER['SCRIPT_NAME']);
$exempt_scripts = ['login.php', 'verify_token.php'];

if (!in_array($current_script, $exempt_scripts)) {
    $payload = authenticateRequest();
    $user_id = $payload['user_id'];
    
    // Verify that the user is a course rep
    if (!verifyCourseRep($user_id, $conn)) {
        sendError('User is not authorized as a course rep', 403);
    }
}
