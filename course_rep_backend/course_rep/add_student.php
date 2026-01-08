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
$department_id = null; // To associate student with the group's department

// --- Input Data (for repopulating form on error) ---
$app_id = '';
$matric = '';
$fname = '';
$lname = '';
$mname = '';
$email = '';
$level = '';

// 1. Validate Group ID & Get Details
if ($group_id <= 0) {
    $errors[] = "Invalid Group specified.";
} else {
    if (!verify_rep_manages_group($conn, $user_id, $group_id)) {
        $errors[] = "Permission Denied: You do not manage this group.";
        // Redirect or display error strongly? Redirect might be better
        $_SESSION['error_messages'] = $errors;
        header('Location: index.php'); // Redirect to dashboard if no permission
        exit;
    }
    $group_details = get_group_details($conn, $group_id);
    if (!$group_details) {
        $errors[] = "Group details not found.";
    } else {
        $department_id = $group_details['department_id'];
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $group_id > 0 && $department_id) {
    // Repopulate form fields from POST data
    $app_id = trim($_POST['application_id'] ?? '');
    $matric = trim($_POST['matric_number'] ?? '');
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $mname = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Use personal email field
    $level = trim($_POST['level'] ?? ''); // Student level

    // --- Validation ---
    if (empty($app_id)) { $errors[] = "Application ID is required."; }
    if (empty($fname)) { $errors[] = "First Name is required."; }
    if (empty($lname)) { $errors[] = "Last Name is required."; }
    // Email is optional, but validate if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid Personal Email format."; }
     if (empty($level)) { $errors[] = "Level is required."; }
    // Optional: Validate Matric Number format if provided
    // Optional: Check if Application ID or Matric Number already exists

    // --- Add Student if No Errors ---
    if (empty($errors)) {
        // NOTE: Students are added *without* a password initially or with a default.
        // Password setting might be handled by students themselves later via a 'forgot password' feature
        // or by an admin. For simplicity, we omit password setting here.
        // Generate a placeholder password or leave it to be set later.
        // For now, let's hash a default temporary password (e.g., 'password123')
        // IMPORTANT: This is NOT secure for production. Implement a proper password reset flow later.
        $default_password = password_hash('password123', PASSWORD_DEFAULT);

        // Basic check for existing application ID
        $stmt_check = $conn->prepare("SELECT user_id FROM students WHERE application_id = ?");
        $stmt_check->bind_param("s", $app_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $errors[] = "Student with this Application ID already exists.";
        } else {
             $stmt = $conn->prepare("INSERT INTO students (application_id, matric_number, password, group_id, department_id, email, first_name, last_name, middle_name, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Added level to bind_param
             if ($stmt) {
                 // Added 's' for level string type
                $stmt->bind_param("sssiisssss", $app_id, $matric, $default_password, $group_id, $department_id, $email, $fname, $lname, $mname, $level);

                if ($stmt->execute()) {
                    $_SESSION['success_messages'] = ["Student '" . escape_html($fname) . " " . escape_html($lname) . "' added successfully."];
                    header("Location: manage_students.php?group_id=" . $group_id);
                    exit;
                } else {
                    $errors[] = "Failed to add student. Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to prepare database statement.";
            }
        }
        $stmt_check->close(); // Close the check statement HERE
    }

    // Store errors in session and redirect to prevent resubmission on refresh
     if (!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
         header("Location: add_student.php?group_id=" . $group_id);
         exit;
    }
} // End of POST request handling

// Handle GET request (or any request that wasn't POST)
else {
    // Check for errors passed via session after POST redirect
    if (isset($_SESSION['error_messages'])) {
        $errors = $_SESSION['error_messages'];
        unset($_SESSION['error_messages']); // Clear after displaying
    }
    // Removed extraneous closing brace here
}

$page_title = "Add New Student";
if ($group_details) {
    $page_title .= " to Group: " . escape_html($group_details['group_name']);
}
include_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container mt-4">
    <h2><?= $page_title ?></h2>

    <?php include __DIR__ . '/includes/messages.php'; // Display errors/success ?>

    <?php if ($group_id > 0 && $group_details): // Only show form if group is valid ?>
        <div class="card">
            <div class="card-body">
                <form action="add_student.php?group_id=<?= $group_id ?>" method="post" novalidate>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="application_id" class="form-label">Application ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="application_id" name="application_id" value="<?= escape_html($app_id) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="matric_number" class="form-label">Matriculation Number</label>
                            <input type="text" class="form-control" id="matric_number" name="matric_number" value="<?= escape_html($matric) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= escape_html($fname) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= escape_html($mname) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= escape_html($lname) ?>" required>
                        </div>

                        <div class="col-md-6">
                             <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="level" name="level" placeholder="E.g., 100, 200" value="<?= escape_html($level) ?>" required>
                        </div>
                        <div class="col-md-6">
                             <label for="email" class="form-label">Personal Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Optional personal email" value="<?= escape_html($email) ?>">
                        </div>

                        <p class="text-muted mt-3 mb-0"><small>Note: Students will be added with a default password. They should use the 'Forgot Password' feature (if implemented) or contact an administrator to set their own password.</small></p>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Add Student</button>
                        <a href="manage_students.php?group_id=<?= $group_id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            Cannot add student. Please ensure you are accessing this page via a valid group from the <a href="index.php">dashboard</a>.
        </div>
    <?php endif; ?>

</div>

<?php include_once __DIR__ . '/includes/templates/footer.php'; ?>
