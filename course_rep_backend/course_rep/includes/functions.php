<?php
// functions.php for Class Rep Panel

// Ensure database connection is included before using these functions
// require_once 'db_connect.php';

/**
 * Fetches the group ID associated with a class rep.
 * Assumes a class rep belongs to only one group for simplicity here.
 * Adjust if a class rep can manage multiple groups.
 */
function get_course_rep_group_id(mysqli $conn, int $course_rep_user_id): ?int {
    $sql = "SELECT group_id FROM courserepgroup WHERE course_rep_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_course_rep_group_id: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $course_rep_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['group_id'] : null;
}

/**
 * Fetches the name of a department group.
 */
function get_group_name(mysqli $conn, int $group_id): ?string {
    $sql = "SELECT group_name FROM departmentgroups WHERE group_id = ?";
    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        error_log("Prepare failed for get_group_name: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['group_name'] : null;
}

/**
 * Fetches details for a specific user by their user ID.
 */
function get_user_details(mysqli $conn, int $user_id): ?array {
    // Fetch details including level from the users table
    $sql = "SELECT user_id, username, matric_number, role, department_id, group_id, email, school_email, first_name, last_name, middle_name, level
            FROM users
            WHERE user_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    // Ensure clean error handling block
    if ($stmt === false) {
        error_log("Prepare failed for get_user_details: (" . $conn->errno . ") " . $conn->error);
        return null; // Exit if statement preparation failed
    }
    // Continue if prepare was successful
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user; // Returns user details array or null if not found
}


/**
 * Fetches all courses in the system.
 * Used for dropdowns or lists of all available courses.
 */
function get_all_courses(mysqli $conn): array {
    $sql = "SELECT course_id, course_code, course_name FROM courses ORDER BY course_code";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("Query failed for get_all_courses: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}


/**
 * Fetches courses associated with a specific department group.
 * This might need adjustment based on how courses are linked to groups (e.g., via department).
 * Fetch courses directly associated with the group via the group_course_lecturer_assignments table.
 */
function get_courses_for_group(mysqli $conn, int $group_id): array {
    // Fetch courses linked directly to the group via the group_course_lecturer_assignments table
     $sql = "SELECT DISTINCT c.course_id, c.course_name, c.course_code
             FROM courses c
             JOIN group_course_lecturer_assignments gcl ON c.course_id = gcl.course_id
             WHERE gcl.group_id = ?
             ORDER BY c.course_code";
    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        error_log("Prepare failed for get_courses_for_group: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    // Bind the group_id
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $courses;
}

/**
 * Fetches the name and code of a specific course.
 */
function get_course_name_and_code(mysqli $conn, int $course_id): ?array {
    $sql = "SELECT course_name, course_code FROM courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_course_name_and_code: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row; // Returns ['course_name' => '...', 'course_code' => '...'] or null
}


/**
 * Fetches all students belonging to a specific department group AND level.
 */
function get_students_in_group_by_level(mysqli $conn, int $group_id, string $level): array {
    // Fetch students matching the group_id AND the specified level.
    // Note: Comparing level strings directly. Ensure consistency (e.g., '100' vs '100 LEVEL').
    // The students table seems to store level as '100', '200', etc.
    $sql = "SELECT user_id, application_id, matric_number, first_name, last_name, email, school_email, level
            FROM students
            WHERE group_id = ? AND level = ?
            ORDER BY last_name, first_name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_students_in_group_by_level: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    // Bind group_id and level
    $stmt->bind_param("is", $group_id, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $students;
}

/**
 * Fetches all users with the 'lecturer' role.
 * Used for populating dropdowns when associating lecturers.
 */
function get_all_lecturers(mysqli $conn): array {
    $sql = "SELECT user_id, username, first_name, last_name
            FROM users
            WHERE role = 'lecturer'
            ORDER BY last_name, first_name";
    $result = $conn->query($sql);
    if (!$result) {
         error_log("Query failed for get_all_lecturers: (" . $conn->errno . ") " . $conn->error);
         return [];
    }
    $lecturers = $result->fetch_all(MYSQLI_ASSOC);
    return $lecturers;
}

/**
 * Fetches the lecturer currently associated with a specific course within a group.
 */
function get_assigned_lecturer_for_course_group(mysqli $conn, int $group_id, int $course_id): ?array {
    // Use the correct table name: group_course_lecturer_assignments
    $sql = "SELECT u.user_id, u.username, u.first_name, u.last_name
            FROM group_course_lecturer_assignments gcl
            JOIN users u ON gcl.lecturer_id = u.user_id
            WHERE gcl.group_id = ? AND gcl.course_id = ? AND u.role = 'lecturer'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_assigned_lecturer_for_course_group: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("ii", $group_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lecturer = $result->fetch_assoc();
    $stmt->close();
    return $lecturer; // Returns lecturer details or null
}


/**
 * Fetches all lecturer assignments for a specific group, including course details.
 */
function get_group_course_lecturer_assignments(mysqli $conn, int $group_id): array {
     // Use the correct table name: group_course_lecturer_assignments
     $sql = "SELECT
        gcl.course_id,
        c.course_code,
        c.course_name,
        u.user_id AS lecturer_id,
        u.username AS lecturer_username,
        u.first_name AS lecturer_first_name,
        u.last_name AS lecturer_last_name
    FROM group_course_lecturer_assignments gcl
    JOIN courses c ON gcl.course_id = c.course_id
    JOIN users u ON gcl.lecturer_id = u.user_id
    WHERE gcl.group_id = ?
      AND u.role = 'lecturer'
    ORDER BY c.course_code";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_group_course_lecturer_assignments: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $assignments;
}


/**
 * Saves the complete set of course-lecturer assignments for a specific group.
 * This function typically deletes all existing assignments for the group
 * and inserts the new ones provided. Uses a transaction.
 *
 * @param mysqli $conn Database connection.
 * @param int $group_id The ID of the group.
 * @param array $assignments An associative array [course_id => lecturer_id].
 *                           Only pairs with lecturer_id > 0 will be saved.
 * @return bool True on success, false on failure.
 */
function save_group_course_lecturer_assignments(mysqli $conn, int $group_id, array $assignments): bool {
    $conn->begin_transaction();

    try {
        // Step 1: Delete existing assignments for this group
        // Use the correct table name: group_course_lecturer_assignments
        $delete_sql = "DELETE FROM group_course_lecturer_assignments WHERE group_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        if (!$delete_stmt) {
            throw new Exception("Prepare failed (delete): " . $conn->error);
        }
        $delete_stmt->bind_param("i", $group_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("Execute failed (delete): " . $delete_stmt->error);
        }
        $delete_stmt->close();

        // Step 2: Insert new assignments
        // Use the correct table name: group_course_lecturer_assignments
        $insert_sql = "INSERT INTO group_course_lecturer_assignments (group_id, course_id, lecturer_id) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            throw new Exception("Prepare failed (insert): " . $conn->error);
        }

        foreach ($assignments as $course_id => $lecturer_id) {
            // Only insert valid assignments (lecturer_id > 0)
            if ($lecturer_id > 0) {
                $insert_stmt->bind_param("iii", $group_id, $course_id, $lecturer_id);
                if (!$insert_stmt->execute()) {
                    // Throw exception to trigger rollback
                    throw new Exception("Execute failed (insert for course $course_id): " . $insert_stmt->error);
                }
            }
        } // End foreach loop
        $insert_stmt->close();

        // If all operations succeeded, commit the transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        error_log("Transaction failed in save_group_course_lecturer_assignments for group $group_id: " . $e->getMessage());
        return false;
    }
}


/**
 * Associates a lecturer with a specific course for a specific group.
 * Uses INSERT ... ON DUPLICATE KEY UPDATE to handle existing associations.
 */
function associate_lecturer_to_course_group(mysqli $conn, int $group_id, int $course_id, int $lecturer_id): bool {
    // Ensure the user being assigned is actually a lecturer
    $check_sql = "SELECT role FROM users WHERE user_id = ? AND role = 'lecturer'";
    $check_stmt = $conn->prepare($check_sql);
     if (!$check_stmt) {
         error_log("Prepare failed for associate_lecturer (check): (" . $conn->errno . ") " . $conn->error);
         return false;
     }
    $check_stmt->bind_param("i", $lecturer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows === 0) {
        error_log("Attempted to assign non-lecturer (ID: $lecturer_id) to course group.");
        $check_stmt->close();
        return false; // User is not a lecturer
    }
    $check_stmt->close();


    // Use INSERT ... ON DUPLICATE KEY UPDATE to either insert or update the lecturer
    // Use the correct table name: group_course_lecturer_assignments
    $sql = "INSERT INTO group_course_lecturer_assignments (group_id, course_id, lecturer_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE lecturer_id = VALUES(lecturer_id)";
    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        error_log("Prepare failed for associate_lecturer_to_course_group: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("iii", $group_id, $course_id, $lecturer_id);
    $success = $stmt->execute();
     if (!$success) {
         error_log("Execute failed for associate_lecturer_to_course_group: (" . $stmt->errno . ") " . $stmt->error);
     }
    $stmt->close();
    return $success;
}

/**
 * Starts a new attendance session.
 * Generates a unique session ID and records the start time.
 */
function start_attendance_session(mysqli $conn, int $course_id, int $group_id, int $initiated_by_user_id): ?string {
    $session_id = uniqid('sess_', true); // Generate a unique session ID
    $start_time = date('Y-m-d H:i:s'); // Current timestamp

    $sql = "INSERT INTO attendancesessions (session_id, course_id, group_id, session_start_time, initiated_by_user_id)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for start_attendance_session: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("siisi", $session_id, $course_id, $group_id, $start_time, $initiated_by_user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success ? $session_id : null;
}

/**
 * Marks attendance for a student within a specific session.
 * Handles different statuses and optional photo/notes.
 */
function mark_attendance(mysqli $conn, string $session_id, int $student_id, int $course_id, int $group_id, int $marked_by_user_id, string $status, ?string $photo_reference = null, ?string $notes = null): bool {
    $attendance_date = date('Y-m-d');
    $attendance_time = date('H:i:s');

    // Prevent duplicate entries for the same student in the same session
    $check_sql = "SELECT attendance_id FROM attendancerecords WHERE session_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
         error_log("Prepare failed for mark_attendance (check): (" . $conn->errno . ") " . $conn->error);
         return false;
     }
    $check_stmt->bind_param("si", $session_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        // Optionally update existing record or just return true/false indicating already marked
        error_log("Student $student_id already marked for session $session_id.");
        $check_stmt->close();
        return true; // Or false if you want to prevent updates this way
    }
    $check_stmt->close();


    $sql = "INSERT INTO attendancerecords
                (session_id, student_id, course_id, group_id, marked_by_user_id, status, photo_reference, notes, attendance_date, attendance_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        error_log("Prepare failed for mark_attendance: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("siiiisssss", $session_id, $student_id, $course_id, $group_id, $marked_by_user_id, $status, $photo_reference, $notes, $attendance_date, $attendance_time);
    $success = $stmt->execute();
     if (!$success) {
         error_log("Execute failed for mark_attendance: (" . $stmt->errno . ") " . $stmt->error);
     }
    $stmt->close();
    return $success;
}

/**
 * Ends an attendance session by recording the end time.
 */
function end_attendance_session(mysqli $conn, string $session_id): bool {
    $end_time = date('Y-m-d H:i:s');
    $sql = "UPDATE attendancesessions SET session_end_time = ? WHERE session_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for end_attendance_session: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("ss", $end_time, $session_id);
    $success = $stmt->execute(); // Correctly assign result to $success
    // Removed incorrect $result = $stmt->get_result();
    $stmt->close();
    return $success; // Return the actual success status
}


/**
 * Fetches detailed attendance records for a specific course and group,
 * optionally filtered by date range, structured for table display.
 * Includes all students enrolled (regular or carryover) for the course/group and all sessions, defaulting to 'Absent'.
 */
function get_detailed_attendance_for_course_group(mysqli $conn, int $course_id, int $group_id, ?string $start_date = null, ?string $end_date = null): array {
    // 1. Get all students enrolled for this course/group (regular + carryover)
    $enrolled_students_sql = "
        -- Regular students in the group
        SELECT s.user_id, s.application_id, s.matric_number, s.first_name, s.last_name, s.email, s.school_email, s.level, 'regular' as enrollment_status -- Added status alias
        FROM students s
        WHERE s.group_id = ?
        UNION
        -- Carryover students enrolled in this course/group
        SELECT s.user_id, s.application_id, s.matric_number, s.first_name, s.last_name, s.email, s.school_email, s.level, 'carryover' as enrollment_status -- Added status alias
        FROM carryover_enrollments ce
        JOIN students s ON ce.student_id = s.user_id
        WHERE ce.course_id = ? AND ce.target_group_id = ?
    ";
    $enrolled_stmt = $conn->prepare($enrolled_students_sql);
    if (!$enrolled_stmt) {
        error_log("Prepare failed for get_detailed_attendance (enrolled students): (" . $conn->errno . ") " . $conn->error);
        return ['students' => [], 'sessions' => [], 'attendance_matrix' => []];
    }
    // Bind group_id, course_id, group_id
    $enrolled_stmt->bind_param("iii", $group_id, $course_id, $group_id);
    $enrolled_stmt->execute();
    $enrolled_result = $enrolled_stmt->get_result();
    $all_enrolled_students_raw = $enrolled_result->fetch_all(MYSQLI_ASSOC);
    $enrolled_stmt->close();

    if (empty($all_enrolled_students_raw)) {
        return ['students' => [], 'sessions' => [], 'attendance_matrix' => []]; // No students, nothing to show
    }

    // Create a map and sort students by name
    $student_map = []; // Map user_id to student details
    foreach ($all_enrolled_students_raw as $student) {
        $student_map[$student['user_id']] = $student;
    }
    // Sort by last name, then first name
    uasort($student_map, function ($a, $b) {
        $cmp = strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
        if ($cmp == 0) {
            $cmp = strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        }
        return $cmp;
    });

    // 2. Get all relevant sessions for the course and group within the date range
    $session_sql = "SELECT session_id, session_start_time AS attendance_date, location
                    FROM attendancesessions
                    WHERE course_id = ? AND group_id = ?";
    $session_params = [$course_id, $group_id];
    $session_types = "ii";

    if ($start_date !== null) {
        // Assuming session_start_time is TIMESTAMP or DATETIME
        $session_sql .= " AND DATE(session_start_time) >= ?";
        $session_params[] = $start_date;
        $session_types .= "s";
    }
    if ($end_date !== null) {
        $session_sql .= " AND DATE(session_start_time) <= ?";
        $session_params[] = $end_date;
        $session_types .= "s";
    }
    $session_sql .= " ORDER BY session_start_time ASC";

    $session_stmt = $conn->prepare($session_sql);
    if (!$session_stmt) {
        error_log("Prepare failed for get_detailed_attendance (sessions): (" . $conn->errno . ") " . $conn->error);
        return ['students' => $student_map, 'sessions' => [], 'attendance_matrix' => []]; // Return students but no sessions
    }
    $session_stmt->bind_param($session_types, ...$session_params);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $all_sessions_raw = $session_result->fetch_all(MYSQLI_ASSOC);
    $session_stmt->close();

    if (empty($all_sessions_raw)) {
         // Return students but no sessions or matrix
         return ['students' => $student_map, 'sessions' => [], 'attendance_matrix' => []];
    }

    $session_ids = array_column($all_sessions_raw, 'session_id');
    $sessions_map = []; // Map session_id to session details
    foreach ($all_sessions_raw as $sess) {
         try {
             $date_obj = new DateTime($sess['attendance_date']);
             $display_header = $date_obj->format('M j'); // e.g., Apr 1
         } catch (Exception $e) {
             $display_header = $sess['attendance_date']; // Fallback
             error_log("Date formatting error: " . $e->getMessage());
         }
         $sessions_map[$sess['session_id']] = [
             'attendance_date' => $sess['attendance_date'],
             'display_header' => $display_header,
             'location' => $sess['location'] ?? null // Handle potential null location
         ];
    }


    // 3. Get existing attendance records for these sessions and students
    $attendance_records = []; // Initialize
    if (!empty($session_ids) && !empty($student_map)) {
        $records_sql = "SELECT student_id, session_id, status
                        FROM attendancerecords
                        WHERE session_id IN (" . implode(',', array_fill(0, count($session_ids), '?')) . ")
                          AND student_id IN (" . implode(',', array_fill(0, count($student_map), '?')) . ")";

        $record_params = array_merge($session_ids, array_keys($student_map));
        $record_types = str_repeat('s', count($session_ids)) . str_repeat('i', count($student_map));

        $record_stmt = $conn->prepare($records_sql);
         if (!$record_stmt) {
            error_log("Prepare failed for get_detailed_attendance (records): (" . $conn->errno . ") " . $conn->error);
            // Proceed without records, defaulting all to Absent
        } else {
            $record_stmt->bind_param($record_types, ...$record_params);
            $record_stmt->execute();
            $record_result = $record_stmt->get_result();
            $attendance_records_raw = $record_result->fetch_all(MYSQLI_ASSOC);
            $record_stmt->close();

            // Re-index attendance records for quick lookup: [student_id][session_id] => status
            foreach ($attendance_records_raw as $rec) {
                if (!isset($attendance_records[$rec['student_id']])) {
                    $attendance_records[$rec['student_id']] = [];
                }
                $attendance_records[$rec['student_id']][$rec['session_id']] = $rec['status'];
            }
        }
    }

    // 4. Calculate Overall Attendance Percentage for each student
    $total_completed_sessions = count($sessions_map); // Total number of sessions fetched

    foreach ($student_map as $student_id => &$student_info) { // Use reference to modify directly
        $present_count = 0;
        if (isset($attendance_records[$student_id])) {
            foreach ($attendance_records[$student_id] as $session_id => $status) {
                // Only count sessions that are in our fetched $sessions_map (completed sessions)
                if (isset($sessions_map[$session_id]) && ($status === 'Present' || $status === 'Present (No Phone)')) {
                    $present_count++;
                }
            }
        }

        // Calculate percentage
        if ($total_completed_sessions > 0) {
            $student_info['overall_attendance_percentage'] = round(($present_count / $total_completed_sessions) * 100, 1);
        } else {
            $student_info['overall_attendance_percentage'] = 0.0; // Or null if preferred
        }
    }
    unset($student_info); // Unset the reference

    // 5. Build the final matrix, defaulting to 'Absent'
    $attendance_matrix = [];
    foreach ($student_map as $student_id => $student_info) {
        $attendance_matrix[$student_id] = [];
        foreach ($sessions_map as $session_id => $session_info) {
            // Check if a record exists, otherwise default to 'Absent'
            $status = $attendance_records[$student_id][$session_id] ?? 'Absent';
            $attendance_matrix[$student_id][$session_id] = $status;
        }
    }

    // Return the structured data including the percentage in the student map
    return [
        'students' => $student_map, // Now includes 'overall_attendance_percentage'
        'sessions' => $sessions_map, // Already ordered by date
        'attendance_matrix' => $attendance_matrix
    ];
}


// --- Authentication & Authorization ---

/**
 * Checks if a class rep is logged in. Redirects to login page if not.
 */
function check_course_rep_login() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Check if user is logged in and has the 'course_rep' role
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'course_rep') {
        // Store the intended destination to redirect after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: _auth/login.php'); // Adjust path as needed
        exit;
    }
}

/**
 * Verifies if the logged-in class rep manages the specified group.
 */
function verify_rep_manages_group(mysqli $conn, int $course_rep_user_id, int $group_id): bool {
    $sql = "SELECT COUNT(*) as count FROM courserepgroup WHERE course_rep_id = ? AND group_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for verify_rep_manages_group: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $course_rep_user_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return ($row && $row['count'] > 0);
}

// --- Data Fetching ---

/**
 * Fetches all groups managed by a specific class rep.
 */
function get_course_rep_groups(mysqli $conn, int $course_rep_user_id): array {
    $sql = "SELECT dg.group_id, dg.group_name, dg.department_id, d.department_name
            FROM courserepgroup crg
            JOIN departmentgroups dg ON crg.group_id = dg.group_id
            LEFT JOIN departments d ON dg.department_id = d.department_id
            WHERE crg.course_rep_id = ?
            ORDER BY d.department_name, dg.group_name LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_course_rep_groups: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $course_rep_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $groups;
}


/**
 * Fetches attendance records for a specific session ID.
 */
function get_attendance_for_session(mysqli $conn, string $session_id): array {
    $sql = "SELECT ar.student_id, ar.status, ar.attendance_time, ar.notes, ar.photo_reference, ar.created_at,
                   s.first_name, s.last_name, s.matric_number
            FROM attendancerecords ar
            JOIN students s ON ar.student_id = s.user_id
            WHERE ar.session_id = ?
            ORDER BY ar.attendance_time DESC, s.last_name ASC"; // Show most recent first
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_attendance_for_session: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $records;
}


/**
 * Finds a student within a specific group by Matric Number or Application ID.
 */
function find_student_by_identifier(mysqli $conn, string $identifier, int $group_id): ?array {
    // Trim whitespace from identifier
    $identifier = trim($identifier);
    if (empty($identifier)) {
        return null;
    }

    // Check if the identifier looks like a matric number or application ID (adjust logic if needed)
    // For simplicity, let's assume matric numbers might contain '/' and app IDs are alphanumeric.
    // A more robust check might be needed based on actual formats.
    $is_matric = strpos($identifier, '/') !== false;
    $is_app_id = ctype_alnum($identifier); // Basic check

    $sql = "SELECT user_id, application_id, matric_number, first_name, last_name, email, level, group_id
            FROM students
            WHERE group_id = ? AND (matric_number = ? OR application_id = ?)
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for find_student_by_identifier: (" . $conn->errno . ") " . $conn->error);
        return null;
    }

    // Bind parameters: group_id, identifier for matric, identifier for app_id
    $stmt->bind_param("iss", $group_id, $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    return $student; // Returns student details array or null if not found
}


/**
 * Finds a student globally (across all groups) by Matric Number or Application ID.
 */
function find_student_globally_by_identifier(mysqli $conn, string $identifier): ?array {
    // Trim whitespace from identifier
    $identifier = trim($identifier);
    if (empty($identifier)) {
        return null;
    }

    $sql = "SELECT user_id, application_id, matric_number, first_name, last_name, email, level, group_id, department_id
            FROM students
            WHERE matric_number = ? OR application_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for find_student_globally_by_identifier: (" . $conn->errno . ") " . $conn->error);
        return null;
    }

    // Bind parameters: identifier for matric, identifier for app_id
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    return $student; // Returns student details array or null if not found
}


/**
 * Fetches details for a specific student by their user ID.
 */
function get_student_details(mysqli $conn, int $student_user_id): ?array {
    $sql = "SELECT user_id, application_id, matric_number, first_name, last_name, middle_name, email, school_email, level, group_id, department_id
            FROM students
            WHERE user_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_student_details: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $student_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    return $student; // Returns student details array or null if not found
}


/**
 * Fetches details for a specific group, including department name.
 */
function get_group_details(mysqli $conn, int $group_id): ?array {
    $sql = "SELECT dg.group_id, dg.group_name, d.department_id, d.department_name
            FROM departmentgroups dg
            LEFT JOIN departments d ON dg.department_id = d.department_id
            WHERE dg.group_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_group_details: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    return $details;
}

/**
 * Fetches courses assigned to a specific group, including the assigned lecturer's name.
 */
function get_assigned_courses_for_group(mysqli $conn, int $group_id): array {
    // Use the correct table name: group_course_lecturer_assignments
    $sql = "SELECT c.course_id, c.course_code, c.course_name,
                   u.user_id as lecturer_id, u.first_name as lecturer_first_name, u.last_name as lecturer_last_name
            FROM group_course_lecturer_assignments gcl
            JOIN courses c ON gcl.course_id = c.course_id
            JOIN users u ON gcl.lecturer_id = u.user_id
            WHERE gcl.group_id = ? AND u.role = 'lecturer'
            ORDER BY c.course_code";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_assigned_courses_for_group: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $courses;
}


/**
 * Fetches all students eligible for attendance in a specific course/group session.
 * Includes regular students from the group AND carryover students enrolled for that course/group.
 * Returns an array of student details, ordered by name.
 */
function get_eligible_students_for_session(mysqli $conn, int $course_id, int $group_id): array {
     $sql = "
        -- Regular students in the group
        SELECT s.user_id, s.application_id, s.matric_number, s.first_name, s.last_name, s.email, s.school_email, s.level, 'regular' as enrollment_status
        FROM students s
        WHERE s.group_id = ?
        UNION
        -- Carryover students enrolled in this course/group
        SELECT s.user_id, s.application_id, s.matric_number, s.first_name, s.last_name, s.email, s.school_email, s.level, 'carryover' as enrollment_status
        FROM carryover_enrollments ce
        JOIN students s ON ce.student_id = s.user_id
        WHERE ce.course_id = ? AND ce.target_group_id = ?
        ORDER BY last_name ASC, first_name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_eligible_students_for_session: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    // Bind group_id, course_id, group_id
    $stmt->bind_param("iii", $group_id, $course_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $students;
}


/**
 * Checks if a student is enrolled as a carryover student for a specific course/group.
 */
function is_carryover_student(mysqli $conn, int $student_id, int $course_id, int $target_group_id): bool {
    $sql = "SELECT enrollment_id FROM carryover_enrollments
            WHERE student_id = ? AND course_id = ? AND target_group_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for is_carryover_student: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("iii", $student_id, $course_id, $target_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_carryover = $result->num_rows > 0;
    $stmt->close();
    return $is_carryover;
}

/**
 * Checks if a carryover student is allowed to self-mark attendance for a specific course/group.
 */
function can_self_mark_attendance(mysqli $conn, int $student_id, int $course_id, int $target_group_id): bool {
    $sql = "SELECT self_mark_allowed FROM carryover_enrollments
            WHERE student_id = ? AND course_id = ? AND target_group_id = ? AND self_mark_allowed = 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for can_self_mark_attendance: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("iii", $student_id, $course_id, $target_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $can_self_mark = $result->num_rows > 0;
    $stmt->close();
    return $can_self_mark;
}


// --- Carryover Enrollment Functions ---

/**
 * Enrolls a student as a carryover student for a specific course into a target group.
 *
 * @param mysqli $conn Database connection.
 * @param int $student_id The ID of the student to enroll.
 * @param int $course_id The ID of the course.
 * @param int $target_group_id The ID of the group the student is enrolling into for this course.
 * @param bool $self_mark_allowed Whether the student is allowed to self-mark attendance (1 for true, 0 for false).
 * @return bool True on success, false on failure.
 */
function enroll_carryover_student(mysqli $conn, int $student_id, int $course_id, int $target_group_id, bool $self_mark_allowed): bool {
    $sql = "INSERT INTO carryover_enrollments (student_id, course_id, target_group_id, self_mark_allowed)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE self_mark_allowed = VALUES(self_mark_allowed)"; // Update self_mark if already enrolled

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for enroll_carryover_student: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $self_mark_int = $self_mark_allowed ? 1 : 0;
    $stmt->bind_param("iiii", $student_id, $course_id, $target_group_id, $self_mark_int);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Execute failed for enroll_carryover_student: (" . $stmt->errno . ") " . $stmt->error);
    }
    $stmt->close();
    return $success;
}

/**
 * Fetches all carryover enrollments targeting a specific group.
 * Includes student and course details.
 *
 * @param mysqli $conn Database connection.
 * @param int $target_group_id The ID of the group.
 * @return array An array of carryover enrollment details.
 */
function get_carryover_enrollments_for_group(mysqli $conn, int $target_group_id): array {
    $sql = "SELECT ce.enrollment_id, ce.student_id, ce.course_id, ce.target_group_id, ce.enrollment_date, ce.self_mark_allowed,
                   s.first_name, s.last_name, s.matric_number, s.application_id,
                   c.course_code, c.course_name
            FROM carryover_enrollments ce
            JOIN students s ON ce.student_id = s.user_id
            JOIN courses c ON ce.course_id = c.course_id
            WHERE ce.target_group_id = ?
            ORDER BY s.last_name, s.first_name, c.course_code";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for get_carryover_enrollments_for_group: (" . $conn->errno . ") " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $target_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $enrollments;
}

/**
 * Removes a specific carryover enrollment by its ID.
 *
 * @param mysqli $conn Database connection.
 * @param int $enrollment_id The ID of the enrollment to remove.
 * @return bool True on success, false on failure.
 */
function remove_carryover_enrollment(mysqli $conn, int $enrollment_id): bool {
    $sql = "DELETE FROM carryover_enrollments WHERE enrollment_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for remove_carryover_enrollment: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $enrollment_id);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Execute failed for remove_carryover_enrollment: (" . $stmt->errno . ") " . $stmt->error);
    } else {
        // Check if a row was actually deleted
        $success = ($conn->affected_rows > 0);
    }
    $stmt->close();
    return $success;
}

/**
 * Updates the self-mark allowed status for a specific carryover enrollment.
 *
 * @param mysqli $conn Database connection.
 * @param int $enrollment_id The ID of the enrollment to update.
 * @param bool $self_mark_allowed The new status (true or false).
 * @return bool True on success, false on failure.
 */
function update_carryover_self_mark(mysqli $conn, int $enrollment_id, bool $self_mark_allowed): bool {
    $sql = "UPDATE carryover_enrollments SET self_mark_allowed = ? WHERE enrollment_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for update_carryover_self_mark: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $self_mark_int = $self_mark_allowed ? 1 : 0;
    $stmt->bind_param("ii", $self_mark_int, $enrollment_id);
    $success = $stmt->execute();
     if (!$success) {
        error_log("Execute failed for update_carryover_self_mark: (" . $stmt->errno . ") " . $stmt->error);
    } else {
        // Check if a row was actually updated (affected_rows might be 0 if value didn't change)
        // For simplicity, return true if execute succeeded. A more robust check might be needed.
        $success = true;
    }
    $stmt->close();
    return $success;
}


// --- Utility Functions ---

/**
 * Escapes HTML special characters for output to prevent XSS.
 * Wrapper around htmlspecialchars.
 */
function escape_html(?string $string): string {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// Ensure clean separation before this comment block
/**
 * Generates a random password of specified length.
 * Uses a mix of uppercase, lowercase, digits, and special characters.
 */

function generate_random_password(int $length = 8): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
} // Ensure no extra braces here


// --- Email Functions ---

/**
 * Sends login details to a user via Brevo (formerly SendinBlue).
 * Uses the Brevo API to send an email with the user's login credentials.
 */

function sendStudentLoginDetails($email_address, $plain_password, $first_name, $matric_no_or_app_id) {
    $data = array(
        "sender" => array(
            "name" => BREVO_SENDER_NAME,
            "email" => BREVO_SENDER_EMAIL
        ),
        "to" => array(
            array(
                "email" => $email_address
            )
        ),
        "subject" => "Your Student Attendance Portal Login Details",
        "htmlContent" => "
            <html>
                <head>
                    <title>Your Student Attendance Portal Login Details</title>
                </head>
                <body>
                    <h1>Hello $first_name,</h1>
                    <p>Here are your login details for the Student Attendance Portal:</p>
                    <p><strong>Matric No./Application ID:</strong> $matric_no_or_app_id</p>
                    <p><strong>Password:</strong> $plain_password</p>
                    <p>You can login with your Matric Number or Application ID.</p>
                    <p>Please change your password after logging in for the first time.</p>
                    <p>Best regards :)</p>
                </body>
            </html>
        "
    );

    $curl = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'api-key: ' . BREVO_API_KEY
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("Error sending email via Brevo: " . $err);
        return false;
    } else {
        return true;
    }
}


/**
 * Fetches the groups associated with a specific class representative.
 * OVERLOADS get_course_rep_groups TO RETURN MORE DETAIL
 *
 * @param mysqli $conn The database connection object.
 * @param int $userId The user ID of the class representative.
 * @return array An array of groups (each group as an associative array) or an empty array if none found or error.
 */
/* // Note: get_course_rep_groups exists above, check if this is needed or needs rename
function getCourseRepGroups(mysqli $conn, int $userId): array {
    $groups = [];
    // This query was provided but seems redundant/differently structured than the existing get_course_rep_groups
    // Using existing one for now. Re-evaluate if this specific structure is required.
    return get_course_rep_groups($conn, $userId);
}
*/


/**
 * Fetches the lecturers assigned to a specific course within a specific group.
 * (Provided in prompt, similar to get_assigned_lecturer_for_course_group but might return multiple if possible)
 *
 * @param mysqli $conn The database connection object.
 * @param int $groupId The ID of the department group.
 * @param int $courseId The ID of the course.
 * @return array An array of lecturers (each lecturer as an associative array) or an empty array if none found or error.
*/
// Removing redundant getLecturersForCourse function as per previous cleanup note.
// Existing functions get_assigned_lecturer_for_course_group and get_group_course_lecturer_assignments cover necessary functionality.

// NOTE: getAllLecturers function exists above with a similar purpose for fetching all lecturers.

/**
 * Associates a lecturer with a specific course within a specific group.
 * Using existing function associate_lecturer_to_course_group which handles insert/update.
 *
 * @param mysqli $conn The database connection object.
 * @param int $groupId The group ID.
 * @param int $courseId The course ID.
 * @param int $lecturerId The lecturer's user ID.
 * @return bool True on success, False on failure.
*/
// Note: Functionality is handled by associate_lecturer_to_course_group()

/**
 * Removes the association of a lecturer from a specific course within a specific group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $groupId The group ID.
 * @param int $courseId The course ID.
 * @param int $lecturerId The lecturer's user ID.
 * @return bool True on success (if a row was deleted), False on failure or if not found.
 */
function removeLecturerFromCourseGroup(mysqli $conn, int $groupId, int $courseId, int $lecturerId): bool {
     // Use the correct table name: group_course_lecturer_assignments
    $sql = "DELETE FROM group_course_lecturer_assignments WHERE group_id = ? AND course_id = ? AND lecturer_id = ?";

     if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iii", $groupId, $courseId, $lecturerId);
        if ($stmt->execute()) {
             // Check if a row was actually deleted
             $success = ($conn->affected_rows > 0);
        } else {
             error_log("Error executing removeLecturerFromCourseGroup query: " . $stmt->error);
             $success = false;
        }
        $stmt->close();
    } else {
         error_log("Error preparing removeLecturerFromCourseGroup query: " . $conn->error);
         $success = false;
    }
    return $success;
}

