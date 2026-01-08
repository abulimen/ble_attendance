<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once '../../admin/includes/config.php';
require_once '../../admin/includes/functions.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION["course_rep_logged_in"]) && $_SESSION["course_rep_logged_in"] === true) {
    header("Location: ../index.php");
    exit();
}

$error_message = "";

// Check if email and timestamp are set in the session
if (!isset($_SESSION["email"]) || !isset($_SESSION["otp_timestamp"])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION["email"];

// Check for maximum OTP attempts
if (isset($_SESSION['otp_attempts'][$email]) && $_SESSION['otp_attempts'][$email] >= MAX_OTP_ATTEMPTS) {
    $error_message = "Too many incorrect OTP attempts. Please try again later.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error_message)) {
    $user_otp = $_POST["otp"];
    $timestamp = $_SESSION["otp_timestamp"];
    $stored_otp = $_SESSION["otp"];

    // Verify OTP
    if (verifyOTP($user_otp, $email, $timestamp, $stored_otp)) {
        // OTP verification successful

        // Clear session data
        // unset($_SESSION["email"]);
        unset($_SESSION["otp_timestamp"]);
        unset($_SESSION["otp"]);

        // Clear OTP attempts
        clearOTPAttempts($email);

        // Set a session variable to indicate successful login
        $_SESSION["course_rep_logged_in"] = true;

        // Redirect to the hod dashboard or a protected page
        header("Location: ../index.php");
        exit();
    } else {
        // Invalid OTP

        // Increment OTP attempts
        if (!isset($_SESSION['otp_attempts'][$email])) {
            $_SESSION['otp_attempts'][$email] = 1;
        } else {
            $_SESSION['otp_attempts'][$email]++;
        }

        // Check if the maximum attempt limit has been reached
        if ($_SESSION['otp_attempts'][$email] >= MAX_OTP_ATTEMPTS) {
            $error_message = "Too many incorrect OTP attempts. Please try again later.";
            // Log this event (consider using a more robust logging mechanism)
            error_log("Max OTP attempts reached for email: " . $email);
        } else {
            $error_message = "Invalid OTP. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="../assets/css/dashlitee1e3.css?ver=3.2.4">
    <link id="skin-default" rel="stylesheet" href="../assets/css/themee1e3.css?ver=3.2.4">
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <div class="nk-main">
            <div class="nk-wrap nk-wrap-nosidebar">
                <div class="nk-content">
                    <div class="nk-block nk-block-middle nk-auth-body">
                        <div class="brand-logo pb-4 text-center">
                            <a href="#" class="logo-link">
                                <img class="logo-light logo-img logo-img-lg" src="../images/logo.png" srcset="../images/logo2x.png 2x" alt="logo">
                                <img class="logo-dark logo-img logo-img-lg" src="../images/logo-dark.png" srcset="../images/logo-dark2x.png 2x" alt="logo-dark">
                            </a>
                        </div>
                        <div class="nk-block-head">
                            <div class="nk-block-head-content">
                                <h5 class="nk-block-title">Verify OTP</h5>
                                <div class="nk-block-des">
                                    <p>Please enter the OTP sent to your email.</p>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($error_message)) : ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <div class="form-label-group">
                                    <label class="form-label" for="otp">OTP</label>
                                </div>
                                <input type="text" class="form-control form-control-lg" id="otp" name="otp" placeholder="Enter your OTP" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-lg btn-primary btn-block">Verify</button>
                            </div>
                        </form>
                        <div class="form-note-s2 text-center pt-4">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/bundlee1e3.js?ver=3.2.4"></script>
    <script src="../assets/js/scriptse1e3.js?ver=3.2.4"></script>
    <script src="../assets/js/demo-settingse1e3.js?ver=3.2.4"></script>
</body>

</html>