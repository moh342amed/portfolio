<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
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

// Fetch admin profile picture
$admin_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_query->bind_param("i", $user_id);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$profile_picture_path = getProfilePicturePath($admin_data['profile_picture'], 'administration');

// Fetch departments for dropdown
$sql_departments = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL";
$result_departments = $conn->query($sql_departments);
$departments = [];
if ($result_departments && $result_departments->num_rows > 0) {
    while($row = $result_departments->fetch_assoc()) {
        if (!empty($row['department'])) {
            $departments[] = $row['department'];
        }
    }
}

// Fetch employees for dropdown
$sql_employees = "SELECT id, name, department FROM users ORDER BY name";
$result_employees = $conn->query($sql_employees);
$employees = [];
if ($result_employees && $result_employees->num_rows > 0) {
    while($row = $result_employees->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Initialize variables for search
$search_name = $_GET['search_name'] ?? '';
$search_department = $_GET['search_department'] ?? '';
$search_penalty_type = $_GET['search_penalty_type'] ?? '';
$search_date_from = $_GET['search_date_from'] ?? '';
$search_date_to = $_GET['search_date_to'] ?? '';

// Initialize SQL query for penalties
$sql_penalties = "SELECT p.*, u.name as employee_name, u.department, u.profile_picture 
                 FROM penalties p 
                 JOIN users u ON p.user_id = u.id 
                 WHERE 1=1";

// Add search conditions if provided
if (!empty($search_name)) {
    $search_name_param = "%" . $search_name . "%";
    $sql_penalties .= " AND u.name LIKE ?";
}

if (!empty($search_department)) {
    $sql_penalties .= " AND u.department = ?";
}

if (!empty($search_penalty_type)) {
    $sql_penalties .= " AND p.penalty_type = ?";
}

if (!empty($search_date_from) && !empty($search_date_to)) {
    $sql_penalties .= " AND p.issue_date BETWEEN ? AND ?";
}

$sql_penalties .= " ORDER BY p.issue_date DESC LIMIT 10";

// Prepare and execute the query
$stmt = $conn->prepare($sql_penalties);

// Bind parameters if they exist
if ($stmt) {
    $bind_types = "";
    $bind_params = [];
    
    if (!empty($search_name)) {
        $bind_types .= "s";
        $bind_params[] = $search_name_param;
    }
    
    if (!empty($search_department)) {
        $bind_types .= "s";
        $bind_params[] = $search_department;
    }
    
    if (!empty($search_penalty_type)) {
        $bind_types .= "s";
        $bind_params[] = $search_penalty_type;
    }
    
    if (!empty($search_date_from) && !empty($search_date_to)) {
        $bind_types .= "ss";
        $bind_params[] = $search_date_from;
        $bind_params[] = $search_date_to;
    }
    
    if (!empty($bind_types)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    
    $stmt->execute();
    $result_penalties = $stmt->get_result();
    $penalties = [];
    
    if ($result_penalties && $result_penalties->num_rows > 0) {
        while($row = $result_penalties->fetch_assoc()) {
            $penalties[] = $row;
        }
    }
} else {
    $penalties = [];
}

// Close connection
$conn->close();

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

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'active':
            return 'badge-warning';
        case 'resolved':
            return 'badge-success';
        case 'revoked':
            return 'badge-danger';
        default:
            return 'badge-warning';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_penalty') {
    // Database connection for form processing
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $employee_id = $_POST['employee_id'] ?? '';
    $penalty_type = $_POST['penalty_type'] ?? '';
    $incident_date = $_POST['incident_date'] ?? '';
    $severity = $_POST['severity'] ?? '';
    $description = $_POST['description'] ?? '';
    $penalty_action = $_POST['penalty_action'] ?? '';
    $notify_employee = isset($_POST['notify_employee']) ? 1 : 0;
    $notify_manager = isset($_POST['notify_manager']) ? 1 : 0;
    
    // Validate required fields
    if (empty($employee_id) || empty($penalty_type) || empty($incident_date) || 
        empty($severity) || empty($description) || empty($penalty_action)) {
        $error_message = "All required fields must be filled out.";
    } else {
        // Insert penalty into database
        $sql = "INSERT INTO penalties (user_id, penalty_type, incident_date, issue_date, severity, 
                description, penalty_action, status, notify_employee, notify_manager, created_by) 
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'active', ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssiis", $employee_id, $penalty_type, $incident_date, 
                        $severity, $description, $penalty_action, $notify_employee, 
                        $notify_manager, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Penalty issued successfully!";
            
            // If notifications are enabled, insert them
            if ($notify_employee) {
                $penalty_id = $stmt->insert_id;
                $title = "New Penalty Issued";
                $message = "You have received a new $severity severity penalty for $penalty_type on $incident_date.";
                
                $notify_sql = "INSERT INTO notifications (user_id, title, message, created_at) 
                              VALUES (?, ?, ?, NOW())";
                $notify_stmt = $conn->prepare($notify_sql);
                $notify_stmt->bind_param("iss", $employee_id, $title, $message);
                $notify_stmt->execute();
            }
            
            // If manager notification is enabled, get manager ID and notify
            if ($notify_manager) {
                $sql_manager = "SELECT manager FROM users WHERE id = ?";
                $stmt_manager = $conn->prepare($sql_manager);
                $stmt_manager->bind_param("i", $employee_id);
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
                            
                            // Get employee name
                            $sql_employee = "SELECT name FROM users WHERE id = ?";
                            $stmt_employee = $conn->prepare($sql_employee);
                            $stmt_employee->bind_param("i", $employee_id);
                            $stmt_employee->execute();
                            $result_employee = $stmt_employee->get_result();
                            $employee_row = $result_employee->fetch_assoc();
                            $employee_name = $employee_row['name'];
                            
                            // Create notification for manager
                            $title = "Penalty Issued To Team Member";
                            $message = "A $severity severity penalty has been issued to $employee_name for $penalty_type on $incident_date.";
                            
                            $notify_sql = "INSERT INTO notifications (user_id, title, message, created_at) 
                                          VALUES (?, ?, ?, NOW())";
                            $notify_stmt = $conn->prepare($notify_sql);
                            $notify_stmt->bind_param("iss", $manager_id, $title, $message);
                            $notify_stmt->execute();
                        }
                    }
                }
            }
            
            // Redirect to avoid form resubmission
            header("Location: PenaltyManagementPage.php?success=1");
            exit;
        } else {
            $error_message = "Error issuing penalty: " . $conn->error;
        }
    }
    
    $conn->close();
}

