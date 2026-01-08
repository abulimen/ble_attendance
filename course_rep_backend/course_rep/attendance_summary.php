<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; // Include functions

// Start session and check login
check_course_rep_login();

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0; // Added course_id

$errors = [];
$group_details = null;
$assigned_courses = []; // To populate dropdown
$selected_course_details = null; // Details of the selected course
$attendance_summary = []; // Placeholder for summary data

// 1. Validate Group ID
if ($group_id <= 0) {
    $errors[] = "Invalid Group specified.";
}

// 2. Verify Rep Manages Group
if (empty($errors) && !verify_rep_manages_group($conn, $user_id, $group_id)) {
    $errors[] = "Permission Denied: You do not manage this group.";
    $group_id = 0; // Prevent further processing
}

// 3. Fetch Group Details and Summary Data (if valid group and permission)
if (empty($errors) && $group_id > 0) {
    $group_details = get_group_details($conn, $group_id);
    if (!$group_details) {
        $errors[] = "Group details not found.";
    } else {
        // Fetch assigned courses for the dropdown
        $assigned_courses = get_assigned_courses_for_group($conn, $group_id);
        if (empty($assigned_courses)) {
             $errors[] = "No courses are assigned to this group.";
        }

        // If a course is selected, validate it and fetch summary
        if ($course_id > 0) {
            // Validate Selected Course ID against assigned courses
            foreach($assigned_courses as $ac) {
                if ($ac['course_id'] == $course_id) {
                    $selected_course_details = $ac;
                    break;
                }
            }
            if (!$selected_course_details) {
                $errors[] = "Invalid or unassigned course selected for this group.";
                $course_id = 0; // Reset course_id if invalid
            } else {
                // Fetch the DETAILED attendance data using the new function (Corrected Argument Order!)
                $detailed_data = get_detailed_attendance_for_course_group($conn, $course_id, $group_id); // Swapped course_id and group_id
                $student_list = $detailed_data['students'];
                $session_list = $detailed_data['sessions'];
                $attendance_matrix = $detailed_data['attendance_matrix'];
            }
        }
        // If no course is selected yet, data arrays remain empty, prompting course selection later
    }
}

// $possible_statuses is no longer needed for column headers

// Update page title based on whether a course is selected
$page_title_suffix = $selected_course_details ? ' for ' . escape_html($selected_course_details['course_code']) : '';
// $page_title = $group_details ? "Attendance Summary: " . escape_html($group_details['group_name']) . $page_title_suffix : "Attendance Summary"; // Original ternary
if ($group_details) {
    $page_title = "Attendance Summary: " . escape_html($group_details['group_name']) . $page_title_suffix;
} else {
    $page_title = "Attendance Summary";
}


include_once __DIR__ . '/includes/templates/header.php';
?>

<div class="nk-block-head nk-block-head-sm">
    <div class="nk-block-between">
        <div class="nk-block-head-content">
            <h3 class="nk-block-title page-title"><?php echo $page_title ?></h3>
            <div class="nk-block-des text-soft">
                <p>View detailed attendance summary for a selected course.</p>
            </div>
        </div><!-- .nk-block-head-content -->
         <div class="nk-block-head-content">
            <div class="toggle-wrap nk-block-tools-toggle">
                <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="pageMenu"><em class="icon ni ni-menu-alt-r"></em></a>
                <div class="toggle-expand-content" data-content="pageMenu">
                    <ul class="nk-block-tools g-3">
                         <?php if ($selected_course_details): // Show change/export only if a course is selected ?>
                            <li>
                                <!-- Link to the export script, passing group_id and course_id -->
                                <a href="export_attendance_summary.php?group_id=<?php echo $group_id; ?>&course_id=<?php echo $course_id; ?>&download=.csv" class="btn btn-white btn-dim btn-outline-primary" target="_blank">
                                    <em class="icon ni ni-download-cloud"></em><span>Export CSV</span>
                                </a>
                            </li>
                            <li><a href="attendance_summary.php?group_id=<?php echo $group_id; ?>" class="btn btn-white btn-dim btn-outline-light"><em class="icon ni ni-swap"></em><span>Change Course</span></a></li>
                         <?php endif; ?>
                        <li><a href="index.php" class="btn btn-white btn-outline-light"><em class="icon ni ni-arrow-left"></em><span>Back to Dashboard</span></a></li>
                    </ul>
                </div>
            </div><!-- .toggle-wrap -->
        </div><!-- .nk-block-head-content -->
    </div><!-- .nk-block-between -->
</div><!-- .nk-block-head -->

