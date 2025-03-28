<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
}

// Check if employee ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: EmployeeDirectoryPage.php");
    exit;
}

$employee_id = $_GET['id'];

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Admin User';

// Get employee details
$sql = "SELECT * FROM users WHERE id = ? AND role = 'employee'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: EmployeeDirectoryPage.php");
    exit;
}

$employee = $result->fetch_assoc();

// Get attendance statistics
$current_year = date('Y');
$current_month = date('m');

// Current month attendance
$attendance_sql = "SELECT 
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN clock_out IS NOT NULL THEN 1 ELSE 0 END) AS completed_days,
                    SUM(CASE WHEN TIMEDIFF(clock_in, CONCAT(DATE(clock_in), ' 09:00:00')) > '00:15:00' THEN 1 ELSE 0 END) AS late_days
                FROM users_attendance 
                WHERE user_id = ? 
                AND MONTH(clock_in) = ? 
                AND YEAR(clock_in) = ?";

$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("iii", $employee_id, $current_month, $current_year);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
$attendance_stats = $attendance_result->fetch_assoc();

// Recent leave requests
$leave_sql = "SELECT * FROM leave_requests 
             WHERE user_id = ? 
             ORDER BY submitted_at DESC LIMIT 5";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $employee_id);
$leave_stmt->execute();
$leave_result = $leave_stmt->get_result();
$recent_leaves = [];
while ($leave_row = $leave_result->fetch_assoc()) {
    $recent_leaves[] = $leave_row;
}

// Recent attendance modifications
$modification_sql = "SELECT * FROM attendance_modifications 
                   WHERE user_id = ? 
                   ORDER BY created_at DESC LIMIT 5";
$modification_stmt = $conn->prepare($modification_sql);
$modification_stmt->bind_param("i", $employee_id);
$modification_stmt->execute();
$modification_result = $modification_stmt->get_result();
$recent_modifications = [];
while ($mod_row = $modification_result->fetch_assoc()) {
    $recent_modifications[] = $mod_row;
}

// Get last 7 days attendance
$recent_attendance_sql = "SELECT DATE(clock_in) as date, 
                        TIME(clock_in) as clock_in_time, 
                        TIME(clock_out) as clock_out_time,
                        TIMEDIFF(TIME(clock_out), TIME(clock_in)) as hours_worked,
                        CASE 
                            WHEN TIMEDIFF(TIME(clock_in), '09:00:00') > '00:15:00' THEN 'Late' 
                            ELSE 'On Time' 
                        END as status
                    FROM users_attendance 
                    WHERE user_id = ? 
                    ORDER BY date DESC LIMIT 7";
$recent_attendance_stmt = $conn->prepare($recent_attendance_sql);
$recent_attendance_stmt->bind_param("i", $employee_id);
$recent_attendance_stmt->execute();
$recent_attendance_result = $recent_attendance_stmt->get_result();
$recent_attendance = [];
while ($att_row = $recent_attendance_result->fetch_assoc()) {
    $recent_attendance[] = $att_row;
}

// Close connection
$conn->close();

