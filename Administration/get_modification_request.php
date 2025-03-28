<?php
// Start session to verify user is logged in
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Validate request ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

$request_id = intval($_GET['id']);

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

// Get modification request details
$sql = "
    SELECT am.*, u.name, u.username 
    FROM attendance_modifications am
    JOIN users u ON am.user_id = u.id
    WHERE am.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Request not found']);
    exit;
}

$request = $result->fetch_assoc();

// Get attendance record for the date
$sql_attendance = "
    SELECT 
        DATE_FORMAT(clock_in, '%h:%i %p') as clock_in,
        DATE_FORMAT(clock_out, '%h:%i %p') as clock_out
    FROM users_attendance 
    WHERE user_id = ? AND DATE(clock_in) = ?
";

$stmt = $conn->prepare($sql_attendance);
$stmt->bind_param("is", $request['user_id'], $request['modification_date']);
$stmt->execute();
$attendance_result = $stmt->get_result();
$attendance = $attendance_result->num_rows > 0 ? $attendance_result->fetch_assoc() : null;

// Format date for display
$request['modification_date'] = date('F j, Y', strtotime($request['modification_date']));

// Add attendance data if available
$response = $request;
$response['attendance'] = $attendance;

// Return JSON response
echo json_encode($response);

$conn->close();
?>