<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Start session and check login status
check_course_rep_login();

$course_rep_id = $_SESSION['user_id'];
$errors = [];
$success_count = 0;
$skipped_rows = []; // To store details of rows skipped due to duplicates
$failed_validation_rows = []; // To store details of rows that failed other validation
$inserted_students_details = []; // To store details for emailing
$email_success_count = 0;
$email_failure_count = 0;

// --- 1. Basic Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: manage_students.php'); // Redirect back if not POST
    exit;
}

$group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);

if (!$group_id) {
    $_SESSION['error_message'] = "Missing or invalid Group ID.";
    header('Location: manage_students.php'); // Redirect back
    exit;
}

// --- 2. Verify Permissions and Get Group/Rep Details ---
if (!verify_rep_manages_group($conn, $course_rep_id, $group_id)) {
    $_SESSION['error_message'] = "Permission Denied: You do not manage this group.";
    header('Location: index.php'); // Redirect to dashboard
    exit;
}

$group_details = get_group_details($conn, $group_id);
$course_rep_details = get_user_details($conn, $course_rep_id);

if (!$group_details || !$course_rep_details) {
    $_SESSION['error_message'] = "Could not retrieve group or user details.";
    error_log("Failed to get group/rep details for group $group_id, rep $course_rep_id in process_bulk_students.php");
    header('Location: manage_students.php?group_id=' . $group_id);
    exit;
}

$department_id = $group_details['department_id'];
$course_rep_level_full = $course_rep_details['level'] ?? null;
$course_rep_level_numeric = null;
if ($course_rep_level_full) {
    preg_match('/^\d+/', $course_rep_level_full, $matches);
    if (isset($matches[0])) {
        $course_rep_level_numeric = $matches[0];
    }
}

if (!$course_rep_level_numeric) {
    $_SESSION['error_message'] = "Could not determine the level for the current class representative. Cannot assign level to students.";
    error_log("Could not determine numeric level for class rep ID: $course_rep_id with level: $course_rep_level_full in process_bulk_students.php");
    header('Location: manage_students.php?group_id=' . $group_id);
    exit;
}

// --- 3. File Upload Handling ---
if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => "File exceeds upload_max_filesize directive in php.ini.",
        UPLOAD_ERR_FORM_SIZE  => "File exceeds MAX_FILE_SIZE directive specified in the HTML form.",
        UPLOAD_ERR_PARTIAL    => "File was only partially uploaded.",
        UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
    ];
    $error_code = $_FILES['student_csv']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error_message_text = $upload_errors[$error_code] ?? "Unknown error."; // Use temporary variable
    $_SESSION['error_message'] = "File upload error: " . $error_message_text;
    header('Location: manage_students.php?group_id=' . $group_id);
    exit;
}

