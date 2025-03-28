<?php
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

$name = $_POST['name'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$role = $_POST['role'];

if (empty($name) || empty($username) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['error' => 'All fields are required!']);
    exit;
}

// Check if username or email already exists
$sql = "SELECT * FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['error' => 'Username or email already exists!']);
    exit;
}

$password = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $name, $username, $email, $password, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Registration successful! Redirecting to login page...']);
} else {
    echo json_encode(['error' => 'Registration failed!']);
}

$stmt->close();
$conn->close();
?>