<?php
// require_once '../includes/config.php'; // Incorrect path or file doesn't exist
require_once 'includes/db_connect.php'; // Include the DB connection file
require_once 'includes/functions.php'; // Class rep specific functions

// Start session and check if logged in and is a class rep
check_course_rep_login();

$course_rep_user_id = $_SESSION['user_id'];
$group_id = get_course_rep_group_id($conn, $course_rep_user_id);
$group_details = $group_id ? get_group_details($conn, $group_id) : null;

if (!$group_id || !$group_details) {
    die("Error: Class representative group not found or not assigned.");
}

$page_title = "Manage Carryover Enrollments";
$page_heading = "Manage Carryover Students joining " . escape_html($group_details['group_name'] ?? 'Your Group');

$message = '';
$error = '';
$search_identifier = '';
$found_student = null;

// Get pagination parameters
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get courses for the rep's group
$courses_for_group = get_courses_for_group($conn, $group_id);

// --- Handle Form Submissions ---

// 1. Handle Student Search for Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_student') {
    $search_identifier = trim($_POST['student_identifier'] ?? '');
    if (!empty($search_identifier)) {
        $found_student = find_student_globally_by_identifier($conn, $search_identifier);
        if (!$found_student) {
            $error = "Student with identifier '" . escape_html($search_identifier) . "' not found.";
        }
    } else {
        $error = "Please enter a Matric Number or Application ID to search.";
    }
}

// 2. Handle Carryover Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_carryover') {
    $student_id_to_enroll = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $course_id_to_enroll = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $allow_self_mark = true;

    if ($student_id_to_enroll && $course_id_to_enroll && $group_id) {
        $is_course_valid = false;
        foreach ($courses_for_group as $course) {
            if ($course['course_id'] == $course_id_to_enroll) {
                $is_course_valid = true;
                break;
            }
        }

        if ($is_course_valid) {
            if (enroll_carryover_student($conn, $student_id_to_enroll, $course_id_to_enroll, $group_id, $allow_self_mark)) {
                $message = "Student successfully enrolled as carryover.";
                $found_student = null;
                $search_identifier = '';
            } else {
                $error = "Failed to enroll carryover student. They might already be enrolled for this course/group, or a database error occurred.";
            }
        } else {
             $error = "Invalid course selected for this group.";
        }
    } else {
        $error = "Invalid data provided for enrollment.";
    }
}

// 3. Handle Enrollment Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_enrollment') {
    $enrollment_id_to_remove = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    if ($enrollment_id_to_remove) {
        if (remove_carryover_enrollment($conn, $enrollment_id_to_remove)) {
            $message = "Carryover enrollment removed successfully.";
        } else {
            $error = "Failed to remove carryover enrollment. It might have already been removed or a database error occurred.";
        }
    }
}

// --- Fetch Data for Display ---
$filters = [];
if (!empty($search_query)) {
    $filters['search'] = $search_query;
}
$enrollments_data = get_carryover_enrollments_for_group($conn, $group_id, $filters, $current_page, $per_page);
$existing_enrollments = $enrollments_data['data'];
$total_pages = $enrollments_data['total_pages'];
?>

<?php include_once __DIR__ . '/includes/templates/header.php'; ?>
<style>
    .custom-table {
    font-size: 0.95rem;
    border: 1px solid #dee2e6;
}

.custom-table th,
.custom-table td {
    vertical-align: middle;
}

.custom-table tbody tr:hover {
    background-color: #f1f1f1;
}

