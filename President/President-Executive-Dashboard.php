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

// Overall Attendance Rate
$sql_attendance = "
    SELECT 
        COUNT(CASE WHEN clock_out IS NOT NULL THEN 1 END) AS present_days,
        COUNT(*) AS total_days
    FROM users_attendance 
    WHERE DATE(clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$result_attendance = $conn->query($sql_attendance);
$attendance_data = $result_attendance->fetch_assoc();
$overall_attendance_rate = $attendance_data['total_days'] > 0 
    ? round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100, 1) 
    : 0;

// Pending Leave Requests
$sql_pending_leaves = "
    SELECT COUNT(*) AS pending_count
    FROM leave_requests
    WHERE status = 'pending'
";
$result_pending = $conn->query($sql_pending_leaves);
$pending_leaves = $result_pending->fetch_assoc()['pending_count'];

// Employees On Leave Today
$sql_on_leave = "
    SELECT COUNT(DISTINCT user_id) AS on_leave_count
    FROM leave_requests
    WHERE status = 'approved'
    AND CURDATE() BETWEEN start_date AND end_date
";
$result_on_leave = $conn->query($sql_on_leave);
$on_leave_count = $result_on_leave->fetch_assoc()['on_leave_count'];

// Total Employees
$sql_total_employees = "SELECT COUNT(*) AS total FROM users WHERE role = 'employee'";
$result_total = $conn->query($sql_total_employees);
$total_employees = $result_total->fetch_assoc()['total'];
$on_leave_percentage = ($total_employees > 0) 
    ? round(($on_leave_count / $total_employees) * 100, 1) 
    : 0;

// Critical Alerts
$alerts = [];

// Understaffed Departments
$sql_dept_leaves = "
    SELECT u.department, COUNT(DISTINCT lr.user_id) AS leave_count
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    WHERE lr.status = 'approved'
    AND CURDATE() BETWEEN lr.start_date AND lr.end_date
    GROUP BY u.department
    HAVING COUNT(DISTINCT lr.user_id) > 3
";
$result_dept_leaves = $conn->query($sql_dept_leaves);
while ($row = $result_dept_leaves->fetch_assoc()) {
    $alerts[] = [
        'type' => 'danger',
        'title' => 'Staffing Alert',
        'message' => "{$row['department']} Department is currently understaffed due to unexpected leave ({$row['leave_count']} employees)."
    ];
}

