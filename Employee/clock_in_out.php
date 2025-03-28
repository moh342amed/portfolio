<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get the action type (clock-in or clock-out)
$action = $_POST['action'] ?? null;

if (!in_array($action, ['clock-in', 'clock-out'])) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

if ($action === 'clock-in') {
    // Check if user already clocked in today
    $check_sql = "SELECT id FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $current_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['error' => 'You have already clocked in today']);
        exit;
    }
    
    // Insert clock-in record
    $sql = "INSERT INTO users_attendance (user_id, clock_in) VALUES (?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        // Update user's today_status
        $update_sql = "UPDATE users SET today_status = 'present', clock_in = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Clock-in successful', 'time' => date('H:i:s')]);
    } else {
        echo json_encode(['error' => 'Clock-in failed']);
    }
} else { // clock-out
    // Check if user has clocked in today but not out yet
    $check_sql = "SELECT id FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $current_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['error' => 'You have not clocked in today or already clocked out']);
        exit;
    }
    
    $attendance_id = $check_result->fetch_assoc()['id'];
    
    // Update record with clock-out time
    $sql = "UPDATE users_attendance SET clock_out = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attendance_id);
    
    if ($stmt->execute()) {
        // Update user's today_status
        $update_sql = "UPDATE users SET clock_out = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Clock-out successful', 'time' => date('H:i:s')]);
    } else {
        echo json_encode(['error' => 'Clock-out failed']);
    }
}