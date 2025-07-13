<?php
// ---------------------------------------------------------------------------
// resend_otp.php (Charmaine)
//
// Generates and emails a new OTP to logged-in users requesting a resend.
// Validates session, updates OTP in session, sends email via PHPMailer, then redirects to OTP input form.
//
// Inputs: email, username (session)
// Side Effects: stores new OTP, sends email
// Redirects: otp_form.php on success, login.php if session missing
// Errors: Displays PHPMailer error message if email sending fails.
//
// ---------------------------------------------------------------------------

session_start();
require 'vendor/autoload.php'; // Load Composer dependencies (PHPMailer)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check for essential session variables; redirect to login if missing
if (!isset($_SESSION['email']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Generate a new 6-digit OTP
$otp = rand(100000, 999999);

// Store OTP and generation time in session
$_SESSION['otp'] = $otp;
$_SESSION['otp_time'] = time(); // Used for timeout validation

// Configure and send email using PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();                            // Use SMTP
    $mail->Host = 'smtp.gmail.com';             // Gmail SMTP server
    $mail->SMTPAuth = true;                     // Enable authentication
    $mail->Username = 'spamacc2306@gmail.com';  // Gmail address
    $mail->Password = 'lfvc kyov oife mwze';    // Gmail app password (secure this!)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL encryption
    $mail->Port = 465;                          // Port for SSL

    // Set email headers and content
    $mail->setFrom('spamacc2306@gmail.com', 'Verztec');
    $mail->addAddress($_SESSION['email'], $_SESSION['username']);
    $mail->isHTML(true);
    $mail->Subject = 'Resent OTP Code';
    $mail->Body = "Your new OTP is: <b>$otp</b>. Please do not share this code.";

    // Send the email
    $mail->send();

    // Redirect to OTP form page
    header("Location: otp_form.php");
    exit();

} catch (Exception $e) {
    // Show error if email could not be sent
    echo "Could not resend OTP. Mailer Error: {$mail->ErrorInfo}";
}
