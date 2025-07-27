<?php

require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../connect.php';

header('Content-Type: text/html; charset=utf-8');

$message = "";
//Hi
// Fetch audit logs
$auditLogs = [];
$sql = "
  SELECT 
    al.log_id,
    al.timestamp,
    al.user_id,
    al.category,
    al.action,
    al.details,
    u.username
  FROM audit_log AS al
  LEFT JOIN users AS u ON al.user_id = u.user_id
  ORDER BY al.timestamp DESC
";
$result = $conn->query($sql);
if ($result && $result->num_rows) {
    while ($row = $result->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec Admin – Audit Log</title>
  <link rel="icon" href="images/favicon.ico">
  <!-- Bootstrap & Font-Awesome & your CSS -->
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    :root {
      --bg-color: #f2f3fa;
      --text-color: #333;
      --header-bg: white;
      --sidebar-bg: #fff;
      --table-bg: #fff;
      --table-header-bg: #212529;
      --table-header-text: #fff;
      --border-color: #ddd;
      --shadow: rgba(0,0,0,0.1);
      --link-color: #333;
      --active-bg: #FFD050;
    }

    [data-theme="dark"] {
      --bg-color: #1a1a1a;
      --text-color: #e0e0e0;
      --header-bg: #2d2d2d;
      --sidebar-bg: #2d2d2d;
      --table-bg: #2d2d2d;
      --table-header-bg: #1a1a1a;
      --table-header-text: #e0e0e0;
      --border-color: #404040;
      --shadow: rgba(0,0,0,0.3);
      --link-color: #e0e0e0;
      --active-bg: #FFD050;
    }

    * {
      transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    html, body { height:100%; margin:0; }
    body {
      background: var(--bg-color);
      color: var(--text-color);
      padding-top: 160px;
      padding-bottom: 160px;
    }
    .sidebar-card {
      background: var(--sidebar-bg);
      border-radius: 8px;
      box-shadow: 0 2px 8px var(--shadow);
      margin: 1rem;
      padding: 1rem;
      min-height: calc(100vh - 320px);
    }
    .sidebar-card .nav-link {
      color: var(--link-color);
      margin-bottom: .75rem;
      border-radius: 6px;
      padding: .75rem 1rem;
    }
    .sidebar-card .nav-link.active {
      background-color: var(--active-bg);
      color: #000;
    }
    .search-box {
      position: relative;
      background: var(--sidebar-bg);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      width: 250px;
    }
    .search-box input {
      border: none;
      padding: .375rem .75rem .375rem 2.5rem;
      width: 100%;
      border-radius: 8px;
      background: var(--sidebar-bg);
      color: var(--text-color);
    }
    .search-box i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    .filter-dropdown .dropdown-toggle::after {
      margin-left: .5em;
      border-top: .3em solid #fff;
      border-right: .3em solid transparent;
      border-left: .3em solid transparent;
    }
    .table-container {
      background: var(--table-bg);
      border-radius: 8px;
      overflow-y: auto; 
      box-shadow: 0 2px 4px var(--shadow);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .table-container table thead th {
      background: var(--table-header-bg);
      color: var(--table-header-text);
    }
    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }
    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
    }
    #audit-table thead th {
      position: sticky;
      top: 0;
      background: var(--table-header-bg);
      color: var(--table-header-text);
      z-index: 10;
    }
    
    .header-area {
      background: var(--header-bg) !important;
    }
    
    .table {
      background: var(--table-bg);
      color: var(--text-color);
    }
    
    .table tbody tr {
      background: var(--table-bg);
      color: var(--text-color);
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
            <img src="images/logo.png" alt="Verztec">
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
            <button><img src="images/Profile-Icon.svg" alt="Profile"></button>
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
              <a class="nav-link active d-flex align-items-center" href="#">
                <i class="fa fa-clock-rotate-left me-2"></i> Audit Log
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/announcement.php">
                <i class="fa fa-bullhorn me-2"></i> Announcements
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Main -->
      <div class="col-md-10 d-flex flex-column px-4" style="height:calc(100vh - 320px);">
        <!-- Header + Count -->
        <div class="mb-2">
          <h4 class="fw-bold">
            Audit Logs (<span id="logCount"><?= count($auditLogs) ?></span>)
          </h4>
        </div>

        <!-- Controls Row -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <!-- Search -->
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" placeholder="Search log">
          </div>
          
          <!-- Filter Buttons Group -->
          <div class="d-flex gap-2">
            <!-- Category Filter -->
            <div class="dropdown filter-dropdown">
              <button 
                class="btn btn-dark dropdown-toggle" 
                id="categoryFilterBtn" 
                data-bs-toggle="dropdown" 
                aria-expanded="false"
              >
                Category: All
              </button>
              <div 
                class="dropdown-menu p-3" 
                id="categoryFilterMenu"
                aria-labelledby="categoryFilterBtn"
                style="max-height:300px; overflow-y:auto;"
              >
                <!-- dynamically filled -->
              </div>
            </div>

            <!-- Action Filter -->
            <div class="dropdown filter-dropdown">
              <button 
                class="btn btn-dark dropdown-toggle" 
                id="actionFilterBtn" 
                data-bs-toggle="dropdown" 
                aria-expanded="false"
              >
                Action: All
              </button>
              <div 
                class="dropdown-menu p-3" 
                id="actionFilterMenu"
                aria-labelledby="actionFilterBtn"
                style="max-height:300px; overflow-y:auto;"
              >
                <!-- dynamically filled -->
              </div>
            </div>
          </div>
        </div>


        <!-- Table -->
        <div class="table-container">
          <table id="audit-table" class="table table-hover mb-0 w-100">
            <thead id="audit-table" class="table-dark">
              <tr>
                <th>Log ID</th>
                <th>Timestamp (UTC+8)</th>
                <th>Performed by</th>
                <th>Category</th>
                <th>Action</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td><?= htmlspecialchars($log['log_id']) ?></td>
                  <?php
                  // Get user's country for timezone conversion
                  $user_country = $_SESSION['country'] ?? 'Singapore';
                  $formattedTimestamp = TimezoneHelper::convertToUserTimezone($log['timestamp'], $user_country, 'Y-m-d H:i:s');
                  ?>
                  <td><?= $formattedTimestamp ?></td>
                  <td><?= htmlspecialchars($log['username']) ?></td>
                  <td><?= htmlspecialchars($log['category']) ?></td>
                  <td><?= htmlspecialchars($log['action']) ?></td>
                  <td><?= htmlspecialchars($log['details']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
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

  <!-- JS includes -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="js\notification.js"></script> <!-- script for handling announcements -->
  <script>
    // Initialize DataTable
    const table = $('#audit-table').DataTable({
      dom: 'rt',
      paging: false,
      info: false,
      lengthChange: false
    });

    // Update log-count on load
    $('#logCount').text(table.rows().count());

    // Hook up search box
    $('#tableSearch').on('input', function(){
      table.search(this.value).draw();
    });

    // Build the Category filter menu
    const categories = new Set();
    table.rows().every(function(){
      const val = this.data()[3]; // column 4 = Category (0-based indexing)
      categories.add(val);
    });

    let categoryMenuHtml = '';
    [...categories].sort().forEach(cat => {
      categoryMenuHtml += `
        <div class="form-check">
          <input class="form-check-input category-checkbox" type="checkbox" value="${cat}" checked>
          <label class="form-check-label">${cat}</label>
        </div>`;
    });
    $('#categoryFilterMenu').html(categoryMenuHtml);

    // Build the Action filter menu
    const actions = new Set();
    table.rows().every(function(){
      const val = this.data()[4]; // column 5 = Action
      actions.add(val);
    });

    let actionMenuHtml = '';
    [...actions].sort().forEach(act => {
      actionMenuHtml += `
        <div class="form-check">
          <input class="form-check-input action-checkbox" type="checkbox" value="${act}" checked>
          <label class="form-check-label">${act}</label>
        </div>`;
    });
    $('#actionFilterMenu').html(actionMenuHtml);

    // Custom filtering logic to filter by both category and action
    $.fn.dataTable.ext.search.push((settings, row) => {

      const category = row[3];
      const action = row[4];
      
      // Get checked categories
      const selectedCategories = $('.category-checkbox:checked').map((_,el) => el.value).get();
      // Get checked actions
      const selectedActions = $('.action-checkbox:checked').map((_,el) => el.value).get();

      const categoryMatch = !selectedCategories.length || selectedCategories.includes(category);
      const actionMatch = !selectedActions.length || selectedActions.includes(action);

      return categoryMatch && actionMatch;
    });

    // When Category checkboxes change, redraw + update button
    $('#categoryFilterMenu').on('change', '.category-checkbox', function(){
      table.draw();
      const chosenCategories = $('.category-checkbox:checked').map((_,el) => el.value).get();
      $('#categoryFilterBtn').text('Category: ' + (chosenCategories.length ? chosenCategories.join(', ') : 'All'));
    });

    // When Action checkboxes change, redraw + update button
    $('#actionFilterMenu').on('change', '.action-checkbox', function(){
      table.draw();
      const chosenActions = $('.action-checkbox:checked').map((_,el) => el.value).get();
      $('#actionFilterBtn').text('Action: ' + (chosenActions.length ? chosenActions.join(', ') : 'All'));
    });

    // Theme functionality
    function toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      updateThemeIcon();
    }

    function updateThemeIcon() {
      const theme = document.documentElement.getAttribute('data-theme');
      const themeIcon = document.querySelector('.menu a[onclick="toggleTheme()"] i');
      if (themeIcon) {
        if (theme === 'dark') {
          themeIcon.className = 'fa-regular fa-sun';
        } else {
          themeIcon.className = 'fa-regular fa-moon';
        }
      }
    }

    // Initialize theme on page load
    document.addEventListener('DOMContentLoaded', function() {
      const savedTheme = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
      updateThemeIcon();
    });

  </script>
  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>
</html>
