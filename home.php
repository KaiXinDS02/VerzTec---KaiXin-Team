<?php
session_start();
include 'admin/auto_log_function.php';
require_once __DIR__ . '/includes/TimezoneHelper.php';

if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'USER';
}

if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'John Doe';
}

// Get user's country for timezone calculations
$user_country = $_SESSION['country'] ?? 'Singapore';

// Function to get greeting based on user's timezone
function getGreeting($user_country, $testHour = null) {
    if ($testHour !== null) {
        // For testing purposes
        if ($testHour >= 6 && $testHour < 12) {
            return 'Good Morning';
        } elseif ($testHour >= 12 && $testHour < 18) {
            return 'Good Afternoon';
        } else {
            return 'Good Evening';
        }
    }
    
    // Use TimezoneHelper for actual greeting based on user's timezone
    return TimezoneHelper::getGreeting($user_country);
}

// Function to capitalize each word in a name
function capitalizeName($name) {
    return ucwords(strtolower(trim($name)));
}

// Test hour override (for testing purposes)
$testHour = isset($_GET['testHour']) ? (int)$_GET['testHour'] : null;

// Use TimezoneHelper for user's timezone-based greeting
$greeting = $testHour !== null ? getGreeting($user_country, $testHour) : getGreeting($user_country);
$capitalizedName = capitalizeName($_SESSION['username']);


?> 

