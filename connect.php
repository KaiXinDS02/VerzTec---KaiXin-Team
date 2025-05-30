<!-- Database Connection to phpMyAdmin -->
<?php

$host = "localhost";
$user = "root";
$pass = "";
$db = "login";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// Optional: enable error reporting for development
// Remove or comment this in production
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
<!-- Database Connection to phpMyAdmin -->






