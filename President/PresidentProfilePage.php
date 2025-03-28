<?php
// Start session to access user info
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

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Initialize success/error messages
$successMsg = '';
$errorMsg = '';

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $emergency_contact = trim($_POST['emergency_contact']);
    
    // Update personal information
    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, emergency_contact = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $name, $email, $phone, $address, $emergency_contact, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name; // Update session name
        $successMsg = "Profile information updated successfully!";
    } else {
        $errorMsg = "Failed to update profile. Please try again.";
    }

    // Handle password change if requested
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $sql_pw = "SELECT password FROM users WHERE id = ?";
        $stmt_pw = $conn->prepare($sql_pw);
        $stmt_pw->bind_param("i", $user_id);
        $stmt_pw->execute();
        $result_pw = $stmt_pw->get_result();
        $user_pw = $result_pw->fetch_assoc();
        
        if (password_verify($current_password, $user_pw['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update_pw = "UPDATE users SET password = ? WHERE id = ?";
                $stmt_update_pw = $conn->prepare($sql_update_pw);
                $stmt_update_pw->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt_update_pw->execute()) {
                    $successMsg .= " Password updated successfully!";
                } else {
                    $errorMsg = "Failed to update password. Please try again.";
                }
            } else {
                $errorMsg = "New password and confirmation do not match!";
            }
        } else {
            $errorMsg = "Current password is incorrect!";
        }
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filesize = $_FILES['profile_picture']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errorMsg = "Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed.";
        } elseif ($filesize > 5242880) { // 5MB max
            $errorMsg = "File size exceeds the maximum limit (5MB).";
        } else {
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = "user_" . $user_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $sql_pic = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt_pic = $conn->prepare($sql_pic);
                $stmt_pic->bind_param("si", $new_filename, $user_id);
                
                if ($stmt_pic->execute()) {
                    $successMsg .= " Profile picture updated successfully!";
                } else {
                    $errorMsg = "Failed to update profile picture in database.";
                }
            } else {
                $errorMsg = "Failed to upload profile picture.";
            }
        }
    }
}

