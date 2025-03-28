<?php
session_start();

// Enhanced security and error handling
header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Unknown error occurred'
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "attendance_management";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Sanitize and validate inputs
    $user_id = $_SESSION['user_id'];
    $modification_date = $conn->real_escape_string($_POST['modification_date']);
    $modification_type = $conn->real_escape_string($_POST['modification_type']);
    $modification_time = isset($_POST['modification_time']) ? $conn->real_escape_string($_POST['modification_time']) : null;
    $modification_reason = $conn->real_escape_string($_POST['modification_reason']);

    // Input validation
    if (empty($modification_date) || empty($modification_type) || empty($modification_reason)) {
        throw new Exception('All required fields must be filled');
    }

    // Check for existing pending requests
    $check_sql = "SELECT id FROM attendance_modifications WHERE user_id = ? AND modification_date = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $modification_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        throw new Exception('A pending modification request already exists for this date');
    }

    // Insert modification request with prepared statement
    $sql = "INSERT INTO attendance_modifications (
        user_id, 
        modification_date, 
        modification_type, 
        modification_time, 
        reason, 
        status, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issss", 
        $user_id, 
        $modification_date, 
        $modification_type, 
        $modification_time, 
        $modification_reason
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to submit modification request: ' . $stmt->error);
    }

    // Create notification for admin/manager
    $title = "Attendance Modification Request";
    $message = "New modification request from " . $_SESSION['name'] . " on " . $modification_date;
    
    $admin_id = 1; // Replace with actual admin/manager logic
    $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at, read) VALUES (?, ?, ?, NOW(), 0)";
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->bind_param("iss", $admin_id, $title, $message);
    $notification_stmt->execute();

    $response = [
        'status' => 'success',
        'message' => 'Modification request submitted successfully'
    ];

} catch (Exception $e) {
    // Log error for server-side tracking
    error_log("Modification Request Error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
} finally {
    // Close database connection if it exists
    if (isset($conn)) {
        $conn->close();
    }
}

// Return JSON response
echo json_encode($response);
exit;
?>