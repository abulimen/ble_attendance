<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session
session_start();

// Check login status (assuming check_course_rep_login function exists)
check_course_rep_login();

// Get group_id from URL
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);

if (!$group_id) {
    $_SESSION['error_message'] = "No group specified or invalid group ID.";
    header('Location: index.php');
    exit;
}

// Verify Class Rep manages this group
$course_rep_id = $_SESSION['user_id'];
if (!verify_rep_manages_group($conn, $course_rep_id, $group_id)) {
    $_SESSION['error_message'] = "You do not have permission to manage this group.";
    header('Location: index.php');
    exit;
}

// Get student IDs from POST
if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
    $student_ids = $_POST['student_ids'];

    // Prepare the placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));

    // Construct the SQL query
    $sql = "DELETE FROM students WHERE user_id IN (" . $placeholders . ")";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    // Bind the parameters
    $types = str_repeat('i', count($student_ids)); // 'i' for integer
    $stmt->bind_param($types, ...$student_ids);


    // Execute the query
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Selected students deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting students: " . $stmt->error;
    }

    // Close statement
    $stmt->close();
} else {
    $_SESSION['error_message'] = "No students selected for deletion.";
}
error_log($_SESSION['success_message']); // Log the student IDs for debugging
// Redirect back to manage_students.php
header('Location: manage_students.php?group_id=' . urlencode($group_id));
exit;
?>