<?php
// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login status - Redirect if not logged in
check_course_rep_login(); // This function should handle the redirect

// Fetch Class Rep details for the header/sidebar
$user_id = $_SESSION['user_id'] ?? 0;
$first_name = $_SESSION['first_name'] ?? 'Rep';
$last_name = $_SESSION['last_name'] ?? '';

// Get the groups managed by this class rep
$managed_groups = get_course_rep_groups($conn, $user_id);



// You might need to fetch group details if they are needed in the header/sidebar universally
// $group_id = $_SESSION['group_id'] ?? null; // Assuming group ID is stored in session, or fetch it

// Set a default page title if not overridden by the specific page
if (!isset($page_title)) {
    $page_title = "Class Rep Dashboard";
}


// Get group_id from URL for sidebar links
$current_group_id = isset($managed_groups[0]['group_id']) ? (int)$managed_groups[0]['group_id'] : 0;
// error_log("Current Group ID: " . $current_group_id); // Debugging line to check group ID
?>
<!DOCTYPE html>
<html lang="en" class="js"> <!-- Added class="js" -->
<head>
    <meta charset="utf-8">
    <meta name="author" content="XpanSieve Solutions"> <!-- Consistent author -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="BLE Attendance System - Class Rep Panel">
    <!-- Fav Icon -->
    <link rel="shortcut icon" href="images/favicon.png"> <!-- Relative to base -->
    <!-- Page Title -->
    <title><?php echo escape_html($page_title); ?> | BLE Attendance</title> <!-- Use full PHP tags -->
    <!-- StyleSheets -->
    <link rel="stylesheet" href="assets/css/dashlitee1e3.css?ver=3.2.4">
    <link id="skin-default" rel="stylesheet" href="assets/css/themee1e3.css?ver=3.2.4">
    <!-- FontAwesome is usually included in DashLite bundle, but keep if needed -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> -->


</head>

