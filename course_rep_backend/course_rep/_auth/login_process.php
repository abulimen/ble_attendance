<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php'; // Database connection
require_once __DIR__ . '/../includes/functions.php'; // Helper functions

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'course_rep') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_matric = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Basic validation
    if (empty($username_or_matric) || empty($password)) {
        header("Location: login.php?error=" . urlencode("Username/Matric Number and Password are required."));
        exit;
    }

    // Prepare SQL statement to prevent SQL injection
    // Assuming the HOD creates the class rep user in the 'users' table with role 'course_rep'
    // users might log in with username or matric_number
    $sql = "SELECT user_id, username, password, role, first_name, last_name
            FROM users
            WHERE (username = ? OR matric_number = ?) AND role = 'course_rep'";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $username_or_matric, $username_or_matric);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            // Assuming password hashing was used when creating the user (e.g., password_hash())
            if ($password == $user['password']) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username']; // Or use first/last name
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Redirect to the class rep dashboard
                // Check if there was an intended URL saved (optional)
                // $redirect_url = $_SESSION['intended_url'] ?? '../index.php';
                // unset($_SESSION['intended_url']); // Clear the intended URL
                header("Location: ../index.php");
                exit;

            } else {
                // Invalid password
                header("Location: login.php?error=" . urlencode("Invalid credentials."));
                exit;
            }
        } else {
            // User not found or not a class rep
            header("Location: login.php?error=" . urlencode("Invalid credentials or user is not a class representative."));
            exit;
        }
        $stmt->close();
    } else {
        // SQL error
        error_log("Error preparing login statement: " . $conn->error);
        header("Location: login.php?error=" . urlencode("Login error. Please try again later."));
        exit;
    }

    $conn->close();

} else {
    // If not a POST request, redirect to login form
    header("Location: login.php");
    exit;
}
?>