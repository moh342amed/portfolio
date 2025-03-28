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

// Check if the penalty exists and is active
$check_sql = "SELECT p.*, u.name as employee_name, u.id as employee_id 
              FROM penalties p 
              JOIN users u ON p.user_id = u.id 
              WHERE p.id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $penalty_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    // Penalty not found
    $conn->close();
    header("Location: PenaltyManagementPage.php?error=penalty_not_found");
    exit;
}

$penalty = $result->fetch_assoc();

// Check if penalty is already resolved or revoked
if ($penalty['status'] !== 'active') {
    $conn->close();
    header("Location: PenaltyManagementPage.php?error=penalty_not_active");
    exit;
}

// Initialize error and success messages
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $revoke_reason = $_POST['revoke_reason'] ?? '';
    $notify_employee = isset($_POST['notify_employee']) ? 1 : 0;
    
    if (empty($revoke_reason)) {
        $error_message = "Please provide a reason for revoking this penalty.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update penalty status to revoked
            $update_sql = "UPDATE penalties SET 
                          status = 'revoked', 
                          resolved_by = ?,
                          resolved_date = CURDATE(),
                          updated_at = NOW()
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $user_id, $penalty_id);
            $update_stmt->execute();
            
            // Insert revocation comment in database (optional - you could add a comments table if needed)
            // For now, we'll just notify the employee if selected
            
            // Send notification to employee if selected
            if ($notify_employee) {
                $title = "Penalty Revoked";
                $message = "Your penalty for " . ucwords(str_replace('_', ' ', $penalty['penalty_type'])) . 
                           " issued on " . $penalty['issue_date'] . " has been revoked. Reason: " . $revoke_reason;
                
                $notify_sql = "INSERT INTO notifications (user_id, title, message, created_at) 
                              VALUES (?, ?, ?, NOW())";
                $notify_stmt = $conn->prepare($notify_sql);
                $notify_stmt->bind_param("iss", $penalty['employee_id'], $title, $message);
                $notify_stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message and redirect
            $success_message = "Penalty has been successfully revoked.";
            header("Location: PenaltyManagementPage.php?success=penalty_revoked");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error revoking penalty: " . $e->getMessage();
        }
    }
}

// Format penalty type for display
$penalty_type_formatted = str_replace('_', ' ', ucwords($penalty['penalty_type']));

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

