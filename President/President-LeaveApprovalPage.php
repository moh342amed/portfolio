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

// Get president's name
$user_id = $_SESSION['user_id'];
$president_name = $_SESSION['name'];

// Get pending leave requests with user details
$pending_sql = "SELECT lr.*, u.name as employee_name, u.department 
                FROM leave_requests lr 
                JOIN users u ON lr.user_id = u.id 
                WHERE lr.status = 'pending' 
                ORDER BY lr.submitted_at DESC";
$pending_result = $conn->query($pending_sql);

// Get extended leave requests (leave requests more than 14 days)
$extended_sql = "SELECT lr.*, u.name as employee_name, u.department 
                FROM leave_requests lr 
                JOIN users u ON lr.user_id = u.id 
                WHERE DATEDIFF(lr.end_date, lr.start_date) > 14 
                ORDER BY lr.submitted_at DESC";
$extended_result = $conn->query($extended_sql);

// Get recently approved leave requests
$approved_sql = "SELECT lr.*, u.name as employee_name, u.department 
                FROM leave_requests lr 
                JOIN users u ON lr.user_id = u.id 
                WHERE lr.status = 'approved' 
                ORDER BY lr.submitted_at DESC LIMIT 15";
$approved_result = $conn->query($approved_sql);

// Get rejected leave requests
$rejected_sql = "SELECT lr.*, u.name as employee_name, u.department 
                FROM leave_requests lr 
                JOIN users u ON lr.user_id = u.id 
                WHERE lr.status = 'rejected' 
                ORDER BY lr.submitted_at DESC LIMIT 15";
$rejected_result = $conn->query($rejected_sql);

// Handle leave request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_comment = isset($_POST['comment']) ? $_POST['comment'] : '';
    
    if ($action === 'approve') {
        $update_sql = "UPDATE leave_requests SET status = 'approved' WHERE id = ?";
    } elseif ($action === 'reject') {
        $update_sql = "UPDATE leave_requests SET status = 'rejected' WHERE id = ?";
    }
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $request_id);
    
    if ($stmt->execute()) {
        // Redirect to refresh the page
        header('Location: President-LeaveApprovalPage.php?status=success');
        exit;
    } else {
        $error_message = "Failed to update request";
    }
}

// Get details of a specific leave request for the modal
$request_details = null;
if (isset($_GET['request_id'])) {
    $request_id = $_GET['request_id'];
    $details_sql = "SELECT lr.*, u.name as employee_name, u.id as employee_id, 
                   u.department, u.annual_leave_balance, u.sick_leave_balance, 
                   u.personal_leave_balance, u.unpaid_leave_balance 
                   FROM leave_requests lr 
                   JOIN users u ON lr.user_id = u.id 
                   WHERE lr.id = ?";
    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $request_details = $result->fetch_assoc();
    }
}