$file_tmp_path = $_FILES['student_csv']['tmp_name'];
$file_name = $_FILES['student_csv']['name'];
$file_size = $_FILES['student_csv']['size'];
$file_type = $_FILES['student_csv']['type'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$allowed_ext = ['csv'];
if (!in_array($file_ext, $allowed_ext)) {
    $_SESSION['error_message'] = "Invalid file type. Only CSV files are allowed.";
    header('Location: manage_students.php?group_id=' . $group_id);
    exit;
}

// Optional: Check file size limit (e.g., 5MB)
if ($file_size > 5 * 1024 * 1024) {
    $_SESSION['error_message'] = "File size exceeds the limit (5MB).";
    header('Location: manage_students.php?group_id=' . $group_id);
    exit;
}

// --- 4. CSV Parsing and Validation ---
$expected_headers = ['application_id', 'first_name', 'last_name', 'middle_name', 'email', 'school_email', 'matric_number']; // Added school_email
$valid_rows = [];
$row_number = 0; // 0 for header row

if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row_number++;
        $num_cols = count($data);

        // --- Header Validation (Row 1) ---
        if ($row_number === 1) {
            $actual_headers_raw = array_map('trim', $data);
            $actual_headers = array_map('strtolower', $actual_headers_raw); // Normalize headers

            // Check if core required headers are present (app_id, first, last, email)
            $required_core_headers = ['application_id', 'first_name', 'last_name', 'email']; // school_email is optional
            if (count(array_intersect($required_core_headers, $actual_headers)) < count($required_core_headers)) {
                 $errors[] = "CSV header missing required columns. Required: application_id, first_name, last_name, email. Found: " . implode(', ', $actual_headers_raw);
                 break; // Stop processing if core headers are wrong
            }
            // Ensure the number of actual headers matches expected for combining later
             if (count($actual_headers) !== count($expected_headers)) {
                 $errors[] = "CSV header column count mismatch. Expected " . count($expected_headers) . " columns based on template, found " . count($actual_headers) . ". Headers found: " . implode(', ', $actual_headers_raw);
                 break;
             }
            // Store header mapping if needed for flexibility, but for now assume fixed order
            continue; // Skip header row
        }

        // --- Data Row Validation ---
        $row_errors = [];
        // Map data to expected keys based on the expected header order
        // Ensure data array has enough elements to match expected headers
        if ($num_cols < count($expected_headers)) {
             // Pad data array with nulls if columns are missing in this row
             $data = array_pad($data, count($expected_headers), null);
        } elseif ($num_cols > count($expected_headers)) {
             // Trim extra columns if too many
             $data = array_slice($data, 0, count($expected_headers));
        }
        $row_data = array_combine($expected_headers, array_map('trim', $data));


        // Required fields check
        if (empty($row_data['application_id'])) { $row_errors[] = "Application ID is required."; }
        if (empty($row_data['first_name'])) { $row_errors[] = "First Name is required."; }
        if (empty($row_data['last_name'])) { $row_errors[] = "Last Name is required."; }
        // Middle name is optional
        if (empty($row_data['email'])) {
            $row_errors[] = "Email is required (for sending login details).";
        } elseif (!filter_var($row_data['email'], FILTER_VALIDATE_EMAIL)) {
            $row_errors[] = "Invalid Email format.";
        }
        // Validate optional school_email if provided
        if (!empty($row_data['school_email']) && !filter_var($row_data['school_email'], FILTER_VALIDATE_EMAIL)) {
             $row_errors[] = "Invalid School Email format.";
        }


        // Optional Matric Number, Middle Name, School Email
        $matric_number = $row_data['matric_number'] ?? null;
        if (empty($matric_number)) $matric_number = null;
        $middle_name = $row_data['middle_name'] ?? null;
        if (empty($middle_name)) $middle_name = null;
        $school_email = $row_data['school_email'] ?? null;
        if (empty($school_email)) $school_email = null;


        // --- Skip Row if Duplicate ---
        $is_duplicate = false;
        $duplicate_reason = '';

        // Check Application ID (only if no validation errors so far)
        if (empty($row_errors)) {
            $stmt_check_app = $conn->prepare("SELECT user_id FROM students WHERE application_id = ?");
            $stmt_check_app->bind_param("s", $row_data['application_id']);
            $stmt_check_app->execute();
            if ($stmt_check_app->get_result()->num_rows > 0) {
                $is_duplicate = true;
                $duplicate_reason = "Duplicate Application ID '{$row_data['application_id']}'";
            }
            $stmt_check_app->close();
        }

        // Check Matric Number if provided and not already found duplicate (and no validation errors)
        if (!$is_duplicate && empty($row_errors) && $matric_number !== null) {
            $stmt_check_mat = $conn->prepare("SELECT user_id FROM students WHERE matric_number = ?");
            $stmt_check_mat->bind_param("s", $matric_number);
            $stmt_check_mat->execute();
            if ($stmt_check_mat->get_result()->num_rows > 0) {
                $is_duplicate = true;
                $duplicate_reason = "Duplicate Matric Number '{$matric_number}'";
            }
            $stmt_check_mat->close();
        }

        // --- Store Row based on outcome ---
        if ($is_duplicate) {
            // Store skipped row details
            $skipped_rows[] = ['line' => $row_number, 'data' => $data, 'reason' => $duplicate_reason];
        } elseif (!empty($row_errors)) {
            // Store row with validation errors
            $failed_validation_rows[] = ['line' => $row_number, 'data' => $data, 'errors' => $row_errors];
        } else {
            // Add validated data for insertion
            $valid_rows[] = [
                'application_id' => $row_data['application_id'],
                'first_name' => $row_data['first_name'],
                'last_name' => $row_data['last_name'],
                'middle_name' => $middle_name,
                'email' => $row_data['email'],
                'school_email' => $school_email, // Added school email
                'matric_number' => $matric_number
            ];
        }
    } // End while loop
    fclose($handle);
} else {
    $errors[] = "Could not open the uploaded CSV file.";
}

