<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/messages.php'; // Include messages

// Start session and check login status
check_course_rep_login();

$page_title = "Add Single Student";
$errors = [];
$group_details = null;
$course_rep_id = $_SESSION['user_id'];

// 1. Get group_id from URL
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);

if (!$group_id) {
    $_SESSION['error_message'] = "No group specified or invalid group ID.";
    header('Location: index.php');
    exit;
}

// 2. Verify Class Rep manages this group
if (!verify_rep_manages_group($conn, $course_rep_id, $group_id)) {
    $_SESSION['error_message'] = "You do not have permission to manage this group.";
    header('Location: index.php');
    exit;
}

// 3. Fetch Group Details (to get department_id for insertion)
$group_details = get_group_details($conn, $group_id);
if (!$group_details || !isset($group_details['department_id'])) {
     $_SESSION['error_message'] = "Could not retrieve necessary group or department details.";
     header('Location: index.php');
     exit;
}
$department_id = $group_details['department_id'];
$page_title .= " to " . escape_html($group_details['group_name'] ?? 'Group'); // Update page title

// 4. Handle Form Submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $app_id = trim(filter_input(INPUT_POST, 'application_id', FILTER_SANITIZE_STRING));
    $matric_no = trim(filter_input(INPUT_POST, 'matric_number', FILTER_SANITIZE_STRING)) ?: null; // Allow empty string -> null
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $middle_name = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING)) ?: null;
    // Level input removed from form
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $school_email = trim(filter_input(INPUT_POST, 'school_email', FILTER_VALIDATE_EMAIL));

    // Basic Validation
    if (empty($app_id)) $errors[] = "Application ID is required.";
    if (empty($first_name)) $errors[] = "First Name is required.";
    if (empty($last_name)) $errors[] = "Last Name is required.";
    // Level validation removed
    if (!empty($email) && $email === false) $errors[] = "Personal Email is not valid.";
    if (!empty($school_email) && $school_email === false) $errors[] = "School Email is not valid.";

    // Generate random password here
    $plain_password = generate_random_password(); // Generate a random password
    if (!$plain_password) {
        $errors[] = "Failed to generate password.";
    }

    // --- Get Class Rep's Level ---
    $course_rep_level_numeric = null; // Initialize
    if (empty($errors)) { // Only proceed if basic validation passed
        $course_rep_details = get_user_details($conn, $course_rep_id);
        $course_rep_level_full = $course_rep_details['level'] ?? null;
        if ($course_rep_level_full) {
            preg_match('/^\d+/', $course_rep_level_full, $matches);
            if (isset($matches[0])) {
                $course_rep_level_numeric = $matches[0]; // This should be the numeric level string e.g., '100'
            }
        }
        if (!$course_rep_level_numeric) {
            $errors[] = "Could not determine your level. Cannot assign level to student.";
            error_log("Could not determine numeric level for class rep ID: $course_rep_id in add_single_student.php");
        }
    }
    // --- End Get Class Rep's Level ---


    // Check if student already exists (by Application ID primarily)
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT user_id FROM students WHERE application_id = ?");
        $stmt_check->bind_param("s", $app_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $errors[] = "A student with this Application ID already exists in the system.";
        }
        $stmt_check->close();
    }

    // If no errors, password was generated, and level determined, proceed with insertion
    if (empty($errors) && isset($plain_password) && isset($course_rep_level_numeric)) {
        // Hash the password before storing
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
             $errors[] = "Failed to hash password.";
             error_log("Password hashing failed for App ID: " . $app_id);
        } else {
            // SQL statement updated to use hashed password and derived level
            $sql = "INSERT INTO students (application_id, matric_number, password, group_id, department_id, email, school_email, first_name, last_name, middle_name, level)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql);

            if ($stmt_insert) {
                // Bind parameters using derived level
                $stmt_insert->bind_param(
                    "sssiissssss", // Types: s, s, s, i, i, s, s, s, s, s, s
                    $app_id,
                    $matric_no,
                    $hashed_password, // Use the hashed password
                    $group_id,
                    $department_id,
                    $email, // Personal email (validated)
                    $school_email, // School email (validated)
                    $first_name,
                    $last_name,
                    $middle_name,
                    $course_rep_level_numeric // Use derived class rep level (as string)
                );

                if ($stmt_insert->execute()) {
                    $new_student_id = $conn->insert_id; // Get the new student ID

                    $_SESSION['success_message'] = "Student '" . escape_html($first_name) . " " . escape_html($last_name) . "' added successfully to the group! ";

                    // --- Attempt to send emails ---
                    $matric_no_or_app_id = !empty($matric_no) ? $matric_no : $app_id;
                    $emails_to_send_to = [];
                    // Use validated emails directly
                    if($email !== false && !empty($email)) $emails_to_send_to[] = $email;
                    if($school_email !== false && !empty($school_email)) $emails_to_send_to[] = $school_email;

                    $email_success_count = 0;
                    $failed_emails = [];
                    if(!empty($emails_to_send_to)) {
                        foreach($emails_to_send_to as $email_address) {
                             // Validation already done above
                             if(sendStudentLoginDetails($email_address, $plain_password, $first_name, $matric_no_or_app_id)) {
                                 $email_success_count++;
                             } else {
                                 $failed_emails[] = $email_address; // Track failed ones
                             }
                        }
                    }
                     // --- End Attempt to send emails ---

                     // --- Set appropriate Session Messages based on email success ---
                     if (count($emails_to_send_to) > 0) {
                        if ($email_success_count == count($emails_to_send_to)) {
                             $_SESSION['success_message'] .= "Login details sent to student email(s).";
                        } elseif ($email_success_count > 0) {
                             // Add warning if some failed
                             $_SESSION['warning_message'] = "Student added, but failed to send login details to: " . implode(', ', $failed_emails) . ". Check logs/Brevo settings.";
                        } else {
                              // Overwrite success message with error if no emails were sent despite having addresses
                             unset($_SESSION['success_message']); // Remove partial success message
                             $_SESSION['error_message'] = "Student added, but failed to send login details to any email address. Please check Brevo settings/logs.";
                        }
                     } else {
                          // Add warning if no emails were provided to send to
                          $_SESSION['warning_message'] = "Student added, but no email addresses were provided to send login details.";
                     }
                     // --- End Set Session Messages ---

                     // Redirect regardless of email success/failure, messages will inform user
                    header('Location: manage_students.php?group_id=' . urlencode($group_id));
                    exit;
                } else {
                    // Log detailed error if insert fails
                    error_log("SQL Error adding student: " . $stmt_insert->error . " | App ID: " . $app_id);
                    $errors[] = "Database error: Could not add the student. Please try again or check logs.";
                }
                $stmt_insert->close();
            } else {
                 $errors[] = "Database error: Failed to prepare statement.";
                 error_log("SQL Prepare Error adding student: " . $conn->error);
            }
        } // end else for password hash check
    } // end if no errors
    // If there were errors, the form will be redisplayed below with error messages
}


