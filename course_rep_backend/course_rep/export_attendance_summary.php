<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session and check login
check_course_rep_login();

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

$errors = [];
$group_details = null;
$selected_course_details = null;
$student_list = [];
$session_list = [];
$attendance_matrix = [];

// Basic validation
if ($group_id <= 0 || $course_id <= 0) {
    die("Invalid group or course specified.");
}

// Verify Rep Manages Group
if (!verify_rep_manages_group($conn, $user_id, $group_id)) {
    die("Permission Denied: You do not manage this group.");
}

// Fetch Group and Course Details (mostly for filename)
$group_details = get_group_details($conn, $group_id);
$selected_course_details = get_course_name_and_code($conn, $course_id); // Reuse existing function

if (!$group_details || !$selected_course_details) {
    die("Could not retrieve group or course details.");
}

// Fetch the detailed attendance data
$detailed_data = get_detailed_attendance_for_course_group($conn, $course_id, $group_id);
$student_list = $detailed_data['students'] ?? [];
$session_list = $detailed_data['sessions'] ?? [];
$attendance_matrix = $detailed_data['attendance_matrix'] ?? [];

if (empty($student_list) || empty($session_list)) {
    // Optional: Redirect back with an error message or show a simple message
    die("No attendance data available to export for this course.");
}

// Generate filename and directory
$export_base = __DIR__ . '/../exports';
$year_month = date('Y-m');
$export_dir = "$export_base/$year_month";
if (!is_dir($export_dir)) {
    mkdir($export_dir, 0777, true);
}
$filename = "attendance_summary_" . preg_replace('/[^a-z0-9_]+/i', '-', $group_details['group_name']) . "_" . preg_replace('/[^a-z0-9_]+/i', '-', $selected_course_details['course_code']) . "_" . date('Y-m-d') . ".csv";
$filepath = "$export_dir/$filename";

// Open file for writing
$output = fopen($filepath, 'w');
if ($output === false) {
    die("Failed to open file for writing.");
}

// --- Write Header Row ---
$header_row = ['Student Name', 'Matric Number', 'Overall (%)']; // Start with student details
foreach ($session_list as $session_info) {
    // Format session date and add location if available
    $header_date = date('Y-m-d H:i', strtotime($session_info['attendance_date']));
    $header_text = $header_date;
    if (!empty($session_info['location'])) {
         $header_text .= ' (' . $session_info['location'] . ')';
    }
    $header_row[] = $header_text;
}
fputcsv($output, $header_row);

// --- Write Data Rows ---
foreach ($student_list as $student_id => $student_info) {
    $row = [
        escape_html($student_info['first_name'] . ' ' . $student_info['last_name']),
        escape_html($student_info['matric_number'] ?? 'N/A'),
         $student_info['overall_attendance_percentage'] ?? '0.0' // Include overall percentage
    ];
    foreach ($session_list as $session_id => $session_info) {
        $status = $attendance_matrix[$student_id][$session_id] ?? '-'; // Default if no record
        $row[] = escape_html($status);
    }
    fputcsv($output, $row);
}

// Close output stream
fclose($output);

// Redirect user to the saved file
$relative_path = str_replace(__DIR__ . '/../', '', $filepath);
$download_url = '/' . str_replace('\\', '/', $relative_path); // For Windows compatibility
header('Location: ' . $download_url);
exit;

?>
