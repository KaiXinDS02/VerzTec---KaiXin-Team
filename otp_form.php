<?php
session_start();

$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userInput = $_POST['otp_input'];

    if (isset($_SESSION['otp']) && $userInput == $_SESSION['otp']) {
        // OTP matched, now redirect based on role
        unset($_SESSION['otp']);

        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'USER') {
                header("Location: home_user.html");
                exit();
            } else if ($_SESSION['role'] === 'MANAGER' || $_SESSION['role'] === 'ADMIN') {
                header("Location: home_admin.html");
                exit();
            } else {
                // Unknown role, fallback
                $error = "Unknown user role. Access denied.";
            }
        } else {
            $error = "User role not set. Please login again.";
        }
    } else {
        // OTP didn't match
        $error = "❌ Invalid OTP. Please try again.";
    }
}
?>

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