// Count pending requests
$count_pending = $pending_result->num_rows;
$count_extended = $extended_result->num_rows;
$count_approved = $approved_result->num_rows;
$count_rejected = $rejected_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave Approval - President</title>
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Common styles from the AttendanceSystemCommonStyles.html -->
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

    /* Additional Custom Styles for Leave Approval */
    .leave-details {
      background-color: rgba(52, 152, 219, 0.05);
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
    }

    .leave-details-row {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }

    .leave-detail-item {
      flex: 1;
      min-width: 200px;
      margin-bottom: 10px;
    }

    .leave-detail-label {
      font-weight: bold;
      margin-bottom: 3px;
      color: var(--dark-text);
    }

    .approval-history {
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px dashed var(--border-color);
    }

    .approval-step {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }

    .approval-step-icon {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background-color: var(--secondary-color);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 10px;
    }

    .approval-step-icon.pending {
      background-color: var(--warning-color);
    }

    .approval-step-content {
      flex: 1;
    }

    .tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }

    .tab {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }

    .tab.active {
      border-bottom-color: var(--primary-color);
      font-weight: bold;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .priority-badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 0.7rem;
      margin-left: 10px;
    }

    .priority-high {
      background-color: var(--danger-color);
      color: white;
    }

    .priority-medium {
      background-color: var(--warning-color);
      color: white;
    }

    .action-buttons {
      margin-top: 15px;
      display: flex;
      gap: 10px;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .leave-details-row {
        flex-direction: column;
      }
      
      .leave-detail-item {
        width: 100%;
      }
      
      .action-buttons {
        flex-direction: column;
      }
      
      .action-buttons .btn {
        width: 100%;
        margin-bottom: 10px;
      }
    }
    /* Tab Content Styling */
    .tab-content {
      display: none;
      opacity: 0;
      transform: translateY(10px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .tab-content.active {
      display: block;
      opacity: 1;
      transform: translateY(0);
      animation: fadeIn 0.3s ease forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card-body {
      padding: 20px;
      background-color: white;
      border-radius: 0 0 4px 4px;
      position: relative;
    }

    .card-body table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-bottom: 0;
    }

    .card-body table thead {
      background-color: var(--light-bg);
    }

    .card-body table th {
      padding: 12px 15px;
      text-align: left;
      font-weight: 600;
      color: var(--dark-text);
      border-bottom: 1px solid var(--border-color);
    }

    .card-body table td {
      padding: 12px 15px;
      vertical-align: middle;
      border-bottom: 1px solid var(--border-color);
    }

    .card-body table tr:last-child td {
      border-bottom: none;
    }

    .card-body table tr:hover {
      background-color: rgba(46, 204, 113, 0.05);
      transition: background-color 0.2s ease;
    }

    .card-body .text-center {
      text-align: center;
    }

    .card-body .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 0.9rem;
      transition: all 0.2s ease;
    }

    .card-body .btn:hover {
      opacity: 0.8;
      transform: translateY(-2px);
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <!-- Header -->
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
        <li><a href="./President-LeaveApprovalPage.php" class="active"><i class="fas fa-calendar-check"></i> <span>Leave Approval</span></a></li>
        <li><a href="./PresidentReportsReviewPage.php"><i class="fas fa-file-signature"></i> <span>Reports Review</span></a></li>
        <li><a href="./PresidentAttendanceOverviewPage.php"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
      </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="page-title">
        <h1>Leave Approval</h1>
      </div>
      
      <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
      <div class="alert alert-success">
        Leave request has been updated successfully!
      </div>
      <?php endif; ?>
      
      <!-- Tabs -->
      <div class="tabs">
        <div class="tab active" data-tab="pending">Pending Approval (<?php echo $count_pending; ?>)</div>
        <div class="tab" data-tab="extended">Extended Leaves (<?php echo $count_extended; ?>)</div>
        <div class="tab" data-tab="approved">Recently Approved (<?php echo $count_approved; ?>)</div>
        <div class="tab" data-tab="rejected">Rejected (<?php echo $count_rejected; ?>)</div>
      </div>
      
      <!-- Pending Approvals Tab -->
      <div class="tab-content active" id="pending-tab">
        <div class="card mb-20">
          <div class="card-header">
            Pending Leave Requests
            <div>
              <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
                <i class="fas fa-filter"></i> Filter
              </button>
              <button class="btn" style="padding: 5px 10px; font-size: 0.8rem; margin-left: 10px;">
                <i class="fas fa-sort"></i> Sort
              </button>
            </div>
          </div>
          <div class="card-body">
            <table>
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Leave Type</th>
                  <th>Duration</th>
                  <th>Status</th>
                  <th>Priority</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($pending_result->num_rows > 0): ?>
                  <?php while($row = $pending_result->fetch_assoc()): ?>
                    <?php 
                      // Calculate duration
                      $start_date = new DateTime($row['start_date']);
                      $end_date = new DateTime($row['end_date']);
                      $interval = $start_date->diff($end_date);
                      $days = $interval->days + 1; // Include both start and end dates
                      
                      // Determine priority based on leave type and duration
                      $priority = 'Medium';
                      if ($row['leave_type'] == 'Medical Leave' || $days > 14) {
                          $priority = 'High';
                      } elseif ($days <= 3) {
                          $priority = 'Low';
                      }
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                      <td><?php echo htmlspecialchars($row['department']); ?></td>
                      <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                      <td><?php echo $days; ?> days (<?php echo date('M d', strtotime($row['start_date'])); ?>-<?php echo date('d, Y', strtotime($row['end_date'])); ?>)</td>
                      <td><span class="badge badge-warning">Pending Final Approval</span></td>
                      <td><span class="priority-badge priority-<?php echo strtolower($priority); ?>"><?php echo $priority; ?></span></td>
                      <td>
                        <a href="President-LeaveApprovalPage.php?request_id=<?php echo $row['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                          Review
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center">No pending leave requests found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Leave Request Details (Shown when request_id is in URL) -->
        <?php if($request_details): ?>
        <div class="card mb-20" id="leave-details">
          <div class="card-header">
            Leave Request Details
            <a href="President-LeaveApprovalPage.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
              <i class="fas fa-times"></i> Close
            </a>
          </div>
          <div class="card-body">
            <div class="leave-details">
              <div class="leave-details-row">
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Employee</div>
                  <div><?php echo htmlspecialchars($request_details['employee_name']); ?> (ID: EMP-<?php echo $request_details['employee_id']; ?>)</div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Position</div>
                  <div><?php echo isset($request_details['position']) ? htmlspecialchars($request_details['position']) : 'Employee'; ?></div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Department</div>
                  <div><?php echo htmlspecialchars($request_details['department']); ?></div>
                </div>
              </div>
              
              <div class="leave-details-row">
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Leave Type</div>
                  <div><?php echo htmlspecialchars($request_details['leave_type']); ?></div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Duration</div>
                  <?php 
                    $start_date = new DateTime($request_details['start_date']);
                    $end_date = new DateTime($request_details['end_date']);
                    $interval = $start_date->diff($end_date);
                    $days = $interval->days + 1; // Include both start and end dates
                  ?>
                  <div><?php echo $days; ?> days (<?php echo date('F j', strtotime($request_details['start_date'])); ?>-<?php echo date('j, Y', strtotime($request_details['end_date'])); ?>)</div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Status</div>
                  <div><span class="badge badge-warning">Pending Final Approval</span></div>
                </div>
              </div>
              
              <div class="leave-details-row">
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Supporting Documents</div>
                  <div>
                    <?php if (!empty($request_details['attachment'])): ?>
                      <a href="../uploads/<?php echo $request_details['attachment']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;" target="_blank">
                        <i class="fas fa-file"></i> View Attachment
                      </a>
                    <?php else: ?>
                      <span>No attachments provided</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Leave Balance</div>
                  <?php
                    $leave_type = strtolower($request_details['leave_type']);
                    $balance = '';
                    
                    if (strpos($leave_type, 'annual') !== false) {
                        $balance = "Annual: " . $request_details['annual_leave_balance'] . " days remaining";
                    } elseif (strpos($leave_type, 'sick') !== false) {
                        $balance = "Sick: " . $request_details['sick_leave_balance'] . " days remaining";
                    } elseif (strpos($leave_type, 'personal') !== false) {
                        $balance = "Personal: " . $request_details['personal_leave_balance'] . " days remaining";
                    } else {
                        $balance = "Unpaid: " . $request_details['unpaid_leave_balance'] . " days remaining";
                    }
                  ?>
                  <div><?php echo $balance; ?></div>
                </div>
                <div class="leave-detail-item">
                  <div class="leave-detail-label">Request Date</div>
                  <div><?php echo date('F j, Y', strtotime($request_details['submitted_at'])); ?></div>
                </div>
              </div>
              
              <div class="leave-details-row">
                <div class="leave-detail-item" style="flex: 3;">
                  <div class="leave-detail-label">Reason for Leave</div>
                  <div>
                    <?php echo nl2br(htmlspecialchars($request_details['reason'])); ?>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="approval-history">
              <h3>Approval History</h3>
              
              <!-- This would need to come from a separate approvals table in your database -->
              <div class="approval-step">
                <div class="approval-step-icon">
                  <i class="fas fa-check"></i>
                </div>
                <div class="approval-step-content">
                  <strong>Initial Submission</strong>
                  <div>Submitted on <?php echo date('F j, Y', strtotime($request_details['submitted_at'])); ?></div>
                </div>
              </div>
              
              <div class="approval-step">
                <div class="approval-step-icon pending">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="approval-step-content">
                  <strong>President's Final Approval</strong> - <?php echo $president_name; ?>
                  <div>Pending</div>
                </div>
              </div>
            </div>
            
            <form method="POST" action="President-LeaveApprovalPage.php">
              <input type="hidden" name="request_id" value="<?php echo $request_details['id']; ?>">
              
              <div class="form-group mt-20">
                <label for="president-notes">Notes/Comments</label>
                <textarea id="president-notes" name="comment" rows="3" placeholder="Add your notes or comments about this leave request..."></textarea>
              </div>
              
              <div class="action-buttons">
                <button type="submit" name="action" value="approve" class="btn btn-secondary">Approve Leave Request</button>
                <button type="submit" name="action" value="request_info" class="btn btn-warning">Request Additional Information</button>
                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject Leave Request</button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Extended Leaves Tab -->
      <div class="tab-content" id="extended-tab">
        <div class="card">
          <div class="card-header">Extended Leave Requests</div>
          <div class="card-body">
            <table>
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Leave Type</th>
                  <th>Duration</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($extended_result->num_rows > 0): ?>
                  <?php while($row = $extended_result->fetch_assoc()): ?>
                    <?php 
                      // Calculate duration
                      $start_date = new DateTime($row['start_date']);
                      $end_date = new DateTime($row['end_date']);
                      $interval = $start_date->diff($end_date);
                      $days = $interval->days + 1; // Include both start and end dates
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                      <td><?php echo htmlspecialchars($row['department']); ?></td>
                      <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                      <td><?php echo $days; ?> days (<?php echo date('M d', strtotime($row['start_date'])); ?>-<?php echo date('d, Y', strtotime($row['end_date'])); ?>)</td>
                      <td><span class="badge badge-<?php echo $row['status'] == 'approved' ? 'success' : ($row['status'] == 'rejected' ? 'danger' : 'warning'); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                      <td>
                        <a href="President-LeaveApprovalPage.php?request_id=<?php echo $row['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                          Review
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center">No extended leave requests found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- Approved Tab -->
      <div class="tab-content" id="approved-tab">
        <div class="card">
          <div class="card-header">Recently Approved Requests</div>
          <div class="card-body">
            <table>
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Leave Type</th>
                  <th>Duration</th>
                  <th>Approved Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($approved_result->num_rows > 0): ?>
                  <?php while($row = $approved_result->fetch_assoc()): ?>
                    <?php 
                      // Calculate duration
                      $start_date = new DateTime($row['start_date']);
                      $end_date = new DateTime($row['end_date']);
                      $interval = $start_date->diff($end_date);
                      $days = $interval->days + 1; // Include both start and end dates
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                      <td><?php echo htmlspecialchars($row['department']); ?></td>
                      <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                      <td><?php echo $days; ?> days (<?php echo date('M d', strtotime($row['start_date'])); ?>-<?php echo date('d, Y', strtotime($row['end_date'])); ?>)</td>
                      <td><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
                      <td>
                        <a href="President-LeaveApprovalPage.php?request_id=<?php echo $row['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                          View
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center">No approved leave requests found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- Rejected Tab -->
      <div class="tab-content" id="rejected-tab">
        <div class="card">
          <div class="card-header">Rejected Requests</div>
          <div class="card-body">
            <table>
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Leave Type</th>
                  <th>Duration</th>
                  <th>Rejected Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rejected_result->num_rows > 0): ?>
                  <?php while($row = $rejected_result->fetch_assoc()): ?>
                    <?php 
                      // Calculate duration
                      $start_date = new DateTime($row['start_date']);
                      $end_date = new DateTime($row['end_date']);
                      $interval = $start_date->diff($end_date);
                      $days = $interval->days + 1; // Include both start and end dates
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                      <td><?php echo htmlspecialchars($row['department']); ?></td>
                      <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                      <td><?php echo $days; ?> days (<?php echo date('M d', strtotime($row['start_date'])); ?>-<?php echo date('d, Y', strtotime($row['end_date'])); ?>)</td>
                      <td><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
                      <td>
                        <a href="President-LeaveApprovalPage.php?request_id=<?php echo $row['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">
                          View
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center">No rejected leave requests found.</td>
                  </tr>
                <?php endif; ?>
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
    
    // Tab switching functionality
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all tab content
        document.querySelectorAll('.tab-content').forEach(content => {
          content.classList.remove('active');
        });
        
        // Show corresponding tab content
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId + '-tab').classList.add('active');
      });
    });
  </script>
</body>
</html>