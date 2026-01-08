<?php
// Start session and include necessary files
require_once __DIR__ . '/includes/db_connect.php';  
require_once __DIR__ . '/includes/functions.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in as a class rep
check_course_rep_login();

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Basic validation for required fields
    // Changed 'matric_number' to 'student_identifier' to match the form
    if (!isset($_POST['session_id'], $_POST['course_id'], $_POST['group_id'], $_POST['student_identifier'], $_POST['photo_data'])) {
        // Redirect back with a general error if essential fields are missing
        header("Location: take_attendance.php?group_id=" . urlencode($_POST['group_id'] ?? '') . "&course_id=" . urlencode($_POST['course_id'] ?? '') . "&error=" . urlencode("Missing required form data."));
        exit;
    }

    $session_id = trim($_POST['session_id']);
    $course_id = (int)$_POST['course_id'];
    $group_id = (int)$_POST['group_id'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : "";
    $course_rep_id = $_SESSION['user_id']; // Get class rep ID from session

    $redirect_url = "take_attendance.php?group_id=" . urlencode($group_id) . "&course_id=" . urlencode($course_id);

    // --- Validation ---

    // 1. Verify the class rep manages this group
    if (!verify_rep_manages_group($conn, $course_rep_id, $group_id)) {
        header("Location: " . $redirect_url . "&error=" . urlencode("Permission Denied: You do not manage this group."));
        exit;
    }

    // 2. Find the student globally by matric number or application ID
    // Use the identifier from the form field (name="student_identifier")
    $student_identifier = trim($_POST['student_identifier']);
    $student = find_student_globally_by_identifier($conn, $student_identifier);
    if (!$student) {
        header("Location: " . $redirect_url . "&error=" . urlencode("Student with identifier '{$student_identifier}' not found anywhere. Ensure correct Matric or Application ID is entered."));
        exit;
    }
    $student_id = (int)$student['user_id']; // Get student's user_id

    // 3. Verify the found student is ELIGIBLE for this course/group session
    $is_regular_student = ((int)$student['group_id'] === $group_id);
    $is_carryover_enrolled = is_carryover_student($conn, $student_id, $course_id, $group_id);

    if (!$is_regular_student && !$is_carryover_enrolled) {
         // Student exists but is neither in the group nor enrolled as carryover for this course/group
         header("Location: " . $redirect_url . "&error=" . urlencode("Student {$student['first_name']} {$student['last_name']} ({$student_identifier}) is not eligible (not in group or enrolled as carryover) for this course session."));
         exit;
     }


    // 4. Handle Photo Upload (from base64 photo_data POST param)
    $photo_reference = null; // Will store the URL to the saved image

    if (isset($_POST['photo_data']) && !empty($_POST['photo_data'])) {
        $photo_data = $_POST['photo_data'];
        // Extract base64 data and mime type
        if (preg_match('/^data:(image\/(jpeg|png|gif));base64,(.*)$/', $photo_data, $matches)) {
            $photo_type = $matches[1]; // e.g., image/jpeg
            $base64_image = $matches[3];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($photo_type, $allowed_mime_types)) {
                header("Location: " . $redirect_url . "&error=" . urlencode("Invalid image type. Only JPG, PNG, GIF allowed."));
                exit;
            }
            $image_content = base64_decode($base64_image);
            if ($image_content === false) {
                header("Location: " . $redirect_url . "&error=" . urlencode("Failed to decode image data."));
                exit;
            }
            // Generate unique filename
            $ext = ($photo_type === 'image/jpeg') ? 'jpg' : (($photo_type === 'image/png') ? 'png' : 'gif');
            $filename = 'attendance_' . uniqid() . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/attendance_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            $file_path = $upload_dir . $filename;
            if (file_put_contents($file_path, $image_content) === false) {
                header("Location: " . $redirect_url . "&error=" . urlencode("Failed to save image on server."));
                exit;
            }
            // Build accessible URL (adjust base URL as needed)
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $photo_reference = $base_url . '/uploads/attendance_photos/' . $filename;
            // Optional: check file size
            if (filesize($file_path) > 5 * 1024 * 1024) {
                unlink($file_path);
                header("Location: " . $redirect_url . "&error=" . urlencode("Image file too large (max 5MB)."));
                exit;
            }
        } else {
            header("Location: " . $redirect_url . "&error=" . urlencode("Invalid photo data format."));
            exit;
        }
    } else {
        // Handle missing photo_data
        header("Location: " . $redirect_url . "&error=" . urlencode("No photo data provided."));
        exit;
    }

    // --- Record Attendance ---

    if ($photo_reference) {
        $status = 'Present (No Phone)'; // Or adjust based on specific logic if needed

        // --- Database Insertion Logic ---
        $attendance_date = date('Y-m-d'); // Use current server date
        $attendance_time = date('H:i:s'); // Use current server time

        // Check if student is already marked for this session to prevent duplicates
        $sql_check = "SELECT attendance_id FROM attendancerecords WHERE session_id = ? AND student_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $duplicate_found = false;
        if ($stmt_check) {
            $stmt_check->bind_param("si", $session_id, $student_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $duplicate_found = true;
            }
            $stmt_check->close();
        } else {
            error_log("Error preparing check duplicate statement: " . $conn->error);
            // Optionally handle this error more gracefully, but proceeding might still be okay if duplicate insertion fails later due to constraints.
        }

            if ($duplicate_found) {
                // Use the identifier variable which holds the input value
                header("Location: " . $redirect_url . "&error=" . urlencode("Student {$student['first_name']} {$student['last_name']} ({$student_identifier}) is already marked in this session."));
                exit;
            }

        // Prepare the INSERT statement
        $sql_insert = "INSERT INTO attendancerecords (
                           session_id, student_id, course_id, group_id, marked_by_user_id,
                           status, photo_reference, notes, attendance_date, attendance_time
                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        if ($stmt_insert) {
            $stmt_insert->bind_param("siiissssss",
                $session_id,
                $student_id,
                $course_id,
                $group_id,
                $course_rep_id,
                $status,
                $photo_reference, // Store the URL to the saved image
                $notes,
                $attendance_date,
                $attendance_time // Semicolon was missing here
            );

            if ($stmt_insert->execute()) {
                // Success
                 $stmt_insert->close();
                 // Use the identifier variable which holds the input value
                header("Location: " . $redirect_url . "&success=" . urlencode("Attendance for {$student['first_name']} {$student['last_name']} ({$student_identifier}) marked manually."));
                 exit;
            } else {
                // Execution failed
                error_log("Failed to execute manual attendance insert: " . $stmt_insert->error);
                 $stmt_insert->close();
                 header("Location: " . $redirect_url . "&error=" . urlencode("Failed to save attendance record to the database. Error: " . $stmt_insert->error)); // Show specific DB error might be revealing
                 exit;
             }
        } else {
             // Prepare failed
             error_log("Failed to prepare manual attendance insert statement: " . $conn->error);
             header("Location: " . $redirect_url . "&error=" . urlencode("Database error preparing statement. Please contact admin."));
            exit;
         }

    } else {
         // This should not happen if photo validation passes, but as a fallback
         header("Location: " . $redirect_url . "&error=" . urlencode("Photo reference missing after upload attempt."));
         exit;
     }


} else {
    // If accessed directly via GET, redirect back
     header("Location: index.php"); // Redirect to dashboard or appropriate page
     exit;
}

// Close the connection if it was opened (though redirects exit anyway)
 $conn->close();
?>
