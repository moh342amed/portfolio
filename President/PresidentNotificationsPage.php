<?php
session_start();

// Check if user is logged in and has president role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'president') {
    header("Location: ../login.html");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data including profile picture
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT name, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$user_name = $user['name'];
$profile_picture = $user['profile_picture'];
$upload_dir = 'uploads/profile_pictures/';

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Fetch notifications for the president
$sql_notifications = "
    SELECT * FROM notifications 
    WHERE user_id = ? OR user_id = 0 
    ORDER BY created_at DESC 
    LIMIT 20
";
$stmt = $conn->prepare($sql_notifications);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_notifications = $stmt->get_result();
$notifications = [];
while($row = $result_notifications->fetch_assoc()) {
    $notifications[] = $row;
}

// Count unread notifications
$sql_unread = "SELECT COUNT(*) as unread_count FROM notifications WHERE (user_id = ? OR user_id = 0) AND `read` = 0";
$stmt = $conn->prepare($sql_unread);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_unread = $stmt->get_result();
$unread_count = $result_unread->fetch_assoc()['unread_count'];

// Close connection
$conn->close();

// Function to determine notification type class
function getNotificationTypeClass($title) {
    if (strpos(strtolower($title), 'urgent') !== false || 
        strpos(strtolower($title), 'critical') !== false) {
        return 'critical';
    } else if (strpos(strtolower($title), 'leave') !== false) {
        return 'warning';
    } else if (strpos(strtolower($title), 'report') !== false) {
        return 'info';
    } else if (strpos(strtolower($title), 'goal') !== false || 
              strpos(strtolower($title), 'achieved') !== false) {
        return 'success';
    } else if (strpos(strtolower($title), 'attendance') !== false) {
        return 'warning';
    } else {
        return 'info';
    }
}

