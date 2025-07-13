<?php
session_start();
// ---------------------------------------------------------------------------
// edit_visibility.php (charmaine)
//
// Purpose:
//   Allows ADMIN and MANAGER users to update file visibility scope:
//   - 'ALL' (public), restricted by DEPARTMENT(s), or COUNTRY(ies).
//
// Features:
//   - Validates user role and session.
//   - Clears old visibility and applies new settings.
//   - MANAGER can restrict by department or country; ADMIN can select multiple.
//   - Updates file timestamp and logs changes.
//   - Runs Python scripts to update vector store and reloads backend.
//
// Inputs (POST):
//   - file_id, visibility, departments, countries, manager_visibility.
//
// Access:
//   - ADMIN and MANAGER only.
//
// Side Effects:
//   - DB updates, vector store re-ingestion, logging.
//
// Redirect:
//   - Back to files.php.
//
// ---------------------------------------------------------------------------


// Include database connection
require_once __DIR__ . '/../connect.php';

// Include audit logging functions
include 'auto_log_function.php';

// Get user ID and role from session
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

// Redirect unauthorized users (not ADMIN or MANAGER) back to files page
if (!$user_id || !in_array($role, ['ADMIN', 'MANAGER'])) {
    header("Location: ../files.php");
    exit;
}

// Get file ID and visibility settings from POST request
$file_id = intval($_POST['file_id'] ?? 0);
$visibility = $_POST['visibility'] ?? 'all';        // 'all' or 'restricted'
$departments = $_POST['departments'] ?? [];         // array of departments (if any)
$countries = $_POST['countries'] ?? [];             // array of countries (if any)

// Validate file ID, redirect if invalid
if ($file_id <= 0) {
    header("Location: ../files.php");
    exit;
}

// Delete all existing visibility settings for this file to reset before new inserts
$deleteStmt = $conn->prepare("DELETE FROM file_visibility WHERE file_id = ?");
$deleteStmt->bind_param("i", $file_id);
$deleteStmt->execute();
$deleteStmt->close();

if ($role === 'ADMIN') {
    // ADMIN users can set visibility to ALL or multiple departments/countries
    if ($visibility === 'all') {
        // Insert a single visibility record with scope 'ALL'
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // If restricted, insert multiple DEPARTMENT and COUNTRY visibility entries if provided
        if (!empty($departments)) {
            $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
            foreach ($departments as $dept) {
                $cleanDept = trim($dept);
                if ($cleanDept !== '') {
                    $stmt->bind_param("is", $file_id, $cleanDept);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        if (!empty($countries)) {
            $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
            foreach ($countries as $country) {
                $cleanCountry = trim($country);
                if ($cleanCountry !== '') {
                    $stmt->bind_param("is", $file_id, $cleanCountry);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
    }
} elseif ($role === 'MANAGER') {
    // MANAGER users can restrict visibility by either department or country
    $managerVisibility = $_POST['manager_visibility'] ?? 'department'; // default to 'department'

    if ($visibility === 'restricted') {
        if ($managerVisibility === 'department') {
            // Insert visibility records for departments selected by manager
            if (!empty($departments)) {
                $depts = is_array($departments) ? $departments : [$departments];
                $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
                foreach ($depts as $dept) {
                    $cleanDept = trim($dept);
                    if ($cleanDept !== '') {
                        $stmt->bind_param("is", $file_id, $cleanDept);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
        } elseif ($managerVisibility === 'country') {
            // Insert visibility records for countries selected by manager
            if (!empty($countries)) {
                $ctrys = is_array($countries) ? $countries : [$countries];
                $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
                foreach ($ctrys as $country) {
                    $cleanCountry = trim($country);
                    if ($cleanCountry !== '') {
                        $stmt->bind_param("is", $file_id, $cleanCountry);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
        }
    } else {
        // If MANAGER chooses 'all' visibility, insert single record with 'ALL' scope
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Update the uploaded_at timestamp to current time after changes
$updateTimeStmt = $conn->prepare("UPDATE files SET uploaded_at = NOW() WHERE id = ?");
$updateTimeStmt->bind_param("i", $file_id);
$updateTimeStmt->execute();
$updateTimeStmt->close();

// Retrieve the filename for logging purposes
$nameStmt = $conn->prepare("SELECT filename FROM files WHERE id = ?");
$nameStmt->bind_param("i", $file_id);
$nameStmt->execute();
$nameStmt->bind_result($filename);
$nameStmt->fetch();
$nameStmt->close();

// Log the visibility edit action with user info and file details
log_action($conn, $user_id, 'files', 'edit', "Edited visibility for $filename (file ID: $file_id)");

// Prepare the filename argument for shell commands safely
$escaped_filename = escapeshellarg($filename);
$output = [];

// Run the vector purge Python script to remove old vector data for this file
exec("cd /var/www/html/chatbot && python3 chatbot/purge_vectors.py $escaped_filename 2>&1", $output, $return_code);

// Run the ingest_single Python script to re-ingest the updated file content
exec("cd /var/www/html/chatbot && python3 chatbot/ingest_single.py $escaped_filename 2>&1", $output, $return_code);

// Trigger vectorstore reload in the chatbot backend via HTTP POST request
$reload_url = 'http://host.docker.internal:8000/reload_vectorstore';
$ch = curl_init($reload_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$reload_response = curl_exec($ch);
$curl_error = curl_error($ch);
$reload_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);


// After completion, redirect user back to files listing page
header("Location: ../files.php");
exit;
