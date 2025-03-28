<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
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

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Admin User';

// Fetch admin profile picture
$admin_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_query->bind_param("i", $user_id);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$profile_picture_path = getProfilePicturePath($admin_data['profile_picture'], 'administration');

// Initialize filter variables
$department = isset($_GET['department']) ? $_GET['department'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Build query based on filters
$query = "
    SELECT am.*, u.name, u.username, u.profile_picture 
    FROM attendance_modifications am
    JOIN users u ON am.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($department)) {
    $query .= " AND u.department = ?";
    $params[] = $department;
    $types .= "s";
}

if (!empty($status)) {
    $query .= " AND am.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($date_range)) {
    $query .= " AND am.modification_date = ?";
    $params[] = $date_range;
    $types .= "s";
}

$query .= " ORDER BY am.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Count requests by status
$sql_pending = "SELECT COUNT(*) as count FROM attendance_modifications WHERE status = 'pending'";
$sql_approved = "SELECT COUNT(*) as count FROM attendance_modifications WHERE status = 'approved'";
$sql_rejected = "SELECT COUNT(*) as count FROM attendance_modifications WHERE status = 'rejected'";

$pending_count = $conn->query($sql_pending)->fetch_assoc()['count'];
$approved_count = $conn->query($sql_approved)->fetch_assoc()['count'];
$rejected_count = $conn->query($sql_rejected)->fetch_assoc()['count'];

