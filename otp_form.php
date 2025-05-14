<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Enter OTP</title>
</head>
<body>
    <h2>Enter the OTP you received</h2>
    <form method="POST" action="verify_otp.php">
        <input type="text" name="otp_input" placeholder="Enter OTP" required>
        <button type="submit">Verify</button>
    </form>
</body>
</html>