<div class="nk-block">
    <?php include __DIR__ . '/includes/messages.php'; // Corrected path - Display errors/success ?>

    <?php if (!empty($errors) && $group_id > 0 && $course_id <= 0 && empty($assigned_courses)): ?>
         <!-- Special case: Group is valid, but no courses assigned -->
         <div class="alert alert-warning">
             <div class="alert-icon"><em class="icon ni ni-alert-circle"></em></div>
             No courses are assigned to this group. Please assign courses via the 'Manage Courses/Lecturers' page.
         </div>
         <p><a href="index.php" class="btn btn-light">Back to Dashboard</a></p>
    <?php elseif (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Error!</strong> Please resolve the issues below:
             <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo escape_html($err); ?></li>
                <?php endforeach; ?>
            </ul>
         </div>
         <p><a href="index.php" class="btn btn-light">Back to Dashboard</a></p>
        <?php
        // No need to include footer/exit here
        ?>
    <?php endif; ?>


    <?php // --- COURSE SELECTION FORM (Show if group is valid but course not selected) ---
        if ($group_id > 0 && $course_id <= 0 && !empty($assigned_courses) && empty($errors)) : // Added empty($errors) check
    ?>
        <div class="card card-bordered">
            <div class="card-inner">
                <h5 class="card-title">Select Course for Summary</h5>
                <p>Please select the course you want to view the attendance summary for:</p>
                <form action="attendance_summary.php" method="GET">
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
                        <button type="submit" class="btn btn-primary">View Summary</button>
                    </div>
                </form>
            </div>
        </div>

    <?php // --- SUMMARY DISPLAY (Show if group and course are valid) ---
          elseif ($group_details && $selected_course_details && empty($errors)): // Added empty($errors) check
    ?>
        <!-- Selected course info is now in the header tools -->

        <!-- Display Detailed Attendance Timeline -->
        <div class="card card-bordered card-stretch">
             <div class="card-inner-group">
                <div class="card-header">
                    <h5 class="card-title">Detailed Attendance for <?php echo escape_html($selected_course_details['course_code']); ?></h5>
                </div>
                <div class="card-inner p-0">
                    <?php if (!empty($student_list) && !empty($session_list)): ?>
                        <div class="table-responsive">  <!-- Ensure the responsive wrapper is here -->
                            <div class="nk-tb-list nk-tb-ulist is-compact"> <!-- Use DashLite list structure, removed table tags -->
                                <div class="nk-tb-item nk-tb-head"> <!-- Header Row -->
                                    <div class="nk-tb-col"><span class="sub-text">Name</span></div>
                                    <div class="nk-tb-col tb-col-sm"><span class="sub-text">Matric No.</span></div>
                                    <?php foreach ($session_list as $session_id => $session_info): ?>
                                        <div class="nk-tb-col text-center session-header" title="<?php echo escape_html($session_id); ?>">
                                            <span class="sub-text"><?php echo $session_info['display_header']; ?> <br><small>(<?php echo $session_info['location']; ?>)</small></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div><!-- .nk-tb-item -->

                                <?php foreach ($student_list as $student_id => $student_info): ?>
                                    <div class="nk-tb-item"> <!-- Data Row -->
                                        <div class="nk-tb-col">
                                            <span><?php echo escape_html($student_info['first_name'] . ' ' . $student_info['last_name']); ?></span>
                                            <?php if (isset($student_info['enrollment_status']) && $student_info['enrollment_status'] === 'carryover'): ?>
                                                <span class="badge bg-dim bg-danger ms-1" title="Carryover Student">CO</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="nk-tb-col tb-col-sm">
                                            <span><?php echo escape_html($student_info['matric_number'] ?? 'N/A'); ?></span>
                                        </div>
                                        <?php foreach ($session_list as $session_id => $session_info): ?>
                                            <?php
                                                // Look up the status in the matrix
                                                $status = $attendance_matrix[$student_id][$session_id] ?? '-'; // Default to '-' if no record
                                                // Determine badge class based on status
                                                $badge_class = 'bg-light text-dark'; // Default for '-' or unknown
                                                if ($status === 'Present' || $status === 'Present (No Phone)') $badge_class = 'bg-success';
                                                elseif ($status === 'Absent') $badge_class = 'bg-danger';
                                                elseif ($status === 'Excused') $badge_class = 'bg-warning';
                                            ?>
                                            <div class="nk-tb-col text-center" title="<?php echo escape_html($status); ?>">
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo escape_html($status); ?></span>
                                            </div>
                                        <?php endforeach; // End inner foreach ($session_list) ?>
                                    </div><!-- .nk-tb-item -->
                                <?php endforeach; // End outer foreach ($student_list) ?>
                            </div><!-- .nk-tb-list -->
                        </div><!-- .table-responsive -->
                    <?php else: ?>
                         <div class="card-inner">
                            <div class="alert alert-light">No attendance records found for this course and group yet.</div>
                         </div>
                    <?php endif; ?>
                </div><!-- .card-inner -->
            </div><!-- .card-inner-group -->
        </div><!-- .card -->

    <?php elseif (empty($errors)): // Only show this if no other errors occurred ?>
        <div class="alert alert-danger">Could not load group details or course is invalid. Please select a valid group and course from your dashboard.</div>
    <?php endif; ?>

</div><!-- .nk-block -->

<?php include_once __DIR__ . '/includes/templates/footer.php'; ?>
