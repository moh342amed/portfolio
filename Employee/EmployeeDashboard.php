<?php
// Start the session and check if user is logged in
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
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

// Fetch user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, name, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Calculate Present This Month
$current_month = date('Y-m');
$sql_present = "SELECT COUNT(DISTINCT DATE(clock_in)) as present_days 
                FROM users_attendance 
                WHERE user_id = ? 
                AND DATE_FORMAT(clock_in, '%Y-%m') = ?";
$stmt_present = $conn->prepare($sql_present);
$stmt_present->bind_param("is", $user_id, $current_month);
$stmt_present->execute();
$present_result = $stmt_present->get_result();
$present_data = $present_result->fetch_assoc();
$present_this_month = $present_data['present_days'] ?? 0;

// Calculate Late Arrivals
$sql_late = "SELECT COUNT(*) as late_arrivals 
             FROM users_attendance 
             WHERE user_id = ? 
             AND DATE_FORMAT(clock_in, '%Y-%m') = ? 
             AND HOUR(clock_in) >= 9 AND MINUTE(clock_in) > 0";
$stmt_late = $conn->prepare($sql_late);
$stmt_late->bind_param("is", $user_id, $current_month);
$stmt_late->execute();
$late_result = $stmt_late->get_result();
$late_data = $late_result->fetch_assoc();
$late_arrivals = $late_data['late_arrivals'] ?? 0;

// Fetch Leave Balance
$sql_leave = "SELECT annual_leave_balance, sick_leave_balance, personal_leave_balance 
              FROM users 
              WHERE id = ?";
$stmt_leave = $conn->prepare($sql_leave);
$stmt_leave->bind_param("i", $user_id);
$stmt_leave->execute();
$leave_result = $stmt_leave->get_result();
$leave_data = $leave_result->fetch_assoc();
$leave_balance = ($leave_data['annual_leave_balance'] ?? 0) + 
                 ($leave_data['sick_leave_balance'] ?? 0) + 
                 ($leave_data['personal_leave_balance'] ?? 0);

// Fetch recent attendance records
$sql_attendance = "SELECT DATE_FORMAT(clock_in, '%M %d, %Y') as date, 
                    DATE_FORMAT(clock_in, '%h:%i %p') as clock_in_time, 
                    DATE_FORMAT(clock_out, '%h:%i %p') as clock_out_time, 
                    IF(HOUR(clock_in) >= 9 AND MINUTE(clock_in) > 0, 'Late', 'Present') as status
                    FROM users_attendance 
                    WHERE user_id = ? 
                    ORDER BY clock_in DESC LIMIT 5";
$stmt_attendance = $conn->prepare($sql_attendance);
$stmt_attendance->bind_param("i", $user_id);
$stmt_attendance->execute();
$attendance_result = $stmt_attendance->get_result();

// Check today's status
$today = date('Y-m-d');
$sql_today = "SELECT * FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ?";
$stmt_today = $conn->prepare($sql_today);
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$today_result = $stmt_today->get_result();
$today_status = "Not Clocked In";
$clock_in_disabled = "";
$clock_out_disabled = "disabled";

if ($today_result->num_rows > 0) {
    $today_record = $today_result->fetch_assoc();
    if ($today_record['clock_out'] == NULL) {
        $today_status = "Clocked In";
        $clock_in_disabled = "disabled";
        $clock_out_disabled = "";
    } else {
        $today_status = "Clocked Out";
        $clock_in_disabled = "disabled";
        $clock_out_disabled = "disabled";
    }
}

