<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session and check login
check_course_rep_login();

$course_rep_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$group_id = 0; // Will be fetched from student data

$errors = [];
$student_data = null;
$page_title = "Edit Student"; // Default title

// --- Input Data (from database or POST for repopulating form) ---
$app_id = '';
$matric = '';
$fname = '';
$lname = '';
$mname = '';
$email = '';
$school_email = '';
$level = '';

// 1. Validate Student ID & Fetch Data
if ($student_id <= 0) {
    $_SESSION['error_messages'] = ["Invalid Student specified."];
    header('Location: index.php'); // Redirect to dashboard
    exit;
}

$student_data = get_student_details($conn, $student_id); // You'll need to create this function
if (!$student_data) {
    $_SESSION['error_messages'] = ["Student not found."];
    header('Location: index.php');
    exit;
}

// 2. Verify Class Rep Manages this Student's Group
$group_id = $student_data['group_id'];
if (!verify_rep_manages_group($conn, $course_rep_user_id, $group_id)) {
    $_SESSION['error_messages'] = ["Permission Denied: You do not manage the group this student belongs to."];
    header('Location: index.php'); // Redirect to dashboard
    exit;
}

// --- Populate initial form data from database ---
$app_id = $student_data['application_id']; // Generally not editable, but good to have
$matric = $student_data['matric_number'] ?? '';
$fname = $student_data['first_name'] ?? '';
$lname = $student_data['last_name'] ?? '';
$mname = $student_data['middle_name'] ?? '';
$email = $student_data['email'] ?? ''; // Personal email
$school_email = $student_data['school_email'] ?? '';
$level = $student_data['level'] ?? '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get updated data from POST, overwriting db data for validation/saving
    // Application ID is NOT updated - it's usually immutable
    $matric = trim($_POST['matric_number'] ?? '');
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $mname = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Personal email
    $school_email = trim($_POST['school_email'] ?? ''); // School email
    $level = trim($_POST['level'] ?? ''); // Student level

    // --- Validation ---
    if (empty($fname)) { $errors[] = "First Name is required."; }
    if (empty($lname)) { $errors[] = "Last Name is required."; }
    // Level validation removed as level is not editable here
    
    // Validate personal email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Personal Email format.";
    }
    // Validate school email if provided
    if (!empty($school_email) && !filter_var($school_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid School Email format.";
    }

    // Initialize update status variable here
    $update_success = false;

    // --- Check for conflicts (only if basic validation passed) ---
    if (empty($errors)) {
        // Check Matric Number conflict (if provided)
        if (!empty($matric)) {
            $stmt_check_matric = $conn->prepare("SELECT user_id FROM students WHERE matric_number = ? AND user_id != ?");
            if ($stmt_check_matric) {
                $stmt_check_matric->bind_param("si", $matric, $student_id);
                $stmt_check_matric->execute();
                if ($stmt_check_matric->get_result()->num_rows > 0) {
                    $errors[] = "Matriculation Number '" . escape_html($matric) . "' is already used by another student.";
                }
                $stmt_check_matric->close();
            } else {
                $errors[] = "Failed to prepare matric number conflict check.";
            }
        }

        // Check Personal Email conflict (if provided and no errors yet)
        if (empty($errors) && !empty($email)) {
            $stmt_check_email = $conn->prepare("SELECT user_id FROM students WHERE email = ? AND user_id != ?");
             if ($stmt_check_email) {
                $stmt_check_email->bind_param("si", $email, $student_id);
                $stmt_check_email->execute();
                if ($stmt_check_email->get_result()->num_rows > 0) {
                    $errors[] = "Personal Email '" . escape_html($email) . "' is already used by another student.";
                }
                $stmt_check_email->close();
            } else {
                $errors[] = "Failed to prepare personal email conflict check.";
            }
        }

        // Check School Email conflict (if provided and no errors yet)
        if (empty($errors) && !empty($school_email)) {
            $stmt_check_school_email = $conn->prepare("SELECT user_id FROM students WHERE school_email = ? AND user_id != ?");
             if ($stmt_check_school_email) {
                $stmt_check_school_email->bind_param("si", $school_email, $student_id);
                $stmt_check_school_email->execute();
                if ($stmt_check_school_email->get_result()->num_rows > 0) {
                    $errors[] = "School Email '" . escape_html($school_email) . "' is already used by another student.";
                }
                $stmt_check_school_email->close();
            } else {
                $errors[] = "Failed to prepare school email conflict check.";
            }
        }
    } // End conflict checks


    // --- Update Student if No Errors (including conflict checks) ---
    if (empty($errors)) {
        // $update_success = false; // Already initialized above
        $password_reset_success = true; // Assume true unless reset is attempted and fails
        $email_sent_success = true; // Assume true unless reset is attempted and email fails

        // Prepare main update statement (excluding password and level)
        $stmt = $conn->prepare("UPDATE students SET email = ?, school_email = ?, first_name = ?, last_name = ?, middle_name = ? WHERE user_id = ? AND group_id = ?");

        if ($stmt) {
            // Binding parameters updated (removed 's' for level, level variable removed)
            $stmt->bind_param("sssssii", $email, $school_email, $fname, $lname, $mname, $student_id, $group_id);
            if ($stmt->execute()) {
                $update_success = true; // Main details updated
                $_SESSION['success_messages'] = ["Student '" . escape_html($fname) . " " . escape_html($lname) . "' details updated successfully."];

                // --- Handle Password Reset ---
                if (isset($_POST['reset_password']) && $_POST['reset_password'] == '1') {
                    $new_plain_password = generate_random_password(8);
                    $new_hashed_password = password_hash($new_plain_password, PASSWORD_DEFAULT);

                    $stmt_pass = $conn->prepare("UPDATE students SET password = ? WHERE user_id = ?");
                    if ($stmt_pass) {
                        $stmt_pass->bind_param("si", $new_hashed_password, $student_id);
                        if ($stmt_pass->execute()) {
                            // Password updated in DB, now try to send email
                            $login_identifier = !empty($matric) ? $matric : $app_id; // Use matric if available, else app id
                            $email_to_send = !empty($email) ? $email : $school_email; // Prefer personal email if available

                            if (!empty($email_to_send)) {
                                if (sendStudentLoginDetails($email_to_send, $new_plain_password, $fname, $login_identifier)) {
                                    $_SESSION['success_messages'][] = "Password reset and new login details emailed successfully.";
                                } else {
                                    $email_sent_success = false;
                                    $errors[] = "Password was reset, but failed to send login details email to " . escape_html($email_to_send) . ". Please notify the student manually.";
                                    error_log("Failed to send password reset email to {$email_to_send} for student ID {$student_id}");
                                }
                            } else {
                                 $email_sent_success = false; // Cannot send email if none exists
                                 $errors[] = "Password was reset, but no email address is available to send the new login details.";
                            }
                        } else {
                            $password_reset_success = false;
                            $errors[] = "Failed to reset password. Database error: " . $stmt_pass->error;
                        }
                        $stmt_pass->close();
                    } else {
                         $password_reset_success = false;
                         $errors[] = "Failed to prepare password reset statement.";
                    }
                } // End password reset check

                // Redirect only if main update was successful AND (password wasn't reset OR password reset succeeded)
                if ($update_success && $password_reset_success) {
                     // Store any accumulated errors (like email failure) before redirecting
                     if (!empty($errors)) {
                         $_SESSION['error_messages'] = $errors;
                     }
                     header("Location: manage_students.php?group_id=" . $group_id);
                     exit;
                }
                // If password reset failed, we fall through and display errors below
            } // End of if ($stmt->execute())
            else {
                 $errors[] = "Failed to update student details. Database error: " . $stmt->error;
            }
            $stmt->close();
        } // End of if ($stmt)
        else {
            $errors[] = "Failed to prepare database update statement.";
        }
    } // End of if (empty($errors))

    // If we fall through here, it means either initial validation failed,
    // or the main update succeeded but password reset/email failed.
    // Errors (if any) will be displayed below the form.
    // We store errors in session ONLY if we were about to redirect but couldn't due to password/email issues.
    if ($update_success && !$password_reset_success && !empty($errors)) {
         $_SESSION['error_messages'] = $errors;
         // We don't redirect here, let the page reload with errors displayed.
    } elseif ($update_success && $password_reset_success && !$email_sent_success && !empty($errors)) {
         $_SESSION['error_messages'] = $errors;
         // We don't redirect here, let the page reload with errors displayed.
    }


} // End of if ($_SERVER['REQUEST_METHOD'] === 'POST')
else {
    // GET request: Display any errors passed from a previous failed POST attempt
    if (isset($_SESSION['error_messages'])) {
        $errors = $_SESSION['error_messages'];
        unset($_SESSION['error_messages']);
    }
}

