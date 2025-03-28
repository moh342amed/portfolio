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

// Get admin information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Admin User';

// Check if employee ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: EmployeeDirectoryPage.php");
    exit;
}

$employee_id = $_GET['id'];
$success_message = '';
$error_message = '';

// Process form submission if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $department = $_POST['department'];
    $join_date = $_POST['join_date'] ?? null;
    $address = $_POST['address'] ?? null;
    $emergency_contact = $_POST['emergency_contact'] ?? null;

    // Handle department selection
    if ($department === 'other' && !empty($_POST['newDepartment'])) {
        $department = $_POST['newDepartment'];
    }

    // Check if username or email already exists (excluding current employee)
    $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssi", $username, $email, $employee_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error_message = "Username or email already exists for another employee!";
    } else {
        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET name = ?, username = ?, email = ?, phone = ?, department = ?, 
                          join_date = ?, address = ?, emergency_contact = ?, password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssssssi", $name, $username, $email, $phone, $department, 
                                 $join_date, $address, $emergency_contact, $hashed_password, $employee_id);
        } else {
            $update_sql = "UPDATE users SET name = ?, username = ?, email = ?, phone = ?, department = ?, 
                          join_date = ?, address = ?, emergency_contact = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssssi", $name, $username, $email, $phone, $department, 
                                 $join_date, $address, $emergency_contact, $employee_id);
        }

        if ($update_stmt->execute()) {
            $success_message = "Employee information updated successfully!";
        } else {
            $error_message = "Error updating employee information: " . $conn->error;
        }
    }
}

// Fetch employee data
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

// Get unique departments for the dropdown
$dept_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''";
$dept_result = $conn->query($dept_sql);
$departments = [];

