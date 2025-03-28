<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
}

// Check if penalty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: PenaltyManagementPage.php");
    exit;
}

$penalty_id = $_GET['id'];

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
$user_name = $_SESSION['name'] ?? 'Admin User';

// Fetch penalty details
$sql = "SELECT p.*, u.name as employee_name, u.department, u.email as employee_email,
         c.name as created_by_name, r.name as resolved_by_name
         FROM penalties p
         JOIN users u ON p.user_id = u.id
         LEFT JOIN users c ON p.created_by = c.id
         LEFT JOIN users r ON p.resolved_by = r.id
         WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $penalty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Penalty not found
    $conn->close();
    header("Location: PenaltyManagementPage.php");
    exit;
}

$penalty = $result->fetch_assoc();

// Format penalty type for display
$penalty_type_formatted = str_replace('_', ' ', ucwords($penalty['penalty_type']));

// Close connection
$conn->close();

// Function to get severity class
function getSeverityClass($severity) {
    switch(strtolower($severity)) {
        case 'low':
            return 'severity-low';
        case 'medium':
            return 'severity-medium';
        case 'high':
            return 'severity-high';
        default:
            return 'severity-low';
    }
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'active':
            return 'badge-warning';
        case 'resolved':
            return 'badge-success';
        case 'revoked':
            return 'badge-danger';
        default:
            return 'badge-warning';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Penalty Details - Attendance System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    :root {
      --primary-color: #3498db;
      --secondary-color: #e74c3c;
      --success-color: #2ecc71;
      --warning-color: #f39c12;
      --dark-color: #34495e;
      --light-color: #f5f5f5;
      --border-color: #ddd;
      --sidebar-width: 250px;
      --sidebar-collapsed: 70px;
      --header-height: 60px;
      --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: var(--font-family);
      background-color: #f0f2f5;
      color: #333;
    }
    
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      height: var(--header-height);
      background-color: white;
      border-bottom: 1px solid var(--border-color);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 999;
    }
    
    .logo {
      font-weight: bold;
      font-size: 24px;
      color: var(--primary-color);
    }
    
    .app-title {
      font-size: 18px;
      font-weight: 500;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      cursor: pointer;
      position: relative;
    }
    
    .user-profile img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 10px;
    }
    
    .user-dropdown {
      position: absolute;
      top: 60px;
      right: 0;
      background-color: white;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: none;
      z-index: 1000;
    }
    
    .user-dropdown.show {
      display: block;
    }
    
    .user-dropdown ul {
      list-style: none;
      padding: 0;
    }
    
    .user-dropdown ul li {
      padding: 0;
    }
    
    .user-dropdown ul li a {
      padding: 10px 20px;
      display: block;
      color: #333;
      text-decoration: none;
      transition: background-color 0.3s;
    }
    
    .user-dropdown ul li a:hover {
      background-color: #f5f5f5;
    }
    
    .layout {
      display: flex;
      margin-top: var(--header-height);
      min-height: calc(100vh - var(--header-height));
    }
    
    .sidebar {
      width: var(--sidebar-width);
      background-color: var(--dark-color);
      height: calc(100vh - var(--header-height));
      position: fixed;
      top: var(--header-height);
      left: 0;
      transition: width 0.3s;
      overflow-y: auto;
      z-index: 998;
    }
    
    .sidebar.collapsed {
      width: var(--sidebar-collapsed);
    }
    
    .sidebar .toggle-btn {
      background-color: transparent;
      border: none;
      color: white;
      font-size: 20px;
      padding: 15px;
      cursor: pointer;
      width: 100%;
      text-align: right;
    }
    
    .sidebar ul {
      list-style: none;
      padding: 0;
    }
    
    .sidebar ul li {
      padding: 0;
    }
    
    .sidebar ul li a {
      padding: 15px;
      display: flex;
      align-items: center;
      color: white;
      text-decoration: none;
      transition: background-color 0.3s;
    }
    
    .sidebar ul li a:hover,
    .sidebar ul li a.active {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar ul li a i {
      margin-right: 15px;
      font-size: 18px;
      width: 25px;
      text-align: center;
    }
    
    .sidebar.collapsed ul li a span {
      display: none;
    }
    
    .main-content {
      flex: 1;
      padding: 20px;
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s;
    }
    
    .page-title {
      margin-bottom: 20px;
    }
    
    .page-title h1 {
      font-size: 24px;
      font-weight: 500;
      color: var(--dark-color);
    }
    
    .card {
      background-color: white;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      overflow: hidden;
    }
    
    .card-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 500;
    }
    
    .card-body {
      padding: 20px;
    }
    
    .btn {
      display: inline-block;
      padding: 8px 15px;
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      font-size: 14px;
      transition: background-color 0.3s;
    }
    
    .btn:hover {
      background-color: #2980b9;
    }
    
    .btn-secondary {
      background-color: #7f8c8d;
    }
    
    .btn-secondary:hover {
      background-color: #6c7a7d;
    }
    
    .btn-danger {
      background-color: var(--secondary-color);
    }
    
    .btn-danger:hover {
      background-color: #c0392b;
    }
    
    .btn-success {
      background-color: var(--success-color);
    }
    
    .btn-success:hover {
      background-color: #27ae60;
    }
    
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .penalty-severity {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 500;
      text-transform: uppercase;
    }
    
    .severity-low {
      background-color: #d1ecf1;
      color: #0c5460;
    }
    
    .severity-medium {
      background-color: #fff3cd;
      color: #856404;
    }
    
    .severity-high {
      background-color: #f8d7da;
      color: #721c24;
    }
    
    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 500;
      text-transform: uppercase;
    }
    
    .badge-warning {
      background-color: #fff3cd;
      color: #856404;
    }
    
    .badge-success {
      background-color: #d4edda;
      color: #155724;
    }
    
    .badge-danger {
      background-color: #f8d7da;
      color: #721c24;
    }
    
    .timeline {
      position: relative;
      padding-left: 50px;
      margin-top: 20px;
    }
    
    .timeline:before {
      content: '';
      position: absolute;
      left: 20px;
      top: 0;
      bottom: 0;
      width: 2px;
      background-color: #e0e0e0;
    }
    
    .timeline-item {
      position: relative;
      margin-bottom: 30px;
    }
    
    .timeline-item:last-child {
      margin-bottom: 0;
    }
    
    .timeline-icon {
      position: absolute;
      left: -37px;
      top: 0;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background-color: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 12px;
    }
    
    .timeline-date {
      font-size: 12px;
      color: #777;
      margin-bottom: 5px;
    }
    
    .timeline-content {
      background-color: #f9f9f9;
      border-radius: 4px;
      padding: 15px;
    }
    
    .detail-row {
      display: flex;
      margin-bottom: 15px;
    }
    
    .detail-label {
      flex: 0 0 200px;
      font-weight: 500;
    }
    
    .detail-value {
      flex: 1;
    }
    
    .action-buttons {
      margin-top: 30px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        width: var(--sidebar-collapsed);
      }
      
      .sidebar ul li a span {
        display: none;
      }
      
      .main-content {
        margin-left: var(--sidebar-collapsed);
      }
      
      .detail-row {
        flex-direction: column;
      }
      
      .detail-label {
        margin-bottom: 5px;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Penalty Details</div>
    <div class="user-profile" id="userProfileBtn">
      <img src="/api/placeholder/32/32" alt="<?php echo htmlspecialchars($user_name); ?>">
      <span><?php echo htmlspecialchars($user_name); ?></span>
      <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
    </div>
    <div class="user-dropdown" id="userDropdown">
      <ul>
        <li><a href="#"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
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
        <li><a href="./EmployeeDirectoryPage.html"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
        <li><a href="./AdminLeaveRequestManagementPage.html"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
        <li><a href="./AttendanceModificationManagementPage.html"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.php" class="active"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.html"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-title">
        <h1>Penalty Details</h1>
      </div>
      
      <div class="card">
        <div class="card-header">
          <div>Penalty #<?php echo htmlspecialchars($penalty_id); ?> - <?php echo htmlspecialchars($penalty_type_formatted); ?></div>
          <span class="badge <?php echo getStatusBadgeClass($penalty['status']); ?>"><?php echo ucfirst(htmlspecialchars($penalty['status'])); ?></span>
        </div>
        <div class="card-body">
          <div class="detail-row">
            <div class="detail-label">Employee</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['employee_name']); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Department</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['department']); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['employee_email']); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Penalty Type</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty_type_formatted); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Severity</div>
            <div class="detail-value">
              <span class="penalty-severity <?php echo getSeverityClass($penalty['severity']); ?>">
                <?php echo ucfirst(htmlspecialchars($penalty['severity'])); ?>
              </span>
            </div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Date of Incident</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['incident_date']); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Issue Date</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['issue_date']); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Penalty Action</div>
            <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($penalty['penalty_action']))); ?></div>
          </div>
          
          <div class="detail-row">
            <div class="detail-label">Description</div>
            <div class="detail-value"><?php echo nl2br(htmlspecialchars($penalty['description'])); ?></div>
          </div>
          
          <?php if (!empty($penalty['documents'])): ?>
          <div class="detail-row">
            <div class="detail-label">Supporting Documents</div>
            <div class="detail-value">
              <a href="uploads/penalties/<?php echo htmlspecialchars($penalty['documents']); ?>" target="_blank" class="btn btn-secondary">
                <i class="fas fa-file-download"></i> View Document
              </a>
            </div>
          </div>
          <?php endif; ?>
          
          <h3 style="margin: 30px 0 15px 0;">Penalty History</h3>
          
          <div class="timeline">
            <div class="timeline-item">
              <div class="timeline-icon">
                <i class="fas fa-plus"></i>
              </div>
              <div class="timeline-date"><?php echo htmlspecialchars($penalty['created_at']); ?></div>
              <div class="timeline-content">
                <strong>Penalty Created</strong>
                <p>Penalty was issued by <?php echo htmlspecialchars($penalty['created_by_name']); ?></p>
                <p>Notifications sent: 
                  <?php 
                  $notifications = [];
                  if ($penalty['notify_employee']) $notifications[] = 'Employee';
                  if ($penalty['notify_manager']) $notifications[] = 'Manager';
                  echo !empty($notifications) ? implode(', ', $notifications) : 'None';
                  ?>
                </p>
              </div>
            </div>
            
            <?php if ($penalty['status'] == 'resolved' && !empty($penalty['resolved_date'])): ?>
            <div class="timeline-item">
              <div class="timeline-icon">
                <i class="fas fa-check"></i>
              </div>
              <div class="timeline-date"><?php echo htmlspecialchars($penalty['resolved_date']); ?></div>
              <div class="timeline-content">
                <strong>Penalty Resolved</strong>
                <p>Penalty was resolved by <?php echo htmlspecialchars($penalty['resolved_by_name']); ?></p>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if ($penalty['status'] == 'revoked'): ?>
            <div class="timeline-item">
              <div class="timeline-icon">
                <i class="fas fa-times"></i>
              </div>
              <div class="timeline-date"><?php echo htmlspecialchars($penalty['updated_at']); ?></div>
              <div class="timeline-content">
                <strong>Penalty Revoked</strong>
                <p>Penalty was revoked by <?php echo htmlspecialchars($penalty['resolved_by_name']); ?></p>
              </div>
            </div>
            <?php endif; ?>
          </div>
          
          <div class="action-buttons">
            <a href="PenaltyManagementPage.php" class="btn btn-secondary">Back to List</a>
            
            <?php if ($penalty['status'] === 'active'): ?>
            <a href="EditPenalty.php?id=<?php echo $penalty_id; ?>" class="btn">Edit Penalty</a>
            <a href="RevokePenalty.php?id=<?php echo $penalty_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to revoke this penalty?')">Revoke Penalty</a>
            <?php endif; ?>
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
    
    // Mobile responsiveness
    document.addEventListener('DOMContentLoaded', function() {
      const mediaQuery = window.matchMedia('(max-width: 768px)');
      function handleScreenChange(e) {
        if (e.matches) {
          document.getElementById('sidebar').classList.add('collapsed');
          document.querySelector('.main-content').style.marginLeft = 'var(--sidebar-collapsed)';
        } else {
          document.getElementById('sidebar').classList.remove('collapsed');
          document.querySelector('.main-content').style.marginLeft = 'var(--sidebar-width)';
        }
      }
      mediaQuery.addEventListener('change', handleScreenChange);
      handleScreenChange(mediaQuery);
    });
  </script>
</body>
</html>