<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userInput = $_POST['otp_input'];

    if (isset($_SESSION['otp']) && $userInput == $_SESSION['otp']) {
        // ✅ OTP matched, redirect to home.html
        unset($_SESSION['otp']); 
        header("Location: home.html");
        exit();
    } else {
        // ❌ OTP didn't match, stay on OTP form with an error
        $_SESSION['otp_error'] = "❌ Invalid OTP. Please try again.";
        header("Location: otp_form.php");
        exit();
    }
} else {
    echo "No OTP submitted.";
}