<body class="nk-body bg-lighter npc-general has-sidebar "> <!-- Match admin body class -->

    <div class="nk-app-root">
        <!-- main @s -->
        <div class="nk-main">
            <!-- sidebar @s -->
            <div class="nk-sidebar nk-sidebar-fixed is-dark" data-content="sidebarMenu">
                <div class="nk-sidebar-element nk-sidebar-head">
                    <div class="nk-menu-trigger">
                        <a href="#" class="nk-nav-toggle nk-quick-nav-icon d-xl-none" data-target="sidebarMenu"><em class="icon ni ni-arrow-left"></em></a>
                        <a href="#" class="nk-nav-compact nk-quick-nav-icon d-none d-xl-inline-flex" data-target="sidebarMenu"><em class="icon ni ni-menu"></em></a>
                    </div>
                    <div class="nk-sidebar-brand">
                        <a href="index.php" class="logo-link nk-sidebar-logo">
                            <!-- Use relative paths from base -->
                            <img class="logo-light logo-img" src="../admin/images/logo.png" srcset="../admin/images/logo2x.png 2x" alt="logo">
                            <img class="logo-dark logo-img" src="../admin/images/logo-dark.png" srcset="../admin/images/logo-dark2x.png 2x" alt="logo-dark">
                            <!-- Removed nio-version span -->
                        </a>
                    </div>
                </div><!-- .nk-sidebar-element -->
                <div class="nk-sidebar-element nk-sidebar-body">
                    <div class="nk-sidebar-content">
                        <div class="nk-sidebar-menu" data-simplebar>
                            <ul class="nk-menu">
                                <li class="nk-menu-heading">
                                    <h6 class="overline-title text-primary-alt">Main Menu</h6>
                                </li><!-- .nk-menu-item -->
                                <li class="nk-menu-item">
                                    <a href="index.php" class="nk-menu-link">
                                        <span class="nk-menu-icon"><em class="icon ni ni-dashboard"></em></span>
                                        <span class="nk-menu-text">Dashboard/Select Group</span> <!-- Combined for clarity -->
                                    </a>
                                </li><!-- .nk-menu-item -->
                                <li class="nk-menu-heading">
                                    <h6 class="overline-title text-primary-alt">Group Actions</h6>
                                </li><!-- .nk-menu-heading -->
                                <!-- Add links relevant to class rep, you might need dynamic links based on selected group -->
                                <!-- Keep Class Rep specific links, but ensure structure matches admin if needed -->
                                <!-- Links updated to include group_id if available -->
                                <li class="nk-menu-item">
                                    <a href="manage_courses.php<?php echo $current_group_id > 0 ? '?group_id=' . $current_group_id : ''; ?>" class="nk-menu-link <?php echo $current_group_id <= 0 ? 'disabled' : ''; ?>">
                                        <span class="nk-menu-icon"><em class="icon ni ni-book-read"></em></span>
                                        <span class="nk-menu-text">Manage Courses/Lecturers</span>
                                    </a>
                                </li>
                                <li class="nk-menu-item">
                                     <a href="take_attendance.php<?php echo $current_group_id > 0 ? '?group_id=' . $current_group_id : ''; ?>" class="nk-menu-link <?php echo $current_group_id <= 0 ? 'disabled' : ''; ?>">
                                        <span class="nk-menu-icon"><em class="icon ni ni-list-check"></em></span>
                                        <span class="nk-menu-text">Take Attendance</span>
                                    </a>
                                </li>
                                <li class="nk-menu-item">
                                     <a href="attendance_summary.php<?php echo $current_group_id > 0 ? '?group_id=' . $current_group_id : ''; ?>" class="nk-menu-link <?php echo $current_group_id <= 0 ? 'disabled' : ''; ?>">
                                        <span class="nk-menu-icon"><em class="icon ni ni-pie-alt"></em></span>
                                        <span class="nk-menu-text">Attendance Summary</span>
                                    </a>
                                </li>
                                <li class="nk-menu-item">
                                     <a href="manage_students.php<?php echo $current_group_id > 0 ? '?group_id=' . $current_group_id : ''; ?>" class="nk-menu-link <?php echo $current_group_id <= 0 ? 'disabled' : ''; ?>">
                                        <span class="nk-menu-icon"><em class="icon ni ni-users"></em></span>
                                        <span class="nk-menu-text">Manage Students</span>
                                    </a>
                                </li>
                                <li class="nk-menu-item">
                                    <a href="manage_carryover.php<?php echo $current_group_id > 0 ? '?group_id=' . $current_group_id : ''; ?>" class="nk-menu-link <?php echo $current_group_id <= 0 ? 'disabled' : ''; ?>">
                                        <span class="nk-menu-icon"><em class="icon ni ni-redo"></em></span>
                                        <span class="nk-menu-text">Manage Carryover</span>
                                    </a>
                                </li>
                            </ul><!-- .nk-menu -->
                         </div><!-- .nk-sidebar-menu -->
                    </div><!-- .nk-sidebar-content -->
                </div><!-- .nk-sidebar-element -->
            </div>
            <!-- sidebar @e -->
            <!-- wrap @s -->
            <div class="nk-wrap">
                <!-- main header @s -->
                <div class="nk-header nk-header-fixed is-light">
                    <div class="container-fluid">
                        <div class="nk-header-wrap">
                            <div class="nk-menu-trigger d-xl-none ms-n1">
                                <a href="#" class="nk-nav-toggle nk-quick-nav-icon" data-target="sidebarMenu"><em class="icon ni ni-menu"></em></a>
                            </div>
                            <div class="nk-header-brand d-xl-none">
                                <a href="index.php" class="logo-link">
                                    <img class="logo-light logo-img" src="../admin/images/logo.png" srcset="../admin/images/logo2x.png 2x" alt="logo">
                                    <img class="logo-dark logo-img" src="../admin/images/logo-dark.png" srcset="../admin/images/logo-dark2x.png 2x" alt="logo-dark">
                                </a>
                            </div><!-- .nk-header-brand -->
                            <!-- Add Clock like admin -->
                            <div class="nk-header-news d-none d-xl-block">
                                <div class="nk-news-list">
                                    <div class="nk-news-item">
                                        <div class="nk-news-icon"><em class="icon ni ni-clock"></em></div>
                                        <div class="nk-news-text">
                                            <p><span id="clock"></span></p>
                                            <script>
                                                setInterval(() => {
                                                    const now = new Date();
                                                    document.getElementById('clock').innerText = now.toLocaleString();
                                                }, 1000);
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="nk-header-tools">
                                <ul class="nk-quick-nav">
                                    <li class="dropdown user-dropdown">
                                        <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                                            <div class="user-toggle">
                                                <div class="user-avatar sm"><em class="icon ni ni-user-alt"></em></div>
                                                <div class="user-info d-none d-md-block">
                                                    <div class="user-status">Class Rep</div> <!-- Added status -->
                                                    <div class="user-name dropdown-indicator"><?php echo escape_html($first_name . ' ' . $last_name); ?></div>
                                                </div>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-md dropdown-menu-end dropdown-menu-s1">
                                            <div class="dropdown-inner user-card-wrap bg-lighter d-none d-md-block">
                                                <div class="user-card">
                                                    <div class="user-avatar">
                                                        <span><?php echo escape_html(strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1))); ?></span>
                                                    </div>
                                                    <div class="user-info">
                                                        <span class="lead-text"><?php echo escape_html($first_name . ' ' . $last_name); ?></span>
                                                        <span class="sub-text"><?php echo $_SESSION['email'] ?? ''; ?></span> <!-- Display email -->
                                                    </div>
                                                </div>
                                            </div>
                                             <!-- Add profile link if needed -->
                                            <!-- <div class="dropdown-inner"> -->
                                                <!-- <ul class="link-list"> -->
                                                    <!-- <li><a href="profile.php"><em class="icon ni ni-user-alt"></em><span>View Profile</span></a></li> -->
                                                <!-- </ul> -->
                                            <!-- </div> -->
                                            <div class="dropdown-inner">
                                                <ul class="link-list">
                                                    <li><a href="_auth/logout.php"><em class="icon ni ni-signout"></em><span>Sign out</span></a></li>
                                                 </ul>
                                            </div>
                                        </div>
                                    </li><!-- .dropdown -->
                                </ul><!-- .nk-quick-nav -->
                            </div><!-- .nk-header-tools -->
                        </div><!-- .nk-header-wrap -->
                    </div><!-- .container-fluid -->
                </div>
                <!-- main header @e -->
                <!-- content @s -->
                <div class="nk-content">
                    <div class="container-fluid">
                        <div class="nk-content-inner">
                            <div class="nk-content-body">
                                <!-- Content from specific pages will go here -->
