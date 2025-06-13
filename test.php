<?php
require_once 'connect.php';
session_start();

include 'admin/auto_log_function.php'; // your fixed logger file

auto_log_action(); // direct call

echo "Testing audit log...";
?>
