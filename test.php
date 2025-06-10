<?php
$mysqli = new mysqli('db', 'user', 'password', 'Verztec');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}
echo '✅ Connected successfully!';
?>
