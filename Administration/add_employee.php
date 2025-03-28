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

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $phone = $_POST['phone'] ?? '';
    $join_date = $_POST['join_date'] ?? NULL;
    
    // Handle department selection
    if ($_POST['department'] === 'other' && !empty($_POST['newDepartment'])) {
        $department = $_POST['newDepartment'];
    } else if (!empty($_POST['department'])) {
        $department = $_POST['department'];
    } else {
        $department = '';
    }
    
    // Validate input
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Required fields cannot be empty!";
        header("Location: EmployeeDirectoryPage.php");
        exit;
    }
    
    // Validate phone number format
    if (!empty($phone) && !preg_match("/^[0-9]{10}$/", $phone)) {
        $_SESSION['error_message'] = "Invalid phone number format!";
        header("Location: EmployeeDirectoryPage.php");
        exit;
    }
    
    // Validate join date
    if (!empty($join_date) && !strtotime($join_date)) {
        $_SESSION['error_message'] = "Invalid join date!";
        header("Location: EmployeeDirectoryPage.php");
        exit;
    }
    
    // Check if username or email already exists
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Username or email already exists!";
        header("Location: EmployeeDirectoryPage.php");
        exit;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Set default leave balances
    $annual_leave = 20;
    $sick_leave = 10;
    $personal_leave = 5;
    $unpaid_leave = 365;
    
    // Insert new employee
    $sql = "INSERT INTO users (name, username, email, password, role, phone, department, join_date, 
            annual_leave_balance, sick_leave_balance, personal_leave_balance, unpaid_leave_balance) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssiiii", $name, $username, $email, $hashed_password, $role, 
                     $phone, $department, $join_date, $annual_leave, $sick_leave, 
                     $personal_leave, $unpaid_leave);
    
    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        
        // Create notification for new employee addition
        $notification_title = "New Employee Added";
        $notification_message = "A new employee, {$name}, has been added to the system.";
        $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at) VALUES (0, ?, ?, NOW())";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("ss", $notification_title, $notification_message);
        $notification_stmt->execute();
        
        $_SESSION['success_message'] = "Employee added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding employee: " . $stmt->error;
    }
    
    header("Location: EmployeeDirectoryPage.php");
    exit;
}

// If not a POST request, redirect back to employee directory
header("Location: EmployeeDirectoryPage.php");
exit;
?>