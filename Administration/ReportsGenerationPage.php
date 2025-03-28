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
$user_name = $_SESSION['name'];

// Fetch admin profile picture
$admin_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_query->bind_param("i", $user_id);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$profile_picture_path = getProfilePicturePath($admin_data['profile_picture'], 'administration');

// Get departments for dropdown
$sql_departments = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''";
$result_departments = $conn->query($sql_departments);
$departments = [];
if ($result_departments && $result_departments->num_rows > 0) {
    while($row = $result_departments->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Get report history
$sql_reports = "SELECT r.*, u.name as generator_name, s.name as signer_name 
                FROM reports r 
                LEFT JOIN users u ON r.generated_by = u.id 
                LEFT JOIN users s ON r.signed_by = s.id 
                ORDER BY r.generated_date DESC 
                LIMIT 10";
$result_reports = $conn->query($sql_reports);
$reports = [];
if ($result_reports && $result_reports->num_rows > 0) {
    while($row = $result_reports->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Close connection
$conn->close();

// Function to format datetime
function formatDateTime($datetime) {
    return date('M d, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AttendX - Reports Generation</title>
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
    
    /* Reports Page Specific Styles */
    .report-type-card {
      border: 1px solid var(--border-color);
      border-radius: 4px;
      padding: 15px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .report-type-card:hover {
      background-color: var(--light-bg);
      transform: translateY(-2px);
    }
    
    .report-type-card .report-icon {
      font-size: 1.5rem;
      margin-right: 15px;
      color: var(--primary-color);
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: rgba(52, 152, 219, 0.1);
      border-radius: 50%;
    }
    
    .report-type-card .report-details {
      flex-grow: 1;
    }
    
    .report-type-card .report-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .report-type-card .report-description {
      font-size: 0.9rem;
      color: #666;
    }
    
    .report-history-item {
      display: flex;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid var(--border-color);
    }
    
    .report-history-item:last-child {
      border-bottom: none;
    }
    
    .report-history-item .report-info {
      flex-grow: 1;
    }
    
    .report-history-item .report-actions {
      display: flex;
      gap: 10px;
    }
    
    .report-history-item .report-name {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .report-history-item .report-meta {
      font-size: 0.85rem;
      color: #666;
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
    
    .tab-container {
      margin-bottom: 20px;
    }
    
    .tab-buttons {
      display: flex;
      border-bottom: 1px solid var(--border-color);
    }
    
    .tab-btn {
      padding: 10px 20px;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      color: var(--dark-text);
    }
    
    .tab-btn.active {
      color: var(--primary-color);
      border-bottom: 2px solid var(--primary-color);
    }
    
    .tab-content {
      display: none;
      padding: 20px 0;
    }
    
    .tab-content.active {
      display: block;
    }
    
    .schedule-option {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }
    
    .schedule-option input[type="radio"] {
      margin-right: 10px;
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Reports Generation</div>
    <div class="user-profile" id="userProfileBtn">
      <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
      <span><?php echo htmlspecialchars($user_name); ?></span>
      <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
    </div>
    <div class="user-dropdown" id="userDropdown">
      <ul>
        <li><a href="./MyProfile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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
        <li><a href="./AttendanceModificationManagementPage.php"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.php"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-title">
        <h1>Reports Generation</h1>
      </div>
      
      <div class="tab-container">
        <div class="tab-buttons">
          <button class="tab-btn active" data-tab="generate">Generate Report</button>
          <button class="tab-btn" data-tab="schedule">Schedule Reports</button>
          <button class="tab-btn" data-tab="history">Report History</button>
        </div>
        
        <div class="tab-content active" id="generate">
          <div class="card">
            <div class="card-header">Generate New Report</div>
            <div class="card-body">
              <form id="reportForm" action="generate_report.php" method="post">
                <div class="form-group">
                  <label>Report Type</label>
                  <select id="reportType" name="reportType" required>
                    <option value="">Select Report Type</option>
                    <option value="attendance">Attendance Summary</option>
                    <option value="leave">Leave Management</option>
                    <option value="performance">Performance Analysis</option>
                    <option value="penalty">Penalty Report</option>
                    <option value="department">Department Summary</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Date Range</label>
                  <div class="grid">
                    <div class="col-6">
                      <label>From</label>
                      <input type="date" id="startDate" name="startDate" required>
                    </div>
                    <div class="col-6">
                      <label>To</label>
                      <input type="date" id="endDate" name="endDate" required>
                    </div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Department</label>
                  <select id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Format</label>
                  <select id="format" name="format">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Include Charts</label>
                  <div>
                    <input type="checkbox" id="includeCharts" name="includeCharts" value="1" checked>
                    <label for="includeCharts" style="display: inline;">Include visual charts and graphs</label>
                  </div>
                </div>
                
                <div class="text-right">
                  <button type="button" class="btn btn-secondary" id="previewReport">Preview</button>
                  <button type="submit" class="btn" id="generateReport">Generate Report</button>
                </div>
              </form>
            </div>
          </div>
          
          <div class="card">
            <div class="card-header">Available Report Types</div>
            <div class="card-body">
              <div class="report-type-card">
                <div class="report-icon">
                  <i class="fas fa-calendar-check"></i>
                </div>
                <div class="report-details">
                  <div class="report-title">Attendance Summary</div>
                  <div class="report-description">Overview of attendance records including present, absent, and late statistics.</div>
                </div>
              </div>
              
              <div class="report-type-card">
                <div class="report-icon">
                  <i class="fas fa-umbrella-beach"></i>
                </div>
                <div class="report-details">
                  <div class="report-title">Leave Management</div>
                  <div class="report-description">Summary of leave requests, approvals, and remaining balances.</div>
                </div>
              </div>
              
              <div class="report-type-card">
                <div class="report-icon">
                  <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-details">
                  <div class="report-title">Performance Analysis</div>
                  <div class="report-description">Analysis of employee performance based on attendance and other metrics.</div>
                </div>
              </div>
              
              <div class="report-type-card">
                <div class="report-icon">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="report-details">
                  <div class="report-title">Penalty Report</div>
                  <div class="report-description">Summary of penalties issued and their status.</div>
                </div>
              </div>
              
              <div class="report-type-card">
                <div class="report-icon">
                  <i class="fas fa-building"></i>
                </div>
                <div class="report-details">
                  <div class="report-title">Department Summary</div>
                  <div class="report-description">Overview of department performance and attendance statistics.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="tab-content" id="schedule">
          <div class="card">
            <div class="card-header">Schedule Automatic Reports</div>
            <div class="card-body">
              <form id="scheduleForm" action="schedule_report.php" method="post">
                <div class="form-group">
                  <label>Report Name</label>
                  <input type="text" name="reportName" placeholder="Enter a name for this scheduled report" required>
                </div>
                
                <div class="form-group">
                  <label>Report Type</label>
                  <select name="reportType" required>
                    <option value="">Select Report Type</option>
                    <option value="attendance">Attendance Summary</option>
                    <option value="leave">Leave Management</option>
                    <option value="performance">Performance Analysis</option>
                    <option value="penalty">Penalty Report</option>
                    <option value="department">Department Summary</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Recipients</label>
                  <input type="text" name="recipients" placeholder="Enter email addresses separated by commas" required>
                </div>
                
                <div class="form-group">
                  <label>Schedule</label>
                  <div class="schedule-options">
                    <div class="schedule-option">
                      <input type="radio" name="schedule" id="daily" value="daily">
                      <label for="daily">Daily</label>
                    </div>
                    <div class="schedule-option">
                      <input type="radio" name="schedule" id="weekly" value="weekly" checked>
                      <label for="weekly">Weekly</label>
                    </div>
                    <div class="schedule-option">
                      <input type="radio" name="schedule" id="monthly" value="monthly">
                      <label for="monthly">Monthly</label>
                    </div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Format</label>
                  <select name="format">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
                </div>
                
                <div class="text-right">
                  <button type="submit" class="btn">Schedule Report</button>
                </div>
              </form>
            </div>
          </div>
          
          <div class="card">
            <div class="card-header">Currently Scheduled Reports</div>
            <div class="card-body">
              <table>
                <thead>
                  <tr>
                    <th>Report Name</th>
                    <th>Type</th>
                    <th>Frequency</th>
                    <th>Recipients</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // This would be populated from a scheduled_reports table in the database
                  // For now, showing placeholders until database table is created
                  ?>
                  <tr>
                    <td>Weekly Attendance Summary</td>
                    <td>Attendance Summary</td>
                    <td>Weekly (Every Monday)</td>
                    <td>admin@example.com</td>
                    <td>
                      <button class="btn btn-secondary edit-schedule" data-id="1">Edit</button>
                      <button class="btn btn-danger delete-schedule" data-id="1">Delete</button>
                    </td>
                  </tr>
                  <tr>
                    <td>Monthly Department Performance</td>
                    <td>Performance Analysis</td>
                    <td>Monthly (1st of month)</td>
                    <td>admin@example.com, manager@example.com</td>
                    <td>
                      <button class="btn btn-secondary edit-schedule" data-id="2">Edit</button>
                      <button class="btn btn-danger delete-schedule" data-id="2">Delete</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <div class="tab-content" id="history">
          <div class="card">
            <div class="card-header">Report History</div>
            <div class="card-body">
              <?php if (empty($reports)): ?>
                <p>No reports have been generated yet.</p>
              <?php else: ?>
                <?php foreach ($reports as $report): ?>
                <div class="report-history-item">
                  <div class="report-info">
                    <div class="report-name"><?php echo htmlspecialchars($report['title']); ?></div>
                    <div class="report-meta">
                      Generated on <?php echo formatDateTime($report['generated_date']); ?> by 
                      <?php echo htmlspecialchars($report['generator_name']); ?> | 
                      <?php echo strtoupper(htmlspecialchars($report['attachment'] ? pathinfo($report['attachment'], PATHINFO_EXTENSION) : 'PDF')); ?> Format
                    </div>
                  </div>
                  <div class="report-actions">
                    <button class="btn btn-secondary view-report" data-id="<?php echo $report['id']; ?>">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <a href="download_report.php?id=<?php echo $report['id']; ?>" class="btn">
                      <i class="fas fa-download"></i> Download
                    </a>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
              
              <?php if (empty($reports)): ?>
              <!-- Sample data if no reports exist -->
              <div class="report-history-item">
                <div class="report-info">
                  <div class="report-name">February Attendance Summary</div>
                  <div class="report-meta">Generated on Mar 01, 2025 by Admin User | PDF Format</div>
                </div>
                <div class="report-actions">
                  <button class="btn btn-secondary"><i class="fas fa-eye"></i> View</button>
                  <button class="btn"><i class="fas fa-download"></i> Download</button>
                </div>
              </div>
              <?php endif; ?>
            </div>
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
    
    // Tab functionality
    document.querySelectorAll('.tab-btn').forEach(button => {
      button.addEventListener('click', function() {
        // Remove active class from all buttons and content
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Add active class to clicked button and corresponding content
        this.classList.add('active');
        document.getElementById(this.getAttribute('data-tab')).classList.add('active');
      });
    });
    
    // Report type card selection
    document.querySelectorAll('.report-type-card').forEach(card => {
      card.addEventListener('click', function() {
        const reportTitle = this.querySelector('.report-title').textContent;
        const reportTypeSelect = document.getElementById('reportType');
        
        // Find and select the matching option
        for (let i = 0; i < reportTypeSelect.options.length; i++) {
          if (reportTypeSelect.options[i].text === reportTitle) {
            reportTypeSelect.selectedIndex = i;
            break;
          }
        }
        
        // Switch to generate tab if not already active
        document.querySelector('.tab-btn[data-tab="generate"]').click();
      });
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
    
    // Form validation and preview
    document.getElementById('previewReport').addEventListener('click', function() {
      const form = document.getElementById('reportForm');
      if (validateForm(form)) {
        // Show preview (you could implement this as a modal or AJAX call)
        alert('Report preview is being generated. Please wait...');
      }
    });
    
    // Form submission
    document.getElementById('reportForm').addEventListener('submit', function(e) {
      if (!validateForm(this)) {
        e.preventDefault();
      } else {
        // Continue with form submission
        alert('Report generation initiated. The report will be available shortly.');
      }
    });
    
    // Basic form validation
    function validateForm(form) {
      let isValid = true;
      const reportType = form.querySelector('#reportType');
      const startDate = form.querySelector('#startDate');
      const endDate = form.querySelector('#endDate');
      
      if (!reportType.value) {
        alert('Please select a report type');
        reportType.focus();
        isValid = false;
      } else if (!startDate.value) {
        alert('Please select a start date');
        startDate.focus();
        isValid = false;
      } else if (!endDate.value) {
        alert('Please select an end date');
        endDate.focus();
        isValid = false;
      } else if (new Date(startDate.value) > new Date(endDate.value)) {
        alert('End date must be after start date');
        endDate.focus();
        isValid = false;
      }
      
      return isValid;
    }
    
    // Delete scheduled report confirmation
    document.querySelectorAll('.delete-schedule').forEach(button => {
      button.addEventListener('click', function() {
        if (confirm('Are you sure you want to delete this scheduled report?')) {
          // Send delete request to server
          const id = this.getAttribute('data-id');
          // Implement AJAX call to delete_scheduled_report.php with id parameter
          alert('Scheduled report deleted successfully');
          // Then refresh the page or remove the row from the table
        }
      });
    });
    
    // View report action
    document.querySelectorAll('.view-report').forEach(button => {
      button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        window.open('view_report.php?id=' + id, '_blank');
      });
    });
  </script>
</body>
</html>