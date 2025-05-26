<?php
require 'connect.php';

$result = $conn->query("SELECT user_id, username, email, department, role, country FROM users ORDER BY user_id DESC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['user_id']}</td>
                <td>{$row['username']}</td>
                <td>{$row['email']}</td>
                <td>{$row['department']}</td>
                <td>{$row['role']}</td>
                <td>{$row['country']}</td>
            </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>No users found</td></tr>";
}
?>
