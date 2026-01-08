<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once '../../admin/includes/config.php';
require_once '../../admin/includes/functions.php';

// Redirect to login if user is not logged in except he forgot password
if (!isset($_GET["forgot_password"]) && (!isset($_SESSION["hod_logged_in"]) || $_SESSION["hod_logged_in"] !== true)) {
    header("Location: login.php");
    exit();
}

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First step - Request password reset
    if (isset($_POST["email"])) {
        $email = $_POST["email"];

        // Database connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Check if email exists in Lecturer's table
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'course_rep' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            // Generate OTP for password reset
            $otp = generateNumericOTP(OTP_LENGTH);

            // Store reset information in session
            $_SESSION["reset_email"] = $email;
            $_SESSION["reset_otp"] = $otp;
            $_SESSION["reset_timestamp"] = time();

            // Send OTP via Brevo
            if (sendOTPViaBrevo($email, $otp)) {
                header("Location: ?step=verify");
                exit();
            } else {
                $error_message = "Error sending reset code. Please try again.";
            }
        } else {
            $error_message = "Email not found in our records.";
        }
        $stmt->close();
        $conn->close();
    }

    // Second step - Verify OTP
    if (isset($_POST["otp"])) {
        $user_otp = $_POST["otp"];

        if (verifyOTP($user_otp, $_SESSION["reset_email"], $_SESSION["reset_timestamp"], $_SESSION["reset_otp"])) {
            header("Location: ?step=reset");
            exit();
        } else {
            $error_message = "Invalid or expired reset code.";
        }
    }

    // Final step - Reset Password
    if (isset($_POST["new_password"]) && isset($_POST["confirm_password"])) {
        if ($_POST["new_password"] === $_POST["confirm_password"]) {
            $new_password = $_POST["new_password"];
            $email = $_SESSION["reset_email"];

            // Database connection
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'course_rep' ");
            $stmt->bind_param("ss", $hashed_password, $email);

            if ($stmt->execute()) {
                // Clear reset session data
                $_SESSION = array();

                // Destroy the session
                session_destroy();
                $success_message = "Password reset successful. You can now login with your new password.";
            } else {
                $error_message = "Error resetting password. Please try again.";
            }
            $stmt->close();
            $conn->close();
        } else {
            $error_message = "Passwords do not match.";
        }
    }
}

$step = $_GET["step"] ?? "request";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
                                <h5 class="nk-block-title">Reset Password</h5>
                                <div class="nk-block-des">
                                    <?php if ($step === "request"): ?>
                                        <p>Enter your email to receive a reset code.</p>
                                    <?php elseif ($step === "verify"): ?>
                                        <p>Enter the reset code sent to your email.</p>
                                    <?php else: ?>
                                        <p>Enter your new password.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                                <p class="mt-3"><a href="login.php">Return to Login</a></p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?step=" . $step; ?>">
                                <?php if ($step === "request"): ?>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="email">Email</label>
                                        </div>
                                        <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your email address" required>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-lg btn-primary btn-block">Send Reset Code</button>
                                    </div>
                                <?php elseif ($step === "verify"): ?>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="otp">Reset Code</label>
                                        </div>
                                        <input type="text" class="form-control form-control-lg" id="otp" name="otp" placeholder="Enter reset code" required>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-lg btn-primary btn-block">Verify Code</button>
                                    </div>
                                <?php elseif ($step === "reset"): ?>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="new_password">New Password</label>
                                        </div>
                                        <div class="form-control-wrap">
                                            <input type="password" class="form-control form-control-lg" id="new_password" name="new_password" placeholder="Enter new password" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="confirm_password">Confirm Password</label>
                                        </div>
                                        <div class="form-control-wrap">
                                            <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-lg btn-primary btn-block">Reset Password</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
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