<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
}

// Helper function to get profile picture path
function getProfilePicturePath($picture, $role = 'employee', $default = 'default-profile.png') {
    // Define paths for different roles
    $upload_dirs = [
        'employee' => '/projectweb/Employee/uploads/profile_pictures/',
        'administration' => '/projectweb/Administration/uploads/profile_pictures/'
    ];
    
    // Use default path if role is not specified or invalid
    $upload_dir = $upload_dirs[$role] ?? $upload_dirs['employee'];
    
    // If picture is empty or null, use default
    if (empty($picture)) {
        return $upload_dir . $default;
    }
    
    // Return full path
    return $upload_dir . $picture;
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

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Fetch admin profile picture
$admin_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_query->bind_param("i", $user_id);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$profile_picture_path = getProfilePicturePath($admin_data['profile_picture'], 'administration');

// Get filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Base query
$sql = "SELECT lr.*, u.name as employee_name, u.department, u.id as employee_id, 
               u.annual_leave_balance, u.sick_leave_balance, u.personal_leave_balance, u.unpaid_leave_balance 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE 1=1";

// Apply filters
if ($status_filter && $status_filter != 'all') {
    $sql .= " AND lr.status = '$status_filter'";
}

if ($department_filter) {
    $sql .= " AND u.department = '$department_filter'";
}

if ($date_filter) {
    $sql .= " AND (lr.start_date >= '$date_filter' OR lr.end_date >= '$date_filter')";
}

$sql .= " ORDER BY lr.submitted_at DESC";

$result = $conn->query($sql);

// Count requests by status
$count_pending = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$count_approved = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved'")->fetch_assoc()['count'];
$count_rejected = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'rejected'")->fetch_assoc()['count'];

// Get all departments for filter dropdown
$departments_result = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''");
$departments = [];
while($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Handle approve/reject action
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_comment = $_POST['admin_comment'] ?? '';
    
    if ($action == 'approve') {
        $status = 'approved';
    } else {
        $status = 'rejected';
    }
    
    $update_sql = "UPDATE leave_requests SET status = ?, admin_comment = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssii", $status, $admin_comment, $user_id, $request_id);
    
    if ($stmt->execute()) {
        // If leave is approved, update the user's leave balance
        if ($status == 'approved') {
            // Get leave details
            $leave_sql = "SELECT lr.*, u.id as user_id, u.annual_leave_balance, u.sick_leave_balance, 
                          u.personal_leave_balance, u.unpaid_leave_balance 
                       FROM leave_requests lr
                       JOIN users u ON lr.user_id = u.id
                       WHERE lr.id = ?";
            $leave_stmt = $conn->prepare($leave_sql);
            $leave_stmt->bind_param("i", $request_id);
            $leave_stmt->execute();
            $leave_result = $leave_stmt->get_result();
            $leave_data = $leave_result->fetch_assoc();
            
            // Determine which balance to update based on leave type
            $balance_column = '';
            switch ($leave_data['leave_type']) {
                case 'Annual Leave':
                    $balance_column = 'annual_leave_balance';
                    $new_balance = $leave_data['annual_leave_balance'] - $leave_data['days'];
                    break;
                case 'Sick Leave':
                    $balance_column = 'sick_leave_balance';
                    $new_balance = $leave_data['sick_leave_balance'] - $leave_data['days'];
                    break;
                case 'Personal Leave':
                    $balance_column = 'personal_leave_balance';
                    $new_balance = $leave_data['personal_leave_balance'] - $leave_data['days'];
                    break;
                case 'Unpaid Leave':
                    $balance_column = 'unpaid_leave_balance';
                    $new_balance = $leave_data['unpaid_leave_balance'] - $leave_data['days'];
                    break;
            }
            
            // Update the balance if applicable
            if ($balance_column) {
                $update_balance_sql = "UPDATE users SET $balance_column = ? WHERE id = ?";
                $balance_stmt = $conn->prepare($update_balance_sql);
                $balance_stmt->bind_param("ii", $new_balance, $leave_data['user_id']);
                $balance_stmt->execute();
            }
            
            // Create notification for the user
            $notification_title = "Leave Request Approved";
            $notification_message = "Your {$leave_data['leave_type']} request from " . 
                                   date('M j, Y', strtotime($leave_data['start_date'])) . " to " . 
                                   date('M j, Y', strtotime($leave_data['end_date'])) . " has been approved.";
            
            $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("iss", $leave_data['user_id'], $notification_title, $notification_message);
            $notification_stmt->execute();
        } else {
            // Create rejection notification
            $leave_sql = "SELECT lr.*, u.id as user_id FROM leave_requests lr JOIN users u ON lr.user_id = u.id WHERE lr.id = ?";
            $leave_stmt = $conn->prepare($leave_sql);
            $leave_stmt->bind_param("i", $request_id);
            $leave_stmt->execute();
            $leave_result = $leave_stmt->get_result();
            $leave_data = $leave_result->fetch_assoc();
            
            $notification_title = "Leave Request Rejected";
            $notification_message = "Your {$leave_data['leave_type']} request from " . 
                                   date('M j, Y', strtotime($leave_data['start_date'])) . " to " . 
                                   date('M j, Y', strtotime($leave_data['end_date'])) . " has been rejected.";
            if ($admin_comment) {
                $notification_message .= " Reason: $admin_comment";
            }
            
            $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("iss", $leave_data['user_id'], $notification_title, $notification_message);
            $notification_stmt->execute();
        }
        
        // Redirect to refresh the page
        header("Location: AdminLeaveRequestManagementPage.php?status=$status_filter");
        exit;
    }
}

