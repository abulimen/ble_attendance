<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session and check login
check_course_rep_login();

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$errors = [];
$success_message = '';
$group_details = null;
$department_id = null;
$department_lecturers = [];
$current_assignments = []; // Keep for the processing logic below, but list is used for display
$assigned_course_ids_lookup = []; // To store IDs of assigned courses for filtering
$available_courses = []; // Courses not yet assigned to this group

// 1. Validate Group ID
if ($group_id <= 0) {
    $errors[] = "Invalid Group specified.";
}

// 2. Verify Rep Manages Group
if (empty($errors) && !verify_rep_manages_group($conn, $user_id, $group_id)) {
    $errors[] = "Permission Denied: You do not manage this group.";
    $group_id = 0; // Prevent further processing
}

// 3. Fetch Group Details and Department Info (if valid group and permission)
if (empty($errors) && $group_id > 0) {
    $group_details = get_group_details($conn, $group_id);
    if (!$group_details) {
        $errors[] = "Group details not found.";
    } else {
        $department_id = $group_details['department_id'];
        if (!$department_id) {
            $errors[] = "Could not determine the department for this group.";
        }
    }
}

// 4. Fetch ALL Lecturers, Current Assignments for this group, and ALL Courses (if group is valid)
if (empty($errors) && $group_id > 0) {
    $all_lecturers = get_all_lecturers($conn); // Fetch all lecturers
    $current_assignments_list = get_group_course_lecturer_assignments($conn, $group_id);
    $all_courses = get_all_courses($conn);

    // Create a lookup array of assigned course IDs for easier filtering
    $assigned_course_ids_lookup = !empty($current_assignments_list) ? array_column($current_assignments_list, 'course_id') : [];

    // Determine available courses
    $available_courses = array_filter($all_courses, function($course) use ($assigned_course_ids_lookup) {
        // Check if the course ID is NOT in the lookup array of assigned IDs
        return !in_array($course['course_id'], $assigned_course_ids_lookup);
    });
} else {
    // Initialize to prevent errors later if group/dept validation failed
    $current_assignments_list = []; // Use the new variable name
    $all_lecturers = [];
    $current_assignments = [];
    $all_courses = [];
    $available_courses = [];
}


// 5. Handle Form Submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && $group_id > 0 && $department_id) {
    // Security check: Verify the submitted group_id matches the one in the URL/session context
    if (!isset($_POST['group_id']) || (int)$_POST['group_id'] !== $group_id) {
        $errors[] = "Form submission error. Group mismatch.";
    } else {
        // Directly build the array of assignments to save
        $assignments_to_save = [];

        // Process updates from the table (existing assignments)
        if (isset($_POST['assignments']) && is_array($_POST['assignments'])) {
            foreach ($_POST['assignments'] as $course_id_str => $lecturer_id_str) {
                $course_id = (int)$course_id_str;
                $lecturer_id = (int)$lecturer_id_str;
                // Keep only valid assignments (lecturer selected)
                if ($course_id > 0 && $lecturer_id > 0) {
                    $assignments_to_save[$course_id] = $lecturer_id;
                }
                // If lecturer_id is 0, it signifies an unassignment.
                // Since save_group_course_lecturer_assignments deletes all previous entries first,
                // simply *not* including this course_id in $assignments_to_save effectively removes it.
            }
        }

        // Process the *new* assignment from the hidden inputs populated by JS
        // These values are only present if a course/lecturer was selected in the "Add New" section
        if (isset($_POST['new_course_id']) && !empty($_POST['new_course_id']) && isset($_POST['new_lecturer_id']) && !empty($_POST['new_lecturer_id'])) {
            $new_course_id = (int)$_POST['new_course_id'];
            $new_lecturer_id = (int)$_POST['new_lecturer_id'];

            // Only add if both a valid course and a valid lecturer (not '0') were selected
            if ($new_course_id > 0 && $new_lecturer_id > 0) {
                 // Add or overwrite any assignment for this course_id coming from the 'assignments' array.
                 // The newly added assignment takes precedence.
                $assignments_to_save[$new_course_id] = $new_lecturer_id;
            }
        }

        // --- DEBUG LOGGING ---
        error_log("Attempting to save assignments for group_id: " . $group_id);
        error_log("Final assignments to save: " . print_r($assignments_to_save, true));
        // --- END DEBUG LOGGING ---

        // Save the final set of assignments (only includes course/lecturer pairs where lecturer_id > 0)
        if (save_group_course_lecturer_assignments($conn, $group_id, $assignments_to_save)) {
            $success_message = "Course assignments updated successfully!";

             // --- Refresh data after successful save ---
             // Re-fetch the current assignments list to reflect changes
             $current_assignments_list = get_group_course_lecturer_assignments($conn, $group_id);
             // Re-fetch all courses (in case assignments affect availability logic, though usually not)
             $all_courses = get_all_courses($conn);
             // Re-create the lookup array of assigned course IDs for filtering available courses
             $assigned_course_ids_lookup = !empty($current_assignments_list) ? array_column($current_assignments_list, 'course_id') : [];
             // Re-filter available courses based on the updated assignments
             $available_courses = array_filter($all_courses, function($course) use ($assigned_course_ids_lookup) {
                 return !in_array($course['course_id'], $assigned_course_ids_lookup);
             });
             // No need to reset $final_assignments as it's not used in the refined logic.

        } else {
             $errors[] = "Failed to save course assignments. Please check logs or try again.";
        }
    }
}


