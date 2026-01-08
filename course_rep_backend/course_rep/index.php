<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure user is logged in as a class rep
check_course_rep_login(); // Redirects if not logged in

$page_title = "Class Rep Dashboard";

// Get logged-in user details
$user_id = $_SESSION['user_id'] ?? 0;
$first_name = $_SESSION['first_name'] ?? 'Rep';
$last_name = $_SESSION['last_name'] ?? '';

// Get the groups managed by this class rep
$managed_groups = get_course_rep_groups($conn, $user_id);


// Get selected group/course IDs from URL
// Default to the first group if only one exists and none is selected in URL
$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : ($managed_groups[0]['group_id'] ?? 0);
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Fetch assigned courses for the selected group for the dropdown
$assigned_courses = [];
if ($selected_group_id > 0) {
    $assigned_courses = get_assigned_courses_for_group($conn, $selected_group_id);
}

// --- Fetch data for charts/insights based on $selected_course_id ---
$last_session_data = [];
$overall_attendance_rate = null;
$low_attendance_students = [];

if ($selected_course_id > 0 && $selected_group_id > 0) {
    // Fetch real data using the functions
    $last_session_data = get_last_session_summary($conn, $selected_course_id, $selected_group_id);
    $overall_attendance_rate = get_overall_attendance_rate($conn, $selected_course_id, $selected_group_id); // Using 'Present / Total Expected'
    $low_attendance_students = get_low_attendance_students($conn, $selected_course_id, $selected_group_id, 5); // Get top 5
}


include_once __DIR__ . '/includes/templates/header.php';

?>

<div class="nk-block-head nk-block-head-sm">
    <div class="nk-block-between">
        <div class="nk-block-head-content">
            <h3 class="nk-block-title page-title">Welcome, <?php echo escape_html($first_name); ?>!</h3>
            <div class="nk-block-des text-soft">
                <p>Your dashboard overview. Select a group and course to view insights.</p>
            </div>
        </div><!-- .nk-block-head-content -->
         <div class="nk-block-head-content">
            <div class="toggle-wrap nk-block-tools-toggle">
                <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="pageMenu"><em class="icon ni ni-menu-alt-r"></em></a>
                <div class="toggle-expand-content" data-content="pageMenu">
                    <ul class="nk-block-tools g-3">
                         <?php if ($selected_group_id > 0): ?>
                            <li><a href="index.php" class="btn btn-white btn-dim btn-outline-light"><em class="icon ni ni-home"></em><span>View All Groups</span></a></li>
                         <?php endif; ?>
                    </ul>
                </div>
            </div><!-- .toggle-wrap -->
        </div><!-- .nk-block-head-content -->
    </div><!-- .nk-block-between -->
</div><!-- .nk-block-head -->

