<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
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

// Fetch user data
$userId = $_SESSION['user_id'];
$sql = "SELECT id, name, profile_picture, department, email, username, manager, join_date, address, emergency_contact, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found");
}

$user = $result->fetch_assoc();

// Fetch recent activities for the user
$activitySql = "SELECT * FROM users_attendance WHERE user_id = ? ORDER BY clock_in DESC LIMIT 5";
$activityStmt = $conn->prepare($activitySql);
$activityStmt->bind_param("i", $userId);
$activityStmt->execute();
$activities = $activityStmt->get_result();

// Fetch notifications for the user
$notificationSql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notificationStmt = $conn->prepare($notificationSql);
$notificationStmt->bind_param("i", $userId);
$notificationStmt->execute();
$notifications = $notificationStmt->get_result();

// Split the name into first and last name
$nameParts = explode(' ', $user['name'], 2);
$firstName = $nameParts[0];
$lastName = isset($nameParts[1]) ? $nameParts[1] : '';

// Close database connection
$stmt->close();
$activityStmt->close();
$notificationStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | Attendance Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    
    .mobile-menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--dark-text);
      cursor: pointer;
      margin-right: 15px;
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
    
    /* Profile Specific Styles */
    .page-title {
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .profile-container {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 20px;
    }
    
    .profile-card {
      background-color: white;
      border-radius: 8px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }
    
    .profile-image {
      text-align: center;
      padding: 30px 20px;
    }
    
    .profile-image img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      border: 5px solid var(--light-bg);
      object-fit: cover;
    }
    
    .profile-image h2 {
      margin-top: 15px;
      font-size: 1.3rem;
    }
    
    .profile-image p {
      color: var(--primary-color);
      font-weight: 500;
    }
    
    .profile-info-list {
      list-style-type: none;
      padding: 0;
    }
    
    .profile-info-list li {
      padding: 15px 20px;
      border-top: 1px solid var(--border-color);
      display: flex;
      align-items: center;
    }
    
    .profile-info-list li i {
      width: 20px;
      margin-right: 10px;
      color: var(--primary-color);
    }
    
    .profile-info-list li .info-label {
      min-width: 120px;
      font-weight: 600;
    }
    
    .profile-info-list li .info-value {
      flex-grow: 1;
    }
    
    .tab-container {
      width: 100%;
    }
    
    .tab-buttons {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      background-color: white;
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
    }
    
    .tab-button {
      padding: 15px 20px;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      font-size: 1rem;
      opacity: 0.7;
      position: relative;
    }
    
    .tab-button.active {
      opacity: 1;
      color: var(--primary-color);
    }
    
    .tab-button.active::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      width: 100%;
      height: 2px;
      background-color: var(--primary-color);
    }
    
    .tab-content {
      background-color: white;
      padding: 20px;
      border-bottom-left-radius: 8px;
      border-bottom-right-radius: 8px;
    }
    
    .tab-pane {
      display: none;
    }
    
    .tab-pane.active {
      display: block;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
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
    
    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--primary-color);
      color: var(--primary-color);
    }
    
    .btn-outline:hover {
      background-color: var(--primary-color);
      color: white;
    }
    
    .activity-timeline {
      position: relative;
      padding-left: 30px;
    }
    
    .activity-timeline::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background-color: var(--border-color);
    }
    
    .timeline-item {
      position: relative;
      margin-bottom: 30px;
    }
    
    .timeline-item:last-child {
      margin-bottom: 0;
    }
    
    .timeline-item::before {
      content: '';
      position: absolute;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: var(--primary-color);
      left: -26px;
      top: 5px;
    }
    
    .timeline-date {
      font-size: 0.85rem;
      color: var(--dark-text);
      opacity: 0.7;
      margin-bottom: 5px;
    }
    
    .timeline-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
      .profile-container {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        z-index: 99;
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .mobile-menu-toggle {
        display: block;
      }
      
      .form-row {
        grid-template-columns: 1fr;
        gap: 10px;
      }
    }
  </style>
