<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['action'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $data['action'];
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

if ($action === 'clock_in') {
    // Check if already clocked in today
    $sql_check = "SELECT id FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $user_id, $today);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        echo json_encode(['error' => 'You have already clocked in today']);
        exit;
    }
    
    // Record clock in
    $sql = "INSERT INTO users_attendance (user_id, clock_in) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $now);
    
    if ($stmt->execute()) {
        // Update user stats (increment present days if first clock-in of the day)
        $sql_update = "UPDATE users SET present_days = present_days + 1, today_status = 'Clocked In' WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $user_id);
        $stmt_update->execute();
        
        // Check if late (after 9 AM)
        $hour = date('H');
        $minute = date('i');
        if ($hour >= 9 && $minute > 0) {
            $sql_late = "UPDATE users SET late_arrivals = late_arrivals + 1 WHERE id = ?";
            $stmt_late = $conn->prepare($sql_late);
            $stmt_late->bind_param("i", $user_id);
            $stmt_late->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to clock in']);
    }
} else if ($action === 'clock_out') {
    // Check if clocked in today
    $sql_check = "SELECT id FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $user_id, $today);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        echo json_encode(['error' => 'You need to clock in first or you have already clocked out']);
        exit;
    }
    
    $attendance_id = $result_check->fetch_assoc()['id'];
    
    // Record clock out
    $sql = "UPDATE users_attendance SET clock_out = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $now, $attendance_id);
    
    if ($stmt->execute()) {
        // Update user stats
        $sql_update = "UPDATE users SET today_status = 'Clocked Out' WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $user_id);
        $stmt_update->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to clock out']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>