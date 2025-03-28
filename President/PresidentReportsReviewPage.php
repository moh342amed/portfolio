<?php
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

// Fetch user data including profile picture
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT name, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$user_name = $user['name'];
$profile_picture = $user['profile_picture'];
$upload_dir = 'uploads/profile_pictures/';

// Fetch pending reports with more detailed information
$sql_pending_reports = "
    SELECT 
        r.id AS report_id,
        r.title AS report_title,
        r.department,
        u.name AS generated_by,
        r.generated_date,
        r.priority,
        r.attachment,
        r.comments AS report_summary
    FROM reports r
    JOIN users u ON r.generated_by = u.id
    WHERE r.status = 'pending'
    ORDER BY r.generated_date DESC
";
$result_pending_reports = $conn->query($sql_pending_reports);
$pending_reports = [];
while ($row = $result_pending_reports->fetch_assoc()) {
    // Optional: Prepare attachment URL if exists
    $row['attachment_url'] = !empty($row['attachment']) ? 'uploads/reports/' . $row['attachment'] : null;
    $pending_reports[] = $row;
}

// Fetch recently signed reports
$sql_signed_reports = "
    SELECT 
        r.id AS report_id,
        r.title AS report_title,
        r.department,
        r.signed_date,
        u_signed.name AS signed_by,
        u_generated.name AS generated_by,
        r.attachment,
        r.comments AS report_summary
    FROM reports r
    JOIN users u_signed ON r.signed_by = u_signed.id
    JOIN users u_generated ON r.generated_by = u_generated.id
    WHERE r.status = 'signed'
    ORDER BY r.signed_date DESC
    LIMIT 5