// Department Performance Data
$sql_dept_performance = "
    SELECT 
        u.department,
        COUNT(ua.id) AS total_days,
        SUM(CASE WHEN ua.clock_out IS NOT NULL THEN 1 ELSE 0 END) AS present_days,
        COUNT(DISTINCT lr.user_id) AS leave_count
    FROM users u
    LEFT JOIN users_attendance ua ON u.id = ua.user_id
    LEFT JOIN leave_requests lr ON u.id = lr.user_id 
        AND lr.status = 'approved' 
        AND CURDATE() BETWEEN lr.start_date AND lr.end_date
    WHERE u.department IS NOT NULL AND u.department != ''
    AND DATE(ua.clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.department
";
$result_dept_performance = $conn->query($sql_dept_performance);
$kpi_data = [];
while ($row = $result_dept_performance->fetch_assoc()) {
    // Calculate attendance rate
    $attendance_rate = $row['total_days'] > 0 
        ? round(($row['present_days'] / $row['total_days']) * 100, 1)
        : 0;
    
    // Determine department status
    $status = $attendance_rate > 90 ? 'Excellent' : 
              ($attendance_rate > 80 ? 'Good' : 'Needs Attention');
    
    $status_class = $attendance_rate > 90 ? 'success' : 
                    ($attendance_rate > 80 ? 'info' : 'warning');
    
    $kpi_data[] = [
        'department' => $row['department'],
        'attendance_rate' => $attendance_rate,
        'leave_count' => $row['leave_count'],
        'status' => $status,
        'status_class' => $status_class
    ];
}

// Get current user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Dashboard - HRMS</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <!-- Add your CSS here -->
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
    
    /* Cards and Containers */
    .card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      margin-bottom: 20px;
    }
    
    .card-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .card-body {
      padding: 20px;
    }
    
    /* Grid System */
    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 20px;
    }
    
    .col-12 { grid-column: span 12; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    .col-3 { grid-column: span 3; }
    
    /* Form Elements */
    .form-group {
      margin-bottom: 15px;
    }
    
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }
    
    input[type="text"],
    input[type="password"],
    input[type="email"],
    input[type="date"],
    input[type="time"],
    select,
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 1rem;
    }
    
    .btn {
      display: inline-block;
      padding: 10px 15px;
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      font-size: 0.9rem;
      transition: background-color 0.3s;
    }
    
    .btn:hover {
      background-color: var(--primary-dark);
    }
    
    .btn-secondary {
      background-color: var(--secondary-color);
    }
    
    .btn-secondary:hover {
      background-color: var(--secondary-dark);
    }
    
    .btn-warning {
      background-color: var(--warning-color);
    }
    
    .btn-danger {
      background-color: var(--danger-color);
    }
    
    /* Table Styles */
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    table th, table td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
    }
    
    table th {
      background-color: var(--light-bg);
      font-weight: 600;
    }
    
    /* Utility Classes */
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .mb-10 { margin-bottom: 10px; }
    .mb-20 { margin-bottom: 20px; }
    .mt-10 { margin-top: 10px; }
    .mt-20 { margin-top: 20px; }
    
    /* Status Badges */
    .badge {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 0.8rem;
      color: white;
    }
    
    .badge-success { background-color: var(--secondary-color); }
    .badge-warning { background-color: var(--warning-color); }
    .badge-danger { background-color: var(--danger-color); }
    .badge-info { background-color: var(--primary-color); }
    
    /* Dashboard Specific Styles */
    .stat-card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      padding: 20px;
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    
    .stat-card-title {
      font-size: 0.9rem;
      color: #7f8c8d;
      margin-bottom: 10px;
    }
    
    .stat-card-value {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 10px;
    }
    
    .stat-card-footer {
      margin-top: auto;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
    }
    
    .stat-card-trend-up {
      color: var(--secondary-color);
    }
    
    .stat-card-trend-down {
      color: var(--danger-color);
    }
    
    /* Alert Styles */
    .alert {
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 4px;
      border-left: 4px solid;
    }
    
    .alert-danger {
      background-color: rgba(231, 76, 60, 0.1);
      border-color: var(--danger-color);
    }
    
    .alert-warning {
      background-color: rgba(243, 156, 18, 0.1);
      border-color: var(--warning-color);
    }
    
    .alert-info {
      background-color: rgba(52, 152, 219, 0.1);
      border-color: var(--primary-color);
    }
    
    /* Chart Container */
    .chart-container {
      height: 300px;
      position: relative;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .grid {
        grid-template-columns: 1fr;
      }
      
      .col-6, .col-4, .col-3 {
        grid-column: span 12;
      }
      
      .sidebar {
        position: fixed;
        z-index: 99;
        transform: translateX(-100%);
        width: var(--sidebar-width);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .menu-toggle {
        display: block;
      }
      
      .main-content {
        margin-left: 0 !important;
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
        <li><a href="./President-Executive-Dashboard.php" class="active"><i class="fas fa-chart-line"></i> <span>Executive Dashboard</span></a></li>
        <li><a href="./President-LeaveApprovalPage.php"><i class="fas fa-calendar-check"></i> <span>Leave Approval</span></a></li>
        <li><a href="./PresidentReportsReviewPage.php"><i class="fas fa-file-signature"></i> <span>Reports Review</span></a></li>
        <li><a href="./PresidentAttendanceOverviewPage.php"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
      </ul>
    </div>
    
    <div class="main-content">
        <!-- Department-wide Statistics -->
        <div class="grid mb-20">
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-card-title">Overall Attendance Rate</div>
                    <div class="stat-card-value"><?php echo $overall_attendance_rate; ?>%</div>
                </div>
            </div>
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-card-title">Pending Leave Requests</div>
                    <div class="stat-card-value"><?php echo $pending_leaves; ?></div>
                </div>
            </div>
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-card-title">Employees On Leave Today</div>
                    <div class="stat-card-value"><?php echo $on_leave_count; ?></div>
                    <div class="stat-card-footer">
                        <span><?php echo $on_leave_percentage; ?>% of total workforce</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Critical Alerts -->
        <div class="card mb-20">
            <div class="card-header">Critical Alerts & Notifications</div>
            <div class="card-body">
                <?php if (empty($alerts)): ?>
                    <div class="alert alert-info">
                        <strong>No Alerts:</strong> There are no critical alerts at this time.
                    </div>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['type']; ?>">
                            <strong><?php echo $alert['title']; ?>:</strong> <?php echo $alert['message']; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Key Performance Indicators -->
        <div class="grid">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Department Performance</div>
                    <div class="card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Attendance Rate</th>
                                    <th>Employees on Leave</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kpi_data as $kpi): ?>
                                <tr>
                                    <td><?php echo $kpi['department']; ?></td>
                                    <td><?php echo $kpi['attendance_rate']; ?>%</td>
                                    <td><?php echo $kpi['leave_count']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $kpi['status_class']; ?>">
                                            <?php echo $kpi['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
  </script>
</body>
</html>