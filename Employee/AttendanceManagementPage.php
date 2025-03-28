<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /projectweb/login.html");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_name = $_SESSION['name'];

// Get current month and year
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$current_month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
$current_year = $year;

// Get days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get first day of month
$first_day_of_month = date('N', strtotime("$year-$month-01"));
if ($first_day_of_month == 7) $first_day_of_month = 0; // Convert Sunday from 7 to 0

// Get user's join date
$sql_join_date = "SELECT join_date FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_join_date);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$join_date_result = $stmt->get_result();
$join_date_row = $join_date_result->fetch_assoc();
$join_date = $join_date_row['join_date'];

// Get attendance data for current month
try {
  $sql = "SELECT 
              u.id, 
              u.name, 
              u.profile_picture, 
              u.present_this_month, 
              u.late_arrivals, 
              u.leave_balance,
              u.join_date,
              DATE(ua.clock_in) as date,
              TIME(ua.clock_in) as clock_in_time,
              TIME(ua.clock_out) as clock_out_time
          FROM 
              users u
          LEFT JOIN 
              users_attendance ua 
          ON 
              u.id = ua.user_id 
              AND MONTH(ua.clock_in) = ? 
              AND YEAR(ua.clock_in) = ?
          WHERE 
              u.id = ?";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
      throw new Exception("Prepare failed: " . $conn->error);
  }

  $stmt->bind_param("iii", $month, $year, $user_id);
  if (!$stmt->execute()) {
      throw new Exception("Execute failed: " . $stmt->error);
  }

  $result = $stmt->get_result();
  if (!$result) {
      throw new Exception("Get result failed: " . $stmt->error);
  }

  $attendance_data = [];
  $user = [];

  while ($row = $result->fetch_assoc()) {
      if (empty($user)) {
          $user = [
              'id' => $row['id'],
              'name' => $row['name'],
              'profile_picture' => $row['profile_picture'],
              'present_this_month' => $row['present_this_month'],
              'late_arrivals' => $row['late_arrivals'],
              'leave_balance' => $row['leave_balance'],
              'join_date' => $row['join_date']
          ];
      }

      if ($row['date']) {
          $attendance_data[$row['date']] = [
              'clock_in' => $row['clock_in_time'],
              'clock_out' => $row['clock_out_time']
          ];
      }
  }
} catch (Exception $e) {
  // Log error and set a user-friendly message
  error_log("Attendance Data Retrieval Error: " . $e->getMessage());
  $error_message = "Unable to retrieve attendance data. Please try again later.";
}

// Count attendance statistics for the month
$sql_stats = "SELECT 
                COUNT(*) as present_days,
                SUM(CASE WHEN TIME(clock_in) > '09:05:00' THEN 1 ELSE 0 END) as late_arrivals
              FROM 
                users_attendance
              WHERE 
                user_id = ? AND
                MONTH(clock_in) = ? AND
                YEAR(clock_in) = ?";

$stmt = $conn->prepare($sql_stats);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

$present_days = $stats['present_days'] ?? 0;
$late_arrivals = $stats['late_arrivals'] ?? 0;

// Calculate work days in month (excluding weekends - now Friday and Saturday)
$work_days = 0;
$workable_days = 0; // Days after joining
for ($i = 1; $i <= $days_in_month; $i++) {
    $date_str = sprintf('%s-%02d-%02d', $year, $month, $i);
    $day_of_week = date('N', strtotime($date_str));
    
    // Changed weekend to Friday (5) and Saturday (6)
    if ($day_of_week != 5 && $day_of_week != 6) { // Sunday to Thursday
        $work_days++;
        
        // Check if the date is after join date
        if (strtotime($date_str) >= strtotime($join_date)) {
            $workable_days++;
        }
    }
}

