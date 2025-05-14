<!-- Page where users key in OTP code before being directed to homepage -->
<?php
session_start();

$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userInput = $_POST['otp_input'];

    if (isset($_SESSION['otp']) && $userInput == $_SESSION['otp']) {
        // ✅ OTP matched
        unset($_SESSION['otp']); 
        header("Location: home.html");
        exit();
    } else {
        // ❌ OTP didn't match
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

    <!-- Display error message if any -->
    <?php if (!empty($error)) : ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <!-- OTP Form -->
    <form method="POST" action="">
        <input type="text" name="otp_input" placeholder="Enter OTP" required>
        <button type="submit">Verify</button>
    </form>
</body>
</html>
