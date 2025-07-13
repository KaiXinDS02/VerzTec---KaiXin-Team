<?php
session_start();
header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// add_country.php (charmaine)
//
// Handles POST request to add a new country to the database.
// Validates input, prevents duplicates, logs action if user is logged in,
// and returns JSON success or error message.
//
// Requires database connection and logging function.
// ---------------------------------------------------------------------------

// Include database connection and logging utilities
include __DIR__ . '/../connect.php';
include 'auto_log_function.php';

// ---------------------------------------------------------------------------
// Main Logic - Handle POST Request
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize the new country name from POST data
    $country = trim($_POST['new_country'] ?? '');

    if (!empty($country)) {
        // Check if the country already exists in the database
        $check = $conn->prepare("SELECT country_id FROM countries WHERE country = ?");
        $check->bind_param("s", $country);
        $check->execute();
        $check->store_result();

        // If country exists, return error response and exit
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Country already exists.']);
            $check->close();
            exit;
        }
        $check->close();

        // Insert the new country into the database
        $stmt = $conn->prepare("INSERT INTO countries (country) VALUES (?)");
        $stmt->bind_param("s", $country);

        if ($stmt->execute()) {
            // Log the action if user session is active
            if (isset($_SESSION['user_id'])) {
                $details = "Added country: $country";
                log_action($conn, $_SESSION['user_id'], "users", "add", $details);
            }

            // Return success response
            echo json_encode(['success' => true, 'message' => 'Country added successfully.']);
        } else {
            // Return database error
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        // Country name is empty
        echo json_encode(['success' => false, 'message' => 'Country name is required.']);
    }
} else {
    // Invalid request method (not POST)
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Close the database connection
$conn->close();
?>