// Close connection if not posting form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revoke Penalty - Attendance System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    /* CSS Variables for consistent theming */
    :root {
      --primary-color: #3498db;
      --secondary-color: #2c3e50;
      --warning-color: #f39c12;
      --danger-color: #e74c3c;
      --success-color: #2ecc71;
      --light-color: #ecf0f1;
      --dark-color: #34495e;
      --sidebar-width: 250px;
      --sidebar-collapsed: 70px;
      --header-height: 60px;
      --radius: 8px;
      --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      background-color: #f5f7fa;
      color: #333;
    }
    
    /* Header Styles */
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      height: var(--header-height);
      background-color: #fff;
      box-shadow: var(--shadow);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
    }
    
    .logo {
      font-size: 24px;
      font-weight: bold;
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
      object-fit: cover;
      margin-right: 10px;
    }
    
    .user-dropdown {
      position: absolute;
      top: 50px;
      right: 0;
      background-color: #fff;
      box-shadow: var(--shadow);
      border-radius: var(--radius);
      width: 200px;
      display: none;
      z-index: 101;
    }
    
    .user-dropdown.show {
      display: block;
    }
    
    .user-dropdown ul {
      list-style: none;
    }
    
    .user-dropdown ul li a {
      display: block;
      padding: 12px 15px;
      text-decoration: none;
      color: #333;
      transition: background-color 0.2s;
    }
    
    .user-dropdown ul li a:hover {
      background-color: #f5f7fa;
    }
    
    /* Layout Styles */
    .layout {
      display: flex;
      margin-top: var(--header-height);
      min-height: calc(100vh - var(--header-height));
    }
    
    /* Sidebar Styles */
    .sidebar {
      width: var(--sidebar-width);
      background-color: var(--secondary-color);
      color: #fff;
      position: fixed;
      top: var(--header-height);
      left: 0;
      bottom: 0;
      overflow-y: auto;
      transition: width 0.3s ease;
      z-index: 99;
    }
    
    .sidebar.collapsed {
      width: var(--sidebar-collapsed);
    }
    
    .toggle-btn {
      background-color: transparent;
      border: none;
      color: #fff;
      font-size: 18px;
      cursor: pointer;
      padding: 15px;
      width: 100%;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar ul {
      list-style: none;
      padding: 0;
    }
    
    .sidebar ul li a {
      display: flex;
      align-items: center;
      padding: 15px;
      text-decoration: none;
      color: rgba(255, 255, 255, 0.7);
      transition: background-color 0.2s, color 0.2s;
      white-space: nowrap;
      overflow: hidden;
    }
    
    .sidebar ul li a i {
      margin-right: 15px;
      min-width: 20px;
      text-align: center;
    }
    
    .sidebar ul li a:hover, .sidebar ul li a.active {
      background-color: rgba(255, 255, 255, 0.1);
      color: #fff;
    }
    
    .sidebar.collapsed ul li a span {
      display: none;
    }
    
    /* Main Content Styles */
    .main-content {
      flex: 1;
      padding: 20px;
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s ease;
    }
    
    .page-title {
      margin-bottom: 20px;
    }
    
    .page-title h1 {
      font-size: 24px;
      font-weight: 500;
      color: var(--dark-color);
    }
    
    /* Card Styles */
    .card {
      background-color: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 20px;
      overflow: hidden;
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 20px;
      border-bottom: 1px solid #eee;
      font-weight: 500;
      color: var(--dark-color);
      background-color: #fafafa;
    }
    
    .card-body {
      padding: 20px;
    }
    
    /* Form Styles */
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
    }
    
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="email"],
    .form-group input[type="password"],
    .form-group input[type="file"],
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: var(--radius);
      font-size: 14px;
      transition: border-color 0.2s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--primary-color);
      outline: none;
    }
    
    /* Button Styles */
    .btn {
      display: inline-block;
      padding: 10px 20px;
      background-color: var(--primary-color);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      transition: background-color 0.2s;
    }
    
    .btn:hover {
      background-color: #2980b9;
    }
    
    .btn-secondary {
      background-color: #95a5a6;
    }
    
    .btn-secondary:hover {
      background-color: #7f8c8d;
    }
    
    .btn-danger {
      background-color: var(--danger-color);
    }
    
    .btn-danger:hover {
      background-color: #c0392b;
    }
    
    /* Utility Classes */
    .text-right {
      text-align: right;
    }
    
    .text-center {
      text-align: center;
    }
    
    .mt-10 {
      margin-top: 10px;
    }
    
    .mt-20 {
      margin-top: 20px;
    }
    
    /* Alert Styles */
    .alert {
      padding: 15px;
      border-radius: var(--radius);
      margin-bottom: 20px;
    }
    
    .alert-success {
      background-color: #d5f5e3;
      color: #27ae60;
      border: 1px solid #2ecc71;
    }
    
    .alert-danger {
      background-color: #fadbd8;
      color: #c0392b;
      border: 1px solid #e74c3c;
    }
    
    /* Badge Styles */
    .badge {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-warning {
      background-color: #fef9e7;
      color: #f39c12;
      border: 1px solid #f1c40f;
    }
    
    .badge-success {
      background-color: #d5f5e3;
      color: #27ae60;
      border: 1px solid #2ecc71;
    }
    
    .badge-danger {
      background-color: #fadbd8;
      color: #c0392b;
      border: 1px solid #e74c3c;
    }
    
    /* Severity Classes */
    .penalty-severity {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .severity-low {
      background-color: #d5f5e3;
      color: #27ae60;
    }
    
    .severity-medium {
      background-color: #fef9e7;
      color: #f39c12;
    }
    
    .severity-high {
      background-color: #fadbd8;
      color: #c0392b;
    }
    
    /* Detail Styles */
    .detail-row {
      display: flex;
      margin-bottom: 15px;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
    }
    
    .detail-row:last-child {
      border-bottom: none;
    }
    
    .detail-label {
      flex: 0 0 200px;
      font-weight: 500;
      color: #7f8c8d;
    }
    
    .detail-value {
      flex: 1;
    }
    
    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 30px;
    }
    
    /* Responsive Adjustments */
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
      
      .action-buttons {
        flex-direction: column;
      }
      
      .action-buttons .btn {
        width: 100%;
        margin-bottom: 10px;
      }
    }
    
    /* Confirmation Section Styles */
    .confirmation-section {
      border-left: 4px solid var(--danger-color);
      padding-left: 20px;
      margin: 20px 0;
      background-color: #fff9f9;
      padding: 20px;
      border-radius: var(--radius);
    }
    
    .confirmation-section h3 {
      color: var(--danger-color);
      margin-bottom: 15px;
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Revoke Penalty</div>
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
        <h1>Revoke Penalty</h1>
      </div>
      
      <?php if(!empty($error_message)): ?>
      <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <?php if(!empty($success_message)): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
      <?php endif; ?>
      
      <div class="card">
        <div class="card-header">
          <div>Penalty Details</div>
        </div>
        <div class="card-body">
          <div class="detail-row">
            <div class="detail-label">Employee</div>
            <div class="detail-value"><?php echo htmlspecialchars($penalty['employee_name']); ?></div>
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
          
          <div class="confirmation-section">
            <h3><i class="fas fa-exclamation-triangle"></i> Revocation Confirmation</h3>
            <p>You are about to revoke this penalty. This action will remove the penalty from the employee's record and cannot be undone.</p>
            
            <form method="POST" action="">
              <div class="form-group">
                <label>Reason for Revocation*</label>
                <textarea name="revoke_reason" rows="4" placeholder="Provide a detailed reason for revoking this penalty..." required></textarea>
              </div>
              
              <div class="form-group">
                <div>
                  <input type="checkbox" id="notifyEmployee" name="notify_employee" checked>
                  <label for="notifyEmployee" style="display: inline;">Notify Employee about Revocation</label>
                </div>
              </div>
              
              <div class="action-buttons">
                <a href="ViewPenalty.php?id=<?php echo $penalty_id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Confirm Revocation</button>
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