<div class="nk-block">
    <?php include __DIR__ . '/includes/messages.php'; ?>

    <!-- Group Selection List (Only show if multiple groups or none selected) -->
    <?php if (count($managed_groups) > 1 || $selected_group_id <= 0): ?>
    <div class="card card-bordered card-stretch mb-4">
        <div class="card-header border-bottom">Select Your Group</div>
        <div class="card-inner-group">
            <div class="card-inner p-0">
                <div class="nk-tb-list nk-tb-ulist">
                    <div class="nk-tb-item nk-tb-head">
                        <div class="nk-tb-col"><span class="sub-text">Group Name</span></div>
                        <div class="nk-tb-col tb-col-md"><span class="sub-text">Department</span></div>
                        <div class="nk-tb-col tb-col-lg"><span class="sub-text">Faculty</span></div>
                        <div class="nk-tb-col nk-tb-col-tools text-end"></div>
                    </div><!-- .nk-tb-item -->

                    <?php if (!empty($managed_groups)): ?>
                        <?php foreach ($managed_groups as $group):
                            $is_selected_group = ($group['group_id'] == $selected_group_id);
                        ?>
                            <div class="nk-tb-item<?php echo $is_selected_group ? ' bg-lighter' : ''; ?>">
                                <div class="nk-tb-col">
                                    <a href="index.php?group_id=<?php echo $group['group_id']; ?>">
                                        <span class="tb-lead"><?php echo escape_html($group['group_name']); ?></span>
                                    </a>
                                </div>
                                <div class="nk-tb-col tb-col-md">
                                    <span><?php echo escape_html($group['department_name']); ?></span>
                                </div>
                                <div class="nk-tb-col tb-col-lg">
                                    <span><?php echo escape_html($group['faculty_name']); ?></span>
                                </div>
                                <div class="nk-tb-col nk-tb-col-tools">
                                     <a href="index.php?group_id=<?php echo $group['group_id']; ?>" class="btn btn-sm btn-primary">Select Group</a>
                                </div>
                            </div><!-- .nk-tb-item -->
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="nk-tb-item">
                            <div class="nk-tb-col nk-tb-col-notice">
                                <div class="alert alert-info">
                                    <div class="alert-icon"><em class="icon ni ni-alert-circle"></em></div>
                                    <strong>No Groups Assigned.</strong> You are not currently assigned to manage any department groups. Please contact your Head of Department (HOD).
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- .nk-tb-list -->
            </div><!-- .card-inner -->
        </div><!-- .card-inner-group -->
    </div><!-- .card -->
    <?php endif; ?>


    <!-- Course Selection Dropdown (Only show if a group is selected) -->
    <?php if ($selected_group_id > 0 && !empty($assigned_courses)): ?>
    <div class="card card-bordered mb-4">
        <div class="card-inner">
             <h5 class="card-title">Course Insights</h5>
            <form id="courseSelectionForm" method="GET" action="index.php">
                <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="form-label" for="dashboard_course_id">Select Course:</label>
                            <div class="form-control-wrap">
                                <select id="dashboard_course_id" name="course_id" class="form-select js-select2" data-search="on" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($assigned_courses as $course): ?>
                                        <option value="<?php echo escape_html($course['course_id']); ?>" <?php echo ($course['course_id'] == $selected_course_id) ? 'selected' : ''; ?>>
                                            <?php echo escape_html($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Load Insights</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($selected_group_id > 0): ?>
         <div class="alert alert-warning">No courses assigned to the selected group.</div>
    <?php endif; ?>

    <!-- Attendance Insights Section (Shown only if a course is selected) -->
    <?php if ($selected_course_id > 0): ?>
    <div class="row g-gs">
        <!-- Last Session Summary Chart -->
        <div class="col-md-6 col-xxl-4">
            <div class="card card-bordered h-100">
                <div class="card-inner">
                    <div class="card-title-group align-start mb-2">
                        <div class="card-title">
                            <h6 class="title">Last Session Summary</h6>
                            <p><small class="text-soft">Breakdown for the most recent completed session.</small></p>
                        </div>
                        <!-- Add tools if needed -->
                    </div>
                    <div class="nk-chart-analytics-session" style="min-height: 150px;"> <!-- Added min-height -->
                        <?php if (!empty($last_session_data)): ?>
                            <canvas id="lastSessionChart" height="200"></canvas>
                        <?php else: ?>
                            <p class="text-soft">No completed session data available for this course.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Attendance Rate Chart -->
        <div class="col-md-6 col-xxl-4">
             <div class="card card-bordered h-100">
                 <div class="card-inner">
                     <div class="card-title-group align-start mb-2">
                         <div class="card-title">
                             <h6 class="title">Overall Attendance Rate</h6>
                             <p><small class="text-soft">Based on 'Present' status across all sessions.</small></p>
                         </div>
                     </div>
                     <div class="nk-chart-analytics-overall-rate" style="min-height: 120px;"> <!-- Added min-height -->
                         <?php if ($overall_attendance_rate !== null): ?>
                            <canvas id="overallRateChart" height="120"></canvas> <!-- Canvas for Rate Chart -->
                         <?php else: ?>
                             <p class="text-soft">Not enough data to calculate rate.</p>
                         <?php endif; ?>
                     </div>
                 </div>
             </div>
        </div>

        <!-- Students with Low Attendance Chart -->
         <div class="col-md-12 col-xxl-4"> <!-- Adjusted column size -->
             <div class="card card-bordered h-100">
                 <div class="card-inner">
                     <div class="card-title-group align-start mb-2">
                         <div class="card-title">
                             <h6 class="title">Top 5 Students with Low Attendance</h6>
                         </div>
                     </div>
                      <div class="nk-chart-analytics-low-attendance" style="min-height: 150px;"> <!-- Added min-height -->
                         <?php if (!empty($low_attendance_students)): ?>
                            <canvas id="lowAttendanceChart" height="150"></canvas> <!-- Canvas for Low Attendance Chart -->
                         <?php else: ?>
                             <p class="text-soft">No students flagged for low attendance currently.</p>
                         <?php endif; ?>
                     </div>
                 </div>
             </div>
        </div>

    </div>
    <?php endif; ?>

</div><!-- .nk-block -->

<?php
// Convert PHP data to JSON for JavaScript charts
$last_session_labels_json = !empty($last_session_data) ? json_encode(array_keys($last_session_data)) : '[]';
$last_session_data_json = !empty($last_session_data) ? json_encode(array_values($last_session_data)) : '[]';
$overall_rate_json = json_encode($overall_attendance_rate); // Pass the rate itself
$low_attendance_labels_json = !empty($low_attendance_students) ? json_encode(array_column($low_attendance_students, 'name')) : '[]';
$low_attendance_data_json = !empty($low_attendance_students) ? json_encode(array_column($low_attendance_students, 'rate')) : '[]';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js Initialization

    // 1. Last Session Summary Chart
    const ctxLastSession = document.getElementById('lastSessionChart');
    const lastSessionData = <?php echo $last_session_data_json; ?>;
    const lastSessionLabels = <?php echo $last_session_labels_json; ?>;
    // console.log("Last Session Data:", lastSessionData, lastSessionLabels); // Debug Log

    if (ctxLastSession && lastSessionData.length > 0) {
        const backgroundColors = [
            'rgba(24, 183, 88, 0.8)', // Present (DashLite Success)
            'rgba(231, 76, 60, 0.8)',  // Absent (Red)
            'rgba(251, 184, 45, 0.8)', // Late (DashLite Warning)
            'rgba(52, 152, 219, 0.8)', // Excused (Blue) - Add if needed
            'rgba(130, 143, 163, 0.8)' // Other/No Phone (DashLite Secondary/Grey)
        ];
        const dataColorsLastSession = lastSessionLabels.map(label => {
             if (label.includes('Present')) return backgroundColors[0];
             if (label === 'Absent') return backgroundColors[1];
             if (label === 'Late') return backgroundColors[2];
             if (label === 'Excused') return backgroundColors[3];
             return backgroundColors[4];
        });

        new Chart(ctxLastSession, {
            type: 'doughnut',
            data: {
                labels: lastSessionLabels,
                datasets: [{
                    label: 'Last Session',
                    data: lastSessionData,
                    backgroundColor: dataColorsLastSession,
                    hoverOffset: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed !== null) { label += context.parsed; }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Overall Attendance Rate Chart (Simplified Vertical Bar)
    const ctxOverallRate = document.getElementById('overallRateChart');
    const overallRate = <?php echo $overall_rate_json; ?>;
    // console.log("Overall Rate Data:", overallRate); // Debug Log

    if (ctxOverallRate && overallRate !== null) {
        new Chart(ctxOverallRate, {
            type: 'bar',
            data: {
                labels: ['Overall Attendance'], // Single category
                datasets: [{
                    label: 'Rate (%)',
                    data: [overallRate],
                    backgroundColor: ['rgba(24, 183, 88, 0.8)'], // DashLite Success
                    borderColor: ['rgba(24, 183, 88, 1)'],
                    borderWidth: 1,
                    maxBarThickness: 60 // Make the single bar reasonably thick
                }]
            },
            options: {
                // indexAxis: 'x', // Default is vertical
                responsive: true,
                maintainAspectRatio: false,
                 scales: {
                    y: { // Y-axis represents the percentage
                        max: 100,
                        beginAtZero: true,
                         ticks: {
                            callback: function(value) { return value + "%" }
                        }
                    },
                    x: { // X-axis represents the category
                         grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                         enabled: true,
                         callbacks: {
                            label: function(context) {
                                return `Rate: ${context.raw.toFixed(1)}%`;
                            }
                        }
                    }
                }
            }
        });
    }

     // 3. Low Attendance Students Chart
    const ctxLowAttendance = document.getElementById('lowAttendanceChart');
    const lowAttendanceData = <?php echo $low_attendance_data_json; ?>;
    const lowAttendanceLabels = <?php echo $low_attendance_labels_json; ?>;
    // console.log("Low Attendance Data:", lowAttendanceData, lowAttendanceLabels); // Debug Log

     if (ctxLowAttendance && lowAttendanceData.length > 0) {
        new Chart(ctxLowAttendance, {
            type: 'bar', // Horizontal bar chart
            data: {
                labels: lowAttendanceLabels, // Student names
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: lowAttendanceData, // Rates
                    backgroundColor: 'rgba(231, 76, 60, 0.8)', // Red bars
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 1,
                    barPercentage: 0.5,
                    categoryPercentage: 0.7
                }]
            },
            options: {
                indexAxis: 'y', // Make bars horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                         ticks: { callback: function(value) { return value + "%" }, font: { size: 10 } },
                         grid: { color: '#efefef' }
                    },
                    y: {
                         ticks: { font: { size: 10 } },
                         grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                     tooltip: {
                         callbacks: {
                            label: function(context) {
                                return `Rate: ${context.raw.toFixed(1)}%`;
                            }
                        }
                    }
                }
            }
        });
    }

});
</script>

<?php
include_once __DIR__ . '/includes/templates/footer.php';
?>
