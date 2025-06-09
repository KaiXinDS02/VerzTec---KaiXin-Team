<?php
require __DIR__ . '/../connect.php';

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
                <td class='text-center'>
                    
                    <button class='btn btn-sm btn-link text-black edit-user' title='Edit'
                            data-userid='{$row['user_id']}'
                            data-username='{$row['username']}'
                            data-email='{$row['email']}'
                            data-department='{$row['department']}'
                            data-role='{$row['role']}'
                            data-country='{$row['country']}'>
                        <i class='fa fa-edit'></i>
                    </button>

                    <button class='btn btn-sm btn-link text-black delete-user' title='Delete' data-userid='{$row['user_id']}'>
                        <i class='fa fa-trash'></i>
                    </button>
                </td>
            </tr>";
    }


} else {
    echo "<tr><td colspan='7' class='text-center'>No users found</td></tr>";
}
?>
