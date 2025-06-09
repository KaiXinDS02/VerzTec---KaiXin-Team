<?php
$conn = new mysqli("db", "user", "password", "Verztec");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
echo "Connected successfully";
?>