$page_title = $group_details ? "Manage Courses: " . escape_html($group_details['group_name']) : "Manage Courses";
include_once __DIR__ . '/includes/templates/header.php';
?>
<style>
    /* WRAPPER for gradient scroll indicator */
.scroll-wrapper {
    position: relative;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    overflow: hidden;
}

/* Fixed gradient on the right to indicate scroll */
.scroll-wrapper::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 100%;
    background: linear-gradient(to left, #ffffff, rgba(255, 255, 255, 0));
    pointer-events: none;
    z-index: 5;
}

/* TABLE CONTAINER - horizontally scrollable */
.custom-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

/* TABLE */
.custom-table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
    color: #333;
}

/* TABLE HEAD */
.custom-table thead {
    background-color: #f5f7f9;
    color: #222;
}

.custom-table thead th {
    text-align: left;
    font-weight: 600;
    padding: 14px 18px;
    white-space: nowrap;
    border-bottom: 1px solid #dee2e6;
}

/* TABLE BODY */
.custom-table tbody td {
    padding: 12px 18px;
    border-bottom: 1px solid #f0f0f0;
    white-space: nowrap;
    font-size: 15px;
}

/* TABLE ROW HOVER */
.custom-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* CHECKBOX COLUMN */
.custom-table th.select-col,
.custom-table td.select-col {
    width: 50px;
    text-align: center;
}

/* ACTIONS COLUMN */
.custom-table-actions a {
    color: #1d72b8;
    font-size: 15px;
    text-decoration: none;
}

.custom-table-actions a:hover {
    text-decoration: underline;
}

/* RESPONSIVE FONT SIZE */
@media (max-width: 768px) {
    .custom-table thead th,
    .custom-table tbody td {
        font-size: 14px;
        padding: 10px 12px;
    }
}

</style>
<div class="nk-block-head nk-block-head-sm">
    <div class="nk-block-between">
        <div class="nk-block-head-content">
            <h3 class="nk-block-title page-title"><?php echo $page_title ?></h3>
            <div class="nk-block-des text-soft">
                <p>Assign lecturers to courses for group: <strong><?php echo escape_html($group_details['group_name'] ?? 'N/A'); ?></strong></p>
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
    <?php include __DIR__ . '/includes/messages.php'; // Display errors & success ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <p>Please resolve the errors above or select a valid group from the dashboard.</p>
        </div>
        <?php
        // No need to include footer and exit here, let the main footer handle it
        ?>
    <?php elseif ($group_details && $department_id): ?>
        <form action="manage_courses.php?group_id=<?php echo $group_id ?>" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id ?>">

            <div class="card card-bordered card-stretch mb-4">
                <div class="card-inner-group">
                    <div class="card-header">
                        <h5 class="card-title">Assigned Courses</h5>
                    </div>
                    <div class="card-inner p-0">
                        <?php if (!empty($current_assignments_list)): ?>
