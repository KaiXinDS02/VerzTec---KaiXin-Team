<?php
session_start();
include 'admin/auto_log_function.php';

// Set $_SESSION['role'] after login
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'USER';
}

if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'John Doe'; // fallback
}
?> 

<!DOCTYPE html>
<html lang="en-US">
<head>
  <!-- Meta setup -->
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec</title>
  <link rel="icon" href="images/favicon.ico">
  <!-- Bootstrap, FontAwesome, your CSS -->
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    
    .rc-content-box .contents {
      background: transparent !important;
      padding: 0 !important;
    }

    
    .input-box {
      position: relative;
      margin-bottom: 1.5rem;
    }
    .input-box input {
      width: 100%;
      padding: .75rem 1rem;         /* top/bottom and right padding */
      padding-left: 2rem;           /* push text past the icon */
      border-radius: 50px;
      border: 1px solid transparent;
      background:transparent;
      font-size: 1rem;
      color: #333;
      outline: none;
    }
    .input-box .search-icon {
      position: absolute;
      top: 50%;
      left: 1rem;
      transform: translateY(-50%);
      color: #999;
      font-size: 1.2rem;
      pointer-events: none;
    }

    /* === Your announcements === */
    .announcements-wp {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-top: 1rem;
      transition: box-shadow 0.3s ease;
      height: 490px; /* Fixed height */
      overflow-y: auto; /* Scroll if content overflows */
      cursor: pointer;
      position: relative;
      display: flex;
      flex-direction: column;
    }

    .announcements-wp h3 {
      font-size: 1.4rem; /* Slightly bigger */
      margin: 0;
      padding: 1rem 1.2rem;
      color: #fff;
      background-color: #81869E;
      border-radius: 8px 8px 0 0;
      border-bottom: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 1;
      user-select: none;
      flex-shrink: 0;
    }

    .announcement {
      border-bottom: 1px solid #eee;
      padding: 1.2rem 1.2rem; 
      flex-shrink: 0; 
    }
    .announcement:last-child {
      border-bottom: none;
    }

    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .announcement-header h4 {
      font-size: 1.2rem; 
      font-weight: 600;
      color: #000;
      margin: 0;
      flex: 1;
    }

    .priority-btn {
      border: none;
      border-radius: 20px;
      padding: 0.45rem 1.2rem; 
      font-size: 1rem; 
      font-weight: bold;
      color: #fff;
      cursor: default;
      user-select: none;
      white-space: nowrap;
      margin-left: 1rem;
      flex-shrink: 0;
    }

    .priority-high {
      background-color: #d9534f;
    }

    .priority-medium {
      background-color: #f0ad4e;
    }

    .priority-low {
      background-color: #5bc0de;
    }

    .announcement p {
      font-size: 1rem; 
      color: #444;
      margin: 0;
      line-height: 1.5;
      max-width: 100%;
      white-space: normal;
    }
  </style>
</head>
<body>

  <!-- page header area -->
  <header class="header-area">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp">
            <img src="images/logo.png" alt="Verztec">
          </a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2
                    d-flex justify-content-center
                    justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li class="active"><a href="#">Home</a></li>
              <li><a href="chatbot.html">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
                <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6
                    d-flex justify-content-end
                    order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt=""></button>
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
  <!-- /page header area -->

  <main class="page-content-wp">
    <div class="container-fluid">
      <div class="row gap-x-40">
        <!-- left sidebar -->
        <div class="col-lg-3">
          <div class="left-sidebar">
            <div class="sidebar-top">
              <figure><img src="images/ellipse.png" alt=""></figure>
              <div class="content">
                <h3><?= htmlspecialchars($_SESSION['username']) ?></h3>
                <span><?= htmlspecialchars($_SESSION['department']) ?>/<?= htmlspecialchars($_SESSION['role']) ?></span>
                <div class="d-flex align-items-center gap-2 justify-content-center pt-1">
                  <img src="images/location.svg" alt="">
                  <span><?= htmlspecialchars($_SESSION['country']) ?></span>
                </div>
              </div>
            </div>

            <!-- dynamic announcements card  -->
            <div class="announcements-wp" onclick="window.location.href='admin/display_announcements.php'">
              <h3>Announcements</h3>
              <?php
              require_once 'connect.php';
              $sql = "SELECT title, context, priority FROM announcements ORDER BY timestamp DESC LIMIT 3";
              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $priority = strtolower($row['priority']);
                  $priorityClass = match ($priority) {
                    'high' => 'priority-high',
                    'medium' => 'priority-medium',
                    'low' => 'priority-low',
                    default => 'priority-low',
                  };
              ?>
                <div class="announcement">
                  <div class="announcement-header">
                    <h4><?= htmlspecialchars($row['title']) ?></h4>
                    <button class="priority-btn <?= $priorityClass ?>"><?= ucfirst($priority) ?></button>
                  </div>
                  <p><?= mb_strimwidth(strip_tags($row['context']), 0, 120, '...') ?></p>
                </div>
              <?php
                endwhile;
              else:
                echo "<p style='font-size: 1rem; padding: 1rem;'>No announcements found.</p>";
              endif;
              $conn->close();
              ?>
            </div>
          </div>
        </div>

        <!-- main area -->
        <div class="col-lg-9">
          <div class="right-content-wp">
            <h2>Good Morning, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            <div class="rc-content-box">

              <!-- SEARCH -->
              <div class="contents">
                <div class="input-box">
                  <i class="fa fa-search search-icon"></i>
                  <input id="activitySearch"
                         type="text"
                         placeholder="What are you looking for today?">
                </div>
              </div>

              <!-- ACTIVITY CARDS -->
              <div class="row g-x-4">
                <div class="col-xl-4 col-lg-6">
                  <div class="single-acti-box">
                    <div class="d-flex align-items-center gap-2">
                      <img src="images/tabler_message-chatbot-filled.svg" alt="">
                      <p>AI Chat bot for all <br> your inquiries</p>
                    </div>
                    <div class="text-end"><a href="chatbot.html">Go Now</a></div>
                  </div>
                </div>
                <div class="col-xl-4 col-lg-6">
                  <div class="single-acti-box bg-green">
                    <div class="d-flex align-items-center gap-2">
                      <img src="images/mdi_files.svg" alt="">
                      <p>Files and Policies</p>
                    </div>
                    <div class="text-end"><a href="files.php">Go Now</a></div>
                  </div>
                </div>
                <?php if ($_SESSION['role'] !== 'USER'): ?>
                <div class="col-xl-4 col-lg-6">
                  <div class="single-acti-box" style="background:#FCBD33;">
                    <div class="d-flex align-items-center gap-2">
                      <img src="images/mdi_settings.svg" alt="">
                      <p>Admin Page</p>
                    </div>
                    <div class="text-end"><a href="admin/users.php">Go Now</a></div>
                  </div>
                </div>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- scripts -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script>
    // Client‐side filter
    (function(){
      const input = document.getElementById('activitySearch');
      input.addEventListener('input', function(){
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.single-acti-box').forEach(box => {
          const txt = box.textContent.toLowerCase();
          box.parentElement.style.display = txt.includes(q) ? '' : 'none';
        });
      });
    })();
  </script>

  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>
</html>