// Get leaves for the month
$sql_leaves = "SELECT COUNT(*) as leave_days FROM leaves WHERE user_id = ? AND MONTH(start_date) = ? AND YEAR(start_date) = ? AND status = 'approved'";
$stmt = $conn->prepare($sql_leaves);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$leaves_result = $stmt->get_result();
$leaves = $leaves_result->fetch_assoc();

$leave_days = $leaves['leave_days'] ?? 0;

// Calculate absent days (workable days minus present days minus leave days)
$absent_days = $workable_days - $present_days - $leave_days;
if ($absent_days < 0) $absent_days = 0;

// Calculate previous and next month/year
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Check if today is user's joining anniversary
$is_joining_anniversary = false;
$joining_message = '';
if (date('m-d', strtotime($join_date)) == date('m-d')) {
    $years_at_company = date('Y') - date('Y', strtotime($join_date));
    if ($years_at_company == 0) {
        $is_joining_anniversary = true;
        $joining_message = "🎉 Welcome to the team, " . htmlspecialchars($user['name']) . "! We're thrilled to have you with us on your first day!";
    } else {
        $is_joining_anniversary = true;
        $joining_message = "🎂 Happy Work Anniversary, " . htmlspecialchars($user['name']) . "! {$years_at_company} " . ($years_at_company == 1 ? "year" : "years") . " of awesome work!";
    }
}

