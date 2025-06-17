<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../connect.php';

header('Content-Type: text/html; charset=utf-8');

$message = "";

// Fetch audit logs
$auditLogs = [];
$sql = "
  SELECT 
    al.log_id,
    al.timestamp,
    al.user_id,
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
    html, body { height:100%; margin:0; }
    body {
      background: #f2f3fa;
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
    .filter-dropdown .dropdown-toggle {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      color: #333;
    }
    .filter-dropdown .dropdown-toggle::after {
      margin-left: .5em;
      border-top: .3em solid #333;
      border-right: .3em solid transparent;
      border-left: .3em solid transparent;
    }
    .filter-dropdown .dropdown-toggle:hover {
      background: #000;
      color: #fff;
      border-color: #000;
    }
    .filter-dropdown .dropdown-toggle:hover::after {
      border-top-color: #fff;
    }
    .table-container {
      background: #fff;
      border-radius: 8px;
      overflow-y: auto; 
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .table-container table thead th {
      background: #212529;
      color: #fff;
    }
    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }
    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
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
              <li><a href="chatbot.html">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <li class="active"><a href="#">Admin</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile"></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-message-smile"></i> Inbox</a></li>
                <li><a href="#"><i class="fa-regular fa-gear"></i> Settings</a></li>
                <li><a href="#"><i class="fa-regular fa-square-question"></i> Help</a></li>
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
              <a class="nav-link d-flex align-items-center" href="admin/announcements.html">
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
          <!-- Action Filter -->
          <div class="dropdown filter-dropdown">
            <button 
              class="btn dropdown-toggle" 
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

        <!-- Table -->
        <div class="table-container">
          <table id="audit-table" class="table table-hover mb-0 w-100">
            <thead class="table-dark">
              <tr>
                <th>Log ID</th>
                <th>Timestamp (UTC+8)</th>
                <th>Performed by</th>
                <th>Action</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td><?= htmlspecialchars($log['log_id']) ?></td>
                  <td><?= htmlspecialchars($log['timestamp']) ?></td>
                  <td><?= htmlspecialchars($log['username']) ?></td>
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

  <!-- JS includes -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    // Initialize DataTable
    const table = $('#audit-table').DataTable({
      dom: 'rt',
      paging: false,
      info: false,
      lengthChange: false
    });

    // Update log‐count on load
    $('#logCount').text(table.rows().count());

    // Hook up search box
    $('#tableSearch').on('input', function(){
      table.search(this.value).draw();
    });

    // Build the Action‐filter menu
    const actions = new Set();
    table.rows().every(function(){
      const val = this.data()[3]; // column 3 = Action
      actions.add(val);
    });

    let menuHtml = '';
    [...actions].sort().forEach(act => {
      menuHtml += `
        <div class="form-check">
          <input class="form-check-input action-checkbox" type="checkbox" value="${act}">
          <label class="form-check-label">${act}</label>
        </div>`;
    });
    $('#actionFilterMenu').html(menuHtml);

    // Custom filtering logic
    $.fn.dataTable.ext.search.push((settings, row) => {
      const selected = $('.action-checkbox:checked').map((_,el)=>el.value).get();
      return !selected.length || selected.includes(row[3]);
    });

    // When checkboxes change, redraw + update button
    $('#actionFilterMenu').on('change', '.action-checkbox', function(){
      table.draw();
      const chosen = $('.action-checkbox:checked').map((_,el)=>el.value).get();
      $('#actionFilterBtn').text('Action: ' + (chosen.length ? chosen.join(', ') : 'All'));
    });
  </script>
</body>
</html>