// --- 5. Database Insertion (Transaction) ---
if (empty($errors) && !empty($valid_rows)) {
    $conn->begin_transaction();
    $insert_success = true; // Assume success until proven otherwise

    // Prepare statement outside the loop - Removed 'role' column
    $sql_insert = "INSERT INTO students (application_id, matric_number, password, group_id, department_id, email, school_email, first_name, last_name, middle_name, level)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    if (!$stmt_insert) {
        $errors[] = "Database error: Could not prepare statement. " . $conn->error;
        $insert_success = false;
        $conn->rollback(); // Rollback immediately if prepare fails
    } else {
        foreach ($valid_rows as $index => $student_data) {
            $plain_password = generate_random_password(8); // Generate unique password
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

            // Bind parameters - Corrected types and removed role (11 params)
            $stmt_insert->bind_param("sssiissssss", // 11 types
                $student_data['application_id'],     // s
                $student_data['matric_number'],      // s (VARCHAR)
                $hashed_password,                    // s (VARCHAR)
                $group_id,                           // i (INT)
                $department_id,                      // i (INT)
                $student_data['email'],              // s (VARCHAR)
                $student_data['school_email'],       // s (VARCHAR)
                $student_data['first_name'],         // s (VARCHAR)
                $student_data['last_name'],          // s (VARCHAR)
                $student_data['middle_name'],        // s (VARCHAR)
                $course_rep_level_numeric            // s (VARCHAR) - level is VARCHAR
            );

            if (!$stmt_insert->execute()) {
                $insert_success = false;
                // Add to failed validation rows as DB insert error
                $failed_validation_rows[] = [
                    'line' => 'N/A (DB Insert)', // Indicate failure during DB operation
                    'data' => array_values($student_data), // Show data that failed
                    'errors' => ["Database insert error: " . $stmt_insert->error]
                ];
                error_log("Bulk student insert failed for App ID {$student_data['application_id']}: " . $stmt_insert->error);
                break; // Stop processing further rows on first DB error
            } else {
                $success_count++;
                // Store details needed for email (ensure keys match what sendStudentLoginDetails expects)
                $inserted_students_details[] = [
                    'email' => $student_data['email'],
                    'first_name' => $student_data['first_name'],
                    'matric_or_app_id' => $student_data['application_id'], // Use App ID for login identifier
                    'password' => $plain_password
                ];
            }
        } // End foreach loop

        $stmt_insert->close();

        // Commit or Rollback
        if ($insert_success) {
            $conn->commit();
        } else {
            $conn->rollback();
            $errors[] = "Transaction rolled back due to database insertion errors. No students were added in this batch.";
            $success_count = 0; // Reset success count on rollback
            $inserted_students_details = []; // Clear details for emailing
        }
    } // End else (stmt_insert prepared successfully)

} // End if (no initial errors and valid rows exist)


// --- 6. Email Notifications ---
if ($success_count > 0 && !empty($inserted_students_details)) {
    // $email_failures = 0; // Renamed to $email_failure_count
    foreach ($inserted_students_details as $details) {
        if (sendStudentLoginDetails($details['email'], $details['password'], $details['first_name'], $details['matric_or_app_id'])) {
             $email_success_count++;
        } else {
            $email_failure_count++;
            error_log("Failed to send login details email to: " . $details['email']);
        }
    }
    // Warning message moved to feedback section below
}

// --- 7. Set Feedback and Redirect ---
$final_message = ''; // Build the final message string

// Success Count
if ($success_count > 0) {
    $final_message .= "<div class='alert alert-success'>Successfully imported <strong>{$success_count}</strong> students.</div>";
}

// Skipped Count & Details
$skipped_count = count($skipped_rows);
if ($skipped_count > 0) {
    $final_message .= "<div class='alert alert-info'>Skipped <strong>{$skipped_count}</strong> rows due to duplicate Application ID or Matric Number:<br>";
    $final_message .= "<ul>";
    foreach ($skipped_rows as $skip) {
        $final_message .= "<li>Line {$skip['line']}: [" . implode(', ', $skip['data']) . "] - Reason: {$skip['reason']}</li>";
    }
    $final_message .= "</ul></div>";
}

// Validation Failure Count & Details
$validation_fail_count = count($failed_validation_rows);
if ($validation_fail_count > 0) {
    $final_message .= "<div class='alert alert-danger'><strong>{$validation_fail_count}</strong> rows failed validation or database insertion:<br>";
     $final_message .= "<ul>";
    foreach ($failed_validation_rows as $fail) {
        $final_message .= "<li>Line {$fail['line']}: [" . implode(', ', $fail['data']) . "] - Errors: " . implode('; ', $fail['errors']) . "</li>";
    }
     $final_message .= "</ul></div>";
}

// General Errors (like header mismatch)
if (!empty($errors)) {
     $final_message .= "<div class='alert alert-danger'><strong>Import Errors:</strong><br>" . implode("<br>", $errors) . "</div>";
}

// Email Status
if ($success_count > 0) { // Only report email status if inserts were attempted
    $final_message .= "<div class='alert alert-light'>Email Notifications: {$email_success_count} sent successfully, {$email_failure_count} failed.</div>";
     if ($email_failure_count > 0) {
         $final_message .= "<div class='alert alert-warning'>Please check system logs for email sending errors or notify students with failed emails manually.</div>";
     }
}


// Handle case where file was valid but contained no processable rows
if ($success_count === 0 && $skipped_count === 0 && $validation_fail_count === 0 && empty($errors)) {
     $final_message = "<div class='alert alert-warning'>The uploaded CSV file was processed, but contained no valid student data rows to import or skip.</div>";
}

// Store the combined message in the session
if (!empty($final_message)) {
    // Use a generic session key for feedback
    $_SESSION['import_feedback'] = $final_message;
}


// Redirect back
header('Location: manage_students.php?group_id=' . $group_id);
exit;
?>
