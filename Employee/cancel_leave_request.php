<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get leave request ID
$leaveId = $_POST['leave_id'];
$userId = $_SESSION['user_id'];

// Validate input
if (empty($leaveId)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Leave request ID is required']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Get leave request details
    $leaveQuery = $conn->prepare("SELECT leave_type, days, status FROM leave_requests WHERE id = ? AND user_id = ?");
    $leaveQuery->bind_param("ii", $leaveId, $userId);
    $leaveQuery->execute();
    $result = $leaveQuery->get_result();
    
    if ($result->num_rows === 0) {
        // Leave request not found or doesn't belong to this user
        throw new Exception("Leave request not found or unauthorized");
    }
    
    $leave = $result->fetch_assoc();
    
    // Can only cancel pending requests
    if ($leave['status'] !== 'pending') {
        throw new Exception("Cannot cancel a request that has already been " . $leave['status']);
    }
    
    // Update leave request status to 'cancelled'
    $updateLeave = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
    $updateLeave->bind_param("i", $leaveId);
    $updateLeave->execute();
    
    // Restore leave balance (except for unpaid leave)
    if ($leave['leave_type'] !== 'unpaid') {
        $balanceColumn = $leave['leave_type'] . '_leave_balance';
        $updateBalance = $conn->prepare("UPDATE users SET $balanceColumn = $balanceColumn + ? WHERE id = ?");
        $updateBalance->bind_param("ii", $leave['days'], $userId);
        $updateBalance->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>