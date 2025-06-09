<?php
session_start();
// Database Connection for Authentication
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userInput = $_POST['otp_input'];

    if (isset($_SESSION['otp']) && $userInput == $_SESSION['otp']) {
        // OTP matched, now redirect based on role
        unset($_SESSION['otp']);

        if (isset($_SESSION['role'])) {
            header("Location: home.php");
            exit();
        } else {
            $error = "User role not set. Please login again.";
        }
    } else {
        // OTP didn't match
        $error = "❌ Invalid OTP. Please try again.";
    }
}
?>
<!-- Database Connection for Authentication -->





<!-- Front-end -->
<!DOCTYPE html>
<html>
<head>
    <title>Enter OTP</title>
</head>
<body>
    <h2>Enter the OTP you received</h2>

    <?php if (!empty($error)) : ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="otp_input" placeholder="Enter OTP" required>
        <button type="submit">Verify</button>
    </form>
</body>
</html>
<!-- Front-end -->