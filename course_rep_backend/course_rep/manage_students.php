<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/messages.php'; // Include messages

// Start session and check login status
check_course_rep_login();

$page_title = "Manage Students";
$errors = [];
$students = [];
$group_details = null;
$course_rep_id = $_SESSION['user_id'];

// 1. Get group_id from URL
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);

if (!$group_id) {
    // Redirect if group_id is missing or invalid - perhaps to index.php?
    // Or display an error message here if preferred.
    $_SESSION['error_message'] = "No group specified or invalid group ID.";
    header('Location: index.php'); // Redirect to rep dashboard
    exit;
}

// 2. Verify Class Rep manages this group
if (!verify_rep_manages_group($conn, $course_rep_id, $group_id)) {
    $_SESSION['error_message'] = "You do not have permission to manage this group.";
    header('Location: index.php'); // Redirect to rep dashboard
    exit;
}

// 3. Fetch Group Details
$group_details = get_group_details($conn, $group_id);
if (!$group_details) {
     // Should not happen if verify passed, but good practice
     $_SESSION['error_message'] = "Could not retrieve details for the specified group.";
     header('Location: index.php');
     exit;
}

$page_title .= " - " . escape_html($group_details['group_name'] ?? 'Group'); // Update page title

// 4. Fetch Class Rep's Level
$course_rep_details = get_user_details($conn, $course_rep_id);
$course_rep_level_full = $course_rep_details['level'] ?? null; // e.g., '100 LEVEL'
$course_rep_level_numeric = null;
if ($course_rep_level_full) {
    // Extract numeric part (e.g., '100' from '100 LEVEL')
    preg_match('/^\d+/', $course_rep_level_full, $matches);
    if (isset($matches[0])) {
        $course_rep_level_numeric = $matches[0];
    }
}

// 5. Fetch Students in this Group matching the Class Rep's Level
if ($course_rep_level_numeric) {
    $students = get_students_in_group_by_level($conn, $group_id, $course_rep_level_numeric);
} else {
    $students = []; // Or handle error: Class rep level not found/invalid
    $errors[] = "Could not determine the level for the current class representative.";
    // Log this error as well
    error_log("Could not determine numeric level for class rep ID: $course_rep_id with level: $course_rep_level_full");
}


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
            <h3 class="nk-block-title page-title"><?php echo $page_title; ?></h3>
            <div class="nk-block-des text-soft">
                <p>Department: <?php echo escape_html($group_details['department_name'] ?? 'N/A'); ?> | Faculty: <?php echo escape_html($group_details['faculty_name'] ?? 'N/A'); ?></p>
                <p>You have <?php echo count($students); ?> students in this group.</p>
            </div>
        </div><!-- .nk-block-head-content -->
        <div class="nk-block-head-content">
            <div class="toggle-wrap nk-block-tools-toggle">
                <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="pageMenu"><em class="icon ni ni-menu-alt-r"></em></a>
                <div class="toggle-expand-content" data-content="pageMenu">
                    <ul class="nk-block-tools g-3">
                        <li><a href="add_single_student.php?group_id=<?php echo urlencode($group_id); ?>" class="btn btn-primary"><em class="icon ni ni-user-add"></em><span>Add Single Student</span></a></li>
                        <li><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importCsvModal"><em class="icon ni ni-file-xls"></em><span>Add via CSV</span></button></li>
                        <li><a href="index.php" class="btn btn-white btn-outline-light"><em class="icon ni ni-arrow-left"></em><span>Back to Dashboard</span></a></li>
                    </ul>
                </div>
            </div><!-- .toggle-wrap -->
        </div><!-- .nk-block-head-content -->
    </div><!-- .nk-block-between -->
</div><!-- .nk-block-head -->

<div class="nk-block">
    <?php include __DIR__ . '/includes/messages.php'; ?>

<div class="card-inner p-0">
    <?php if (empty($students)): ?>
        <div class="card-inner">
            <div class="alert alert-info">No students are currently registered in this group for level <?php echo escape_html($course_rep_level_numeric); ?>.</div>
        </div>
    <?php else: ?>
        <form method="post" action="delete_students.php?group_id=<?php echo urlencode($group_id); ?>" id="studentsForm">
            <input type="hidden" name="group_id" value="<?php echo urlencode($group_id); ?>">
            <div class="scroll-wrapper">
            <div class="custom-table-container">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="select-col"><input type="checkbox" id="selectAllStudents"></th>
                            <th>Matric / App ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email(s)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="select-col">
                                    <input type="checkbox" class="student-checkbox" name="student_ids[]" value="<?php echo escape_html($student['user_id']); ?>" id="student_<?php echo escape_html($student['user_id']); ?>">
                                </td>
                                <td><?php echo escape_html($student['matric_number'] ?: $student['application_id']); ?></td>
                                <td><?php echo escape_html($student['first_name']); ?></td>
                                <td><?php echo escape_html($student['last_name']); ?></td>
                                <td>
                                    <?php if (!empty($student['school_email'])) echo escape_html($student['school_email']) . '<br>'; ?>
                                    <?php if (!empty($student['email']) && $student['email'] !== $student['school_email']) echo '<span class="text-soft">' . escape_html($student['email']) . '</span>'; ?>
                                </td>
                                <td class="custom-table-actions">
                                    <a href="edit_student.php?student_id=<?php echo urlencode($student['user_id']); ?>&group_id=<?php echo urlencode($group_id); ?>" title="Edit Student">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="mt-3" style="text-align: right;">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete selected students?')" id="deleteSelectedBtn" style="display: none;">
                    <em class="icon ni ni-trash"></em> Delete Selected
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

</div><!-- .nk-block -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAllStudents');
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

        function toggleDeleteButton() {
            const anyChecked = Array.from(studentCheckboxes).some(cb => cb.checked);
            deleteSelectedBtn.style.display = anyChecked ? 'inline-block' : 'none';
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                toggleDeleteButton();
            });
        }

        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    // Check if all are checked
                    const allChecked = Array.from(studentCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
                toggleDeleteButton();
            });
        });

        // Initial check in case the page is reloaded with checkboxes checked
        toggleDeleteButton();
    });
</script>

<!-- Import CSV Modal -->
<div class="modal fade" tabindex="-1" id="importCsvModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Students via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="process_bulk_students.php" method="post" enctype="multipart/form-data" id="importCsvForm">
                    <input type="hidden" name="group_id" value="<?php echo escape_html($group_id); ?>">
                    <div class="form-group mb-3">
                        <label class="form-label" for="student_csv">Select CSV File</label>
                        <div class="form-control-wrap">
                            <input type="file" class="form-control" id="student_csv" name="student_csv" accept=".csv" required>
                        </div>
                        <div class="form-note mt-1">
                            Ensure the CSV follows the required format.
                            <a href="samples/student_template.csv" download><b>Download Template</b></a>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Import Students</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <span class="sub-text">Import process might take a few moments. Students will receive login details via email upon successful import.</span>
            </div>
        </div>
    </div>
</div>
<!-- End Import CSV Modal -->


<?php
include_once __DIR__ . '/includes/templates/footer.php';
?>
