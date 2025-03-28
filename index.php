<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: application/json');

$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed!']);
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];

if (empty($username) || empty($password)) {
    echo json_encode(['error' => 'Both fields are required!']);
    exit;
}

$sql = "SELECT id, username, name, email, role, password FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Invalid username or password!']);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['error' => 'Invalid username or password!']);
    exit;
}

// Prevent session fixation and clear old session data
session_regenerate_id(true);
session_unset();

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role']; 
$_SESSION['name'] = $user['name'];

// Determine redirect based on role
$redirect_url = "";
switch ($user['role']) {
    case 'employee':
        $redirect_url = "./Employee/EmployeeDashboard.php";
        break;
    case 'administration':
        $redirect_url = "./Administration/EmployeeDirectoryPage.php";
        break;
    case 'president':
        $redirect_url = "./President/President-Executive-Dashboard.php";
        break;
    default:
        echo json_encode(['error' => 'Invalid user role!']);
        exit;
}

// Send success response
echo json_encode(['success' => true, 'redirect' => $redirect_url]);
exit;
?>