</head>
<body>
  <header>
    <button class="mobile-menu-toggle" id="mobile-menu-toggle">
      <i class="fas fa-bars"></i>
    </button>
    <div class="logo">AMS</div>
    <div class="app-title">Attendance Management System</div>
    <div class="user-profile" id="user-profile">
      <?php 
        $profilePicPath = './uploads/profile_pictures/' . htmlspecialchars($user['profile_picture']);
        if (!empty($user['profile_picture']) && file_exists($profilePicPath)) {
          echo '<img src="' . $profilePicPath . '?t=' . time() . '" alt="User Avatar">';
        } else {
          echo '<img src="./uploads/profile_pictures/default-profile.png" alt="User Avatar">';
        }
      ?>
      <span><?php echo htmlspecialchars($user['name']); ?></span>
    </div>
  </header>
  
  <div class="layout">
    <div class="sidebar" id="sidebar">
      <button class="toggle-btn" id="sidebar-toggle">
        <i class="fas fa-chevron-left"></i>
      </button>
      <ul>
        <li>
          <a href="./EmployeeDashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="./EmployeeProfilePage.php" class="active">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
          </a>
        </li>
        <li>
          <a href="./AttendanceManagementPage.php">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
          </a>
        </li>
        <li>
          <a href="./LeaveManagementPage.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Leave Management</span>
          </a>
        </li>
        <li>
          <a href="./EmployeeNotificationsPage.php">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
          </a>
        </li>
        <li>
          <a href="/projectweb/logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="main-content">
      <div class="page-title">
        <h1>My Profile</h1>
      </div>
      
      <div class="profile-container">
        <div class="profile-card">

          <div class="profile-image">
            <?php 
            $profilePicPath = './uploads/profile_pictures/' . htmlspecialchars($user['profile_picture']);
            // Check if the file actually exists
            if (!empty($user['profile_picture']) && file_exists($profilePicPath)) {
              echo '<img src="' . $profilePicPath . '?t=' . time() . '" alt="User Avatar">';
            } else {
              // Fall back to default image
              echo '<img src="./uploads/profile_pictures/default-profile.png" alt="User Avatar">';
            }
            ?>
            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
            <p><?php echo htmlspecialchars($user['department'] ?? 'Department not set'); ?></p>
            <button class="btn btn-outline mt-10" onclick="document.getElementById('profile-picture-input').click()">Change Picture</button>
            <input type="file" id="profile-picture-input" style="display: none;" accept="image/*" onchange="uploadProfilePicture(event)">
          </div>

          <ul class="profile-info-list">
            <li>
              <i class="fas fa-id-card"></i>
              <span class="info-label">Employee ID:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['id']); ?></span>
            </li>
            <li>
              <i class="fas fa-envelope"></i>
              <span class="info-label">Email:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
            </li>
            <li>
              <i class="fas fa-user"></i>
              <span class="info-label">Username:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
            </li>
            <li>
              <i class="fas fa-building"></i>
              <span class="info-label">Department:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'Not specified'); ?></span>
            </li>
            <li>
              <i class="fas fa-user-tie"></i>
              <span class="info-label">Manager:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['manager'] ?? 'Not specified'); ?></span>
            </li>
            <li>
              <i class="fas fa-calendar-alt"></i>
              <span class="info-label">Join Date:</span>
              <span class="info-value"><?php echo htmlspecialchars($user['join_date'] ?? 'Not specified'); ?></span>
            </li>
          </ul>
        </div>
        
        <div class="tab-container">
          <div class="tab-buttons">
            <button class="tab-button active" data-tab="personal">Personal Information</button>
            <button class="tab-button" data-tab="password">Change Password</button>
            <button class="tab-button" data-tab="activity">Recent Activity</button>
          </div>
          
          <div class="tab-content">
            <div class="tab-pane active" id="personal">
              <form id="personal-info-form" method="post" action="update_profile.php">
                <div class="form-row">
                  <div class="form-group">
                    <label for="first-name">First Name</label>
                    <input type="text" id="first-name" name="first-name" value="<?php echo htmlspecialchars($firstName); ?>">
                  </div>
                  <div class="form-group">
                    <label for="last-name">Last Name</label>
                    <input type="text" id="last-name" name="last-name" value="<?php echo htmlspecialchars($lastName); ?>">
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                  </div>
                  <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                  </div>
                  <div class="form-group">
                    <label for="manager">Manager</label>
                    <input type="text" id="manager" name="manager" value="<?php echo htmlspecialchars($user['manager'] ?? ''); ?>">
                  </div>
                </div>
                
                <div class="form-group">
                  <label for="join-date">Join Date</label>
                  <input type="date" id="join-date" name="join-date" value="<?php echo (!empty($user['join_date']) && $user['join_date'] != '0000-00-00') ? $user['join_date'] : ''; ?>">
                </div>

                <div class="form-group">
                  <label for="address">Address</label>
                  <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                  <label for="emergency-contact">Emergency Contact</label>
                  <input type="text" id="emergency-contact" name="emergency-contact" value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>">
                </div>
                
                <div class="text-right">
                  <button type="submit" class="btn btn-secondary">Save Changes</button>
                </div>
              </form>
            </div>
            
            <div class="tab-pane" id="password">
              <form id="password-form" method="post" action="update_password.php">
                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                <div class="form-group">
                  <label for="current-password">Current Password</label>
                  <input type="password" id="current-password" name="current-password" required>
                </div>
                
                <div class="form-group">
                  <label for="new-password">New Password</label>
                  <input type="password" id="new-password" name="new-password" required>
                </div>
                
                <div class="form-group">
                  <label for="confirm-password">Confirm New Password</label>
                  <input type="password" id="confirm-password" name="confirm-password" required>
                </div>
                
                <div class="text-right">
                  <button type="submit" class="btn btn-secondary">Update Password</button>
                </div>
              </form>
            </div>
            
            <div class="tab-pane" id="activity">
              <div class="activity-timeline">
                <?php if ($activities->num_rows > 0): ?>
                  <?php while ($activity = $activities->fetch_assoc()): ?>
                    <div class="timeline-item">
                      <div class="timeline-date">
                        <?php echo date('F j, Y, g:i A', strtotime($activity['clock_in'])); ?>
                      </div>
                      <div class="timeline-title">Clock In</div>
                      <div class="timeline-content">You clocked in for the day.</div>
                    </div>
                    
                    <?php if ($activity['clock_out']): ?>
                    <div class="timeline-item">
                      <div class="timeline-date">
                        <?php echo date('F j, Y, g:i A', strtotime($activity['clock_out'])); ?>
                      </div>
                      <div class="timeline-title">Clock Out</div>
                      <div class="timeline-content">You clocked out for the day.</div>
                    </div>
                    <?php endif; ?>
                  <?php endwhile; ?>
                <?php else: ?>
                  <div class="timeline-item">
                    <div class="timeline-content">No recent activity found.</div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      if (sidebar.classList.contains('collapsed')) {
        mainContent.style.marginLeft = '0';
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
      } else {
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-left"></i>';
      }
    });
    
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    
    mobileMenuToggle.addEventListener('click', function() {
      sidebar.classList.toggle('active');
    });
    
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
      button.addEventListener('click', function() {
        // Remove active class from all buttons
        tabButtons.forEach(btn => btn.classList.remove('active'));
        
        // Add active class to current button
        this.classList.add('active');
        
        // Hide all tab panes
        tabPanes.forEach(pane => pane.classList.remove('active'));
        
        // Show the current tab pane
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
      });
    });

    // Password confirmation validation
    const passwordForm = document.getElementById('password-form');
    passwordForm.addEventListener('submit', function(e) {
      const newPassword = document.getElementById('new-password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      
      if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
      }
    });


    function uploadProfilePicture(event) {
      const file = event.target.files[0];
      if (!file) return;
      
      // Show loading indicator or message
      const profileImage = document.querySelector('.profile-image img');
      profileImage.style.opacity = '0.5';
      
      const formData = new FormData();
      formData.append('profile_picture', file);

      fetch('upload_profile_picture.php', {
        method: 'POST',
        body: formData,
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          // Update the profile picture on the page with cache-busting parameter
          profileImage.src = './uploads/profile_pictures/' + data.fileName + '?t=' + new Date().getTime();
          profileImage.style.opacity = '1';
          alert('Profile picture updated successfully!');
        } else {
          profileImage.style.opacity = '1';
          alert('Error uploading profile picture: ' + data.error);
          console.error('Upload error:', data.error);
        }
      })
      .catch(error => {
        profileImage.style.opacity = '1';
        console.error('Upload error:', error);
        alert('An error occurred while uploading the image. Please try again.');
      });
    }
    
  </script>`
</body>
</html>