// Function to format date range
function formatDateRange($start_date, $end_date, $days) {
    return date('M j, Y', strtotime($start_date)) . ' - ' . 
           date('M j, Y', strtotime($end_date)) . ' (' . $days . ' days)';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Directory | Attendance System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
      height: calc(100vh - var(--header-height)); /* Changed from min-height to height */
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
    
    /* Employee Directory Specific */
    .search-container {
      margin-bottom: 20px;
      display: flex;
    }
    
    .search-container input {
      flex-grow: 1;
      margin-right: 10px;
    }
    
    .employee-card {
      display: flex;
      align-items: center;
      padding: 15px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .employee-card:last-child {
      border-bottom: none;
    }
    
    .employee-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background-color: var(--light-bg);
      margin-right: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: var(--primary-color);
    }
    
    .employee-avatar img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    }
    
    .employee-info {
      flex-grow: 1;
    }
    
    .employee-name {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .employee-position {
      color: #777;
      font-size: 0.9rem;
    }
    
    .employee-actions {
      display: flex;
      gap: 10px;
    }
    
    /* Responsive adjustments */
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
      
      .employee-actions {
        flex-direction: column;
      }
      
      .employee-card {
        flex-direction: column;
        text-align: center;
      }
      
      .employee-avatar {
        margin-right: 0;
        margin-bottom: 10px;
      }
    }
    
    /* New styles for tabs */
    .tabs {
      display: flex;
      margin-bottom: 20px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .tabs .tab {
      padding: 10px 15px;
      text-decoration: none;
      color: var(--dark-text);
      border-bottom: 2px solid transparent;
      transition: all 0.3s ease;
    }
    
    .tabs .tab.active {
      color: var(--primary-color);
      border-bottom-color: var(--primary-color);
      font-weight: bold;
    }
    
    .tabs .tab:hover {
      color: var(--primary-color);
    }
    
    /* Modal styles */
    .request-details {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
    
    .request-details.active {
      display: flex;
    }
    
    .request-details .card {
      max-width: 800px;
      width: 90%;
      max-height: 90%;
      overflow-y: auto;
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Leave Request Management</div>
    <div class="user-profile" id="userProfileBtn">
      <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
      <span><?php echo htmlspecialchars($user_name); ?></span>
      <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
    </div>
    <div class="user-dropdown" id="userDropdown">
      <ul>
        <li><a href="#"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="/projectweb/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </div>
  </header>
  
  <div class="layout">
    <aside class="sidebar" id="sidebar">
      <button class="toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
      </button>
      <ul>
        <li><a href="./EmployeeDirectoryPage.php"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
        <li><a href="./AdminLeaveRequestManagementPage.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
        <li><a href="./AttendanceModificationManagementPage.php"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.php"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-title">
        <h1>Leave Request Management</h1>
      </div>
      
      <div class="card mb-20">
        <div class="card-header">
          <div>Leave Request Filters</div>
        </div>
        <div class="card-body">
          <form action="" method="GET">
            <div class="grid">
              <div class="col-4">
                <div class="form-group">
                  <label>Status</label>
                  <select name="status">
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                  </select>
                </div>
              </div>
              <div class="col-4">
                <div class="form-group">
                  <label>Department</label>
                  <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                      <option value="<?php echo $dept; ?>" <?php echo $department_filter == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-4">
                <div class="form-group">
                  <label>Date Range</label>
                  <input type="date" name="date_range" value="<?php echo $date_filter; ?>">
                </div>
              </div>
            </div>
            <button type="submit" class="btn mt-10">Apply Filters</button>
          </form>
        </div>
      </div>
      
      <div class="tabs">
        <a href="?status=pending" class="tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" data-tab="pending">Pending (<?php echo $count_pending; ?>)</a>
        <a href="?status=approved" class="tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>" data-tab="approved">Approved (<?php echo $count_approved; ?>)</a>
        <a href="?status=rejected" class="tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>" data-tab="rejected">Rejected (<?php echo $count_rejected; ?>)</a>
      </div>
      
      <div class="card">
        <div class="card-header">
          <div>
            <?php 
              if ($status_filter == 'approved') echo 'Approved Leave Requests';
              elseif ($status_filter == 'rejected') echo 'Rejected Leave Requests';
              else echo 'Pending Leave Requests';
            ?>
          </div>
        </div>
        <div class="card-body">
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Duration</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                    <td><?php echo formatDateRange($row['start_date'], $row['end_date'], $row['days']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($row['submitted_at'])); ?></td>
                    <td>
                      <span class="badge badge-<?php 
                        echo $row['status'] == 'approved' ? 'success' : 
                             ($row['status'] == 'rejected' ? 'danger' : 'warning'); 
                      ?>">
                        <?php echo ucfirst($row['status']); ?>
                      </span>
                    </td>
                    <td>
                      <button class="btn btn-secondary" onclick="showRequestDetails(
                        <?php echo $row['id']; ?>, 
                        '<?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES); ?>', 
                        '<?php echo htmlspecialchars($row['employee_id'], ENT_QUOTES); ?>', 
                        '<?php echo htmlspecialchars($row['department'] ?? 'Not specified', ENT_QUOTES); ?>', 
                        '<?php echo htmlspecialchars($row['leave_type'], ENT_QUOTES); ?>', 
                        '<?php echo formatDateRange($row['start_date'], $row['end_date'], $row['days']); ?>', 
                        '<?php echo date('M j, Y', strtotime($row['submitted_at'])); ?>', 
                        '<?php echo $row['status']; ?>', 
                        '<?php echo htmlspecialchars($row['reason'] ?? 'No reason provided', ENT_QUOTES); ?>', 
                        'Annual: <?php echo $row['annual_leave_balance']; ?> days / Sick: <?php echo $row['sick_leave_balance']; ?> days / Personal: <?php echo $row['personal_leave_balance']; ?> days'
                      )">
                        <?php echo $row['status'] == 'pending' ? 'Review' : 'View Details'; ?>
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center">No leave requests found</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Leave Request Details Modal -->
      <div id="requestDetailsModal" class="request-details">
        <div class="card">
          <div class="card-header">
            <div>Leave Request Details</div>
            <button class="btn" onclick="closeRequestDetails()">Close</button>
          </div>
          <div class="card-body">
            <form id="leaveActionForm" method="POST" action="">
              <input type="hidden" id="request_id" name="request_id" value="">
              <input type="hidden" id="action" name="action" value="">
              
              <div class="grid">
                <div class="col-6">
                  <div class="form-group">
                    <label>Employee Name</label>
                    <div id="employeeName"></div>
                  </div>
                  <div class="form-group">
                    <label>Employee ID</label>
                    <div id="employeeId"></div>
                  </div>
                  <div class="form-group">
                    <label>Department</label>
                    <div id="department"></div>
                  </div>
                  <div class="form-group">
                    <label>Leave Type</label>
                    <div id="leaveType"></div>
                  </div>
                  <div class="form-group">
                    <label>Duration</label>
                    <div id="leaveDuration"></div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Request Date</label>
                    <div id="requestDate"></div>
                  </div>
                  <div class="form-group">
                    <label>Status</label>
                    <div id="leaveStatus"></div>
                  </div>
                  <div class="form-group">
                    <label>Reason</label>
                    <div id="leaveReason"></div>
                  </div>
                  <div class="form-group">
                    <label>Leave Balance</label>
                    <div id="leaveBalance"></div>
                  </div>
                </div>
              </div>
              
              <div id="actionSection" style="display: none;">
                <div class="form-group">
                  <label>Your Decision</label>
                  <select id="leaveDecision" onchange="updateActionButton()">
                    <option value="">Select</option>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Comments (optional)</label>
                  <textarea name="admin_comment" rows="3" placeholder="Add your comments here..."></textarea>
                </div>
                
                <div class="text-right">
                  <button type="button" id="rejectBtn" class="btn btn-danger mr-10" style="display: none;" onclick="submitAction('reject')">Reject</button>
                  <button type="button" id="approveBtn" class="btn btn-secondary" style="display: none;" onclick="submitAction('approve')">Approve</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    // Toggle sidebar
    document.getElementById('toggleSidebar').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.querySelector('.main-content').style.marginLeft = 
        document.getElementById('sidebar').classList.contains('collapsed') ? 
        'var(--sidebar-collapsed)' : 'var(--sidebar-width)';
    });
    
    // User dropdown
    document.getElementById('userProfileBtn').addEventListener('click', function() {
      document.getElementById('userDropdown').classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
      if (!event.target.matches('#userProfileBtn') && 
          !event.target.closest('#userProfileBtn') && 
          !event.target.matches('#userDropdown') && 
          !event.target.closest('#userDropdown')) {
        document.getElementById('userDropdown').classList.remove('show');
      }
    });
    
    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
      const mediaQuery = window.matchMedia('(max-width: 768px)');
      function handleScreenChange(e) {
        if (e.matches) {
          document.getElementById('sidebar').classList.add('collapsed');
          document.querySelector('.main-content').style.marginLeft = '0';
        } else {
          document.getElementById('sidebar').classList.remove('collapsed');
          document.querySelector('.main-content').style.marginLeft = 'var(--sidebar-width)';
        }
      }
      mediaQuery.addListener(handleScreenChange);
      handleScreenChange(mediaQuery);
    });
    
    // Show request details
    function showRequestDetails(id, name, empId, dept, leaveType, duration, requestDate, status, reason, balance) {
      document.getElementById('request_id').value = id;
      document.getElementById('employeeName').textContent = name;
      document.getElementById('employeeId').textContent = empId;
      document.getElementById('department').textContent = dept;
      document.getElementById('leaveType').textContent = leaveType;
      document.getElementById('leaveDuration').textContent = duration;
      document.getElementById('requestDate').textContent = requestDate;
      
      let statusHtml = '';
      if (status === 'approved') {
        statusHtml = '<span class="badge badge-success">Approved</span>';
      } else if (status === 'rejected') {
        statusHtml = '<span class="badge badge-danger">Rejected</span>';
      } else {
        statusHtml = '<span class="badge badge-warning">Pending</span>';
      }
      document.getElementById('leaveStatus').innerHTML = statusHtml;
      
      document.getElementById('leaveReason').textContent = reason;
      document.getElementById('leaveBalance').textContent = balance;
      
      // Reset decision dropdown
      document.getElementById('leaveDecision').selectedIndex = 0;
      updateActionButton();
      
      // Show/hide action section based on status
      if (status === 'pending') {
        document.getElementById('actionSection').style.display = 'block';
      } else {
        document.getElementById('actionSection').style.display = 'none';
      }
      
      document.getElementById('requestDetailsModal').style.display = 'flex';
    }
    
    // Updated closeRequestDetails function
    function closeRequestDetails() {
      document.getElementById('requestDetailsModal').style.display = 'none';
    }
    
    // Updated updateActionButton function
    function updateActionButton() {
      const decision = document.getElementById('leaveDecision').value;
      const rejectBtn = document.getElementById('rejectBtn');
      const approveBtn = document.getElementById('approveBtn');
      
      rejectBtn.style.display = decision === 'reject' ? 'inline-block' : 'none';
      approveBtn.style.display = decision === 'approve' ? 'inline-block' : 'none';
      
      // Disable submission if no decision is made
      if (decision === '') {
        rejectBtn.disabled = true;
        approveBtn.disabled = true;
      } else {
        rejectBtn.disabled = false;
        approveBtn.disabled = false;
      }
    }
    
    // Add event listener to decision dropdown
    document.getElementById('leaveDecision').addEventListener('change', updateActionButton);
    
    // Initialize action buttons state
    document.addEventListener('DOMContentLoaded', function() {
      updateActionButton();
    });
  </script>
</body>
</html>