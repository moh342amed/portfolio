<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /projectweb/login.html');
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

// Get user details
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, profile_picture, annual_leave_balance, sick_leave_balance, personal_leave_balance, unpaid_leave_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get leave history
$leaveQuery = $conn->prepare("SELECT id, leave_type, start_date, end_date, days, status, submitted_at, reason FROM leave_requests WHERE user_id = ? ORDER BY submitted_at DESC");
$leaveQuery->bind_param("i", $userId);
$leaveQuery->execute();
$leaveHistory = $leaveQuery->get_result();

// Get pending leave requests
$pendingQuery = $conn->prepare("SELECT id, leave_type, start_date, end_date, days, status, submitted_at, reason FROM leave_requests WHERE user_id = ? AND status = 'pending' ORDER BY submitted_at DESC");
$pendingQuery->bind_param("i", $userId);
$pendingQuery->execute();
$pendingRequests = $pendingQuery->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave Management | Attendance System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* Importing common styles */
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
    
    /* Leave Management Specific Styles */
    .leave-balance-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .balance-card {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
      padding: 15px;
      text-align: center;
    }
    
    .balance-card .leave-type {
      font-weight: 600;
      margin-bottom: 10px;
      color: var(--dark-text);
      font-size: 0.9rem;
    }
    
    .balance-card .balance {
      font-size: 1.8rem;
      font-weight: bold;
      color: var(--primary-color);
    }
    
    .balance-card .total {
      font-size: 0.8rem;
      color: var(--dark-text);
      opacity: 0.7;
      margin-top: 5px;
    }
    
    .balance-card.sick-leave .balance {
      color: var(--warning-color);
    }
    
    .balance-card.personal-leave .balance {
      color: var(--secondary-color);
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }
    
    input[type="text"],
    input[type="date"],
    select,
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 1rem;
    }
    
    textarea {
      min-height: 100px;
      resize: vertical;
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
    
    .leave-history-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .leave-history-table th, 
    .leave-history-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
    }
    
    .leave-history-table th {
      background-color: var(--light-bg);
      font-weight: 600;
    }
    
    .leave-history-table tr:hover {
      background-color: var(--light-bg);
    }
    
    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-success { 
      background-color: rgba(46, 204, 113, 0.2);
      color: var(--secondary-dark);
    }
    
    .badge-warning { 
      background-color: rgba(243, 156, 18, 0.2);
      color: var(--warning-color);
    }
    
    .badge-danger { 
      background-color: rgba(231, 76, 60, 0.2);
      color: var(--danger-color);
    }
    
    .badge-info { 
      background-color: rgba(52, 152, 219, 0.2);
      color: var(--primary-color);
    }
    
    /* Responsive Adjustments */
    @media (max-width: 1024px) {
      .grid .col-6 {
        grid-column: span 12;
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
      
      .main-content {
        margin-left: 0 !important;
      }
    }
    
    @media (max-width: 480px) {
      .leave-balance-cards {
        grid-template-columns: 1fr;
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
      <img src="./uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="User Avatar">
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
          <a href="./EmployeeProfilePage.php">
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
          <a href="./LeaveManagementPage.php" class="active">
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
        <h1>Leave Management</h1>
      </div>
      
      <!-- Leave Balance Section -->
      <div class="leave-balance-cards">
        <div class="balance-card">
          <div class="leave-type">Annual Leave</div>
          <div class="balance"><?php echo htmlspecialchars($user['annual_leave_balance']); ?></div>
          <div class="total">of 20 days</div>
        </div>
        
        <div class="balance-card sick-leave">
          <div class="leave-type">Sick Leave</div>
          <div class="balance"><?php echo htmlspecialchars($user['sick_leave_balance']); ?></div>
          <div class="total">of 10 days</div>
        </div>
        
        <div class="balance-card personal-leave">
          <div class="leave-type">Personal Leave</div>
          <div class="balance"><?php echo htmlspecialchars($user['personal_leave_balance']); ?></div>
          <div class="total">of 5 days</div>
        </div>
        
        <div class="balance-card">
          <div class="leave-type">Unpaid Leave</div>
          <div class="balance"><?php echo htmlspecialchars($user['unpaid_leave_balance']); ?></div>
          <div class="total">days available</div>
        </div>
      </div>
      
      <div class="grid">
        <div class="col-6">
          <!-- Leave Request Form -->
          <div class="card">
            <div class="card-header">
              <i class="fas fa-plus-circle"></i> Submit Leave Request
            </div>
            <div class="card-body">
              <form id="leave-request-form" action="submit_leave_request.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                  <label for="leave-type">Leave Type</label>
                  <select id="leave-type" name="leave_type" required>
                    <option value="">-- Select Leave Type --</option>
                    <option value="annual">Annual Leave</option>
                    <option value="sick">Sick Leave</option>
                    <option value="personal">Personal Leave</option>
                    <option value="unpaid">Unpaid Leave</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="start-date">Start Date</label>
                  <input type="date" id="start-date" name="start_date" required>
                </div>
                
                <div class="form-group">
                  <label for="end-date">End Date</label>
                  <input type="date" id="end-date" name="end_date" required>
                </div>
                
                <div class="form-group">
                  <label for="reason">Reason for Leave</label>
                  <textarea id="reason" name="reason" placeholder="Provide details about your leave request"></textarea>
                </div>
                
                <div class="form-group">
                  <label>Attachments (if any)</label>
                  <input type="file" id="attachments" name="attachment">
                </div>
                
                <div class="form-group">
                  <button type="submit" class="btn btn-secondary">Submit Request</button>
                  <button type="reset" class="btn" style="background-color: var(--border-color);">Reset</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        
        <div class="col-6">
          <!-- Leave History -->
          <div class="card">
            <div class="card-header">
              <i class="fas fa-history"></i> Leave History
            </div>
            <div class="card-body">
              <table class="leave-history-table">
                <thead>
                  <tr>
                    <th>Leave Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($leaveHistory->num_rows > 0): ?>
                    <?php while($leave = $leaveHistory->fetch_assoc()): ?>
                      <tr>
                        <td><?php echo htmlspecialchars(ucfirst($leave['leave_type'])); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($leave['start_date']))); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($leave['end_date']))); ?></td>
                        <td><?php echo htmlspecialchars($leave['days']); ?></td>
                        <td><span class="badge badge-<?php echo getStatusClass($leave['status']); ?>"><?php echo htmlspecialchars(ucfirst($leave['status'])); ?></span></td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center">No leave history found</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Pending Requests -->
          <div class="card">
            <div class="card-header">
              <i class="fas fa-clock"></i> Pending Requests
            </div>
            <div class="card-body">
              <?php if ($pendingRequests->num_rows > 0): ?>
                <?php while($pending = $pendingRequests->fetch_assoc()): ?>
                  <div class="pending-request">
                    <h3><?php echo htmlspecialchars(ucfirst($pending['leave_type'])); ?> Leave: 
                      <?php echo date('M d', strtotime($pending['start_date'])); ?>-<?php echo date('d, Y', strtotime($pending['end_date'])); ?> 
                      (<?php echo htmlspecialchars($pending['days']); ?> days)
                    </h3>
                    <p><strong>Reason:</strong> <?php echo htmlspecialchars($pending['reason']); ?></p>
                    <p><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($pending['submitted_at'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-warning">Awaiting approval</span></p>
                    <div class="mt-10">
                      <button class="btn cancel-request" data-id="<?php echo $pending['id']; ?>" style="background-color: var(--danger-color);">
                        <i class="fas fa-times"></i> Cancel Request
                      </button>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <p>No pending leave requests.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <?php
  function getStatusClass($status) {
    switch($status) {
      case 'approved':
        return 'success';
      case 'pending':
        return 'warning';
      case 'rejected':
        return 'danger';
      default:
        return 'secondary';
    }
  }
  ?>
  
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
    
    // Date validation
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    
    endDateInput.addEventListener('change', function() {
      if (startDateInput.value && endDateInput.value) {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        if (endDate < startDate) {
          alert('End date cannot be before start date');
          endDateInput.value = '';
        }
      }
    });
    
    // Cancel request functionality
    document.querySelectorAll('.cancel-request').forEach(button => {
      button.addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel this leave request?')) {
          const leaveId = this.getAttribute('data-id');
          
          fetch('cancel_leave_request.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'leave_id=' + leaveId
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success message
              const notification = document.createElement('div');
              notification.className = 'notification success';
              notification.innerHTML = `
                <strong>Success!</strong>
                <p>Your leave request has been cancelled successfully.</p>
              `;
              
              document.body.appendChild(notification);
              
              // Remove notification after 3 seconds
              setTimeout(function() {
                notification.style.opacity = '0';
                setTimeout(function() {
                  document.body.removeChild(notification);
                }, 300);
              }, 3000);
              
              // Reload page after successful cancellation
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            } else {
              alert('Failed to cancel leave request: ' + data.error);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the leave request');
          });
        }
      });
    });
  </script>
</body>
</html>