// Check for success message from redirect
$success_message = isset($_GET['success']) && $_GET['success'] == 1 ? "Penalty issued successfully!" : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance System - Penalty Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
    
    /* Penalty Specific Styles */
    .penalty-table .penalty-actions {
      display: flex;
      gap: 5px;
    }
    
    .penalty-table .btn {
      padding: 5px 10px;
      font-size: 0.8rem;
    }
    
    .penalty-severity {
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 0.8rem;
    }
    
    .severity-low {
      background-color: rgba(46, 204, 113, 0.2);
    }
    
    .severity-medium {
      background-color: rgba(243, 156, 18, 0.2);
    }
    
    .severity-high {
      background-color: rgba(231, 76, 60, 0.2);
    }
    
    .search-filters {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .search-filters .form-group {
      flex: 1;
      min-width: 200px;
    }
    
    /* Tab System */
    .tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }
    
    .tab {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }
    
    .tab.active {
      border-bottom-color: var(--primary-color);
      font-weight: bold;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Responsive Adjustments */
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
    }


        .penalty-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 15px;
    }

    .penalty-table thead {
        background-color: var(--light-bg);
    }

    .penalty-table th, 
    .penalty-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .penalty-table th {
        font-weight: 600;
        color: var(--dark-text);
        text-transform: uppercase;
        font-size: 0.9rem;
    }

    .penalty-table tr:hover {
        background-color: rgba(52, 152, 219, 0.05);
    }

    .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .employee-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
    }

    /* Enhanced Badge and Severity Styles */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-warning {
        background-color: rgba(241, 196, 15, 0.2);
        color: #d35400;
    }

    .badge-success {
        background-color: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .badge-danger {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    .penalty-severity {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .severity-low {
        background-color: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .severity-medium {
        background-color: rgba(241, 196, 15, 0.2);
        color: #d35400;
    }

    .severity-high {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    /* Penalty Actions Improvements */
    .penalty-actions {
        display: flex;
        gap: 8px;
    }

    .penalty-actions .btn {
        padding: 6px 10px;
        font-size: 0.8rem;
    }

    /* Empty State Styling */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        background-color: var(--light-bg);
        border-radius: 4px;
    }

    .empty-state i {
        color: var(--border-color);
        margin-bottom: 15px;
    }

    .empty-state p {
        color: var(--dark-text);
        opacity: 0.7;
    }

    /* Alert Styling */
    .alert {
        padding: 12px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
    }

    .alert-success {
        background-color: rgba(46, 204, 113, 0.1);
        border-left: 4px solid var(--secondary-color);
        color: #27ae60;
    }

    .alert-danger {
        background-color: rgba(231, 76, 60, 0.1);
        border-left: 4px solid var(--danger-color);
        color: #c0392b;
    }

    /* Responsive Table Adjustments */
    @media (max-width: 768px) {
        .penalty-table {
            display: block;
            overflow-x: auto;
        }

        .penalty-table thead {
            display: none;
        }

        .penalty-table tbody tr {
            display: block;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .penalty-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--light-bg);
            padding: 10px;
        }

        .penalty-table tbody td::before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
        }

        .penalty-actions {
            flex-direction: column;
        }
    }


    .penalty-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 15px;
    }

    .penalty-table thead {
        background-color: var(--light-bg);
    }

    .penalty-table th, 
    .penalty-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .penalty-table th {
        font-weight: 600;
        color: var(--dark-text);
        text-transform: uppercase;
        font-size: 0.9rem;
    }

    .penalty-table tr:hover {
        background-color: rgba(52, 152, 219, 0.05);
    }

    .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .employee-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
    }

    /* Enhanced Badge and Severity Styles */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-warning {
        background-color: rgba(241, 196, 15, 0.2);
        color: #d35400;
    }

    .badge-success {
        background-color: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .badge-danger {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    .penalty-severity {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .severity-low {
        background-color: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .severity-medium {
        background-color: rgba(241, 196, 15, 0.2);
        color: #d35400;
    }

    .severity-high {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    /* Penalty Actions Improvements */
    .penalty-actions {
        display: flex;
        gap: 8px;
    }

    .penalty-actions .btn {
        padding: 6px 10px;
        font-size: 0.8rem;
    }

    /* Empty State Styling */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        background-color: var(--light-bg);
        border-radius: 4px;
    }

    .empty-state i {
        color: var(--border-color);
        margin-bottom: 15px;
    }

    .empty-state p {
        color: var(--dark-text);
        opacity: 0.7;
    }

    /* Alert Styling */
    .alert {
        padding: 12px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
    }

    .alert-success {
        background-color: rgba(46, 204, 113, 0.1);
        border-left: 4px solid var(--secondary-color);
        color: #27ae60;
    }

    .alert-danger {
        background-color: rgba(231, 76, 60, 0.1);
        border-left: 4px solid var(--danger-color);
        color: #c0392b;
    }

    /* Responsive Table Adjustments */
    @media (max-width: 768px) {
        .penalty-table {
            display: block;
            overflow-x: auto;
        }

        .penalty-table thead {
            display: none;
        }

        .penalty-table tbody tr {
            display: block;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .penalty-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--light-bg);
            padding: 10px;
        }

        .penalty-table tbody td::before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
        }

        .penalty-actions {
            flex-direction: column;
        }
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">AttendX</div>
    <div class="app-title">Penalty Management</div>
    <div class="user-profile" id="userProfileBtn">
      <img src="<?= htmlspecialchars($profile_picture_path) ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
      <span><?php echo htmlspecialchars($user_name); ?></span>
      <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
    </div>
    <div class="user-dropdown" id="userDropdown">
      <ul>
        <li><a href="#"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
        <li><a href="./EmployeeDirectoryPage.php"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
        <li><a href="./AdminLeaveRequestManagementPage.php"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
        <li><a href="./AttendanceModificationManagementPage.php"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
        <li><a href="./PenaltyManagementPage.php" class="active"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
        <li><a href="./ReportsGenerationPage.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
      </ul>
    </aside>
    
    <main class="main-content">
      <div class="page-title">
        <h1>Penalty Management</h1>
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
      
      <div class="tabs">
        <div class="tab <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'penalties' ? 'active' : ''; ?>" data-tab="penalties">Penalty History</div>
        <div class="tab <?php echo isset($_GET['tab']) && $_GET['tab'] === 'new-penalty' ? 'active' : ''; ?>" data-tab="new-penalty">Issue New Penalty</div>
      </div>
      
      <div id="penalties-tab" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'penalties' ? 'active' : ''; ?>">
        <div class="card">
          <div class="card-header">
            <div>Search Penalties</div>
          </div>
          <div class="card-body">
            <form method="GET" action="PenaltyManagementPage.php">
              <input type="hidden" name="tab" value="penalties">
              <div class="search-filters">
                <div class="form-group">
                  <label>Employee Name</label>
                  <input type="text" name="search_name" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
                <div class="form-group">
                  <label>Department</label>
                  <select name="search_department">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $search_department === $department ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($department); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Penalty Type</label>
                  <select name="search_penalty_type">
                    <option value="">All Types</option>
                    <option value="late_arrival" <?php echo $search_penalty_type === 'late_arrival' ? 'selected' : ''; ?>>Late Arrival</option>
                    <option value="early_departure" <?php echo $search_penalty_type === 'early_departure' ? 'selected' : ''; ?>>Early Departure</option>
                    <option value="unauth_absence" <?php echo $search_penalty_type === 'unauth_absence' ? 'selected' : ''; ?>>Unauthorized Absence</option>
                    <option value="performance" <?php echo $search_penalty_type === 'performance' ? 'selected' : ''; ?>>Performance Issue</option>
                    <option value="policy_violation" <?php echo $search_penalty_type === 'policy_violation' ? 'selected' : ''; ?>>Policy Violation</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                  <label>Date Range</label>
                  <div style="display: flex; gap: 10px;">
                    <input type="date" name="search_date_from" style="flex: 1;" value="<?php echo htmlspecialchars($search_date_from); ?>">
                    <input type="date" name="search_date_to" style="flex: 1;" value="<?php echo htmlspecialchars($search_date_to); ?>">
                  </div>
                </div>
              <button type="submit" class="btn mt-10">Search</button>
            </form>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header">
            <div>Penalty Records</div>
          </div>
          <div class="card-body">
            <?php if (empty($penalties)): ?>
            <div class="empty-state">
              <i class="fas fa-search" style="font-size: 48px; color: #ccc;"></i>
              <p>No penalties found. Try adjusting your search criteria or issue new penalties.</p>
            </div>
            <?php else: ?>
            <table class="penalty-table">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Penalty Type</th>
                  <th>Issue Date</th>
                  <th>Severity</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <table class="penalty-table">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Penalty Type</th>
                    <th>Issue Date</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($penalties as $penalty): 
                    $emp_profile_path = getProfilePicturePath($penalty['profile_picture']); 
                  ?>
                  <tr>
                    <td>
                      <div class="employee-info">
                        <img src="<?= htmlspecialchars($emp_profile_path) ?>" 
                            alt="<?php echo htmlspecialchars($penalty['employee_name']); ?>" 
                            class="employee-avatar"
                            onerror="this.src='/projectweb/Employee/uploads/profile_pictures/default-profile.png'">
                        <?php echo htmlspecialchars($penalty['employee_name']); ?>
                      </div>
                    </td>
                  <td><?php echo htmlspecialchars($penalty['employee_name']); ?></td>
                  <td><?php echo htmlspecialchars($penalty['department']); ?></td>
                  <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($penalty['penalty_type']))); ?></td>
                  <td><?php echo htmlspecialchars($penalty['issue_date']); ?></td>
                  <td><span class="penalty-severity <?php echo getSeverityClass($penalty['severity']); ?>"><?php echo ucfirst(htmlspecialchars($penalty['severity'])); ?></span></td>
                  <td><span class="badge <?php echo getStatusBadgeClass($penalty['status']); ?>"><?php echo ucfirst(htmlspecialchars($penalty['status'])); ?></span></td>
                  <td class="penalty-actions">
                    <a href="ViewPenalty.php?id=<?php echo $penalty['id']; ?>" class="btn">View</a>
                    <?php if ($penalty['status'] === 'active'): ?>
                    <a href="EditPenalty.php?id=<?php echo $penalty['id']; ?>" class="btn btn-secondary">Edit</a>
                    <a href="RevokePenalty.php?id=<?php echo $penalty['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to revoke this penalty?')">Revoke</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            
            <div class="text-center mt-20">
              <a href="?page=2&tab=penalties" class="btn">Load More</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div id="new-penalty-tab" class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] === 'new-penalty' ? 'active' : ''; ?>">
        <div class="card">
          <div class="card-header">
            <div>Issue New Penalty</div>
          </div>
          <div class="card-body">
            <form method="POST" action="PenaltyManagementPage.php?tab=new-penalty" enctype="multipart/form-data">
              <input type="hidden" name="action" value="issue_penalty">
              <div class="grid">
                <div class="col-6">
                  <div class="form-group">
                    <label>Employee*</label>
                    <select name="employee_id" required>
                      <option value="">Select Employee</option>
                      <?php foreach($employees as $employee): ?>
                      <option value="<?php echo $employee['id']; ?>">
                        <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department'] ?? 'No Department'); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Penalty Type*</label>
                    <select name="penalty_type" required>
                      <option value="">Select Penalty Type</option>
                      <option value="late_arrival">Late Arrival</option>
                      <option value="early_departure">Early Departure</option>
                      <option value="unauth_absence">Unauthorized Absence</option>
                      <option value="performance">Performance Issue</option>
                      <option value="policy_violation">Policy Violation</option>
                    </select>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Date of Incident*</label>
                    <input type="date" name="incident_date" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Severity*</label>
                    <select name="severity" required>
                      <option value="">Select Severity</option>
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label>Description*</label>
                    <textarea name="description" rows="5" placeholder="Describe the incident and reason for penalty..." required></textarea>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label>Penalty Action*</label>
                    <select name="penalty_action" required>
                      <option value="">Select Action</option>
                      <option value="warning">Warning</option>
                      <option value="formal_warning">Formal Warning</option>
                      <option value="final_warning">Final Warning</option>
                      <option value="suspension">Suspension</option>
                      <option value="pay_deduction">Pay Deduction</option>
                    </select>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label>Documents</label>
                    <input type="file" name="attachment">
                    <small>Optional: Attach any supporting documents (max 5MB)</small>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label>Send Notification</label>
                    <div>
                      <input type="checkbox" id="notifyEmployee" name="notify_employee" checked>
                      <label for="notifyEmployee" style="display: inline;">Notify Employee</label>
                    </div>
                    <div>
                      <input type="checkbox" id="notifyManager" name="notify_manager" checked>
                      <label for="notifyManager" style="display: inline;">Notify Department Manager</label>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="text-right mt-20">
                <a href="PenaltyManagementPage.php" class="btn" style="margin-right: 10px;">Cancel</a>
                <button type="submit" class="btn btn-danger">Issue Penalty</button>
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
    
    // Tab functionality
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
      tab.addEventListener('click', function() {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
          content.classList.remove('active');
        });
        
        // Deactivate all tabs
        document.querySelectorAll('.tab').forEach(t => {
          t.classList.remove('active');
        });
        
        // Activate clicked tab and its content
        this.classList.add('active');
        document.getElementById(this.dataset.tab + '-tab').classList.add('active');
        
        // Update URL without reloading page
        const url = new URL(window.location);
        url.searchParams.set('tab', this.dataset.tab);
        window.history.pushState({}, '', url);
      });
    });
    
    // Mobile responsiveness
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
    
    // Show success message temporarily
    <?php if(!empty($success_message)): ?>
    setTimeout(function() {
      document.querySelector('.alert-success').style.display = 'none';
    }, 5000);
    <?php endif; ?>
  </script>
</body>
</html>