// Function to format date time
function formatTimeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->d == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) {
                return "Just now";
            } else {
                return $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
            }
        } else {
            return "Today, " . date('g:i A', strtotime($datetime));
        }
    } else if ($diff->d == 1) {
        return "Yesterday, " . date('g:i A', strtotime($datetime));
    } else if ($diff->d < 7) {
        return date('D', strtotime($datetime)) . ", " . date('g:i A', strtotime($datetime));
    } else {
        return date('M j, Y, g:i A', strtotime($datetime));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>President - Notifications | Attendance Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    /* CSS Variables for consistent theming */
    :root {
      --primary-color: #3498db;
      --primary-dark: #2980b9;
      --secondary-color: #2ecc71;
      --secondary-dark: #27ae60;
      --warning-color: #f39c12;
      --danger-color: #e74c3c;
      --light-bg: #f5f7fa;
      --dark-bg: #34495e;
      --dark-text: #2c3e50;
      --light-text: #ecf0f1;
      --border-color: #bdc3c7;
      --card-shadow: 0 2px 10px rgba(0,0,0,0.1);
      --header-height: 60px;
      --sidebar-width: 250px;
      --sidebar-collapsed: 60px;
    }
    
    /* Reset and Base Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      background-color: var(--light-bg);
      color: var(--dark-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    
    /* Layout Components */
    .layout {
      display: flex;
      min-height: calc(100vh - var(--header-height));
    }
    
    header {
      height: var(--header-height);
      background-color: white;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: space-between; 
      padding: 0 20px;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    header .logo {
      font-weight: bold;
      font-size: 1.2rem;
      color: var(--primary-color);
    }
    
    header .app-title {
      margin-left: 20px;
      font-size: 1.1rem;
    }
    
    header .user-profile {
      margin-left: auto;
      display: flex;
      align-items: center;
      cursor: pointer;
    }
    
    header .user-profile img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 10px;
    }
    
    .user-menu {
      position: relative;
    }
    
    .user-dropdown {
      position: absolute;
      top: calc(var(--header-height) - 10px);
      right: 0;
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      width: 200px;
      display: none;
      z-index: 101;
    }
    
    .user-dropdown.show {
      display: block;
    }
    
    .user-dropdown ul {
      list-style-type: none;
    }
    
    .user-dropdown ul li a {
      display: block;
      padding: 10px 15px;
      text-decoration: none;
      color: var(--dark-text);
    }
    
    .user-dropdown ul li a:hover {
      background-color: var(--light-bg);
    }
    
    /* Sidebar Navigation */
    .sidebar {
      width: var(--sidebar-width);
      background-color: var(--dark-bg);
      color: var(--light-text);
      height: calc(100vh - var(--header-height));
      position: sticky;
      top: var(--header-height);
      transition: width 0.3s;
      overflow-x: hidden;
    }
    
    .sidebar.collapsed {
      width: var(--sidebar-collapsed);
    }
    
    .sidebar .toggle-btn {
      width: 100%;
      padding: 15px;
      text-align: right;
      color: var(--light-text);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.2rem;
    }
    
    .sidebar ul {
      list-style-type: none;
      padding: 0;
    }
    
    .sidebar ul li a {
      display: flex;
      align-items: center;
      padding: 15px;
      color: var(--light-text);
      text-decoration: none;
      transition: background-color 0.3s;
    }
    
    .sidebar ul li a:hover {
      background-color: rgba(255,255,255,0.1);
    }
    
    .sidebar ul li a.active {
      background-color: var(--primary-color);
    }
    
    .sidebar ul li a i {
      margin-right: 15px;
      width: 20px;
      text-align: center;
    }
    
    .sidebar.collapsed ul li a span {
      display: none;
    }
    
    /* Main Content Area */
    .main-content {
      flex-grow: 1;
      padding: 20px;
      transition: margin-left 0.3s;
    }
    
    /* Page Title */
    .page-title {
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-color);
    }
    
    /* Notification Specific Styles */
    .notification-list {
      max-height: 600px;
      overflow-y: auto;
    }
    
    .notification-item {
      margin-bottom: 15px;
      padding: 15px;
      border-radius: 4px;
      background-color: white;
      box-shadow: var(--card-shadow);
      border-left: 4px solid var(--primary-color);
      display: flex;
      align-items: flex-start;
    }
    
    .notification-item.unread {
      background-color: rgba(52, 152, 219, 0.05);
    }
    
    .notification-item.critical {
      border-left-color: var(--danger-color);
    }
    
    .notification-item.warning {
      border-left-color: var(--warning-color);
    }
    
    .notification-item.success {
      border-left-color: var(--secondary-color);
    }
    
    .notification-icon {
      margin-right: 15px;
      font-size: 1.5rem;
      width: 30px;
      text-align: center;
    }
    
    .notification-icon.critical {
      color: var(--danger-color);
    }
    
    .notification-icon.warning {
      color: var(--warning-color);
    }
    
    .notification-icon.success {
      color: var(--secondary-color);
    }
    
    .notification-icon.info {
      color: var(--primary-color);
    }
    
    .notification-content {
      flex-grow: 1;
    }
    
    .notification-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .notification-message {
      margin-bottom: 10px;
      color: #555;
    }
    
    .notification-time {
      font-size: 0.8rem;
      color: #777;
    }
    
    .notification-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }
    
    .notification-actions button {
      margin-left: 10px;
      padding: 5px 10px;
      border: none;
      border-radius: 3px;
      cursor: pointer;
      font-size: 0.8rem;
    }
    
    .filter-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      background-color: white;
      padding: 10px 15px;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
    }
    
    .filter-options {
      display: flex;
      gap: 10px;
    }
    
    .filter-button {
      padding: 5px 10px;
      border: 1px solid var(--border-color);
      background-color: white;
      border-radius: 20px;
      cursor: pointer;
      font-size: 0.9rem;
    }
    
    .filter-button.active {
      background-color: var(--primary-color);
      color: white;
      border-color: var(--primary-color);
    }
    
    @media (max-width: 768px) {
      .filter-bar {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
      }
      
      .filter-options {
        width: 100%;
        overflow-x: auto;
        padding-bottom: 5px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="logo">HRMS</div>
    <div class="app-title">Attendance Management System</div>
    <div class="user-menu">
      <div class="user-profile">
        <?php 
        // Profile picture display logic
        $profile_picture_path = $upload_dir . $profile_picture;
        if (!empty($profile_picture) && file_exists($profile_picture_path)): ?>
          <img src="<?php echo $profile_picture_path; ?>" alt="User Profile">
        <?php else: ?>
          <img src="/api/placeholder/32/32" alt="User Profile">
        <?php endif; ?>
        <span><?php echo $user_name; ?> (President)</span>
        <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
      </div>
      <div class="user-dropdown">
        <ul>
          <li><a href="./PresidentProfilePage.php"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
          <li><a href="/projectweb/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </header>
  
  <!-- Main Layout -->
  <div class="layout">
    <!-- Sidebar -->
    <div class="sidebar">
      <button class="toggle-btn">
        <i class="fas fa-bars"></i>
      </button>
      <ul>
        <li><a href="./President-Executive-Dashboard.php"><i class="fas fa-chart-line"></i> <span>Executive Dashboard</span></a></li>
        <li><a href="./President-LeaveApprovalPage.php"><i class="fas fa-calendar-check"></i> <span>Leave Approval</span></a></li>
        <li><a href="./PresidentReportsReviewPage.php"><i class="fas fa-file-signature"></i> <span>Reports Review</span></a></li>
        <li><a href="./PresidentAttendanceOverviewPage.php"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php" class="active"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
      </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="page-title">
        <h1>Notifications <?php if($unread_count > 0): ?><span class="badge badge-danger"><?php echo $unread_count; ?> new</span><?php endif; ?></h1>
      </div>
      
      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-options">
          <button class="filter-button active" data-filter="all">All</button>
          <button class="filter-button" data-filter="critical">Critical</button>
          <button class="filter-button" data-filter="warning">Attendance & Leave</button>
          <button class="filter-button" data-filter="info">Reports</button>
          <button class="filter-button" data-filter="success">Achievements</button>
        </div>
        <div>
          <button id="mark-all-read" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
            <i class="fas fa-check-double"></i> Mark All as Read
          </button>
        </div>
      </div>
      
      <!-- Notifications List -->
      <div class="notification-list">
        <?php if (empty($notifications)): ?>
          <div class="no-notifications">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications at this time</p>
          </div>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <?php 
              $type_class = getNotificationTypeClass($notification['title']); 
              $read_class = $notification['read'] ? '' : 'unread';
              $time_ago = formatTimeAgo($notification['created_at']);
            ?>
            <div class="notification-item <?php echo $type_class; ?> <?php echo $read_class; ?>" data-id="<?php echo $notification['id']; ?>">
              <div class="notification-icon <?php echo $type_class; ?>">
                <?php if ($type_class == 'critical'): ?>
                  <i class="fas fa-exclamation-circle"></i>
                <?php elseif ($type_class == 'warning'): ?>
                  <i class="fas fa-exclamation-triangle"></i>
                <?php elseif ($type_class == 'info'): ?>
                  <i class="fas fa-info-circle"></i>
                <?php elseif ($type_class == 'success'): ?>
                  <i class="fas fa-check-circle"></i>
                <?php endif; ?>
              </div>
              <div class="notification-content">
                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                <div class="notification-message">
                  <?php echo htmlspecialchars($notification['message']); ?>
                </div>
                <div class="notification-time"><?php echo $time_ago; ?></div>
                <div class="notification-actions">
                  <?php if (strpos(strtolower($notification['title']), 'leave request') !== false): ?>
                    <button class="btn approve-btn" data-id="<?php echo $notification['id']; ?>" style="background-color: var(--success-color);">Approve</button>
                    <button class="btn reject-btn" data-id="<?php echo $notification['id']; ?>" style="background-color: var(--danger-color);">Reject</button>
                  <?php elseif (strpos(strtolower($notification['title']), 'report') !== false): ?>
                    <button class="btn view-btn" data-id="<?php echo $notification['id']; ?>" style="background-color: var(--primary-color);">View Report</button>
                  <?php else: ?>
                    <button class="btn view-details-btn" data-id="<?php echo $notification['id']; ?>" style="background-color: var(--primary-color);">View Details</button>
                  <?php endif; ?>
                  <button class="btn dismiss-btn" data-id="<?php echo $notification['id']; ?>" style="background-color: #6c757d;">Dismiss</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script>
    // Toggle User Dropdown
    document.querySelector('.user-profile').addEventListener('click', function() {
      document.querySelector('.user-dropdown').classList.toggle('show');
    });
    
    // Toggle Sidebar
    document.querySelector('.toggle-btn').addEventListener('click', function() {
      document.querySelector('.sidebar').classList.toggle('collapsed');
      
      const mainContent = document.querySelector('.main-content');
      if (document.querySelector('.sidebar').classList.contains('collapsed')) {
        mainContent.style.marginLeft = '60px';
      } else {
        mainContent.style.marginLeft = '0';
      }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.user-menu')) {
        document.querySelector('.user-dropdown').classList.remove('show');
      }
    });
    
    // Filter buttons
    document.querySelectorAll('.filter-button').forEach(button => {
      button.addEventListener('click', function() {
        document.querySelectorAll('.filter-button').forEach(btn => {
          btn.classList.remove('active');
        });
        this.classList.add('active');
        
        const filter = this.getAttribute('data-filter');
        const items = document.querySelectorAll('.notification-item');
        
        items.forEach(item => {
          if (filter === 'all') {
            item.style.display = 'flex';
          } else {
            if (item.classList.contains(filter)) {
              item.style.display = 'flex';
            } else {
              item.style.display = 'none';
            }
          }
        });
      });
    });
    
    // Mark notification as read when clicked
    document.querySelectorAll('.notification-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn')) {
          const notificationId = this.getAttribute('data-id');
          markAsRead(notificationId);
          this.classList.remove('unread');
        }
      });
    });
    
    // Dismiss notification
    document.querySelectorAll('.dismiss-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const notificationId = this.getAttribute('data-id');
        dismissNotification(notificationId);
      });
    });
    
    // Mark all as read
    document.getElementById('mark-all-read').addEventListener('click', function() {
      markAllAsRead();
    });
    
    // View details, approve, reject buttons
    document.querySelectorAll('.view-details-btn, .approve-btn, .reject-btn, .view-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const notificationId = this.getAttribute('data-id');
        const action = this.className.split(' ')[1].split('-')[0]; // extract action type from class
        handleNotificationAction(notificationId, action);
      });
    });
    
    // Functions to handle notification actions via AJAX
    function markAsRead(id) {
      fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateUnreadBadge();
        }
      })
      .catch(error => console.error('Error:', error));
    }
    
    function dismissNotification(id) {
      fetch('dismiss_notification.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const notification = document.querySelector(`.notification-item[data-id="${id}"]`);
          notification.style.animation = 'fadeOut 0.5s';
          setTimeout(() => {
            notification.remove();
            updateUnreadBadge();
          }, 500);
        }
      })
      .catch(error => console.error('Error:', error));
    }
    
    function markAllAsRead() {
      fetch('mark_all_notifications_read.php', {
        method: 'POST'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
          });
          updateUnreadBadge(0);
        }
      })
      .catch(error => console.error('Error:', error));
    }
    
    function handleNotificationAction(id, action) {
      // Mark as read first
      markAsRead(id);
      
      // Handle specific actions
      switch(action) {
        case 'approve':
          window.location.href = `President-LeaveApprovalPage.php?approve=${id}`;
          break;
        case 'reject':
          window.location.href = `President-LeaveApprovalPage.php?reject=${id}`;
          break;
        case 'view':
          window.location.href = `PresidentReportsReviewPage.php?report=${id}`;
          break;
        default:
          // View details is default
          window.location.href = `notification_details.php?id=${id}`;
      }
    }
    
    function updateUnreadBadge(count = null) {
      if (count === null) {
        // If count not provided, fetch current count
        fetch('get_unread_count.php')
          .then(response => response.json())
          .then(data => {
            const badge = document.querySelector('.badge.badge-danger');
            if (data.count > 0) {
              if (badge) {
                badge.textContent = data.count + ' new';
              } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'badge badge-danger';
                newBadge.textContent = data.count + ' new';
                document.querySelector('.page-title h1').appendChild(newBadge);
              }
            } else if (badge) {
              badge.remove();
            }
          })
          .catch(error => console.error('Error:', error));
      } else {
        // Use provided count
        const badge = document.querySelector('.badge.badge-danger');
        if (count > 0) {
          if (badge) {
            badge.textContent = count + ' new';
          } else {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge badge-danger';
            newBadge.textContent = count + ' new';
            document.querySelector('.page-title h1').appendChild(newBadge);
          }
        } else if (badge) {
          badge.remove();
        }
      }
    }
  </script>
</body>
</html>