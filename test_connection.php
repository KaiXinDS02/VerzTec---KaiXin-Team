<?php
$host = 'db';
$user = 'user';
$pass = 'password';
$db = 'Verztec';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo 'Connection successful';
?>