</style>
<div class="container">
    <h2><?= $page_heading ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= escape_html($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape_html($error) ?></div>
    <?php endif; ?>

    <hr>

    <!-- Section 1: Enroll New Carryover Student -->
    <h3>Enroll New Carryover Student</h3>

    <!-- Student Search Form -->
    <form method="POST" action="manage_carryover.php" class="mb-3 form-inline">
        <input type="hidden" name="action" value="search_student">
        <div class="form-group mr-2">
            <label for="student_identifier" class="sr-only">Student Matric No./App ID:</label>
            <input type="text" class="form-control" id="student_identifier" name="student_identifier" placeholder="Enter Matric No. or App ID" value="<?= escape_html($search_identifier) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Search Student</button>
    </form>

    <!-- Enrollment Form (Shown if student found) -->
    <?php if ($found_student): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Student Found:</h5>
                <p>
                    <strong>Name:</strong> <?= escape_html($found_student['first_name'] ?? '') ?> <?= escape_html($found_student['last_name'] ?? '') ?><br>
                    <strong>Matric No:</strong> <?= escape_html($found_student['matric_number'] ?? 'N/A') ?><br>
                    <strong>App ID:</strong> <?= escape_html($found_student['application_id'] ?? 'N/A') ?><br>
                    <strong>Level:</strong> <?= escape_html($found_student['level'] ?? 'N/A') ?><br>
                    <?php
                        $original_group_details = $found_student['group_id'] ? get_group_details($conn, $found_student['group_id']) : null;
                        if ($original_group_details && $original_group_details['group_id'] != $group_id) {
                            echo "<strong>Original Group:</strong> " . escape_html($original_group_details['group_name'] ?? '') . " (" . escape_html($original_group_details['department_name'] ?? '') . ")<br>";
                        } elseif ($found_student['group_id'] == $group_id) {
                             echo "<strong>Note:</strong> This student is already in your group. Enrolling as carryover might be redundant unless specifically needed.<br>";
                        }
                    ?>
                </p>

                <form method="POST" action="manage_carryover.php">
                    <input type="hidden" name="action" value="enroll_carryover">
                    <input type="hidden" name="student_id" value="<?= escape_html($found_student['user_id']) ?>">

                    <div class="form-group">
                        <label for="course_id">Select Course to Enroll In (for <?= escape_html($group_details['group_name']) ?>):</label>
                        <select class="form-control" id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses_for_group as $course): ?>
                                <option value="<?= escape_html($course['course_id']) ?>">
                                    <?= escape_html($course['course_code']) ?> - <?= escape_html($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">Enroll Student</button>
                    <a href="manage_carryover.php" class="btn btn-secondary">Cancel Search</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <hr>

    <!-- Section 2: Existing Carryover Enrollments -->
    <h3>Existing Carryover Enrollments for <?= escape_html($group_details['group_name']) ?></h3>

    <!-- Search Box for Existing Enrollments -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" action="manage_carryover.php" class="form-inline">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, matric no, or course" value="<?= escape_html($search_query) ?>">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="manage_carryover.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($existing_enrollments)): ?>
        <p>No carryover students are currently enrolled for courses in this group.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered custom-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Matric No.</th>
                    <th>Course</th>
                    <th>Level</th>
                    <th>Original Group</th>
                    <th>Enrollment Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existing_enrollments as $enrollment): 
                    $original_group_details = get_group_details($conn, $enrollment['original_group_id'] ?? null);
                ?>
                    <tr>
                        <td><?= escape_html($enrollment['first_name'] ?? '') ?> <?= escape_html($enrollment['last_name'] ?? '') ?></td>
                        <td><?= escape_html($enrollment['matric_number'] ?? $enrollment['application_id'] ?? 'N/A') ?></td>
                        <td><?= escape_html($enrollment['course_code']) ?> - <?= escape_html($enrollment['course_name']) ?></td>
                        <td><?= escape_html($enrollment['level'] ?? 'N/A') ?></td>
                        <td><?= escape_html($original_group_details['group_name'] ?? 'N/A') ?></td>
                        <td><?= escape_html(date('Y-m-d', strtotime($enrollment['enrollment_date']))) ?></td>
                        <td>
                            <form method="POST" action="manage_carryover.php" onsubmit="return confirm('Are you sure you want to remove this carryover enrollment?');" style="display: inline;">
                                <input type="hidden" name="action" value="remove_enrollment">
                                <input type="hidden" name="enrollment_id" value="<?= $enrollment['enrollment_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= ($current_page - 1) ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= ($current_page + 1) ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div><!-- /.container -->

<?php
include_once __DIR__ . '/includes/templates/footer.php';
$conn->close();
?>