// Fetch notifications
$sql_notifications = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt_notifications = $conn->prepare($sql_notifications);
$stmt_notifications->bind_param("i", $user_id);
$stmt_notifications->execute();
$notifications_result = $stmt_notifications->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Attendance Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    
    .mobile-menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--dark-text);
      cursor: pointer;
      margin-right: 15px;
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
    
    /* Dashboard Specific Styles */
    .welcome-banner {
      background-color: var(--primary-color);
      color: white;
      padding: 20px;
      border-radius: 4px;
      margin-bottom: 20px;
      position: relative;
      overflow: hidden;
    }
    
    .welcome-banner h1 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    
    .welcome-banner p {
      opacity: 0.9;
    }
    
    .welcome-banner .banner-icon {
      position: absolute;
      right: 20px;
      bottom: -20px;
      font-size: 6rem;
      opacity: 0.2;
    }
    
    .stat-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .stat-card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      padding: 20px;
      display: flex;
      align-items: center;
    }
    
    .stat-card .icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background-color: rgba(52, 152, 219, 0.1);
      color: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-right: 15px;
    }
    
    .stat-card:nth-child(2) .icon {
      background-color: rgba(46, 204, 113, 0.1);
      color: var(--secondary-color);
    }
    
    .stat-card:nth-child(3) .icon {
      background-color: rgba(243, 156, 18, 0.1);
      color: var(--warning-color);
    }
    
    .stat-card:nth-child(4) .icon {
      background-color: rgba(231, 76, 60, 0.1);
      color: var(--danger-color);
    }
    
    .stat-card .stat-info h3 {
      font-size: 0.9rem;
      color: var(--dark-text);
      opacity: 0.8;
      margin-bottom: 5px;
    }
    
    .stat-card .stat-info .stat-value {
      font-size: 1.5rem;
      font-weight: bold;
    }
    
    .dash-card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      margin-bottom: 20px;
    }
    
    .dash-card .card-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .dash-card .card-body {
      padding: 20px;
    }
    
    .quick-actions {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .quick-action-btn {
      flex: 1 1 calc(50% - 10px);
      min-width: 150px;
      padding: 15px;
      border: none;
      border-radius: 4px;
      background-color: var(--primary-color);
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 500;
      transition: background-color 0.3s;
    }
    
    .quick-action-btn:hover {
      background-color: var(--primary-dark);
    }
    
    .quick-action-btn:nth-child(2) {
      background-color: var(--secondary-color);
    }
    
    .quick-action-btn:nth-child(2):hover {
      background-color: var(--secondary-dark);
    }
    
    .quick-action-btn i {
      margin-right: 10px;
    }
    
    .attendance-list {
      width: 100%;
      border-collapse: collapse;
    }
    
    .attendance-list th, .attendance-list td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
    }
    
    .attendance-list th {
      background-color: var(--light-bg);
      font-weight: 600;
    }
    
    .attendance-list tr:hover {
      background-color: var(--light-bg);
    }
    
    .attendance-list .status {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.85rem;
      display: inline-block;
    }
    
    .status-present {
      background-color: rgba(46, 204, 113, 0.2);
      color: var(--secondary-dark);
    }
    
    .status-late {
      background-color: rgba(243, 156, 18, 0.2);
      color: var(--warning-color);
    }
    
    .status-absent {
      background-color: rgba(231, 76, 60, 0.2);
      color: var(--danger-color);
    }
    
    .notification-item {
      padding: 12px 0;
      border-bottom: 1px solid var(--border-color);
    }
    
    .notification-item:last-child {
      border-bottom: none;
    }
    
    .notification-item .notification-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .notification-item .notification-time {
      font-size: 0.75rem;
      color: var(--dark-text);
      opacity: 0.7;
    }
    
    .view-all {
      display: block;
      text-align: center;
      padding: 10px;
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 500;
    }
    
    .view-all:hover {
      text-decoration: underline;
    }
    
    #clock-in-status {
      font-size: 1.2rem;
      margin-top: 15px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 1024px) {
      .stat-cards {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        z-index: 99;
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .mobile-menu-toggle {
        display: block;
      }
      
      .quick-action-btn {
        flex: 1 1 100%;
      }
    }
    
    @media (max-width: 480px) {
      .stat-cards {
        grid-template-columns: 1fr;
      }
      
      .welcome-banner h1 {
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <header>
    <button class="mobile-menu-toggle" id="mobile-menu-toggle">
      <i class="fas fa-bars"></i>
    </button>
    <div class="logo">AMS</div>
    <div class="app-title">Attendance Management System</div>
    <div class="user-profile" id="user-profile">
      <img src="./uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="User Avatar">
      <span><?php echo htmlspecialchars($user['name']); ?></span>
    </div>
  </header>
  
  <div class="layout">
    <div class="sidebar" id="sidebar">
      <button class="toggle-btn" id="sidebar-toggle">
        <i class="fas fa-chevron-left"></i>
      </button>
      <ul>
        <li>
          <a href="./EmployeeDashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="./EmployeeProfilePage.php">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
          </a>
        </li>
        <li>
          <a href="./AttendanceManagementPage.php">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
          </a>
        </li>
        <li>
          <a href="./LeaveManagementPage.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Leave Management</span>
          </a>
        </li>
        <li>
          <a href="./EmployeeNotificationsPage.php">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
          </a>
        </li>
        <li>
          <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="main-content">
      <div class="welcome-banner">
        <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?></h1>
        <p>Today is <?php echo date('l, F j, Y'); ?></p>
        <div class="banner-icon">
          <i class="fas fa-user-clock"></i>
        </div>
      </div>
      
      <div class="stat-cards">
        <div class="stat-card">
          <div class="icon">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3>Today's Status</h3>
            <div class="stat-value" id="today-status"><?php echo $today_status; ?></div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stat-info">
            <h3>Present This Month</h3>
            <div class="stat-value" id="present-days"><?php echo $present_this_month; ?> days</div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="icon">
            <i class="fas fa-hourglass-half"></i>
          </div>
          <div class="stat-info">
            <h3>Leave Balance</h3>
            <div class="stat-value" id="leave-balance"><?php echo $leave_balance; ?> days</div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div class="stat-info">
            <h3>Late Arrivals</h3>
            <div class="stat-value" id="late-arrivals"><?php echo $late_arrivals; ?> this month</div>
          </div>
        </div>
      </div>
    
    <div class="dash-card">
      <div class="card-header">
        Clock In / Out
        <span id="current-time"></span>
      </div>
      <div class="card-body">
        <div class="quick-actions">
          <button class="quick-action-btn" id="clock-in-btn" <?php echo $clock_in_disabled; ?>>
            <i class="fas fa-sign-in-alt"></i> Clock In
          </button>
          <button class="quick-action-btn" id="clock-out-btn" <?php echo $clock_out_disabled; ?>>
            <i class="fas fa-sign-out-alt"></i> Clock Out
          </button>
        </div>
        <div id="clock-in-status" class="text-center"></div>
      </div>
    </div>
    
    <div class="grid">
      <div class="col-6">
        <div class="dash-card">
          <div class="card-header">
            Recent Attendance
          </div>
          <div class="card-body">
            <table class="attendance-list">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Clock In</th>
                  <th>Clock Out</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($attendance_result->num_rows > 0): ?>
                  <?php while ($row = $attendance_result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo $row['date']; ?></td>
                      <td><?php echo $row['clock_in_time']; ?></td>
                      <td><?php echo $row['clock_out_time'] ?? '--'; ?></td>
                      <td>
                        <span class="status status-<?php echo strtolower($row['status']); ?>">
                          <?php echo $row['status']; ?>
                        </span>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="text-center">No attendance records found</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
            <a href="./AttendanceManagementPage.php" class="view-all">View All Records</a>
          </div>
        </div>
      </div>
      
      <div class="col-6">
        <div class="dash-card">
          <div class="card-header">
            Notifications
          </div>
          <div class="card-body">
            <?php if ($notifications_result->num_rows > 0): ?>
              <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                <div class="notification-item">
                  <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                  <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                  <div class="notification-time"><?php echo htmlspecialchars($notification['time_ago']); ?></div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="notification-item">
                <div class="notification-message">No notifications at this time</div>
              </div>
            <?php endif; ?>
            <a href="./EmployeeNotificationsPage.php" class="view-all">View All Notifications</a>
          </div>
        </div>
        
        <div class="dash-card">
          <div class="card-header">
            Quick Links
          </div>
          <div class="card-body">
            <div class="quick-actions">
              <button class="quick-action-btn" onclick="window.location.href='./LeaveManagementPage.php?action=request'">
                <i class="fas fa-plus-circle"></i> Request Leave
              </button>
              <button class="quick-action-btn" style="background-color: var(--warning-color);" onclick="window.location.href='./AttendanceManagementPage.php?action=modify'">
                <i class="fas fa-edit"></i> Request Modification
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      if (sidebar.classList.contains('collapsed')) {
        mainContent.style.marginLeft = '0';
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
      } else {
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-left"></i>';
      }
    });
    
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    
    mobileMenuToggle.addEventListener('click', function() {
      sidebar.classList.toggle('active');
    });
    
    // Clock In / Out functionality
    const clockInBtn = document.getElementById('clock-in-btn');
    const clockOutBtn = document.getElementById('clock-out-btn');
    const clockInStatus = document.getElementById('clock-in-status');
    const todayStatus = document.getElementById('today-status');
    
    clockInBtn.addEventListener('click', function() {
      // Send AJAX request to record clock in
      fetch('record_attendance.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'clock_in'
        }),
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const now = new Date();
          const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          
          clockInStatus.innerHTML = `You have clocked in at <strong>${timeString}</strong>`;
          clockInBtn.disabled = true;
          clockOutBtn.disabled = false;
          todayStatus.textContent = 'Clocked In';
        } else {
          clockInStatus.innerHTML = `<span class="error">${data.error}</span>`;
        }
      })
      .catch(error => {
        clockInStatus.innerHTML = `<span class="error">An error occurred. Please try again.</span>`;
      });
    });
    
    clockOutBtn.addEventListener('click', function() {
      // Send AJAX request to record clock out
      fetch('record_attendance.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'clock_out'
        }),
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const now = new Date();
          const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          
          clockInStatus.innerHTML = `You have clocked out at <strong>${timeString}</strong>. Have a great evening!`;
          clockOutBtn.disabled = true;
          todayStatus.textContent = 'Clocked Out';
        } else {
          clockInStatus.innerHTML = `<span class="error">${data.error}</span>`;
        }
      })
      .catch(error => {
        clockInStatus.innerHTML = `<span class="error">An error occurred. Please try again.</span>`;
      });
    });
    
    // Display current time
    function updateTime() {
      const now = new Date();
      const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      document.getElementById('current-time').textContent = timeString;
    }
    
    updateTime();
    setInterval(updateTime, 1000);
  </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>