include_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container mt-4">
    <h1><?php echo $page_title; ?></h1>
    <p class="text-muted">Department: <?php echo escape_html($group_details['department_name'] ?? 'N/A'); ?> | Faculty: <?php echo escape_html($group_details['faculty_name'] ?? 'N/A'); ?></p>


    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Error!</strong> Please fix the following issues:
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo escape_html($error); // Escape output ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Enter Student Details</h5>
            <p class="card-text"><small>The student will be added to your current level. A secure password will be automatically generated and sent to the student's email address(es).</small></p>
            <form action="add_single_student.php?group_id=<?php echo urlencode($group_id); ?>" method="POST">
                 <div class="row">
                     <div class="col-md-6 mb-3">
                         <label for="application_id" class="form-label">Application ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="application_id" name="application_id" value="<?php echo escape_html($_POST['application_id'] ?? ''); ?>" required>
                     </div>
                     <div class="col-md-6 mb-3">
                         <label for="matric_number" class="form-label">Matric Number</label>
                         <input type="text" class="form-control" id="matric_number" name="matric_number" value="<?php echo escape_html($_POST['matric_number'] ?? ''); ?>">
                    </div>
                </div>
                 <div class="row">
                     <div class="col-md-4 mb-3">
                         <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo escape_html($_POST['first_name'] ?? ''); ?>" required>
                     </div>
                     <div class="col-md-4 mb-3">
                        <label for="middle_name" class="form-label">Middle Name</label>
                         <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo escape_html($_POST['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo escape_html($_POST['last_name'] ?? ''); ?>" required>
                     </div>
                 </div>
                 <!-- Level input removed -->
                <div class="row">
                     <div class="col-md-6 mb-3">
                         <label for="school_email" class="form-label">School Email</label>
                         <input type="email" class="form-control" id="school_email" name="school_email" value="<?php echo escape_html($_POST['school_email'] ?? ''); ?>">
                     </div>
                     <div class="col-md-6 mb-3">
                         <label for="email" class="form-label">Personal Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo escape_html($_POST['email'] ?? ''); ?>">
                     </div>
                 </div> <!-- Closing the row div -->

                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Student</button>
                <a href="manage_students.php?group_id=<?php echo urlencode($group_id); ?>" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Cancel</a>
            </form>
         </div>
    </div>

 </div>

 <?php
 include_once __DIR__ . '/includes/templates/footer.php';
?>