// Fetch updated user information
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - HRMS</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <style>
    /* Base styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      background-color: #f5f6fa;
      color: #333;
      line-height: 1.6;
    }
    
    /* Header */
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
    
    .logo {
      font-weight: bold;
      font-size: 24px;
      color: #3498db;
    }
    
    .app-title {
      font-size: 18px;
      color: #555;
    }
    
    .user-menu {
      position: relative;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 5px 10px;
      border-radius: 5px;
      transition: background-color 0.3s;
    }
    
    .user-profile:hover {
      background-color: #f1f1f1;
    }
    
    .user-profile img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 10px;
      object-fit: cover;
      border: 2px solid #3498db;
    }
    
    .user-dropdown {
      position: absolute;
      top: 45px;
      right: 0;
      background-color: #fff;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 200px;
      display: none;
      z-index: 1000;
    }
    
    .user-dropdown.show {
      display: block;
    }
    
    .user-dropdown ul {
      list-style: none;
    }
    
    .user-dropdown ul li {
      padding: 0;
    }
    
    .user-dropdown ul li a {
      padding: 10px 15px;
      display: block;
      color: #333;
      text-decoration: none;
      transition: background-color 0.3s;
    }
    
    .user-dropdown ul li a:hover {
      background-color: #f5f6fa;
    }
    
    /* Layout */
    .layout {
      display: flex;
      margin-top: 70px;
    }
    
    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: #2c3e50;
      color: #fff;
      height: calc(100vh - 70px);
      position: fixed;
      transition: all 0.3s;
    }
    
    .sidebar.collapsed {
      width: 60px;
      padding-left: 0;
      padding-right: 0;
    }
    
    .toggle-btn {
      background: none;
      border: none;
      color: #fff;
      font-size: 18px;
      padding: 15px;
      cursor: pointer;
      width: 100%;
      text-align: right;
    }
    
    .sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .sidebar ul li {
      margin-bottom: 5px;
    }
    
    .sidebar ul li a {
      color: #fff;
      text-decoration: none;
      padding: 10px 15px;
      display: flex;
      align-items: center;
      transition: all 0.3s;
    }
    
    .sidebar ul li a i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }
    
    .sidebar ul li a:hover {
      background-color: #34495e;
    }
    
    .sidebar ul li a.active {
      background-color: #3498db;
    }
    
    .sidebar.collapsed ul li a span {
      display: none;
    }
    
    /* Main Content */
    .main-content {
      flex: 1;
      padding: 20px;
      margin-left: 250px;
      transition: all 0.3s;
    }
    
    .page-title {
      margin-bottom: 20px;
    }
    
    .page-title h1 {
      font-size: 24px;
      font-weight: 500;
      color: #2c3e50;
      border-left: 4px solid #3498db;
      padding-left: 10px;
    }
    
    /* Cards */
    .card {
      background-color: #fff;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      overflow: hidden;
    }
    
    .card-header {
      padding: 15px 20px;
      border-bottom: 1px solid #eee;
      font-weight: 500;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
      margin-bottom: 5px;
      font-weight: 500;
      color: #555;
    }
    
    .form-control {
      width: 100%;
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      transition: border-color 0.3s;
    }
    
    .form-control:focus {
      border-color: #3498db;
      outline: none;
    }
    
    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }
    
    /* Grid */
    .grid {
      display: flex;
      flex-wrap: wrap;
      margin: 0 -10px;
    }
    
    .col-6 {
      flex: 0 0 50%;
      max-width: 50%;
      padding: 0 10px;
    }
    
    .col-12 {
      flex: 0 0 100%;
      max-width: 100%;
      padding: 0 10px;
    }
    
    /* Buttons */
    .btn {
      display: inline-block;
      font-weight: 400;
      text-align: center;
      white-space: nowrap;
      vertical-align: middle;
      user-select: none;
      border: 1px solid transparent;
      padding: 0.375rem 0.75rem;
      font-size: 1rem;
      line-height: 1.5;
      border-radius: 0.25rem;
      transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
      cursor: pointer;
    }
    
    .btn-primary {
      color: #fff;
      background-color: #3498db;
      border-color: #3498db;
    }
    
    .btn-primary:hover {
      background-color: #2980b9;
      border-color: #2980b9;
    }
    
    .btn-secondary {
      color: #fff;
      background-color: #95a5a6;
      border-color: #95a5a6;
    }
    
    .btn-secondary:hover {
      background-color: #7f8c8d;
      border-color: #7f8c8d;
    }
    
    /* Alerts */
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border: 1px solid transparent;
      border-radius: 4px;
    }
    
    .alert-success {
      color: #155724;
      background-color: #d4edda;
      border-color: #c3e6cb;
    }
    
    .alert-danger {
      color: #721c24;
      background-color: #f8d7da;
      border-color: #f5c6cb;
    }
    
    /* Profile Section */
    .profile-header {
      position: relative;
      height: 150px;
      background-color: #3498db;
      border-radius: 5px 5px 0 0;
    }
    
    .profile-info {
      display: flex;
      padding: 0 20px;
    }
    
    .profile-picture {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 4px solid #fff;
      background-color: #fff;
      margin-top: -60px;
      overflow: hidden;
      position: relative;
    }
    
    .profile-picture img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-picture .upload-btn {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0,0,0,0.5);
      color: #fff;
      text-align: center;
      padding: 5px 0;
      font-size: 12px;
      cursor: pointer;
      transition: opacity 0.3s;
      opacity: 0;
    }
    
    .profile-picture:hover .upload-btn {
      opacity: 1;
    }
    
    .profile-details {
      padding: 20px;
      padding-left: 30px;
    }
    
    .profile-name {
      font-size: 24px;
      font-weight: 500;
      margin-bottom: 5px;
    }
    
    .profile-role {
      color: #7f8c8d;
      margin-bottom: 15px;
    }
    
    .profile-section {
      margin-top: 20px;
      border-top: 1px solid #eee;
      padding-top: 20px;
    }
    
    .profile-section-title {
      font-size: 18px;
      font-weight: 500;
      margin-bottom: 15px;
      color: #2c3e50;
    }
    
    /* Tabs */
    .tabs {
      display: flex;
      margin-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .tab {
      padding: 10px 20px;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .tab.active {
      border-bottom: 2px solid #3498db;
      color: #3498db;
      font-weight: 500;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Helper classes */
    .mb-10 {
      margin-bottom: 10px;
    }
    
    .mb-20 {
      margin-bottom: 20px;
    }
    
    .mt-20 {
      margin-top: 20px;
    }
    
    .text-center {
      text-align: center;
    }
    
    .text-right {
      text-align: right;
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
        <?php if (!empty($user['profile_picture']) && file_exists('./uploads/profile_pictures/' . $user['profile_picture'])): ?>
          <img src="./uploads/profile_pictures/<?php echo $user['profile_picture']; ?>" alt="User Profile">
        <?php else: ?>
          <img src="/api/placeholder/32/32" alt="User Profile">
        <?php endif; ?>
        <span><?php echo $user['name']; ?> (President)</span>
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
        <li><a href="./President-LeaveApprovalPage.php"><i class="fas fa-calendar-check"></i> <span>Leave Approval</span></a></li>
        <li><a href="./PresidentReportsReviewPage.php"><i class="fas fa-file-signature"></i> <span>Reports Review</span></a></li>
        <li><a href="./PresidentAttendanceOverviewPage.php"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
        <li><a href="./PresidentProfilePage.php" class="active"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
      </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="page-title">
        <h1>My Profile</h1>
      </div>
      
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?php echo $successMsg; ?></div>
      <?php endif; ?>
      
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
      <?php endif; ?>
      
      <div class="card">
        <div class="profile-header"></div>
        <div class="profile-info">
          <div class="profile-picture">
            <?php if (!empty($user['profile_picture']) && file_exists('./uploads/profile_pictures/' . $user['profile_picture'])): ?>
              <img src="./uploads/profile_pictures/<?php echo $user['profile_picture']; ?>" alt="Profile Picture">
            <?php else: ?>
              <img src="/api/placeholder/120/120" alt="Profile Picture">
            <?php endif; ?>
            <label for="profile_picture_upload" class="upload-btn">Change Photo</label>
          </div>
          <div class="profile-details">
            <div class="profile-name"><?php echo $user['name']; ?></div>
            <div class="profile-role">President</div>
            <div><?php echo $user['email']; ?></div>
            <?php if (!empty($user['phone'])): ?>
            <div><?php echo $user['phone']; ?></div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="card-body">
          <div class="tabs">
            <div class="tab active" data-tab="personal-info">Personal Information</div>
            <div class="tab" data-tab="password">Change Password</div>
          </div>
          
          <form action="PresidentProfilePage.php" method="POST" enctype="multipart/form-data">
            <input type="file" id="profile_picture_upload" name="profile_picture" style="display: none;">
            
            <!-- Personal Information Tab -->
            <div class="tab-content active" id="personal-info">
              <div class="grid">
                <div class="col-6">
                  <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo $user['name']; ?>" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" class="form-control" value="<?php echo $user['username']; ?>" disabled>
                    <small style="color: #7f8c8d;">Username cannot be changed</small>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control"><?php echo $user['address']; ?></textarea>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label for="emergency_contact">Emergency Contact</label>
                    <textarea id="emergency_contact" name="emergency_contact" class="form-control"><?php echo $user['emergency_contact']; ?></textarea>
                  </div>
                </div>
                <div class="col-12">
                  <div class="profile-section">
                    <div class="profile-section-title">Account Information</div>
                    <div class="grid">
                      <div class="col-6">
                        <div class="form-group">
                          <label>Role</label>
                          <input type="text" class="form-control" value="President" disabled>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="form-group">
                          <label>Join Date</label>
                          <input type="text" class="form-control" value="<?php echo !empty($user['join_date']) ? date('F d, Y', strtotime($user['join_date'])) : 'Not set'; ?>" disabled>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Change Password Tab -->
            <div class="tab-content" id="password">
              <div class="grid">
                <div class="col-12">
                  <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                  </div>
                </div>
                <div class="col-12">
                  <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Leave password fields empty if you don't want to change your password.
                  </div>
                </div>
              </div>
            </div>
            
            <div class="text-right mt-20">
              <button type="button" class="btn btn-secondary" onclick="resetForm()">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    // Toggle User Dropdown
    document.querySelector('.user-profile').addEventListener('click', function(e) {
      e.stopPropagation();
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
    
    // Tab Switching
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        // Remove active class from all tabs and tab contents
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Add active class to current tab and corresponding content
        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
      });
    });
    
    // Trigger file input when clicking on upload button
    document.querySelector('.upload-btn').addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('profile_picture_upload').click();
    });
    
    // Show preview of selected profile picture
    document.getElementById('profile_picture_upload').addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.querySelector('.profile-picture img').src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
    
    // Reset form function
    function resetForm() {
      window.location.reload();
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.display = 'none';
      });
    }, 5000);
  </script>
</body>
</html>