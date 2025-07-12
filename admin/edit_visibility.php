<?php
session_start();
require_once __DIR__ . '/../connect.php';
include 'auto_log_function.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
if (!$user_id || !in_array($role, ['ADMIN', 'MANAGER'])) {
    header("Location: ../files.php");
    exit;
}

$file_id = intval($_POST['file_id'] ?? 0);
$visibility = $_POST['visibility'] ?? 'all';
$departments = $_POST['departments'] ?? [];
$countries = $_POST['countries'] ?? [];

if ($file_id <= 0) {
    header("Location: ../files.php");
    exit;
}

// Delete old visibility
$deleteStmt = $conn->prepare("DELETE FROM file_visibility WHERE file_id = ?");
$deleteStmt->bind_param("i", $file_id);
$deleteStmt->execute();
$deleteStmt->close();

if ($role === 'ADMIN') {
    // Admin can set 'all' or multiple departments/countries
    if ($visibility === 'all') {
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $stmt->close();
    } else {
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
    // Manager selects which visibility applies
    $managerVisibility = $_POST['manager_visibility'] ?? 'department'; // default to department

    if ($visibility === 'restricted') {
        if ($managerVisibility === 'department') {
            // Insert only department
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
            // Insert only country
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
        // If MANAGER sets visibility to all
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Update the uploaded_at timestamp
$updateTimeStmt = $conn->prepare("UPDATE files SET uploaded_at = NOW() WHERE id = ?");
$updateTimeStmt->bind_param("i", $file_id);
$updateTimeStmt->execute();
$updateTimeStmt->close();

// Get the filename for logging
$nameStmt = $conn->prepare("SELECT filename FROM files WHERE id = ?");
$nameStmt->bind_param("i", $file_id);
$nameStmt->execute();
$nameStmt->bind_result($filename);
$nameStmt->fetch();
$nameStmt->close();


// Logging
log_action($conn, $user_id, 'files', 'edit', "Edited visibility for $filename (file ID: $file_id)");

// === Trigger re-ingestion and vectorstore reload ===
$escaped_filename = escapeshellarg($filename);
$output = [];
exec("cd /var/www/html/chatbot && python3 chatbot/purge_vectors.py $escaped_filename 2>&1", $output, $return_code);
file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single (edit_visibility) exit code: $return_code\n", FILE_APPEND);

exec("cd /var/www/html/chatbot && python3 chatbot/ingest_single.py $escaped_filename 2>&1", $output, $return_code);
file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single (edit_visibility) exit code: $return_code\n", FILE_APPEND);

// Trigger vectorstore reload in chatbot backend
$reload_url = 'http://host.docker.internal:8000/reload_vectorstore';
$ch = curl_init($reload_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$reload_response = curl_exec($ch);
$curl_error = curl_error($ch);
$reload_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
error_log("Reload vectors POST to $reload_url (edit_visibility)");
error_log("Reload vectors cURL error: $curl_error");
error_log("Reload vectors response: $reload_response (HTTP $reload_http_code)");

header("Location: ../files.php");
exit;
