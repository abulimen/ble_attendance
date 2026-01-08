<?php
// Redirect if already logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'course_rep') {
    header("Location: ../index.php"); // Redirect to class rep dashboard
    exit;
}

$page_title = "Class Rep Login";
$error_message = $_GET['error'] ?? ''; // Get error message from URL query parameter

// No database logic here, handled by login_process.php
?>
<!DOCTYPE html>
<html lang="zxx" class="js">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../images/favicon.png"> <!-- Adjusted path -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/dashlitee1e3.css?ver=3.2.4"> <!-- Adjusted path -->
    <link id="skin-default" rel="stylesheet" href="../assets/css/themee1e3.css?ver=3.2.4"> <!-- Adjusted path -->
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <div class="nk-main ">
            <div class="nk-wrap nk-wrap-nosidebar">
                <div class="nk-content ">
                    <div class="nk-split nk-split-page nk-split-md">
                        <div class="nk-split-content nk-block-area nk-block-area-column nk-auth-container bg-white">
                            <div class="nk-block nk-block-middle nk-auth-body">
                                <div class="brand-logo pb-5"><a href="../../index.html" class="logo-link"><img class="logo-light logo-img logo-img-lg" src="../images/logo.png" alt="logo"><img class="logo-dark logo-img logo-img-lg" src="../images/logo-dark.png" alt="logo-dark"></a></div>
                                <div class="nk-block-head">
                                    <div class="nk-block-head-content">
                                        <h5 class="nk-block-title">Class Rep Sign-In</h5>
                                        <div class="nk-block-des">
                                            <p>Access the Class Rep panel using your Matric Number/Application ID and password.</p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($error_message)) : ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                                <?php endif; ?>
                                <form action="login_process.php" method="post">
                                    <div class="form-group">
                                        <div class="form-label-group"><label class="form-label" for="username">Matric No. / Application ID</label></div>
                                        <div class="form-control-wrap"><input type="text" class="form-control form-control-lg" id="username" name="username" placeholder="Enter your Matric No. or Application ID" required></div>
                                    </div>
                                    <div class="form-group">
                                        <div class="form-label-group"><label class="form-label" for="password">Password</label></div>
                                        <div class="form-control-wrap"><a tabindex="-1" href="#" class="form-icon form-icon-right passcode-switch lg" data-target="password"><em class="passcode-icon icon-show icon ni ni-eye"></em><em class="passcode-icon icon-hide icon ni ni-eye-off"></em></a><input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Enter your password" required></div>
                                    </div>
                                    <div class="form-group"><button type="submit" class="btn btn-lg btn-primary btn-block">Sign in</button></div>
                                </form>
                                <!-- Optional: Add links for registration or other actions if needed -->
                            </div>
                            <!-- Footer can be added here if needed -->
                        </div>
                        <div class="nk-split-content nk-split-stretch bg-abstract"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/bundlee1e3.js?ver=3.2.4"></script> <!-- Adjusted path -->
    <script src="../assets/js/scriptse1e3.js?ver=3.2.4"></script> <!-- Adjusted path -->
    <!-- <script src="../assets/js/demo-settingse1e3.js?ver=3.2.4"></script> --> <!-- Demo settings likely not needed -->
</body>

</html>
