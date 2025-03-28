<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "attendance_management");
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

// Get admin information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Admin User';

// Fetch admin profile picture
$admin_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_query->bind_param("i", $user_id);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$profile_picture_path = getProfilePicturePath($admin_data['profile_picture'], 'administration');

// Search and filter parameters
$search_query = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build employee query
$sql = "SELECT id, name, username, email, department, role, profile_picture 
        FROM users WHERE role = 'employee'";

$params = [];
$param_types = '';

// Add search conditions
if (!empty($search_query)) {
    $search_query = "%{$search_query}%";
    $sql .= " AND (name LIKE ? OR id LIKE ? OR department LIKE ? OR role LIKE ?)";
    $params = array_merge($params, [$search_query, $search_query, $search_query, $search_query]);
    $param_types .= 'ssss';
}

// Add department filter
if (!empty($department_filter)) {
    $sql .= " AND department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
}

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Get unique departments
$departments = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''")
    ->fetch_all(MYSQLI_ASSOC);
$departments = array_column($departments, 'department');

$conn->close();
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
  </style>
</head>
<body>
    <header>
        <div class="logo">AttendX</div>
        <div class="app-title">Employee Directory</div>
        <div class="user-profile" id="userProfileBtn">
            <img src="<?= htmlspecialchars($profile_picture_path) ?>" alt="<?= htmlspecialchars($user_name) ?>">
            <span><?= htmlspecialchars($user_name) ?></span>
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
                <li><a href="./EmployeeDirectoryPage.php" class="active"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
                <li><a href="./AdminLeaveRequestManagementPage.php"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
                <li><a href="./AttendanceModificationManagementPage.php"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
                <li><a href="./PenaltyManagementPage.php"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
                <li><a href="./ReportsGenerationPage.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="page-title">
                <h1>Employee Directory</h1>
            </div>
            
            <div class="card">
                <div class="card-header">Employee Search</div>
                <div class="card-body">
                    <form action="EmployeeDirectoryPage.php" method="GET" class="search-form">
                        <div class="search-container">
                            <input type="text" name="search" placeholder="Search by name, ID, position..." 
                                   value="<?= htmlspecialchars($search_query) ?>">
                            <button type="submit" class="btn">Search</button>
                        </div>
                        <div class="form-group">
                            <label>Department Filter</label>
                            <select name="department" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" 
                                        <?= $department_filter == $dept ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Employee List
                    <button class="btn btn-secondary" id="addEmployeeBtn">
                        <i class="fas fa-plus"></i> Add New Employee
                    </button>
                </div>
                <div class="card-body">
                    <div class="employee-list">
                        <?php if (empty($employees)): ?>
                            <div class="no-results">
                                <p>No employees found. Try adjusting your search criteria.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): 
                                $emp_profile_path = getProfilePicturePath($employee['profile_picture']); 
                            ?>
                                <div class="employee-card">
                                    <div class="employee-avatar">
                                        <img src="<?= htmlspecialchars($emp_profile_path) ?>" 
                                             alt="<?= htmlspecialchars($employee['name']) ?>"
                                             onerror="this.src='/projectweb/Employee/uploads/profile_pictures/default-profile.png'">
                                    </div>
                                    <div class="employee-info">
                                        <div class="employee-name"><?= htmlspecialchars($employee['name']) ?></div>
                                        <div class="employee-position">
                                            <?= !empty($employee['role']) ? htmlspecialchars(ucfirst($employee['role'])) : 'Employee' ?> - 
                                            <?= !empty($employee['department']) ? htmlspecialchars($employee['department']) : 'Unassigned' ?>
                                        </div>
                                        <div class="employee-id">ID: EMP<?= str_pad($employee['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                    <div class="employee-actions">
                                        <a href="ViewEmployeeProfile.php?id=<?= $employee['id'] ?>" class="btn">View Profile</a>
                                        <a href="EditEmployee.php?id=<?= $employee['id'] ?>" class="btn btn-secondary">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($employees) >= 10): ?>
                    <div class="text-center mt-20">
                        <button class="btn" id="loadMoreBtn">Load More</button>
                    </div>
                    <?php endif; ?>
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
        
        // Load more functionality - placeholder for pagination
        if (document.getElementById('loadMoreBtn')) {
            document.getElementById('loadMoreBtn').addEventListener('click', function() {
                // This would typically make an AJAX call to load more employees
                alert('This functionality would load more employees with pagination');
            });
        }
    </script>
</body>
</html>