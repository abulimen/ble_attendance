<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

check_course_rep_login();

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$session_id_param = isset($_GET['session_id']) ? trim($_GET['session_id']) : null;

// Handle BLE ID Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ble_id') {
    $session_id = isset($_POST['session_id']) ? $_POST['session_id'] : null;
    $new_ble_id = isset($_POST['ble_id']) ? trim($_POST['ble_id']) : null;

    if ($session_id && $new_ble_id) {
        $update_sql = "UPDATE attendancesessions SET ble_id = ? WHERE session_id = ? AND session_end_time IS NULL";
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param("ss", $new_ble_id, $session_id);
            if ($stmt->execute()) {
                $success_message = "BLE ID updated successfully!";
            } else {
                $error_message = "Failed to update BLE ID: " . $conn->error;
            }
            $stmt->close();
        }
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['PHP_SELF'] . '?group_id=' . $group_id . '&course_id=' . $course_id . '&success=' . urlencode($success_message));
        exit;
    }
}

// Handle Mark Rep Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_rep_attendance') {
    $rep_user_id = $_SESSION['user_id']; // Assuming course rep is the logged-in user
    $session_id_for_rep = isset($_POST['session_id']) ? $_POST['session_id'] : null;
    $course_id_for_rep = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $group_id_for_rep = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    $current_timestamp = date('Y-m-d H:i:s');
    $page_success_message = ''; // Use local variables for messages within this block
    $page_error_message = '';

    if ($session_id_for_rep && $course_id_for_rep > 0 && $group_id_for_rep > 0) {
        // Check if rep is already marked
        $check_sql = "SELECT attendance_id FROM attendancerecords WHERE session_id = ? AND student_id = ?";
        $stmt_check = $conn->prepare($check_sql);
        if ($stmt_check) {
            $stmt_check->bind_param("si", $session_id_for_rep, $rep_user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                // Rep not marked yet, proceed to mark
                // Get student details (matric number, app id) for the rep
                $student_details_sql = "SELECT matric_number, username AS application_id FROM users WHERE user_id = ?";
                $stmt_student = $conn->prepare($student_details_sql);
                if ($stmt_student) {
                    $stmt_student->bind_param("i", $rep_user_id);
                    $stmt_student->execute();
                    $student_result = $stmt_student->get_result();
                    $student_data = $student_result->fetch_assoc();
                    $stmt_student->close();

                    $matric_number = $student_data['matric_number'] ?? null;
                    $application_id = $student_data['application_id'] ?? null;
                    $identifier_for_record = $matric_number ?: $application_id; // Use matric if available, else app_id

                    if ($identifier_for_record) {
                        $insert_sql = "INSERT INTO attendancerecords (session_id, student_id, course_id, group_id, status, marked_by_user_id, attendance_time, notes)
                                       VALUES (?, ?, ?, ?, 'Present', ?, ?, 'Marked by Self (Class Rep)')";
                        $stmt_insert = $conn->prepare($insert_sql);
                        if ($stmt_insert) {
                            $stmt_insert->bind_param("siiiss", $session_id_for_rep, $rep_user_id, $course_id_for_rep, $group_id_for_rep, $rep_user_id, $current_timestamp);
                            if ($stmt_insert->execute()) {
                                $page_success_message = "Your attendance has been marked successfully!";
                            } else {
                                $page_error_message = "Failed to mark your attendance: " . $conn->error;
                            }
                            $stmt_insert->close();
                        } else {
                             $page_error_message = "Database error (prepare insert rep attendance): " . $conn->error;
                        }
                    } else {
                        $page_error_message = "Could not retrieve your student identifier (Matric/App ID). Cannot mark attendance.";
                    }
                } else {
                    $page_error_message = "Database error (prepare student details for rep): " . $conn->error;
                }
            } else {
                $page_error_message = "Your attendance has already been marked for this session.";
            }
            $stmt_check->close();
        } else {
            $page_error_message = "Database error (prepare check rep attendance): " . $conn->error;
        }

        // Redirect to refresh the page
        $redirect_url = $_SERVER['PHP_SELF'] . '?group_id=' . $group_id_for_rep . '&course_id=' . $course_id_for_rep . '&session_id=' . urlencode($session_id_for_rep);
        if ($page_success_message) {
            $redirect_url .= '&success=' . urlencode($page_success_message);
        }
        // Append error if it exists, even if there was a success message (though unlikely for this flow)
        if ($page_error_message) {
            $redirect_url .= ($page_success_message ? '&' : '&') . 'error=' . urlencode($page_error_message);
        }
        header('Location: ' . $redirect_url);
        exit;
    } else {
        $page_error_message = "Missing required parameters to mark your attendance.";
        // Determine fallback IDs for redirect if initial ones are missing
        $fallback_group_id = $group_id_for_rep ?: ($group_id ?: 0); // Use $group_id from outer scope if $group_id_for_rep is null
        $fallback_course_id = $course_id_for_rep ?: ($course_id ?: 0); // Use $course_id from outer scope
        $fallback_session_id = $session_id_for_rep ?: ($session_id_param ?: ''); // Use $session_id_param from outer scope

        $redirect_url = $_SERVER['PHP_SELF'] . '?group_id=' . $fallback_group_id . '&course_id=' . $fallback_course_id;
        if ($fallback_session_id) {
            $redirect_url .= '&session_id=' . urlencode($fallback_session_id);
        }
        $redirect_url .= '&error=' . urlencode($page_error_message);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Move session validation and redirection BEFORE including header
if ($course_id > 0 && $group_id > 0) {
    $sql_find_session = "SELECT session_id, session_start_time, created_at, location
                         FROM attendancesessions
                         WHERE course_id = ? AND group_id = ? AND session_end_time IS NULL
                         ORDER BY created_at DESC
                         LIMIT 1";
    $stmt_find = $conn->prepare($sql_find_session);

    if ($stmt_find) {
        $stmt_find->bind_param("ii", $course_id, $group_id);
        $stmt_find->execute();
        $result_find = $stmt_find->get_result();
        if ($session_data = $result_find->fetch_assoc()) {
            $active_session_id = $session_data['session_id'];

            // Handle redirections before any output
            if ($session_id_param && $active_session_id && $session_id_param !== $active_session_id) {
                header('Location: take_attendance.php?group_id=' . $group_id . '&course_id=' . $course_id . '&session_id=' . urlencode($active_session_id) . '&error=' . urlencode("Redirected to the correct active session."));
                exit;
            } elseif ($active_session_id && !$session_id_param) {
                // Merge current GET parameters with the new ones
                $current_params = $_GET;
                $new_params = [
                    'group_id' => $group_id,
                    'course_id' => $course_id,
                    'session_id' => urlencode($active_session_id),
                ];
                $merged_params = array_merge($current_params, $new_params);

                // Build the query string
                $query_string = http_build_query($merged_params);

                // Redirect to the new URL with merged parameters
                header('Location: take_attendance.php?' . $query_string);
                exit;
            }
        }
        $stmt_find->close();
    }
}

$errors = [];
$success_message = isset($_GET['success']) ? urldecode($_GET['success']) : '';
$error_message = isset($_GET['error']) ? urldecode($_GET['error']) : '';

// --- Data Fetching and Validation ---
$group_details = null;
$course_details = null;
$session_details = null; // Details of the currently active session
$marked_students = [];   // Students already marked in this session
$students_in_group = []; // All students registered in this group
$assigned_courses = []; // Courses assigned to this group

// 1. Validate Group Input FIRST
if ($group_id <= 0) {
    $errors[] = "Invalid Group specified. Please select a group from the dashboard.";
}

// 2. Verify Rep Manages Group
if (empty($errors) && !verify_rep_manages_group($conn, $user_id, $group_id)) {
    $errors[] = "Permission Denied: You do not manage this group.";
    // No point proceeding if permission denied
    $group_id = 0; // Prevent further potentially incorrect queries
    $course_id = 0;
    $session_id_param = null;
}

// 3. Fetch Group Details and Assigned Courses (if group is valid and permitted)
if (empty($errors)) {
    $group_details = get_group_details($conn, $group_id);
    if (!$group_details) {
        $errors[] = "Group details not found.";
    } else {
        // Fetch courses assigned to *this* group using the new function
        $assigned_courses = get_assigned_courses_for_group($conn, $group_id);
        if (empty($assigned_courses)) {
            $errors[] = "No courses are currently assigned to this group. Please assign courses first.";
        }
    }
    // Removed extra closing brace here
}

// --- Page Setup --- Include Header EARLY before potential course selection form
$page_title = $group_details ? "Take Attendance: " . escape_html($group_details['group_name']) : "Take Attendance";
include_once __DIR__ . '/includes/templates/header.php';
?>
<style>
    .blurred-overlay {
        position: relative;
    }

    .blurred-overlay::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        backdrop-filter: blur(0.6px);
        background-color: rgba(255, 255, 255, 0.6);
        z-index: 1;
        pointer-events: all;
    }

    .blurred-overlay * {
        pointer-events: none;
        user-select: none;
    }

    .overlay-alert {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 2;
        background: #ffe6e6;
        border: 1px solid #ffcccc;
        color: #900;
        padding: 20px 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        text-align: center;
        max-width: 90%;
        font-weight: 600;
    }

    /* Custom Styles for Modern Look */
    body {
        font-family: 'Nunito', sans-serif; /* Assuming Nunito is available or loaded by the template */
        background-color: #f5f6fa; /* Light grey background for the page */
        color: #364a63; /* Default text color */
    }

    .nk-block-head {
        padding-bottom: 1.5rem;
    }

    .page-title {
        font-weight: 600;
        color: #364a63;
    }

    .card {
        border: none; /* Remove default border if any */
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); /* Softer shadow */
        border-radius: 0.5rem; /* More rounded corners */
        margin-bottom: 1.5rem; /* Consistent spacing */
    }
    .card-header {
        background-color: #f8f9fa; /* Light header background */
        border-bottom: 1px solid #e9ecef; /* Subtle border */
        padding: 1rem 1.25rem;
    }
    .card-title {
        font-weight: 500;
        color: #364a63;
    }
    .card-inner {
        padding: 1.25rem;
    }

    .btn {
        border-radius: 0.375rem; /* Slightly more rounded buttons */
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }
    .btn-primary {
        background-color: #007bff; /* Standard primary color */
        border-color: #007bff;
    }
    .btn-primary:hover {
        background-color: #0069d9;
        border-color: #0062cc;
    }
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }
     .btn-success:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }
    .btn-outline-light {
        border-color: #dbdfea;
        color: #526484;
    }
    .btn-outline-light:hover {
        background-color: #e5e9f2;
        color: #364a63;
    }


    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #526484;
    }
    .form-control, .form-select {
        border-radius: 0.375rem;
        border: 1px solid #dbdfea;
        padding: 0.5rem 0.75rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control:focus, .form-select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-note {
        font-size: 0.875em;
        color: #8094ae;
    }

    .alert {
        border-radius: 0.375rem;
        padding: 1rem 1.25rem;
    }
    .alert-info {
        background-color: #e6f7ff;
        border-color: #b3e6ff;
        color: #005c99;
    }
    .alert-info .alert-heading {
        color: #004c80;
    }
    .alert-info code {
        background-color: rgba(0,0,0,0.05);
        padding: 0.2em 0.4em;
        border-radius: 3px;
    }

    .session-actions {
        display: flex;
        gap: 0.75rem; /* Space between buttons */
        flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        margin-top: 1rem; /* Space above the buttons */
    }
    .session-actions .btn {
        flex-grow: 1; /* Allow buttons to grow and fill space */
    }

    #camera-container {
        border: 1px solid #dbdfea;
        padding: 1rem;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
    }
    #camera-stream, #snapshot-canvas {
        border-radius: 0.25rem;
    }

    .nk-tb-list {
        border: 1px solid #e9ecef;
        border-radius: 0.375rem;
    }
    .nk-tb-item.nk-tb-head {
        background-color: #f8f9fa;
        font-weight: 500;
    }
    .nk-tb-item {
        border-bottom: 1px solid #e9ecef;
    }
    .nk-tb-item:last-child {
        border-bottom: none;
    }
    .table-responsive {
        margin-top: 1rem;
    }
    .custom-table th {
        background-color: #f8f9fa;
        font-weight: 500;
    }

    /* Responsive adjustments */
    @media (max-width: 767px) {
        .nk-block-between {
            flex-direction: column;
            align-items: flex-start;
        }
        .nk-block-head-content:last-child {
            margin-top: 1rem;
        }
        .session-actions .btn {
            width: 100%; /* Make buttons full width on small screens */
            margin-bottom: 0.5rem;
        }
        .session-actions .btn:last-child {
            margin-bottom: 0;
        }
        .alert-info.d-flex {
            flex-direction: column;
            align-items: flex-start !important; /* Override inline style */
        }
        .alert-info.d-flex > div:last-child { /* This targets the session-actions div container */
            width: 100%;
            margin-top: 1rem;
        }
        #manual-attendance-form .row .col-md-4 {
            margin-bottom: 1rem; /* Add space between form elements on mobile */
        }
    }
    @media (max-width: 575px) {
        .nk-block-head-sm .nk-block-title {
            font-size: 1.5rem;
        }
        .page-title {
             font-size: 1.25rem;
        }
        .card-inner {
            padding: 1rem;
        }
        .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
    }

