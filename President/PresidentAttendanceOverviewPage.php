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

// Get overall attendance statistics for the last 30 days
$sql_overall_attendance = "
    SELECT 
        COUNT(DISTINCT user_id) AS total_employees,
        COUNT(CASE WHEN clock_out IS NOT NULL THEN 1 END) AS total_present,
        COUNT(*) AS total_attendance_records
    FROM users_attendance 
    WHERE DATE(clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$result_attendance = $conn->query($sql_overall_attendance);
$attendance_stats = $result_attendance->fetch_assoc();
$overall_attendance_rate = ($attendance_stats['total_attendance_records'] > 0) 
    ? round(($attendance_stats['total_present'] / $attendance_stats['total_attendance_records']) * 100, 1) 
    : 0;

// Get late arrivals in the last 30 days
$sql_late_arrivals = "
    SELECT COUNT(DISTINCT user_id) AS late_count
    FROM users_attendance
    WHERE DATE(clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND TIME(clock_in) > '09:00:00'
";
$result_late_arrivals = $conn->query($sql_late_arrivals);
$late_arrivals = $result_late_arrivals->fetch_assoc()['late_count'];

// Get unplanned absences
$sql_unplanned_absences = "
    SELECT COUNT(DISTINCT u.id) AS absence_count
    FROM users u
    LEFT JOIN users_attendance ua ON u.id = ua.user_id AND DATE(ua.clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE ua.id IS NULL
";
$result_unplanned_absences = $conn->query($sql_unplanned_absences);
$unplanned_absences = $result_unplanned_absences->fetch_assoc()['absence_count'];

// Get modification requests
$sql_modification_requests = "
    SELECT COUNT(*) AS modification_count
    FROM attendance_modifications
    WHERE status = 'pending'
";
$result_modification_requests = $conn->query($sql_modification_requests);
$modification_requests = $result_modification_requests->fetch_assoc()['modification_count'];

// Get top late employees
$sql_late_employees = "
    SELECT 
        u.name, 
        u.department, 
        COUNT(*) AS late_count,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, CONCAT(DATE(clock_in), ' 09:00:00'), clock_in)), 1) AS avg_delay
    FROM users_attendance ua
    JOIN users u ON ua.user_id = u.id
    WHERE DATE(clock_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND TIME(clock_in) > '09:00:00'
    GROUP BY u.id
    ORDER BY late_count DESC
    LIMIT 4
";
$result_late_employees = $conn->query($sql_late_employees);

// Get attendance modification requests
$sql_attendance_mods = "
    SELECT 
        am.id,
        u.name, 
        am.modification_date, 
        am.modification_type,
        am.status
    FROM attendance_modifications am
    JOIN users u ON am.user_id = u.id
    WHERE am.status = 'pending'
    ORDER BY am.modification_date DESC
    LIMIT 5
";
$result_attendance_mods = $conn->query($sql_attendance_mods);

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
  <title>Attendance System - President Attendance Overview</title>
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
    
    /* Tab navigation */
    .tab-nav {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }
    
    .tab-nav button {
      padding: 10px 20px;
      background: none;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
    }
    
    .tab-nav button.active {
      border-bottom: 3px solid var(--primary-color);
      color: var(--primary-color);
    }
    
    /* Exception report styles */
    .exception-item {
      padding: 15px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
    }
    
    .exception-item:last-child {
      border-bottom: none;
    }
    
    .exception-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: var(--light-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
      color: var(--primary-color);
    }
    
    .exception-icon.warning {
      color: var(--warning-color);
    }
    
    .exception-icon.danger {
      color: var(--danger-color);
    }
    
    .exception-content {
      flex: 1;
    }
    
    .exception-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .exception-meta {
      font-size: 0.8rem;
      color: #777;
    }
    
    .exception-actions {
      margin-left: 10px;
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
      
      .tab-nav {
        overflow-x: auto;
      }
    }
    /* Button Styles */
    .btn {
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.1s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .btn:hover {
        opacity: 0.9;
    }

    .btn:active {
        transform: scale(0.98);
    }

    .btn-secondary {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-secondary:hover {
        background-color: var(--primary-dark);
    }

    .btn-danger {
        background-color: var(--danger-color);
        color: white;
    }

    .btn-danger:hover {
        background-color: #c0392b;
    }

    /* Enhanced Typography */
    body {
        line-height: 1.6;
        font-size: 16px;
    }

    h1, h2, h3 {
        margin-bottom: 15px;
    }

    /* Form Elements */
    select, input {
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 8px 12px;
        width: 100%;
        transition: border-color 0.3s;
    }

    select:focus, input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    }

    /* Responsive Typography */
    @media (max-width: 768px) {
        body {
            font-size: 14px;
        }

        h1 {
            font-size: 1.5rem;
        }

        h2 {
            font-size: 1.3rem;
        }
    }

    /* Additional Hover Effects */
    .tab-nav button:hover {
        background-color: rgba(0,0,0,0.05);
    }

    /* Print Styles */
    @media print {
        .sidebar, header .user-menu, .card-header .btn {
            display: none;
        }

        body {
            background: white;
        }

        .main-content {
            margin-left: 0 !important;
        }
    }
  </style>
</head>
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
        <li><a href="./PresidentAttendanceOverviewPage.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
      </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="page-title">
        <h1>Attendance Overview</h1>
      </div>
      
      <!-- Department Statistics -->
      <div class="grid mb-20">
        <div class="col-3">
          <div class="card">
            <div class="card-body text-center">
              <h2 style="font-size: 2.5rem; color: var(--primary-color);"><?php echo $overall_attendance_rate; ?>%</h2>
              <p>Overall Attendance Rate</p>
              <small class="text-success"><i class="fas fa-arrow-up"></i> 1.3% from last month</small>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card">
            <div class="card-body text-center">
              <h2 style="font-size: 2.5rem; color: var(--warning-color);"><?php echo $late_arrivals; ?></h2>
              <p>Late Arrivals</p>
              <small class="text-danger"><i class="fas fa-arrow-up"></i> 0.5% from last month</small>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card">
            <div class="card-body text-center">
              <h2 style="font-size: 2.5rem; color: var(--danger-color);"><?php echo $unplanned_absences; ?></h2>
              <p>Unplanned Absences</p>
              <small class="text-success"><i class="fas fa-arrow-down"></i> 0.8% from last month</small>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card">
            <div class="card-body text-center">
              <h2 style="font-size: 2.5rem; color: var(--secondary-color);"><?php echo $modification_requests; ?></h2>
              <p>Modification Requests</p>
              <small>8 pending approval</small>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Attendance by Department Chart -->
      <div class="card mb-20">
        <div class="card-header">
          Attendance Rate by Department
        </div>
        <div class="card-body">
          <div class="chart-container">
            <!-- TODO: Implement actual chart -->
            <img src="/api/placeholder/1000/300" alt="Attendance by Department Chart" style="max-width: 100%;">
          </div>
        </div>
      </div>
      
      <!-- Exception Reports and Modification Requests -->
      <div class="grid">
        <div class="col-6">
          <div class="card">
            <div class="card-header">
              Exception Reports
              <div>
                <button class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                  <i class="fas fa-filter"></i> Filter
                </button>
              </div>
            </div>
            <div class="card-body" style="padding: 0;">
              <div class="tab-nav">
                <button class="active">Chronic Lateness</button>
                <button>Frequent Absences</button>
                <button>Pattern Issues</button>
              </div>
              
              <div class="exception-list">
                <?php 
                if ($result_late_employees->num_rows > 0) {
                    while ($employee = $result_late_employees->fetch_assoc()) {
                        $severity = $employee['late_count'] > 5 ? 'danger' : 'warning';
                ?>
                <div class="exception-item">
                  <div class="exception-icon <?php echo $severity; ?>">
                    <i class="fas fa-<?php echo $severity === 'danger' ? 'exclamation-triangle' : 'exclamation-circle'; ?>"></i>
                  </div>
                  <div class="exception-content">
                    <div class="exception-title"><?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['department']); ?>)</div>
                    <div class="exception-description">Late <?php echo $employee['late_count']; ?> times in the last 30 days</div>
                    <div class="exception-meta">Average delay: <?php echo $employee['avg_delay']; ?> minutes</div>
                  </div>
                  <div class="exception-actions">
                    <button class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                      <i class="fas fa-eye"></i> Details
                    </button>
                  </div>
                </div>
                <?php 
                    }
                } else {
                    echo '<p class="text-center">No late employees found.</p>';
                }
                ?>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-6">
          <div class="card">
            <div class="card-header">
              Attendance Modification Requests
              <div>
                <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
                  <i class="fas fa-check"></i> Approve All
                </button>
              </div>
            </div>
            <div class="card-body" style="padding: 0;">
              <table>
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Request Type</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  if ($result_attendance_mods->num_rows > 0) {
                      while ($mod = $result_attendance_mods->fetch_assoc()) {
                  ?>
                  <tr data-modification-id="<?php echo $mod['id']; ?>">
                      <td><?php echo htmlspecialchars($mod['name']); ?></td>
                      <td><?php echo date('M d, Y', strtotime($mod['modification_date'])); ?></td>
                      <td><?php echo htmlspecialchars($mod['modification_type']); ?></td>
                      <td><span class="badge badge-warning"><?php echo htmlspecialchars($mod['status']); ?></span></td>
                      <td>
                          <button class="btn btn-secondary approve-btn" data-id="<?php echo $mod['id']; ?>">
                              <i class="fas fa-check"></i>
                          </button>
                          <button class="btn btn-danger reject-btn" data-id="<?php echo $mod['id']; ?>">
                              <i class="fas fa-times"></i>
                          </button>
                      </td>
                  </tr>
                  <?php 
                      }
                  } else {
                      echo '<tr><td colspan="5" class="text-center">No modification requests found.</td></tr>';
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Monthly Trend -->
      <div class="card mt-20">
        <div class="card-header">
          Monthly Attendance Trends
          <div>
            <select style="padding: 5px; border-radius: 4px; border: 1px solid var(--border-color);">
              <option>Last 6 Months</option>
              <option>Last 12 Months</option>
              <option>Year to Date</option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <!-- TODO: Implement actual chart -->
            <img src="/api/placeholder/1000/300" alt="Monthly Attendance Trends Chart" style="max-width: 100%;">
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
    
    // Tab navigation
    document.querySelectorAll('.tab-nav button').forEach(button => {
      button.addEventListener('click', function() {
        document.querySelectorAll('.tab-nav button').forEach(btn => {
          btn.classList.remove('active');
        });
        this.classList.add('active');
      });
    });

    document.addEventListener('DOMContentLoaded', function() {
    function handleModificationRequest(modificationId, action) {
        // Validate modification ID
        if (!modificationId || modificationId === '0') {
            console.error('Invalid modification ID:', modificationId);
            alert('Error: Invalid modification request');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('modification_id', modificationId);
        formData.append('action', action);

        // Send AJAX request
        fetch('./handle_modification_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-modification-id="${modificationId}"]`);
                if (row) {
                    // Update status badge
                    const statusCell = row.querySelector('.badge');
                    if (statusCell) {
                        statusCell.textContent = data.status;
                        statusCell.className = `badge badge-${action === 'approve' ? 'success' : 'danger'}`;
                    }
                    
                    // Remove action buttons
                    row.querySelector('.approve-btn').remove();
                    row.querySelector('.reject-btn').remove();
                }

                alert(data.message);
            } else {
                console.error('Server error response:', data);
                alert('Error: ' + (data.message || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred');
        });
    }

    // Event delegation for dynamic buttons
    document.querySelector('.main-content').addEventListener('click', function(event) {
        const approveBtn = event.target.closest('.approve-btn');
        const rejectBtn = event.target.closest('.reject-btn');

        if (approveBtn) {
            const modificationId = approveBtn.getAttribute('data-id');
            handleModificationRequest(modificationId, 'approve');
        }

        if (rejectBtn) {
            const modificationId = rejectBtn.getAttribute('data-id');
            handleModificationRequest(modificationId, 'reject');
        }
    });
});
  </script>
</body>
</html>