<div class="scroll-wrapper">
    <div class="custom-table-container">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Assigned Lecturer</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_assignments_list as $assignment): 
                    $course_id = $assignment['course_id'];
                    $lecturer_id = $assignment['lecturer_id'];
                ?>
                <tr>
                    <td><?php echo escape_html($assignment['course_code']); ?></td>
                    <td><?php echo escape_html($assignment['course_name']); ?></td>
                    <td>
                        <select name="assignments[<?php echo $course_id ?>]" class="form-select form-select-sm" data-search="on">
                            <option value="0">-- Unassign / Select Lecturer --</option>
                            <?php foreach ($all_lecturers as $lecturer): ?>
                                <option value="<?php echo $lecturer['user_id'] ?>" <?php echo ($lecturer['user_id'] == $lecturer_id) ? 'selected' : '' ?>>
                                    <?php echo escape_html($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' (' . $lecturer['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="custom-table-actions">
                        <a href="take_attendance.php?group_id=<?php echo $group_id ?>&course_id=<?php echo $course_id ?>" title="Take Attendance">
                            Take Attendance
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
                        <?php else: ?>
                            <div class="card-inner">
                                <div class="alert alert-light">No courses are currently assigned to this group. Use the section below to add courses.</div>
                            </div>
                        <?php endif; ?>
                    </div><!-- .card-inner -->
                </div><!-- .card-inner-group -->
            </div><!-- .card -->

            <div class="card card-bordered card-stretch mt-4">
                 <div class="card-inner-group">
                    <div class="card-header">
                        <h5 class="card-title">Add New Course Assignment</h5>
                    </div>
                    <div class="card-inner">
                        <?php if (!empty($available_courses)): ?>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label class="form-label" for="new_course_id">Select Course to Add:</label>
                                        <div class="form-control-wrap">
                                            <select id="new_course_id" class="form-select js-select2" data-search="on"> <!-- Added js-select2 -->
                                                <option value="">-- Select Course --</option>
                                                <?php foreach ($available_courses as $course): ?>
                                                    <option value="<?php echo $course['course_id'] ?>">
                                                        <?php echo escape_html($course['course_code'] . ' - ' . $course['course_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label class="form-label" for="new_lecturer_id">Assign Lecturer:</label>
                                        <div class="form-control-wrap">
                                            <select id="new_lecturer_id" class="form-select js-select2" data-search="on" disabled> <!-- Added js-select2 -->
                                                <option value="0">-- Select Lecturer --</option>
                                                <?php foreach ($all_lecturers as $lecturer): ?>
                                                    <option value="<?php echo $lecturer['user_id'] ?>">
                                                        <?php echo escape_html($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' (' . $lecturer['username'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 align-self-end">
                                     <p class="form-note">Select course & lecturer, then Save.</p>
                                </div>
                            </div>
                             <p class="form-note mt-2">Note: To add a new course assignment, select the course and the lecturer from the dropdowns above, then click "Save Changes". The selected course will appear in the "Assigned Courses" list on the next page load if saved successfully.</p>
                        <?php else: ?>
                             <div class="alert alert-light">All available courses in the system are already assigned to this group, or no courses exist in the system.</div>
                        <?php endif; ?>
                    </div><!-- .card-inner -->
                </div><!-- .card-inner-group -->
            </div><!-- .card -->

            <div class="nk-block-head nk-block-head-sm">
                 <div class="nk-block-between">
                    <div class="nk-block-head-content">
                        <div class="nk-block-head-sub"></div><!-- .nk-block-head-sub -->
                    </div><!-- .nk-block-head-content -->
                    <div class="nk-block-head-content">
                        <ul class="nk-block-tools g-3">
                            <li><button type="submit" class="btn btn-primary">Save Changes</button></li>
                            <li><a href="index.php" class="btn btn-outline-light bg-white d-none d-sm-inline-flex"><em class="icon ni ni-arrow-left"></em><span>Back</span></a></li>
                        </ul>
                    </div><!-- .nk-block-head-content -->
                </div><!-- .nk-block-between -->
            </div><!-- .nk-block-head -->

        </form>

    <?php else: ?>
         <div class="alert alert-danger">Could not load group or department details. Please select a group from your dashboard.</div>
    <?php endif; ?>

</div><!-- .nk-block -->

<?php
// End main PHP block before comments and script
?>

<!-- Add Javascript to dynamically update the name attribute when a new course is selected -->
<!-- This ensures the selected course_id is used as the key in the POST data -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form'); // Assuming only one form on the page
    const newCourseSelect = document.getElementById('new_course_id');
    const newLecturerSelect = document.getElementById('new_lecturer_id');

    // Initialize Select2 if the elements exist (DashLite uses this)
    if (NioApp && NioApp.Select2) {
        NioApp.Select2('.js-select2'); // Initialize all elements with the class
    }

    if (form && newCourseSelect && newLecturerSelect) {
        // Add hidden inputs for new assignment if they don't exist
        let hiddenCourseInput = document.getElementById('hidden_new_course_id');
        if (!hiddenCourseInput) {
            hiddenCourseInput = document.createElement('input');
            hiddenCourseInput.type = 'hidden';
            hiddenCourseInput.name = 'new_course_id';
            hiddenCourseInput.id = 'hidden_new_course_id';
            form.appendChild(hiddenCourseInput);
        }

        let hiddenLecturerInput = document.getElementById('hidden_new_lecturer_id');
        if (!hiddenLecturerInput) {
            hiddenLecturerInput = document.createElement('input');
            hiddenLecturerInput.type = 'hidden';
            hiddenLecturerInput.name = 'new_lecturer_id';
            hiddenLecturerInput.id = 'hidden_new_lecturer_id';
            form.appendChild(hiddenLecturerInput);
        }

        // Use jQuery for Select2 event handling if available, otherwise standard JS
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery(newCourseSelect).on('change', function() {
                const selectedCourseId = this.value;
                const $lecturerSelect = jQuery(newLecturerSelect);
                if (selectedCourseId) {
                    $lecturerSelect.prop('disabled', false).trigger('change.select2'); // Enable and update Select2
                    hiddenCourseInput.value = selectedCourseId;
                } else {
                    $lecturerSelect.val('0').prop('disabled', true).trigger('change.select2'); // Disable, reset, update Select2
                    hiddenCourseInput.value = '';
                }
                 hiddenLecturerInput.value = $lecturerSelect.val(); // Update hidden lecturer input too
            });

            jQuery(newLecturerSelect).on('change', function() {
                hiddenLecturerInput.value = this.value;
            });
        } else {
            // Fallback for standard JS event listeners if Select2/jQuery not loaded
            newCourseSelect.addEventListener('change', function() {
                const selectedCourseId = this.value;
                if (selectedCourseId) {
                    newLecturerSelect.disabled = false;
                    hiddenCourseInput.value = selectedCourseId;
                } else {
                    newLecturerSelect.disabled = true;
                    newLecturerSelect.value = '0';
                    hiddenCourseInput.value = '';
                }
                 hiddenLecturerInput.value = newLecturerSelect.value;
            });

            newLecturerSelect.addEventListener('change', function() {
                hiddenLecturerInput.value = this.value;
            });
        }
    }
});
</script>


<?php include_once __DIR__ . '/includes/templates/footer.php'; ?>
