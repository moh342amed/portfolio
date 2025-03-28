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
$sql = "SELECT p.*, u.name as employee_name, u.department 
         FROM penalties p
         JOIN users u ON p.user_id = u.id
         WHERE p.id = ? AND p.status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $penalty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Penalty not found or not active
    $conn->close();
    header("Location: PenaltyManagementPage.php");
    exit;
}

$penalty = $result->fetch_assoc();

// Format penalty type for display
$penalty_type_formatted = str_replace('_', ' ', ucwords($penalty['penalty_type']));

// Fetch employees for dropdown
$sql_employees = "SELECT id, name, department FROM users ORDER BY name";
$result_employees = $conn->query($sql_employees);
$employees = [];
if ($result_employees && $result_employees->num_rows > 0) {
    while($row = $result_employees->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Handle form submission
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_penalty') {
    $penalty_type = $_POST['penalty_type'] ?? '';
    $incident_date = $_POST['incident_date'] ?? '';
    $severity = $_POST['severity'] ?? '';
    $description = $_POST['description'] ?? '';
    $penalty_action = $_POST['penalty_action'] ?? '';
    $notify_employee = isset($_POST['notify_employee']) ? 1 : 0;
    $notify_manager = isset($_POST['notify_manager']) ? 1 : 0;
    
    // Check if any notifications are being sent for the first time
    $new_employee_notification = ($notify_employee == 1 && $penalty['notify_employee'] == 0);
    $new_manager_notification = ($notify_manager == 1 && $penalty['notify_manager'] == 0);
    
    // Validate required fields
    if (empty($penalty_type) || empty($incident_date) || 
        empty($severity) || empty($description) || empty($penalty_action)) {
        $error_message = "All required fields must be filled out.";
    } else {
        // Process file upload if provided
        $documents = $penalty['documents']; // Default to existing document
        
        if(isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
            $target_dir = "uploads/penalties/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION);
            $new_filename = "penalty_" . $penalty_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check file size (5MB limit)
            if ($_FILES["attachment"]["size"] > 5000000) {
                $error_message = "File is too large. Maximum size is 5MB.";
            } else if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $documents = $new_filename;
            } else {
                $error_message = "Error uploading file.";
            }
        }
        
        if (empty($error_message)) {
            // Update penalty in database
            $sql = "UPDATE penalties SET 
                    penalty_type = ?, 
                    incident_date = ?, 
                    severity = ?, 
                    description = ?, 
                    penalty_action = ?, 
                    notify_employee = ?, 
                    notify_manager = ?, 
                    documents = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $penalty_type, $incident_date, $severity, 
                            $description, $penalty_action, $notify_employee, 
                            $notify_manager, $documents, $penalty_id);
            
            if ($stmt->execute()) {
                $success_message = "Penalty updated successfully!";
                
                // Send new notifications if applicable
                if ($new_employee_notification) {
                    $employee_id = $penalty['user_id'];
                    $title = "Penalty Update";
                    $message = "A $severity severity penalty for $penalty_type on $incident_date has been updated.";
                    
                    $notify_sql = "INSERT INTO notifications (user_id, title, message, created_at) 
                                  VALUES (?, ?, ?, NOW())";
                    $notify_stmt = $conn->prepare($notify_sql);
                    $notify_stmt->bind_param("iss", $employee_id, $title, $message);
                    $notify_stmt->execute();
                }
                
                // If manager notification is enabled and wasn't before, notify manager
                if ($new_manager_notification) {
                    $sql_manager = "SELECT manager FROM users WHERE id = ?";
                    $stmt_manager = $conn->prepare($sql_manager);
                    $stmt_manager->bind_param("i", $penalty['user_id']);
                    $stmt_manager->execute();
                    $result_manager = $stmt_manager->get_result();
                    
                    if ($result_manager->num_rows > 0) {
                        $manager_row = $result_manager->fetch_assoc();
                        if (!empty($manager_row['manager'])) {
                            $manager_name = $manager_row['manager'];
                            
                            // Get manager ID
                            $sql_manager_id = "SELECT id FROM users WHERE name = ?";
                            $stmt_manager_id = $conn->prepare($sql_manager_id);
                            $stmt_manager_id->bind_param("s", $manager_name);
                            $stmt_manager_id->execute();
                            $result_manager_id = $stmt_manager_id->get_result();
                            
                            if ($result_manager_id->num_rows > 0) {
                                $manager_id_row = $result_manager_id->fetch_assoc();
                                $manager_id = $manager_id_row['id'];
                                
                                // Create notification for manager
                                $title = "Penalty Update For Team Member";
                                $message = "A $severity severity penalty for " . $penalty['employee_name'] . " has been updated.";
                                
                                $notify_sql = "INSERT INTO notifications (user_id, title, message, created_at) 
                                              VALUES (?, ?, ?, NOW())";
                                $notify_stmt = $conn->prepare($notify_sql);
                                $notify_stmt->bind_param("iss", $manager_id, $title, $message);
                                $notify_stmt->execute();
                            }
                        }
                    }
                }
                
                // Redirect to view page to avoid form resubmission
                header("Location: ViewPenalty.php?id=" . $penalty_id);
                exit;
            } else {
                $error_message = "Error updating penalty: " . $conn->error;
            }
        }
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
  <title>Edit Penalty - Attendance System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    /* CSS Variables for consistent theming */
    :root {
      --primary-color: #4a6cf7;
      --secondary-color: #6E8A9E;
      --danger-color: #ff3e61;
      --success-color: #4caf50;
      --warning-color: #ff9800;
      --dark-color: #323a47;
      --light-color: #f5f7fb;
      --text-color: #333;
      --border-color: #e0e0e0;
      --sidebar-width: 250px;
      --sidebar-collapsed: 70px;
      --header-height: 60px;
      --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      --transition-speed: 0.3s;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: #f5f7fb;
      color: var(--text-color);
    }

    /* Header Styles */
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      height: var(--header-height);
      background-color: #fff;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
    }

    .logo {
      font-size: 1.5rem;
      font-weight: bold;
      color: var(--primary-color);
    }

    .app-title {
      font-size: 1.2rem;
      color: var(--secondary-color);
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
      margin-right: 8px;
    }

    .user-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      border-radius: 4px;
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

    .user-dropdown li a {
      display: flex;
      align-items: center;
      padding: 10px 15px;
      color: var(--text-color);
      text-decoration: none;
      transition: background-color 0.2s;
    }

    .user-dropdown li a:hover {
      background-color: #f5f5f5;
    }

    .user-dropdown li a i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }

    /* Layout */
    .layout {
      display: flex;
      margin-top: var(--header-height);
      min-height: calc(100vh - var(--header-height));
    }

    /* Sidebar */
    .sidebar {
      width: var(--sidebar-width);
      background-color: white;
      height: calc(100vh - var(--header-height));
      position: fixed;
      left: 0;
      top: var(--header-height);
      box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
      transition: width var(--transition-speed);
      z-index: 99;
    }

    .sidebar.collapsed {
      width: var(--sidebar-collapsed);
    }

    .sidebar ul {
      list-style: none;
      padding: 20px 0;
    }

    .sidebar li a {
      display: flex;
      align-items: center;
      padding: 15px 20px;
      color: var(--text-color);
      text-decoration: none;
      transition: background-color 0.2s;
      white-space: nowrap;
      overflow: hidden;
    }

    .sidebar li a.active {
      background-color: #f0f4ff;
      color: var(--primary-color);
      border-left: 3px solid var(--primary-color);
    }

    .sidebar li a:hover {
      background-color: #f5f7fb;
    }

    .sidebar li a i {
      margin-right: 15px;
      width: 20px;
      text-align: center;
    }

    .sidebar.collapsed li a span {
      display: none;
    }

    .toggle-btn {
      position: absolute;
      top: 10px;
      right: -10px;
      background: white;
      border: none;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 100;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: var(--sidebar-width);
      padding: 20px;
      transition: margin-left var(--transition-speed);
    }

    /* Page Title */
    .page-title {
      margin-bottom: 20px;
    }

    .page-title h1 {
      font-size: 1.8rem;
      color: var(--dark-color);
    }

    /* Card Styling */
    .card {
      background: white;
      border-radius: 8px;
      box-shadow: var(--card-shadow);
      margin-bottom: 20px;
      overflow: hidden;
    }

    .card-header {
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border-color);
      background-color: #fafafa;
      font-weight: 600;
    }

    .card-body {
      padding: 20px;
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }

    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="time"],
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px 15px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .form-group input[type="text"]:focus,
    .form-group input[type="date"]:focus,
    .form-group input[type="time"]:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-color);
    }

    .form-group input[type="file"] {
      border: 1px solid var(--border-color);
      border-radius: 4px;
      padding: 10px;
      width: 100%;
    }

    .form-group small {
      display: block;
      margin-top: 5px;
      color: var(--secondary-color);
    }

    /* Grid Layout */
    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 20px;
    }

    .col-6 {
      grid-column: span 6;
    }

    .col-12 {
      grid-column: span 12;
    }

    /* Button Styles */
    .btn {
      display: inline-block;
      padding: 10px 20px;
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      font-weight: 500;
      transition: background-color 0.2s;
    }

    .btn:hover {
      background-color: #3a5cd8;
    }

    .btn-secondary {
      background-color: var(--secondary-color);
    }

    .btn-secondary:hover {
      background-color: #5a788c;
    }

    .btn-danger {
      background-color: var(--danger-color);
    }

    .btn-danger:hover {
      background-color: #e5354d;
    }

    /* Alerts */
    .alert {
      padding: 15px 20px;
      border-radius: 4px;
      margin-bottom: 20px;
    }

    .alert-success {
      background-color: #e8f5e9;
      border-left: 4px solid var(--success-color);
      color: #2e7d32;
    }

    .alert-danger {
      background-color: #ffebee;
      border-left: 4px solid var(--danger-color);
      color: #c62828;
    }

    /* Utilities */
    .text-right {
      text-align: right;
    }

    .mt-10 {
      margin-top: 10px;
    }

    .mt-20 {
      margin-top: 20px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .col-6 {
        grid-column: span 12;
      }

      .sidebar {
        width: var(--sidebar-collapsed);
      }

      .sidebar li a span {
        display: none;
      }

      .main-content {
        margin-left: var(--sidebar-collapsed);
        padding: 15px;
      }

      .sidebar.mobile-open {
        width: var(--sidebar-width);
      }

      .sidebar.mobile-open li a span {
        display: inline;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Edit Penalty</div>
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
        <h1>Edit Penalty</h1>
      </div>
      
      <?php if(!empty($success_message)): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
      <?php endif; ?>
      
      <?php if(!empty($error_message)): ?>
      <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <div class="card">
        <div class="card-header">
          <div>Edit Penalty for <?php echo htmlspecialchars($penalty['employee_name']); ?></div>
        </div>
        <div class="card-body">
          <form method="POST" action="EditPenalty.php?id=<?php echo $penalty_id; ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_penalty">
            <div class="grid">
              <div class="col-6">
                <div class="form-group">
                  <label>Employee</label>
                  <input type="text" value="<?php echo htmlspecialchars($penalty['employee_name']); ?> - <?php echo htmlspecialchars($penalty['department']); ?>" disabled>
                </div>
              </div>
              <div class="col-6">
                <div class="form-group">
                  <label>Penalty Type*</label>
                  <select name="penalty_type" required>
                    <option value="">Select Penalty Type</option>
                    <option value="late_arrival" <?php echo $penalty['penalty_type'] === 'late_arrival' ? 'selected' : ''; ?>>Late Arrival</option>
                    <option value="early_departure" <?php echo $penalty['penalty_type'] === 'early_departure' ? 'selected' : ''; ?>>Early Departure</option>
                    <option value="unauth_absence" <?php echo $penalty['penalty_type'] === 'unauth_absence' ? 'selected' : ''; ?>>Unauthorized Absence</option>
                    <option value="performance" <?php echo $penalty['penalty_type'] === 'performance' ? 'selected' : ''; ?>>Performance Issue</option>
                    <option value="policy_violation" <?php echo $penalty['penalty_type'] === 'policy_violation' ? 'selected' : ''; ?>>Policy Violation</option>
                  </select>
                </div>
              </div>
              <div class="col-6">
                <div class="form-group">
                  <label>Date of Incident*</label>
                  <input type="date" name="incident_date" value="<?php echo htmlspecialchars($penalty['incident_date']); ?>" required>
                </div>
              </div>
              <div class="col-6">
                <div class="form-group">
                  <label>Severity*</label>
                  <select name="severity" required>
                    <option value="">Select Severity</option>
                    <option value="low" <?php echo $penalty['severity'] === 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo $penalty['severity'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo $penalty['severity'] === 'high' ? 'selected' : ''; ?>>High</option>
                  </select>
                </div>
              </div>
              <div class="col-12">
                <div class="form-group">
                  <label>Description*</label>
                  <textarea name="description" rows="5" placeholder="Describe the incident and reason for penalty..." required><?php echo htmlspecialchars($penalty['description']); ?></textarea>
                </div>
              </div>
              <div class="col-12">
                <div class="form-group">
                  <label>Penalty Action*</label>
                  <select name="penalty_action" required>
                    <option value="">Select Action</option>
                    <option value="warning" <?php echo $penalty['penalty_action'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="formal_warning" <?php echo $penalty['penalty_action'] === 'formal_warning' ? 'selected' : ''; ?>>Formal Warning</option>
                    <option value="final_warning" <?php echo $penalty['penalty_action'] === 'final_warning' ? 'selected' : ''; ?>>Final Warning</option>
                    <option value="suspension" <?php echo $penalty['penalty_action'] === 'suspension' ? 'selected' : ''; ?>>Suspension</option>
                    <option value="pay_deduction" <?php echo $penalty['penalty_action'] === 'pay_deduction' ? 'selected' : ''; ?>>Pay Deduction</option>
                  </select>
                </div>
              </div>
              <div class="col-12">
                <div class="form-group">
                  <label>Documents</label>
                  <?php if (!empty($penalty['documents'])): ?>
                  <div style="margin-bottom: 10px;">
                    <p>Current Document: <?php echo htmlspecialchars($penalty['documents']); ?></p>
                    <a href="uploads/penalties/<?php echo htmlspecialchars($penalty['documents']); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                      <i class="fas fa-file-download"></i> View Document
                    </a>
                  </div>
                  <?php endif; ?>
                  <input type="file" name="attachment">
                  <small>Optional: Attach new supporting documents (max 5MB). Leave empty to keep existing document.</small>
                </div>
              </div>
              <div class="col-12">
                <div class="form-group">
                  <label>Send Notification</label>
                  <div>
                    <input type="checkbox" id="notifyEmployee" name="notify_employee" <?php echo $penalty['notify_employee'] ? 'checked' : ''; ?>>
                    <label for="notifyEmployee" style="display: inline;">Notify Employee</label>
                  </div>
                  <div>
                    <input type="checkbox" id="notifyManager" name="notify_manager" <?php echo $penalty['notify_manager'] ? 'checked' : ''; ?>>
                    <label for="notifyManager" style="display: inline;">Notify Department Manager</label>
                  </div>
                  <small>Note: New notifications will be sent only if checkboxes that were previously unchecked are now checked.</small>
                </div>
              </div>
            </div>
            
            <div class="text-right mt-20">
              <a href="ViewPenalty.php?id=<?php echo $penalty_id; ?>" class="btn btn-secondary" style="margin-right: 10px;">Cancel</a>
              <button type="submit" class="btn">Update Penalty</button>
            </div>
          </form>
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
    
    // Show success message temporarily
    <?php if(!empty($success_message)): ?>
    setTimeout(function() {
      document.querySelector('.alert-success').style.display = 'none';
    }, 5000);
    <?php endif; ?>
  </script>
</body>
</html>