";
$result_signed_reports = $conn->query($sql_signed_reports);
$signed_reports = [];
while ($row = $result_signed_reports->fetch_assoc()) {
    // Optional: Prepare attachment URL if exists
    $row['attachment_url'] = !empty($row['attachment']) ? 'uploads/reports/' . $row['attachment'] : null;
    $signed_reports[] = $row;
}

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>President - Reports Review & Signing</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Common Styles -->
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
      justify-content: space-between; 
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
    
    /* Badge Styles */
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
    
    /* Buttons */
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
    
    /* Report-specific styles */
    .signature-pad {
      border: 1px dashed var(--border-color);
      border-radius: 4px;
      padding: 15px;
      text-align: center;
      margin: 15px 0;
      cursor: pointer;
    }
    
    .report-preview {
      border: 1px solid var(--border-color);
      padding: 15px;
      height: 300px;
      overflow-y: auto;
      margin-bottom: 15px;
    }
    
    .filter-row {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
      align-items: center;
    }
    
    .filter-item {
      flex: 1;
    }

    /* Responsive */
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
      
      .main-content {
        margin-left: 0 !important;
      }
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal.show {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 4px;
      width: 90%;
      max-width: 800px;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    .modal-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .modal-footer {
      padding: 15px 20px;
      border-top: 1px solid var(--border-color);
      text-align: right;
    }
    
    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }

    /* Form Controls */
    .form-control {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.9rem;
      transition: border-color 0.3s;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-color);
    }

    /* Filter Row Enhancements */
    .filter-row {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
      align-items: flex-end;
      flex-wrap: wrap;
    }

    .filter-item {
      flex: 1;
      min-width: 200px;
    }

    .filter-item label {
      display: block;
      margin-bottom: 5px;
      font-size: 0.9rem;
      color: var(--dark-text);
      font-weight: 500;
    }

    /* Textarea Styles */
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      resize: vertical;
      min-height: 80px;
      font-family: inherit;
    }

    textarea:focus {
      outline: none;
      border-color: var(--primary-color);
    }

    /* Form Group */
    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }

    /* Button Enhancements */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn i {
      font-size: 0.9em;
    }

    /* Card Body Enhancements */
    .card-body {
      padding: 20px;
    }

    .card-body .text-center {
      text-align: center;
    }

    /* Margin Utilities */
    .mb-20 {
      margin-bottom: 20px;
    }

    /* Responsive Adjustments for Filters */
    @media (max-width: 768px) {
      .filter-item {
        min-width: 100%;
      }
      
      .filter-row {
        gap: 10px;
      }
    }

    /* Signature Pad Enhancements */
    .signature-pad {
      border: 1px dashed var(--border-color);
      border-radius: 4px;
      padding: 15px;
      text-align: center;
      margin: 15px 0;
      cursor: pointer;
      background-color: #f9f9f9;
      min-height: 100px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .signature-pad img {
      max-width: 100%;
      margin-bottom: 10px;
    }

    .signature-pad p {
      margin: 0;
      color: #666;
    }

    .user-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 200px;
        z-index: 1000;
    }

    .user-dropdown.show {
        display: block;
    }

    /* Modal backdrop fix */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }

    .modal.show {
        display: flex;
    }

    /* Close button styling */
    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
        padding: 0;
        line-height: 1;
    }

    .close-btn:hover {
        color: #333;
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
        <?php 
        // Profile picture display logic
        $profile_picture_path = $upload_dir . $profile_picture;
        if (!empty($profile_picture) && file_exists($profile_picture_path)): ?>
          <img src="<?php echo $profile_picture_path; ?>" alt="User Profile">
        <?php else: ?>
          <img src="/api/placeholder/32/32" alt="User Profile">
        <?php endif; ?>
        <span><?php echo $user_name; ?> (President)</span>
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
        <li><a href="./PresidentReportsReviewPage.php" class="active"><i class="fas fa-file-signature"></i> <span>Reports Review</span></a></li>
        <li><a href="./PresidentAttendanceOverviewPage.php"><i class="fas fa-clipboard-list"></i> <span>Attendance Overview</span></a></li>
        <li><a href="./PresidentNotificationsPage.php"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
      </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="page-title">
        <h1>Reports Review & Signing</h1>
      </div>
      
      <!-- Filters -->
      <div class="card mb-20">
        <div class="card-header">
          Filter Reports
        </div>
        <div class="card-body">
          <div class="filter-row">
            <div class="filter-item">
              <label>Report Type</label>
              <select class="form-control">
                <option>All Reports</option>
                <option>Monthly Attendance Summary</option>
                <option>Department Performance</option>
                <option>Leave Statistics</option>
                <option>Employee Performance</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Department</label>
              <select class="form-control">
                <option>All Departments</option>
                <option>Human Resources</option>
                <option>IT Department</option>
                <option>Marketing</option>
                <option>Finance</option>
                <option>Operations</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Date Range</label>
              <select class="form-control">
                <option>Last 30 Days</option>
                <option>Last Quarter</option>
                <option>Current Year</option>
                <option>Previous Year</option>
                <option>Custom Range</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Status</label>
              <select class="form-control">
                <option>All</option>
                <option>Pending Review</option>
                <option>Signed</option>
                <option>Archived</option>
              </select>
            </div>
          </div>
          <button class="btn">Apply Filters</button>
        </div>
      </div>
      
      <!-- Pending Reports -->
      <div class="card mb-20">
        <div class="card-header">
          Pending Reports for Review & Signing
          <span class="badge badge-warning"><?php echo count($pending_reports); ?> Pending</span>
        </div>
        <div class="card-body">
          <table>
            <thead>
              <tr>
                <th>Report ID</th>
                <th>Report Title</th>
                <th>Department</th>
                <th>Generated By</th>
                <th>Generated Date</th>
                <th>Priority</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($pending_reports) > 0): ?>
                <?php foreach ($pending_reports as $report): ?>
                  <tr>
                    <td>REP-<?php echo $report['report_id']; ?></td>
                    <td><?php echo htmlspecialchars($report['report_title']); ?></td>
                    <td><?php echo htmlspecialchars($report['department']); ?></td>
                    <td><?php echo htmlspecialchars($report['generated_by']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($report['generated_date'])); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($report['priority']); ?>"><?php echo $report['priority']; ?></span></td>
                    <td>
                      <button class="btn" onclick="openReportModal('REP-<?php echo $report['report_id']; ?>')">Review & Sign</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center">No pending reports found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Recently Signed Reports -->
      <div class="card">
        <div class="card-header">
          Recently Signed Reports
        </div>
        <div class="card-body">
          <table>
            <thead>
              <tr>
                <th>Report ID</th>
                <th>Report Title</th>
                <th>Department</th>
                <th>Signed Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($signed_reports) > 0): ?>
                <?php foreach ($signed_reports as $report): ?>
                  <tr>
                    <td>REP-<?php echo $report['report_id']; ?></td>
                    <td><?php echo htmlspecialchars($report['report_title']); ?></td>
                    <td><?php echo htmlspecialchars($report['department']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($report['signed_date'])); ?></td>
                    <td>
                      <button class="btn btn-secondary" onclick="openReportModal('REP-<?php echo $report['report_id']; ?>', true)">View</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center">No recently signed reports found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Report Review Modal -->
  <div class="modal" id="reportModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Report Review</h2>
        <button class="close-btn" onclick="closeReportModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="report-info">
          <p><strong>Report ID:</strong> <span id="reportId"></span></p>
          <p><strong>Generated By:</strong> <span id="reportGenerator"></span></p>
          <p><strong>Generated Date:</strong> <span id="reportDate"></span></p>
          <p><strong>Department:</strong> <span id="reportDepartment"></span></p>
        </div>
        
        <div class="report-preview">
          <h3 id="reportTitle"></h3>
          
          <div id="reportSummary"></div>
          
          <div id="reportAttachment">
            <p><strong>Attachment:</strong> <a href="#" id="attachmentLink">View Attachment</a></p>
          </div>
        </div>
        
        <div id="signatureSection">
          <h3>Digital Signature</h3>
          <p>By signing this report, you confirm that you have reviewed its contents and approve it for distribution and archival.</p>
          
          <div class="signature-pad" id="signaturePad">
            <img src="/api/placeholder/300/100" alt="Signature Placeholder">
            <p>Click to sign with your digital signature</p>
          </div>
          
          <div class="form-group">
            <label for="comments">Comments (Optional)</label>
            <textarea id="comments" rows="3" placeholder="Add any comments or notes about this report..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="closeReportModal()">Cancel</button>
        <button class="btn btn-secondary" id="signButton">Sign & Approve</button>
      </div>
    </div>
  </div>
  
  <script>
    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        // User profile dropdown functionality
        document.querySelector('.user-profile').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelector('.user-dropdown').classList.toggle('show');
        });

        // Close dropdown when clicking elsewhere
        document.addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.remove('show');
        });

        // Prevent dropdown from closing when clicking inside it
        const dropdown = document.querySelector('.user-dropdown');
        if (dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Modal functionality
        function openReportModal(reportId, viewOnly = false) {
            const modal = document.getElementById('reportModal');
            modal.classList.add('show');
            
            // Find the report details based on reportId
            let reportDetails = null;
            const pendingReports = <?php echo json_encode($pending_reports); ?>;
            const signedReports = <?php echo json_encode($signed_reports); ?>;
            
            // Search in pending reports first
            reportDetails = pendingReports.find(report => `REP-${report.report_id}` === reportId);
            
            // If not found, search in signed reports
            if (!reportDetails) {
                reportDetails = signedReports.find(report => `REP-${report.report_id}` === reportId);
            }
            
            if (reportDetails) {
                // Populate modal with dynamic data
                document.getElementById('reportId').textContent = reportId;
                document.getElementById('reportGenerator').textContent = reportDetails.generated_by;
                document.getElementById('reportDate').textContent = new Date(reportDetails.generated_date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                document.getElementById('reportDepartment').textContent = reportDetails.department;
                document.getElementById('reportTitle').textContent = reportDetails.report_title;
                
                // Set report summary or placeholder
                const summaryDiv = document.getElementById('reportSummary');
                summaryDiv.innerHTML = reportDetails.report_summary 
                    ? `<p><strong>Summary:</strong> ${reportDetails.report_summary}</p>` 
                    : '<p><em>No summary available</em></p>';
                
                // Handle attachment link
                const attachmentLink = document.getElementById('attachmentLink');
                if (reportDetails.attachment_url) {
                    attachmentLink.href = reportDetails.attachment_url;
                    attachmentLink.style.display = 'inline-block';
                } else {
                    attachmentLink.style.display = 'none';
                }
                
                if (viewOnly) {
                    document.getElementById('signatureSection').style.display = 'none';
                    document.getElementById('signButton').style.display = 'none';
                    document.getElementById('modalTitle').textContent = 'View Signed Report';
                } else {
                    document.getElementById('signatureSection').style.display = 'block';
                    document.getElementById('signButton').style.display = 'inline-block';
                    document.getElementById('modalTitle').textContent = 'Report Review & Signing';
                }
            }
        }
        
        // Close modal function
        function closeReportModal() {
            const modal = document.getElementById('reportModal');
            modal.classList.remove('show');
        }
        
        // Close button event listener
        document.querySelector('.close-btn').addEventListener('click', closeReportModal);
        
        // Cancel button event listener
        document.querySelector('.modal-footer button:first-child').addEventListener('click', closeReportModal);
        
        // Sign report functionality
        document.getElementById('signButton').addEventListener('click', function() {
            const reportId = document.getElementById('reportId').textContent.replace('REP-', '');
            const comments = document.getElementById('comments').value;
            
            // AJAX call to sign the report
            fetch('sign_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `report_id=${reportId}&comments=${encodeURIComponent(comments)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Report signed and approved successfully!');
                    closeReportModal();
                    location.reload(); // Reload to reflect changes
                } else {
                    alert('Error signing report: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while signing the report.');
            });
        });
        
        // Make openReportModal available globally
        window.openReportModal = openReportModal;
    });
</script>
</body>
</html>