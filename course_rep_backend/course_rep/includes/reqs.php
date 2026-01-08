<?php
// Get logged-in user details
$user_id = $_SESSION['user_id'] ?? 0;
$first_name = $_SESSION['first_name'] ?? 'Rep';
$last_name = $_SESSION['last_name'] ?? '';

// Get the groups managed by this class rep
$managed_groups = get_course_rep_groups($conn, $user_id);
?>