// Helper function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Helper function to calculate leave duration
function calculateLeaveDays($start, $end) {
    $start_date = new DateTime($start);
    $end_date = new DateTime($end);
    $interval = $start_date->diff($end_date);
    return $interval->days + 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Profile | Attendance System</title>
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
    top: var(--header-height);
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

    /* Page Header and Actions */
    .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    }

    .page-header .actions {
    display: flex;
    gap: 10px;
    }

    /* Profile Container Styles */
    .profile-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    }

    .profile-header {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: var(--primary-color);
    color: white;
    }

    .profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    overflow: hidden;
    }

    .profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    }

    .profile-avatar i {
    font-size: 2rem;
    color: var(--primary-color);
    }

    .profile-summary h2 {
    margin-bottom: 10px;
    }

    .profile-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    }

    .profile-details .detail {
    display: flex;
    align-items: center;
    gap: 5px;
    background-color: rgba(255, 255, 255, 0.2);
    padding: 5px 10px;
    border-radius: 15px;
    }

    /* Profile Content */
    .profile-content {
    padding: 20px;
    }

    .profile-section {
    margin-bottom: 30px;
    }

    .profile-section h3 {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
    margin-bottom: 15px;
    }

    /* Info Grid */
    .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    }

    .info-item {
    padding: 10px;
    border-radius: 4px;
    background-color: var(--light-bg);
    }

    .info-item label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--primary-color);
    }

    .full-width {
    grid-column: 1 / -1;
    }

    /* Leave Balance */
    .leave-balance-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    }

    .leave-balance {
    background-color: var(--light-bg);
    border-radius: 4px;
    padding: 15px;
    text-align: center;
    }

    .leave-type {
    font-weight: 600;
    margin-bottom: 5px;
    }

    .leave-value {
    font-size: 1.5rem;
    color: var(--primary-color);
    }

    /* Stats Container */
    .stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    }

    .stat-card {
    background-color: var(--light-bg);
    border-radius: 4px;
    padding: 15px;
    display: flex;
    align-items: center;
    }

    .stat-icon {
    margin-right: 15px;
    font-size: 2rem;
    color: var(--primary-color);
    }

    .stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 5px;
    }

    /* Table Styles */
    .data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    }

    .data-table th, 
    .data-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    }

    .data-table th {
    background-color: var(--light-bg);
    font-weight: 600;
    }

    .data-table .no-data {
    text-align: center;
    color: #777;
    padding: 20px;
    }

    /* Status Colors */
    .data-table .on-time {
    color: var(--secondary-color);
    }

    .data-table .late {
    color: var(--warning-color);
    }

    .status-approved {
    color: var(--secondary-color);
    }

    .status-pending {
    color: var(--warning-color);
    }

    .status-rejected {
    color: var(--danger-color);
    }

    .truncate {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    }

    /* Button Styles */
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

    /* Responsive adjustments */
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
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .page-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .info-grid,
    .leave-balance-container,
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Employee Profile</div>
    <div class="user-profile" id="userProfileBtn">
      <img src="/api/placeholder/32/32" alt="<?php echo htmlspecialchars($user_name); ?>">
      <span><?php echo htmlspecialchars($user_name); ?></span>
      <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
    </div>
    <div class="user-dropdown" id="userDropdown">
      <ul>
        <li><a href="#"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
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
        <li><a href="./EmployeeDirectoryPage.php" class="active"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
        <li><a href="./AdminLeaveRequestManagementPage.html"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
        <li><a href="./AttendanceModificationManagementPage.html"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.html"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.html"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-header">
        <h1>Employee Profile</h1>
        <div class="actions">
          <a href="EmployeeDirectoryPage.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Directory
          </a>
          <a href="EditEmployee.php?id=<?php echo $employee_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Profile
          </a>
        </div>
      </div>
      
      <div class="profile-container">
        <div class="profile-header">
          <div class="profile-avatar">
            <?php if (!empty($employee['profile_picture']) && $employee['profile_picture'] != 'default-profile.png'): ?>
              <img src="/projectweb/Employee/uploads/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>" alt="Profile Picture">
            <?php else: ?>
              <i class="fas fa-user"></i>
            <?php endif; ?>
          </div>
          <div class="profile-summary">
            <h2><?php echo htmlspecialchars($employee['name']); ?></h2>
            <div class="profile-details">
              <div class="detail">
                <i class="fas fa-id-card"></i>
                <span>EMP<?php echo str_pad($employee['id'], 3, '0', STR_PAD_LEFT); ?></span>
              </div>
              <div class="detail">
                <i class="fas fa-building"></i>
                <span><?php echo !empty($employee['department']) ? htmlspecialchars($employee['department']) : 'Unassigned'; ?></span>
              </div>
              <div class="detail">
                <i class="fas fa-calendar-check"></i>
                <span>Joined: <?php echo !empty($employee['join_date']) ? formatDate($employee['join_date']) : 'N/A'; ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="profile-content">
          <div class="profile-section">
            <h3>Personal Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <label>Full Name</label>
                <div><?php echo htmlspecialchars($employee['name']); ?></div>
              </div>
              <div class="info-item">
                <label>Username</label>
                <div><?php echo htmlspecialchars($employee['username']); ?></div>
              </div>
              <div class="info-item">
                <label>Email</label>
                <div><?php echo htmlspecialchars($employee['email']); ?></div>
              </div>
              <div class="info-item">
                <label>Phone</label>
                <div><?php echo !empty($employee['phone']) ? htmlspecialchars($employee['phone']) : 'Not provided'; ?></div>
              </div>
              <div class="info-item">
                <label>Department</label>
                <div><?php echo !empty($employee['department']) ? htmlspecialchars($employee['department']) : 'Unassigned'; ?></div>
              </div>
              <div class="info-item">
                <label>Manager</label>
                <div><?php echo !empty($employee['manager']) ? htmlspecialchars($employee['manager']) : 'Unassigned'; ?></div>
              </div>
              <div class="info-item full-width">
                <label>Address</label>
                <div><?php echo !empty($employee['address']) ? htmlspecialchars($employee['address']) : 'Not provided'; ?></div>
              </div>
              <div class="info-item full-width">
                <label>Emergency Contact</label>
                <div><?php echo !empty($employee['emergency_contact']) ? htmlspecialchars($employee['emergency_contact']) : 'Not provided'; ?></div>
              </div>
            </div>
          </div>

          <div class="profile-section">
            <h3>Leave Balance</h3>
            <div class="leave-balance-container">
              <div class="leave-balance">
                <div class="leave-type">Annual Leave</div>
                <div class="leave-value"><?php echo $employee['annual_leave_balance'] ?? 0; ?> days</div>
              </div>
              <div class="leave-balance">
                <div class="leave-type">Sick Leave</div>
                <div class="leave-value"><?php echo $employee['sick_leave_balance'] ?? 0; ?> days</div>
              </div>
              <div class="leave-balance">
                <div class="leave-type">Personal Leave</div>
                <div class="leave-value"><?php echo $employee['personal_leave_balance'] ?? 0; ?> days</div>
              </div>
              <div class="leave-balance">
                <div class="leave-type">Unpaid Leave</div>
                <div class="leave-value"><?php echo $employee['unpaid_leave_balance'] ?? 0; ?> days</div>
              </div>
            </div>
          </div>

          <div class="profile-section">
            <h3>Attendance Statistics (Current Month)</h3>
            <div class="stats-container">
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-value"><?php echo $attendance_stats['completed_days'] ?? 0; ?></div>
                  <div class="stat-label">Days Present</div>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-value"><?php echo ($attendance_stats['total_days'] - $attendance_stats['completed_days']) ?? 0; ?></div>
                  <div class="stat-label">Days Absent</div>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-value"><?php echo $attendance_stats['late_days'] ?? 0; ?></div>
                  <div class="stat-label">Late Arrivals</div>
                </div>
              </div>
            </div>
          </div>

          <div class="profile-section">
            <h3>Recent Attendance</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Clock In</th>
                  <th>Clock Out</th>
                  <th>Hours Worked</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_attendance)): ?>
                  <tr>
                    <td colspan="5" class="no-data">No attendance records found for this period.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_attendance as $attendance): ?>
                    <tr>
                      <td><?php echo formatDate($attendance['date']); ?></td>
                      <td><?php echo $attendance['clock_in_time'] ?? 'N/A'; ?></td>
                      <td><?php echo $attendance['clock_out_time'] ?? 'Not Clocked Out'; ?></td>
                      <td><?php echo $attendance['hours_worked'] ?? 'N/A'; ?></td>
                      <td class="<?php echo strtolower($attendance['status']); ?>"><?php echo $attendance['status']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="profile-section">
            <h3>Recent Leave Requests</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Duration</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_leaves)): ?>
                  <tr>
                    <td colspan="5" class="no-data">No leave requests found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_leaves as $leave): ?>
                    <tr>
                      <td><?php echo htmlspecialchars(ucfirst($leave['leave_type'])); ?></td>
                      <td><?php echo formatDate($leave['start_date']); ?></td>
                      <td><?php echo formatDate($leave['end_date']); ?></td>
                      <td><?php echo calculateLeaveDays($leave['start_date'], $leave['end_date']); ?> days</td>
                      <td class="status-<?php echo strtolower($leave['status']); ?>"><?php echo ucfirst($leave['status']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="profile-section">
            <h3>Recent Attendance Modifications</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Time</th>
                  <th>Reason</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_modifications)): ?>
                  <tr>
                    <td colspan="5" class="no-data">No modification requests found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_modifications as $mod): ?>
                    <tr>
                      <td><?php echo formatDate($mod['modification_date']); ?></td>
                      <td><?php echo htmlspecialchars(ucfirst($mod['modification_type'])); ?></td>
                      <td><?php echo $mod['modification_time'] ?? 'N/A'; ?></td>
                      <td class="truncate"><?php echo htmlspecialchars($mod['reason']); ?></td>
                      <td class="status-<?php echo strtolower($mod['status']); ?>"><?php echo ucfirst($mod['status']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
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
  </script>
</body>
</html>