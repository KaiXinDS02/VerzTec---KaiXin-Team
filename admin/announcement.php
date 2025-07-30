<?php
session_start();
include __DIR__ . '/../connect.php';
require_once('../vendor/autoload.php'); // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $target_audience = $_POST['target_audience'] ?? [];
    $audience_str = '';

    if (is_array($target_audience)) {
        $selected = $target_audience;
        sort($selected);

        $audience_str = ($selected === ['admins', 'managers', 'users']) ? 'all' : implode(',', $selected);
        $roles_to_email = $target_audience;
    } else {
        $audience_str = $target_audience;
        $roles_to_email = [$target_audience];
    }

    $priority = $_POST['priority'] ?? '';

    if ($conn->connect_error) {
        $error = 'Database connection error.';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO announcements (title, context, target_audience, priority) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $content, $audience_str, $priority);
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare("UPDATE announcements SET title=?, context=?, target_audience=?, priority=? WHERE id=?");
            $stmt->bind_param("ssssi", $title, $content, $audience_str, $priority, $id);
        } elseif ($action === 'delete' && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
            $stmt->bind_param("i", $id);
        } else {
            $error = 'Invalid action or missing ID.';
        }

        if (isset($stmt) && $stmt->execute()) {
            if ($action === 'add') {
                // Priority badge color
                $priority_color = [
                    'High' => '#d9534f',
                    'Medium' => '#f0ad4e',
                    'Low' => '#5bc0de'
                ];
                $priority_badge_color = $priority_color[$priority] ?? '#6c757d';

                // Email HTML content
                $logo_url = "https://www.giving.sg/res/GetEntityGroupImage/77c5ee27-4e90-4615-ae1e-f1d28822d75b.jpg";
                $bodyContent = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <img src='$logo_url' alt='Logo' style='max-height: 60px;'>
                        </div>
                        <h2 style='color: #333;'>📢 " . htmlspecialchars($title) . "</h2>
                        <p style='color: #555;'>" . nl2br(htmlspecialchars($content)) . "</p>
                        <p><strong>Target Audience:</strong> " . htmlspecialchars($audience_str) . "</p>
                        <p>
                            <strong>Priority:</strong>
                            <span style='padding: 4px 10px; background-color: $priority_badge_color; color: white; border-radius: 5px;'>
                                " . htmlspecialchars($priority) . "
                            </span>
                        </p>
                        <p style='margin-top: 40px; font-size: 12px; color: #999;'>This email was sent by the Announcement System Bot.</p>
                    </div>
                ";

                // Map to DB role values
                $role_map = [
                    'users' => 'USER',
                    'managers' => 'MANAGER',
                    'admins' => 'ADMIN',
                ];

                $roles = [];
                if (strtolower($audience_str) === 'all') {
                    $roles = ['USER', 'MANAGER', 'ADMIN'];
                } else {
                    foreach (explode(',', $audience_str) as $r) {
                        $mapped = $role_map[strtolower(trim($r))] ?? null;
                        if ($mapped) $roles[] = $mapped;
                    }
                }

                foreach ($roles as $role) {
                    $getEmails = $conn->prepare("SELECT username, email FROM users WHERE role = ?");
                    $getEmails->bind_param("s", $role);
                    $getEmails->execute();
                    $result = $getEmails->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'spamacc2306@gmail.com';
                            $mail->Password = 'lfvc kyov oife mwze'; // App password
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('spamacc2306@gmail.com', 'Announcement Bot');
                            $mail->addAddress($row['email'], $row['username']);
                            $mail->isHTML(true);
                            $mail->Subject = "New Announcement: " . $title;
                            $mail->Body = $bodyContent;

                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Failed to send email to {$row['email']}: " . $mail->ErrorInfo);
                        }
                    }

                    $getEmails->close();
                }
            }

            $message = "Action '$action' successful.";
        } else {
            $error = 'Database operation failed: ' . $stmt->error;
        }

        if (isset($stmt)) $stmt->close();
    }
}

