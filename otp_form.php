<?php
session_start();
include 'admin/auto_log_function.php';
if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
$otpError = "";
// Set OTP expiry to 2 minutes (45 seconds)
$otpValiditySeconds = 45;
if (!isset($_SESSION['otp_time'])) {
    $_SESSION['otp_time'] = time();
}
$timeRemaining = ($_SESSION['otp_time'] + $otpValiditySeconds) - time();
$timeRemaining = max($timeRemaining, 0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['otp'])) {
        $enteredOtp = $_POST['otp'];
        if ($enteredOtp == $_SESSION['otp']) {
            // ✅ OTP is correct
            unset($_SESSION['otp'], $_SESSION['otp_time']);
            header("Location: home.php");
            exit();
        } else {
            $otpError = "Invalid OTP. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="style.css">
    <script>
        let countdown = <?php echo $timeRemaining; ?>;
        function updateCountdown() {
            const countdownDisplay = document.getElementById('countdown');
            if (countdown > 0) {
                const minutes = Math.floor(countdown / 60);
                const seconds = countdown % 60;
                const timeString = seconds < 10 ? `0${minutes}:0${seconds}` : `0${minutes}:${seconds}`;
                countdownDisplay.innerText = `Resend OTP in ${timeString}`;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                countdownDisplay.innerHTML = '<a href="otp_resend.php" style="color: #007bff; text-decoration: none;">Resend OTP</a>';
            }
        }
        window.onload = updateCountdown;
    </script>
</head>
<body>
    <main class="login-wrap bg-included">
        <div class="login-form">
            <form method="POST" action="otp_form.php">
                <div class="login-logo px-4 text-center" style="margin-bottom: 30px;">
                    <img src="images/logo.png" alt="VERZTEC Logo" style="max-width: 200px;">
                </div>
                
                <div class="text-center" style="margin-bottom: 30px;">
                    <p style="color: #666; font-size: 14px; margin-bottom: 5px;">A One Time Password (OTP) has been sent to the</p>
                    <p style="color: #666; font-size: 14px; margin-bottom: 0;">email <?php echo isset($_SESSION['email']) ? $_SESSION['email'] : 'chuacharmaine648@gmail.com'; ?></p>
                </div>

                <?php if (!empty($otpError)): ?>
                    <p style="color:red; text-align:center; margin-bottom: 20px;"><?php echo $otpError; ?></p>
                <?php endif; ?>

                <div style="margin-bottom: 15px;">
                    <label for="otp" style="display: block; color: #333; font-size: 14px; margin-bottom: 8px;">OTP Code</label>
                    <input id="otp" type="text" name="otp" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; box-sizing: border-box;">
                </div>

                <div style="text-align: right; margin-bottom: 25px;">
                    <p id="countdown" style="color: #999; font-size: 12px; margin: 0;"></p>
                </div>

                <div class="submit-btn">
                    <button type="submit" style="width: 100%; padding: 15px; background-color: #000; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer;">Verify OTP</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>