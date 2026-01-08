<?php
// Database Configuration - Replace with your actual credentials

if (file_exists('../../admin/includes/config.php')) {
    require_once '../../admin/includes/config.php'; // Include config for constants
} elseif (file_exists('../admin/includes/config.php')) {
    require_once '../admin/includes/config.php'; // Include config for constants
} else {
    die("Configuration file not found");
}

// It's highly recommended to store these in environment variables or a secure config file outside the web root
// define('DB_SERVER', 'localhost'); // Or your database server IP/hostname
// define('DB_USERNAME', 'root');    // Your database username
// define('DB_PASSWORD', '');        // Your database password
// define('DB_NAME', 'x_attendance'); // Replace with your actual database name

// Attempt to connect to MySQL database using mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Log the error securely instead of displaying it directly in production
    // error_log("Database Connection Error: " . $conn->connect_error);
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Set character set to utf8mb4 (optional, but recommended)
$conn->set_charset("utf8mb4");

require_once "functions.php";
require_once "reqs.php";