<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
  /* Make nav bar profile picture smaller and round */
  #nav-profile-pic {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #e0e0e0;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    margin: 0;
    padding: 0;
    display: block;
  }
  #home-profile-pic {
    width: 200px;
    height: 200px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #e0e0e0;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    margin-bottom: 12px;
    display: block;
    margin-left: auto;
    margin-right: auto;
  }
    /* Override main layout to fit viewport */
    .page-content-wp {
      padding-top: 170px !important;
      padding-bottom: 20px !important;
    }
    
    /* Profile picture positioning */
    .sidebar-top {
      margin-top: 20px !important;
      padding: 20px !important;
    }
    
    .right-content-wp h2 {
      font-size: 40px !important;
      font-weight: 500 !important;
      margin-bottom: 15px !important;
    }
    
    .rc-content-box {
      box-shadow: 0px 4px 4px 0px #B6BBDB40;
      background-color: #fff;
      border-radius: 10px;
      padding: 25px 20px !important;
      margin-top: 15px !important;
      min-height: auto !important;
      height: calc(320px + 250px) !important;
    }
    
    .rc-content-box .row {
      margin-top: 20px;
    }
    
    .rc-content-box .contents {
      background: transparent !important;
      padding: 0 !important;
      margin-bottom: 0 !important;
    }
    
    .input-box {
      position: relative;
      margin-bottom: 1rem;
    }
    
    .input-box input {
      width: 100%;
      padding-left: 2rem;
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
    
    .announcements-wp {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-top: 1rem;
      transition: box-shadow 0.3s ease;
      height: 320px !important;
      overflow: hidden;
      overflow-y: auto;
      position: relative;
      display: flex;
      flex-direction: column;
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    
    .announcements-wp::-webkit-scrollbar {
      display: none;
    }
    
    .announcements-wp h3 {
      font-size: 1.4rem;
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
    }
    
    .announcement {
      border-bottom: 1px solid #eee;
      padding: 1rem 1.2rem;
      flex-shrink: 0;
    }
    
    .announcement:last-child {
      border-bottom: none;
      border-radius: 0 0 8px 8px;
      background-color: #fff;
    }
    
    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start; /* ✅ top-align so badge doesn't shift down */
      gap: 1rem;
    }

    .announcement-header h4 {
      font-size: 1.2rem;
      font-weight: 600;
      color: #000;
      margin: 0;
      flex: 1 1 auto;
      word-wrap: break-word;
      overflow-wrap: break-word;
      white-space: normal;
      line-height: 1.4;
      min-width: 0;
    }

    .priority-btn {
      flex-shrink: 0;
      border: none;
      border-radius: 20px;
      padding: 0.45rem 1.2rem;
      font-size: 1rem;
      font-weight: bold;
      color: #fff;
      cursor: default;
      white-space: nowrap;
      margin-left: 0.5rem;
      height: fit-content;
    }
    .priority-high { background-color: #d9534f; }
    .priority-medium { background-color: #f0ad4e; }
    .priority-low { background-color: #5bc0de; }
    .announcement p {
      font-size: 1rem;
      color: #444;
      margin: 0;
      line-height: 1.5;
      white-space: normal;
      overflow-wrap: break-word;
      word-wrap: break-word; /* legacy fallback */
      word-break: break-word; /* optional reinforcement */
    }
    .read-more {
      font-family: 'Gudea', sans-serif;
      font-style: italic;
      color: #2a4d9c;
    }
    .modal-content {
      border-radius: 12px; /* match your header's 12px */
      background-color: #fff; /* white background for modal content */
      overflow: hidden; /* clip overflow to avoid white corner issues */
      border: none !important;
      box-shadow: none !important;
    }
    /* Modal header with rounded top corners */
    .modal-header {
      background-color: #81869E; /* match header color */
      color: #fff;
      border-radius: 12px 12px 0 0;
      border-bottom: none; /* remove border if any */
    }
    /* Modal body should have no extra border radius, full width */
    .modal-body {
      background-color: #fff; /* ensure white */
      padding: 1rem 1.5rem;
      padding-left: 1rem;
      padding-right: 1rem;
      border-radius: 0; /* reset any inherited radius */
    }
    .modal-dialog {
      max-width: 600px; /* or your preferred width */
      margin: 1.75rem auto; /* centers the modal */
      padding-left: 1rem;
      padding-right: 1rem;
    }

    /* Additional responsive adjustments */
    @media (max-height: 800px) {
      .page-content-wp {
        padding-top: 150px !important;
        padding-bottom: 15px !important;
      }
      
      .right-content-wp h2 {
        font-size: 32px !important;
        margin-bottom: 10px !important;
      }
      
      .announcements-wp {
        height: 280px !important;
      }
      
      .rc-content-box {
        height: calc(280px + 210px) !important;
        padding: 20px 15px !important;
        margin-top: 10px !important;
      }
    }
    
    @media (max-height: 700px) {
      .page-content-wp {
        padding-top: 130px !important;
        padding-bottom: 10px !important;
      }
      
      .right-content-wp h2 {
        font-size: 28px !important;
        margin-bottom: 8px !important;
      }
      
      .announcements-wp {
        height: 240px !important;
      }
      
      .rc-content-box {
        height: calc(240px + 170px) !important;
        padding: 15px 10px !important;
        margin-top: 8px !important;
      }
    }

  </style>
</head>
<body>
  <header class="header-area">
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
              <li class="active"><a href="home.php">Home</a></li>
              <li><a href="chatbot.php">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
              <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button style="padding:0; border:none; background:none;"><img id="nav-profile-pic" src="images/Profile-Icon.svg" alt=""></button>
            <div class="menu">
              <ul>
                <li><a href="#" id="nav-profile-item"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#" id="theme-toggle"><i class="fa-regular fa-moon"></i> Theme</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content-wp">
    <div class="container-fluid">
      <div class="row gap-x-40">
        <div class="col-lg-3">
          <div class="left-sidebar">
            <div class="sidebar-top">
              <figure><img id="home-profile-pic" src="images/ellipse.png" alt=""></figure>
              <div class="content">
                <h3><?= htmlspecialchars($_SESSION['username']) ?></h3>
                <span><?= htmlspecialchars($_SESSION['department']) ?>/<?= htmlspecialchars($_SESSION['role']) ?></span>
                <div class="d-flex align-items-center gap-2 justify-content-center pt-1">
                  <img src="images/location.svg" alt="">
                  <span><?= htmlspecialchars($_SESSION['country']) ?></span>
                </div>
              </div>
            </div>

            <div class="announcements-wp">
              <h3>Announcements</h3>
              <?php
                require_once 'connect.php';
                $sql = "SELECT title, context, priority, target_audience, timestamp FROM announcements ORDER BY 
              CASE LOWER(priority)
                  WHEN 'high' THEN 1
                  WHEN 'medium' THEN 2
                  WHEN 'low' THEN 3
                  ELSE 4
                  END, timestamp DESC";
              $result = $conn->query($sql);

          if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
              $priority = strtolower($row['priority']);
              $priorityClass = 'priority-low';
              if ($priority === 'high') $priorityClass = 'priority-high';
              else if ($priority === 'medium') $priorityClass = 'priority-medium';

              $shortContent = mb_strimwidth(strip_tags($row['context']), 0, 50, '...');
              $safeFullContent = nl2br(htmlspecialchars($row['context'], ENT_QUOTES, 'UTF-8'));
              $safeTitle = htmlspecialchars($row['title']);
              
              // Use TimezoneHelper to convert timestamp to user's timezone
              $formattedDate = TimezoneHelper::formatForDisplay($row['timestamp'], $user_country);
              $targetAudience = htmlspecialchars($row['target_audience']);
            ?>

            <div class="announcement">
              <div class="announcement-header">
                <h4><?= $safeTitle ?></h4>
                <button class="priority-btn <?= $priorityClass ?>"><?= ucfirst($priority) ?></button>
              </div>
              <p class="mb-1">Date: <?= $formattedDate ?></p>
              <p>
                <?= $shortContent ?>
                <a href="#" class="read-more" 
                  data-bs-toggle="modal" 
                  data-bs-target="#announcementModal"
                  data-title="<?= $safeTitle ?>"
                  data-full="<?= $safeFullContent ?>"
                  data-priority="<?= ucfirst($priority) ?>"
                  data-audience="<?= $targetAudience ?>"
                  data-timestamp="<?= $formattedDate ?>">
                  Read More
                </a>
              </p>
            </div>
          <?php endwhile; else:
            echo "<p style='font-size: 1rem; padding: 1rem;'>No announcements found.</p>";
          endif;
          $conn->close();
          ?>

            </div>
          </div>
          
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
                    white-space: normal;
                  "></p>

                  <hr>
                  <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                  <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
                  <p><strong>Posted:</strong> <span id="modalTimestamp"></span></p>
                </div>
              </div>
            </div>
          </div>
        </div>  <!-- End of col-lg-3 -->

        <div class="col-lg-9">
          <div class="right-content-wp">
            <h2 id="greeting-display"><?= $greeting ?>, <?= htmlspecialchars($capitalizedName) ?>!</h2>
            
            <div class="rc-content-box">
              <div class="contents">
                <div class="input-box">
                  <i class="fa fa-search search-icon"></i>
                  <input id="activitySearch" type="text" placeholder="What are you looking for today?">
                </div>
                <div class="row g-x-4">
                  <div class="col-xl-4 col-lg-6">
                    <div class="single-acti-box">
                      <div class="d-flex align-items-center gap-2">
                        <img src="images/tabler_message-chatbot-filled.svg" alt="">
                        <p>AI Chat bot for all <br> your inquiries</p>
                      </div>
                      <div class="text-end"><a href="chatbot.php">Go Now</a></div>
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
        </div>  <!-- End of col-lg-9 -->
      </div>
    </div>


  </main>


  <!-- Dark mode styles -->
  <style id="dark-mode-style" media="none">
    body.dark-mode {
      background: #181a1b !important;
      color: #e0e0e0 !important;
    }
    .header-area, .modal-content, .modal-header, .modal-body, .left-sidebar, .rc-content-box, .announcements-wp, .profile-modal-content {
      background: #181a1b !important;
      color: #e0e0e0 !important;
      box-shadow: none !important;
    }
    .header-area {
      border-bottom: 1px solid #23272a !important;
    }
    .modal-header, .announcements-wp h3 {
      background: #23272a !important;
      color: #fff !important;
    }
    .page-menu-wp ul li a, .page-menu-wp ul li.active a {
      color: #e0e0e0 !important;
    }
    .page-menu-wp ul li.active a {
      border-bottom: 2px solid #FFD600 !important;
    }
    /* 1. Logo swap */
    body.dark-mode .page-logo-wp img {
      content: url('images/logo-white.png');
    }
    /* 2. Greeting text white */
    body.dark-mode #greeting-display {
      color: #fff !important;
      text-shadow: 0 1px 8px #0002;
    }
    /* 3. Profile area bg black, text white/gray */
    body.dark-mode .left-sidebar {
      background: transparent !important;
      color: #fff !important;
      border: none !important;
      box-shadow: none !important;
    }
    body.dark-mode .sidebar-top {
      background: #23272a !important;
      color: #fff !important;
      border-radius: 12px !important;
      box-shadow: 0 2px 12px #0002 !important;
    }
    body.dark-mode .left-sidebar .content h3,
    body.dark-mode .left-sidebar .content span,
    body.dark-mode .left-sidebar .d-flex span {
      color: #fff !important;
    }
    body.dark-mode .left-sidebar .d-flex img {
      filter: brightness(0.8) invert(0.8);
    }
    /* 4. Announcements text white/light gray */
    body.dark-mode .announcements-wp {
      background: #181a1b !important;
      color: #e0e0e0 !important;
    }
    body.dark-mode .announcements-wp h3 {
      color: #fff !important;
    }
    body.dark-mode .announcement {
      background: #23272a !important;
      color: #e0e0e0 !important;
      border-bottom: 1px solid #23272a !important;
    }
    body.dark-mode .announcement-header h4 {
      color: #fff !important;
    }
    body.dark-mode .announcement p,
    body.dark-mode .announcement .mb-1 {
      color: #bfc4d1 !important;
    }
    body.dark-mode .priority-btn.priority-high { background: #b22222 !important; }
    body.dark-mode .priority-btn.priority-medium { background: #b8860b !important; }
    body.dark-mode .priority-btn.priority-low { background: #4682b4 !important; }
    /* 5. Search bar fully blueish gray */
    body.dark-mode .input-box {
      background: #232b3b !important;
      border-radius: 50px;
      position: relative;
      margin-bottom: 1rem;
      padding: 0; /* match light mode */
    }
    body.dark-mode .input-box input {
      width: 100%;
      background: #232b3b !important;
      color: #e0e0e0 !important;
      border-radius: 50px;
      border: 1px solid transparent;
      font-size: 1rem;
      outline: none;
      box-shadow: none !important;
      padding-left: 3rem;
      height: unset;
      margin: 0;
      display: block;
      background-clip: padding-box;
    }
    body.dark-mode .input-box .search-icon {
      position: absolute;
      top: 50%;
      left: 1rem;
      transform: translateY(-50%);
      color: #e0e0e0 !important;
      font-size: 1.2rem;
      pointer-events: none;
      line-height: 1;
    }
    body.dark-mode .input-box .search-icon {
      color: #e0e0e0 !important;
    }
    /* 6. Action boxes: keep color, but darker shade */
    body.dark-mode .single-acti-box {
      background: #9b3838ff !important;
      color: #fff !important;
      border: 1.5px solid #232b3b !important;
    }
    body.dark-mode .single-acti-box.bg-green {
      background: #468423ff !important;
      color: #fff !important;
    }
    body.dark-mode .single-acti-box[style*="background:#FCBD33;"] {
      background: #9e7214ff !important;
      color: #fff !important;
    }
    body.dark-mode .single-acti-box .d-flex p {
      color: #fff !important;
    }
    /* Modal, profile modal, etc. */
    body.dark-mode .modal-content, body.dark-mode .modal-header, body.dark-mode .modal-body, body.dark-mode .profile-modal-content {
      background: #23272a !important;
      color: #e0e0e0 !important;
    }
    body.dark-mode #profile-modal .btn-save-profile { background: #FFD600 !important; color: #23272a !important; }
    body.dark-mode #profile-modal .btn-save-profile:hover { background: #fff !important; color: #23272a !important; }
    /* Scrollbar for dark mode */
    body.dark-mode ::-webkit-scrollbar { width: 8px; background: #23272a; }
    body.dark-mode ::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
  </style>


  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="js/notification.js"></script> <!-- Custom script for handling announcements -->

  <script>
    // --- Dark Mode Toggle Logic ---
    function setDarkMode(enabled) {
      const style = document.getElementById('dark-mode-style');
      if (enabled) {
        document.body.classList.add('dark-mode');
        style.media = 'all';
        localStorage.setItem('darkMode', '1');
      } else {
        document.body.classList.remove('dark-mode');
        style.media = 'none';
        localStorage.setItem('darkMode', '0');
      }
    }

    function toggleDarkMode() {
      setDarkMode(!document.body.classList.contains('dark-mode'));
    }

    document.addEventListener('DOMContentLoaded', function() {
      // On page load, set dark mode if user previously enabled it
      if (localStorage.getItem('darkMode') === '1') {
        setDarkMode(true);
      }
      // Attach event to theme toggle
      const themeToggle = document.getElementById('theme-toggle');
      if (themeToggle) {
        themeToggle.addEventListener('click', function(e) {
          e.preventDefault();
          toggleDarkMode();
        });
      }
    });
    // --- End Dark Mode Toggle Logic ---
  </script>

  <script>
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

    const modal = document.getElementById('announcementModal');
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      document.getElementById('modalTitle').textContent = trigger.getAttribute('data-title');
      document.getElementById('modalContent').innerHTML = trigger.getAttribute('data-full');
      document.getElementById('modalAudience').textContent = trigger.getAttribute('data-audience');
      document.getElementById('modalPriority').textContent = trigger.getAttribute('data-priority');
      document.getElementById('modalTimestamp').textContent = trigger.getAttribute('data-timestamp');
    });
    
    // Client-side greeting update functions
    function updateGreeting() {
        const urlParams = new URLSearchParams(window.location.search);
        const testHour = urlParams.get('testHour');
        const userName = '<?= htmlspecialchars($capitalizedName) ?>';
        
        if (testHour) {
            // Use server-side test hour - don't update client-side
            return;
        } else {
            // Fetch updated greeting from server based on user's timezone
            fetch('get_current_greeting.php')
                .then(response => response.json())
                .then(data => {
                    if (data.greeting) {
                        document.getElementById('greeting-display').textContent = `${data.greeting}, ${userName}!`;
                    }
                })
                .catch(error => {
                    console.log('Could not update greeting:', error);
                    // Fallback to current display - no change
                });
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateGreeting();
        
        // Update greeting every minute
        setInterval(function() {
            updateGreeting();
        }, 60000);
    });
  </script>
  <script src="js/inactivity.js"></script>
</body>
</main>



  <!-- Profile Popup Modal (EXACT from chatbot.php) -->
  <div id="profile-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.45);">
    <div class="profile-modal-content" style="background:#fff; max-width:540px; margin:60px auto; border-radius:16px; box-shadow:0 4px 32px rgba(0,0,0,0.20); padding:40px 40px 32px 40px; position:relative;">
      <button onclick="closeProfileModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:26px; color:#888; cursor:pointer;">&times;</button>
      <h2 style="margin-top:0; margin-bottom:24px; font-size:1.6em; text-align:center;">Edit Profile</h2>
      <div style="display:flex; flex-direction:column; align-items:center;">
        <div class="profile-pic-wrapper">
          <img id="profile-pic-preview" class="profile-pic-preview" src="assets/avatars/default.png" alt="Profile Picture">
          <label for="profile-pic-input" class="profile-pic-pencil" title="Change profile picture">
            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M13.586 3.586a2 2 0 0 1 2.828 2.828l-8.5 8.5a2 2 0 0 1-.878.515l-3 1a1 1 0 0 1-1.263-1.263l1-3a2 2 0 0 1 .515-.878l8.5-8.5z" stroke="#444" stroke-width="1.2"/>
              <path d="M11 6l3 3" stroke="#444" stroke-width="1.2"/>
            </svg>
          </label>
          <input type="file" id="profile-pic-input" class="profile-pic-input" accept="image/*" onchange="handleProfilePicChange(this)">
        </div>
        <button onclick="saveProfile()" class="btn-save-profile">Save</button>
      </div>
    </div>
  </div>

  <style>
  #profile-modal input[type="file"]::-webkit-file-upload-button {
    background: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px 12px;
    cursor: pointer;
  }
  #profile-modal input[type="file"]::file-selector-button {
    background: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px 12px;
    cursor: pointer;
  }
  #profile-modal input[type="text"]:focus {
    border-color: #0066cc;
    outline: none;
  }
  #profile-modal button:active {
    background: #005bb5;
  }
  #profile-modal.xlarge-profile-modal .profile-modal-content {
    width: 540px !important;
    max-width: 98vw !important;
    min-height: 520px !important;
    padding: 40px 40px 32px 40px !important;
  }
  #profile-modal .profile-pic-wrapper {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 24px;
  }
  #profile-modal .profile-pic-preview {
    width: 260px;
    height: 260px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #e0e0e0;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  }
  #profile-modal .profile-pic-pencil {
    position: absolute;
    bottom: 18px;
    right: 28px;
    background: #fff;
    border-radius: 50%;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 4px rgba(0,0,0,0.10);
    cursor: pointer;
    border: 1.5px solid #d0d0d0;
    transition: background 0.2s;
  }
  #profile-modal .profile-pic-pencil:hover {
    background: #f0f0f0;
  }
  #profile-modal .profile-pic-pencil svg {
    width: 26px;
    height: 26px;
    color: #444;
  }
  #profile-modal .btn-save-profile {
    background: #111;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 0;
    width: 160px;
    font-size: 1.15em;
    font-weight: 600;
    margin-top: 32px;
    cursor: pointer;
    transition: background 0.18s, color 0.18s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }
  #profile-modal .btn-save-profile:hover {
    background: #FFD600;
    color: #111;
  }
  #profile-modal .profile-pic-input {
    display: none;
  }
  </style>