// Add AJAX handling for dynamic modification request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_modification_request'])) {
  header('Content-Type: application/json');
  
  $response = [
      'status' => 'error',
      'message' => 'Unknown error occurred'
  ];

  try {
      $modification_date = $_POST['modification_date'];
      $modification_type = $_POST['modification_type'];
      $modification_time = $_POST['modification_time'] ?? null;
      $modification_reason = $_POST['modification_reason'];

      // Validate inputs
      if (empty($modification_date) || empty($modification_type) || empty($modification_reason)) {
          throw new Exception("All required fields must be filled");
      }

      // Check existing pending requests with more granular conditions
      $check_sql = "SELECT id, modification_type, modification_time 
                    FROM attendance_modifications 
                    WHERE user_id = ? 
                    AND modification_date = ? 
                    AND status = 'pending'";
      $check_stmt = $conn->prepare($check_sql);
      $check_stmt->bind_param("is", $user_id, $modification_date);
      $check_stmt->execute();
      $check_result = $check_stmt->get_result();

      // Flag to track if a similar request exists
      $similar_request_exists = false;

      while ($existing_request = $check_result->fetch_assoc()) {
          // Check if the new request is for the same modification type
          if ($existing_request['modification_type'] === $modification_type) {
              // If modification time is provided, compare it
              if ($modification_time !== null && $modification_time !== '') {
                  if ($existing_request['modification_time'] === $modification_time) {
                      $similar_request_exists = true;
                      break;
                  }
              } else {
                  // If no specific time, consider it a duplicate request
                  $similar_request_exists = true;
                  break;
              }
          }
      }

      // Throw exception if a similar pending request exists
      if ($similar_request_exists) {
          throw new Exception("A pending modification request for this date and type already exists");
      }

      // Insert modification request
      $sql = "INSERT INTO attendance_modifications (user_id, modification_date, modification_type, modification_time, reason, status, created_at) 
              VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("issss", $user_id, $modification_date, $modification_type, $modification_time, $modification_reason);

      if ($stmt->execute()) {
          // Create notification
          $title = "Attendance Modification Request";
          $message = "New attendance modification request from " . $_SESSION['name'] . " on " . $modification_date;

          $admin_id = 1; // Replace with actual admin/manager logic
          $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
          $notification_stmt = $conn->prepare($notification_sql);
          $notification_stmt->bind_param("iss", $admin_id, $title, $message);
          $notification_stmt->execute();

          $response = [
              'status' => 'success',
              'message' => 'Modification request submitted successfully'
          ];
      } else {
          throw new Exception("Failed to submit modification request");
      }
  } catch (Exception $e) {
      $response = [
          'status' => 'error',
          'message' => $e->getMessage()
      ];
  }

  echo json_encode($response);
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Management | AMS</title>
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
    
    /* Calendar Styles */
    .calendar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .calendar-nav {
      display: flex;
      gap: 10px;
    }
    
    .calendar-nav button {
      background-color: white;
      border: 1px solid var(--border-color);
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .calendar-nav button:hover {
      background-color: var(--light-bg);
    }
    
    .calendar-nav .current-month {
      font-weight: bold;
      padding: 8px 15px;
    }
    
    .calendar {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 10px;
    }
    
    .calendar-day-header {
      text-align: center;
      font-weight: bold;
      padding: 10px;
      background-color: var(--dark-bg);
      color: var(--light-text);
      border-radius: 4px;
    }
    
    .calendar-day {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      height: 100px;
      padding: 10px;
      position: relative;
    }
    
    .calendar-day.inactive {
      opacity: 0.5;
    }
    
    .calendar-day.today {
      box-shadow: 0 0 0 2px var(--primary-color);
    }
    
    .calendar-day-number {
      font-weight: bold;
      font-size: 0.9rem;
    }
    
    .day-status {
      margin-top: 5px;
      font-size: 0.8rem;
      padding: 3px 6px;
      border-radius: 3px;
      display: inline-block;
    }
    
    .status-present {
      background-color: rgba(46, 204, 113, 0.2);
      color: var(--secondary-dark);
    }
    
    .status-absent {
      background-color: rgba(231, 76, 60, 0.2);
      color: var(--danger-color);
    }
    
    .status-late {
      background-color: rgba(243, 156, 18, 0.2);
      color: var(--warning-color);
    }
    
    .status-leave {
      background-color: rgba(52, 152, 219, 0.2);
      color: var(--primary-color);
    }
    
    .day-times {
      font-size: 0.75rem;
      margin-top: 5px;
    }
    
    .attendance-actions {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      gap: 10px;
    }
    
    .attendance-filters select {
      padding: 8px;
      border-radius: 4px;
      border: 1px solid var(--border-color);
    }
    
    .attendance-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .summary-card {
      background-color: white;
      padding: 15px;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      text-align: center;
    }
    
    .summary-card h3 {
      font-size: 0.9rem;
      color: var(--dark-text);
      margin-bottom: 10px;
    }
    
    .summary-card .value {
      font-size: 1.8rem;
      font-weight: bold;
    }
    
    .request-modification {
      margin-top: 20px;
    }
    
    /* Responsive Adjustments */
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
      
      .calendar {
        gap: 5px;
      }
      
      .calendar-day {
        height: 80px;
      }
      
      .attendance-actions {
        flex-direction: column;
      }
    }
    
    @media (max-width: 480px) {
      .calendar-day {
        height: 60px;
        padding: 5px;
      }
      
      .day-times,
      .day-status {
        display: none;
      }
      
      .attendance-summary {
        grid-template-columns: 1fr 1fr;
      }
    }

    /* Request Modification Form Styles */
    .request-modification .card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      margin-top: 20px;
    }

    .request-modification .card-header {
      padding: 15px;
      border-bottom: 1px solid var(--border-color);
      font-weight: bold;
      background-color: var(--light-bg);
    }

    .request-modification .card-body {
      padding: 20px;
    }

    .request-modification .form-group {
      margin-bottom: 15px;
    }

    .request-modification label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }

    .request-modification input[type="date"],
    .request-modification input[type="time"],
    .request-modification select,
    .request-modification textarea {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.9rem;
    }

    .request-modification textarea {
      min-height: 100px;
      resize: vertical;
    }

    .request-modification button[type="submit"] {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .request-modification button[type="submit"]:hover {
      background-color: var(--primary-dark);
    }

    .request-modification #cancel-modification {
      background-color: var(--light-bg);
      border: 1px solid var(--border-color);
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 10px;
      transition: background-color 0.3s;
    }

    .request-modification #cancel-modification:hover {
      background-color: #e0e0e0;
    }

    /* Join day indicator styles */
    .join-day-indicator {
      position: absolute;
      top: 5px;
      right: 5px;
      font-size: 1rem;
    }

    .join-day-message {
      font-size: 0.7rem;
      margin-top: 5px;
      color: var(--primary-color);
      font-weight: bold;
    }

    /* Pre-join status */
    .status-pre-join {
      background-color: rgba(189, 195, 199, 0.2);
      color: var(--dark-text);
    }

    /* Weekend days */
    .weekend {
      background-color: #f9f9f9;
    }

    /* Responsive adjustments for form */
    @media (max-width: 768px) {
      .request-modification .card-body {
        padding: 15px;
      }
      
      .request-modification .form-group {
        margin-bottom: 12px;
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
          <a href="./EmployeeDashboard.php">
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
          <a href="./AttendanceManagementPage.php" class="active">
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
          <a href="/projectweb/logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="main-content">
      <h1 class="page-title">Attendance Management</h1>
      
      <?php if ($is_joining_anniversary): ?>
      <div class="welcome-banner">
        <?php echo $joining_message; ?>
      </div>
      <?php endif; ?>
      
      <div class="attendance-summary">
        <div class="summary-card">
          <h3>Present Days</h3>
          <div class="value"><?php echo $present_days; ?></div>
          <div>This Month</div>
        </div>
        <div class="summary-card">
          <h3>Absent Days</h3>
          <div class="value"><?php echo $absent_days; ?></div>
          <div>This Month</div>
        </div>
        <div class="summary-card">
          <h3>Late Arrivals</h3>
          <div class="value"><?php echo $late_arrivals; ?></div>
          <div>This Month</div>
        </div>
        <div class="summary-card">
          <h3>On Leave</h3>
          <div class="value"><?php echo $leave_days; ?></div>
          <div>This Month</div>
        </div>
      </div>
      
      <div class="attendance-actions">
        <div class="calendar-nav">
          <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn" id="prev-month"><i class="fas fa-chevron-left"></i></a>
          <div class="current-month"><?php echo $current_month_name . ' ' . $current_year; ?></div>
          <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn" id="next-month"><i class="fas fa-chevron-right"></i></a>
        </div>
        
        <div class="attendance-filters">
          <select id="view-type">
            <option value="month">Monthly View</option>
            <option value="week">Weekly View</option>
            <option value="list">List View</option>
          </select>
        </div>
        
        <button class="btn" id="request-modification-btn">
          <i class="fas fa-edit"></i> Request Modification
        </button>
      </div>
      
      <div class="card">
        <div class="card-body">
          <div class="calendar">
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header weekend">Fri</div>
            <div class="calendar-day-header weekend">Sat</div>
            
            <?php
            // Calculate previous month days that show in calendar
            $prev_month_days = $first_day_of_month;
            $prev_month_last_day = date('t', strtotime(date('Y-m', strtotime($year . '-' . $month . '-01')) . ' -1 month'));
            
            // Display previous month days
            for ($i = 0; $i < $prev_month_days; $i++) {
                $prev_day = $prev_month_last_day - $prev_month_days + $i + 1;
                echo '<div class="calendar-day inactive">';
                echo '<div class="calendar-day-number">' . $prev_day . '</div>';
                echo '</div>';
            }
            
            // Display current month days
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date_str = sprintf('%s-%02d-%02d', $year, $month, $day);
                $is_today = (date('Y-m-d') == $date_str) ? 'today' : '';
                $is_before_join = (strtotime($date_str) < strtotime($join_date));
                $is_join_day = ($date_str == $join_date);
                $day_of_week = date('N', strtotime($date_str));
                $is_weekend = ($day_of_week == 5 || $day_of_week == 6); // Friday or Saturday
                
                $day_class = $is_today ? 'today' : '';
                $day_class .= $is_before_join ? ' pre-join' : '';
                $day_class .= $is_weekend ? ' weekend' : '';
                $day_class .= $is_join_day ? ' join-day' : '';
                
                echo '<div class="calendar-day ' . $day_class . '" data-date="' . $date_str . '">';
                echo '<div class="calendar-day-number">' . $day . '</div>';
                
                // Add emoji for join day
                if ($is_join_day) {
                    echo '<div class="join-day-indicator">🎉</div>';
                    echo '<div class="join-day-message">Welcome!</div>';
                }
                
                // Check if date is before join date
                if ($is_before_join) {
                    echo '<div class="day-status status-pre-join">Pre-Join</div>';
                }
                // Check if we have attendance data for this day
                else if (isset($attendance_data[$date_str])) {
                    $clock_in = $attendance_data[$date_str]['clock_in'];
                    $clock_out = $attendance_data[$date_str]['clock_out'] ?? '--:--';
                    
                    // Determine status
                    $status_class = 'status-present';
                    $status_text = 'Present';
                    
                    // Check if late (after 9:05 AM)
                    if (strtotime($clock_in) > strtotime('09:05:00')) {
                        $status_class = 'status-late';
                        $status_text = 'Late';
                    }
                    
                    echo '<div class="day-status ' . $status_class . '">' . $status_text . '</div>';
                    echo '<div class="day-times">' . $clock_in . ' - ' . $clock_out . '</div>';
                } else {
                    // Check if it's a weekday and in the past
                    $is_past = strtotime($date_str) < strtotime(date('Y-m-d'));
                    
                    // Changed to consider Sunday-Thursday as workdays
                    if (!$is_weekend && $is_past) { // Not weekend and in the past
                        // Check if this is a leave day
                        $sql_check_leave = "SELECT COUNT(*) as is_leave FROM leaves 
                                            WHERE user_id = ? 
                                            AND ? BETWEEN start_date AND end_date 
                                            AND status = 'approved'";
                        $stmt = $conn->prepare($sql_check_leave);
                        $stmt->bind_param("is", $user_id, $date_str);
                        $stmt->execute();
                        $leave_check = $stmt->get_result()->fetch_assoc();
                        $is_leave = $leave_check['is_leave'] > 0;
                        
                        if ($is_leave) {
                            echo '<div class="day-status status-leave">Leave</div>';
                        } else {
                            echo '<div class="day-status status-absent">Absent</div>';
                        }
                    }
                }
                
                echo '</div>';
            }
            
            // Calculate next month days to complete the grid
            $total_days_shown = $prev_month_days + $days_in_month;
            $next_month_days = 42 - $total_days_shown; // 42 = 6 rows * 7 days
            
            // Display next month days
            for ($i = 1; $i <= $next_month_days; $i++) {
                echo '<div class="calendar-day inactive">';
                echo '<div class="calendar-day-number">' . $i . '</div>';
                echo '</div>';
            }
            ?>
          </div>
        </div>
      </div>
      
      <div class="request-modification" id="modification-form" style="display: none;">
        <div class="card">
          <div class="card-header">
            Request Attendance Modification
          </div>
          <div class="card-body">
            <form id="attendance-modification-form" method="post" action="/projectweb/submit_modification.php">
              <div class="form-group">
                <label for="modification-date">Date</label>
                <input type="date" id="modification-date" name="modification_date" required>
              </div>
              <div class="form-group">
                <label for="modification-type">Modification Type</label>
                <select id="modification-type" name="modification_type" required>
                  <option value="">-- Select Type --</option>
                  <option value="clock-in">Clock In Correction</option>
                  <option value="clock-out">Clock Out Correction</option>
                  <option value="absent">Absent to Present</option>
                </select>
              </div>
              <div class="form-group">
                <label for="modification-time">Correct Time (if applicable)</label>
                <input type="time" id="modification-time" name="modification_time">
              </div>
              <div class="form-group">
                <label for="modification-reason">Reason</label>
                <textarea id="modification-reason" name="modification_reason" rows="3" required></textarea>
              </div>
              <div class="form-group">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <button type="submit" class="btn">Submit Request</button>
                <button type="button" class="btn btn-secondary" id="cancel-modification">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      if (sidebar.classList.contains('collapsed')) {
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
    
    // Calendar day click
    const calendarDays = document.querySelectorAll('.calendar-day:not(.inactive)');
    
    calendarDays.forEach(day => {
      day.addEventListener('click', function() {
        if (!this.dataset.date) return; // Exit if no date is set

        const dateStr = this.dataset.date;
        const hasAttendance = this.querySelector('.day-status') !== null;
        const isPreJoin = this.classList.contains('pre-join');
        const isJoinDay = this.classList.contains('join-day');
        const isWeekend = this.classList.contains('weekend');

        // Handle pre-join days
        if (isPreJoin) {
            alert(`You were not yet employed on ${dateStr}`);
            return;
        }

        // Handle join day
        if (isJoinDay) {
            alert(`Welcome to the team! This was your first day with us.`);
            return;
        }

        // Handle weekend days
        if (isWeekend) {
            alert(`${dateStr} is a weekend day (Friday or Saturday).`);
            return;
        }

        // Handle days with attendance data
        if (hasAttendance) {
            const status = this.querySelector('.day-status').textContent;
            const times = this.querySelector('.day-times')?.textContent || '';

            if (status === 'Absent') {
                alert(`Attendance details for ${dateStr}\nStatus: ${status}`);
            } else {
                alert(`Attendance details for ${dateStr}\nStatus: ${status}\nTimes: ${times}`);
            }

            // Pre-fill the modification form with the selected date
            document.getElementById('modification-date').value = dateStr;
            return;
        }

        // Handle days without attendance data (non-weekend, non-pre-join)
        document.getElementById('modification-date').value = dateStr;
        document.getElementById('modification-type').value = 'absent';
        document.getElementById('modification-form').style.display = 'block';

        // Smooth scroll to the modification form
        window.scrollTo({
            top: document.getElementById('modification-form').offsetTop - 20,
            behavior: 'smooth'
        });
    });
    });
    
    // Request modification form
    const requestModificationBtn = document.getElementById('request-modification-btn');
    const modificationForm = document.getElementById('modification-form');
    const cancelModificationBtn = document.getElementById('cancel-modification');
    
    requestModificationBtn.addEventListener('click', function() {
      modificationForm.style.display = 'block';
      window.scrollTo({
        top: modificationForm.offsetTop - 20,
        behavior: 'smooth'
      });
    });
    
    cancelModificationBtn.addEventListener('click', function() {
      modificationForm.style.display = 'none';
    });
    
    // Handle view type changes
    document.getElementById('view-type').addEventListener('change', function() {
      alert('View type switching functionality will be implemented in future updates.');
    });
    
    // Date validation - prevent selecting pre-join dates and weekend dates
    const modificationDateInput = document.getElementById('modification-date');
    const joinDate = "<?php echo $join_date; ?>";
    
    modificationDateInput.addEventListener('change', function() {
        if (this.value < joinDate) {
            alert('You cannot request modifications for dates before your joining date.');
            this.value = joinDate;
            return;
        }
        
        // Check if selected date is a weekend (Friday or Saturday)
        const selectedDay = new Date(this.value).getDay();
        if (selectedDay === 5 || selectedDay === 6) { // Friday (5) or Saturday (6)
            alert('You cannot request modifications for weekend days (Friday or Saturday).');
            this.value = '';
        }
    });
    
    // Set min date attribute to join date
    modificationDateInput.setAttribute('min', joinDate);


    document.getElementById('attendance-modification-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax_modification_request', '1');
        
        fetch('/projectweb/Employee/AttendanceManagementPage.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                document.getElementById('modification-form').style.display = 'none';
                // Optional: Refresh page or update UI
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting your request');
        });
    });

  </script>
</body>
</html>