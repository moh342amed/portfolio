<?php
// Start the session and check if user is logged in
session_start();

// Redirect to login page if not logged in
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

// Fetch user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications | Attendance Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>

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
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    /* Notification Specific Styles */
    .notification-filters {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .filter-btn {
      padding: 8px 15px;
      background-color: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.3s;
    }
    
    .filter-btn:hover {
      background-color: var(--light-bg);
    }
    
    .filter-btn.active {
      background-color: var(--primary-color);
      color: white;
      border-color: var(--primary-color);
    }
    
    .notifications-container {
      background-color: white;
      border-radius: 4px;
      box-shadow: var(--card-shadow);
    }
    
    .notification-item {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      transition: background-color 0.3s;
      cursor: pointer;
      display: flex;
      align-items: flex-start;
    }
    
    .notification-item:hover {
      background-color: var(--light-bg);
    }
    
    .notification-item:last-child {
      border-bottom: none;
    }
    
    .notification-item.unread {
      border-left: 3px solid var(--primary-color);
    }
    
    .notification-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: rgba(52, 152, 219, 0.1);
      color: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
      flex-shrink: 0;
    }
    
    .notification-icon.success {
      background-color: rgba(46, 204, 113, 0.1);
      color: var(--secondary-color);
    }
    
    .notification-icon.warning {
      background-color: rgba(243, 156, 18, 0.1);
      color: var(--warning-color);
    }
    
    .notification-icon.danger {
      background-color: rgba(231, 76, 60, 0.1);
      color: var(--danger-color);
    }
    
    .notification-content {
      flex-grow: 1;
    }
    
    .notification-title {
      font-weight: 600;
      margin-bottom: 5px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    
    .notification-time {
      font-size: 0.8rem;
      color: var(--dark-text);
      opacity: 0.7;
      white-space: nowrap;
      margin-left: 10px;
    }
    
    .notification-message {
      font-size: 0.95rem;
      margin-bottom: 5px;
    }
    
    .notification-actions {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }
    
    .notification-actions button {
      padding: 5px 10px;
      border: none;
      border-radius: 4px;
      background-color: var(--light-bg);
      cursor: pointer;
      font-size: 0.8rem;
      transition: background-color 0.3s;
    }
    
    .notification-actions button:hover {
      background-color: var(--border-color);
    }
    
    .notification-actions button.view-details {
      background-color: var(--primary-color);
      color: white;
    }
    
    .notification-actions button.view-details:hover {
      background-color: var(--primary-dark);
    }
    
    .load-more {
      padding: 15px;
      text-align: center;
      background-color: var(--light-bg);
      border: none;
      width: 100%;
      cursor: pointer;
      font-size: 0.9rem;
      border-bottom-left-radius: 4px;
      border-bottom-right-radius: 4px;
      transition: background-color 0.3s;
    }
    
    .load-more:hover {
      background-color: var(--border-color);
    }
    
    .notification-empty {
      padding: 30px;
      text-align: center;
      color: var(--dark-text);
      opacity: 0.7;
    }
    
    .notification-empty i {
      font-size: 3rem;
      margin-bottom: 10px;
      opacity: 0.3;
    }
    
    .notification-detail-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
    
    .modal-content {
      width: 90%;
      max-width: 550px;
      background-color: white;
      border-radius: 8px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }
    
    .modal-header {
      padding: 15px 20px;
      background-color: var(--primary-color);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-header .close-btn {
      background: none;
      border: none;
      color: white;
      font-size: 1.3rem;
      cursor: pointer;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .modal-footer {
      padding: 15px 20px;
      background-color: var(--light-bg);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    .status-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 15px;
      font-size: 0.75rem;
      color: white;
      margin-left: 10px;
    }
    
    .badge-success { background-color: var(--secondary-color); }
    .badge-warning { background-color: var(--warning-color); }
    .badge-danger { background-color: var(--danger-color); }
    .badge-info { background-color: var(--primary-color); }
    
    /* Search box styles */
    .search-box {
      display: flex;
      margin-left: 20px;
    }
    
    .search-box input {
      padding: 8px 12px;
      border: 1px solid var(--border-color);
      border-right: none;
      border-radius: 4px 0 0 4px;
      outline: none;
    }
    
    .search-box button {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 0 4px 4px 0;
      cursor: pointer;
    }
    
    /* Responsive adjustments */
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
      
      .notification-title {
        flex-direction: column;
      }
      
      .notification-time {
        margin-left: 0;
        margin-top: 5px;
      }
      
      .page-title {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
      }
      
      .search-box {
        margin-left: 0;
        width: 100%;
      }
      
      .search-box input {
        flex-grow: 1;
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
          <a href="./LeaveManagementPage.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Leave Management</span>
          </a>
        </li>
        <li>
          <a href="./EmployeeNotificationsPage.php" class="active">
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
        <h1>Notifications</h1>
        <button class="btn" id="mark-all-read">
          <i class="fas fa-check-double"></i> Mark All as Read
        </button>
      </div>
      
      <div class="notification-filters">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="unread">Unread</button>
        <button class="filter-btn" data-filter="leave">Leave Requests</button>
        <button class="filter-btn" data-filter="attendance">Attendance</button>
        <button class="filter-btn" data-filter="system">System</button>
        <button class="filter-btn" data-filter="penalty">Penalties</button>
      </div>
      
      <div class="notifications-container" id="notifications-container">
        <!-- Notifications will be loaded here via JavaScript -->
        <div class="notification-empty">
          <i class="fas fa-bell-slash"></i>
          <p>Loading notifications...</p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Notification Detail Modal -->
  <div class="notification-detail-modal" id="notification-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modal-title">Notification Details</h3>
        <button class="close-btn" id="close-modal">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" id="modal-body">
        <!-- Modal content will be loaded dynamically -->
      </div>
      <div class="modal-footer">
        <button class="btn" id="close-modal-btn">Close</button>
      </div>
    </div>
  </div>
  
  <script>
    // JavaScript code to handle notifications
    const userId = <?php echo json_encode($user_id); ?>;
    let currentPage = 1;
    let currentFilter = 'all';
    let isLoading = false;

    // Function to fetch notifications
    async function fetchNotifications(page = 1, filter = 'all') {
        try {
            const response = await fetch(`fetch_notifications.php?page=${page}&filter=${filter}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return { notifications: [], has_more: false };
        }
    }

    // Function to render notifications
    function renderNotifications(notifications, container, append = false) {
        if (!append) {
            container.innerHTML = '';
        }

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications found.</p>
                </div>`;
            return;
        }

        notifications.forEach(notification => {
            const notificationItem = document.createElement('div');
            notificationItem.className = `notification-item ${notification.read ? '' : 'unread'}`;
            notificationItem.innerHTML = `
                <div class="notification-icon ${notification.icon_class}">
                    <i class="fas fa-${notification.icon_name}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">
                        ${notification.title}
                        <span class="notification-time">${notification.time_ago}</span>
                    </div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-actions">
                        <button class="view-details" data-id="${notification.id}">View Details</button>
                        ${notification.read ? '' : `<button class="mark-read" data-id="${notification.id}">Mark as Read</button>`}
                    </div>
                </div>`;
            container.appendChild(notificationItem);
        });

        setupEventHandlers();
    }

    // Function to load more notifications
    async function loadMoreNotifications() {
        if (isLoading) return;
        isLoading = true;

        currentPage++;
        const data = await fetchNotifications(currentPage, currentFilter);
        const container = document.getElementById('notifications-container');
        renderNotifications(data.notifications, container, true);

        if (!data.has_more) {
            document.getElementById('load-more').style.display = 'none';
        }

        isLoading = false;
    }

    // Function to setup event handlers
    function setupMarkReadHandlers() {
      document.querySelectorAll('.mark-read').forEach(button => {
        if (!button.hasAttribute('data-initialized')) {
          button.setAttribute('data-initialized', 'true');
          button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            
            notificationItem.classList.remove('unread');
            this.remove(); // Remove the "Mark as Read" button
            
            // Update filter counters
            updateFilterCounters();
            
            console.log('Marked notification', notificationId, 'as read');
          });
        }
      });
    }

    // Initial load of notifications
    async function init() {
        const container = document.getElementById('notifications-container');
        const data = await fetchNotifications(currentPage, currentFilter);
        renderNotifications(data.notifications, container);

        if (data.has_more) {
            const loadMoreButton = document.createElement('button');
            loadMoreButton.className = 'load-more';
            loadMoreButton.id = 'load-more';
            loadMoreButton.innerHTML = '<i class="fas fa-spinner"></i> Load More';
            loadMoreButton.addEventListener('click', loadMoreNotifications);
            container.appendChild(loadMoreButton);
        }

        setupEventHandlers();
    }

    // Initialize the page
    init();

    // ... (Keep rest of the JavaScript code) ...
    


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
    
    // Filter notifications
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
      button.addEventListener('click', function() {
        // Remove active class from all filter buttons
        filterButtons.forEach(btn => btn.classList.remove('active'));
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Filter logic
        const filter = this.getAttribute('data-filter');
        
        // Apply filtering
        document.querySelectorAll('.notification-item').forEach(item => {
          if (filter === 'all') {
            item.style.display = 'flex';
          } else if (filter === 'unread' && item.classList.contains('unread')) {
            item.style.display = 'flex';
          } else if (filter === 'unread' && !item.classList.contains('unread')) {
            item.style.display = 'none';
          } else {
            // This would be replaced with actual filtering logic based on notification type
            // For demo, we'll just show all items for other filters
            item.style.display = 'flex';
          }
        });
      });
    });
    
    // Mark all as read
    const markAllReadBtn = document.getElementById('mark-all-read');
    
    markAllReadBtn.addEventListener('click', function() {
      const unreadItems = document.querySelectorAll('.notification-item.unread');
      
      unreadItems.forEach(item => {
        item.classList.remove('unread');
        // Remove the mark as read button from each item
        const markReadBtn = item.querySelector('.mark-read');
        if (markReadBtn) {
          markReadBtn.remove();
        }
      });
      
      // Update filter counters
      updateFilterCounters();
      
      // Show success message
      alert('All notifications marked as read');
    });
    
    // Handle "Mark as Read" button clicks
    function setupMarkReadHandlers() {
      document.querySelectorAll('.mark-read').forEach(button => {
        if (!button.hasAttribute('data-initialized')) {
          button.setAttribute('data-initialized', 'true');
          button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            
            notificationItem.classList.remove('unread');
            this.remove(); // Remove the "Mark as Read" button
            
            // Update filter counters
            updateFilterCounters();
            
            console.log('Marked notification', notificationId, 'as read');
          });
        }
      });
    }
    
    // Initial setup of mark as read handlers
    setupMarkReadHandlers();
    
    // Handle notification detail views
    function setupViewDetailHandlers() {
      document.querySelectorAll('.view-details').forEach(button => {
        if (!button.hasAttribute('data-initialized')) {
          button.setAttribute('data-initialized', 'true');
          button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            const notificationTitleEl = notificationItem.querySelector('.notification-title');
            const notificationTitle = notificationTitleEl.childNodes[0].nodeValue.trim();
            const notificationMessage = notificationItem.querySelector('.notification-message').textContent.trim();
            
            // Mark as read when viewing details
            notificationItem.classList.remove('unread');
            const markReadBtn = notificationItem.querySelector('.mark-read');
            if (markReadBtn) {
              markReadBtn.remove();
            }
            
            // Update filter counters
            updateFilterCounters();
            
            // Set modal content
            modalTitle.textContent = notificationTitle;
            
            // Sample data for different notification types
            let modalContent = '';
            
            if (notificationId === '1') {
              // Leave request approved
              modalContent = `
                <p>${notificationMessage}</p>
                <div style="margin-top: 15px;">
                  <h4>Leave Request Details</h4>
                  <table style="width: 100%; margin-top: 10px;">
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Request Date:</td>
                      <td>March 15, 2025</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Leave Type:</td>
                      <td>Annual Leave</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Period:</td>
                      <td>March 18-20, 2025 (3 days)</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Reason:</td>
                      <td>Personal matters</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Status:</td>
                      <td><span class="status-badge badge-success">Approved</span></td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Approved By:</td>
                      <td>Jane Smith (Administration)</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Comments:</td>
                      <td>Your leave request has been approved. Have a good break!</td>
                    </tr>
                  </table>
                </div>
              `;
            } else if (notificationId === '2') {
              // Attendance modification rejected
              modalContent = `
                <p>${notificationMessage}</p>
                <div style="margin-top: 15px;">
                  <h4>Attendance Modification Details</h4>
                  <table style="width: 100%; margin-top: 10px;">
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Request Date:</td>
                      <td>March 14, 2025</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Date in Question:</td>
                      <td>March 5, 2025</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Original Record:</td>
                      <td>Absent</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Requested Change:</td>
                      <td>Present (Work from home)</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Status:</td>
                      <td><span class="status-badge badge-danger">Rejected</span></td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Rejected By:</td>
                      <td>Robert Johnson (Administration)</td>
                    </tr>
                    <tr>
                      <td style="padding: 8px 0; font-weight: 600;">Reason:</td>
                      <td>Insufficient evidence provided. No work records or communication logs for the date in question. Please submit a new request with supporting documents.</td>
                    </tr>
                  </table>
                </div>
              `;
            } else {
              // Default for other notifications
              modalContent = `
                <p>${notificationMessage}</p>
                <p style="margin-top: 15px;"><em>Additional details are not available for this notification.</em></p>
              `;
            }
            
            modalBody.innerHTML = modalContent;
            
            // Show modal
            notificationModal.style.display = 'flex';
          });
        }
      });
    }
    
    // Initial setup of view detail handlers
    setupViewDetailHandlers();
    
    // Close modal - reference variables first
    const notificationModal = document.getElementById('notification-modal');
    const closeModalBtn = document.getElementById('close-modal');
    const closeModalFooterBtn = document.getElementById('close-modal-btn');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    
    viewDetailsButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.stopPropagation();
        const notificationId = this.getAttribute('data-id');
        const notificationItem = this.closest('.notification-item');
        const notificationTitle = notificationItem.querySelector('.notification-title').textContent.trim().split('\n')[0].trim();
        const notificationMessage = notificationItem.querySelector('.notification-message').textContent.trim();
        
        // Mark as read when viewing details
        notificationItem.classList.remove('unread');
        const markReadBtn = notificationItem.querySelector('.mark-read');
        if (markReadBtn) {
          markReadBtn.remove();
        }
        
        // Set modal content
        modalTitle.textContent = notificationTitle;
        
        // Sample data for different notification types
        let modalContent = '';
        
        if (notificationId === '1') {
          // Leave request approved
          modalContent = `
            <p>${notificationMessage}</p>
            <div style="margin-top: 15px;">
              <h4>Leave Request Details</h4>
              <table style="width: 100%; margin-top: 10px;">
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Request Date:</td>
                  <td>March 15, 2025</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Leave Type:</td>
                  <td>Annual Leave</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Period:</td>
                  <td>March 18-20, 2025 (3 days)</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Reason:</td>
                  <td>Personal matters</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Status:</td>
                  <td><span class="status-badge badge-success">Approved</span></td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Approved By:</td>
                  <td>Jane Smith (Administration)</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Comments:</td>
                  <td>Your leave request has been approved. Have a good break!</td>
                </tr>
              </table>
            </div>
          `;
        } else if (notificationId === '2') {
          // Attendance modification rejected
          modalContent = `
            <p>${notificationMessage}</p>
            <div style="margin-top: 15px;">
              <h4>Attendance Modification Details</h4>
              <table style="width: 100%; margin-top: 10px;">
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Request Date:</td>
                  <td>March 14, 2025</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Date in Question:</td>
                  <td>March 5, 2025</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Original Record:</td>
                  <td>Absent</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Requested Change:</td>
                  <td>Present (Work from home)</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Status:</td>
                  <td><span class="status-badge badge-danger">Rejected</span></td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Rejected By:</td>
                  <td>Robert Johnson (Administration)</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; font-weight: 600;">Reason:</td>
                  <td>Insufficient evidence provided. No work records or communication logs for the date in question. Please submit a new request with supporting documents.</td>
                </tr>
              </table>
            </div>
          `;
        } else {
          // Default for other notifications
          modalContent = `
            <p>${notificationMessage}</p>
            <p style="margin-top: 15px;"><em>Additional details are not available for this notification.</em></p>
          `;
        }
        
        modalBody.innerHTML = modalContent;
        
        // Show modal
        notificationModal.style.display = 'flex';
      });
    });
    
    // Close modal
    function closeModal() {
      notificationModal.style.display = 'none';
    }
    
    closeModalBtn.addEventListener('click', closeModal);
    closeModalFooterBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    notificationModal.addEventListener('click', function(e) {
      if (e.target === notificationModal) {
        closeModal();
      }
    });
    
    // Load more notifications (demo)
    const loadMoreBtn = document.getElementById('load-more');
    let loadCount = 0;
    
    loadMoreBtn.addEventListener('click', function() {
      loadCount++;
      
      if (loadCount === 1) {
        // Add older notifications
        const notificationsContainer = document.querySelector('.notifications-container');
        const loadMoreButton = document.querySelector('.load-more');
        
        const olderNotifications = `
          <div class="notification-item">
            <div class="notification-icon warning">
              <i class="fas fa-user-clock"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">
                Working Hours Update
                <span class="notification-time">March 5, 2025</span>
              </div>
              <div class="notification-message">
                Please note that starting April 1, 2025, the official working hours will be 9:00 AM - 5:30 PM with a 30-minute lunch break.
              </div>
              <div class="notification-actions">
                <button class="view-details" data-id="7">View Details</button>
              </div>
            </div>
          </div>
          
          <div class="notification-item">
            <div class="notification-icon danger">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">
                Penalty Notification
                <span class="notification-time">March 3, 2025</span>
              </div>
              <div class="notification-message">
                A penalty has been imposed for unauthorized absence on February 22, 2025. Please contact HR for more details.
              </div>
              <div class="notification-actions">
                <button class="view-details" data-id="8">View Details</button>
              </div>
            </div>
          </div>
          
          <div class="notification-item">
            <div class="notification-icon">
              <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">
                Monthly Attendance Report
                <span class="notification-time">March 1, 2025</span>
              </div>
              <div class="notification-message">
                Your February 2025 attendance report is now available. Present: 18 days, Late: 2 days, Absent: 0 days.
              </div>
              <div class="notification-actions">
                <button class="view-details" data-id="9">View Details</button>
              </div>
            </div>
          </div>`;
        
        // Insert new notifications before the load more button
        notificationsContainer.insertAdjacentHTML('beforeend', olderNotifications);
        
        // Move the load more button to the end
        notificationsContainer.appendChild(loadMoreButton);
        
        // Add event listeners to new buttons
        const newDetailButtons = document.querySelectorAll('.view-details:not([data-initialized])');
        newDetailButtons.forEach(button => {
          button.setAttribute('data-initialized', 'true');
          button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            const notificationTitle = notificationItem.querySelector('.notification-title').textContent.trim().split('\n')[0].trim();
            const notificationMessage = notificationItem.querySelector('.notification-message').textContent.trim();
            
            // Mark as read when viewing details
            notificationItem.classList.remove('unread');
            const markReadBtn = notificationItem.querySelector('.mark-read');
            if (markReadBtn) {
              markReadBtn.remove();
            }
            
            // Set modal content
            modalTitle.textContent = notificationTitle;
            modalBody.innerHTML = `
              <p>${notificationMessage}</p>
              <p style="margin-top: 15px;"><em>Additional details are not available for this notification.</em></p>
            `;
            
            // Show modal
            notificationModal.style.display = 'flex';
          });
        });
      } else if (loadCount === 2) {
        // Show "no more notifications" and disable the button
        loadMoreBtn.innerHTML = 'No more notifications';
        loadMoreBtn.disabled = true;
        setTimeout(() => {
          loadMoreBtn.style.display = 'none';
          
          // Add "empty state" for when all notifications are loaded
          const notificationsContainer = document.querySelector('.notifications-container');
          const emptyState = document.createElement('div');
          emptyState.className = 'load-more';
          emptyState.innerHTML = 'End of notifications';
          emptyState.style.cursor = 'default';
          notificationsContainer.appendChild(emptyState);
        }, 2000);
      }
    });
    
    // Update notification counters for filters (demo)
    function updateFilterCounters() {
      // In a real application, these would be dynamically calculated
      const allCount = document.querySelectorAll('.notification-item').length;
      const unreadCount = document.querySelectorAll('.notification-item.unread').length;
      
      // Add counters to filter buttons
      document.querySelector('[data-filter="all"]').textContent = `All (${allCount})`;
      document.querySelector('[data-filter="unread"]').textContent = `Unread (${unreadCount})`;
      document.querySelector('[data-filter="leave"]').textContent = 'Leave Requests (3)';
      document.querySelector('[data-filter="attendance"]').textContent = 'Attendance (4)';
      document.querySelector('[data-filter="system"]').textContent = 'System (2)';
      document.querySelector('[data-filter="penalty"]').textContent = 'Penalties (1)';
    }
    
    // Call this function on page load
    updateFilterCounters();
    
    // Call again when notifications are marked as read
    markAllReadBtn.addEventListener('click', updateFilterCounters);
    document.querySelectorAll('.mark-read').forEach(button => {
      button.addEventListener('click', updateFilterCounters);
    });
    
    // Search functionality (demo only)
    function addSearchFunctionality() {
      const pageTitle = document.querySelector('.page-title');
      
      const searchBox = document.createElement('div');
      searchBox.className = 'search-box';
      searchBox.innerHTML = `
        <input type="text" placeholder="Search notifications..." id="search-input">
        <button id="search-btn"><i class="fas fa-search"></i></button>
      `;
      
      pageTitle.appendChild(searchBox);
      
      // Add styles for search box
      const style = document.createElement('style');
      style.textContent = `
        .search-box {
          display: flex;
          margin-left: 20px;
        }
        
        .search-box input {
          padding: 8px 12px;
          border: 1px solid var(--border-color);
          border-right: none;
          border-radius: 4px 0 0 4px;
          outline: none;
        }
        
        .search-box button {
          background-color: var(--primary-color);
          color: white;
          border: none;
          padding: 8px 12px;
          border-radius: 0 4px 4px 0;
          cursor: pointer;
        }
        
        @media (max-width: 768px) {
          .page-title {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
          }
          
          .search-box {
            margin-left: 0;
            width: 100%;
          }
          
          .search-box input {
            flex-grow: 1;
          }
        }
      `;
      
      document.head.appendChild(style);
      
      // Add search functionality
      const searchInput = document.getElementById('search-input');
      const searchBtn = document.getElementById('search-btn');
      
      function performSearch() {
        const searchTerm = searchInput.value.toLowerCase();
        const notificationItems = document.querySelectorAll('.notification-item');
        
        notificationItems.forEach(item => {
          const title = item.querySelector('.notification-title').textContent.toLowerCase();
          const message = item.querySelector('.notification-message').textContent.toLowerCase();
          
          if (title.includes(searchTerm) || message.includes(searchTerm)) {
            item.style.display = 'flex';
          } else {
            item.style.display = 'none';
          }
        });
      }
      
      searchBtn.addEventListener('click', performSearch);
      searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
          performSearch();
        }
      });
    }
    
    // Add search functionality to the page
    addSearchFunctionality();
  </script>
</body>
</html>