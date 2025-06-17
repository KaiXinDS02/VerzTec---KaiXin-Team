<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);

    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $email = $user['email'];

            // Set session variables
            $_SESSION['otp'] = rand(100000, 999999);
            $_SESSION['otp_time'] = time();
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;

            // Send OTP via email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'spamacc2306@gmail.com';
                $mail->Password   = 'lfvc kyov oife mwze';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('spamacc2306@gmail.com', 'Verztec');
                $mail->addAddress($email, $username);
                $mail->isHTML(true);
                $mail->Subject = 'Your OTP Code';
                $mail->Body    = "Your OTP code is: <b>{$_SESSION['otp']}</b>. Please do not share this with anyone.";

                $mail->send();

                header("Location: verification.php");
                exit();
            } catch (Exception $e) {
                $error = "Could not send OTP. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
    } else {
        $error = "Please enter your username.";
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec - Enter Username</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
  <main class="login-wrap bg-included">
    <div class="login-form">
      <form action="forgot_password/enter_username.php" method="POST" novalidate>
        <div class="login-logo px-4">
          <a href="#"><img src="images/logo.png" alt="Verztec"></a>
        </div>

        <!-- Instructions -->
        <div class="single-input pb-3 pb-md-4 text-center">
          <p style="margin-bottom:.25rem;">
            To reset your password, please confirm your account by entering your username. A One Time Password (OTP) will be sent to your registered email address.
          </p>
        </div>

        <!-- Error display -->
        <?php if (!empty($error)): ?>
          <p style="color:red; text-align:center; margin-bottom:1rem;">
            <?= htmlspecialchars($error) ?>
          </p>
        <?php endif; ?>

        <!-- Username input -->
        <div class="single-input pb-3 pb-md-4">
          <label for="username">Enter your username to receive OTP</label>
          <input id="username" name="username" type="text" required />
        </div>

        <!-- Submit -->
        <div class="submit-btn">
          <button type="submit">Send OTP</button>
        </div>
      </form>
    </div>
  </main>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
</body>
</html>