// Fetch announcements to display
$sql = "SELECT id, title, context, priority, target_audience, timestamp FROM announcements ORDER BY timestamp DESC";
$result = $conn->query($sql);

if ($result) {
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
    $count = count($announcements);
    $result->free();
} else {
    $announcements = [];
    $count = 0;
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../" />
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Verztec Admin – Announcements</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="css/bootstrap.css" />
  <link rel="stylesheet" href="css/font-awesome.css" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="css/responsive.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
  <style>
    /* Dark Theme Variables */
    :root {
      --bg-color: #f2f3fa;
      --text-color: #333333;
      --header-bg: #ffffff;
      --chat-panel-bg: #ffffff;
      --chat-bubble-bg: #f8f9fa;
      --input-bg: #ffffff;
      --border-color: #e9ecef;
      --shadow-color: rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
      --bg-color: #1a1a1a;
      --text-color: #e0e0e0;
      --header-bg: #2d2d2d;
      --chat-panel-bg: #2d2d2d;
      --chat-bubble-bg: #3a3a3a;
      --input-bg: #3a3a3a;
      --border-color: #444444;
      --shadow-color: rgba(0,0,0,0.3);
    }
    
    /* Apply theme variables */
    .header-area {
      background-color: var(--header-bg) !important;
      border-bottom: 1px solid var(--border-color);
      transition: background-color 0.3s ease;
    }
    
    .page-user-icon .menu {
      background-color: var(--chat-panel-bg);
      border: 1px solid var(--border-color);
      box-shadow: 0 4px 12px var(--shadow-color);
    }
    
    .page-user-icon .menu ul li a {
      color: var(--text-color);
      transition: color 0.3s ease;
    }
    
    .page-user-icon .menu ul li a:hover {
      background-color: var(--chat-bubble-bg);
    }
    
    html, body { height:100%; margin:0; }
    body {
      background: var(--bg-color);
      color: var(--text-color);
      transition: background-color 0.3s ease, color 0.3s ease;
      padding-top: 160px;
      padding-bottom: 160px;
    }
    .sidebar-card {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin: 1rem;
      padding: 1rem;
      min-height: calc(100vh - 320px);
    }
    .sidebar-card .nav-link {
      color: #333;
      margin-bottom: .75rem;
      border-radius: 6px;
      padding: .75rem 1rem;
    }
    .sidebar-card .nav-link.active {
      background-color: #FFD050;
      color: #000;
    }
    .search-box {
      position: relative;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      width: 250px;
    }
    .search-box input {
      border: none;
      padding: .375rem .75rem .375rem 2.5rem;
      width: 100%;
      border-radius: 8px;
    }
    .search-box i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    .table-container {
      background: #fff;
      border-radius: 8px;
      overflow-y: auto;
      height: 900px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .table-container table thead th {
      background: #212529;
      color: #fff;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }
    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
    }
    .btn-link {
      text-decoration: none;
      cursor: pointer;
      font-size: 1.2rem;
    }
    /* Make the last column (actions) wider */
    #announcementTable th:last-child,
    #announcementTable td:last-child {
      width: 120px;  /* Adjust this width as needed */
      text-align: center;
      white-space: nowrap; /* prevent line breaks inside buttons */
    }
    #announcementTable th:nth-child(3),
    #announcementTable td:nth-child(3),
    #announcementTable th:nth-child(4),
    #announcementTable td:nth-child(4) {
      text-align: left;
    }
    #announcementTable th:nth-child(4),
    #announcementTable td:nth-child(4) {
      width: 180px;
      text-align: left;
      white-space: nowrap;
    }
    #announcementTable td:nth-child(5),
    #announcementTable th:nth-child(5) {
      min-width: 80px; /* or whatever width you want */
      white-space: nowrap; /* so timestamp text stays on one line */
    }
    #announcementTable {
      width: 100%;
    }
    #announcementTable td {
      white-space: normal !important; /* allow wrapping */
      word-break: normal; /* default behavior, no breaking inside words */
      overflow-wrap: break-word; /* this helps wrapping at spaces if needed */
    }
    #announcementTable td:nth-child(2) { /* message column */
      max-width: 450px; /* limit width */
    }
    #announcementTable td:nth-child(1) {
      max-width: 150px; /* limit width for title */
    }
   .small-alert {
        max-width: 500px;
        margin: 10px auto;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);

        display: flex;
        align-items: center; /* Keep this to align the message content and button for general centering */
        text-align: center;
        line-height: 1.3; /* Keep this line-height consistent */

        position: relative;
        /* Removed padding-right here as it's better on the content div */
    }

    .small-alert .btn-close {
        font-size: 0.8rem;
        padding: 0;
        box-shadow: none;
        opacity: 0.7;

        position: absolute;
        right: 10px; /* Distance from the right edge */
        top: 10px; /* Start from a small distance from the top */
        transform: translateY(0); /* Remove the vertical centering transform */
    }

    .small-alert .btn-close:hover {
        opacity: 1;
    }

    .small-alert > div {
        flex-grow: 1;
        text-align: center;
        padding-right: 30px; /* Ensure space for the button */
    }
    #add-target-audience-btn::after,
    #edit-target-audience-btn::after {
      display: none; /* Remove the small dropdown arrow */
    }
    /* Make dropdown menu full width and bigger text */
    .dropdown-menu.p-3 {
      width: 100% !important;
      max-width: none !important;
      min-width: 100%;
      font-size: 1.1rem;
      padding-left: 1rem;
      padding-right: 1rem;
    }

    /* Increase checkbox label font size */
    .form-check-label {
      font-size: 1.1rem;
    }

    /* Make dropdown container full width */
    .dropdown.mb-3 {
      width: 100%;
    }


  </style>
