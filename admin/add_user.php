<?php
// ---------------------------------------------------------------------------
// add_user.php (charmaine)
//
// Handles POST to add a new user to the database.
// Validates input, hashes password securely, inserts record,
// logs the action if user is logged in, and returns success/error.
//
// Requires DB connection and audit logging.
// ---------------------------------------------------------------------------


// Include database connection and logging utilities
require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php'; 

// ---------------------------------------------------------------------------
// Main Logic - Handle POST Request
// ---------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize user input from POST data
    $username = trim($_POST["username"]);
    $password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT); // Hash the password securely
    $email = trim($_POST["email"]);
    $department = trim($_POST["department"]);
    $role = trim($_POST["role"]);
    $country = trim($_POST["country"]);

    // Check that all required fields are provided
    if ($username && $email && $department && $role && $country) {
        // Prepare and execute the SQL statement to insert the new user
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, department, role, country) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password, $email, $department, $role, $country);

        if ($stmt->execute()) {
            // Log the action if user session is active
            if (isset($_SESSION['user_id'])) {
                $details = "Added user: $username (email: $email, role: $role, dept: $department, country: $country)";
                log_action($conn, $_SESSION['user_id'], "users","add", $details);
            }
            // Return success response
            echo "success";
        } else {
            // Return database error
            echo "Database error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // One or more required fields are missing
        echo "All fields are required.";
    }
}
?>
