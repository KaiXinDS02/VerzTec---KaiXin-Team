<?php
require __DIR__ . '/../connect.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);
    $email = trim($_POST["email"]);
    $department = trim($_POST["department"]);
    $role = trim($_POST["role"]);
    $country = trim($_POST["country"]);

    if ($username && $email && $department && $role && $country) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, department, role, country) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password, $email, $department, $role, $country);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "Database error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "All fields are required.";
    }
}
?>