if ($student_data) {
    $page_title = "Edit Student: " . escape_html($student_data['first_name'] . ' ' . $student_data['last_name']);
}
include_once __DIR__ . '/includes/templates/header.php';
?>

<div class="nk-block-head nk-block-head-sm">
    <div class="nk-block-between">
        <div class="nk-block-head-content">
            <h3 class="nk-block-title page-title"><?php echo $page_title ?></h3>
            <div class="nk-block-des text-soft">
                <p>Update student details.</p>
            </div>
        </div><!-- .nk-block-head-content -->
        <div class="nk-block-head-content">
             <a href="manage_students.php?group_id=<?php echo $group_id; ?>" class="btn btn-outline-light bg-white d-none d-sm-inline-flex"><em class="icon ni ni-arrow-left"></em><span>Back to Students</span></a>
             <a href="manage_students.php?group_id=<?php echo $group_id; ?>" class="btn btn-icon btn-outline-light bg-white d-inline-flex d-sm-none"><em class="icon ni ni-arrow-left"></em></a>
        </div><!-- .nk-block-head-content -->
    </div><!-- .nk-block-between -->
</div><!-- .nk-block-head -->

<div class="nk-block">
    <?php include __DIR__ . '/includes/messages.php'; ?>

    <?php if ($student_id > 0 && $student_data): ?>
        <div class="card card-bordered">
            <div class="card-inner">
                 <form action="edit_student.php?student_id=<?php echo $student_id; ?>" method="post" class="form-validate">
                    <div class="row g-gs">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="application_id">Application ID</label>
                                <input type="text" class="form-control" id="application_id" name="application_id" value="<?php echo escape_html($app_id); ?>" readonly disabled>
                                <div class="form-note">Application ID cannot be changed.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="matric_number">Matriculation Number</label>
                                <input type="text" class="form-control" id="matric_number" name="matric_number" value="<?php echo escape_html($matric); ?>" readonly disabled>
                                <div class="form-note">Application ID cannot be changed.</div>
                            </div>
                        </div>

                        <div class="col-md-4">
                             <div class="form-group">
                                <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo escape_html($fname); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                             <div class="form-group">
                                <label class="form-label" for="middle_name">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo escape_html($mname); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                             <div class="form-group">
                                <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo escape_html($lname); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                             <div class="form-group">
                                <label class="form-label" for="email">Personal Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Optional personal email" value="<?php echo escape_html($email); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                             <div class="form-group">
                                <label class="form-label" for="school_email">School Email</label>
                                <input type="email" class="form-control" id="school_email" name="school_email" placeholder="School email" value="<?php echo escape_html($school_email); ?>">
                             </div>
                         </div>
                         <!-- Level Input Removed -->
                         <div class="col-md-6">
                         </div>
                         <div class="col-12 border-top pt-3">
                             <div class="form-group">
                                 <div class="custom-control custom-switch">
                                     <input type="checkbox" class="custom-control-input" name="reset_password" id="reset_password" value="1">
                                     <label class="custom-control-label" for="reset_password">Reset Password & Send New Login Details</label>
                                 </div>
                                 <div class="form-note">Check this box to generate a new random password and email it to the student. The current password will be overwritten.</div>
                             </div>
                         </div>
                        <div class="col-12 mt-4">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="manage_students.php?group_id=<?php echo $group_id; ?>" class="btn btn-light">Cancel</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div><!-- .card-inner -->
        </div><!-- .card -->
    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            Student data could not be loaded. Please return to the <a href="index.php">dashboard</a>.
        </div>
    <?php endif; ?>
</div><!-- .nk-block -->

<?php include_once __DIR__ . '/includes/templates/footer.php'; ?>