</style>

<div class="nk-block-head nk-block-head-sm">
    <div class="nk-block-between">
        <div class="nk-block-head-content">
            <h3 class="nk-block-title page-title"><?php echo $page_title ?></h3>
            <div class="nk-block-des text-soft">
                <p>Select a course and manage attendance sessions.</p>
            </div>
        </div><!-- .nk-block-head-content -->
        <div class="nk-block-head-content">
            <div class="toggle-wrap nk-block-tools-toggle">
                <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="pageMenu"><em class="icon ni ni-menu-alt-r"></em></a>
                <div class="toggle-expand-content" data-content="pageMenu">
                    <ul class="nk-block-tools g-3">
                        <li><a href="index.php" class="btn btn-white btn-outline-light"><em class="icon ni ni-arrow-left"></em><span>Back to Dashboard</span></a></li>
                    </ul>
                </div>
            </div><!-- .toggle-wrap -->
        </div><!-- .nk-block-head-content -->
    </div><!-- .nk-block-between -->
</div><!-- .nk-block-head -->

<div class="nk-block">
    <?php include __DIR__ . '/includes/messages.php'; // Display errors/success messages - Corrected Path 
    ?>

    <?php if (!empty($errors)): ?>
        <!-- Display critical errors related to group validation and stop -->
        <div class="alert alert-danger">
            <strong>Error!</strong> Please resolve the issues below:
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo escape_html($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p><a href="index.php" class="btn btn-light">Back to Dashboard</a></p>
        <?php
        // No need to include footer/exit here, let the main template handle it
        ?>
    <?php endif; ?>

    <?php // --- COURSE SELECTION FORM ---
    // Show this form ONLY if a course_id has NOT been selected/passed yet
    if ($course_id <= 0 && !empty($assigned_courses) && empty($errors)) : // Added empty($errors) check
    ?>
        <div class="card card-bordered">
            <div class="card-inner">
                <h5 class="card-title">Select Course</h5>
                <p>Please select the course you want to take attendance for:</p>
                <form action="take_attendance.php" method="GET">
                    <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                    <div class="form-group">
                        <label class="form-label" for="course_selection">Course:</label>
                        <div class="form-control-wrap">
                            <select id="course_selection" name="course_id" class="form-select js-select2" data-search="on" required>
                                <option value="">-- Select a Course --</option>
                                <?php foreach ($assigned_courses as $course): ?>
                                    <option value="<?php echo escape_html($course['course_id']); ?>">
                                        <?php echo escape_html($course['course_code'] . ' - ' . $course['course_name'] . ' (Lecturer: ' . $course['lecturer_first_name'] . ' ' . $course['lecturer_last_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Select Course</button>
                    </div>
                </form>
            </div>
        </div>

    <?php // --- ATTENDANCE MANAGEMENT SECTION ---
    // This part now ONLY executes if a valid course_id HAS been selected/passed
    elseif ($course_id > 0 && $group_id > 0 && !empty($assigned_courses)) :

        // 1. Validate Selected Course ID against assigned courses (Security Check)
        $selected_course_details = null;
        foreach ($assigned_courses as $ac) {
            if ($ac['course_id'] == $course_id) {
                $selected_course_details = $ac;
                break;
            }
        }
        if (!$selected_course_details) {
            $errors[] = "Invalid or unassigned course selected for this group.";
            // Display error message (already handled by the messages include)
        }

        // Reset potential errors before checking sessions/students if course valid
        $session_details = null;
        $marked_students = [];
        $eligible_students = []; // Renamed from $students_in_group

        // 2. Find Active Session (where session_end_time is NULL)
        $active_session_id = null;
        if (empty($errors)) { // Proceed only if course is valid
            // Fetch location along with other session details
            $sql_find_session = "SELECT session_id, session_start_time, created_at, location, ble_id
                                     FROM attendancesessions
                                     WHERE course_id = ? AND group_id = ? AND session_end_time IS NULL
                                     ORDER BY created_at DESC
                                     LIMIT 1";
            $stmt_find = $conn->prepare($sql_find_session);

            if ($stmt_find) {
                $stmt_find->bind_param("ii", $course_id, $group_id);
                $stmt_find->execute();
                $result_find = $stmt_find->get_result();
                if ($session_data = $result_find->fetch_assoc()) {
                    $session_details = $session_data;
                    $active_session_id = $session_details['session_id'];
                }
                $stmt_find->close();

                // Validate passed session ID against found active session ID if both exist
                if ($session_id_param && $active_session_id && $session_id_param !== $active_session_id) {
                    header('Location: take_attendance.php?group_id=' . $group_id . '&course_id=' . $course_id . '&session_id=' . urlencode($active_session_id) . '&error=' . urlencode("Redirected to the correct active session."));
                    exit;
                } elseif (!$active_session_id && $session_id_param) {
                    $session_details = null;
                    $error_message .= " The specified session ($session_id_param) is no longer active.";
                } elseif ($active_session_id && !$session_id_param) {
                    header('Location: take_attendance.php?group_id=' . $group_id . '&course_id=' . $course_id . '&session_id=' . urlencode($active_session_id));
                    exit;
                }
            } else {
                $errors[] = "Database error checking for active sessions: " . $conn->error;
                $error_message .= ' ' . end($errors);
            }

            // If we have an active session, fetch marked students and eligible students
            if ($active_session_id) {
                $marked_students = get_attendance_for_session($conn, $active_session_id);
                $eligible_students = get_eligible_students_for_session($conn, $course_id, $group_id);
            }
        } // end if(empty($errors)) for session finding
    ?>
        <!-- Display Selected Course Info -->
        <div class="card card-bordered mb-4">
            <div class="card-inner">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Selected Course: <?php echo escape_html($selected_course_details['course_code'] . ' - ' . $selected_course_details['course_name']); ?></h5>
                        <p class="card-text mb-0">Lecturer: <?php echo escape_html($selected_course_details['lecturer_first_name'] . ' ' . $selected_course_details['lecturer_last_name']); ?></p>
                    </div>
                    <a href="take_attendance.php?group_id=<?php echo $group_id; ?>" class="btn btn-sm btn-outline-light">Change Course</a>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <!-- Display session/course validation errors here before proceeding -->
            <div class="alert alert-danger">
                <strong>Error!</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo escape_html($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (!empty($error_message)): ?>
            <!-- Display session/course validation errors here before proceeding -->
            <div class="alert alert-danger">
                <strong>Error!</strong>
                <ul>
                        <li><?php echo escape_html($error_message); ?></li>
                </ul>
            </div>
        <?php endif; ?>
            <?php if ($session_details && $session_details['session_id']): // If a session is active 
            ?>
                <div class="alert alert-info"> <!-- Removed d-flex and align-items-center for block layout, let CSS handle flex for children -->
                    <div class="session-details-content"> <!-- Wrapper for text content -->
                        <h4 class="alert-heading">Active Session</h4>
                        <p class="mb-1"><strong>Started:</strong>
                            <?php
                                $serverTz = date_default_timezone_get();
                                echo escape_html(date('g:i A T \o\n M d, Y', strtotime($session_details['created_at'])));
                            ?>
                        </p>
                        <p class="mb-1"><strong>Session ID:</strong> <code><?php echo escape_html($session_details['session_id']); ?></code></p>
                        <p class="mb-1"><strong>Venue:</strong> <?php echo $session_details['location'] ? escape_html($session_details['location']) : '<em>Not Specified</em>'; ?></p>
                        <p class="mb-2"> <!-- Added mb-2 for spacing before BLE ID update button -->
                            <strong>BLE ID:</strong> <span id="ble-id-display"><?php echo isset($session_details['ble_id']) && $session_details['ble_id'] !== '' ? escape_html($session_details['ble_id']) : '<em>Not Set</em>'; ?></span>
                            <button type="button" class="btn btn-xs btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#updateBleIdModal">
                                <em class="icon ni ni-edit-alt"></em> Update
                            </button>
                        </p>
                    </div>

                    <!-- BLE ID Update Modal (no changes here, just for context) -->
                    <div class="modal fade" id="updateBleIdModal" tabindex="-1" aria-labelledby="updateBleIdModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="updateBleIdModalLabel">Update BLE ID</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="update_ble_id">
                                        <input type="hidden" name="session_id" value="<?php echo escape_html($session_details['session_id']); ?>">
                                        <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                                        <input type="hidden" name="course_id" value="<?php echo escape_html($course_id); ?>">
                                        <div class="form-group">
                                            <label class="form-label" for="new_ble_id">New BLE ID:</label>
                                            <div class="form-control-wrap">
                                                <input type="text" class="form-control" id="new_ble_id" name="ble_id" placeholder="Enter new BLE ID" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- End BLE ID Update Modal -->

                    <div class="session-actions"> <!-- Removed mt-3, CSS will handle spacing -->
                        <form action="end_attendance_session.php" method="POST" style="display:contents;"> <!-- display:contents to allow flex on parent -->
                            <input type="hidden" name="session_id" value="<?php echo escape_html($session_details['session_id']); ?>">
                            <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                            <input type="hidden" name="course_id" value="<?php echo escape_html($course_id); ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to end this attendance session?');"><em class="icon ni ni-stop-circle"></em><span>End Session</span></button>
                        </form>
                        <form action="<?php echo escape_html($_SERVER['PHP_SELF']); ?>" method="POST" style="display:contents;"> <!-- display:contents -->
                            <input type="hidden" name="action" value="mark_rep_attendance">
                            <input type="hidden" name="session_id" value="<?php echo escape_html($session_details['session_id']); ?>">
                            <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                            <input type="hidden" name="course_id" value="<?php echo escape_html($course_id); ?>">
                            <button type="submit" class="btn btn-success"><em class="icon ni ni-user-check"></em><span>Mark My Attendance</span></button>
                        </form>
                    </div>
                </div>

                <div class="card card-bordered mt-4">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">Manually Mark Attendance (No Phone/Exception)</h5>
                    </div>
                    <div class="card-inner">
                        <form action="mark_manual_attendance.php" method="POST" enctype="multipart/form-data" class="form-validate" id="manual-attendance-form">
                            <input type="hidden" name="session_id" value="<?php echo escape_html($session_details['session_id']); ?>">
                            <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                            <input type="hidden" name="course_id" value="<?php echo escape_html($course_id); ?>">
                            <input type="hidden" name="photo_data" id="photo_data">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label" for="student_identifier">Student Matric No. / App ID:</label>
                                        <div class="form-control-wrap">
                                            <input type="text" class="form-control" id="student_identifier" name="student_identifier" required placeholder="Enter Matric No or App ID">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Live Photo:</label>
                                        <div class="form-control-wrap">
                                            <div id="camera-container" style="text-align:center;">
                                                <video id="camera-stream" width="220" height="165" autoplay playsinline style="border-radius:8px; background:#222;"></video>
                                                <canvas id="snapshot-canvas" width="220" height="165" style="display:none;"></canvas>
                                                <div style="margin-top:8px;">
                                                    <button type="button" class="btn btn-primary" id="take-photo-btn">Take Photo</button>
                                                    <button type="button" class="btn btn-secondary" id="retake-photo-btn" style="display:none;">Retake</button>
                                                </div>
                                                <div id="photo-preview-msg" style="margin-top:6px; color:#28a745; font-weight:500; display:none;">Photo captured!</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label" for="notes">Notes (Optional):</label>
                                        <div class="form-control-wrap">
                                            <textarea class="form-control no-resize" id="notes" name="notes" rows="1"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary" id="manual-mark-btn">Mark Manually</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                // Live photo capture logic
                (function() {
                    const video = document.getElementById('camera-stream');
                    const canvas = document.getElementById('snapshot-canvas');
                    const takeBtn = document.getElementById('take-photo-btn');
                    const retakeBtn = document.getElementById('retake-photo-btn');
                    const photoDataInput = document.getElementById('photo_data');
                    const previewMsg = document.getElementById('photo-preview-msg');
                    const form = document.getElementById('manual-attendance-form');
                    let stream = null;
                    let photoTaken = false;

                    // Start camera
                    function startCamera() {
                        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                            navigator.mediaDevices.getUserMedia({ video: true })
                                .then(function(s) {
                                    stream = s;
                                    video.srcObject = stream;
                                    video.play();
                                })
                                .catch(function(err) {
                                    alert('Could not access camera: ' + err.message);
                                });
                        } else {
                            alert('Camera not supported on this device/browser.');
                        }
                    }

                    // Take photo
                    takeBtn.addEventListener('click', function() {
                        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                        const dataUrl = canvas.toDataURL('image/png');
                        photoDataInput.value = dataUrl;
                        video.style.display = 'none';
                        canvas.style.display = '';
                        takeBtn.style.display = 'none';
                        retakeBtn.style.display = '';
                        previewMsg.style.display = '';
                        photoTaken = true;
                    });

                    // Retake photo
                    retakeBtn.addEventListener('click', function() {
                        photoDataInput.value = '';
                        video.style.display = '';
                        canvas.style.display = 'none';
                        takeBtn.style.display = '';
                        retakeBtn.style.display = 'none';
                        previewMsg.style.display = 'none';
                        photoTaken = false;
                    });

                    // Prevent submit if no photo
                    form.addEventListener('submit', function(e) {
                        if (!photoTaken) {
                            e.preventDefault();
                            alert('Please take a live photo before submitting.');
                        }
                    });

                    startCamera();
                })();
                </script>

                <div class="card card-bordered card-stretch mt-4">
                    <div class="card-inner-group">
                        <div class="card-header border-bottom">
                            <h5 class="card-title">Students Marked in this Session (<?php echo count($marked_students); ?>)</h5>
                        </div>
                        <div class="card-inner p-0">
                            <?php if (!empty($marked_students)): ?>
                                <div class="nk-tb-list nk-tb-ulist">
                                    <div class="nk-tb-item nk-tb-head">
                                        <div class="nk-tb-col tb-col-sm"><span class="sub-text">Time</span></div>
                                        <div class="nk-tb-col"><span class="sub-text">Matric No.</span></div>
                                        <div class="nk-tb-col tb-col-lg"><span class="sub-text">Name</span></div>
                                        <div class="nk-tb-col tb-col-md"><span class="sub-text">Status</span></div>
                                        <div class="nk-tb-col tb-col-lg"><span class="sub-text">Notes</span></div>
                                        <div class="nk-tb-col"><span class="sub-text">Photo</span></div>
                                    </div><!-- .nk-tb-item -->
                                    <?php foreach ($marked_students as $record): ?>
                                        <div class="nk-tb-item">
                                            <div class="nk-tb-col tb-col-sm"><span><?php echo escape_html(date('g:i A', strtotime($record['created_at']))); ?></span></div>
                                            <div class="nk-tb-col"><span><?php echo escape_html($record['matric_number']); ?></span></div>
                                            <div class="nk-tb-col tb-col-lg"><span><?php echo escape_html($record['first_name'] . ' ' . $record['last_name']); ?></span></div>
                                            <div class="nk-tb-col tb-col-md"><span class="badge bg-success"><?php echo escape_html($record['status']); ?></span></div>
                                            <div class="nk-tb-col tb-col-lg"><span><?php echo escape_html($record['notes']); ?></span></div>
                                            <div class="nk-tb-col">
                                                <?php if ($record['photo_reference']): ?>
                                                    <a href="<?php echo escape_html($record['photo_reference']); ?>" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </div>
                                        </div><!-- .nk-tb-item -->
                                    <?php endforeach; ?>
                                </div><!-- .nk-tb-list -->
                            <?php else: ?>
                                <div class="card-inner">
                                    <p>No students marked manually or automatically yet in this session.</p>
                                </div>
                            <?php endif; ?>
                        </div><!-- .card-inner -->
                    </div><!-- .card-inner-group -->
                </div><!-- .card -->

                <div class="card card-bordered card-stretch mt-4">
                    <div class="card-inner-group">
                        <div class="card-header border-bottom">
                            <h5 class="card-title">Eligible Students for this Session (<?php echo count($eligible_students); ?>)</h5>
                        </div>
                        <div class="card-inner p-0">
                            <?php if (!empty($eligible_students)):
                                // Create a quick lookup map for marked students status
                                $marked_status_map = [];
                                foreach ($marked_students as $marked) {
                                    $marked_status_map[$marked['student_id']] = $marked['status'];
                                }
                            ?>
                                <!-- Search and Pagination Controls -->
                                <!-- Search and Pagination Controls -->
                                <div class="d-flex flex-column flex-md-row flex-wrap align-items-md-center justify-content-md-between gap-2 mb-3 p-3 bg-light border rounded">
                                    <input type="text" id="student-search-box" class="form-control form-control-sm" placeholder="Search students..." style="max-width: 300px;">
                                    <div id="student-pagination"></div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-striped custom-table" id="eligible-students-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Matric No. / App ID</th>
                                                <th>Enrollment Type</th>
                                                <th>Status for this Session</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eligible_students as $student):
                                                $status = $marked_status_map[$student['user_id']] ?? 'Not Marked';
                                                $status_badge_class = 'bg-light text-dark';
                                                if ($status === 'Present' || $status === 'Present (No Phone)') {
                                                    $status_badge_class = 'bg-success';
                                                } elseif ($status === 'Absent') {
                                                    $status_badge_class = 'bg-danger';
                                                } elseif ($status === 'Late') {
                                                    $status_badge_class = 'bg-warning';
                                                }
                                            ?>
                                                <tr class="eligible-student-row">
                                                    <td><?= escape_html($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                                    <td><?= escape_html($student['matric_number'] ?? $student['application_id']) ?></td>
                                                    <td><span class="tb-status text-primary"><?= escape_html(ucfirst($student['enrollment_status'])) ?></span></td>
                                                    <td><span class="badge <?= $status_badge_class ?>"><?= escape_html($status) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <style>
                                    .pagination {
                                        display: flex;
                                        justify-content: center;
                                        margin: 20px 0 0 0;
                                        padding: 0;
                                        list-style: none;
                                    }

                                    .pagination li {
                                        margin: 0 3px;
                                    }

                                    .pagination li a,
                                    .pagination li span {
                                        display: block;
                                        padding: 0.375rem 0.75rem; /* Adjusted padding */
                                        color: #007bff;
                                        background: #fff; /* White background for items */
                                        border: 1px solid #dee2e6;
                                        border-radius: 0.25rem; /* Standard border radius */
                                        text-decoration: none;
                                        cursor: pointer;
                                        font-size: 0.875rem;
                                        transition: all 0.2s ease;
                                    }
                                     .pagination li a:hover,
                                    .pagination li span:hover:not(.active) {
                                        background-color: #e9ecef;
                                    }

                                    .pagination li.active span {
                                        background: #007bff;
                                        color: #fff;
                                        border-color: #007bff;
                                        cursor: default;
                                    }

                                    .pagination li.disabled span {
                                        color: #6c757d; /* Muted color */
                                        background: #f8f9fa; /* Lighter background for disabled */
                                        border-color: #dee2e6;
                                        cursor: not-allowed;
                                    }
                                    /* No specific style for #student-search-box here, using general form-control styles */
                                </style>
                                <script>
                                    // --- Pagination and Search for Eligible Students Table ---
                                    (function() {
                                        const rows = Array.from(document.querySelectorAll('#eligible-students-table tbody tr.eligible-student-row'));
                                        const searchBox = document.getElementById('student-search-box');
                                        const paginationContainer = document.getElementById('student-pagination');
                                        const rowsPerPage = 10;
                                        let filteredRows = rows;
                                        let currentPage = 1;

                                        function renderTable() {
                                            rows.forEach(row => row.style.display = 'none');
                                            const start = (currentPage - 1) * rowsPerPage;
                                            const end = start + rowsPerPage;
                                            filteredRows.slice(start, end).forEach(row => row.style.display = '');
                                            renderPagination();
                                        }

                                        function renderPagination() {
                                            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
                                            let html = '<ul class="pagination">';
                                            html += `<li class="${currentPage === 1 ? 'disabled' : ''}"><span onclick="${currentPage > 1 ? 'window.changeStudentPage(' + (currentPage - 1) + ')' : ''}">&laquo;</span></li>`;
                                            for (let i = 1; i <= totalPages; i++) {
                                                html += `<li class="${i === currentPage ? 'active' : ''}"><span onclick="window.changeStudentPage(${i})">${i}</span></li>`;
                                            }
                                            html += `<li class="${currentPage === totalPages ? 'disabled' : ''}"><span onclick="${currentPage < totalPages ? 'window.changeStudentPage(' + (currentPage + 1) + ')' : ''}">&raquo;</span></li>`;
                                            html += '</ul>';
                                            paginationContainer.innerHTML = html;
                                        }

                                        window.changeStudentPage = function(page) {
                                            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
                                            if (page < 1 || page > totalPages) return;
                                            currentPage = page;
                                            renderTable();
                                        };

                                        searchBox.addEventListener('input', function() {
                                            const val = this.value.trim().toLowerCase();
                                            filteredRows = rows.filter(row => {
                                                return Array.from(row.cells).some(cell => cell.textContent.toLowerCase().includes(val));
                                            });
                                            currentPage = 1;
                                            renderTable();
                                        });

                                        renderTable();
                                    })();
                                </script>
                            <?php else: ?>
                                <div class="card-inner">
                                    <p>No students (regular or carryover) are currently eligible for this course in this group.</p>
                                </div>
                            <?php endif; ?>
                        </div><!-- .card-inner -->
                    </div><!-- .card-inner-group -->
                </div><!-- .card -->


            <?php else: // If no active session for the selected course 
            ?>
                <div class="card card-bordered">
                    <div class="card-inner">
                        <div class="alert alert-light">There is currently no active attendance session for this course and group.</div>
                        <form action="start_attendance_session.php" method="POST" class="form-validate">
                            <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                            <input type="hidden" name="course_id" value="<?php echo escape_html($course_id); ?>">
                            <div class="form-group">
                                <label class="form-label" for="session_location">Venue/Location:</label>
                                <div class="form-control-wrap">
                                    <input type="text" class="form-control" id="session_location" name="location" placeholder="e.g: A008, CILT, Lab B .etc" required autocomplete="nope">
                                </div>
                                <div class="form-note">Enter the location where this class session is being held.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ble_id">BLE ID:</label>
                                <div class="form-control-wrap">
                                    <input type="text" class="form-control" id="ble_id" name="ble_id" placeholder="e.g: XA-BLE_*********************" required autocomplete="nope">
                                </div>
                                <div class="form-note">Paste the BLE ID From the App.</div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary"><em class="icon ni ni-play-circle"></em><span>Start New Attendance Session</span></button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
    <?php endif; // End elseif for $course_id > 0 
    ?>

</div><!-- .nk-block -->

<?php include_once __DIR__ . '/includes/templates/footer.php'; ?>