// Removed orphaned comment closing tag '*/'

// --- Dashboard Insight Functions ---

/**
 * Fetches the attendance summary (counts of each status) for the most recent *completed* session.
 *
 * @param mysqli $conn Database connection.
 * @param int $course_id The ID of the course.
 * @param int $group_id The ID of the group.
 * @return array Associative array of status counts (e.g., ['Present' => 10, 'Absent' => 2]) or empty array if no completed session found.
 */
function get_last_session_summary(mysqli $conn, int $course_id, int $group_id): array {
    $summary = [];

    // Find the most recent completed session
    $session_sql = "SELECT session_id FROM attendancesessions
                    WHERE course_id = ? AND group_id = ? AND session_end_time IS NOT NULL
                    ORDER BY session_end_time DESC
                    LIMIT 1";
    $session_stmt = $conn->prepare($session_sql);
    if (!$session_stmt) {
        error_log("Prepare failed (get_last_session_summary - find session): " . $conn->error);
        return [];
    }
    $session_stmt->bind_param("ii", $course_id, $group_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $session_row = $session_result->fetch_assoc();
    $session_stmt->close();

    if (!$session_row) {
        return []; // No completed sessions found
    }
    $last_session_id = $session_row['session_id'];

    // Get status counts for that session
    $summary_sql = "SELECT status, COUNT(*) as count
                    FROM attendancerecords
                    WHERE session_id = ?
                    GROUP BY status";
    $summary_stmt = $conn->prepare($summary_sql);
     if (!$summary_stmt) {
        error_log("Prepare failed (get_last_session_summary - get counts): " . $conn->error);
        return [];
    }
    $summary_stmt->bind_param("s", $last_session_id);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();

    while ($row = $summary_result->fetch_assoc()) {
        $summary[$row['status']] = (int)$row['count'];
    }
    $summary_stmt->close();

    // Calculate Absent count for the last session
    // Get total eligible students for the course/group (approximation for the specific session)
    $eligible_students = get_eligible_students_for_session($conn, $course_id, $group_id);
    $eligible_students_count = count($eligible_students);

    // Sum of students with any status recorded in this session
    $marked_students_count = array_sum($summary);

    // Calculate absent students
    $absent_count = $eligible_students_count - $marked_students_count;
    if ($absent_count < 0) $absent_count = 0; // Ensure non-negative

    // Add 'Absent' to the summary if there were absent students
    if ($absent_count > 0) {
         // Check if 'Absent' key already exists (e.g., if manually marked absent)
         $summary['Absent'] = ($summary['Absent'] ?? 0) + $absent_count;
    }
     // Ensure 'Present' exists even if 0, for chart consistency
     if (!isset($summary['Present'])) {
         $summary['Present'] = 0;
     }
     // Combine 'Present' and 'Present (No Phone)' if needed for simpler chart, or handle in JS
     // Example: $summary['Present'] = ($summary['Present'] ?? 0) + ($summary['Present (No Phone)'] ?? 0);
     //          unset($summary['Present (No Phone)']);


    return $summary;
}


/**
 * Calculates the overall attendance rate for a course/group based on 'Present' status.
 * Rate = (Total 'Present' records / Total Expected Attendance Records) * 100
 * Total Expected = Number of eligible students * Number of completed sessions
 *
 * @param mysqli $conn Database connection.
 * @param int $course_id The ID of the course.
 * @param int $group_id The ID of the group.
 * @return float|null The attendance rate percentage, or null if calculation is not possible.
 */
function get_overall_attendance_rate(mysqli $conn, int $course_id, int $group_id): ?float {
    // 1. Count completed sessions for this course/group
    $session_count_sql = "SELECT COUNT(session_id) as session_count FROM attendancesessions
                          WHERE course_id = ? AND group_id = ? AND session_end_time IS NOT NULL";
    $session_stmt = $conn->prepare($session_count_sql);
     if (!$session_stmt) { error_log("Prepare failed (get_overall_attendance_rate - session count): " . $conn->error); return null; }
    $session_stmt->bind_param("ii", $course_id, $group_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $session_row = $session_result->fetch_assoc();
    $session_stmt->close();
    $completed_session_count = $session_row ? (int)$session_row['session_count'] : 0;

    if ($completed_session_count === 0) {
        return null; // No completed sessions, cannot calculate rate
    }

    // 2. Count total 'Present' records across all completed sessions for this course/group
    // Note: Includes 'Present (No Phone)' if that's considered present
    $present_count_sql = "SELECT COUNT(ar.attendance_id) as present_count
                          FROM attendancerecords ar
                          JOIN attendancesessions s ON ar.session_id = s.session_id
                          WHERE ar.course_id = ? AND ar.group_id = ?
                            AND s.session_end_time IS NOT NULL
                            AND (ar.status = 'Present' OR ar.status = 'Present (No Phone)')";
     $present_stmt = $conn->prepare($present_count_sql);
     if (!$present_stmt) { error_log("Prepare failed (get_overall_attendance_rate - present count): " . $conn->error); return null; }
     $present_stmt->bind_param("ii", $course_id, $group_id);
     $present_stmt->execute();
     $present_result = $present_stmt->get_result();
     $present_row = $present_result->fetch_assoc();
     $present_stmt->close();
     $total_present_count = $present_row ? (int)$present_row['present_count'] : 0;

    // 3. Count the number of unique eligible students for this course/group
    // (This assumes the eligible student list doesn't change drastically session to session)
    // A more accurate (but complex) way would be to sum eligible students *per session*.
    // Using the current eligible list as an approximation.
    $eligible_students = get_eligible_students_for_session($conn, $course_id, $group_id);
    $eligible_student_count = count($eligible_students);

    if ($eligible_student_count === 0) {
        return 0.0; // No eligible students, rate is 0%
    }

    // 4. Calculate total expected attendance records
    $total_expected_records = $eligible_student_count * $completed_session_count;

    if ($total_expected_records === 0) {
        return null; // Avoid division by zero
    }

    // 5. Calculate the rate
    $rate = ($total_present_count / $total_expected_records) * 100.0;

    return round($rate, 1); // Return rate rounded to 1 decimal place
}


/**
 * Fetches students with the lowest attendance rates for a specific course/group.
 *
 * @param mysqli $conn Database connection.
 * @param int $course_id The ID of the course.
 * @param int $group_id The ID of the group.
 * @param int $limit The maximum number of students to return.
 * @return array An array of students, each with ['user_id', 'name', 'rate'].
 */
function get_low_attendance_students(mysqli $conn, int $course_id, int $group_id, int $limit = 5): array {
    // 1. Count completed sessions
    $session_count_sql = "SELECT COUNT(session_id) as session_count FROM attendancesessions
                          WHERE course_id = ? AND group_id = ? AND session_end_time IS NOT NULL";
    $session_stmt = $conn->prepare($session_count_sql);
     if (!$session_stmt) { error_log("Prepare failed (get_low_attendance_students - session count): " . $conn->error); return []; }
    $session_stmt->bind_param("ii", $course_id, $group_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $session_row = $session_result->fetch_assoc();
    $session_stmt->close();
    $completed_session_count = $session_row ? (int)$session_row['session_count'] : 0;

    if ($completed_session_count === 0) {
        return []; // No sessions, no rates
    }

    // 2. Get all eligible students (regular + carryover)
    $eligible_students = get_eligible_students_for_session($conn, $course_id, $group_id);
    if (empty($eligible_students)) {
        return []; // No students
    }
    $eligible_student_ids = array_column($eligible_students, 'user_id');
    $student_names_map = array_column($eligible_students, null, 'user_id'); // Map ID to full student details

    // 3. Get 'Present' counts for each eligible student in completed sessions
    $present_counts_sql = "SELECT ar.student_id, COUNT(ar.attendance_id) as present_count
                           FROM attendancerecords ar
                           JOIN attendancesessions s ON ar.session_id = s.session_id
                           WHERE ar.course_id = ? AND ar.group_id = ?
                             AND s.session_end_time IS NOT NULL
                             AND (ar.status = 'Present' OR ar.status = 'Present (No Phone)')
                             AND ar.student_id IN (" . implode(',', array_fill(0, count($eligible_student_ids), '?')) . ")
                           GROUP BY ar.student_id";

    $present_params = array_merge([$course_id, $group_id], $eligible_student_ids);
    $present_types = "ii" . str_repeat('i', count($eligible_student_ids));

    $present_stmt = $conn->prepare($present_counts_sql);
     if (!$present_stmt) { error_log("Prepare failed (get_low_attendance_students - present counts): " . $conn->error); return []; }
    $present_stmt->bind_param($present_types, ...$present_params);
    $present_stmt->execute();
    $present_result = $present_stmt->get_result();

    $student_present_counts = [];
    while ($row = $present_result->fetch_assoc()) {
        $student_present_counts[$row['student_id']] = (int)$row['present_count'];
    }
    $present_stmt->close();

    // 4. Calculate rate for each student and store
    $student_rates = [];
    foreach ($eligible_student_ids as $student_id) {
        $present_count = $student_present_counts[$student_id] ?? 0;
        // Ensure completed_session_count is not zero before division
        $rate = ($completed_session_count > 0) ? ($present_count / $completed_session_count) * 100.0 : 0.0;
        $student_name = ($student_names_map[$student_id]['first_name'] ?? '') . ' ' . ($student_names_map[$student_id]['last_name'] ?? '');
        $student_rates[] = [
            'user_id' => $student_id,
            'name' => trim($student_name),
            'rate' => round($rate, 1) // Round rate to 1 decimal place
        ];
    }

    // 5. Filter students with rate < 75%
    $filtered_rates = array_filter($student_rates, function ($student) {
        return $student['rate'] < 75.0;
    });

    // 6. Sort the filtered students by rate (ascending)
    usort($filtered_rates, function ($a, $b) {
        return $a['rate'] <=> $b['rate']; // PHP 7+ spaceship operator
    });

    // 7. Return the top $limit students from the filtered and sorted list
    return array_slice($filtered_rates, 0, $limit);
}


/**
 * Fetches students with the lowest overall attendance rates across ALL relevant courses for a specific group.
 * This aggregates attendance across multiple courses a student might be taking within the group.
 *
 * @param mysqli $conn Database connection.
 * @param int $group_id The ID of the group.
 * @param int $limit The maximum number of students to return.
 * @param float $threshold The attendance rate below which students are considered (e.g., 75.0).
 * @return array An array of students, each with ['user_id', 'name', 'overall_rate'].
 */
function get_overall_low_attendance_students_for_group(mysqli $conn, int $group_id, int $limit = 5, float $threshold = 75.0): array {
    // 1. Get all courses assigned to this group
    $group_courses = get_courses_for_group($conn, $group_id);
    if (empty($group_courses)) {
        return []; // No courses for this group
    }
    $group_course_ids = array_column($group_courses, 'course_id');

    // 2. Get all unique eligible students (regular + carryover) for this group across all its courses
    $all_eligible_students = [];
    $student_sql = "
        SELECT DISTINCT s.user_id, s.application_id, s.matric_number, s.first_name, s.last_name, s.email, s.school_email, s.level
        FROM students s
        LEFT JOIN carryover_enrollments ce ON s.user_id = ce.student_id
        WHERE s.group_id = ? OR (ce.target_group_id = ? AND ce.course_id IN (" . implode(',', array_fill(0, count($group_course_ids), '?')) . "))";

    $student_params = array_merge([$group_id, $group_id], $group_course_ids);
    $student_types = "ii" . str_repeat('i', count($group_course_ids));

    $student_stmt = $conn->prepare($student_sql);
    if (!$student_stmt) {
         error_log("Prepare failed (get_overall_low_attendance - unique students): " . $conn->error); return [];
    }
    $student_stmt->bind_param($student_types, ...$student_params);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    while ($student_row = $student_result->fetch_assoc()) {
        $all_eligible_students[$student_row['user_id']] = $student_row; // Use user_id as key for uniqueness
    }
    $student_stmt->close();

    if (empty($all_eligible_students)) {
        return []; // No eligible students found
    }
    $all_eligible_student_ids = array_keys($all_eligible_students);

    // 3. Get all completed sessions for courses within this group
    $sessions_sql = "SELECT session_id, course_id, session_start_time FROM attendancesessions
                     WHERE group_id = ? AND course_id IN (" . implode(',', array_fill(0, count($group_course_ids), '?')) . ")
                       AND session_end_time IS NOT NULL
                     ORDER BY session_start_time";
    $session_params = array_merge([$group_id], $group_course_ids);
    $session_types = "i" . str_repeat('i', count($group_course_ids));

    $session_stmt = $conn->prepare($sessions_sql);
    if (!$session_stmt) { error_log("Prepare failed (get_overall_low_attendance - sessions): " . $conn->error); return []; }
    $session_stmt->bind_param($session_types, ...$session_params);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $all_completed_sessions = $session_result->fetch_all(MYSQLI_ASSOC);
    $session_stmt->close();

    if (empty($all_completed_sessions)) {
        return []; // No completed sessions for any course in this group
    }
    $all_completed_session_ids = array_column($all_completed_sessions, 'session_id');
    // Map session_id to course_id for eligibility checks
    $session_course_map = array_column($all_completed_sessions, 'course_id', 'session_id');

    // 4. Get all 'Present' attendance records for these students across these sessions
    $present_records_sql = "SELECT ar.student_id, ar.session_id, COUNT(ar.attendance_id) as present_count
                            FROM attendancerecords ar
                            WHERE ar.group_id = ?
                              AND ar.student_id IN (" . implode(',', array_fill(0, count($all_eligible_student_ids), '?')) . ")
                              AND ar.session_id IN (" . implode(',', array_fill(0, count($all_completed_session_ids), '?')) . ")
                              AND (ar.status = 'Present' OR ar.status = 'Present (No Phone)')
                            GROUP BY ar.student_id, ar.session_id"; // Count per student per session

    $present_params = array_merge([$group_id], $all_eligible_student_ids, $all_completed_session_ids);
    $present_types = "i" . str_repeat('i', count($all_eligible_student_ids)) . str_repeat('s', count($all_completed_session_ids));

    $present_stmt = $conn->prepare($present_records_sql);
     if (!$present_stmt) { error_log("Prepare failed (get_overall_low_attendance - present records): " . $conn->error); return []; }
    $present_stmt->bind_param($present_types, ...$present_params);
    $present_stmt->execute();
    $present_result = $present_stmt->get_result();

    // Map [student_id] => total_present_count across relevant sessions
    $student_total_present_counts = array_fill_keys($all_eligible_student_ids, 0);
    // Map [student_id] => total_expected_sessions_count
    $student_total_expected_sessions = array_fill_keys($all_eligible_student_ids, 0);


    // Build student eligibility map: [student_id] => [course_id1, course_id2, ...]
    $student_eligibility_map = [];
    foreach ($all_eligible_student_ids as $student_id) {
        $student_eligibility_map[$student_id] = [];
        // Regular students are eligible for all group courses by default
        if ($all_eligible_students[$student_id]['group_id'] == $group_id) {
             $student_eligibility_map[$student_id] = $group_course_ids;
        }
         // Check carryover enrollments for this student in this target group
        $carryover_sql = "SELECT course_id FROM carryover_enrollments
                           WHERE student_id = ? AND target_group_id = ? AND course_id IN (" . implode(',', array_fill(0, count($group_course_ids), '?')) . ")";
        $carryover_params = array_merge([$student_id, $group_id], $group_course_ids);
        $carryover_types = "ii" . str_repeat('i', count($group_course_ids));
        $carryover_stmt = $conn->prepare($carryover_sql);
         if ($carryover_stmt) {
             $carryover_stmt->bind_param($carryover_types, ...$carryover_params);
             $carryover_stmt->execute();
             $carryover_result = $carryover_stmt->get_result();
             while ($co_row = $carryover_result->fetch_assoc()) {
                 $student_eligibility_map[$student_id][] = $co_row['course_id'];
             }
             $carryover_stmt->close();
             // Ensure unique courses if student is both regular and carryover somehow
             $student_eligibility_map[$student_id] = array_unique($student_eligibility_map[$student_id]);
        } else {
              error_log("Prepare failed (get_overall_low_attendance - carryover check): " . $conn->error);
        }
    }


    // Calculate total expected sessions for each student
    foreach ($all_completed_sessions as $session) {
         $session_id = $session['session_id'];
         $course_id_for_session = $session['course_id'];
        foreach ($all_eligible_student_ids as $student_id) {
            // Increment expected count if the student is eligible for the course this session was for
            if (in_array($course_id_for_session, $student_eligibility_map[$student_id])) {
                 $student_total_expected_sessions[$student_id]++;
            }
        }
    }


     // Calculate total present counts for each student from fetched records
    while ($row = $present_result->fetch_assoc()) {
        $student_id = $row['student_id'];
         // Only count if the student was actually eligible for the session's course
         $session_id_for_record = $row['session_id'];
         $course_id_for_record = $session_course_map[$session_id_for_record] ?? null;

         if ($course_id_for_record && isset($student_eligibility_map[$student_id]) && in_array($course_id_for_record, $student_eligibility_map[$student_id])) {
               $student_total_present_counts[$student_id]++;
         }

    }
    $present_stmt->close();


    // 5. Calculate overall rate for each student
    $student_rates = [];
    foreach ($all_eligible_student_ids as $student_id) {
        $total_present = $student_total_present_counts[$student_id] ?? 0;
        $total_expected = $student_total_expected_sessions[$student_id] ?? 0;

        if ($total_expected > 0) {
            $rate = ($total_present / $total_expected) * 100.0;
        } else {
            $rate = 0.0; // Or null/100% if no sessions expected? Defaulting to 0.
        }

        $student_details = $all_eligible_students[$student_id];
        $student_name = ($student_details['first_name'] ?? '') . ' ' . ($student_details['last_name'] ?? '');

        $student_rates[] = [
            'user_id' => $student_id,
            'name' => trim($student_name),
            'overall_rate' => round($rate, 1)
        ];
    }

    // 6. Filter students with rate < threshold
    $filtered_rates = array_filter($student_rates, function ($student) use ($threshold) {
        // Important: comparison is < not <= threshold
        return $student['overall_rate'] < $threshold;
    });


    // 7. Sort the filtered students by overall_rate (ascending)
    usort($filtered_rates, function ($a, $b) {
        return $a['overall_rate'] <=> $b['overall_rate'];
    });

    // 8. Return the top $limit students
    return array_slice($filtered_rates, 0, $limit);
}