while ($dept_row = $dept_result->fetch_assoc()) {
    if (!empty($dept_row['department'])) {
        $departments[] = $dept_row['department'];
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
  <title>Edit Employee | Attendance System</title>
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

    /* Page Title and Header */
    .page-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    }

    .page-title h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark-text);
    }

    /* Card Styles */
    .card {
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    margin-bottom: 20px;
    }

    .mt-20 {
    margin-top: 20px;
    }

    .mt-15 {
    margin-top: 15px;
    }

    .card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    }

    .card-body {
    padding: 20px;
    }

    /* Form Styles */
    .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
    }

    .form-group {
    margin-bottom: 15px;
    }

    .form-group.hidden {
    display: none;
    }

    .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    }

    .form-group input, 
    .form-group select, 
    .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
    }

    .form-group textarea {
    resize: vertical;
    }

    .form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    }

    /* Alert Styles */
    .alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 4px;
    font-size: 0.9rem;
    }

    .alert-success {
    background-color: rgba(46, 204, 113, 0.2);
    border: 1px solid var(--secondary-color);
    color: var(--secondary-dark);
    }

    .alert-danger {
    background-color: rgba(231, 76, 60, 0.2);
    border: 1px solid var(--danger-color);
    color: var(--danger-color);
    }

    /* Stats Grid */
    .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
    }

    .stat-item {
    background-color: var(--light-bg);
    padding: 15px;
    border-radius: 6px;
    text-align: center;
    }

    .stat-title {
    font-size: 0.9rem;
    margin-bottom: 5px;
    color: var(--dark-text);
    }

    .stat-value {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--primary-color);
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
    
    .page-title {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    }

    @media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Edit Employee</div>
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
      <div class="page-title">
        <h1>Edit Employee: <?php echo htmlspecialchars($employee['name']); ?></h1>
        <a href="EmployeeDirectoryPage.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to Directory
        </a>
      </div>
      
      <?php if (!empty($success_message)): ?>
      <div class="alert alert-success">
        <?php echo $success_message; ?>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger">
        <?php echo $error_message; ?>
      </div>
      <?php endif; ?>
      
      <div class="card">
        <div class="card-header">
          <div>Employee Information</div>
        </div>
        <div class="card-body">
          <form action="EditEmployee.php?id=<?php echo $employee_id; ?>" method="POST" id="editEmployeeForm">
            <div class="form-row">
              <div class="form-group">
                <label for="name">Full Name*</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
              </div>
              <div class="form-group">
                <label for="username">Username*</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($employee['username']); ?>" required>
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
              </div>
              <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label for="department">Department</label>
                <select id="department" name="department">
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $dept): ?>
                  <option value="<?php echo htmlspecialchars($dept); ?>" 
                    <?php echo ($employee['department'] == $dept) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept); ?>
                  </option>
                  <?php endforeach; ?>
                  <option value="other">Other (Add New)</option>
                </select>
              </div>
              <div class="form-group hidden" id="newDepartmentGroup">
                <label for="newDepartment">New Department Name</label>
                <input type="text" id="newDepartment" name="newDepartment">
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label for="join_date">Join Date</label>
                <input type="date" id="join_date" name="join_date" 
                  value="<?php echo $employee['join_date'] ?? ''; ?>">
              </div>
              <div class="form-group">
                <label for="password">New Password <small>(Leave empty to keep current)</small></label>
                <input type="password" id="password" name="password">
              </div>
            </div>
            
            <div class="form-group">
              <label for="address">Address</label>
              <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
              <label for="emergency_contact">Emergency Contact</label>
              <textarea id="emergency_contact" name="emergency_contact" rows="2"><?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Update Employee</button>
              <a href="EmployeeDirectoryPage.php" class="btn">Cancel</a>
            </div>
          </form>
        </div>
      </div>
      
      <div class="card mt-20">
        <div class="card-header">
          <div>Employee Stats</div>
        </div>
        <div class="card-body">
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-title">Annual Leave Balance</div>
              <div class="stat-value"><?php echo $employee['annual_leave_balance'] ?? 0; ?> days</div>
            </div>
            <div class="stat-item">
              <div class="stat-title">Sick Leave Balance</div>
              <div class="stat-value"><?php echo $employee['sick_leave_balance'] ?? 0; ?> days</div>
            </div>
            <div class="stat-item">
              <div class="stat-title">Personal Leave Balance</div>
              <div class="stat-value"><?php echo $employee['personal_leave_balance'] ?? 0; ?> days</div>
            </div>
            <div class="stat-item">
              <div class="stat-title">Present Days (This Month)</div>
              <div class="stat-value"><?php echo $employee['present_this_month'] ?? 0; ?> days</div>
            </div>
            <div class="stat-item">
              <div class="stat-title">Late Arrivals</div>
              <div class="stat-value"><?php echo $employee['late_arrivals'] ?? 0; ?></div>
            </div>
            <div class="stat-item">
              <div class="stat-title">Present / Absent</div>
              <div class="stat-value">
                <?php 
                $present = $employee['present_days'] ?? 0;
                $absent = $employee['absent_days'] ?? 0;
                echo $present . ' / ' . $absent;
                ?>
              </div>
            </div>
          </div>
          <div class="form-actions mt-15">
            <a href="ViewEmployeeProfile.php?id=<?php echo $employee_id; ?>" class="btn">View Full Profile</a>
            <a href="ResetLeaveBalance.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">Reset Leave Balance</a>
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
    
    // Department dropdown handler
    const departmentSelect = document.getElementById('department');
    const newDepartmentGroup = document.getElementById('newDepartmentGroup');
    
    departmentSelect.addEventListener('change', function() {
      if (this.value === 'other') {
        newDepartmentGroup.classList.remove('hidden');
      } else {
        newDepartmentGroup.classList.add('hidden');
      }
    });
    
    // Form validation
    document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
      const username = document.getElementById('username').value;
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      
      // Basic validation for password only if it's being changed
      if (password !== '' && password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
      }
      
      // If "other" is selected in department, check that new department is filled
      if (departmentSelect.value === 'other' && document.getElementById('newDepartment').value.trim() === '') {
        alert('Please enter a new department name');
        e.preventDefault();
        return false;
      }
    });
    
    // Mobile responsive behavior
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