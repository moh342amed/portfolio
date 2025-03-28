<?php
// Start session to access user info
session_start();

// Check if user is logged in and has president role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'president') {
    header("Location: ../login.html");
    exit;
}

// Check if notification ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: PresidentNotificationsPage.php");
    exit;
}

$notification_id = $_GET['id'];

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

// Fetch the specific notification
$sql = "SELECT * FROM notifications WHERE id = ? AND (user_id = ? OR user_id = 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Notification not found or doesn't belong to this user
    header("Location: PresidentNotificationsPage.php");
    exit;
}

$notification = $result->fetch_assoc();

// Mark notification as read
if ($notification['read'] == 0) {
    $sql_update = "UPDATE notifications SET `read` = 1 WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $notification_id);
    $stmt_update->execute();
}

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
function formatDateTime($datetime) {
    return date('F j, Y, g:i A', strtotime($datetime));
}

// Get related data based on notification type
$related_data = null;
$data_type = '';

// Check notification title to determine related data
if (strpos(strtolower($notification['title']), 'leave request') !== false) {
    // Find related leave request
    $sql_related = "SELECT lr.*, u.name FROM leave_requests lr 
                  JOIN users u ON lr.user_id = u.id 
                  WHERE CONCAT('Leave Request from ', u.name) = ? 
                  OR (lr.id IN (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(message, '#', -1), ' ', 1) 
                               FROM notifications 
                               WHERE id = ?))
                  ORDER BY lr.submitted_at DESC LIMIT 1";
    $stmt_related = $conn->prepare($sql_related);
    $stmt_related->bind_param("si", $notification['title'], $notification_id);
    $stmt_related->execute();
    $result_related = $stmt_related->get_result();
    
    if ($result_related->num_rows > 0) {
        $related_data = $result_related->fetch_assoc();
        $data_type = 'leave';
    }
} else if (strpos(strtolower($notification['title']), 'attendance') !== false) {
    // Find related attendance data
    $sql_related = "SELECT ua.*, u.name FROM users_attendance ua 
                  JOIN users u ON ua.user_id = u.id 
                  WHERE ua.user_id IN (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(message, 'ID: ', -1), '.', 1) 
                                     FROM notifications 
                                     WHERE id = ?)
                  ORDER BY ua.clock_in DESC LIMIT 5";
    $stmt_related = $conn->prepare($sql_related);
    $stmt_related->bind_param("i", $notification_id);
    $stmt_related->execute();
    $result_related = $stmt_related->get_result();
    
    if ($result_related->num_rows > 0) {
        $related_data = [];
        while ($row = $result_related->fetch_assoc()) {
            $related_data[] = $row;
        }
        $data_type = 'attendance';
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notification Details | Attendance Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    /* CSS Variables for consistent theming */
    :root {
      --primary-color: #1976d2;
      --primary-light: #63a4ff;
      --primary-dark: #004ba0;
      --secondary-color: #f50057;
      --dark-color: #333;
      --light-color: #f4f4f4;
      --danger-color: #dc3545;
      --success-color: #28a745;
      --warning-color: #ffc107;
      --info-color: #17a2b8;
      --white: #ffffff;
      --gray: #6c757d;
      --gray-light: #e9ecef;
      --gray-dark: #343a40;
      --font-main: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      --transition-speed: 0.3s;
      --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Base Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: var(--font-main);
      background-color: #f5f5f5;
      color: var(--dark-color);
      line-height: 1.6;
    }

    /* Header */
    header {
      background-color: var(--primary-color);
      color: var(--white);
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      box-shadow: var(--shadow);
    }

    .logo {
      font-size: 24px;
      font-weight: bold;
      letter-spacing: 1px;
    }

    .app-title {
      font-size: 18px;
      letter-spacing: 0.5px;
    }

    .user-menu {
      position: relative;
      cursor: pointer;
    }

    .user-profile {
      display: flex;
      align-items: center;
      background-color: rgba(255, 255, 255, 0.1);
      padding: 8px 12px;
      border-radius: 4px;
      transition: var(--transition-speed);
    }

    .user-profile:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }

    .user-profile img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 8px;
    }

    .user-dropdown {
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--white);
      border-radius: 4px;
      box-shadow: var(--shadow);
      min-width: 200px;
      display: none;
      z-index: 1000;
    }

    .user-dropdown.show {
      display: block;
    }

    .user-dropdown ul {
      list-style: none;
    }

    .user-dropdown ul li a {
      color: var(--dark-color);
      padding: 12px 16px;
      text-decoration: none;
      display: block;
      transition: var(--transition-speed);
    }

    .user-dropdown ul li a:hover {
      background-color: var(--gray-light);
    }

    .user-dropdown ul li a i {
      margin-right: 10px;
      color: var(--primary-color);
    }

    /* Layout */
    .layout {
      display: flex;
      margin-top: 60px;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: var(--white);
      height: calc(100vh - 60px);
      position: fixed;
      box-shadow: var(--shadow);
      transition: var(--transition-speed);
      z-index: 900;
    }

    .sidebar.collapsed {
      width: 60px;
    }

    .sidebar ul {
      list-style: none;
      padding: 20px 0;
    }

    .sidebar ul li {
      margin-bottom: 5px;
    }

    .sidebar ul li a {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      color: var(--dark-color);
      text-decoration: none;
      transition: var(--transition-speed);
      white-space: nowrap;
      overflow: hidden;
    }

    .sidebar ul li a.active {
      background-color: var(--primary-light);
      color: var(--white);
    }

    .sidebar ul li a:hover {
      background-color: var(--gray-light);
    }

    .sidebar ul li a i {
      margin-right: 10px;
      font-size: 18px;
      width: 20px;
      text-align: center;
    }

    .sidebar ul li a.active i {
      color: var(--white);
    }

    .sidebar.collapsed ul li a span {
      display: none;
    }

    .toggle-btn {
      background-color: transparent;
      border: none;
      color: var(--dark-color);
      font-size: 20px;
      cursor: pointer;
      padding: 10px;
      width: 100%;
      text-align: right;
      transition: var(--transition-speed);
    }

    .toggle-btn:hover {
      background-color: var(--gray-light);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 20px;
      transition: var(--transition-speed);
      margin-left: 250px;
      min-height: calc(100vh - 60px);
    }

    .sidebar.collapsed + .main-content {
      margin-left: 60px;
    }

    .page-title {
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .page-title h1 {
      font-size: 28px;
      font-weight: 500;
      color: var(--dark-color);
    }

    .badge {
      display: inline-block;
      padding: 5px 10px;
      font-size: 12px;
      border-radius: 20px;
      margin-left: 10px;
    }

    .badge-danger {
      background-color: var(--danger-color);
      color: var(--white);
    }

    /* Notification Details Card */
    .notification-details-card {
      background-color: var(--white);
      border-radius: 8px;
      box-shadow: var(--shadow);
      margin-bottom: 20px;
      padding: 25px;
    }

    .notification-header {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--gray-light);
    }

    .notification-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 20px;
      font-size: 28px;
    }

    .notification-icon.critical {
      background-color: rgba(220, 53, 69, 0.1);
      color: var(--danger-color);
    }

    .notification-icon.warning {
      background-color: rgba(255, 193, 7, 0.1);
      color: var(--warning-color);
    }

    .notification-icon.info {
      background-color: rgba(23, 162, 184, 0.1);
      color: var(--info-color);
    }

    .notification-icon.success {
      background-color: rgba(40, 167, 69, 0.1);
      color: var(--success-color);
    }

    .notification-title-area h2 {
      font-size: 22px;
      margin-bottom: 5px;
    }

    .notification-meta {
      color: var(--gray);
      font-size: 14px;
    }

    .notification-body {
      margin-bottom: 30px;
      font-size: 16px;
      line-height: 1.7;
    }

    .notification-actions {
      display: flex;
      gap: 10px;
    }

    .btn {
      display: inline-block;
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 500;
      text-align: center;
      transition: var(--transition-speed);
      text-decoration: none;
    }

    .btn i {
      margin-right: 8px;
    }

    .btn-primary {
      background-color: var(--primary-color);
      color: var(--white);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .btn-success {
      background-color: var(--success-color);
      color: var(--white);
    }

    .btn-success:hover {
      background-color: #218838;
    }

    .btn-danger {
      background-color: var(--danger-color);
      color: var(--white);
    }

    .btn-danger:hover {
      background-color: #c82333;
    }

    .btn-secondary {
      background-color: var(--gray);
      color: var(--white);
    }

    .btn-secondary:hover {
      background-color: var(--gray-dark);
    }

    /* Related Data Section */
    .related-data-section {
      background-color: var(--white);
      border-radius: 8px;
      box-shadow: var(--shadow);
      padding: 25px;
    }

    .related-data-section h3 {
      font-size: 20px;
      margin-bottom: 20px;
      color: var(--primary-color);
      border-bottom: 1px solid var(--gray-light);
      padding-bottom: 10px;
    }

    /* Table Styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    .data-table th, .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--gray-light);
    }

    .data-table th {
      background-color: var(--gray-light);
      font-weight: 600;
    }

    .data-table tr:hover {
      background-color: rgba(0, 0, 0, 0.02);
    }

    /* Data Item Grid */
    .data-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .data-item {
      margin-bottom: 15px;
    }

    .data-item-label {
      font-weight: 600;
      color: var(--gray);
      font-size: 14px;
      margin-bottom: 5px;
    }

    .data-item-value {
      font-size: 16px;
    }

    /* Status badges */
    .status-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
    }

    .status-pending {
      background-color: rgba(255, 193, 7, 0.2);
      color: #856404;
    }

    .status-approved {
      background-color: rgba(40, 167, 69, 0.2);
      color: #155724;
    }

    .status-rejected {
      background-color: rgba(220, 53, 69, 0.2);
      color: #721c24;
    }

    /* Back button */
    .back-link {
      display: inline-flex;
      align-items: center;
      color: var(--primary-color);
      text-decoration: none;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .back-link i {
      margin-right: 8px;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    /* Responsive design */
    @media (max-width: 768px) {
      .sidebar {
        width: 60px;
      }
      
      .sidebar ul li a span {
        display: none;
      }
      
      .main-content {
        margin-left: 60px;
      }
      
      .data-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 576px) {
      header {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px;
      }
      
      .user-menu {
        margin-top: 10px;
        align-self: flex-end;
      }
      
      .main-content {
        padding: 15px;
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
        <img src="/api/placeholder/32/32" alt="User Profile">
        <span><?php echo $user_name; ?> (President)</span>
        <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
      </div>
      <div class="user-dropdown">
        <ul>
          <li><a href="#"><i class="fas fa-user"></i> My Profile</a></li>
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
      <a href="./PresidentNotificationsPage.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Notifications
      </a>
      
      <div class="page-title">
        <h1>Notification Details</h1>
      </div>
      
      <!-- Notification Details Card -->
      <div class="notification-details-card">
        <div class="notification-header">
          <?php 
            $type_class = getNotificationTypeClass($notification['title']); 
          ?>
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
          <div class="notification-title-area">
            <h2><?php echo htmlspecialchars($notification['title']); ?></h2>
            <div class="notification-meta">
              <span><i class="far fa-clock"></i> <?php echo formatDateTime($notification['created_at']); ?></span>
            </div>
          </div>
        </div>
        
        <div class="notification-body">
          <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
        </div>
        
        <div class="notification-actions">
          <?php if (strpos(strtolower($notification['title']), 'leave request') !== false): ?>
            <a href="President-LeaveApprovalPage.php?approve=<?php echo $notification['id']; ?>" class="btn btn-success">
              <i class="fas fa-check"></i> Approve
            </a>
            <a href="President-LeaveApprovalPage.php?reject=<?php echo $notification['id']; ?>" class="btn btn-danger">
              <i class="fas fa-times"></i> Reject
            </a>
          <?php elseif (strpos(strtolower($notification['title']), 'report') !== false): ?>
            <a href="PresidentReportsReviewPage.php?report=<?php echo $notification['id']; ?>" class="btn btn-primary">
              <i class="fas fa-file-alt"></i> View Full Report
            </a>
          <?php endif; ?>
          <a href="javascript:void(0);" id="dismiss-notification" class="btn btn-secondary" data-id="<?php echo $notification['id']; ?>">
            <i class="fas fa-trash"></i> Dismiss
          </a>
        </div>
      </div>
      
      <!-- Related Data Section -->
      <?php if ($related_data): ?>
      <div class="related-data-section">
        <?php if ($data_type == 'leave'): ?>
          <h3>Leave Request Details</h3>
          <div class="data-grid">
            <div class="data-item">
              <div class="data-item-label">Employee</div>
              <div class="data-item-value"><?php echo htmlspecialchars($related_data['name']); ?></div>
            </div>
            <div class="data-item">
              <div class="data-item-label">Leave Type</div>
              <div class="data-item-value"><?php echo htmlspecialchars(ucfirst($related_data['leave_type'])); ?></div>
            </div>
            <div class="data-item">
              <div class="data-item-label">Duration</div>
              <div class="data-item-value"><?php echo date('M d, Y', strtotime($related_data['start_date'])); ?> - <?php echo date('M d, Y', strtotime($related_data['end_date'])); ?> (<?php echo $related_data['days']; ?> days)</div>
            </div>
            <div class="data-item">
              <div class="data-item-label">Status</div>
              <div class="data-item-value">
                <span class="status-badge status-<?php echo strtolower($related_data['status']); ?>">
                  <?php echo ucfirst($related_data['status']); ?>
                </span>
              </div>
            </div>
            <div class="data-item">
              <div class="data-item-label">Submitted On</div>
              <div class="data-item-value"><?php echo date('M d, Y, h:i A', strtotime($related_data['submitted_at'])); ?></div>
            </div>
            <div class="data-item">
              <div class="data-item-label">Team Lead Approval</div>
              <div class="data-item-value">
                <span class="status-badge status-<?php echo strtolower($related_data['team_lead_approval']); ?>">
                  <?php echo ucfirst($related_data['team_lead_approval']); ?>
                </span>
              </div>
            </div>
          </div>
          
          <div class="data-item" style="grid-column: 1 / -1;">
            <div class="data-item-label">Reason</div>
            <div class="data-item-value"><?php echo nl2br(htmlspecialchars($related_data['reason'])); ?></div>
          </div>
          
          <?php if (!empty($related_data['admin_comment'])): ?>
          <div class="data-item" style="grid-column: 1 / -1;">
            <div class="data-item-label">Admin Comment</div>
            <div class="data-item-value"><?php echo nl2br(htmlspecialchars($related_data['admin_comment'])); ?></div>
          </div>
          <?php endif; ?>
          
        <?php elseif ($data_type == 'attendance'): ?>
          <h3>Recent Attendance Records</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($related_data as $attendance): ?>
                <tr>
                  <td><?php echo date('M d, Y', strtotime($attendance['clock_in'])); ?></td>
                  <td><?php echo date('h:i A', strtotime($attendance['clock_in'])); ?></td>
                  <td>
                    <?php 
                      echo $attendance['clock_out'] 
                        ? date('h:i A', strtotime($attendance['clock_out'])) 
                        : '<span style="color: var(--warning-color);">Not clocked out</span>'; 
                    ?>
                  </td>
                  <td>
                    <?php 
                      if ($attendance['clock_out']) {
                        $clock_in = new DateTime($attendance['clock_in']);
                        $clock_out = new DateTime($attendance['clock_out']);
                        $interval = $clock_in->diff($clock_out);
                        echo $interval->format('%h hours, %i minutes');
                      } else {
                        echo '<span style="color: var(--warning-color);">In progress</span>';
                      }
                    ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php endif; ?>
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
        mainContent.style.marginLeft = '250px';
      }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.user-menu')) {
        document.querySelector('.user-dropdown').classList.remove('show');
      }
    });
    
    // Dismiss notification
    document.getElementById('dismiss-notification').addEventListener('click', function() {
      const notificationId = this.getAttribute('data-id');
      dismissNotification(notificationId);
    });
    
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
          window.location.href = 'PresidentNotificationsPage.php';
        }
      })
      .catch(error => console.error('Error:', error));
    }
  </script>
</body>
</html>