</head>
<body>

  <!-- Fixed Header -->
  <header class="header-area" style="position:fixed;top:0;left:0;width:100%;z-index:999;background:white;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp">
            <img src="images/logo.png" alt="Verztec" />
          </a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li><a href="chatbot.php">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <li class="active"><a href="admin/users.php">Admin</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile" /></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-moon"></i> Theme</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-2">
        <div class="sidebar-card">
          <ul class="nav flex-column">
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/users.php">
                <i class="fa fa-users me-2"></i> Users
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/audit_log.php">
                <i class="fa fa-clock-rotate-left me-2"></i> Audit log
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active d-flex align-items-center" href="admin/announcement.php">
                <i class="fa fa-bullhorn me-2"></i> Announcements
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Main -->
      <div class="col-md-10 d-flex flex-column px-4" style="height:calc(100vh - 320px);">
        <div class="mb-2">
          <h4 class="fw-bold">Announcements (<?= $count ?>)</h4>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search announcements" />
          </div>
          <button class="btn btn-dark" id="add-ann-btn">
            <i class="fa fa-plus me-2"></i> Add Announcement
          </button>
        </div>

        <div class="table-container">
          <table class="table" id="announcementTable">
            <thead>
              <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Priority</th>
                <th>Target Audience</th>
                <th>Date</th>
                <th></th> <!-- No header label for actions -->
              </tr>
            </thead>
            <tbody>
              <?php foreach ($announcements as $row): ?>
              <tr
                data-id="<?= $row['id'] ?>"
                data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES) ?>"
                data-content="<?= htmlspecialchars($row['context'] ?? '', ENT_QUOTES) ?>"
                data-priority="<?= htmlspecialchars($row['priority'] ?? '', ENT_QUOTES) ?>"
                data-target_audience="<?= htmlspecialchars($row['target_audience'] ?? '', ENT_QUOTES) ?>"
              >
                <td><?= htmlspecialchars($row['title'] ?? 'Untitled') ?></td>
                <td><?= nl2br(htmlspecialchars($row['context'] ?? 'No message')) ?></td>
                <td><?= htmlspecialchars($row['priority'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['target_audience'] ?? '') ?></td>
                <td>
                  <?php
                    if (isset($row['timestamp'])) {
                        $date = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
                        $date->setTimezone(new DateTimeZone('Asia/Singapore'));
                        echo $date->format("d M Y, H:i");
                    } else {
                        echo 'N/A';
                    } 
                    ?>
                </td>
                <td>
                  <button
                    class="edit-announcement btn-link"
                    data-id="<?= $row['id'] ?>"
                    data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES) ?>"
                    data-content="<?= htmlspecialchars($row['context'] ?? '', ENT_QUOTES) ?>"
                    data-priority="<?= htmlspecialchars($row['priority'] ?? '', ENT_QUOTES) ?>"
                    data-target_audience="<?= htmlspecialchars($row['target_audience'] ?? '', ENT_QUOTES) ?>"
                    title="Edit Announcement"
                  >
                    <i class="fa fa-edit"></i>
                  </button>
                  <button
                    class="delete-announcement btn-link ms-2"
                    data-id="<?= $row['id'] ?>"
                    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                    title="Delete Announcement"
                  >
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ADD MODAL -->
  <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="addAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="add" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addAnnouncementModalLabel">Add Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>


          <div class="modal-body">
            <div id="add-modal-message" class="mb-3"></div> 
            <label for="add-title" class="form-label">Title</label>
            <input type="text" class="form-control mb-3" id="add-title" name="title" required />
            <label for="add-content" class="form-label">Message</label>
            <textarea class="form-control mb-3" id="add-content" name="content" rows="4" required></textarea>
            <label for="add-priority" class="form-label">Priority</label>
            <select class="form-select mb-3" id="add-priority" name="priority" required>
            <option value="">Select Priority</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
          </select>

            <label for="add-target-audience-btn" class="form-label">Target Audience</label>

            <div class="dropdown mb-3">
              <button
                class="form-select dropdown-toggle text-start"  
                type="button"
                id="add-target-audience-btn"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-haspopup="true"
              >
                Select Target Audience
              </button>
              <div class="dropdown-menu p-3" aria-labelledby="add-target-audience-btn" style="max-width: 250px;">
                <div id="add-target-audience-group" class="form-check">
                  <input type="checkbox" class="form-check-input target-checkbox" id="add-users" value="Users" />
                  <label class="form-check-label" for="add-users">Users</label><br>

                  <input type="checkbox" class="form-check-input target-checkbox" id="add-managers" value="Managers" />
                  <label class="form-check-label" for="add-managers">Managers</label><br>

                  <input type="checkbox" class="form-check-input target-checkbox" id="add-admins" value="Admins" />
                  <label class="form-check-label" for="add-admins">Admins</label>
                </div>
              </div>
            </div>

            <input type="hidden" name="target_audience" id="add-target-audience-hidden" value="">

            </div>

          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Add</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="editAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="edit" />
        <input type="hidden" id="edit-id" name="id" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label for="edit-title" class="form-label">Title</label>
            <input type="text" class="form-control mb-3" id="edit-title" name="title" required />
            <label for="edit-content" class="form-label">Message</label>
            <textarea class="form-control mb-3" id="edit-content" name="content" rows="4" required></textarea>
            <label for="edit-priority" class="form-label">Priority</label>
            <select class="form-select mb-3" id="edit-priority" name="priority" required>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </select>

             <label class="form-label">Target Audience</label>
              <div class="dropdown mb-3">
                <button
                  class="form-select dropdown-toggle text-start"
                  type="button"
                  id="edit-target-audience-btn"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                  aria-haspopup="true"
                >
                  Select Target Audience
                </button>
                <div class="dropdown-menu p-3" aria-labelledby="edit-target-audience-btn" style="max-width: 250px;">
                  <div id="edit-target-audience-group" class="form-check">
                    <input type="checkbox" class="form-check-input target-checkbox" id="edit-users" value="Users" />
                    <label class="form-check-label" for="edit-users">Users</label><br />

                    <input type="checkbox" class="form-check-input target-checkbox" id="edit-managers" value="Managers" />
                    <label class="form-check-label" for="edit-managers">Managers</label><br />

                    <input type="checkbox" class="form-check-input target-checkbox" id="edit-admins" value="Admins" />
                    <label class="form-check-label" for="edit-admins">Admins</label>
                  </div>
                </div>
              </div>

              <input type="hidden" name="target_audience" id="edit-target-audience-hidden" value="">
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-dark">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>

  <!-- DELETE MODAL -->
  <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-labelledby="deleteAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="deleteAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" id="delete-id" name="id" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteAnnouncementModalLabel">Delete Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete this announcement?</p>
            <p><strong id="delete-title"></strong></p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Delete</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- notification -->
    <!-- Modal with additional fields -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none;">
          <div class="modal-header" style="background-color:#81869E; color:#fff; border-radius: 12px 12px 0 0;">
            <h5 class="modal-title" id="announcementModalLabel" style="
              font-family: 'Gudea', sans-serif;
              font-weight: normal;
              white-space: normal;
              word-wrap: break-word;
              overflow-wrap: break-word;
            ">Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body" style="
            font-family: 'Open Sans', sans-serif;
            color:#000;
            font-size:1rem;
            padding: 1.5rem 1.75rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
          ">
            <h5 id="modalTitle" style="
              margin-bottom: 1rem;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></h5>

            <p id="modalContent" style="
              margin: 0;
              padding: 0;
              text-align: left;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></p>

            <hr>
            <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
            <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
            <p><strong>Posted:</strong> <span id="modalTimestamp"></span></p>
          </div>
        </div>
      </div>
    </div>


  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="js\notification.js"></script> <!-- script for handling announcements -->


  <script>
    // --- Helper Functions ---
    // Synchronizes the state of audience checkboxes with a hidden input and dropdown button text.
    function setupAudienceCheckboxSync(groupId, hiddenId, dropdownBtnId) {
        const checkboxes = document.querySelectorAll(`#${groupId} .target-checkbox`);
        const hiddenInput = document.getElementById(hiddenId);
        const dropdownBtn = document.getElementById(dropdownBtnId);

        function updateAudience() {
            const selected = Array.from(checkboxes)
                .filter(c => c.checked)
                .map(c => c.value);

            if (selected.length === checkboxes.length) {
                hiddenInput.value = 'all';
                dropdownBtn.textContent = 'All';
            } else if (selected.length === 0) {
                hiddenInput.value = '';
                dropdownBtn.textContent = 'Select Target Audience';
            } else {
                hiddenInput.value = selected.join(',');
                dropdownBtn.textContent = selected.join(', ');
            }
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateAudience);
        });

        return updateAudience;
    }

    // Displays an alert message within a specified container.
    function displayModalAlert(containerId, message, type) {
        $(`#${containerId}`).empty();
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show small-alert" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $(`#${containerId}`).html(alertHtml);
    }

    // --- Global Variables ---
    let updateAddAudience;
    let updateEditAudience;

    // --- Event Listeners and Initializations ---
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize audience checkbox synchronization for add and edit modals.
        updateAddAudience = setupAudienceCheckboxSync('add-target-audience-group', 'add-target-audience-hidden', 'add-target-audience-btn');
        updateEditAudience = setupAudienceCheckboxSync('edit-target-audience-group', 'edit-target-audience-hidden', 'edit-target-audience-btn');

        // Initial call to set the display for the add audience dropdown.
        updateAddAudience();

        // Load saved theme on page load.
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        console.log('Loaded theme:', savedTheme);
    });

    $(document).ready(function () {
        // Initialize DataTables for the announcement table.
        const table = $('#announcementTable').DataTable({
            paging: false,
            dom: "rt",
        });

        // Search functionality for the DataTables.
        $('#tableSearch').on('input', function () {
            table.search(this.value).draw();
        });

        // --- Add Announcement Modal Logic ---
        $('#add-ann-btn').on('click', function () {
            $('#addAnnouncementForm')[0].reset(); // Reset form fields
            $('#add-modal-message').empty(); // Clear any previous alert messages
            $('#add-target-audience-group input.target-checkbox').prop('checked', false); // Uncheck all audience checkboxes
            updateAddAudience(); // Update the audience dropdown text
            $('#add-priority').val(''); // Reset priority selection

            $('#addAnnouncementModal').modal('show');
        });

        // Handle form submission for Add Announcement Modal with client-side validation.
        $('#addAnnouncementForm').on('submit', function (event) {
            $('#add-modal-message').empty(); // Clear previous messages on submit attempt

            const title = $('#add-title').val().trim();
            const content = $('#add-content').val().trim();
            const priority = $('#add-priority').val();
            const targetAudience = $('#add-target-audience-hidden').val();

            let errorMessage = '';

            if (!title) {
                errorMessage += 'Title is required.<br>';
            }
            if (!content) {
                errorMessage += 'Message is required.<br>';
            }
            if (!priority) {
                errorMessage += 'Priority is required.<br>';
            }
            if (!targetAudience) {
                errorMessage += 'Target Audience is required.<br>';
            }

            if (errorMessage) {
                event.preventDefault(); // Prevent form submission
                displayModalAlert('add-modal-message', errorMessage, 'danger'); // Display red error within the modal
            }
            // If no errors, the form will submit normally.
        });

        // --- Edit Announcement Modal Logic ---
        $('#announcementTable').on('click', '.edit-announcement', function () {
            const tr = $(this).closest('tr');
            $('#edit-id').val(tr.data('id'));
            $('#edit-title').val(tr.data('title'));
            $('#edit-content').val(tr.data('content'));
            $('#edit-priority').val(tr.data('priority'));

            const audienceStr = tr.data('target_audience');
            let audienceArr = [];

            if (audienceStr.toLowerCase() === 'all') {
                audienceArr = ['Users', 'Managers', 'Admins'];
            } else {
                audienceArr = audienceStr.split(',').map(s => s.trim().charAt(0).toUpperCase() + s.trim().slice(1));
            }

            // Uncheck all audience checkboxes before setting based on data
            $('#edit-target-audience-group input.target-checkbox').prop('checked', false);

            audienceArr.forEach(val => {
                $(`#edit-target-audience-group input.target-checkbox[value="${val}"]`).prop('checked', true);
            });

            updateEditAudience(); // Update the edit audience dropdown text

            $('#editAnnouncementModal').modal('show');
        });

        // --- Delete Announcement Modal Logic ---
        $('#announcementTable').on('click', '.delete-announcement', function () {
            const tr = $(this).closest('tr');
            $('#delete-id').val(tr.data('id'));
            $('#delete-title').text(tr.data('title'));
            $('#deleteAnnouncementModal').modal('show');
        });
    });

    // --- Theme Toggle Functionality ---
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme); // Save theme preference
        updateThemeIcon(newTheme); // Update theme icon
        console.log('Theme switched to:', newTheme);
    }

    function updateThemeIcon(theme) {
        const themeIcon = document.querySelector('a[onclick="toggleTheme()"] i');
        if (themeIcon) {
            if (theme === 'dark') {
                themeIcon.className = 'fa-regular fa-sun';
            } else {
                themeIcon.className = 'fa-regular fa-moon';
            }
        }
    }

</script>
  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>

</html>

