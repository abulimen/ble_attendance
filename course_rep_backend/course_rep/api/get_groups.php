<?php
// API Get Groups Endpoint
require_once __DIR__ . '/config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Authentication is now handled automatically in config.php
// $payload and $user_id are already available

// Get the groups managed by this course rep
$sql = "SELECT g.group_id, g.group_name 
        FROM courserepgroup crg
        JOIN departmentgroups g ON crg.group_id = g.group_id
        WHERE crg.course_rep_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($group = $result->fetch_assoc()) {
        $groups[] = $group;
    }
    
    $stmt->close();
    
    sendSuccess('Groups retrieved successfully', ['groups' => $groups]);
} else {
    error_log("Error preparing groups query: " . $conn->error);
    sendError('Database error', 500);
}

$conn->close();
?>