// Get departments for filter dropdown
$sql_departments = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''";
$departments_result = $conn->query($sql_departments);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance System - Attendance Modification Management</title>
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
      --input-bg: #f9f9f9;
      --input-border: #e0e0e0;
      --btn-primary: #3498db;
      --btn-primary-hover: #2980b9;
      --btn-secondary: #95a5a6;
      --btn-secondary-hover: #7f8c8d;
    }

    /* Grid System Enhancement */
    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 20px;
    }

    .col-4 {
      grid-column: span 4;
    }

    @media (max-width: 768px) {
      .col-4 {
        grid-column: span 12;
      }
    }

    /* Form Styling */
    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: var(--dark-text);
    }

    select, input[type="text"], input[type="date"], textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--input-border);
      border-radius: 4px;
      background-color: var(--input-bg);
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    select:focus, 
    input[type="text"]:focus, 
    input[type="date"]:focus, 
    textarea:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    }

    /* Button Styling */
    .btn {
      display: inline-block;
      padding: 10px 15px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.1s;
      font-weight: 600;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .btn-secondary {
      background-color: var(--btn-secondary);
      color: white;
    }

    .btn-secondary:hover {
      background-color: var(--btn-secondary-hover);
    }

    .btn-danger {
      background-color: var(--danger-color);
      color: white;
    }

    .btn-danger:hover {
      background-color: #c0392b;
    }

    /* Request Employee Avatar */
    .request-employee-avatar img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 15px;
    }

    /* Enhanced Request Item Layout */
    .request-item {
      display: flex;
      align-items: center;
      gap: 15px;
      transition: background-color 0.3s;
      border-radius: 4px;
      padding: 15px;
    }

    .request-item:hover {
      background-color: var(--light-bg);
    }

    /* No Data Styling */
    .no-data {
      text-align: center;
      color: var(--btn-secondary);
      padding: 30px;
      background-color: var(--light-bg);
      border-radius: 4px;
    }

    /* Modal Enhancements */
    .modal-content {
      max-height: 90vh;
      overflow-y: auto;
    }

    /* Responsive Adjustments */
    @media (max-width: 576px) {
      .request-item {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
      }
      
      .request-actions {
        width: 100%;
        margin-top: 10px;
      }
    }

    /* Error and Success States */
    .error {
      color: var(--danger-color);
      background-color: #f9e6e6;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }

    /* Accessibility and Focus States */
    *:focus {
      outline: 2px solid var(--primary-color);
      outline-offset: 2px;
    }
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
    
    /* Tab Navigation */
    .tab-nav {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }
    
    .tab-nav a {
      padding: 10px 20px;
      cursor: pointer;
      text-decoration: none;
      color: var(--dark-text);
      border-bottom: 3px solid transparent;
    }
    
    .tab-nav a.active {
      border-bottom-color: var(--primary-color);
      color: var(--primary-color);
    }
    
    /* Modification Request List */
    .request-list {
      max-height: 500px;
      overflow-y: auto;
    }
    
    .request-item {
      padding: 15px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
    }
    
    .request-item:last-child {
      border-bottom: none;
    }
    
    .request-details {
      flex-grow: 1;
    }
    
    .request-employee {
      font-weight: bold;
      margin-bottom: 5px;
    }
    
    .request-date, .request-reason {
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 5px;
    }
    
    .request-actions {
      display: flex;
      gap: 10px;
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

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal.show {
      display: flex;
    }
    
    .modal-content {
      background-color: white;
      width: 100%;
      max-width: 500px;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
    }
    
    .modal-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      font-weight: bold;
      display: flex;
      justify-content: space-between;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .modal-footer {
      padding: 15px 20px;
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #777;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        z-index: 99;
        transform: translateX(-100%);
        width: var(--sidebar-width);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0 !important;
      }
      
      .request-item {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .request-actions {
        margin-top: 10px;
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Attendance Modification Management</div>
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
        <li><a href="./AdminLeaveRequestManagementPage.php"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
        <li><a href="./AttendanceModificationManagementPage.php" class="active"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.php"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-title">
        <h1>Attendance Modification Management</h1>
      </div>
      
      <div class="card">
        <div class="card-header">
          <div>Filter Requests</div>
        </div>
        <div class="card-body">
          <form method="GET" action="">
            <div class="grid">
              <div class="col-4">
                <div class="form-group">
                  <label>Department</label>
                  <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                      <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department == $dept) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-4">
                <div class="form-group">
                  <label>Status</label>
                  <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo ($status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo ($status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                </div>
              </div>
              <div class="col-4">
                <div class="form-group">
                  <label>Date</label>
                  <input type="date" name="date_range" value="<?php echo htmlspecialchars($date_range); ?>">
                </div>
              </div>
            </div>
            <button type="submit" class="btn">Apply Filters</button>
          </form>
        </div>
      </div>
      
      <div class="card">
        <div class="card-header">
          <div>Modification Requests</div>
        </div>
        <div class="card-body">
          <div class="tab-nav">
            <a href="?status=pending" class="<?php echo ($status == 'pending' || $status == '') ? 'active' : ''; ?>">Pending (<?php echo $pending_count; ?>)</a>
            <a href="?status=approved" class="<?php echo ($status == 'approved') ? 'active' : ''; ?>">Approved (<?php echo $approved_count; ?>)</a>
            <a href="?status=rejected" class="<?php echo ($status == 'rejected') ? 'active' : ''; ?>">Rejected (<?php echo $rejected_count; ?>)</a>
          </div>
          
          <div class="request-list">
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): 
                $emp_profile_path = getProfilePicturePath($row['profile_picture']);
              ?>
                <div class="request-item">
                  <div class="request-employee-avatar">
                    <img src="<?php echo htmlspecialchars($emp_profile_path); ?>" 
                         alt="<?php echo htmlspecialchars($row['name']); ?>" 
                         onerror="this.src='/projectweb/Employee/uploads/profile_pictures/default-profile.png'">
                  </div>
                  <div class="request-details">
                    <div class="request-employee"><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)</div>
                    <div class="request-date">
                      Date: <?php echo date('F j, Y', strtotime($row['modification_date'])); ?> | 
                      Submitted: <?php echo date('F j, Y', strtotime($row['created_at'])); ?>
                    </div>
                    <div class="request-reason">
                      Reason: <?php echo htmlspecialchars($row['reason']); ?>
                    </div>
                  </div>
                  <div class="request-actions">
                    <button class="btn btn-secondary" onclick="openModal('<?php echo $row['id']; ?>')">Review</button>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="no-data">No modification requests found</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Modal for Review -->
  <div class="modal" id="reviewModal">
    <div class="modal-content">
      <div class="modal-header">
        <div>Review Attendance Modification Request</div>
        <button class="close-btn" onclick="closeModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div id="modalContent">
          <!-- Content will be loaded here via AJAX -->
        </div>
      </div>
    </div>
  </div>

  <script>
    // Rest of the JavaScript remains the same as in the previous version
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
    
    // Mobile responsive
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
    
    // Existing Modal functionality scripts remain the same
    function openModal(requestId) {
      const modal = document.getElementById('reviewModal');
      modal.classList.add('show');
      
      // Load request data via AJAX
      fetch('get_modification_request.php?id=' + requestId)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            document.getElementById('modalContent').innerHTML = '<div class="error">' + data.error + '</div>';
          } else {
            const userAttendance = data.attendance ? 
              `Clock In: ${data.attendance.clock_in || 'Not Recorded'}, Clock Out: ${data.attendance.clock_out || 'Not Recorded'}` : 
              'No attendance record found';
            
            let html = `
              <form id="modificationForm">
                <input type="hidden" name="request_id" value="${data.id}">
                <div class="form-group">
                  <label>Employee</label>
                  <input type="text" value="${data.name} (${data.username})" readonly>
                </div>
                <div class="form-group">
                  <label>Date</label>
                  <input type="text" value="${data.modification_date}" readonly>
                </div>
                <div class="form-group">
                  <label>Reason for Modification</label>
                  <textarea readonly rows="3">${data.reason}</textarea>
                </div>
                <div class="form-group">
                  <label>Modification Type</label>
                  <input type="text" value="${data.modification_type}" readonly>
                </div>
                <div class="form-group">
                  <label>Current Record</label>
                  <div>${userAttendance}</div>
                </div>
                <div class="form-group">
                  <label>Admin Decision</label>
                  <select name="status" ${data.status !== 'pending' ? 'disabled' : ''}>
                    <option value="">Select Decision</option>
                    <option value="approved" ${data.status === 'approved' ? 'selected' : ''}>Approve</option>
                    <option value="rejected" ${data.status === 'rejected' ? 'selected' : ''}>Reject</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Comments</label>
                  <textarea name="admin_comment" rows="3" ${data.status !== 'pending' ? 'readonly' : ''}>${data.admin_comment || ''}</textarea>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                  ${data.status === 'pending' ? `
                    <button type="button" class="btn btn-danger" onclick="processRequest('rejected')">Reject</button>
                    <button type="button" class="btn btn-secondary" onclick="processRequest('approved')">Approve</button>
                  ` : ''}
                </div>
              </form>
            `;
            document.getElementById('modalContent').innerHTML = html;
          }
        })
        .catch(error => {
          document.getElementById('modalContent').innerHTML = '<div class="error">Error loading request data</div>';
        });
    }
    
    function closeModal() {
      document.getElementById('reviewModal').classList.remove('show');
    }
    
    function processRequest(decision) {
      const form = document.getElementById('modificationForm');
      const formData = new FormData(form);
      formData.append('status', decision);
      
      fetch('process_modification_request.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Request ' + decision + ' successfully!');
          closeModal();
          // Reload page to reflect changes
          window.location.reload();
        } else {
          alert('Error: ' + data.error);
        }
      })
      .catch(error => {
        alert('Error processing request');
      });
    }
  </script>
</body>
</html>