<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session to access logged-in user data
session_start(); // Make sure session is started to check login

// Ensure user is logged in as a class rep
check_course_rep_login();

$user_id = $_SESSION['user_id'];

// Check if the form was submitted using POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect back if accessed directly or via GET
    header('Location: index.php');
    exit;
}

// --- Get Course, Group, and Location Info ---
// Using POST as the form now includes location which shouldn't be in GET
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$location = isset($_POST['location']) ? trim($_POST['location']) : null;
$ble_id = isset($_POST['ble_id']) ? trim($_POST['ble_id']) : null;

// Construct the redirection URL base early for error reporting
$redirect_url = "take_attendance.php?group_id=" . urlencode($group_id) . "&course_id=" . urlencode($course_id); // Ensure IDs are urlencoded

// Basic Input Validation
$errors = [];
if ($group_id <= 0) {
    $errors[] = "Invalid group specified.";
}
if ($course_id <= 0) {
    $errors[] = "Invalid course specified.";
}
if (empty($location)) {
    $errors[] = "Venue/Location is required.";
}
if (empty($ble_id)) {
    $errors[] = "BLE ID is required.";
}

// Verify this rep manages this group (if inputs are valid so far)
if (empty($errors) && !verify_rep_manages_group($conn, $user_id, $group_id)) {
    $errors[] = "Permission Denied: You do not manage this group.";
}

// If initial errors, redirect back immediately
if (!empty($errors)) {
    $error_message = urlencode(implode(" ", $errors));
    header('Location: ' . $redirect_url . '&error=' . $error_message);
    exit;
}

// --- Check for existing active session ---
// Check if a session exists for this course/group where session_end_time is NULL
$sql_check = "SELECT session_id FROM attendancesessions WHERE course_id = ? AND group_id = ? AND session_end_time IS NULL LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
$existing_session_id = null;
if ($stmt_check) {
    $stmt_check->bind_param("ii", $course_id, $group_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
        $existing_session_id = $row_check['session_id'];
    }
    $stmt_check->close();
} else {
    error_log("DB Error: Failed to prepare check for existing session: " . $conn->error);
    $errors[] = "Database error checking for existing sessions.";
    header('Location: ' . $redirect_url . '&error=' . urlencode(implode(" ", $errors)));
    exit;
}

if ($existing_session_id) {
    $errors[] = "An active session ($existing_session_id) already exists for this course and group. Please end the previous session first.";
    header('Location: ' . $redirect_url . '&error=' . urlencode(implode(" ", $errors)) . '&session_id=' . urlencode($existing_session_id)); // Redirect to the existing session page
    exit;
}
// --- End Check ---


// --- Generate Session ID ---
try {
    $session_id = 'sess_' . bin2hex(random_bytes(8)); // More robust unique ID
} catch (Exception $e) {
    error_log("Random generation failed: " . $e->getMessage());
    $errors[] = "Failed to generate secure session data.";
    header('Location: ' . $redirect_url . '&error=' . urlencode(implode(" ", $errors)));
    exit;
}
// Removed OTP generation and expiry calculation

// --- Insert into attendancesessions table ---
// Added location to INSERT statement
$sql_insert = "INSERT INTO attendancesessions
                (session_id, course_id, group_id, initiated_by_user_id, location, ble_id, session_start_time)
                VALUES (?, ?, ?, ?, ?, ?, NOW())"; // Use NOW() for session_start_time

$stmt_insert = $conn->prepare($sql_insert);
$insert_success = false;
if ($stmt_insert) {
    $stmt_insert->bind_param("siiiss", $session_id, $course_id, $group_id, $user_id, $location, $ble_id);
    if ($stmt_insert->execute()) {
        if ($stmt_insert->affected_rows > 0) {
             $insert_success = true;
        } else {
            error_log("DB Insert Warning: No rows affected when inserting session $session_id for location '$location'"); // Added location to log
            $errors[] = "Failed to confirm session start in database."; // Less scary error
        }
    } else {
        error_log("DB Execute Error: Failed to insert attendance session record for $session_id, location '$location': " . $stmt_insert->error); // Added location to log
        $errors[] = "Failed to save session start in database.";
    }
    $stmt_insert->close();
} else {
    error_log("DB Prepare Error: Failed to prepare insert statement for session (location '$location'): " . $conn->error); // Added location to log
    $errors[] = "Database error initiating session.";
}


// --- Redirect based on success ---
if ($insert_success) {
    // Updated success message
    $success_message = urlencode("Attendance session started.");
    header('Location: ' . $redirect_url . '&session_id=' . urlencode($session_id) . '&success=' . $success_message);
    exit;
} else {
    $error_message = urlencode(implode(" ", $errors));
    header('Location: ' . $redirect_url . '&error=' . $error_message);
    exit;
}

?>