<script>
    // --- Profile Popup Modal Logic (EXACT from chatbot.php) ---
    // Open profile modal when nav profile menu is clicked
    document.addEventListener('DOMContentLoaded', function() {
      // Attach event to the Profile menu item only
      const navProfileItem = document.getElementById('nav-profile-item');
      if (navProfileItem) {
        navProfileItem.addEventListener('click', function(e) {
          e.preventDefault();
          openProfileModal();
        });
      }
      // Load profile info on page load (optional)
      loadProfileInfo();
    });

    function openProfileModal() {
      const modal = document.getElementById('profile-modal');
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
      // Make modal even bigger
      modal.classList.add('xlarge-profile-modal');
      loadProfileInfo();
    }

    function closeProfileModal() {
      const modal = document.getElementById('profile-modal');
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
      // Remove modal size classes when closing
      modal.classList.remove('large-profile-modal');
      modal.classList.remove('xlarge-profile-modal');
    }

    // Handle profile picture preview
    function handleProfilePicChange(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('profile-pic-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    // Save profile (picture only)
    async function saveProfile() {
      const picInput = document.getElementById('profile-pic-input');
      const formData = new FormData();
      if (picInput.files && picInput.files[0]) {
        formData.append('profile_pic', picInput.files[0]);
      }
      try {
        const response = await fetch('save_profile.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          alert('Profile updated successfully!');
          // After saving, reload and update all profile pics
          await updateAllProfilePics();
          closeProfileModal();
        } else {
          alert('Failed to update profile: ' + (result.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Error saving profile: ' + err.message);
      }
    }

    // Load profile info (picture)
    async function loadProfileInfo() {
      await updateAllProfilePics();
    }

    // Update all profile pictures (modal, nav, home)
    async function updateAllProfilePics() {
      try {
        const response = await fetch('get_profile.php');
        if (!response.ok) return;
        const data = await response.json();
        let picUrl = (data && data.profile_pic_url) ? data.profile_pic_url : 'assets/avatars/default.png';
        // If the URL is not absolute, make it relative to the site root
        if (!/^https?:\/\//i.test(picUrl) && !picUrl.startsWith('/')) {
          picUrl = '/' + picUrl.replace(/^\/+/,'');
        }
        // Modal
        const modalImg = document.getElementById('profile-pic-preview');
        if (modalImg) {
          modalImg.src = picUrl;
          modalImg.onerror = function() { modalImg.src = '/assets/avatars/default.png'; };
        }
        // Nav bar
        const navImg = document.getElementById('nav-profile-pic');
        if (navImg) {
          navImg.src = picUrl;
          navImg.onerror = function() { navImg.src = '/assets/avatars/default.png'; };
        }
        // Home sidebar
        const homeImg = document.getElementById('home-profile-pic');
        if (homeImg) {
          homeImg.src = picUrl;
          homeImg.onerror = function() { homeImg.src = '/assets/avatars/default.png'; };
        }
      } catch (err) {
        // fallback for all
        const modalImg = document.getElementById('profile-pic-preview');
        if (modalImg) modalImg.src = '/assets/avatars/default.png';
        const navImg = document.getElementById('nav-profile-pic');
        if (navImg) navImg.src = '/assets/avatars/default.png';
        const homeImg = document.getElementById('home-profile-pic');
        if (homeImg) homeImg.src = '/assets/avatars/default.png';
      }
    }

    // Close modal when clicking outside content
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('profile-modal');
      if (modal && e.target === modal) {
        closeProfileModal();
      }
    });

    // --- End Profile Popup Modal Logic ---
</script>
</html>

</html>

