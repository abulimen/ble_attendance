<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in as a class rep
check_course_rep_login();

$user_id = $_SESSION['user_id'];

// Check if the form was submitted using POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); // Redirect if accessed directly
    exit;
}

// --- Get Session, Course, and Group Info ---
$session_id = isset($_POST['session_id']) ? trim($_POST['session_id']) : null;
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

// Construct the redirection URL base early
$redirect_url = "take_attendance.php?group_id=" . urlencode($group_id) . "&course_id=" . urlencode($course_id);

// Basic Input Validation
$errors = [];
if (empty($session_id)) {
    $errors[] = "Invalid session specified.";
}
if ($group_id <= 0) {
    $errors[] = "Invalid group specified.";
}
if ($course_id <= 0) {
    $errors[] = "Invalid course specified.";
}

// Verify this rep manages this group (if inputs are valid so far)
if (empty($errors) && !verify_rep_manages_group($conn, $user_id, $group_id)) {
    $errors[] = "Permission Denied: You do not manage this group.";
}

// If initial errors, redirect back immediately
if (!empty($errors)) {
    $error_message = urlencode(implode(" ", $errors));
    // Try redirecting without session_id if it was invalid
    header('Location: ' . $redirect_url . '&error=' . $error_message);
    exit;
}

// --- End the Session ---
// Update the session record to set the end time
$sql_update = "UPDATE attendancesessions
               SET session_end_time = NOW()
               WHERE session_id = ?
               AND course_id = ?
               AND group_id = ?
               AND initiated_by_user_id = ? -- Optional: Ensure only the initiator can end it? Or just check group management?
               AND session_end_time IS NULL"; // Only end sessions that are currently active

$stmt_update = $conn->prepare($sql_update);
$update_success = false;
$affected_rows = 0;

if ($stmt_update) {
    // Binding: session_id (s), course_id (i), group_id (i), user_id (i)
    $stmt_update->bind_param("siii", $session_id, $course_id, $group_id, $user_id); // Added user_id check

    if ($stmt_update->execute()) {
        $affected_rows = $stmt_update->affected_rows;
        if ($affected_rows > 0) {
            $update_success = true;
        } else {
            // Check if the session was already ended or didn't match criteria
            $sql_check_again = "SELECT session_id FROM attendancesessions WHERE session_id = ? AND session_end_time IS NOT NULL";
            $stmt_check_again = $conn->prepare($sql_check_again);
            $already_ended = false;
            if($stmt_check_again) {
                $stmt_check_again->bind_param("s", $session_id);
                $stmt_check_again->execute();
                $stmt_check_again->store_result();
                $already_ended = $stmt_check_again->num_rows > 0;
                $stmt_check_again->close();
            }

            if ($already_ended) {
                 $errors[] = "Session ($session_id) was already ended.";
            } else {
                 $errors[] = "Failed to end session. It might not exist, not belong to you, or is already ended.";
                 error_log("Failed to end session $session_id for user $user_id. Affected rows: 0. Already ended: " . ($already_ended ? 'Yes' : 'No'));
            }
        }
    } else {
        error_log("DB Execute Error: Failed to update attendance session record for $session_id: " . $stmt_update->error);
        $errors[] = "Database error ending session.";
    }
    $stmt_update->close();
} else {
    error_log("DB Prepare Error: Failed to prepare update statement for session: " . $conn->error);
    $errors[] = "Database error preparing to end session.";
}

// --- Redirect based on success ---
if ($update_success) {
    $success_message = urlencode("Attendance session ({$session_id}) ended successfully.");
    // Redirect back to take_attendance WITHOUT the session_id, so it shows "Start New Session"
    header('Location: take_attendance.php?group_id=' . urlencode($group_id) . '&course_id=' . urlencode($course_id) . '&success=' . $success_message);
    exit;
} else {
    $error_message = urlencode(implode(" ", $errors));
    // Redirect back, keeping the session_id in the URL might be helpful for context if the error was "already ended"
    header('Location: ' . $redirect_url . '&session_id=' . urlencode($session_id) . '&error=' . $error_message);
    exit;
}

?>
